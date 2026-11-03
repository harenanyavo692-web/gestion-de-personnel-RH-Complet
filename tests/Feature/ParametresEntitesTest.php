<?php

namespace Tests\Feature;

use App\Models\AccountingEntity;
use App\Models\AccountingExercise;
use App\Models\CategorieFinanciere;
use App\Models\Employe;
use App\Models\OrdreDepense;
use App\Models\OrganizationEntity;
use App\Models\Permission;
use App\Models\Recette;
use App\Models\ReferenceItem;
use App\Models\Role;
use App\Models\User;
use App\Services\ParametreService;
use App\Support\Fmt;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParametresEntitesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'actif' => true]);
    }

    private function employe(array $extra = []): Employe
    {
        return Employe::create($extra + [
            'matricule' => 'M' . uniqid(), 'nom' => 'Rakoto', 'prenom' => 'Jean', 'email' => uniqid() . '@t.mg', 'poste' => 'Agent',
            'date_embauche' => today(), 'statut' => 'actif',
        ]);
    }

    // ---------- Accès et navigation ----------

    public function test_les_quatre_pages_sont_accessibles_et_dans_la_sidebar(): void
    {
        $this->actingAs($this->user('rh'))->get('/rh/parametres')->assertOk()->assertSee('Paramètres RH');
        $this->get('/rh/entites')->assertOk()->assertSee('Entités RH');
        $this->get('/rh/entites?vue=arbre')->assertOk();

        foreach (['general', 'plan', 'journaux', 'categories', 'paiements', 'exercices', 'securite'] as $s) {
            $this->actingAs($this->user('direction'))->get("/comptabilite/parametres?section=$s")->assertOk();
        }
        $this->get('/comptabilite/entites')->assertOk()->assertSee('Entités comptables')->assertSee('Paramètres comptabilité');
        foreach (['general', 'personnel', 'organisation', 'contrats', 'conges', 'workflow'] as $s) {
            $this->actingAs($this->user('rh'))->get("/rh/parametres?section=$s")->assertOk();
        }
    }

    public function test_les_non_autorises_ne_modifient_rien(): void
    {
        $this->actingAs($this->user('comptable'))->get('/rh/parametres')->assertForbidden();
        $this->get('/rh/entites')->assertForbidden();
        $this->put('/comptabilite/parametres', ['section' => 'securite'])->assertForbidden();
        $this->actingAs($this->user('rh'))->post('/comptabilite/entites', [])->assertForbidden();

        // Le RAF consulte les paramètres comptables mais ne les modifie pas.
        $this->actingAs($this->user('raf'))->get('/comptabilite/parametres')->assertOk();
        $this->put('/comptabilite/parametres', ['section' => 'securite', 'seuil_double_validation' => 1, 'alerte_budget_pct' => 50])->assertForbidden();
        $this->post('/comptabilite/parametres/exercices', [])->assertForbidden();
    }

    // ---------- Paramètres RH ----------

    public function test_les_parametres_rh_persistent_apres_rechargement(): void
    {
        $this->actingAs($this->user('rh'));
        $this->put('/rh/parametres', ['section' => 'general', 'organisation' => 'CDPHM Test', 'lignes_par_page' => 25, 'email' => 'x@cdphm.mg'])->assertRedirect();
        $this->put('/rh/parametres', ['section' => 'workflow', 'validateur' => 'direction', 'delai_reponse_jours' => 7, 'validation_requise' => 1])->assertRedirect();

        $this->get('/rh/parametres?section=general')->assertSee('CDPHM Test');
        $p = app(ParametreService::class);
        $this->assertSame(25, $p->get('rh.general')['lignes_par_page']);
        $this->assertSame('direction', $p->get('rh.workflow')['validateur']);
        $this->put('/rh/parametres', ['section' => 'general', 'lignes_par_page' => 25])->assertSessionHasErrors('organisation');
    }

    public function test_les_listes_de_reference_refusent_les_codes_en_double(): void
    {
        $this->actingAs($this->user('rh'));
        $this->post('/references', ['groupe' => 'type_contrat', 'code' => 'apprenti', 'libelle' => 'Apprentissage'])->assertRedirect();
        $this->assertDatabaseHas('reference_items', ['groupe' => 'type_contrat', 'code' => 'APPRENTI']);
        $this->post('/references', ['groupe' => 'type_contrat', 'code' => 'APPRENTI', 'libelle' => 'Autre'])->assertSessionHasErrors('code');

        // L'absence garde ses règles (jours, justificatif) ; un groupe hors domaine est refusé.
        $this->post('/references', ['groupe' => 'type_absence', 'code' => 'FORM', 'libelle' => 'Formation', 'jours_annuels' => 5, 'justificatif' => 1]);
        $this->assertSame(5, ReferenceItem::where('code', 'FORM')->first()->meta['jours_annuels']);
        $this->post('/references', ['groupe' => 'mode_paiement', 'code' => 'TROC', 'libelle' => 'Troc'])->assertForbidden();

        // Désactivation (pas de suppression).
        $item = ReferenceItem::where('code', 'APPRENTI')->first();
        $this->put("/references/{$item->id}", ['libelle' => 'Apprentissage'])->assertRedirect();
        $this->assertFalse($item->fresh()->actif);
    }

    // ---------- Entités RH ----------

    public function test_entites_rh_codes_uniques_et_hierarchie_sans_cycle(): void
    {
        $this->actingAs($this->user('rh'));
        $this->post('/rh/entites', ['code' => 'dir-gen', 'nom' => 'Direction générale', 'type' => 'direction', 'actif' => 1])->assertRedirect();
        $dir = OrganizationEntity::where('code', 'DIR-GEN')->first();
        $this->post('/rh/entites', ['code' => 'DIR-GEN', 'nom' => 'Doublon', 'type' => 'service'])->assertSessionHasErrors('code');

        $this->post('/rh/entites', ['code' => 'SRV-A', 'nom' => 'Service A', 'type' => 'service', 'parent_id' => $dir->id, 'actif' => 1]);
        $srv = OrganizationEntity::where('code', 'SRV-A')->first();

        $this->put("/rh/entites/{$dir->id}", ['code' => 'DIR-GEN', 'nom' => 'D', 'type' => 'direction', 'parent_id' => $dir->id, 'actif' => 1])->assertSessionHasErrors('parent_id');
        $this->put("/rh/entites/{$dir->id}", ['code' => 'DIR-GEN', 'nom' => 'D', 'type' => 'direction', 'parent_id' => $srv->id, 'actif' => 1])->assertSessionHasErrors('parent_id');
        $this->assertNull($dir->fresh()->parent_id);

        // Désactiver un parent dont une fille est active est refusé.
        $this->put("/rh/entites/{$dir->id}", ['code' => 'DIR-GEN', 'nom' => 'D', 'type' => 'direction'])->assertSessionHasErrors('actif');
        $this->get('/rh/entites?vue=arbre')->assertOk()->assertSee('Service A');
        $this->get('/rh/entites?q=SRV&type=service&statut=actif')->assertOk()->assertSee('SRV-A')->assertDontSee('DIR-GEN');
    }

    public function test_une_entite_avec_employes_ne_se_supprime_pas_et_le_renommage_suit(): void
    {
        $this->actingAs($this->user('rh'));
        $e = OrganizationEntity::where('code', 'CPT')->first();
        $emp = $this->employe(['entity_id' => $e->id, 'departement' => $e->nom]);

        $this->delete("/rh/entites/{$e->id}")->assertSessionHasErrors('entite');
        $this->assertNotNull($e->fresh());

        $this->put("/rh/entites/{$e->id}", ['code' => 'CPT', 'nom' => 'Comptabilité générale', 'type' => 'departement', 'actif' => 1])->assertRedirect();
        $this->assertSame('Comptabilité générale', $emp->fresh()->departement);

        // Désactivée : l'employé garde son rattachement historique.
        $this->put("/rh/entites/{$e->id}", ['code' => 'CPT', 'nom' => 'Comptabilité générale', 'type' => 'departement'])->assertRedirect();
        $this->assertSame($e->id, $emp->fresh()->entity_id);
    }

    public function test_un_employe_se_rattache_a_une_entite_active(): void
    {
        $this->actingAs($this->user('rh'));
        $actif = OrganizationEntity::where('code', 'CHF')->first();
        $inactif = OrganizationEntity::create(['code' => 'OLD', 'nom' => 'Ancien service', 'type' => 'service', 'actif' => false]);
        $base = ['prenom' => 'A', 'nom' => 'B', 'email' => 'ab@t.mg', 'poste' => 'Agent', 'date_embauche' => '2026-01-01', 'statut' => 'actif'];

        $this->post('/employes', $base + ['entity_id' => $inactif->id])->assertSessionHasErrors('entity_id');
        $this->post('/employes', $base + ['entity_id' => $actif->id])->assertRedirect();
        $this->assertDatabaseHas('employes', ['email' => 'ab@t.mg', 'entity_id' => $actif->id, 'departement' => 'Chambre froide']);
        $this->get('/employes?entity_id=' . $actif->id)->assertOk()->assertSee('ab@t.mg');
    }

    // ---------- Paramètres comptabilité ----------

    public function test_devise_et_prefixes_sont_appliques(): void
    {
        $this->actingAs($this->user('direction'));
        $this->assertSame('1 500 Ar', Fmt::ar(1500));

        $this->put('/comptabilite/parametres', [
            'section' => 'general', 'devise_code' => 'eur', 'devise_symbole' => '€', 'decimales' => 2,
            'prefixes' => ['REC' => 'rcp', 'OD' => 'ODP', 'REG' => 'REG', 'CS' => 'CS', 'LOT' => 'LOT', 'SP' => 'SP', 'TR' => 'TR'],
        ])->assertRedirect();

        $this->assertSame('1 500,00 €', Fmt::ar(1500));
        $this->assertStringStartsWith('RCP-', Fmt::reference('REC'));
        $this->put('/comptabilite/parametres', ['section' => 'general', 'devise_code' => 'MGA', 'devise_symbole' => 'Ar', 'decimales' => 0, 'prefixes' => ['REC' => 'a b']])
            ->assertSessionHasErrors('prefixes.REC');
    }

    public function test_comptes_et_journaux_uniques_et_desactivables(): void
    {
        $this->actingAs($this->user('direction'));
        $this->post('/comptabilite/parametres/comptes', ['numero' => '701', 'libelle' => 'Prestations', 'type' => 'produit'])->assertRedirect();
        $this->assertDatabaseHas('accounting_accounts', ['numero' => '701', 'classe' => 7]);
        $this->post('/comptabilite/parametres/comptes', ['numero' => '701', 'libelle' => 'Doublon', 'type' => 'produit'])->assertSessionHasErrors('numero');
        $this->post('/comptabilite/parametres/comptes', ['numero' => 'AB1', 'libelle' => 'X', 'type' => 'produit'])->assertSessionHasErrors('numero');
        $this->post('/comptabilite/parametres/journaux', ['code' => 'CAI', 'libelle' => 'Doublon', 'type' => 'caisse'])->assertSessionHasErrors('code');

        $cat = CategorieFinanciere::where('nom', 'Transport')->where('type', 'recette')->first();
        $this->put("/comptabilite/parametres/categories/{$cat->id}", [])->assertSessionHasErrors('categorie'); // catégorie automatique
        $this->post('/comptabilite/parametres/categories', ['type' => 'depense', 'nom' => 'Formation'])->assertRedirect();
    }

    public function test_exercices_pas_de_chevauchement_et_cloture_bloque_les_operations(): void
    {
        $dir = $this->user('direction');
        $this->actingAs($dir);
        $annee = today()->year;
        $this->post('/comptabilite/parametres/exercices', ['code' => "EX$annee", 'libelle' => 'Exercice', 'date_debut' => "$annee-01-01", 'date_fin' => "$annee-12-31"])->assertRedirect();
        $this->post('/comptabilite/parametres/exercices', ['code' => 'CHEV', 'libelle' => 'Chevauche', 'date_debut' => "$annee-06-01", 'date_fin' => ($annee + 1) . '-05-31'])->assertSessionHasErrors('date_debut');

        $ex = AccountingExercise::first();
        $cat = CategorieFinanciere::recettes()->first();
        $payload = ['date_recette' => today()->toDateString(), 'montant' => 1000, 'categorie_id' => $cat->id, 'origine' => 'Test'];
        $compta = $this->user('comptable');

        // Une dépense en attente empêche la clôture
        $od = OrdreDepense::create(['reference' => 'OD1', 'date_depense' => today(), 'motif' => 'x', 'montant' => 10, 'categorie_id' => CategorieFinanciere::depenses()->first()->id, 'soumis_par' => $compta->id]);
        $this->actingAs($dir)->post("/comptabilite/parametres/exercices/{$ex->id}/cloturer")->assertSessionHasErrors('exercice');
        $od->update(['statut' => 'validee']);
        $this->post("/comptabilite/parametres/exercices/{$ex->id}/cloturer")->assertRedirect();

        $this->actingAs($compta)->post('/comptabilite/recettes', $payload)->assertSessionHasErrors('date_recette');
        $this->assertSame(0, Recette::count());

        $this->actingAs($dir)->post("/comptabilite/parametres/exercices/{$ex->id}/rouvrir")->assertRedirect();
        $this->actingAs($compta)->post('/comptabilite/recettes', $payload)->assertRedirect();
        $this->assertSame(1, Recette::count());
    }

    public function test_double_validation_au_dessus_du_seuil(): void
    {
        $compta = $this->user('comptable');
        $cat = CategorieFinanciere::depenses()->first()->id;
        $gros = OrdreDepense::create(['reference' => 'G', 'date_depense' => today(), 'motif' => 'gros', 'montant' => 5000000, 'categorie_id' => $cat, 'soumis_par' => $compta->id]);
        $petit = OrdreDepense::create(['reference' => 'P', 'date_depense' => today(), 'motif' => 'petit', 'montant' => 100000, 'categorie_id' => $cat, 'soumis_par' => $compta->id]);

        $this->actingAs($this->user('direction'))->put('/comptabilite/parametres', ['section' => 'securite', 'seuil_double_validation' => 1000000, 'alerte_budget_pct' => 80, 'bloquer_exercice_cloture' => 1])->assertRedirect();

        $this->actingAs($this->user('raf'))->post("/raf/validations/{$gros->id}", ['decision' => 'valider'])->assertForbidden();
        $this->post("/raf/validations/{$petit->id}", ['decision' => 'valider'])->assertRedirect();
        $this->actingAs($this->user('direction'))->post("/raf/validations/{$gros->id}", ['decision' => 'valider'])->assertRedirect();
        $this->assertSame('validee', $gros->fresh()->statut);
    }

    // ---------- Entités comptables et budgets ----------

    public function test_entites_comptables_regles_de_gestion(): void
    {
        $this->actingAs($this->user('direction'));
        $ex = AccountingExercise::create(['code' => 'EX', 'libelle' => 'EX', 'date_debut' => today()->startOfYear(), 'date_fin' => today()->endOfYear()]);
        $rh = OrganizationEntity::where('code', 'CHF')->first();

        $this->post('/comptabilite/entites', ['code' => 'cc-froid', 'nom' => 'Froid', 'type' => 'centre_cout', 'organization_entity_id' => $rh->id, 'exercise_id' => $ex->id, 'budget_reference' => 1000000, 'actif' => 1])->assertRedirect();
        $parent = AccountingEntity::where('code', 'CC-FROID')->first();
        $this->post('/comptabilite/entites', ['code' => 'CC-FROID', 'nom' => 'Doublon', 'type' => 'projet'])->assertSessionHasErrors('code');
        $this->post('/comptabilite/entites', ['code' => 'SANS-EX', 'nom' => 'X', 'type' => 'projet', 'budget_reference' => 100])->assertSessionHasErrors('exercise_id');

        // Les budgets des filles ne dépassent pas celui de la parente
        $this->post('/comptabilite/entites', ['code' => 'F1', 'nom' => 'F1', 'type' => 'projet', 'parent_id' => $parent->id, 'exercise_id' => $ex->id, 'budget_reference' => 700000, 'actif' => 1])->assertRedirect();
        $this->post('/comptabilite/entites', ['code' => 'F2', 'nom' => 'F2', 'type' => 'projet', 'parent_id' => $parent->id, 'exercise_id' => $ex->id, 'budget_reference' => 400000, 'actif' => 1])->assertSessionHasErrors('budget_reference');

        // Pas de cycle
        $f1 = AccountingEntity::where('code', 'F1')->first();
        $this->put("/comptabilite/entites/{$parent->id}", ['code' => 'CC-FROID', 'nom' => 'Froid', 'type' => 'centre_cout', 'parent_id' => $f1->id, 'actif' => 1])->assertSessionHasErrors('parent_id');
    }

    public function test_entite_utilisee_non_supprimable_et_budget_suivi(): void
    {
        $dir = $this->user('direction');
        $compta = $this->user('comptable');
        $ex = AccountingExercise::create(['code' => 'EX', 'libelle' => 'EX', 'date_debut' => today()->startOfYear(), 'date_fin' => today()->endOfYear()]);
        $e = AccountingEntity::create(['code' => 'CC1', 'nom' => 'Centre', 'type' => 'centre_cout', 'exercise_id' => $ex->id, 'budget_reference' => 1000000]);

        $this->actingAs($compta)->post('/comptabilite/depenses', [
            'date_depense' => today()->toDateString(), 'motif' => 'Achat', 'montant' => 900000,
            'categorie_id' => CategorieFinanciere::depenses()->first()->id, 'accounting_entity_id' => $e->id, 'mode_paiement' => 'ESPECES',
        ])->assertRedirect();
        $od = OrdreDepense::first();
        $this->assertSame($e->id, $od->accounting_entity_id);
        $this->actingAs($this->user('raf'))->post("/raf/validations/{$od->id}", ['decision' => 'valider']);

        $this->actingAs($dir)->get('/comptabilite/budgets')->assertOk()->assertSee('CC1')->assertSee('Seuil atteint');
        $this->delete("/comptabilite/entites/{$e->id}")->assertSessionHasErrors('entite');
        $this->assertNotNull($e->fresh());
        // Le budget ne peut pas passer sous le déjà dépensé
        $this->put("/comptabilite/entites/{$e->id}", ['code' => 'CC1', 'nom' => 'Centre', 'type' => 'centre_cout', 'exercise_id' => $ex->id, 'budget_reference' => 500000, 'actif' => 1])->assertSessionHasErrors('budget_reference');

        // Fiche : opérations visibles selon les autorisations
        $this->get("/comptabilite/entites/{$e->id}")->assertOk()->assertSee('Achat');
        $this->actingAs($this->user('raf'))->get("/comptabilite/entites/{$e->id}")->assertOk();
        // Désactivation sans perte d'historique
        $this->actingAs($dir)->put("/comptabilite/entites/{$e->id}", ['code' => 'CC1', 'nom' => 'Centre', 'type' => 'centre_cout', 'exercise_id' => $ex->id, 'budget_reference' => 1000000])->assertRedirect();
        $this->assertFalse($e->fresh()->actif);
        $this->assertSame($e->id, $od->fresh()->accounting_entity_id);
    }

    // ---------- Rôles ----------

    public function test_matrice_des_roles(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/admin/roles')->assertOk();
        $role = Role::where('slug', 'comptable')->first();
        $perm = Permission::where('slug', 'recettes.voir')->value('id');

        $this->put("/admin/roles/{$role->id}", ['permissions' => [$perm]])->assertRedirect();
        $this->assertSame(['recettes.voir'], $role->permissions()->pluck('slug')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.permissions_modifiees']);

        // Effet immédiat côté serveur
        $this->actingAs($this->user('comptable'))->get('/comptabilite/depenses')->assertForbidden();

        // Le rôle administrateur est verrouillé ; un non-admin n'accède pas à la matrice.
        $this->actingAs($admin)->put('/admin/roles/' . Role::where('slug', 'admin')->value('id'), ['permissions' => []])->assertForbidden();
        $this->actingAs($this->user('raf'))->get('/admin/roles')->assertForbidden();
    }

    public function test_les_pages_de_creation_d_entites_ne_renvoient_pas_404(): void
    {
        $rh = User::factory()->create(['role_id' => Role::where('slug', 'rh')->value('id'), 'actif' => true]);
        $this->actingAs($rh)->get('/rh/entites/create')->assertOk()->assertSee('Nouvelle entité');
        $this->get('/rh/entites')->assertOk();

        $comptable = User::factory()->create(['role_id' => Role::where('slug', 'comptable')->value('id'), 'actif' => true]);
        $this->actingAs($comptable)->get('/comptabilite/entites/create')->assertOk();
        $this->get('/comptabilite/entites')->assertOk();
    }
}

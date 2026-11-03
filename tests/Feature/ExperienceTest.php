<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Employe;
use App\Models\Infrastructure;
use App\Models\Lot;
use App\Models\Recette;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create($extra + ['role_id' => Role::where('slug', $role)->value('id'), 'actif' => true]);
    }

    private function employe(array $extra = []): Employe
    {
        return Employe::create($extra + ['matricule' => 'M' . uniqid(), 'nom' => 'Rakoto', 'prenom' => 'Jean', 'email' => uniqid() . '@t.mg', 'poste' => 'Agent', 'date_embauche' => '2020-01-01', 'statut' => 'actif']);
    }

    // ---------- 1. Journal d'activité ----------

    public function test_les_modeles_journalisent_leurs_changements_et_le_journal_se_filtre(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin);
        $e = $this->employe();
        $e->update(['poste' => 'Chef', 'salaire' => 500000]);
        $e->delete();

        $actions = ActivityLog::pluck('action')->all();
        $this->assertContains('employe.cree', $actions);
        $this->assertContains('employe.modifie', $actions);
        $this->assertContains('employe.supprime', $actions);
        $modif = ActivityLog::where('action', 'employe.modifie')->first();
        $this->assertSame('Agent', $modif->details['changements']['poste']['avant']);
        $this->assertSame('Chef', $modif->details['changements']['poste']['apres']);

        $this->get('/admin/audit?module=employe&user_id=' . $admin->id)->assertOk()->assertSee('employe.modifie');
        $this->get('/admin/audit?q=introuvable-xyz')->assertOk()->assertSee('Aucun événement');
        $csv = $this->get('/admin/audit/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('employe.cree', $csv);
        $this->actingAs($this->user('comptable'))->get('/admin/audit')->assertForbidden();
    }

    // ---------- 3. Corbeille ----------

    public function test_corbeille_restauration_suppression_definitive_et_vidage(): void
    {
        $admin = $this->user('admin');
        $a = $this->employe(['nom' => 'Alpha']);
        $b = $this->employe(['nom' => 'Beta']);
        $this->actingAs($this->user('rh'))->delete("/employes/{$a->id}")->assertRedirect();
        $this->delete("/employes/{$b->id}")->assertRedirect();
        $this->assertSoftDeleted($a);
        $this->get('/employes')->assertOk()->assertDontSee('Alpha');

        $this->actingAs($this->user('comptable'))->get('/admin/corbeille')->assertForbidden();
        $this->actingAs($this->user('rh'));
        $this->actingAs($admin)->get('/admin/corbeille?type=employes')->assertOk()->assertSee('Alpha')->assertSee('Beta');

        $this->post("/admin/corbeille/employes/{$a->id}/restaurer")->assertRedirect();
        $this->assertNotSoftDeleted($a);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employe.restaure']);

        $this->delete("/admin/corbeille/employes/{$b->id}")->assertRedirect();
        $this->assertDatabaseMissing('employes', ['id' => $b->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employe.supprime_definitivement']);

        $this->delete("/employes/{$a->id}");
        $this->delete('/admin/corbeille', ['type' => 'tout'])->assertRedirect();
        $this->assertDatabaseMissing('employes', ['id' => $a->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'corbeille.videe']);
        $this->get('/admin/corbeille?type=inconnu')->assertOk();
        $this->post('/admin/corbeille/inconnu/1/restaurer')->assertNotFound();
    }

    public function test_corbeille_protege_les_references_et_couvre_recettes_et_clients(): void
    {
        $admin = $this->user('admin');
        $emp = $this->employe();
        \App\Models\PaiementSalaire::create(['employe_id' => $emp->id, 'mois' => '2026-03', 'montant' => 1000, 'date_paiement' => today(), 'paye_par' => $admin->id]);

        $this->actingAs($this->user('rh'))->delete("/employes/{$emp->id}")->assertSessionHasErrors('employe'); // salaires versés : refus métier

        $client = Client::create(['nom' => 'Coop']);
        $cdd = $this->user('cdd');
        $this->actingAs($cdd)->post('/exploitation/lots', ['client_id' => $client->id, 'espece' => 'Crevettes', 'poids_entree' => 100, 'infrastructure_id' => Infrastructure::where('nom', 'Tunnel 1')->value('id')]);
        $cdc = $this->user('cdc');
        $this->actingAs($cdc)->delete("/exploitation/clients/{$client->id}")->assertSessionHasErrors('client'); // lot en stock
        Lot::query()->update(['statut' => 'sorti']);
        $this->delete("/exploitation/clients/{$client->id}")->assertRedirect();
        $this->assertSoftDeleted($client);

        $recette = Recette::create(['reference' => 'R1', 'date_recette' => today(), 'montant' => 10, 'saisi_par' => $admin->id, 'categorie_id' => \App\Models\CategorieFinanciere::recettes()->first()->id, 'origine' => 'x']);
        $recette->delete();
        $this->actingAs($admin)->get('/admin/corbeille?type=recettes')->assertOk()->assertSee('R1');
        $this->post("/admin/corbeille/recettes/{$recette->id}/restaurer")->assertRedirect();
        $this->assertNotSoftDeleted($recette);
        $this->get('/admin/corbeille?type=clients')->assertOk()->assertSee('Coop');
    }

    // ---------- 2. Rôles ----------

    public function test_creation_copie_et_suppression_de_roles(): void
    {
        $this->actingAs($this->user('admin'));
        $this->post('/admin/roles', ['libelle' => 'Auditeur', 'copie_de' => Role::where('slug', 'comptable')->value('id')])->assertRedirect();
        $role = Role::where('slug', 'auditeur')->first();
        $this->assertSame(Role::where('slug', 'comptable')->first()->permissions()->count(), $role->permissions()->count());
        $this->post('/admin/roles', ['libelle' => 'Auditeur'])->assertSessionHasErrors('libelle');

        $user = $this->user('comptable', ['role_id' => $role->id]);
        $this->delete("/admin/roles/{$role->id}")->assertSessionHasErrors('role'); // encore attribué
        $user->update(['role_id' => null]);
        $this->delete("/admin/roles/{$role->id}")->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->delete('/admin/roles/' . Role::where('slug', 'admin')->value('id'))->assertForbidden();
    }

    // ---------- 8. Profil ----------

    public function test_profil_modification_mot_de_passe_et_photo(): void
    {
        \Storage::fake();
        $u = $this->user('rh', ['password' => 'AncienMotDePasse1']);
        $this->actingAs($u);
        $this->get('/parametres')->assertOk()->assertSee('Mon profil');

        $this->post('/parametres', ['name' => 'Nouveau Nom', 'email' => $u->email, 'telephone' => '+261 34 00 000 00'])->assertRedirect();
        $this->assertSame('Nouveau Nom', $u->fresh()->name);
        $this->assertSame('+261 34 00 000 00', $u->fresh()->telephone);

        // Le nouveau mot de passe exige l'ancien
        $this->post('/parametres', ['name' => 'N', 'email' => $u->email, 'password' => 'NouveauMdp123', 'password_confirmation' => 'NouveauMdp123'])->assertSessionHasErrors('current_password');
        $this->post('/parametres', ['name' => 'N', 'email' => $u->email, 'current_password' => 'mauvais', 'password' => 'NouveauMdp123', 'password_confirmation' => 'NouveauMdp123'])->assertSessionHasErrors('current_password');
        $this->post('/parametres', ['name' => 'N', 'email' => $u->email, 'current_password' => 'AncienMotDePasse1', 'password' => 'NouveauMdp123', 'password_confirmation' => 'NouveauMdp123'])->assertRedirect();
        $this->assertTrue(\Hash::check('NouveauMdp123', $u->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'profil.modifie', 'user_id' => $u->id]);

        // Photo : ajout, lecture protégée, suppression
        $this->post('/parametres/avatar', ['avatar' => UploadedFile::fake()->createWithContent('moi.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='))])->assertRedirect();
        $this->assertNotNull($u->fresh()->avatar_chemin);
        \Storage::assertExists($u->fresh()->avatar_chemin);
        $this->get("/avatars/{$u->id}")->assertOk();
        $this->post('/parametres/avatar', ['avatar' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])->assertSessionHasErrors('avatar');

        $chemin = $u->fresh()->avatar_chemin;
        $this->delete('/parametres/avatar')->assertRedirect();
        $this->assertNull($u->fresh()->avatar_chemin);
        \Storage::assertMissing($chemin);

        auth()->logout();
        $this->get("/avatars/{$u->id}")->assertRedirect('/login');
    }

    // ---------- 4, 6, 7, 9, 10 : interface ----------

    public function test_l_interface_embarque_theme_toasts_squelette_graphiques_et_grille(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get('/tableau-de-bord')->assertOk()->assertSee('theme-toggle', false)->assertSee('id="toasts"', false)
            ->assertSee('confirm-modal', false)->assertSee('page-skeleton', false)->assertSee('Rechercher une page', false);

        // Graphiques et statistiques : données JSON présentes lorsqu'il y a de quoi tracer
        $this->employe(['entity_id' => \App\Models\OrganizationEntity::where('code', 'CPT')->value('id')]);
        $this->get('/tableau-de-bord')->assertOk()->assertSee('chart-data', false)->assertSee('data-chart="effectif"', false);

        // Bascule liste / grille
        $this->get('/employes')->assertOk()->assertSee('data-view-group="employes"', false)->assertSee('data-view="grille"', false);
        Client::create(['nom' => 'Zeta']);
        $this->get('/exploitation/clients')->assertStatus(403); // admin n'a pas clients.voir
        $this->actingAs($this->user('cdc'))->get('/exploitation/clients')->assertOk()->assertSee('data-view-group="clients"', false);
        $this->actingAs($this->user('rh'))->get('/rh/entites')->assertOk()->assertSee('data-view-group="entites-rh"', false);
        $this->actingAs($this->user('direction'))->get('/comptabilite/entites')->assertOk()->assertSee('data-view-group="entites-compta"', false);
    }

    public function test_le_nom_de_categorie_ne_peut_pas_casser_les_donnees_des_graphiques(): void
    {
        $comptable = $this->user('comptable');
        \App\Models\CategorieFinanciere::create(['nom' => '</script><b>x', 'type' => 'recette']);
        Recette::create(['reference' => 'R', 'date_recette' => today(), 'montant' => 100, 'saisi_par' => $comptable->id, 'categorie_id' => \App\Models\CategorieFinanciere::where('nom', 'like', '%script%')->value('id'), 'origine' => 'x']);

        $html = $this->actingAs($this->user('direction'))->get('/tableau-de-bord')->assertOk()->getContent();
        $this->assertStringNotContainsString('</script><b>x', $html); // échappé en </script>
    }

    // ---------- Périmètre RH (compte Rindra) ----------

    public function test_le_rh_voit_la_corbeille_et_le_journal_de_son_perimetre_uniquement(): void
    {
        $rh = $this->user('rh');
        $e = $this->employe(['nom' => 'Alpha']);
        $this->actingAs($rh)->delete("/employes/{$e->id}");
        $recette = Recette::create(['reference' => 'R9', 'date_recette' => today(), 'montant' => 10, 'saisi_par' => $rh->id, 'categorie_id' => \App\Models\CategorieFinanciere::recettes()->first()->id, 'origine' => 'x']);
        $recette->delete();
        \App\Services\AuditLogger::log('recette.creee', $recette, ['libelle' => 'R9']);

        // Corbeille : employés et contrats seulement
        $this->get('/admin/corbeille')->assertOk()->assertSee('Alpha')->assertSee('Contrats')->assertDontSee('Recettes');
        $this->get('/admin/corbeille?type=recettes')->assertOk()->assertDontSee('R9'); // type non autorisé : retombe sur le premier type permis
        $this->post("/admin/corbeille/recettes/{$recette->id}/restaurer")->assertNotFound();
        $this->delete('/admin/corbeille', ['type' => 'recettes'])->assertNotFound();
        $this->post("/admin/corbeille/employes/{$e->id}/restaurer")->assertRedirect();
        $this->assertNotSoftDeleted($e);

        // Journal : actions RH visibles, finances masquées
        $this->get('/admin/audit')->assertOk()->assertSee('employe.')->assertDontSee('recette.creee')->assertSee('périmètre ressources humaines');
        $this->get('/admin/audit?module=recette')->assertOk()->assertDontSee('recette.creee');
        $this->assertStringNotContainsString('recette.creee', $this->get('/admin/audit/export')->assertOk()->streamedContent());

        // Les non-RH n'y accèdent pas, l'admin voit tout
        $this->actingAs($this->user('caissier'))->get('/admin/corbeille')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/admin/audit')->assertOk()->assertSee('recette.creee');
    }

    public function test_le_tableau_de_bord_rh_affiche_ses_cartes_meme_sans_donnees(): void
    {
        $this->actingAs($this->user('rh'))->get('/tableau-de-bord')->assertOk()
            ->assertSee('Effectif par entité')->assertSee("Jours d'absence validés")->assertSee('Contrats à échéance')->assertSee('Demandes de congé en attente')
            ->assertSee('Rattachez vos employés')->assertSee('Aucune absence validée')->assertSee('Aperçu');
    }
}

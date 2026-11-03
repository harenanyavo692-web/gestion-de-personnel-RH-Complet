<?php

namespace Tests\Feature;

use App\Models\CategorieFinanciere;
use App\Models\Employe;
use App\Models\OrdreDepense;
use App\Models\Recette;
use App\Models\Role;
use App\Models\User;
use App\Services\FinanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role, string $email = null): User
    {
        return User::factory()->create([
            'email' => $email ?? "$role@test.mg",
            'role_id' => Role::where('slug', $role)->value('id'),
            'actif' => true,
        ]);
    }

    private function ordre(User $auteur, float $montant = 100000): OrdreDepense
    {
        return OrdreDepense::create([
            'reference' => 'OD-TEST-' . uniqid(), 'date_depense' => today(), 'motif' => 'Achat test', 'montant' => $montant,
            'categorie_id' => CategorieFinanciere::depenses()->first()->id, 'statut' => 'en_attente', 'soumis_par' => $auteur->id,
        ]);
    }

    public function test_les_invites_sont_redirigees_vers_la_connexion(): void
    {
        foreach (['/tableau-de-bord', '/comptabilite/recettes', '/raf/validations', '/admin/utilisateurs'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_chaque_page_s_affiche_pour_un_role_autorise(): void
    {
        $this->actingAs($this->user('admin'));
        foreach (['/tableau-de-bord', '/employes', '/presences', '/parametres', '/admin/utilisateurs', '/admin/utilisateurs/create', '/admin/audit'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($this->user('raf'));
        foreach (['/raf/validations', '/raf/salaires', '/raf/rapprochements', '/raf/rapports', '/comptabilite/recettes', '/comptabilite/depenses'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['jour', 'semaine', 'mois', 'annee'] as $p) {
            $this->get("/comptabilite/situation?periode=$p")->assertOk();
        }
    }

    public function test_les_permissions_sont_controlees_cote_serveur(): void
    {
        $this->actingAs($this->user('comptable'))
            ->get('/raf/validations')->assertForbidden();
        $this->get('/admin/utilisateurs')->assertForbidden();
        $this->get('/employes')->assertForbidden();

        // Le RAF n'a pas les droits d'administrateur.
        $this->actingAs($this->user('raf'))->get('/admin/utilisateurs')->assertForbidden();
        $this->get('/admin/audit')->assertForbidden();
        $this->post('/comptabilite/recettes', [])->assertForbidden();
    }

    public function test_le_raf_valide_un_ordre_et_l_action_est_tracee(): void
    {
        $comptable = $this->user('comptable');
        $raf = $this->user('raf');
        $ordre = $this->ordre($comptable);

        $this->actingAs($raf)->post("/raf/validations/{$ordre->id}", ['decision' => 'valider'])->assertRedirect();

        $this->assertSame('validee', $ordre->fresh()->statut);
        $this->assertSame($raf->id, $ordre->fresh()->traite_par);
        $this->assertDatabaseHas('audit_logs', ['action' => 'depense.validee', 'user_id' => $raf->id, 'cible_id' => $ordre->id]);

        // Déjà traité : une seconde décision est refusée.
        $this->post("/raf/validations/{$ordre->id}", ['decision' => 'refuser', 'commentaire' => 'x'])->assertForbidden();
    }

    public function test_un_refus_exige_un_commentaire(): void
    {
        $ordre = $this->ordre($this->user('comptable'));

        $this->actingAs($this->user('raf'))
            ->post("/raf/validations/{$ordre->id}", ['decision' => 'refuser'])
            ->assertSessionHasErrors('commentaire');
        $this->assertSame('en_attente', $ordre->fresh()->statut);
    }

    public function test_on_ne_valide_pas_son_propre_ordre(): void
    {
        $raf = $this->user('raf');
        $ordre = $this->ordre($raf);

        $this->actingAs($raf)->post("/raf/validations/{$ordre->id}", ['decision' => 'valider'])->assertForbidden();
        $this->assertSame('en_attente', $ordre->fresh()->statut);
    }

    public function test_le_solde_se_calcule_depuis_la_base(): void
    {
        $comptable = $this->user('comptable');
        $raf = $this->user('raf');
        $cat = CategorieFinanciere::recettes()->first();

        $this->actingAs($comptable)->post('/comptabilite/recettes', [
            'date_recette' => today()->toDateString(), 'montant' => 500000, 'categorie_id' => $cat->id, 'origine' => 'Client A',
        ])->assertRedirect();

        $valide = $this->ordre($comptable, 120000);
        $this->ordre($comptable, 999999); // en attente : ne compte pas
        $this->actingAs($raf)->post("/raf/validations/{$valide->id}", ['decision' => 'valider']);

        Employe::create([
            'matricule' => 'M1', 'nom' => 'Rakoto', 'prenom' => 'Jean', 'email' => 'j@t.mg', 'poste' => 'Agent',
            'departement' => 'Direction', 'date_embauche' => today(), 'salaire' => 80000, 'statut' => 'actif',
        ]);
        $employe = Employe::first();
        $this->post("/raf/salaires/{$employe->id}", ['mois' => now()->format('Y-m')])->assertRedirect();
        $this->assertSame(500000 - 120000 - 80000.0, app(FinanceService::class)->soldeLivres(now()));

        // Un salaire ne se paie qu'une fois par mois.
        $this->post("/raf/salaires/{$employe->id}", ['mois' => now()->format('Y-m')])->assertSessionHasErrors('employe');
        $this->assertDatabaseCount('paiements_salaires', 1);
    }

    public function test_les_montants_invalides_sont_rejetes(): void
    {
        $cat = CategorieFinanciere::recettes()->first();

        $this->actingAs($this->user('comptable'))->post('/comptabilite/recettes', [
            'date_recette' => today()->toDateString(), 'montant' => -5, 'categorie_id' => $cat->id, 'origine' => 'X',
        ])->assertSessionHasErrors('montant');
        $this->assertSame(0, Recette::count());

        // Une catégorie de dépense n'est pas acceptée pour une recette.
        $this->post('/comptabilite/recettes', [
            'date_recette' => today()->toDateString(), 'montant' => 10, 'categorie_id' => CategorieFinanciere::depenses()->first()->id, 'origine' => 'X',
        ])->assertSessionHasErrors('categorie_id');
    }

    public function test_le_rapprochement_recalcule_le_solde_des_livres_cote_serveur(): void
    {
        $raf = $this->user('raf');
        Recette::create([
            'reference' => 'R1', 'date_recette' => today(), 'montant' => 300000, 'saisi_par' => $raf->id,
            'categorie_id' => CategorieFinanciere::recettes()->first()->id, 'origine' => 'Test',
        ]);

        $this->actingAs($raf)->post('/raf/rapprochements', [
            'periode' => 'Test', 'date_arrete' => today()->toDateString(), 'solde_tresor' => 250000,
            'ecarts' => [['libelle' => 'Versement en transit', 'montant' => -50000]],
            'solde_livres' => 1, // ignoré : jamais fourni par le client
        ])->assertRedirect();

        $this->assertDatabaseHas('rapprochements', ['solde_livres' => 300000, 'ecart_final' => 0, 'statut' => 'rapproche']);
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $user = $this->user('rh');
        $user->update(['actif' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_un_administrateur_ne_peut_pas_se_desactiver_lui_meme(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->put("/admin/utilisateurs/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'role_id' => Role::where('slug', 'rh')->value('id'),
        ])->assertRedirect();

        $admin->refresh();
        $this->assertTrue($admin->actif);
        $this->assertSame('admin', $admin->role->slug);
    }

    public function test_les_justificatifs_sont_proteges_par_permission(): void
    {
        \Storage::fake();
        $comptable = $this->user('comptable');
        $this->actingAs($comptable)->post('/comptabilite/depenses', [
            'date_depense' => today()->toDateString(), 'motif' => 'Pièces', 'montant' => 1000,
            'categorie_id' => CategorieFinanciere::depenses()->first()->id,
            'justificatif' => \Illuminate\Http\UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $piece = \App\Models\PieceJustificative::first();
        $this->get("/comptabilite/pieces/{$piece->id}")->assertOk();

        $this->actingAs($this->user('rh'))->get("/comptabilite/pieces/{$piece->id}")->assertForbidden();
    }
}

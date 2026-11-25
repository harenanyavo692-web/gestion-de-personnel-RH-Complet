<?php

namespace Tests\Feature;

use App\Models\CategorieFinanciere;
use App\Models\Employe;
use App\Models\Recette;
use App\Models\Role;
use App\Models\User;
use App\Services\FinanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivesTest extends TestCase
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

    private function employe(string $nom, string $statut = 'inactif'): Employe
    {
        return Employe::create(['matricule' => 'M' . uniqid(), 'nom' => $nom, 'prenom' => 'Jean', 'email' => uniqid() . '@t.mg', 'poste' => 'Agent', 'date_embauche' => '2020-01-01', 'statut' => $statut]);
    }

    public function test_journal_corbeille_et_archives_sont_trois_pages_separees(): void
    {
        $this->actingAs($this->user('admin'));

        foreach (['/admin/audit' => "Journal d'activité", '/admin/corbeille' => 'Corbeille', '/admin/archives' => 'Archives'] as $url => $titre) {
            $page = $this->get($url)->assertOk()->assertSee($titre);
            $page->assertDontSee('aria-label="Sections"', false); // plus d'onglets partagés
            $page->assertSee(route('admin.audit'), false)->assertSee(route('admin.corbeille.index'), false)->assertSee(route('admin.archives.index'), false); // trois entrées de menu
        }
    }

    public function test_archiver_retire_de_la_liste_et_se_retrouve_dans_les_archives(): void
    {
        $admin = $this->user('admin');
        $e = $this->employe('Zafindrabe');
        $this->actingAs($admin);
        $this->get('/employes')->assertSee('Zafindrabe');

        $this->post("/admin/archives/employes/{$e->id}/archiver")->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($e->fresh()->archived_at);
        $this->assertSame($admin->id, $e->fresh()->archived_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'archive.archive']);

        $this->get('/employes'); // le message de confirmation cite le nom : on le consomme d'abord
        $this->get('/employes')->assertDontSee('Zafindrabe');
        $this->get('/admin/archives?type=employes')->assertOk()->assertSee('Zafindrabe')->assertSee($admin->name);
        $this->get('/admin/corbeille?type=employes')->assertDontSee('Zafindrabe'); // archive ≠ corbeille

        $this->post("/admin/archives/employes/{$e->id}/restaurer")->assertRedirect();
        $this->assertNull($e->fresh()->archived_at);
        $this->get('/employes')->assertSee('Zafindrabe');
        $this->assertDatabaseHas('audit_logs', ['action' => 'archive.desarchive']);
    }

    public function test_la_page_personnel_montre_l_archivage_et_le_lien_vers_les_archives(): void
    {
        $actif = $this->employe('Vivant', 'actif');
        $inactif = $this->employe('Parti', 'inactif');
        $this->actingAs($this->user('rh'));

        // Un employé actif a un bouton grisé qui explique pourquoi ; un inactif a un vrai bouton.
        $page = $this->get('/employes')->assertOk()->assertSee('Archives')
            ->assertSee('Seul un employé inactif peut être archivé', false)
            ->assertSee("/admin/archives/employes/{$inactif->id}/archiver", false)
            ->assertDontSee("/admin/archives/employes/{$actif->id}/archiver", false);

        $this->post("/admin/archives/employes/{$inactif->id}/archiver")->assertRedirect();
        $this->get('/employes'); // consomme le message de confirmation
        $this->get('/employes')->assertDontSee('Parti')->assertSee(route('admin.archives.index', ['type' => 'employes']), false);
        $this->get('/admin/archives?type=employes')->assertOk()->assertSee('Parti');

        // Le comptable ne gère pas les employés : pas d'accès à la page Personnel ni de lien vers les archives.
        $this->actingAs($this->user('comptable'))->get('/employes')->assertForbidden();
    }

    public function test_un_employe_actif_ne_peut_pas_etre_archive(): void
    {
        $e = $this->employe('Actif', 'actif');
        $this->actingAs($this->user('admin'))->post("/admin/archives/employes/{$e->id}/archiver")->assertSessionHasErrors('archive');
        $this->assertNull($e->fresh()->archived_at);
    }

    public function test_droits_sur_les_archives(): void
    {
        $e = $this->employe('Rakoto');

        // Le comptable ne gère pas les employés : ni archivage, ni consultation.
        $this->actingAs($this->user('comptable'));
        $this->post("/admin/archives/employes/{$e->id}/archiver")->assertForbidden();
        $this->get('/admin/archives')->assertForbidden();

        // Le RH archive dans son périmètre, mais ne voit pas les types réservés à l'administration.
        $this->actingAs($this->user('rh'));
        $this->post("/admin/archives/employes/{$e->id}/archiver")->assertRedirect();
        $this->get('/admin/archives?type=clients')->assertOk()->assertDontSee('Recettes');

        // Suppression définitive : super administrateur uniquement.
        $this->delete("/admin/archives/employes/{$e->id}")->assertForbidden();
        $this->actingAs($this->user('admin'))->delete("/admin/archives/employes/{$e->id}")->assertForbidden();
        $this->actingAs($this->user('superadmin'))->delete("/admin/archives/employes/{$e->id}")->assertRedirect();
        $this->assertDatabaseMissing('employes', ['id' => $e->id]);
    }

    public function test_une_recette_archivee_quitte_la_liste_mais_reste_comptee(): void
    {
        $admin = $this->user('admin');
        $recette = Recette::create(['reference' => 'REC-ARCH-1', 'date_recette' => today(), 'montant' => 1000, 'saisi_par' => $admin->id, 'categorie_id' => CategorieFinanciere::recettes()->first()->id, 'origine' => 'x']);
        $this->actingAs($this->user('comptable'));
        $this->get('/comptabilite/recettes')->assertSee('REC-ARCH-1');

        $this->post("/admin/archives/recettes/{$recette->id}/archiver")->assertRedirect();

        $this->get('/comptabilite/recettes'); // consomme le message de confirmation
        $this->get('/comptabilite/recettes')->assertDontSee('REC-ARCH-1');
        $this->actingAs($admin)->get('/admin/archives?type=recettes')->assertSee('REC-ARCH-1');
        $this->assertSame(1000.0, app(FinanceService::class)->totalRecettes()); // les états financiers ne changent pas
    }
}

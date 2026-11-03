<?php

namespace Tests\Feature;

use App\Models\CategorieFinanciere;
use App\Models\DossierRegie;
use App\Models\Recette;
use App\Models\Role;
use App\Models\User;
use App\Services\ParametreService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegieTarifsTest extends TestCase
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

    private function recette(string $categorie, float $montant, User $u): void
    {
        Recette::create(['reference' => uniqid('R'), 'date_recette' => today(), 'montant' => $montant, 'saisi_par' => $u->id,
            'categorie_id' => CategorieFinanciere::where('nom', $categorie)->value('id'), 'origine' => 'Test']);
    }

    public function test_circuit_complet_de_la_regie(): void
    {
        $regisseuse = $this->user('regisseuse');
        $this->recette('Tunnel de congélation', 100000, $regisseuse);
        $this->recette('Chambre froide', 50000, $regisseuse);
        $this->recette('Clients spéciaux', 999999, $regisseuse); // hors journal de régie

        $this->actingAs($regisseuse)->post('/comptabilite/regie/cloturer')->assertRedirect();
        $d = DossierRegie::first();
        $this->assertEquals(150000, $d->montant);

        // Une seule clôture par jour
        $this->post('/comptabilite/regie/cloturer')->assertSessionHasErrors('regie');

        // Étapes dans l'ordre uniquement
        $this->post("/comptabilite/regie/{$d->id}/transmettre")->assertSessionHasErrors('regie');
        $this->post("/comptabilite/regie/{$d->id}/verser")->assertRedirect();
        $this->post("/comptabilite/regie/{$d->id}/recu", ['recu_tresor_num' => 'RT-77'])->assertRedirect();
        $this->post("/comptabilite/regie/{$d->id}/transmettre")->assertRedirect();

        // La régisseuse ne peut pas émettre l'ordre ; le comptable oui
        $this->post("/comptabilite/regie/{$d->id}/emettre")->assertForbidden();
        $this->actingAs($this->user('comptable'))->post("/comptabilite/regie/{$d->id}/emettre")->assertRedirect();

        $d->refresh();
        $this->assertSame('cloture', $d->statut);
        $this->assertSame('OV-' . $d->reference, $d->ordre_versement_num);
        $this->get("/comptabilite/regie/{$d->id}/ordre-versement")->assertOk()->assertSee('RT-77');
        $this->assertDatabaseHas('audit_logs', ['action' => 'regie.ordre_versement_emis']);
    }

    public function test_cloture_impossible_sans_encaissement(): void
    {
        $this->actingAs($this->user('regisseuse'))->post('/comptabilite/regie/cloturer')->assertSessionHasErrors('regie');
        $this->assertSame(0, DossierRegie::count());
    }

    public function test_majoration_client_et_usine_comme_dans_la_maquette(): void
    {
        $c = $this->user('comptable');
        // 1000 kg, 48 h, faute client, tarif 450, taux 50 % : 450000 + 1000*(24/24)*450*1.5 = 1 125 000
        $this->actingAs($c)->post('/comptabilite/retards', ['lot' => 'LOT-1', 'client' => 'A', 'poids_kg' => 1000, 'heures_tunnel' => 48, 'responsabilite' => 'client'])->assertRedirect();
        $this->assertDatabaseHas('retards_tunnel', ['lot' => 'LOT-1', 'penalite' => 675000, 'montant_tunnel' => 1125000]);

        // Faute usine : pas de pénalité, facturé au prorata des jours
        $this->post('/comptabilite/retards', ['lot' => 'LOT-2', 'client' => 'B', 'poids_kg' => 1000, 'heures_tunnel' => 48, 'responsabilite' => 'usine']);
        $this->assertDatabaseHas('retards_tunnel', ['lot' => 'LOT-2', 'penalite' => 0, 'montant_tunnel' => 900000]);

        $this->post('/comptabilite/retards', ['lot' => 'L3', 'client' => 'C', 'poids_kg' => 10, 'heures_tunnel' => 10, 'responsabilite' => 'client'])->assertSessionHasErrors('heures_tunnel');

        $this->post('/comptabilite/retards/taux', ['taux' => 80])->assertRedirect();
        $this->assertEquals(80, app(ParametreService::class)->get('majoration.taux_retard'));
    }

    public function test_les_tarifs_ne_sont_modifiables_que_par_les_roles_autorises(): void
    {
        $this->actingAs($this->user('comptable'))->get('/comptabilite/tarifs')->assertOk()->assertSee('Mahajanga - Tananarivo');
        $this->post('/comptabilite/tarifs', [])->assertForbidden();

        $admin = $this->user('admin');
        $p = app(ParametreService::class);
        $payload = [
            'tunnel' => 500, 'stockage_chambre' => 25, 'stockage_societe' => 15, 'stockage_crevettes' => 50, 'exception_4t_tana' => 350,
            'interurbain' => $p->get('tarifs.transport_interurbain'), 'urbain' => $p->get('tarifs.transport_urbain'),
            'location' => $p->get('tarifs.location'), 'eau' => $p->get('tarifs.eau'), 'entete' => $p->get('entete'),
        ];
        $this->actingAs($admin)->post('/comptabilite/tarifs', $payload)->assertRedirect();
        $this->assertEquals(500, app(ParametreService::class)->get('tarifs.tunnel'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'parametre.modifie']);
    }

    public function test_les_valeurs_par_defaut_reprennent_la_maquette(): void
    {
        $p = app(ParametreService::class);
        $this->assertEquals(450, $p->get('tarifs.tunnel'));
        $this->assertEquals(400, $p->get('tarifs.transport_interurbain')['Mahajanga - Tananarivo']['avecGO']);
        $this->assertEquals(2098693.80, $p->get('tarifs.location')['8T']);
        $this->assertEquals(50.0, $p->tarifStockage('Crevettes'));
        $this->assertEquals(25.0, $p->tarifStockage('Poisson Capitaine'));
    }
}

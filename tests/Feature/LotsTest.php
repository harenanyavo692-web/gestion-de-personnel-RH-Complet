<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Infrastructure;
use App\Models\Lot;
use App\Models\Recette;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotsTest extends TestCase
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

    private function entree(User $u, string $infra = 'Tunnel 1', float $poids = 1000, array $extra = []): Lot
    {
        $client = Client::firstOrCreate(['nom' => 'Coop Boeny'], ['special' => false]);
        $this->actingAs($u)->post('/exploitation/lots', [
            'client_id' => $client->id, 'espece' => 'Crevettes', 'poids_entree' => $poids,
            'infrastructure_id' => Infrastructure::where('nom', $infra)->value('id'), ...$extra,
        ])->assertRedirect();

        return Lot::latest('id')->first();
    }

    public function test_cycle_complet_entree_transfert_encaissement_sortie(): void
    {
        $lot = $this->entree($this->user('cdd'));
        $this->assertSame('tunnel', $lot->type_infrastructure);
        $this->assertEquals(50, $lot->tarif_chambre); // crevettes

        // Le CDD ne peut ni transférer ni encaisser
        $this->post("/exploitation/lots/{$lot->id}/transferer", [])->assertForbidden();
        $this->post("/exploitation/lots/{$lot->id}/tunnel")->assertForbidden();

        $chf = Infrastructure::where('nom', 'CHF 1')->value('id');
        $this->actingAs($this->user('magasinier'))->post("/exploitation/lots/{$lot->id}/transferer", [
            'infrastructure_id' => $chf, 'poids_contre_pesee' => 980, 'jours_prevus' => 2,
        ])->assertRedirect();
        $lot->refresh();
        $this->assertSame('chambre', $lot->type_infrastructure);
        $this->assertEquals(450000, $lot->montant_tunnel); // 1000 kg x 1 j x 450
        $this->assertEquals(980, $lot->stock_restant);

        $caisse = $this->user('caissier');
        $this->actingAs($caisse)->post("/exploitation/lots/{$lot->id}/tunnel")->assertRedirect();
        $this->post("/exploitation/lots/{$lot->id}/tunnel")->assertSessionHasErrors('lot'); // pas de double encaissement
        $this->post("/exploitation/lots/{$lot->id}/froid")->assertRedirect();

        // Recettes générées : elles alimentent la régie
        $this->assertDatabaseHas('recettes', ['numero_bon' => $lot->reference, 'montant' => 450000]);
        $this->assertDatabaseHas('recettes', ['numero_bon' => $lot->reference, 'montant' => 98000]); // 980 x 2 j x 50
        $this->assertSame(548000.0, (float) Recette::sum('montant'));

        // Libération possible car froid payé
        $this->actingAs($this->user('magasinier'))->post("/exploitation/lots/{$lot->id}/liberer")->assertRedirect();
        $this->assertSame('sorti', $lot->fresh()->statut);
    }

    public function test_depassement_24h_exige_une_responsabilite_et_alimente_le_registre(): void
    {
        $lot = $this->entree($this->user('cdd'));
        $lot->update(['date_entree' => now()->subHours(48)]);
        $chf = Infrastructure::where('nom', 'CHF 1')->value('id');
        $mag = $this->user('magasinier');

        $this->actingAs($mag)->post("/exploitation/lots/{$lot->id}/transferer", ['infrastructure_id' => $chf, 'poids_contre_pesee' => 1000])
            ->assertSessionHasErrors('responsabilite');

        $this->post("/exploitation/lots/{$lot->id}/transferer", ['infrastructure_id' => $chf, 'poids_contre_pesee' => 1000, 'responsabilite' => 'client'])->assertRedirect();
        // 450000 (24 h) + 1000 x 1 j x 450 x 1,5 = 1 125 000
        $this->assertDatabaseHas('retards_tunnel', ['lot' => $lot->reference, 'responsabilite' => 'client', 'montant_tunnel' => 1125000, 'penalite' => 675000]);
    }

    public function test_capacite_et_statut_des_infrastructures(): void
    {
        $cdd = $this->user('cdd');
        $client = Client::create(['nom' => 'X']);
        $tunnel = Infrastructure::where('nom', 'Tunnel 1')->first();

        $this->actingAs($cdd)->post('/exploitation/lots', ['client_id' => $client->id, 'espece' => 'Requin', 'poids_entree' => 1300, 'infrastructure_id' => $tunnel->id])
            ->assertSessionHasErrors('poids_entree');

        $tunnel->update(['statut' => 'maintenance']);
        $this->post('/exploitation/lots', ['client_id' => $client->id, 'espece' => 'Requin', 'poids_entree' => 100, 'infrastructure_id' => $tunnel->id])
            ->assertSessionHasErrors('infrastructure_id');
        $this->assertSame(0, Lot::count());
    }

    public function test_entree_directe_en_chambre_et_tarif_negocie_reserve_aux_clients_speciaux(): void
    {
        $cdd = $this->user('cdd');
        $normal = Client::create(['nom' => 'Normal']);
        $vip = Client::create(['nom' => 'VIP', 'special' => true]);
        $chf = Infrastructure::where('nom', 'CHF 2')->value('id');

        $this->actingAs($cdd)->post('/exploitation/lots', ['client_id' => $normal->id, 'espece' => 'Poisson (autres)', 'poids_entree' => 500, 'infrastructure_id' => $chf])
            ->assertSessionHasErrors('motif_entree_directe');

        $this->post('/exploitation/lots', ['client_id' => $normal->id, 'espece' => 'Poisson (autres)', 'poids_entree' => 500, 'infrastructure_id' => $chf, 'motif_entree_directe' => 'economie', 'tarif_negocie' => 5]);
        $this->post('/exploitation/lots', ['client_id' => $vip->id, 'espece' => 'Poisson (autres)', 'poids_entree' => 500, 'infrastructure_id' => $chf, 'motif_entree_directe' => 'preconge', 'tarif_negocie' => 10]);

        $a = Lot::where('client_id', $normal->id)->first();
        $b = Lot::where('client_id', $vip->id)->first();
        $this->assertNull($a->tarif_negocie);
        $this->assertEquals(10, $b->tarif_negocie);
        $this->assertTrue($a->paye_tunnel); // entrée directe : pas de tunnel à payer
    }

    public function test_retrait_partiel_facture_au_prorata_et_solde_le_lot(): void
    {
        $lot = $this->entree($this->user('cdd'), 'CHF 3', 1000, ['motif_entree_directe' => 'economie']);
        $lot->update(['tarif_chambre' => 25, 'date_entree_chambre' => now()->subDays(2), 'date_entree' => now()->subDays(2)]);
        $mag = $this->user('magasinier');

        $this->actingAs($mag)->post("/exploitation/lots/{$lot->id}/sortie", ['poids' => 2000])->assertSessionHasErrors('poids');
        $this->post("/exploitation/lots/{$lot->id}/sortie", ['poids' => 400])->assertRedirect();
        $this->assertEquals(600, $lot->fresh()->stock_restant);
        $this->assertDatabaseHas('sorties_partielles', ['lot_id' => $lot->id, 'poids' => 400, 'montant' => 20000]); // 400 x 2 j x 25

        $this->post("/exploitation/lots/{$lot->id}/sortie", ['poids' => 600]);
        $this->assertSame('sorti', $lot->fresh()->statut);
    }

    public function test_pages_exploitation_et_droits(): void
    {
        $this->actingAs($this->user('cdc'))->get('/exploitation/clients')->assertOk();
        $this->post('/exploitation/clients', ['nom' => 'Nouveau', 'special' => 1, 'conditions' => 'Paiement échelonné'])->assertRedirect();
        $this->assertDatabaseHas('clients', ['nom' => 'Nouveau', 'special' => true]);
        $this->post('/exploitation/clients', ['nom' => 'Nouveau'])->assertSessionHasErrors('nom');

        $this->actingAs($this->user('caissier'))->post('/exploitation/clients', ['nom' => 'Z'])->assertForbidden();

        $lot = $this->entree($this->user('cdd'));
        $this->actingAs($this->user('comptable'))->get('/exploitation/lots')->assertOk()->assertSee($lot->reference);
        $this->get("/exploitation/lots/{$lot->id}/bon-entree")->assertOk()->assertSee('Bon d');
        $this->get('/comptabilite/recettes')->assertOk();
    }

    public function test_registres_comptables_flux_creances_et_export(): void
    {
        $lot = $this->entree($this->user('cdd'));
        $this->actingAs($this->user('magasinier'))->post("/exploitation/lots/{$lot->id}/transferer", ['infrastructure_id' => Infrastructure::where('nom', 'CHF 1')->value('id'), 'poids_contre_pesee' => 1000]);
        $this->actingAs($this->user('caissier'))->post("/exploitation/lots/{$lot->id}/tunnel");
        $this->actingAs($this->user('magasinier'))->post("/exploitation/lots/{$lot->id}/sortie", ['poids' => 300]);

        $comptable = $this->user('comptable');
        $this->actingAs($comptable)->get('/comptabilite/registres')->assertOk()
            ->assertSee($lot->reference)->assertSee('Registre des bons de sortie')->assertSee('Créances en cours');
        $this->get('/comptabilite/registres?periode=semaine&type=froid')->assertOk();

        $csv = $this->get('/comptabilite/registres/export')->assertOk()->streamedContent();
        $this->assertStringContainsString("BONS D'ENTREE", $csv);
        $this->assertStringContainsString($lot->reference, $csv);
        $this->get('/comptabilite/situation/export?periode=mois')->assertOk();

        $this->actingAs($this->user('magasinier'))->get('/comptabilite/registres')->assertForbidden();
        $this->actingAs($this->user('raf'))->get('/raf/rapports')->assertOk()->assertSee('7 derniers jours')->assertSee('Personnel actif');
    }
}

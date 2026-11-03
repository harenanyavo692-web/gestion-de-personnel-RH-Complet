<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Infrastructure;
use App\Models\Lot;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaisseClientsTest extends TestCase
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

    private function lot(): Lot
    {
        $client = Client::create(['nom' => 'Coop Boeny', 'contact' => '034 00 000 00']);

        return Lot::create([
            'reference' => 'LOT-T1', 'client_id' => $client->id, 'infrastructure_id' => Infrastructure::where('nom', 'Tunnel 1')->value('id'),
            'type_infrastructure' => 'tunnel', 'espece' => 'Crevettes', 'poids_entree' => 1000, 'stock_restant' => 1000,
            'date_entree' => now(), 'tarif_chambre' => 50, 'jours_prevus' => 1, 'cree_par' => $this->user('cdd')->id,
        ]);
    }

    public function test_la_caisse_liste_les_factures_et_le_journal_est_exportable(): void
    {
        $lot = $this->lot();

        $this->actingAs($this->user('cdd'))->get('/caisse')->assertForbidden();

        $caisse = $this->user('caissier');
        $this->actingAs($caisse)->get('/caisse')->assertOk()->assertSee('LOT-T1')->assertSee('Coop Boeny');
        $this->get('/caisse?client_id=' . $lot->client_id)->assertOk()->assertSee('Fiche de vigilance client');

        $this->post("/exploitation/lots/{$lot->id}/tunnel")->assertRedirect();
        $this->get('/caisse?section=journal')->assertOk()->assertSee('Tunnel de congélation')->assertDontSee('Aucun encaissement ce jour-là');

        $this->get('/caisse/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_journal_detaille_de_la_regie(): void
    {
        $lot = $this->lot();
        $this->actingAs($this->user('caissier'))->post("/exploitation/lots/{$lot->id}/tunnel");

        $this->actingAs($this->user('regisseuse'))->get('/comptabilite/regie')->assertOk()->assertSee('Journal détaillé')->assertSee('LOT-T1');
        $this->get('/comptabilite/regie/journal/export?date=' . today()->toDateString())->assertOk();
    }

    public function test_fiche_client_et_indicateurs(): void
    {
        $lot = $this->lot();

        $this->actingAs($this->user('cdc'))->get('/exploitation/clients')->assertOk()->assertSee('Total comptes clients')->assertSee('Volume total brassé');
        $this->get("/exploitation/clients/{$lot->client_id}")->assertOk()->assertSee('Coop Boeny')->assertSee('LOT-T1')->assertSee('Tendance');
        $this->get("/exploitation/clients/{$lot->client_id}?par=semaine")->assertOk();
    }

    public function test_parametres_des_modules_clients_caisse_documents(): void
    {
        $cdc = $this->user('cdc');
        $this->actingAs($cdc)->post('/exploitation/clients', ['nom' => 'Pêcherie Nord', 'contact' => '034', 'special' => 1, 'volume_historique' => 12500, 'tendance' => 'baisse'])->assertSessionHasNoErrors();
        $c = Client::where('nom', 'Pêcherie Nord')->first();
        $this->assertEquals(12500, $c->volume_historique);
        $this->assertSame('baisse', $c->tendance);

        // Fiche : tendance initiale tant qu'il n'y a pas de lots, et situation à une date de référence.
        $this->get("/exploitation/clients/{$c->id}?date=" . today()->toDateString())->assertOk()->assertSee('En baisse')->assertSee('Situation au');
        $this->get('/exploitation/clients')->assertOk()->assertSee('Volume historique');

        // Générateur de documents du module CDC.
        $this->get('/exploitation/clients/documents?client_id=' . $c->id)->assertOk()->assertSee('Générateur de documents — CDC')->assertSee('Pêcherie Nord');
        $this->post('/exploitation/clients/documents', [
            'type' => 'fiche_client', 'date' => today()->toDateString(), 'client' => 'Pêcherie Nord',
            'lignes' => [['designation' => 'Abonnement', 'qte' => 1, 'pu' => 1000]],
        ])->assertOk()->assertSee('Fiche client');
        $this->post('/exploitation/clients/documents', ['type' => 'lettre_voiture', 'date' => today()->toDateString(), 'client' => 'X', 'lignes' => [['designation' => 'A', 'qte' => 1, 'pu' => 1]]])->assertSessionHasErrors('type');

        // Caisse : le caissier ouvre un dossier de client spécial et reçoit un versement.
        $this->actingAs($this->user('caissier'))->get('/caisse')->assertOk()->assertSee('Clients spéciaux');
        $this->post('/comptabilite/clients-speciaux', ['client' => 'Pêcherie Nord', 'motif' => 'Tarif négocié', 'montant_convenu' => 10000])->assertSessionHasNoErrors();
        $compte = \App\Models\CompteSpecial::first();
        $this->post("/comptabilite/clients-speciaux/{$compte->id}/versements", ['montant' => 4000])->assertSessionHasNoErrors();
        $this->assertEquals(4000, $compte->fresh()->versements()->sum('montant'));
    }
}

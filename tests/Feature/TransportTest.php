<?php

namespace Tests\Feature;

use App\Models\MissionTransport;
use App\Models\Recette;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\RegieService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportTest extends TestCase
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

    private function mission(array $extra = []): array
    {
        return $extra + [
            'vehicule_id' => Vehicule::where('nom', 'like', '%4 Tonnes%')->value('id'),
            'prestation' => 'interurbain', 'destination' => 'Mahajanga - Toamasina', 'option_carburant' => 'avecGO',
            'poids_kg' => 1000, 'chauffeur' => 'Rakoto',
        ];
    }

    public function test_cout_standard_selon_les_baremes_de_la_maquette(): void
    {
        $log = $this->user('logisticien');
        $this->actingAs($log)->post('/exploitation/transport', $this->mission())->assertRedirect();
        $this->assertEquals(700000, MissionTransport::first()->cout); // 1000 kg x 700 Ar (avec gasoil)

        // Exception camion 4 T Mahajanga - Tananarivo : 350 Ar/kg ; le pick-up reste au tarif standard (400)
        $this->post('/exploitation/transport', $this->mission(['destination' => 'Mahajanga - Tananarivo']));
        $this->assertEquals(350000, MissionTransport::latest('id')->first()->cout);
        $this->post('/exploitation/transport', $this->mission(['destination' => 'Mahajanga - Tananarivo', 'vehicule_id' => Vehicule::where('nom', 'like', '%Pick-up%')->value('id')]));
        $this->assertEquals(400000, MissionTransport::latest('id')->first()->cout);

        // Un véhicule en transit n'est plus proposé
        Vehicule::where('nom', 'like', '%Pick-up%')->update(['statut' => 'en_transit']);
        $this->post('/exploitation/transport', $this->mission(['vehicule_id' => Vehicule::where('nom', 'like', '%Pick-up%')->value('id')]))->assertSessionHasErrors('vehicule_id');
    }

    public function test_encaissement_cree_la_recette_et_met_le_vehicule_en_transit(): void
    {
        $this->actingAs($this->user('logisticien'))->post('/exploitation/transport', $this->mission(['cout_negocie' => 650000]))->assertRedirect();
        $m = MissionTransport::first();
        $this->assertTrue($m->negocie);
        $this->assertEquals(650000, $m->cout);
        $this->assertEquals(700000, $m->cout_standard);

        // La logistique ne peut pas encaisser ; la caisse oui, une seule fois.
        $this->post("/exploitation/transport/{$m->id}/encaisser")->assertForbidden();
        $this->actingAs($this->user('caissier'))->post("/exploitation/transport/{$m->id}/encaisser")->assertRedirect();
        $this->post("/exploitation/transport/{$m->id}/encaisser")->assertSessionHasErrors('mission');

        $this->assertSame('valide', $m->fresh()->statut);
        $this->assertSame('en_transit', $m->vehicule->fresh()->statut);
        $this->assertDatabaseHas('recettes', ['numero_bon' => $m->reference, 'montant' => 650000]);
        $this->assertEquals(650000, app(RegieService::class)->journal()['transport']); // alimente la régie
        $this->assertSame(1, Recette::count());
    }

    public function test_pages_et_annulation(): void
    {
        $log = $this->user('logisticien');
        $this->actingAs($log)->post('/exploitation/transport', $this->mission());
        $m = MissionTransport::first();

        $this->get('/exploitation/transport')->assertOk()->assertSee($m->reference);
        $this->get("/exploitation/transport/{$m->id}/ordre-mission")->assertOk()->assertSee('Ordre de mission');
        $this->post("/exploitation/transport/{$m->id}/annuler")->assertRedirect();
        $this->assertSame('annule', $m->fresh()->statut);

        $this->actingAs($this->user('comptable'))->post('/exploitation/transport', $this->mission())->assertForbidden();
        $this->post('/exploitation/vehicules', ['nom' => 'X', 'type' => 'poids_lourd'])->assertForbidden();
    }

    public function test_prestation_sans_tarif_est_refusee(): void
    {
        $this->actingAs($this->user('logisticien'))->post('/exploitation/transport', $this->mission(['destination' => 'Local Mahajanga']))
            ->assertSessionHasErrors('prestation');
        $this->assertSame(0, MissionTransport::count());
    }

    public function test_generateur_de_documents_transport(): void
    {
        $this->actingAs($this->user('logisticien'));
        $this->get('/exploitation/transport/documents')->assertOk()->assertSee('Générateur de documents');

        $this->post('/exploitation/transport/documents', [
            'type' => 'facture', 'numero' => 'F-001', 'date' => '2026-03-02', 'client' => 'Coop Boeny', 'remise' => 500,
            'lignes' => [['designation' => 'Fret Mahajanga-Tana', 'qte' => 2, 'unite' => 'T', 'pu' => 1000]],
        ])->assertOk()->assertSee('Facture')->assertSee('Coop Boeny')->assertSee('TOTAL À PAYER');

        // Remise supérieure au sous-total et document sans ligne : refusés.
        $this->post('/exploitation/transport/documents', [
            'type' => 'facture', 'date' => '2026-03-02', 'client' => 'X', 'remise' => 9999,
            'lignes' => [['designation' => 'A', 'qte' => 1, 'pu' => 10]],
        ])->assertSessionHasErrors('remise');
        $this->post('/exploitation/transport/documents', ['type' => 'libre', 'date' => '2026-03-02', 'client' => 'X'])->assertSessionHasErrors('lignes');
    }
}

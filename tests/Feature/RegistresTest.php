<?php

namespace Tests\Feature;

use App\Models\CategorieFinanciere;
use App\Models\CompteSpecial;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistresTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'actif' => true]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_une_creance_apparait_quand_le_facture_depasse_l_encaisse(): void
    {
        $this->actingAs($this->user('comptable'))->post('/comptabilite/recettes', [
            'date_recette' => today()->toDateString(), 'montant' => 200000, 'montant_facture' => 500000,
            'categorie_id' => CategorieFinanciere::where('nom', 'Chambre froide')->value('id'),
            'origine' => 'Lot 12', 'client' => 'Client X', 'numero_bon' => 'BE-001', 'poids_kg' => 1200,
        ])->assertRedirect();

        $this->get('/comptabilite/recettes?registre=froid')->assertOk()
            ->assertSee('BE-001')->assertSee('Créances en cours')->assertSee('300 000 Ar');
    }

    public function test_le_facture_ne_peut_pas_etre_inferieur_a_l_encaisse(): void
    {
        $this->actingAs($this->user('comptable'))->post('/comptabilite/recettes', [
            'date_recette' => today()->toDateString(), 'montant' => 500, 'montant_facture' => 100,
            'categorie_id' => CategorieFinanciere::recettes()->first()->id, 'origine' => 'X',
        ])->assertSessionHasErrors('montant_facture');
    }

    public function test_un_versement_special_alimente_les_recettes_et_ne_peut_pas_depasser_le_solde(): void
    {
        \App\Models\Client::create(['nom' => 'VIP', 'special' => true]);
        $this->actingAs($this->user('comptable'))->post('/comptabilite/clients-speciaux', [
            'client' => 'VIP', 'motif' => 'Tarif négocié', 'montant_convenu' => 100000,
        ])->assertRedirect();
        $compte = CompteSpecial::first();

        $this->post("/comptabilite/clients-speciaux/{$compte->id}/versements", ['montant' => 60000])->assertRedirect();
        $this->assertDatabaseHas('recettes', ['client' => 'VIP', 'montant' => 60000]);

        $this->post("/comptabilite/clients-speciaux/{$compte->id}/versements", ['montant' => 50000])->assertSessionHasErrors('montant');
        $this->assertDatabaseCount('versements_speciaux', 1);
        $this->get('/comptabilite/clients-speciaux')->assertOk()->assertSee('Partiel');
    }

    public function test_lettre_rh_generee_et_reservee_aux_roles_autorises(): void
    {
        $this->actingAs($this->user('comptable'))->get('/raf/lettres')->assertForbidden();
        $this->post('/raf/lettres', ['type' => 'attestation_travail', 'nom' => 'X', 'poste' => 'Y'])->assertForbidden();

        $this->actingAs($this->user('raf'))->get('/raf/lettres')->assertOk()->assertSee('Aucune lettre délivrée');
        $this->post('/raf/lettres', ['type' => 'attestation_travail', 'nom' => 'Rakoto Jean', 'poste' => 'Agent', 'signataire' => 'Le DG'])->assertRedirect();

        $lettre = \App\Models\LettreRh::first();
        $this->assertStringStartsWith('LET-', $lettre->reference);
        $this->get("/raf/lettres/{$lettre->id}")->assertOk()->assertSee('Rakoto Jean')->assertSee('Attestation de travail')->assertSee('Le DG');
        $this->get('/raf/lettres')->assertOk()->assertSee('Rakoto Jean')->assertSee($lettre->reference);

        // Type inconnu et dates incohérentes refusés ; suppression du registre.
        $this->post('/raf/lettres', ['type' => 'inconnu', 'nom' => 'X', 'poste' => 'Y'])->assertSessionHasErrors('type');
        $this->post('/raf/lettres', ['type' => 'conge', 'nom' => 'X', 'poste' => 'Y', 'date_debut' => '2026-05-10', 'date_fin' => '2026-05-01'])->assertSessionHasErrors('date_fin');
        $this->delete("/raf/lettres/{$lettre->id}")->assertRedirect();
        // Suppression douce : la lettre quitte le registre mais reste récupérable depuis la corbeille.
        $this->assertSoftDeleted('lettres_rh', ['id' => $lettre->id]);
        $this->assertSame(0, \App\Models\LettreRh::count());
    }
}

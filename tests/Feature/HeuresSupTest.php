<?php

namespace Tests\Feature;

use App\Models\Employe;
use App\Models\HeureSupplementaire;
use App\Models\Role;
use App\Models\User;
use App\Services\FinanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeuresSupTest extends TestCase
{
    use RefreshDatabase;

    private function employe(): Employe
    {
        return Employe::create([
            'matricule' => 'M1', 'nom' => 'Rakoto', 'prenom' => 'Jean', 'email' => 'j@t.mg', 'poste' => 'Agent',
            'departement' => 'Direction', 'date_embauche' => today(), 'salaire' => 173330, 'statut' => 'actif',
        ]);
    }

    private function raf(): User
    {
        $this->seed(RolePermissionSeeder::class);

        return User::factory()->create(['role_id' => Role::where('slug', 'raf')->value('id'), 'actif' => true]);
    }

    public function test_paiement_des_heures_sup_est_compte_dans_les_charges(): void
    {
        $e = $this->employe();
        $this->actingAs($this->raf());
        $mois = now()->format('Y-m');

        $this->post("/raf/salaires/{$e->id}/heures-sup", ['date' => today()->toDateString(), 'heures' => 4, 'majoration' => 50])->assertSessionHasNoErrors();
        $this->post("/raf/salaires/{$e->id}/heures-sup/payer", ['mois' => $mois])->assertSessionHasNoErrors();

        // 173 330 / 173,33 = 1 000 Ar/h × 4 h × 1,5
        $this->assertSame(6000.0, (float) HeureSupplementaire::first()->montant);
        $this->assertSame(6000.0, app(FinanceService::class)->totalSalaires());

        // Rien à repayer.
        $this->post("/raf/salaires/{$e->id}/heures-sup/payer", ['mois' => $mois])->assertSessionHasErrors('employe');
    }

    public function test_plafond_hebdomadaire(): void
    {
        $e = $this->employe();
        $this->actingAs($this->raf());
        $d = today()->startOfWeek()->toDateString();

        $this->post("/raf/salaires/{$e->id}/heures-sup", ['date' => $d, 'heures' => 12, 'majoration' => 30])->assertSessionHasNoErrors();
        $this->post("/raf/salaires/{$e->id}/heures-sup", ['date' => $d, 'heures' => 9, 'majoration' => 30])->assertSessionHasErrors('heures');
        $this->assertDatabaseCount('heures_supplementaires', 1);
    }

    public function test_employe_avec_heures_sup_ne_se_supprime_pas(): void
    {
        $e = $this->employe();
        $this->actingAs($this->raf());
        $this->post("/raf/salaires/{$e->id}/heures-sup", ['date' => today()->toDateString(), 'heures' => 2, 'majoration' => 30]);

        $rh = User::factory()->create(['role_id' => Role::where('slug', 'rh')->value('id'), 'actif' => true]);
        $this->actingAs($rh)->delete("/employes/{$e->id}")->assertSessionHasErrors('employe');
        $this->assertDatabaseHas('employes', ['id' => $e->id]);
    }

    public function test_impression_du_recu_et_de_l_etat_de_paie(): void
    {
        $e = $this->employe();
        $raf = $this->raf();
        $this->actingAs($raf);
        $mois = now()->format('Y-m');

        $this->post("/raf/salaires/{$e->id}/heures-sup", ['date' => today()->toDateString(), 'heures' => 4, 'majoration' => 50, 'motif' => 'Inventaire']);
        $this->post("/raf/salaires/{$e->id}", ['mois' => $mois])->assertSessionHasNoErrors();
        $this->post("/raf/salaires/{$e->id}/heures-sup/payer", ['mois' => $mois])->assertSessionHasNoErrors();
        $paiement = \App\Models\PaiementSalaire::first();

        // 173 330 Ar de salaire + 6 000 Ar d'heures sup = 179 330 Ar
        $this->get("/raf/salaires/{$paiement->id}/recu")->assertOk()
            ->assertSee('Reçu de paiement de salaire')->assertSee($e->nom_complet)
            ->assertSee('SAL-' . str_replace('-', '', $mois) . '-0001')->assertSee('Inventaire')
            ->assertSee(\App\Support\Fmt::ar(173330))->assertSee(\App\Support\Fmt::ar(179330));
        $this->get("/raf/salaires/etat?mois={$mois}")->assertOk()
            ->assertSee('État de paie du personnel')->assertSee($e->nom_complet)->assertSee(\App\Support\Fmt::ar(179330));
        $this->get('/raf/salaires?mois=' . $mois)->assertOk()->assertSee("/raf/salaires/{$paiement->id}/recu", false)->assertSee('État de paie du mois')->assertSee('Imprimer le reçu');
        $this->get('/raf/salaires/etat?mois=invalide')->assertOk(); // mois invalide : repli sur le mois courant

        // Consultation réservée à ceux qui voient les salaires.
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'comptable')->value('id'), 'actif' => true]));
        $this->get("/raf/salaires/{$paiement->id}/recu")->assertForbidden();
        $this->get('/raf/salaires/etat')->assertForbidden();
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'rh')->value('id'), 'actif' => true]));
        $this->get("/raf/salaires/{$paiement->id}/recu")->assertOk();
    }

    public function test_historique_des_paiements_reunit_salaires_et_heures_sup(): void
    {
        $e = $this->employe();
        $autre = Employe::create(['matricule' => 'M2', 'nom' => 'Rabe', 'prenom' => 'Paul', 'email' => 'p@t.mg', 'poste' => 'Agent', 'date_embauche' => today(), 'salaire' => 100000, 'statut' => 'actif']);
        $this->actingAs($this->raf());
        $mois = now()->format('Y-m');

        $this->get('/raf/salaires/historique')->assertOk()->assertSee('Historique des paiements')->assertSee('Aucun paiement enregistré pour le moment');

        $this->post("/raf/salaires/{$e->id}/heures-sup", ['date' => today()->toDateString(), 'heures' => 4, 'majoration' => 50]);
        $this->post("/raf/salaires/{$e->id}", ['mois' => $mois]);
        $this->post("/raf/salaires/{$e->id}/heures-sup/payer", ['mois' => $mois]);
        $this->post("/raf/salaires/{$autre->id}", ['mois' => $mois]);

        // 173 330 + 6 000 (heures sup) + 100 000 = 279 330 Ar, trois lignes
        $this->get('/raf/salaires')->assertOk()->assertSee('Derniers paiements')->assertSee(route('raf.salaires.historique'), false);
        $this->get('/raf/salaires/historique')->assertOk()
            ->assertSee('Heures sup · 4 h')->assertSee($e->nom_complet)->assertSee($autre->nom_complet)
            ->assertSee('Total (3 paiements)')->assertSee(\App\Support\Fmt::ar(279330));

        // Filtre par employé : il ne reste que ses deux paiements (salaire + heures sup).
        $this->get("/raf/salaires/historique?h_employe={$e->id}")->assertOk()->assertSee('Total (2 paiements)')->assertSee(\App\Support\Fmt::ar(179330));
        // Filtre sur un mois sans paiement.
        $this->get('/raf/salaires/historique?h_mois=2020-01')->assertOk()->assertSee('Aucun paiement pour ces critères');
        $this->get('/raf/salaires/historique?h_mois=invalide&h_employe=abc')->assertOk()->assertSee('Total (3 paiements)'); // filtres invalides ignorés
    }

    public function test_le_rh_saisit_et_paie_les_salaires_et_les_heures_sup(): void
    {
        $e = $this->employe();
        $this->raf(); // charge les rôles
        $rh = User::factory()->create(['role_id' => Role::where('slug', 'rh')->value('id'), 'actif' => true]);
        $this->actingAs($rh);
        $mois = now()->format('Y-m');

        $this->get('/raf/salaires')->assertOk()->assertSee('Saisir des heures supplémentaires')->assertDontSee('Paiement par le RAF');
        $this->post("/raf/salaires/{$e->id}/heures-sup", ['date' => today()->toDateString(), 'heures' => 2, 'majoration' => 30, 'motif' => 'Clôture'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('heures_supplementaires', ['employe_id' => $e->id, 'saisi_par' => $rh->id, 'motif' => 'Clôture']);

        // Le RH paie le salaire puis les heures supplémentaires ; le reçu et l'historique les montrent.
        $this->post("/raf/salaires/{$e->id}", ['mois' => $mois])->assertSessionHasNoErrors();
        $this->post("/raf/salaires/{$e->id}/heures-sup/payer", ['mois' => $mois])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('paiements_salaires', ['employe_id' => $e->id, 'mois' => $mois, 'paye_par' => $rh->id]);
        $this->assertNotNull(HeureSupplementaire::first()->date_paiement);
        $this->get('/raf/salaires')->assertOk()->assertSee('Imprimer le reçu');
        $this->get('/raf/salaires/historique')->assertOk()->assertSee('Total (2 paiements)');

        // Un salaire ne se paie qu'une fois par mois.
        $this->post("/raf/salaires/{$e->id}", ['mois' => $mois])->assertSessionHasErrors('employe');

        // Une ligne payée ne se supprime plus ; un compte sans droit ne peut ni saisir ni payer.
        $this->delete('/raf/heures-sup/' . HeureSupplementaire::first()->id)->assertStatus(422);
        $comptable = User::factory()->create(['role_id' => Role::where('slug', 'comptable')->value('id'), 'actif' => true]);
        $this->actingAs($comptable)->post("/raf/salaires/{$e->id}/heures-sup", ['date' => today()->toDateString(), 'heures' => 1, 'majoration' => 30])->assertForbidden();
        $this->post("/raf/salaires/{$e->id}", ['mois' => '2020-01'])->assertForbidden();
    }

    public function test_la_direction_consulte_les_salaires_sans_pouvoir_payer(): void
    {
        $e = $this->employe();
        $this->raf();
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'direction')->value('id'), 'actif' => true]));

        $this->get('/raf/salaires')->assertOk()->assertSee('Paiement par le RAF');
        $this->post("/raf/salaires/{$e->id}", ['mois' => now()->format('Y-m')])->assertForbidden();
        $this->post("/raf/salaires/{$e->id}/heures-sup/payer", ['mois' => now()->format('Y-m')])->assertForbidden();
    }
}

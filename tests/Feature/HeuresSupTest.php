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
}

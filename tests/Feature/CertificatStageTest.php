<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Stagiaire;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificatStageTest extends TestCase
{
    use RefreshDatabase;

    private function stagiaire(string $debut, string $fin): Stagiaire
    {
        return Stagiaire::create(['nom' => 'Rabe', 'prenom' => 'Lala', 'etablissement' => 'Université de Mahajanga', 'filiere' => 'Gestion', 'date_debut' => $debut, 'date_fin' => $fin]);
    }

    private function rh(): User
    {
        $this->seed(RolePermissionSeeder::class);

        return User::factory()->create(['role_id' => Role::where('slug', 'rh')->value('id'), 'actif' => true]);
    }

    public function test_certificat_delivre_une_fois_apres_la_fin_du_stage(): void
    {
        $s = $this->stagiaire(today()->subDays(40)->toDateString(), today()->subDays(10)->toDateString());
        $this->actingAs($this->rh());

        $this->post("/rh/stagiaires/{$s->id}/certificat", ['appreciation' => 'bien', 'commentaire_certificat' => 'Sérieux et ponctuel'])->assertRedirect("/rh/stagiaires/{$s->id}/certificat");
        $s->refresh();
        $this->assertStringStartsWith('CERT-', $s->certificat_numero);

        $this->get("/rh/stagiaires/{$s->id}/certificat")->assertOk()->assertSee('Certificat de stage')->assertSee('Lala Rabe')->assertSee('Université de Mahajanga')->assertSee('Bien');

        // Ni second certificat, ni suppression.
        $this->post("/rh/stagiaires/{$s->id}/certificat", [])->assertSessionHasErrors('certificat');
        $this->delete("/rh/stagiaires/{$s->id}")->assertSessionHasErrors('certificat');
        $this->assertDatabaseHas('stagiaires', ['id' => $s->id]);
    }

    public function test_pas_de_certificat_avant_la_fin_du_stage(): void
    {
        $en_cours = $this->stagiaire(today()->subDays(5)->toDateString(), today()->addDays(20)->toDateString());
        $this->actingAs($this->rh());

        $this->post("/rh/stagiaires/{$en_cours->id}/certificat", [])->assertSessionHasErrors('certificat');
        $this->get("/rh/stagiaires/{$en_cours->id}/certificat")->assertNotFound();
        $this->get('/rh/stagiaires')->assertOk()->assertSee('Stage non terminé');
    }

    public function test_seule_la_rh_delivre(): void
    {
        $s = $this->stagiaire(today()->subDays(40)->toDateString(), today()->subDays(10)->toDateString());
        $this->rh();
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'caissier')->value('id'), 'actif' => true]))
            ->post("/rh/stagiaires/{$s->id}/certificat", [])->assertForbidden();
    }
}

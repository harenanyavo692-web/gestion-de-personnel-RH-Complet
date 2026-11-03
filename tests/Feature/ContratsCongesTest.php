<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Contrat;
use App\Models\Employe;
use App\Models\OrganizationEntity;
use App\Models\Presence;
use App\Models\Role;
use App\Models\User;
use App\Services\ParametreService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContratsCongesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create($extra + ['role_id' => Role::where('slug', $role)->value('id'), 'actif' => true]);
    }

    private function employe(array $extra = []): Employe
    {
        return Employe::create($extra + [
            'matricule' => 'M' . uniqid(), 'nom' => 'Rakoto', 'prenom' => 'Jean', 'email' => uniqid() . '@t.mg', 'poste' => 'Agent',
            'date_embauche' => '2020-01-01', 'statut' => 'actif',
        ]);
    }

    // Lundi 2 mars 2026 → vendredi 6 mars 2026 : 5 jours ouvrés
    private function demande(Employe $e, array $extra = []): array
    {
        return $extra + ['employe_id' => $e->id, 'type_code' => 'CONGE_ANNUEL', 'date_debut' => '2026-03-02', 'date_fin' => '2026-03-06'];
    }

    public function test_contrats_chevauchement_et_regles_de_date(): void
    {
        $rh = $this->user('rh');
        $e = $this->employe();
        $this->actingAs($rh)->get('/rh/contrats')->assertOk();

        $this->post('/rh/contrats', ['employe_id' => $e->id, 'type_code' => 'CDD', 'date_debut' => '2026-01-01'])->assertSessionHasErrors('date_fin');
        $this->post('/rh/contrats', ['employe_id' => $e->id, 'type_code' => 'CDD', 'date_debut' => '2026-06-01', 'date_fin' => '2026-01-01'])->assertSessionHasErrors('date_fin');

        $this->post('/rh/contrats', ['employe_id' => $e->id, 'type_code' => 'CDD', 'date_debut' => '2026-01-01', 'date_fin' => '2026-06-30', 'salaire' => 400000, 'appliquer_salaire' => 1])->assertRedirect();
        $this->assertEquals(400000, $e->fresh()->salaire);
        $this->post('/rh/contrats', ['employe_id' => $e->id, 'type_code' => 'CDI', 'date_debut' => '2026-06-01'])->assertSessionHasErrors('date_debut');

        $c = Contrat::first();
        $this->post("/rh/contrats/{$c->id}/terminer", ['termine_le' => '2026-05-31', 'motif_fin' => 'Démission'])->assertRedirect();
        $this->assertSame('termine', $c->fresh()->statut);
        $this->post('/rh/contrats', ['employe_id' => $e->id, 'type_code' => 'CDI', 'date_debut' => '2026-06-01'])->assertRedirect(); // sans fin : accepté

        $this->actingAs($this->user('comptable'))->get('/rh/contrats')->assertForbidden();
        $this->post('/rh/contrats', [])->assertForbidden();
    }

    public function test_demande_validation_et_impact_sur_le_pointage(): void
    {
        $e = $this->employe();
        $this->actingAs($this->user('rh'))->post('/rh/conges', $this->demande($e))->assertRedirect();
        $a = Absence::first();
        $this->assertSame('en_attente', $a->statut);
        $this->assertEquals(5, $a->jours); // week-end exclu

        // Chevauchement refusé
        $this->post('/rh/conges', $this->demande($e, ['date_debut' => '2026-03-05', 'date_fin' => '2026-03-10']))->assertSessionHasErrors('date_debut');

        // Workflow par défaut : validateur = responsable de l'entité (ou direction en suppléance) ; le RH n'a pas la main
        $this->post("/rh/conges/{$a->id}/decider", ['decision' => 'valider'])->assertForbidden();

        $dir = $this->user('direction');
        $this->actingAs($dir)->post("/rh/conges/{$a->id}/decider", ['decision' => 'refuser'])->assertSessionHasErrors('commentaire');
        $this->post("/rh/conges/{$a->id}/decider", ['decision' => 'valider'])->assertRedirect();

        $this->assertSame('validee', $a->fresh()->statut);
        $this->assertSame(5, Presence::where('employe_id', $e->id)->where('type', 'congé')->count());
        $this->post("/rh/conges/{$a->id}/decider", ['decision' => 'valider'])->assertForbidden(); // déjà traitée

        // Annulation (RH) : les jours quittent le pointage
        $this->actingAs($this->user('rh'))->post("/rh/conges/{$a->id}/annuler")->assertRedirect();
        $this->assertSame(0, Presence::where('employe_id', $e->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'absence.validee']);
    }

    public function test_le_workflow_parametre_designe_le_validateur(): void
    {
        $e = $this->employe();
        $this->actingAs($this->user('rh'))->put('/rh/parametres', ['section' => 'workflow', 'validateur' => 'rh', 'delai_reponse_jours' => 3, 'validation_requise' => 1]);
        $this->post('/rh/conges', $this->demande($e));
        $a = Absence::first();

        $this->actingAs($this->user('direction'))->post("/rh/conges/{$a->id}/decider", ['decision' => 'valider'])->assertForbidden();
        $this->actingAs($this->user('rh'))->post("/rh/conges/{$a->id}/decider", ['decision' => 'valider'])->assertRedirect();
        $this->assertSame('validee', $a->fresh()->statut);
    }

    public function test_le_responsable_de_l_entite_valide_et_personne_ne_valide_sa_propre_demande(): void
    {
        $chef = $this->employe(['email' => 'chef@cdphm.mg']);
        $entite = OrganizationEntity::where('code', 'CHF')->first();
        $entite->update(['responsable_id' => $chef->id]);
        $e = $this->employe(['entity_id' => $entite->id]);

        $this->actingAs($this->user('rh'))->post('/rh/conges', $this->demande($e));
        $a = Absence::first();

        // L'utilisateur « chef » (même e-mail que le responsable) peut décider, avec un rôle qui voit les congés
        $role = Role::create(['slug' => 'chef', 'libelle' => 'Chef']);
        $role->permissions()->sync(\App\Models\Permission::where('slug', 'conges.voir')->pluck('id'));
        $user = User::factory()->create(['email' => 'chef@cdphm.mg', 'role_id' => $role->id, 'actif' => true]);
        $this->actingAs($user)->post("/rh/conges/{$a->id}/decider", ['decision' => 'valider'])->assertRedirect();

        // Le responsable ne peut pas valider sa propre absence
        $propre = Absence::create(['employe_id' => $chef->id, 'type_code' => 'CONGE_ANNUEL', 'date_debut' => '2026-04-06', 'date_fin' => '2026-04-07', 'jours' => 2, 'demande_par' => $user->id]);
        $this->post("/rh/conges/{$propre->id}/decider", ['decision' => 'valider'])->assertForbidden();
    }

    public function test_solde_justificatif_et_validation_desactivee(): void
    {
        \Storage::fake();
        $rh = $this->user('rh');
        $e = $this->employe();
        $this->actingAs($rh);

        // Permission exceptionnelle : plafond 10 jours/an ; 15 jours ouvrés demandés → refusé
        $this->post('/rh/conges', $this->demande($e, ['type_code' => 'PERMISSION', 'date_debut' => '2026-03-02', 'date_fin' => '2026-03-20']))->assertSessionHasErrors('date_fin');
        $this->post('/rh/conges', $this->demande($e, ['type_code' => 'PERMISSION']))->assertRedirect(); // 5 jours : ok
        $this->post('/rh/conges', $this->demande($e, ['type_code' => 'PERMISSION', 'date_debut' => '2026-03-16', 'date_fin' => '2026-03-27']))->assertSessionHasErrors('date_fin'); // 10 > 5 restants

        // Solde négatif autorisé par le paramétrage
        $this->put('/rh/parametres', ['section' => 'workflow', 'validateur' => 'rh', 'delai_reponse_jours' => 3, 'validation_requise' => 1, 'solde_negatif_autorise' => 1]);
        $this->post('/rh/conges', $this->demande($e, ['type_code' => 'PERMISSION', 'date_debut' => '2026-03-16', 'date_fin' => '2026-03-27']))->assertRedirect();

        // Justificatif exigé pour la maladie
        $this->post('/rh/conges', $this->demande($e, ['type_code' => 'MALADIE', 'date_debut' => '2026-05-04', 'date_fin' => '2026-05-05']))->assertSessionHasErrors('justificatif');
        $this->post('/rh/conges', $this->demande($e, ['type_code' => 'MALADIE', 'date_debut' => '2026-05-04', 'date_fin' => '2026-05-05', 'justificatif' => UploadedFile::fake()->create('certificat.pdf', 50, 'application/pdf')]))->assertRedirect();
        $m = Absence::where('type_code', 'MALADIE')->first();
        $this->get("/rh/conges/{$m->id}/justificatif")->assertOk();
        $this->actingAs($this->user('comptable'))->get("/rh/conges/{$m->id}/justificatif")->assertForbidden();

        // Week-end seul : aucun jour ouvré
        $this->actingAs($rh)->post('/rh/conges', $this->demande($e, ['type_code' => 'MISSION', 'date_debut' => '2026-03-07', 'date_fin' => '2026-03-08']))->assertSessionHasErrors('date_debut');

        // Validation désactivée : validée d'office et visible au pointage
        $this->put('/rh/parametres', ['section' => 'workflow', 'validateur' => 'rh', 'delai_reponse_jours' => 3]);
        $this->assertFalse(app(ParametreService::class)->get('rh.workflow')['validation_requise']);
        $autre = $this->employe();
        $this->post('/rh/conges', $this->demande($autre))->assertRedirect();
        $this->assertSame('validee', Absence::where('employe_id', $autre->id)->first()->statut);
        $this->assertSame(5, Presence::where('employe_id', $autre->id)->count());
    }

    public function test_pages_conges_et_soldes(): void
    {
        $e = $this->employe();
        $this->actingAs($this->user('rh'))->post('/rh/conges', $this->demande($e));
        $this->get('/rh/conges')->assertOk()->assertSee('Rakoto')->assertSee('En attente');
        $this->get('/rh/conges?vue=soldes')->assertOk()->assertSee('Rakoto');
        $this->get('/rh/conges?statut=validee')->assertOk()->assertDontSee('Valider');
        $this->actingAs($this->user('caissier'))->get('/rh/conges')->assertForbidden();
    }

    public function test_conges_et_absences_sont_separes(): void
    {
        $e = $this->employe();
        $this->actingAs($this->user('rh'));
        $this->post('/rh/conges', $this->demande($e)); // congé annuel
        $this->post('/rh/conges', $this->demande($e, ['type_code' => 'MISSION', 'date_debut' => '2026-04-06', 'date_fin' => '2026-04-07']));

        $this->get('/rh/conges')->assertOk()->assertSee('Congé annuel')->assertDontSee('Mission');
        $this->get('/rh/absences')->assertOk()->assertSee('Mission')->assertDontSee('Congé annuel');
        $this->get('/rh/absences?vue=soldes')->assertOk()->assertDontSee('Soldes');
    }

    public function test_pointage_alimente_par_les_demandes_validees(): void
    {
        $e = $this->employe();
        $this->actingAs($this->user('rh'));
        $this->post('/rh/conges', $this->demande($e, ['type_code' => 'MALADIE', 'date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'justificatif' => UploadedFile::fake()->create('c.pdf', 50, 'application/pdf')]));
        $a = Absence::first();
        $this->actingAs($this->user('direction'))->post("/rh/conges/{$a->id}/decider", ['decision' => 'valider']);
        $this->actingAs($this->user('rh'));

        $this->assertSame(2, Presence::where('employe_id', $e->id)->where('type', 'absence')->count());
        $this->post("/presences/{$e->id}/pointer", ['date' => '2026-03-02', 'type' => 'présence'])->assertSessionHasErrors('type');
        $this->get('/presences?date=2026-03-02')->assertOk()->assertSee('Issu d');
        $this->get('/presences?date=2026-03-02')->assertSee('Absents')->assertSee('Non pointés');
        $this->post("/presences/{$e->id}/pointer", ['date' => '2026-03-04', 'type' => 'congé'])->assertSessionHasErrors('type');
    }

    public function test_conges_maternite_paternite_convalescence(): void
    {
        $e = $this->employe();
        $this->actingAs($this->user('rh'));
        foreach (['MATERNITE' => 'Congé de maternité', 'PATERNITE' => 'Congé de paternité', 'CONVALESCENCE' => 'Congé de convalescence'] as $code => $libelle) {
            $this->post('/rh/conges', $this->demande($e, ['type_code' => $code, 'date_debut' => '2026-03-02', 'date_fin' => '2026-03-03']))->assertSessionHasErrors('justificatif'); // justificatif exigé
            $this->post('/rh/conges', $this->demande($e, ['type_code' => $code, 'date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'justificatif' => UploadedFile::fake()->create('j.pdf', 20, 'application/pdf')]))->assertSessionHasNoErrors();
            $this->get('/rh/conges')->assertSee($libelle);
            $this->get('/rh/absences')->assertDontSee($libelle);
            Absence::query()->delete();
        }
    }

    public function test_liste_des_contrats_affiche_cdd_cdi_et_progression(): void
    {
        $e = $this->employe();
        $this->actingAs($this->user('rh'));
        $debut = today()->subDays(10)->toDateString();
        $this->post('/rh/contrats', ['employe_id' => $e->id, 'type_code' => 'CDD', 'date_debut' => $debut, 'date_fin' => today()->addDays(20)->toDateString(), 'salaire' => 100000]);

        $page = $this->get('/rh/contrats')->assertOk();
        $page->assertSee('CDD — Contrat à durée déterminée', false)->assertSee('Reste 20 j')->assertSee('Terminer le contrat');
        $c = Contrat::first();
        $this->assertSame(33, $c->avancement);
        $this->assertSame('info', $c->type_tone);

        $this->post("/rh/contrats/{$c->id}/terminer", ['termine_le' => today()->toDateString(), 'motif_fin' => 'Fin de mission']);
        $this->post('/rh/contrats', ['employe_id' => $e->id, 'type_code' => 'CDI', 'date_debut' => today()->addDay()->toDateString()]);
        $this->get('/rh/contrats')->assertSee('Durée indéterminée')->assertSee('CDI');
        $this->assertNull(Contrat::where('type_code', 'CDI')->first()->avancement);
    }
}

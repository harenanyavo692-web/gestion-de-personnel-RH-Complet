<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\Employe;
use App\Models\Presence;
use App\Models\ReferenceItem;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Demandes de congés et d'absences : calcul des jours, soldes, workflow de validation, impact sur les présences. */
class AbsenceService
{
    public function __construct(private ParametreService $params) {}

    private function erreur(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }

    /** @return list<Carbon> jours ouvrés (lundi à vendredi) de la période */
    public function joursOuvres(Carbon|string $debut, Carbon|string $fin): array
    {
        return collect(CarbonPeriod::create($debut, $fin))->filter(fn ($d) => $d->isWeekday())->values()->all();
    }

    public function annee(): int
    {
        return (int) ($this->params->get('rh.general')['annee_reference'] ?: now()->year);
    }

    /** Solde d'un type pour un employé : plafond annuel, jours validés ou demandés, reste. Plafond null = illimité. */
    public function solde(Employe $employe, ReferenceItem $type, ?int $annee = null, ?int $ignorerId = null): array
    {
        $annee ??= $this->annee();
        $plafond = $type->meta['jours_annuels'] ?? null;
        $pris = (float) Absence::where('employe_id', $employe->id)->where('type_code', $type->code)
            ->whereIn('statut', ['validee', 'en_attente'])->whereYear('date_debut', $annee)
            ->when($ignorerId, fn ($q) => $q->where('id', '!=', $ignorerId))->sum('jours');

        return ['plafond' => $plafond, 'pris' => $pris, 'reste' => $plafond === null ? null : $plafond - $pris];
    }

    public function creer(array $d, User $user, ?UploadedFile $justificatif): Absence
    {
        return DB::transaction(function () use ($d, $user, $justificatif) {
            $employe = Employe::findOrFail($d['employe_id']);
            $type = ReferenceItem::where('groupe', 'type_absence')->where('code', $d['type_code'])->where('actif', true)->first()
                ?? $this->erreur('type_code', "Ce type d'absence n'est pas disponible.");
            if ($employe->statut === 'inactif') {
                $this->erreur('employe_id', 'Cet employé est inactif.');
            }

            $jours = count($this->joursOuvres($d['date_debut'], $d['date_fin']));
            if ($jours === 0) {
                $this->erreur('date_debut', 'La période ne contient aucun jour ouvré.');
            }
            if (Absence::where('employe_id', $employe->id)->whereIn('statut', ['en_attente', 'validee'])
                ->whereDate('date_debut', '<=', $d['date_fin'])->whereDate('date_fin', '>=', $d['date_debut'])->exists()) {
                $this->erreur('date_debut', 'Cet employé a déjà une absence (en attente ou validée) sur cette période.');
            }
            if (($type->meta['justificatif'] ?? false) && ! $justificatif) {
                $this->erreur('justificatif', "Un justificatif est exigé pour ce type d'absence.");
            }

            $solde = $this->solde($employe, $type, Carbon::parse($d['date_debut'])->year);
            if ($solde['reste'] !== null && $jours > $solde['reste'] && ! $this->params->get('rh.workflow')['solde_negatif_autorise']) {
                $this->erreur('date_fin', "Solde insuffisant : {$solde['reste']} jour(s) restant(s) pour « {$type->libelle} », {$jours} demandé(s).");
            }

            $auto = ! $this->params->get('rh.workflow')['validation_requise'];
            $absence = Absence::create([
                'employe_id' => $employe->id, 'type_code' => $type->code, 'date_debut' => $d['date_debut'], 'date_fin' => $d['date_fin'],
                'jours' => $jours, 'motif' => $d['motif'] ?? null, 'demande_par' => $user->id,
                'justificatif_chemin' => $justificatif?->store('absences'), 'justificatif_nom' => $justificatif?->getClientOriginalName(),
                'statut' => $auto ? 'validee' : 'en_attente', 'traite_par' => $auto ? $user->id : null, 'traite_le' => $auto ? now() : null,
            ]);
            if ($auto) {
                $this->marquerPresences($absence);
            }
            AuditLogger::log('absence.demandee', $absence, ['employe' => $employe->nom_complet, 'type' => $type->code, 'jours' => $jours, 'auto_validee' => $auto]);

            return $absence;
        });
    }

    public function traiter(Absence $absence, string $decision, ?string $commentaire, User $user): Absence
    {
        return DB::transaction(function () use ($absence, $decision, $commentaire, $user) {
            $a = Absence::whereKey($absence->id)->lockForUpdate()->firstOrFail();
            if ($a->statut !== 'en_attente') {
                $this->erreur('decision', 'Cette demande a déjà été traitée.');
            }
            $a->update([
                'statut' => $decision === 'valider' ? 'validee' : 'refusee',
                'traite_par' => $user->id, 'traite_le' => now(), 'commentaire' => $commentaire,
            ]);
            if ($decision === 'valider') {
                $this->marquerPresences($a);
            }
            AuditLogger::log($decision === 'valider' ? 'absence.validee' : 'absence.refusee', $a, ['employe_id' => $a->employe_id, 'jours' => (string) $a->jours]);

            return $a;
        });
    }

    public function annuler(Absence $absence): Absence
    {
        return DB::transaction(function () use ($absence) {
            $a = Absence::whereKey($absence->id)->lockForUpdate()->firstOrFail();
            if (! in_array($a->statut, ['en_attente', 'validee'], true)) {
                $this->erreur('absence', 'Cette demande ne peut plus être annulée.');
            }
            if ($a->statut === 'validee') {
                Presence::where('employe_id', $a->employe_id)->where('note', "absence #{$a->id}")->delete();
            }
            $a->update(['statut' => 'annulee']);
            AuditLogger::log('absence.annulee', $a, ['employe_id' => $a->employe_id]);

            return $a;
        });
    }

    /** Une demande validée apparaît au pointage sur chaque jour ouvré : congé, absence ou mission. */
    private function marquerPresences(Absence $a): void
    {
        $type = $a->type_code === 'MISSION' ? 'mission'
            : (Absence::natureDuType(ReferenceItem::where('groupe', 'type_absence')->where('code', $a->type_code)->firstOrFail()) === 'conge' ? 'congé' : 'absence');

        foreach ($this->joursOuvres($a->date_debut, $a->date_fin) as $jour) {
            Presence::updateOrCreate(
                ['employe_id' => $a->employe_id, 'date' => $jour->toDateString()],
                ['type' => $type, 'heure_arrivee' => null, 'note' => "absence #{$a->id}"],
            );
        }
    }
}

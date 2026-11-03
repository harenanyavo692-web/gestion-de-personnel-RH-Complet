<?php

namespace App\Services;

use App\Models\Employe;
use App\Models\HeureSupplementaire;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HeuresSupService
{
    /** Paie toutes les heures supplémentaires impayées d'un employé pour un mois (AAAA-MM). */
    public function payer(Employe $employe, string $mois, User $payeur): float
    {
        return DB::transaction(function () use ($employe, $mois, $payeur) {
            $employe = Employe::whereKey($employe->id)->lockForUpdate()->firstOrFail();

            if ($employe->statut === 'inactif' || (float) $employe->salaire <= 0) {
                throw ValidationException::withMessages(['employe' => "Cet employé n'a pas de salaire de référence."]);
            }

            $lignes = HeureSupplementaire::where('employe_id', $employe->id)
                ->whereNull('date_paiement')
                ->where('date', 'like', "{$mois}-%")
                ->lockForUpdate()->get();

            if ($lignes->isEmpty()) {
                throw ValidationException::withMessages(['employe' => 'Aucune heure supplémentaire à payer pour ce mois.']);
            }

            $total = 0.0;
            foreach ($lignes as $l) {
                $montant = $l->calculerMontant((float) $employe->salaire);
                $l->update(['montant' => $montant, 'date_paiement' => now()->toDateString(), 'paye_par' => $payeur->id]);
                $total += $montant;
            }

            AuditLogger::log('heures_sup.payees', $employe, [
                'employe' => $employe->nom_complet,
                'mois' => $mois,
                'heures' => (string) $lignes->sum('heures'),
                'montant' => (string) $total,
            ]);

            return $total;
        });
    }
}

<?php

namespace App\Services;

use App\Models\Employe;
use App\Models\PaiementSalaire;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaiementSalaireService
{
    /** Enregistre le salaire d'un employé pour un mois (AAAA-MM), une seule fois. */
    public function payer(Employe $employe, string $mois, User $payeur): PaiementSalaire
    {
        return DB::transaction(function () use ($employe, $mois, $payeur) {
            $employe = Employe::whereKey($employe->id)->lockForUpdate()->firstOrFail();

            if ($employe->statut === 'inactif' || (float) $employe->salaire <= 0) {
                throw ValidationException::withMessages(['employe' => "Cet employé n'a pas de salaire payable."]);
            }

            if (PaiementSalaire::where('employe_id', $employe->id)->where('mois', $mois)->exists()) {
                throw ValidationException::withMessages(['employe' => 'Salaire déjà payé pour ce mois.']);
            }

            $paiement = PaiementSalaire::create([
                'employe_id' => $employe->id,
                'mois' => $mois,
                'montant' => $employe->salaire,
                'date_paiement' => now()->toDateString(),
                'paye_par' => $payeur->id,
            ]);

            AuditLogger::log('salaire.paye', $paiement, [
                'employe' => $employe->nom_complet,
                'mois' => $mois,
                'montant' => (string) $paiement->montant,
            ]);

            return $paiement;
        });
    }
}

<?php

namespace App\Services;

use App\Models\OrdreDepense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValidationDepenseService
{
    /**
     * Valide ou refuse un ordre de dépense de façon atomique.
     * Le verrou évite qu'un ordre soit traité deux fois en parallèle.
     */
    public function traiter(OrdreDepense $ordre, string $decision, ?string $commentaire, User $raf): OrdreDepense
    {
        return DB::transaction(function () use ($ordre, $decision, $commentaire, $raf) {
            $ordre = OrdreDepense::whereKey($ordre->id)->lockForUpdate()->firstOrFail();

            if ($ordre->statut !== 'en_attente') {
                throw ValidationException::withMessages(['decision' => 'Cet ordre a déjà été traité.']);
            }

            $valide = $decision === 'valider';

            $ordre->update([
                'statut' => $valide ? 'validee' : 'refusee',
                'commentaire_raf' => $commentaire,
                'traite_par' => $raf->id,
                'traite_le' => now(),
            ]);

            AuditLogger::log($valide ? 'depense.validee' : 'depense.refusee', $ordre, [
                'reference' => $ordre->reference,
                'montant' => (string) $ordre->montant,
            ]);

            return $ordre;
        });
    }
}

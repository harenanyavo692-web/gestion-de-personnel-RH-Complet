<?php

namespace App\Policies;

use App\Models\OrdreDepense;
use App\Models\User;
use App\Services\ParametreService;

class OrdreDepensePolicy
{
    /** Le RAF valide, mais jamais un ordre qu'il a lui-même soumis (séparation des tâches). */
    public function valider(User $user, OrdreDepense $ordre): bool
    {
        $seuil = (float) app(ParametreService::class)->get('compta.securite')['seuil_double_validation'];
        // Au-delà du seuil, la validation est réservée aux détenteurs du droit « valider_haut » (direction).
        $autorise = $seuil > 0 && (float) $ordre->montant >= $seuil
            ? $user->hasPermission('depenses.valider_haut')
            : $user->hasPermission('depenses.valider');

        return $autorise
            && $ordre->statut === 'en_attente'
            && $ordre->soumis_par !== $user->id;
    }

    /** Annulation par son auteur, tant que l'ordre n'est pas traité. */
    public function annuler(User $user, OrdreDepense $ordre): bool
    {
        return $user->hasPermission('depenses.soumettre')
            && $ordre->statut === 'en_attente'
            && $ordre->soumis_par === $user->id;
    }
}

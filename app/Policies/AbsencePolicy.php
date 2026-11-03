<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;
use App\Services\ParametreService;

class AbsencePolicy
{
    /**
     * Qui peut décider dépend du workflow paramétré (RH › Workflow de validation) :
     * le service RH, la direction, ou le responsable de l'entité de l'employé (la direction peut suppléer).
     * Personne ne traite sa propre demande (rapprochement par e-mail employé / utilisateur).
     */
    public function traiter(User $user, Absence $absence): bool
    {
        if ($absence->statut !== 'en_attente') {
            return false;
        }
        $absence->loadMissing('employe.entite.responsable');
        if (strcasecmp((string) $absence->employe->email, $user->email) === 0) {
            return false;
        }

        return match (app(ParametreService::class)->get('rh.workflow')['validateur']) {
            'rh' => $user->hasPermission('conges.valider'),
            'direction' => $user->hasPermission('conges.valider_direction'),
            default => $user->hasPermission('conges.valider_direction')
                || ($absence->employe->entite?->responsable && strcasecmp($absence->employe->entite->responsable->email, $user->email) === 0 && $user->hasPermission('conges.voir')),
        };
    }

    public function annuler(User $user, Absence $absence): bool
    {
        return $user->hasPermission('conges.gerer') && in_array($absence->statut, ['en_attente', 'validee'], true);
    }
}

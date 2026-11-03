<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Contrat;
use App\Models\Employe;
use App\Models\LettreRh;
use App\Models\OrdreDepense;
use App\Models\Recette;
use App\Models\Stagiaire;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Éléments qui peuvent être mis à la corbeille ou archivés (même liste pour les deux écrans).
 * type => [modèle, libellé, périmètre ('rh' ou 'admin'), permission qui permet d'archiver / restaurer].
 */
class Elements
{
    public const TYPES = [
        'employes' => [Employe::class, 'Employés', 'rh', 'employes.gerer'],
        'stagiaires' => [Stagiaire::class, 'Stagiaires', 'rh', 'employes.gerer'],
        'contrats' => [Contrat::class, 'Contrats', 'rh', 'contrats.gerer'],
        'lettres' => [LettreRh::class, 'Lettres RH', 'rh', 'lettres.generer'],
        'clients' => [Client::class, 'Clients', 'admin', 'clients.gerer'],
        'recettes' => [Recette::class, 'Recettes', 'admin', 'recettes.gerer'],
        'depenses' => [OrdreDepense::class, 'Ordres de dépense', 'admin', 'depenses.soumettre'],
    ];

    /**
     * Types accessibles à un utilisateur : tous avec la permission « admin », ceux du périmètre RH avec la permission « rh ».
     *
     * @return array<string, array>
     */
    public static function accessibles(User $u, string $permAdmin, string $permRh): array
    {
        return array_filter(self::TYPES, fn ($d) => $u->can($permAdmin) || ($d[2] === 'rh' && $u->can($permRh)));
    }

    /** Peut-il archiver / désarchiver ce type ? (droit de gestion du module, ou administrateur des archives) */
    public static function peutGerer(User $u, string $type): bool
    {
        return isset(self::TYPES[$type]) && ($u->can(self::TYPES[$type][3]) || $u->can('admin.archives'));
    }

    /** Raison pour laquelle l'élément ne peut pas être archivé, ou null s'il le peut. */
    public static function refusArchivage(string $type, Model $m): ?string
    {
        if ($m->archived_at) {
            return 'Cet élément est déjà archivé.';
        }

        return match ($type) {
            'employes' => $m->statut === 'inactif' ? null : 'Seul un employé inactif peut être archivé (passez-le d\'abord en statut « inactif »).',
            'contrats' => ($m->statut !== 'actif' || $m->expire) ? null : 'Un contrat en cours ne peut pas être archivé : terminez-le d\'abord.',
            'depenses' => $m->statut === 'en_attente' ? 'Un ordre en attente de validation ne peut pas être archivé.' : null,
            default => null,
        };
    }
}

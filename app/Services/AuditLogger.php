<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /** Journalise une action sensible (utilisateur et IP issus de la requête courante). */
    public static function log(string $action, ?Model $cible = null, array $details = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'cible_type' => $cible ? $cible::class : null,
            'cible_id' => $cible?->getKey(),
            'details' => $details ?: null,
            'ip' => request()->ip(),
        ]);
    }
}

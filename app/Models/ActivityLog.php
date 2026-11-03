<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Journal d'activité : qui a fait quoi, quand, depuis quelle adresse (table audit_logs). */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $fillable = ['user_id', 'action', 'cible_type', 'cible_id', 'details', 'ip'];

    protected $casts = ['details' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Module = premier segment de l'action (« depense.validee » → « depense »). */
    public function getModuleAttribute(): string
    {
        return explode('.', $this->action)[0];
    }

    /** Ton du badge : rouge pour les suppressions/refus, vert pour les créations/validations. */
    public function getToneAttribute(): string
    {
        return match (true) {
            (bool) preg_match('/(supprim|refus|annul|echec|purge|vide)/', $this->action) => 'danger',
            (bool) preg_match('/(cree|creee|valid|restaur|ajout|encaiss|paye|ouvert|ouvre)/', $this->action) => 'success',
            (bool) preg_match('/(modifi|statut|clotur|transfer)/', $this->action) => 'warning',
            default => 'info',
        };
    }
}

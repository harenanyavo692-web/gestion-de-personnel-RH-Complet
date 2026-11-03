<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Support\Str;

/**
 * Journalise automatiquement les événements Eloquent d'un modèle.
 * Un modèle choisit ses événements via $activityEvents (created, updated, deleted, restored, forceDeleted)
 * pour ne pas doubler les journaux explicites déjà écrits par les services métier.
 */
trait LogsActivity
{
    /** Champs jamais écrits dans le journal. */
    protected array $activityHidden = ['password', 'remember_token', 'updated_at', 'created_at', 'deleted_at'];

    public static function bootLogsActivity(): void
    {
        $evenements = (new static)->activityEvents ?? ['created', 'updated', 'deleted'];
        $slug = Str::snake(class_basename(static::class));

        if (in_array('created', $evenements, true)) {
            static::created(fn ($m) => AuditLogger::log("$slug.cree", $m, ['libelle' => $m->libelleActivite()]));
        }
        if (in_array('updated', $evenements, true)) {
            static::updated(function ($m) use ($slug) {
                $diff = collect($m->getChanges())->except($m->activityHidden)
                    ->map(fn ($nouveau, $champ) => ['avant' => $m->getOriginal($champ), 'apres' => $nouveau])->all();
                if ($diff) {
                    AuditLogger::log("$slug.modifie", $m, ['libelle' => $m->libelleActivite(), 'changements' => $diff]);
                }
            });
        }
        if (in_array('deleted', $evenements, true)) {
            static::deleted(function ($m) use ($slug) {
                if (! (method_exists($m, 'isForceDeleting') && $m->isForceDeleting())) {
                    AuditLogger::log("$slug.supprime", $m, ['libelle' => $m->libelleActivite()]);
                }
            });
        }
        if (in_array('restored', $evenements, true) && method_exists(static::class, 'restored')) {
            static::restored(fn ($m) => AuditLogger::log("$slug.restaure", $m, ['libelle' => $m->libelleActivite()]));
        }
        if (in_array('forceDeleted', $evenements, true) && method_exists(static::class, 'forceDeleted')) {
            static::forceDeleted(fn ($m) => AuditLogger::log("$slug.supprime_definitivement", $m, ['libelle' => $m->libelleActivite()]));
        }
    }

    /** Texte lisible identifiant l'enregistrement dans le journal. */
    public function libelleActivite(): string
    {
        return (string) ($this->nom_complet ?? $this->nom ?? $this->reference ?? $this->motif ?? $this->name ?? '#' . $this->getKey());
    }
}

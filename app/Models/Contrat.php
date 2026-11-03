<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contrat extends Model
{
    use SoftDeletes, LogsActivity, Archivable;

    /** Événements journalisés automatiquement (les autres sont déjà écrits par les services métier). */
    public array $activityEvents = ['deleted', 'restored', 'forceDeleted'];

    protected $guarded = [];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date', 'termine_le' => 'date', 'salaire' => 'decimal:2'];

    public function employe()
    {
        return $this->belongsTo(Employe::class);
    }

    public function getTypeLibelleAttribute(): string
    {
        return ReferenceItem::where('groupe', 'type_contrat')->where('code', $this->type_code)->value('libelle') ?? $this->type_code;
    }

    /** Couleur du badge du type : CDI vert, CDD bleu, stage orange, autres neutre. */
    public function getTypeToneAttribute(): string
    {
        return ['CDI' => 'success', 'CDD' => 'info', 'STAGE' => 'warning'][$this->type_code] ?? 'neutral';
    }

    /** Avancement d'un contrat à durée déterminée, de 0 à 100 (null si durée indéterminée). */
    public function getAvancementAttribute(): ?int
    {
        if (! $this->date_fin) {
            return null;
        }
        $total = max($this->date_debut->diffInDays($this->date_fin), 1);
        $fait = $this->date_debut->isFuture() ? 0 : $this->date_debut->diffInDays(min(today(), $this->date_fin));

        return (int) min(100, round($fait / $total * 100));
    }

    /** Un contrat à échéance dépassée est signalé comme expiré sans changer son statut stocké. */
    public function getExpireAttribute(): bool
    {
        return $this->statut === 'actif' && $this->date_fin && $this->date_fin->lt(today());
    }
}

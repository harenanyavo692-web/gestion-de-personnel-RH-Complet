<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes, LogsActivity, Archivable;

    /** Événements journalisés automatiquement (les autres sont déjà écrits par les services métier). */
    public array $activityEvents = ['deleted', 'restored', 'forceDeleted'];

    protected $fillable = ['nom', 'contact', 'special', 'conditions', 'actif', 'volume_historique', 'tendance'];

    public const TENDANCES = ['stable' => 'Stable', 'hausse' => 'En hausse', 'baisse' => 'En baisse (alerte)'];
    protected $casts = ['special' => 'boolean', 'actif' => 'boolean'];

    public function lots()
    {
        return $this->hasMany(Lot::class);
    }
}

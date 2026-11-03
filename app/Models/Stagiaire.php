<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stagiaire extends Model
{
    use SoftDeletes, LogsActivity, Archivable;

    /** La suppression est journalisée par le contrôleur ; restauration et purge le sont ici. */
    public array $activityEvents = ['restored', 'forceDeleted'];

    protected $fillable = [
        'nom', 'prenom', 'email', 'telephone', 'etablissement', 'filiere',
        'entity_id', 'tuteur_id', 'date_debut', 'date_fin', 'gratification', 'note',
        'certificat_numero', 'certificat_le', 'certificat_par', 'appreciation', 'commentaire_certificat',
    ];

    public const APPRECIATIONS = ['passable' => 'Passable', 'bien' => 'Bien', 'tres_bien' => 'Très bien', 'excellent' => 'Excellent'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'gratification' => 'decimal:2',
        'certificat_le' => 'date',
    ];

    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    /** À venir, en cours ou terminé selon les dates. */
    public function getStatutAttribute(): string
    {
        return match (true) {
            $this->date_debut->isFuture() => 'à venir',
            $this->date_fin->isPast() && ! $this->date_fin->isToday() => 'terminé',
            default => 'en cours',
        };
    }

    public function getCertifieAttribute(): bool
    {
        return $this->certificat_numero !== null;
    }

    /** Un certificat se délivre une fois le stage terminé, jamais avant. */
    public function getCertifiableAttribute(): bool
    {
        return $this->statut === 'terminé';
    }

    public function entite()
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function tuteur()
    {
        return $this->belongsTo(Employe::class, 'tuteur_id');
    }
}

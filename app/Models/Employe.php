<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employe extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, Archivable;

    public array $activityEvents = ['created', 'updated', 'deleted', 'restored', 'forceDeleted'];

    protected $fillable = [
        'matricule',
        'nom',
        'prenom',
        'email',
        'telephone',
        'poste',
        'departement',
        'date_embauche',
        'salaire',
        'statut',
        'photo',
        'entity_id',
        'adresse',
    ];

    protected $casts = [
        'date_embauche' => 'date',
        'salaire' => 'decimal:2',
    ];

    /**
     * Nom complet affiché dans l'interface.
     */
    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    /**
     * Ancienneté en années, utile pour les tableaux RH.
     */
    public function getAncienneteAttribute(): int
    {
        return (int) $this->date_embauche->diffInYears(now());
    }

    /**
     * Couleur du badge de statut pour l'UI.
     */
    public function getStatutColorAttribute(): string
    {
        return match ($this->statut) {
            'actif' => 'emerald',
            'congé' => 'amber',
            'suspendu' => 'orange',
            'inactif' => 'rose',
            default => 'slate',
        };
    }

    public function presences()
    {
        return $this->hasMany(Presence::class);
    }

    public function entite()
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function contrats()
    {
        return $this->hasMany(Contrat::class);
    }

    public function heuresSupplementaires()
    {
        return $this->hasMany(HeureSupplementaire::class);
    }

    public function paiementsSalaires()
    {
        return $this->hasMany(PaiementSalaire::class);
    }

    public function scopeRecherche($query, ?string $terme)
    {
        if (! $terme) {
            return $query;
        }

        return $query->where(function ($q) use ($terme) {
            $q->where('nom', 'like', "%{$terme}%")
              ->orWhere('prenom', 'like', "%{$terme}%")
              ->orWhere('matricule', 'like', "%{$terme}%")
              ->orWhere('poste', 'like', "%{$terme}%");
        });
    }
}

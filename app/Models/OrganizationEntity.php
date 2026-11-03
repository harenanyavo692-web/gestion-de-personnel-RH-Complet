<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationEntity extends Model
{
    public const TYPES = [
        'direction' => 'Direction',
        'departement' => 'Département',
        'service' => 'Service',
        'unite' => 'Unité',
        'bureau' => 'Bureau / antenne',
    ];

    protected $fillable = ['code', 'nom', 'type', 'description', 'parent_id', 'responsable_id', 'localisation', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function enfants()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function responsable()
    {
        return $this->belongsTo(Employe::class, 'responsable_id');
    }

    public function employes()
    {
        return $this->hasMany(Employe::class, 'entity_id');
    }

    public function entitesComptables()
    {
        return $this->hasMany(AccountingEntity::class);
    }

    public function getTypeLibelleAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Identifiants de tous les descendants (sert à interdire les cycles). */
    public function descendantIds(): array
    {
        $ids = [];
        $front = [$this->id];
        while ($front) {
            $front = self::whereIn('parent_id', $front)->pluck('id')->all();
            $ids = array_merge($ids, $front);
        }

        return $ids;
    }

    /** Chemin hiérarchique lisible : Direction › Département › Service. */
    public function getCheminAttribute(): string
    {
        $noms = [$this->nom];
        $p = $this->parent;
        $garde = 0;
        while ($p && $garde++ < 20) {
            array_unshift($noms, $p->nom);
            $p = $p->parent;
        }

        return implode(' › ', $noms);
    }
}

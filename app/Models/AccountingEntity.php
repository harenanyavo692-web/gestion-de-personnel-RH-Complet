<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingEntity extends Model
{
    public const TYPES = [
        'centre_cout' => 'Centre de coûts',
        'section_budgetaire' => 'Section budgétaire',
        'projet' => 'Projet / activité',
        'service_budget' => 'Service responsable de budget',
        'perimetre' => 'Périmètre comptable',
    ];

    protected $fillable = [
        'code', 'nom', 'type', 'description', 'parent_id', 'organization_entity_id',
        'responsable_id', 'exercise_id', 'budget_reference', 'actif',
    ];

    protected $casts = ['actif' => 'boolean', 'budget_reference' => 'decimal:2'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function enfants()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function organisation()
    {
        return $this->belongsTo(OrganizationEntity::class, 'organization_entity_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function exercice()
    {
        return $this->belongsTo(AccountingExercise::class, 'exercise_id');
    }

    public function recettes()
    {
        return $this->hasMany(Recette::class);
    }

    public function depenses()
    {
        return $this->hasMany(OrdreDepense::class);
    }

    public function getTypeLibelleAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Vrai si au moins une recette ou une dépense (même supprimée logiquement) y est rattachée. */
    public function aDesOperations(): bool
    {
        return Recette::withTrashed()->where('accounting_entity_id', $this->id)->exists()
            || OrdreDepense::where('accounting_entity_id', $this->id)->exists();
    }

    /** Dépenses validées rattachées à l'entité et à ses entités filles. */
    public function depenseConsommee(): float
    {
        $ids = array_merge([$this->id], $this->enfants()->pluck('id')->all());

        return (float) OrdreDepense::whereIn('accounting_entity_id', $ids)->where('statut', 'validee')->sum('montant');
    }
}

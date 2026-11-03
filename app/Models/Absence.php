<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absence extends Model
{
    public const STATUTS = ['en_attente' => 'En attente', 'validee' => 'Validée', 'refusee' => 'Refusée', 'annulee' => 'Annulée'];

    protected $guarded = [];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date', 'traite_le' => 'datetime', 'jours' => 'decimal:1'];

    /** Un type plafonné en jours/an est un congé ; sinon (maladie, mission…) c'est une absence. */
    public static function natureDuType(ReferenceItem $type): string
    {
        return ($type->meta['nature'] ?? null) ?: (($type->meta['jours_annuels'] ?? null) !== null ? 'conge' : 'absence');
    }

    public function employe()
    {
        return $this->belongsTo(Employe::class)->withTrashed();
    }

    public function demandeur()
    {
        return $this->belongsTo(User::class, 'demande_par');
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function getTypeLibelleAttribute(): string
    {
        return ReferenceItem::where('groupe', 'type_absence')->where('code', $this->type_code)->value('libelle') ?? $this->type_code;
    }
}

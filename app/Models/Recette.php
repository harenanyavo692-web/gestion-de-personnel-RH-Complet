<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recette extends Model
{
    use SoftDeletes, LogsActivity, Archivable;

    public array $activityEvents = ['restored', 'forceDeleted'];

    protected $fillable = [
        'reference', 'numero_bon', 'date_recette', 'montant', 'montant_facture', 'categorie_id', 'origine',
        'accounting_entity_id', 'journal_id', 'account_id', 'mode_paiement',
        'client', 'espece', 'poids_kg', 'destination', 'note', 'saisi_par',
    ];

    protected $casts = [
        'date_recette' => 'date',
        'montant' => 'decimal:2',
        'montant_facture' => 'decimal:2',
    ];

    public function categorie()
    {
        return $this->belongsTo(CategorieFinanciere::class, 'categorie_id');
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    public function pieces()
    {
        return $this->morphMany(PieceJustificative::class, 'piece');
    }

    public function versementSpecial()
    {
        return $this->hasOne(VersementSpecial::class);
    }
}

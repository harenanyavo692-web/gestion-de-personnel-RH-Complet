<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VersementSpecial extends Model
{
    protected $table = 'versements_speciaux';

    protected $fillable = ['compte_special_id', 'recette_id', 'montant', 'date_versement'];

    protected $casts = ['date_versement' => 'date', 'montant' => 'decimal:2'];

    public function compte()
    {
        return $this->belongsTo(CompteSpecial::class, 'compte_special_id');
    }

    public function recette()
    {
        return $this->belongsTo(Recette::class);
    }
}

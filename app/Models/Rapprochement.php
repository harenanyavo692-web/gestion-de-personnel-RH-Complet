<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rapprochement extends Model
{
    protected $fillable = [
        'periode', 'date_arrete', 'solde_livres', 'solde_tresor', 'ecart_final', 'statut', 'cree_par',
    ];

    protected $casts = [
        'date_arrete' => 'date',
        'solde_livres' => 'decimal:2',
        'solde_tresor' => 'decimal:2',
        'ecart_final' => 'decimal:2',
    ];

    public function ecarts()
    {
        return $this->hasMany(RapprochementEcart::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}

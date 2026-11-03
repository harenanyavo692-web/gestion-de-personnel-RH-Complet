<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaiementSalaire extends Model
{
    protected $table = 'paiements_salaires';

    protected $fillable = ['employe_id', 'mois', 'montant', 'date_paiement', 'paye_par'];

    protected $casts = [
        'date_paiement' => 'date',
        'montant' => 'decimal:2',
    ];

    public function employe()
    {
        return $this->belongsTo(Employe::class);
    }

    public function payeur()
    {
        return $this->belongsTo(User::class, 'paye_par');
    }
}

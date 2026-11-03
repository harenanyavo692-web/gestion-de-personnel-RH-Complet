<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingJournal extends Model
{
    public const TYPES = [
        'caisse' => 'Caisse', 'banque' => 'Banque', 'achats' => 'Achats',
        'ventes' => 'Ventes / recettes', 'operations_diverses' => 'Opérations diverses',
    ];

    protected $fillable = ['code', 'libelle', 'type', 'actif'];

    protected $casts = ['actif' => 'boolean'];
}

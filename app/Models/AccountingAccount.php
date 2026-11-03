<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingAccount extends Model
{
    public const TYPES = ['actif' => 'Actif', 'passif' => 'Passif', 'charge' => 'Charge', 'produit' => 'Produit'];

    protected $fillable = ['numero', 'libelle', 'classe', 'type', 'actif'];

    protected $casts = ['actif' => 'boolean'];
}

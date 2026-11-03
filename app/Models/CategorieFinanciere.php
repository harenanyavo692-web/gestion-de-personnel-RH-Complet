<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategorieFinanciere extends Model
{
    protected $table = 'categories_financieres';

    protected $fillable = ['nom', 'type'];

    public function scopeRecettes($q)
    {
        return $q->where('type', 'recette')->orderBy('nom');
    }

    public function scopeDepenses($q)
    {
        return $q->where('type', 'depense')->orderBy('nom');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceItem extends Model
{
    protected $fillable = ['domaine', 'groupe', 'code', 'libelle', 'meta', 'ordre', 'actif'];

    protected $casts = ['meta' => 'array', 'actif' => 'boolean'];

    public function scopeGroupe($q, string $groupe)
    {
        return $q->where('groupe', $groupe)->orderBy('ordre')->orderBy('libelle');
    }

    public function scopeActifs($q)
    {
        return $q->where('actif', true);
    }
}

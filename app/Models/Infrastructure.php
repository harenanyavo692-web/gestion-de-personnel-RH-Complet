<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Infrastructure extends Model
{
    protected $fillable = ['nom', 'type', 'capacite_kg', 'statut'];

    public function lots()
    {
        return $this->hasMany(Lot::class);
    }

    public function getOccupationAttribute(): float
    {
        return (float) $this->lots()->where('statut', 'en_stock')->sum('stock_restant');
    }
}

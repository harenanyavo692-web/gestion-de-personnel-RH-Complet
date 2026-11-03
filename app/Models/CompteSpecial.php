<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompteSpecial extends Model
{
    protected $table = 'comptes_speciaux';

    protected $fillable = ['reference', 'client', 'motif', 'montant_convenu', 'cree_par'];

    protected $casts = ['montant_convenu' => 'decimal:2'];

    public function versements()
    {
        return $this->hasMany(VersementSpecial::class, 'compte_special_id');
    }

    public function getPayeAttribute(): float
    {
        return (float) ($this->versements_sum_montant ?? $this->versements->sum('montant'));
    }

    public function getResteAttribute(): float
    {
        return max(0, (float) $this->montant_convenu - $this->paye);
    }
}

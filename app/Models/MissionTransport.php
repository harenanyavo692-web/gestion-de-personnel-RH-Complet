<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissionTransport extends Model
{
    protected $table = 'missions_transport';

    protected $guarded = [];

    protected $casts = [
        'poids_kg' => 'decimal:2', 'cout' => 'decimal:2', 'cout_standard' => 'decimal:2',
        'negocie' => 'boolean', 'valide_le' => 'datetime',
    ];

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }
}

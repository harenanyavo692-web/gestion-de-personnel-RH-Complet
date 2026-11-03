<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicule extends Model
{
    public const TYPES = ['poids_lourd' => 'Poids lourd', 'vehicule_leger' => 'Véhicule léger', 'maritime' => 'Maritime'];

    public const STATUTS = ['disponible' => 'Disponible', 'en_transit' => 'En transit', 'maintenance' => 'Maintenance'];

    protected $fillable = ['nom', 'immatriculation', 'type', 'statut'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetardTunnel extends Model
{
    protected $table = 'retards_tunnel';
    protected $fillable = ['lot', 'client', 'poids_kg', 'heures_tunnel', 'responsabilite', 'taux_applique', 'montant_tunnel', 'penalite', 'saisi_par'];
    protected $casts = ['montant_tunnel' => 'decimal:2', 'penalite' => 'decimal:2', 'poids_kg' => 'decimal:2'];
}

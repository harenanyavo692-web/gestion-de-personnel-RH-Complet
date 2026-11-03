<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date_entree' => 'datetime',
        'date_entree_chambre' => 'datetime',
        'poids_entree' => 'decimal:2',
        'stock_restant' => 'decimal:2',
        'montant_tunnel' => 'decimal:2',
        'penalite_tunnel' => 'decimal:2',
        'montant_froid_encaisse' => 'decimal:2',
        'tarif_negocie' => 'decimal:2',
        'tarif_chambre' => 'decimal:2',
        'duree_indeterminee' => 'boolean',
        'paye_tunnel' => 'boolean',
        'paye_froid' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function infrastructure()
    {
        return $this->belongsTo(Infrastructure::class);
    }

    public function sorties()
    {
        return $this->hasMany(SortiePartielle::class);
    }

    public function getTarifStockageAttribute(): float
    {
        return (float) ($this->tarif_negocie ?: $this->tarif_chambre);
    }

    /** Heures écoulées depuis l'entrée en tunnel. */
    public function getHeuresDepuisEntreeAttribute(): int
    {
        return (int) floor($this->date_entree->diffInMinutes(now()) / 60);
    }

    /** Jours facturables en chambre : au minimum 1. */
    public function getJoursChambreAttribute(): float
    {
        $debut = $this->date_entree_chambre ?? $this->date_entree;

        return max($debut->diffInMinutes(now()) / 60 / 24, 1);
    }

    /** Une dette existe tant qu'un volet (tunnel / froid) du lot n'est pas encaissé. */
    public function getSoldeEstimeAttribute(): float
    {
        if ($this->statut === 'sorti') {
            return 0;
        }
        $solde = 0;
        if (! $this->paye_tunnel && $this->type_infrastructure === 'chambre' && $this->montant_tunnel) {
            $solde += (float) $this->montant_tunnel;
        }
        if (! $this->paye_froid && $this->type_infrastructure === 'chambre') {
            $solde += $this->montantFroidEstime();
        }

        return $solde;
    }

    public function montantFroidEstime(): float
    {
        $jours = $this->duree_indeterminee ? $this->jours_chambre : max((float) $this->jours_prevus, 1);

        return round((float) $this->stock_restant * $jours * $this->tarif_stockage);
    }
}

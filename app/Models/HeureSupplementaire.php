<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeureSupplementaire extends Model
{
    protected $table = 'heures_supplementaires';

    /** Base mensuelle légale (40 h/semaine) pour le taux horaire. */
    public const HEURES_MOIS = 173.33;

    /** Plafond légal d'heures supplémentaires par semaine. */
    public const MAX_HEURES_SEMAINE = 20;

    protected $fillable = [
        'employe_id', 'date', 'heures', 'majoration', 'motif',
        'montant', 'date_paiement', 'paye_par', 'saisi_par',
    ];

    protected $casts = [
        'date' => 'date',
        'date_paiement' => 'date',
        'heures' => 'decimal:2',
        'montant' => 'decimal:2',
    ];

    public function employe()
    {
        return $this->belongsTo(Employe::class)->withTrashed();
    }

    public function payeur()
    {
        return $this->belongsTo(User::class, 'paye_par');
    }

    /** Montant dû selon le salaire de l'employé. */
    public function calculerMontant(float $salaire): float
    {
        return round($salaire / self::HEURES_MOIS * (float) $this->heures * (1 + $this->majoration / 100), 2);
    }
}

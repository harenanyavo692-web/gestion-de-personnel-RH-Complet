<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DossierRegie extends Model
{
    protected $table = "dossiers_regie";

    public const STATUTS = [
        'quittance_emise' => 'Quittance émise',
        'verse' => 'Versé — en attente du reçu',
        'recu_obtenu' => 'Reçu Trésor obtenu',
        'transmis_raf' => 'Transmis à la RAF',
        'cloture' => 'Clôturé',
    ];

    protected $guarded = [];

    protected $casts = [
        'date_journal' => 'date', 'montant' => 'decimal:2', 'tunnel' => 'decimal:2', 'froid' => 'decimal:2', 'transport' => 'decimal:2',
        'verse_le' => 'datetime', 'recu_tresor_le' => 'datetime', 'transmis_raf_le' => 'datetime', 'ordre_versement_le' => 'datetime',
    ];

    public function getStatutLibelleAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }
}

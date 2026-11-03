<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrdreDepense extends Model
{
    use SoftDeletes, LogsActivity, Archivable;

    /** Événements journalisés automatiquement (les autres sont déjà écrits par les services métier). */
    public array $activityEvents = ['restored', 'forceDeleted'];

    protected $table = 'ordres_depense';

    public const STATUTS = [
        'en_attente' => 'En attente',
        'validee' => 'Validée',
        'refusee' => 'Refusée',
    ];

    protected $fillable = [
        'reference', 'date_depense', 'motif', 'beneficiaire', 'montant', 'categorie_id',
        'statut', 'soumis_par', 'traite_par', 'traite_le', 'commentaire_raf',
        'accounting_entity_id', 'journal_id', 'account_id', 'mode_paiement',
    ];

    protected $casts = [
        'date_depense' => 'date',
        'traite_le' => 'datetime',
        'montant' => 'decimal:2',
    ];

    public function categorie()
    {
        return $this->belongsTo(CategorieFinanciere::class, 'categorie_id');
    }

    public function soumisPar()
    {
        return $this->belongsTo(User::class, 'soumis_par');
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function pieces()
    {
        return $this->morphMany(PieceJustificative::class, 'piece');
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }
}

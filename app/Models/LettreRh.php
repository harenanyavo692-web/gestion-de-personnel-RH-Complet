<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Lettre RH officielle générée et conservée (registre des documents délivrés). */
class LettreRh extends Model
{
    use SoftDeletes, LogsActivity, Archivable;

    /** La suppression est journalisée par le contrôleur ; restauration et purge le sont ici. */
    public array $activityEvents = ['restored', 'forceDeleted'];

    protected $table = 'lettres_rh';

    public const TYPES = [
        'demission' => 'Lettre de démission',
        'renvoi' => 'Lettre de renvoi / fin de contrat',
        'conge' => 'Autorisation / permission de congé',
        'fin_stage' => 'Attestation de fin de stage',
        'attestation_travail' => 'Attestation de travail',
        'certificat_travail' => 'Certificat de travail (solde de tout compte)',
        'note_service' => 'Note de service / convocation',
        'avertissement' => "Lettre d'avertissement",
    ];

    protected $fillable = ['reference', 'type', 'employe_id', 'nom', 'poste', 'date_debut', 'date_fin', 'motif', 'signataire', 'cree_par'];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date'];

    public function employe()
    {
        return $this->belongsTo(Employe::class)->withTrashed();
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function libelleActivite(): string
    {
        return $this->reference . ' — ' . $this->nom;
    }

    public function getTypeLibelleAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}

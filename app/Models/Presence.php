<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Presence extends Model
{
    protected $fillable = [
        'employe_id', 'date', 'heure_arrivee', 'heure_depart', 'type', 'note',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employe()
    {
        return $this->belongsTo(Employe::class)->withTrashed();
    }
}

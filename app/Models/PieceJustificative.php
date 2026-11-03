<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PieceJustificative extends Model
{
    protected $table = 'pieces_justificatives';

    protected $fillable = ['piece_type', 'piece_id', 'nom_original', 'chemin', 'mime', 'taille', 'ajoute_par'];

    public function piece()
    {
        return $this->morphTo();
    }
}

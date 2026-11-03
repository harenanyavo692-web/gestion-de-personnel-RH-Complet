<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RapprochementEcart extends Model
{
    public $timestamps = false;

    protected $table = 'rapprochement_ecarts';

    protected $fillable = ['rapprochement_id', 'libelle', 'montant'];

    protected $casts = ['montant' => 'decimal:2'];
}

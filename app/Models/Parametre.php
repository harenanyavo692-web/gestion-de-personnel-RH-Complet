<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parametre extends Model
{
    protected $primaryKey = 'cle';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['cle', 'valeur', 'modifie_par'];
    protected $casts = ['valeur' => 'array'];
}

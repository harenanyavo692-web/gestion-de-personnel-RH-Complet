<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingExercise extends Model
{
    protected $fillable = ['code', 'libelle', 'date_debut', 'date_fin', 'statut', 'cloture_le', 'cloture_par'];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date', 'cloture_le' => 'datetime'];

    public function entites()
    {
        return $this->hasMany(AccountingEntity::class, 'exercise_id');
    }
}

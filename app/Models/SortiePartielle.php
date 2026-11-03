<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SortiePartielle extends Model
{
    protected $table = 'sorties_partielles';

    protected $guarded = [];

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }
}

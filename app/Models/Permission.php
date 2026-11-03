<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['slug', 'libelle'];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentaireRapport extends Model
{
    protected $table = 'commentaires_rapport';

    protected $fillable = ['user_id', 'contenu'];

    public function auteur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

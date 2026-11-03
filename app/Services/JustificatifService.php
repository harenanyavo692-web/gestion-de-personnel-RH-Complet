<?php

namespace App\Services;

use App\Models\PieceJustificative;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class JustificatifService
{
    /** Stocke la pièce sur le disque privé (hors de public/) et la rattache à l'opération. */
    public function attacher(Model $operation, UploadedFile $fichier, int $userId): PieceJustificative
    {
        return $operation->pieces()->create([
            'nom_original' => $fichier->getClientOriginalName(),
            'chemin' => $fichier->store('justificatifs'),
            'mime' => $fichier->getClientMimeType(),
            'taille' => $fichier->getSize(),
            'ajoute_par' => $userId,
        ]);
    }
}

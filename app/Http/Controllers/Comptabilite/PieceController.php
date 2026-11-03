<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Models\OrdreDepense;
use App\Models\PieceJustificative;
use App\Models\Recette;
use Illuminate\Support\Facades\Storage;

class PieceController extends Controller
{
    /** Téléchargement protégé : le fichier n'est jamais exposé publiquement. */
    public function show(PieceJustificative $piece)
    {
        $permission = match ($piece->piece_type) {
            OrdreDepense::class => 'depenses.voir',
            Recette::class => 'recettes.voir',
            default => abort(404),
        };
        $this->authorize($permission);

        abort_unless(Storage::exists($piece->chemin), 404);

        return Storage::download($piece->chemin, $piece->nom_original);
    }
}

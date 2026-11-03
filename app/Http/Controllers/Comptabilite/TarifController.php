<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Services\ParametreService;
use Illuminate\Http\Request;

/** Tarifs et en-tête officielle : consultation, calculateur de facturation et modification (droit dédié). */
class TarifController extends Controller
{
    public function index(ParametreService $p)
    {
        return view('comptabilite.tarifs', ['p' => $p->tous()]);
    }

    public function update(Request $request, ParametreService $p)
    {
        $this->authorize('tarifs.gerer');
        $nombre = ['required', 'numeric', 'min:0', 'max:999999999'];

        $data = $request->validate([
            'tunnel' => $nombre, 'stockage_chambre' => $nombre, 'stockage_societe' => $nombre, 'stockage_crevettes' => $nombre,
            'exception_4t_tana' => $nombre,
            'interurbain' => ['required', 'array'], 'interurbain.*.avecGO' => $nombre, 'interurbain.*.sansGO' => $nombre,
            'urbain' => ['required', 'array'], 'urbain.*.camion5T' => $nombre, 'urbain.*.camion8T' => $nombre,
            'location.5T' => $nombre, 'location.8T' => $nombre,
            'eau.poissons_crevettes' => $nombre, 'eau.crabes_traites' => $nombre, 'eau.crabes_non_traites' => $nombre,
            'entete' => ['required', 'array'],
            'entete.*' => ['nullable', 'string', 'max:300'],
        ]);

        // On ne conserve que les lignes de barème qui existent déjà : pas de création de clé par le formulaire.
        $garder = fn (array $envoye, string $cle) => array_intersect_key($envoye, $p->get($cle));

        $p->set('tarifs.tunnel', (float) $data['tunnel']);
        $p->set('tarifs.stockage_chambre', (float) $data['stockage_chambre']);
        $p->set('tarifs.stockage_societe', (float) $data['stockage_societe']);
        $p->set('tarifs.stockage_crevettes', (float) $data['stockage_crevettes']);
        $p->set('tarifs.exception_4t_tana', (float) $data['exception_4t_tana']);
        $p->set('tarifs.transport_interurbain', $garder($data['interurbain'], 'tarifs.transport_interurbain'));
        $p->set('tarifs.transport_urbain', $garder($data['urbain'], 'tarifs.transport_urbain'));
        $p->set('tarifs.location', $data['location']);
        $p->set('tarifs.eau', $data['eau']);
        $p->set('entete', array_intersect_key($data['entete'], $p->get('entete')));

        return back()->with('success', 'Tarifs et paramètres enregistrés.');
    }
}

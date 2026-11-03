<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Models\RetardTunnel;
use App\Services\AuditLogger;
use App\Services\ParametreService;
use Illuminate\Http\Request;

/** Majoration de retard tunnel (> 24 h) et registre des responsabilités. */
class RetardController extends Controller
{
    public function index(ParametreService $p)
    {
        $tous = RetardTunnel::orderByDesc('id')->get();
        $client = $tous->where('responsabilite', 'client');

        return view('comptabilite.retards', [
            'taux' => $p->get('majoration.taux_retard'),
            'tarifTunnel' => $p->get('tarifs.tunnel'),
            'retards' => RetardTunnel::orderByDesc('id')->paginate(15),
            'nbTotal' => $tous->count(),
            'nbClient' => $client->count(),
            'nbUsine' => $tous->where('responsabilite', 'usine')->count(),
            'penalites' => (float) $client->sum('penalite'),
        ]);
    }

    public function taux(Request $request, ParametreService $p)
    {
        $this->authorize('majoration.gerer');
        $data = $request->validate(['taux' => ['required', 'numeric', 'between:0,500']]);
        $p->set('majoration.taux_retard', (float) $data['taux']);

        return back()->with('success', "Taux de majoration mis à jour : +{$data['taux']} %.");
    }

    /** Calcul identique à la maquette ; le taux appliqué est figé dans l'enregistrement. */
    public function store(Request $request, ParametreService $p)
    {
        $this->authorize('majoration.gerer');
        $data = $request->validate([
            'lot' => ['required', 'string', 'max:40'],
            'client' => ['required', 'string', 'max:150'],
            'poids_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'heures_tunnel' => ['required', 'integer', 'gt:24', 'max:100000'],
            'responsabilite' => ['required', 'in:client,usine'],
        ], ['heures_tunnel.gt' => 'Un dépassement concerne une durée supérieure à 24 h.']);

        $tarif = (float) $p->get('tarifs.tunnel');
        $taux = (float) $p->get('majoration.taux_retard');
        $poids = (float) $data['poids_kg'];

        if ($data['responsabilite'] === 'client') {
            $penalite = round($poids * (($data['heures_tunnel'] - 24) / 24) * $tarif * (1 + $taux / 100));
            $montant = round($poids * $tarif) + $penalite;
        } else {
            $penalite = 0;
            $montant = round($poids * max($data['heures_tunnel'] / 24, 1) * $tarif);
        }

        $retard = RetardTunnel::create([...$data, 'taux_applique' => $taux, 'montant_tunnel' => $montant, 'penalite' => $penalite, 'saisi_par' => $request->user()->id]);
        AuditLogger::log('retard.enregistre', $retard, ['lot' => $retard->lot, 'responsabilite' => $retard->responsabilite, 'penalite' => $penalite]);

        return back()->with('success', 'Dépassement enregistré.');
    }
}

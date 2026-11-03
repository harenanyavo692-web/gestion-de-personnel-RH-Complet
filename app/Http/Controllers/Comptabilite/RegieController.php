<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Models\DossierRegie;
use App\Services\ParametreService;
use App\Services\RegieService;
use Illuminate\Http\Request;

class RegieController extends Controller
{
    private function date(Request $request): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->date) && strtotime($request->date) ? $request->date : today()->toDateString();
    }

    public function export(Request $request, RegieService $regie)
    {
        return $regie->exporter($this->date($request));
    }

    public function index(Request $request, RegieService $regie)
    {
        $date = $this->date($request);

        return view('comptabilite.regie', [
            'date' => $date,
            'lignes' => $regie->lignes($date),
            'journal' => $regie->journal(),
            'dejaCloture' => DossierRegie::whereDate('date_journal', today())->exists(),
            'dossiers' => DossierRegie::orderByDesc('date_journal')->paginate(15),
        ]);
    }

    public function cloturer(Request $request, RegieService $regie)
    {
        $this->authorize('regie.gerer');
        $d = $regie->cloturerJournal($request->user());

        return redirect()->route('comptabilite.regie.quittance', $d)->with('success', "Journée clôturée — quittance {$d->reference} générée.");
    }

    public function verser(Request $request, DossierRegie $dossier, RegieService $regie)
    {
        $this->authorize('regie.gerer');
        $regie->avancer($dossier, 'quittance_emise', 'verse', ['verse_le' => now()], 'regie.verse_tresor');

        return back()->with('success', 'Versement au Trésor enregistré. En attente du reçu officiel.');
    }

    public function recu(Request $request, DossierRegie $dossier, RegieService $regie)
    {
        $this->authorize('regie.gerer');
        $data = $request->validate(['recu_tresor_num' => ['required', 'string', 'max:60']]);
        $regie->avancer($dossier, 'verse', 'recu_obtenu', [...$data, 'recu_tresor_le' => now()], 'regie.recu_tresor');

        return back()->with('success', 'Reçu du Trésor enregistré.');
    }

    public function transmettre(Request $request, DossierRegie $dossier, RegieService $regie)
    {
        $this->authorize('regie.gerer');
        $regie->avancer($dossier, 'recu_obtenu', 'transmis_raf', ['transmis_raf_le' => now()], 'regie.transmis_raf');

        return back()->with('success', "Dossier transmis pour l'émission de l'ordre de versement.");
    }

    public function emettre(Request $request, DossierRegie $dossier, RegieService $regie)
    {
        $this->authorize('regie.emettre');
        $d = $regie->avancer($dossier, 'transmis_raf', 'cloture', [
            'ordre_versement_num' => 'OV-' . $dossier->reference, 'ordre_versement_le' => now(), 'cloture_par' => $request->user()->id,
        ], 'regie.ordre_versement_emis');

        return redirect()->route('comptabilite.regie.ordre', $d)->with('success', 'Ordre de versement émis. Dossier clôturé et comptabilisé.');
    }

    public function quittance(DossierRegie $dossier, ParametreService $p)
    {
        return view('comptabilite.document-regie', ['dossier' => $dossier, 'entete' => $p->get('entete'), 'type' => 'quittance']);
    }

    public function ordre(DossierRegie $dossier, ParametreService $p)
    {
        abort_unless($dossier->statut === 'cloture', 404);

        return view('comptabilite.document-regie', ['dossier' => $dossier, 'entete' => $p->get('entete'), 'type' => 'ordre']);
    }
}

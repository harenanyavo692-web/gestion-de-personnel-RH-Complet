<?php

namespace App\Http\Controllers\RAF;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\HeureSupplementaire;
use App\Models\PaiementSalaire;
use App\Services\HeuresSupService;
use App\Services\PaiementSalaireService;
use App\Services\ParametreService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class SalaireController extends Controller
{
    public function index(Request $request)
    {
        $mois = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->mois) ? $request->mois : now()->format('Y-m');

        $employes = Employe::where('statut', '!=', 'inactif')->orderBy('nom')
            ->with(['paiementsSalaires' => fn ($q) => $q->where('mois', $mois)])->get();

        $hs = HeureSupplementaire::with('employe')->where('date', 'like', "{$mois}-%")
            ->orderByDesc('date')->orderByDesc('id')->get();
        $hsParEmploye = $hs->groupBy('employe_id');

        return view('raf.salaires', [
            'mois' => $mois,
            'employes' => $employes,
            'masse' => $employes->sum('salaire'),
            'verse' => $employes->sum(fn ($e) => (float) $e->paiementsSalaires->sum('montant')),
            'heuresSup' => $hs,
            'hsParEmploye' => $hsParEmploye,
            'hsDu' => $employes->sum(fn ($e) => $hsParEmploye->get($e->id, collect())->whereNull('date_paiement')
                ->sum(fn ($l) => $l->calculerMontant((float) $e->salaire))),
        ] + $this->donneesHistorique(new Request, 5));
    }

    /**
     * Historique des paiements : salaires et heures supplémentaires payées, du plus récent au plus ancien.
     * Les heures payées ensemble (même employé, même mois, même jour de paiement) forment une seule ligne.
     * Filtres : h_employe (id) et h_mois (AAAA-MM, mois de la période payée).
     */
    private function donneesHistorique(Request $request, int $parPage = 10): array
    {
        $employeId = $request->integer('h_employe') ?: null;
        $periode = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->h_mois) ? $request->h_mois : null;

        $salaires = PaiementSalaire::with(['employe', 'payeur'])
            ->when($employeId, fn ($q) => $q->where('employe_id', $employeId))
            ->when($periode, fn ($q) => $q->where('mois', $periode))
            ->get()->map(fn ($p) => [
                'type' => 'salaire', 'employe' => $p->employe, 'periode' => $p->mois, 'montant' => (float) $p->montant,
                'date' => $p->date_paiement, 'payeur' => $p->payeur?->name, 'paiement' => $p, 'detail' => null, 'tri' => $p->id,
            ]);

        $heures = HeureSupplementaire::with(['employe', 'payeur'])->whereNotNull('date_paiement')
            ->when($employeId, fn ($q) => $q->where('employe_id', $employeId))
            ->when($periode, fn ($q) => $q->where('date', 'like', "{$periode}-%"))
            ->get()->groupBy(fn ($h) => $h->employe_id . '|' . $h->date->format('Y-m') . '|' . $h->date_paiement->toDateString())
            ->map(fn ($g) => [
                'type' => 'heures_sup', 'employe' => $g->first()->employe, 'periode' => $g->first()->date->format('Y-m'),
                'montant' => (float) $g->sum('montant'), 'date' => $g->first()->date_paiement, 'payeur' => $g->first()->payeur?->name,
                'paiement' => null, 'detail' => rtrim(rtrim(number_format((float) $g->sum('heures'), 2, ',', ''), '0'), ',') . ' h', 'tri' => $g->max('id'),
            ]);

        $lignes = $salaires->concat($heures)->sortByDesc(fn ($l) => $l['date']->format('Ymd') . str_pad((string) $l['tri'], 9, '0', STR_PAD_LEFT))->values();
        $page = max(1, $request->integer('page'));

        return [
            'historique' => (new LengthAwarePaginator($lignes->forPage($page, $parPage)->values(), $lignes->count(), $parPage, $page, ['path' => $request->url(), 'query' => $request->query()]))->fragment('historique'),
            'historiqueTotal' => (float) $lignes->sum('montant'),
            'historiqueEmployes' => Employe::orderBy('nom')->get(['id', 'nom', 'prenom']),
        ];
    }

    public function payer(Request $request, Employe $employe, PaiementSalaireService $service)
    {
        $data = $request->validate(['mois' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $service->payer($employe, $data['mois'], $request->user());

        return back()->with('success', "Salaire de {$employe->nom_complet} enregistré.");
    }

    /** Page dédiée : tout l'historique des paiements, avec filtres. */
    public function historique(Request $request)
    {
        return view('raf.historique-paiements', $this->donneesHistorique($request));
    }

    /** Reçu de paiement d'un salaire (avec les heures supplémentaires payées pour le même mois). */
    public function recu(PaiementSalaire $paiement, ParametreService $parametres)
    {
        $paiement->load(['employe', 'payeur']);
        $heuresSup = HeureSupplementaire::where('employe_id', $paiement->employe_id)
            ->where('date', 'like', "{$paiement->mois}-%")->whereNotNull('date_paiement')
            ->orderBy('date')->get();

        return view('raf.recu-salaire', [
            'paiement' => $paiement,
            'heuresSup' => $heuresSup,
            'numero' => 'SAL-' . str_replace('-', '', $paiement->mois) . '-' . str_pad((string) $paiement->id, 4, '0', STR_PAD_LEFT),
            'periode' => Carbon::createFromFormat('Y-m-d', $paiement->mois . '-01')->translatedFormat('F Y'),
            'entete' => $parametres->get('entete'),
        ]);
    }

    /** État de paie d'un mois : tous les salaires versés, à imprimer et à signer. */
    public function etat(Request $request, ParametreService $parametres)
    {
        $mois = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->mois) ? $request->mois : now()->format('Y-m');
        $paiements = PaiementSalaire::with(['employe', 'payeur'])->where('mois', $mois)->get()->sortBy(fn ($p) => $p->employe->nom)->values();
        $heuresSup = HeureSupplementaire::where('date', 'like', "{$mois}-%")->whereNotNull('date_paiement')->get()->groupBy('employe_id');

        return view('raf.etat-paie', [
            'mois' => $mois,
            'periode' => Carbon::createFromFormat('Y-m-d', $mois . '-01')->translatedFormat('F Y'),
            'paiements' => $paiements,
            'heuresSup' => $heuresSup,
            'nonPayes' => Employe::where('statut', '!=', 'inactif')->whereDoesntHave('paiementsSalaires', fn ($q) => $q->where('mois', $mois))->count(),
            'entete' => $parametres->get('entete'),
        ]);
    }

    public function ajouterHeuresSup(Request $request, Employe $employe)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'heures' => ['required', 'numeric', 'min:0.25', 'max:12'],
            'majoration' => ['required', 'integer', 'in:30,50,100'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        $date = Carbon::parse($data['date']);
        $semaine = (float) HeureSupplementaire::where('employe_id', $employe->id)
            ->whereBetween('date', [$date->copy()->startOfWeek()->toDateString(), $date->copy()->endOfWeek()->toDateString()])
            ->sum('heures');
        if ($semaine + (float) $data['heures'] > HeureSupplementaire::MAX_HEURES_SEMAINE) {
            throw ValidationException::withMessages(['heures' => 'Plafond de '.HeureSupplementaire::MAX_HEURES_SEMAINE." h supplémentaires par semaine dépassé ({$semaine} h déjà saisies)."]);
        }

        HeureSupplementaire::create($data + ['employe_id' => $employe->id, 'saisi_par' => $request->user()->id]);

        return back()->with('success', "Heures supplémentaires de {$employe->nom_complet} enregistrées.");
    }

    public function supprimerHeuresSup(HeureSupplementaire $heure)
    {
        abort_if($heure->date_paiement, 422, 'Heures déjà payées.');
        $heure->delete();

        return back()->with('success', 'Ligne supprimée.');
    }

    public function payerHeuresSup(Request $request, Employe $employe, HeuresSupService $service)
    {
        $data = $request->validate(['mois' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $total = $service->payer($employe, $data['mois'], $request->user());

        return back()->with('success', "Heures supplémentaires de {$employe->nom_complet} payées (".\App\Support\Fmt::ar($total).').');
    }
}

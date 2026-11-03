<?php

namespace App\Http\Controllers\RAF;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\HeureSupplementaire;
use App\Models\PaiementSalaire;
use App\Services\HeuresSupService;
use App\Services\PaiementSalaireService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
            'historique' => PaiementSalaire::with(['employe', 'payeur'])->orderByDesc('date_paiement')->orderByDesc('id')->paginate(10),
        ]);
    }

    public function payer(Request $request, Employe $employe, PaiementSalaireService $service)
    {
        $data = $request->validate(['mois' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $service->payer($employe, $data['mois'], $request->user());

        return back()->with('success', "Salaire de {$employe->nom_complet} enregistré.");
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

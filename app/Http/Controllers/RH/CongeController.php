<?php

namespace App\Http\Controllers\RH;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Employe;
use App\Models\ReferenceItem;
use App\Services\AbsenceService;
use App\Services\ParametreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CongeController extends Controller
{
    public function index(Request $request, string $nature, AbsenceService $service, ParametreService $params)
    {
        $types = ReferenceItem::groupe('type_absence')->get()->filter(fn ($t) => Absence::natureDuType($t) === $nature)->values();
        $codes = $types->pluck('code');
        $workflow = $params->get('rh.workflow');
        $soldes = collect();

        if ($nature === 'conge' && $request->vue === 'soldes') {
            $annee = $service->annee();
            $soldes = Employe::where('statut', '!=', 'inactif')->orderBy('nom')->get()->map(fn ($e) => [
                'employe' => $e,
                'lignes' => $types->where('actif', true)->filter(fn ($t) => ($t->meta['jours_annuels'] ?? null) !== null)
                    ->map(fn ($t) => ['type' => $t] + $service->solde($e, $t, $annee)),
            ]);
        }

        $absences = Absence::with(['employe.entite', 'demandeur', 'traitePar'])->whereIn('type_code', $codes)
            ->when($request->statut, fn ($q, $s) => $q->where('statut', $s))
            ->when($request->type, fn ($q, $t) => $q->where('type_code', $t))
            ->when($request->q, fn ($q, $t) => $q->whereHas('employe', fn ($e) => $e->where('nom', 'like', "%{$t}%")->orWhere('prenom', 'like', "%{$t}%")))
            ->orderByRaw("statut = 'en_attente' desc")->orderByDesc('date_debut')->paginate(12)->withQueryString();

        return view('rh.conges', [
            'nature' => $nature, 'absences' => $absences, 'types' => $types, 'soldes' => $soldes,
            'vue' => $nature === 'conge' && $request->vue === 'soldes' ? 'soldes' : 'demandes',
            'annee' => $service->annee(), 'workflow' => $workflow,
            'employes' => Employe::where('statut', '!=', 'inactif')->orderBy('nom')->get(['id', 'nom', 'prenom']),
            'enAttente' => Absence::whereIn('type_code', $codes)->where('statut', 'en_attente')->count(),
            'enRetard' => Absence::whereIn('type_code', $codes)->where('statut', 'en_attente')->where('created_at', '<', now()->subDays($workflow['delai_reponse_jours']))->count(),
            'enCours' => Absence::whereIn('type_code', $codes)->where('statut', 'validee')->whereDate('date_debut', '<=', today())->whereDate('date_fin', '>=', today())->count(),
        ]);
    }

    public function store(Request $request, AbsenceService $service)
    {
        $this->authorize('conges.gerer');
        $data = $request->validate([
            'employe_id' => ['required', 'exists:employes,id'],
            'type_code' => ['required', 'string', 'max:40'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'motif' => ['nullable', 'string', 'max:300'],
            'justificatif' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], ['date_fin.after_or_equal' => 'La fin ne peut pas précéder le début.']);

        $a = $service->creer($data, $request->user(), $request->file('justificatif'));

        return back()->with('success', $a->statut === 'validee' ? 'Absence enregistrée et validée (validation désactivée dans les paramètres).' : 'Demande enregistrée, en attente de validation.');
    }

    public function decider(Request $request, Absence $absence, AbsenceService $service)
    {
        $this->authorize('traiter', $absence);
        $data = $request->validate([
            'decision' => ['required', 'in:valider,refuser'],
            'commentaire' => ['required_if:decision,refuser', 'nullable', 'string', 'max:300'],
        ], ['commentaire.required_if' => 'Un commentaire est obligatoire pour refuser une demande.']);
        $service->traiter($absence, $data['decision'], $data['commentaire'] ?? null, $request->user());

        return back()->with('success', $data['decision'] === 'valider' ? 'Demande validée — les jours apparaissent au pointage.' : 'Demande refusée.');
    }

    public function annuler(Absence $absence, AbsenceService $service)
    {
        $this->authorize('annuler', $absence);
        $service->annuler($absence);

        return back()->with('success', 'Demande annulée.');
    }

    public function justificatif(Absence $absence)
    {
        $this->authorize('conges.voir');
        abort_unless($absence->justificatif_chemin && Storage::exists($absence->justificatif_chemin), 404);

        return Storage::download($absence->justificatif_chemin, $absence->justificatif_nom);
    }
}

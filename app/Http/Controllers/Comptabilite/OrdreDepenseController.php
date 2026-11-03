<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrdreDepenseRequest;
use App\Models\CategorieFinanciere;
use App\Models\OrdreDepense;
use App\Services\AuditLogger;
use App\Services\ExerciceService;
use App\Services\JustificatifService;
use App\Support\Fmt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdreDepenseController extends Controller
{
    public function index(Request $request)
    {
        $ordres = OrdreDepense::with(['categorie', 'soumisPar', 'pieces'])
            ->when($request->statut, fn ($q, $s) => $q->where('statut', $s))
            ->when($request->categorie_id, fn ($q, $c) => $q->where('categorie_id', $c))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('motif', 'like', "%{$t}%")->orWhere('beneficiaire', 'like', "%{$t}%")->orWhere('reference', 'like', "%{$t}%")))
            ->orderByDesc('date_depense')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('comptabilite.depenses', [
            'ordres' => $ordres,
            'categories' => CategorieFinanciere::depenses()->get(),
            'enAttente' => OrdreDepense::where('statut', 'en_attente')->sum('montant'),
            'valide' => OrdreDepense::where('statut', 'validee')->sum('montant'),
        ]);
    }

    public function store(StoreOrdreDepenseRequest $request, JustificatifService $pieces)
    {
        app(ExerciceService::class)->verifierOuvert($request->date_depense, 'date_depense');

        DB::transaction(function () use ($request, $pieces) {
            $ordre = OrdreDepense::create([
                ...$request->safe()->except('justificatif'),
                'reference' => Fmt::reference('OD'),
                'statut' => 'en_attente',
                'soumis_par' => $request->user()->id,
            ]);

            if ($request->hasFile('justificatif')) {
                $pieces->attacher($ordre, $request->file('justificatif'), $request->user()->id);
            }

            AuditLogger::log('depense.soumise', $ordre, ['reference' => $ordre->reference, 'montant' => (string) $ordre->montant]);
        });

        return back()->with('success', 'Ordre de dépense transmis au RAF pour validation.');
    }

    public function destroy(OrdreDepense $depense)
    {
        $this->authorize('annuler', $depense);

        AuditLogger::log('depense.annulee', $depense, ['reference' => $depense->reference]);
        
        $depense->delete();

        return back()->with('success', 'Ordre de dépense annulé.');
    }
}

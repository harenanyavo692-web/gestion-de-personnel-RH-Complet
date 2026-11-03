<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecetteRequest;
use App\Models\CategorieFinanciere;
use App\Models\Lot;
use App\Models\Recette;
use App\Services\AuditLogger;
use App\Services\ExerciceService;
use App\Services\JustificatifService;
use App\Support\Fmt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecetteController extends Controller
{
    /** Registres de la maquette → catégories de recette correspondantes. */
    private const REGISTRES = [
        'tunnel' => 'Tunnel de congélation',
        'froid' => 'Chambre froide',
        'transport' => 'Transport',
    ];

    public function index(Request $request)
    {
        $categories = CategorieFinanciere::recettes()->get();
        $periode = fn ($q) => $q
            ->when($request->du, fn ($q, $d) => $q->whereDate('date_recette', '>=', $d))
            ->when($request->au, fn ($q, $d) => $q->whereDate('date_recette', '<=', $d));

        $registre = self::REGISTRES[$request->registre] ?? null;

        $liste = $periode(Recette::query())
            ->when($registre, fn ($q) => $q->whereHas('categorie', fn ($c) => $c->where('nom', $registre)))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('origine', 'like', "%{$t}%")->orWhere('client', 'like', "%{$t}%")
                ->orWhere('numero_bon', 'like', "%{$t}%")->orWhere('reference', 'like', "%{$t}%")))
            ->when($request->categorie_id, fn ($q, $c) => $q->where('categorie_id', $c));

        $parCategorie = $periode(Recette::query())->with('categorie')->get()
            ->groupBy(fn ($r) => $r->categorie->nom)->map->sum('montant');

        $lotsEnStock = Lot::with('client')->where('statut', 'en_stock')->get();
        $creances = $periode(Recette::query())->whereColumn('montant_facture', '>', 'montant')->get();

        return view('comptabilite.recettes', [
            'recettes' => (clone $liste)->with(['categorie', 'auteur', 'pieces'])
                ->orderByDesc('date_recette')->orderByDesc('id')->paginate(15)->withQueryString(),
            'categories' => $categories,
            'registres' => self::REGISTRES,
            'total' => (float) $parCategorie->sum(),
            'parCategorie' => $parCategorie,
            'creances' => $creances,
            'lotsNonSoldes' => $lotsEnStock->filter(fn ($l) => $l->solde_estime > 0)->count(),
            'montantCreances' => (float) $creances->sum(fn ($r) => $r->montant_facture - $r->montant) + $lotsEnStock->sum->solde_estime,
            'totalRegistre' => (float) (clone $liste)->sum('montant'),
        ]);
    }

    public function store(StoreRecetteRequest $request, JustificatifService $pieces)
    {
        app(ExerciceService::class)->verifierOuvert($request->date_recette, 'date_recette');

        DB::transaction(function () use ($request, $pieces) {
            $recette = Recette::create([
                ...$request->safe()->except('justificatif'),
                'reference' => Fmt::reference('REC'),
                'saisi_par' => $request->user()->id,
            ]);

            if ($request->hasFile('justificatif')) {
                $pieces->attacher($recette, $request->file('justificatif'), $request->user()->id);
            }

            AuditLogger::log('recette.creee', $recette, ['reference' => $recette->reference, 'montant' => (string) $recette->montant]);
        });

        return back()->with('success', 'Recette enregistrée.');
    }

    public function destroy(Recette $recette)
    {
        $this->authorize('recettes.gerer');

        if ($recette->versementSpecial()->exists()) {
            return back()->withErrors(['recette' => 'Cette recette provient d\'un compte spécial : supprimez-la depuis ce compte.']);
        }

        $recette->delete(); // suppression logique : la trace reste en base
        AuditLogger::log('recette.supprimee', $recette, ['reference' => $recette->reference, 'montant' => (string) $recette->montant]);

        return back()->with('success', 'Recette supprimée.');
    }
}

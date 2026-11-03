<?php

namespace App\Http\Controllers\RAF;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRapprochementRequest;
use App\Models\Rapprochement;
use App\Services\AuditLogger;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RapprochementController extends Controller
{
    public function index(Request $request, FinanceService $finance)
    {
        $arrete = $request->date('arrete') ?? now();

        return view('raf.rapprochement', [
            'arrete' => $arrete,
            'soldeLivres' => $finance->soldeLivres($arrete),
            'historique' => Rapprochement::with('auteur')->withCount('ecarts')->orderByDesc('date_arrete')->paginate(10),
        ]);
    }

    public function store(StoreRapprochementRequest $request, FinanceService $finance)
    {
        $data = $request->validated();
        $ecarts = collect($data['ecarts'] ?? [])->filter(fn ($e) => filled($e['libelle'] ?? null) && isset($e['montant']));

        // Le solde des livres est recalculé côté serveur : il n'est jamais fourni par le client.
        $livres = $finance->soldeLivres(Carbon::parse($data['date_arrete']));
        $final = round($livres + $ecarts->sum('montant') - (float) $data['solde_tresor'], 2);

        $rapprochement = DB::transaction(function () use ($request, $data, $ecarts, $livres, $final) {
            $r = Rapprochement::create([
                'periode' => $data['periode'],
                'date_arrete' => $data['date_arrete'],
                'solde_livres' => $livres,
                'solde_tresor' => $data['solde_tresor'],
                'ecart_final' => $final,
                'statut' => abs($final) < 0.005 ? 'rapproche' : 'ecart',
                'cree_par' => $request->user()->id,
            ]);
            $r->ecarts()->createMany($ecarts->map(fn ($e) => ['libelle' => $e['libelle'], 'montant' => $e['montant']])->all());
            AuditLogger::log('rapprochement.enregistre', $r, ['periode' => $r->periode, 'ecart_final' => (string) $final]);

            return $r;
        });

        return redirect()->route('raf.rapprochements.index')
            ->with('success', $rapprochement->statut === 'rapproche' ? 'Rapprochement enregistré : aucun écart.' : 'Rapprochement enregistré avec un écart restant à justifier.');
    }
}

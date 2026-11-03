<?php

namespace App\Http\Controllers\RAF;

use App\Http\Controllers\Controller;
use App\Models\CommentaireRapport;
use App\Models\OrdreDepense;
use App\Models\Recette;
use App\Services\AuditLogger;
use App\Services\FinanceService;
use App\Support\Fmt;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RapportController extends Controller
{
    public function index(Request $request, FinanceService $finance)
    {
        [$du, $au] = $this->bornes($request);

        $recettes = Recette::with('categorie')->whereBetween('date_recette', [$du, $au])->get()
            ->groupBy(fn ($r) => $r->categorie->nom)->map->sum('montant')->sortDesc();
        $depenses = OrdreDepense::with('categorie')->where('statut', 'validee')->whereBetween('date_depense', [$du, $au])->get()
            ->groupBy(fn ($r) => $r->categorie->nom)->map->sum('montant')->sortDesc();

        return view('raf.rapports', [
            'du' => $du, 'au' => $au,
            'recettes' => $recettes,
            'depenses' => $depenses,
            'totalRecettes' => $finance->totalRecettes($du, $au),
            'totalDepenses' => $finance->totalDepenses($du, $au),
            'totalSalaires' => $finance->totalSalaires($du, $au),
            'resultat' => $finance->resultat($du, $au),
            'sept' => collect(range(6, 0))->map(function ($i) use ($finance) {
                $jour = now()->subDays($i)->startOfDay();

                return ['label' => $jour->format('d/m'), 'recettes' => $finance->totalRecettes($jour, $jour), 'depenses' => $finance->totalDepenses($jour, $jour)];
            })->all(),
            'commentaires' => CommentaireRapport::with('auteur')->latest()->take(10)->get(),
        ]);
    }

    public function commenter(Request $request)
    {
        $data = $request->validate(['contenu' => ['required', 'string', 'max:2000']]);

        $commentaire = CommentaireRapport::create(['user_id' => $request->user()->id, 'contenu' => $data['contenu']]);
        AuditLogger::log('rapport.commente', $commentaire);

        return back()->with('success', 'Commentaire enregistré.');
    }

    /** Export CSV (séparateur « ; », BOM UTF-8 pour Excel) de toutes les opérations de la période. */
    public function export(Request $request)
    {
        [$du, $au] = $this->bornes($request);
        AuditLogger::log('rapport.exporte', null, ['du' => $du->toDateString(), 'au' => $au->toDateString()]);

        $lignes = collect()
            ->concat(Recette::with('categorie')->whereBetween('date_recette', [$du, $au])->get()->map(fn ($r) => [
                $r->date_recette->toDateString(), 'Recette', $r->reference, $r->categorie->nom, $r->origine, $r->montant]))
            ->concat(OrdreDepense::with('categorie')->where('statut', 'validee')->whereBetween('date_depense', [$du, $au])->get()->map(fn ($o) => [
                $o->date_depense->toDateString(), 'Dépense', $o->reference, $o->categorie->nom, $o->motif, '-' . $o->montant]))
            ->sortBy(0);

        return response()->streamDownload(function () use ($lignes) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Type', 'Référence', 'Catégorie', 'Libellé', 'Montant (Ar)'], ';');
            foreach ($lignes as $l) {
                fputcsv($out, array_map([Fmt::class, 'csv'], $l), ';');
            }
            fclose($out);
        }, "operations_{$du->toDateString()}_{$au->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function bornes(Request $request): array
    {
        $du = $request->date('du') ?? now()->startOfMonth();
        $au = $request->date('au') ?? now();

        return $du->gt($au) ? [Carbon::parse($au)->startOfDay(), Carbon::parse($du)->endOfDay()] : [$du->startOfDay(), $au->endOfDay()];
    }
}

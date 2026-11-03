<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Models\HeureSupplementaire;
use App\Models\OrdreDepense;
use App\Models\PaiementSalaire;
use App\Models\Recette;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

/** Situation financière : vue journalière, hebdomadaire, mensuelle ou annuelle. */
class SituationController extends Controller
{
    private const PERIODES = ['jour', 'semaine', 'mois', 'annee'];

    public function index(Request $request)
    {
        $periode = in_array($request->periode, self::PERIODES, true) ? $request->periode : 'mois';
        [$debut, $fin, $precDebut, $precFin] = $this->bornes($periode);

        $lignes = $this->lignes($debut, $fin, $periode === 'annee' ? 'Y-m' : 'Y-m-d');

        $totaux = [
            'recettes' => $lignes->sum('recettes'),
            'depenses' => $lignes->sum('depenses'),
            'salaires' => $lignes->sum('salaires'),
        ];
        $totaux['resultat'] = $totaux['recettes'] - $totaux['depenses'] - $totaux['salaires'];

        $precedent = $this->lignes($precDebut, $precFin, 'Y-m-d');
        $precRecettes = $precedent->sum('recettes');
        $variation = $precRecettes > 0 ? round(($totaux['recettes'] - $precRecettes) / $precRecettes * 100, 1) : null;

        return view('comptabilite.situation', compact('periode', 'debut', 'fin', 'lignes', 'totaux', 'variation'));
    }

    /** Export CSV de la période affichée (une ligne par jour, ou par mois pour l'année). */
    public function export(Request $request)
    {
        $periode = in_array($request->periode, self::PERIODES, true) ? $request->periode : 'mois';
        [$debut, $fin] = $this->bornes($periode);
        $lignes = $this->lignes($debut, $fin, $periode === 'annee' ? 'Y-m' : 'Y-m-d');

        return response()->streamDownload(function () use ($lignes) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['Période', 'Recettes', 'Dépenses validées', 'Salaires', 'Résultat'], ';');
            foreach ($lignes as $l) {
                fputcsv($o, [$l['cle'], $l['recettes'], $l['depenses'], $l['salaires'], $l['recettes'] - $l['depenses'] - $l['salaires']], ';');
            }
            fclose($o);
        }, "situation_{$periode}_" . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function bornes(string $periode): array
    {
        $now = now();

        return match ($periode) {
            'jour' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), $now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'semaine' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), $now->copy()->subDays(13)->startOfDay(), $now->copy()->subDays(7)->endOfDay()],
            'annee' => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), $now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), $now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
        };
    }

    /** Une ligne par jour (ou par mois), les périodes sans mouvement apparaissent à zéro. */
    private function lignes(Carbon $debut, Carbon $fin, string $format)
    {
        $somme = fn ($modele, string $col) => $modele
            ->whereBetween($col, [$debut->toDateString(), $fin->toDateString()])
            ->get(['montant', $col])
            ->groupBy(fn ($r) => Carbon::parse($r->$col)->format($format))
            ->map->sum('montant');

        $recettes = $somme(Recette::query(), 'date_recette');
        $depenses = $somme(OrdreDepense::where('statut', 'validee'), 'date_depense');
        $salaires = $somme(PaiementSalaire::query(), 'date_paiement');
        $heuresSup = $somme(HeureSupplementaire::whereNotNull('date_paiement'), 'date_paiement');

        $pas = $format === 'Y-m' ? '1 month' : '1 day';

        return collect(CarbonPeriod::create($debut, $pas, $fin))
            ->map(fn ($d) => $d->format($format))->unique()->map(fn ($cle) => [
                'cle' => $cle,
                'recettes' => (float) ($recettes[$cle] ?? 0),
                'depenses' => (float) ($depenses[$cle] ?? 0),
                'salaires' => (float) ($salaires[$cle] ?? 0) + (float) ($heuresSup[$cle] ?? 0),
            ])->values();
    }
}

<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\MissionTransport;
use App\Models\Recette;
use App\Models\SortiePartielle;
use App\Services\AuditLogger;
use App\Services\FinanceService;
use App\Support\Fmt;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Onglet « Comptable » de la maquette : registres des flux, créances et état de synthèse. */
class RegistreController extends Controller
{
    private const CATEGORIES = ['tunnel' => 'Tunnel de congélation', 'froid' => 'Chambre froide', 'transport' => 'Transport'];

    private function debut(Request $request): ?Carbon
    {
        return match ($request->periode) {
            'semaine' => now()->subDays(7)->startOfDay(),
            'mois' => now()->subDays(30)->startOfDay(),
            default => null,
        };
    }

    private function type(Request $request): string
    {
        return array_key_exists($request->type, self::CATEGORIES) ? $request->type : 'tout';
    }

    private function donnees(Request $request): array
    {
        $debut = $this->debut($request);
        $type = $this->type($request);
        $inclut = fn (string $t) => $type === 'tout' || $type === $t;

        $lots = $inclut('tunnel') || $inclut('froid')
            ? Lot::with(['client', 'infrastructure'])->when($debut, fn ($q) => $q->where('date_entree', '>=', $debut))
                ->when($type === 'froid', fn ($q) => $q->where('type_infrastructure', 'chambre'))
                ->orderByDesc('date_entree')->get()
            : collect();

        // Bons de sortie : retraits partiels, et lots sortis sans retrait enregistré (libération simple).
        $sorties = collect();
        if ($inclut('froid') || $inclut('tunnel')) {
            $partielles = SortiePartielle::with('lot.client', 'lot.infrastructure')->when($debut, fn ($q) => $q->where('created_at', '>=', $debut))->get();
            foreach ($partielles as $s) {
                $sorties->push(['ref' => $s->reference, 'date' => $s->created_at, 'client' => $s->lot->client->nom, 'espece' => $s->lot->espece,
                    'infra' => $s->lot->infrastructure->nom, 'poids' => (float) $s->poids, 'tunnel' => 0.0, 'chambre' => (float) $s->montant]);
            }
            $libres = Lot::with(['client', 'infrastructure'])->where('statut', 'sorti')->whereDoesntHave('sorties')
                ->when($debut, fn ($q) => $q->where('updated_at', '>=', $debut))->get();
            foreach ($libres as $l) {
                $sorties->push(['ref' => $l->reference, 'date' => $l->updated_at, 'client' => $l->client->nom, 'espece' => $l->espece,
                    'infra' => $l->infrastructure->nom, 'poids' => (float) $l->poids_entree, 'tunnel' => (float) ($l->paye_tunnel ? $l->montant_tunnel : 0),
                    'chambre' => (float) $l->montant_froid_encaisse]);
            }
            $sorties = $sorties->sortByDesc('date')->values();
        }

        $missions = $inclut('transport')
            ? MissionTransport::with('vehicule')->when($debut, fn ($q) => $q->where('created_at', '>=', $debut))->orderByDesc('id')->get()
            : collect();

        $recettes = Recette::with('categorie')->when($debut, fn ($q) => $q->whereDate('date_recette', '>=', $debut))->get()
            ->groupBy(fn ($r) => $r->categorie->nom)->map->sum('montant');

        $creances = Lot::with(['client', 'infrastructure'])->where('statut', 'en_stock')->orderBy('date_entree')->get()->filter(fn ($l) => $l->solde_estime > 0)->values();

        return compact('lots', 'sorties', 'missions', 'recettes', 'creances', 'type') + ['periode' => $request->periode ?: 'tout'];
    }

    public function index(Request $request, FinanceService $finance)
    {
        $d = $this->donnees($request);
        $debut = $this->debut($request);

        return view('comptabilite.registres', $d + [
            'kpi' => [
                'total' => (float) $d['recettes']->sum(), 'tunnel' => (float) ($d['recettes'][self::CATEGORIES['tunnel']] ?? 0),
                'froid' => (float) ($d['recettes'][self::CATEGORIES['froid']] ?? 0), 'transport' => (float) ($d['recettes'][self::CATEGORIES['transport']] ?? 0),
                'creances' => (float) $d['creances']->sum->solde_estime, 'nonSoldes' => $d['creances']->count(),
            ],
            'synthese' => [
                'recettes' => $finance->totalRecettes($debut), 'depenses' => $finance->totalDepenses($debut),
                'salaires' => $finance->totalSalaires($debut), 'resultat' => $finance->resultat($debut),
            ],
        ]);
    }

    /** Export CSV (« ; », BOM UTF-8) des trois registres. */
    public function export(Request $request)
    {
        $d = $this->donnees($request);
        AuditLogger::log('registres.exporte', null, ['periode' => $d['periode'], 'type' => $d['type']]);

        return response()->streamDownload(function () use ($d) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            $ligne = fn (array $l) => fputcsv($o, array_map([Fmt::class, 'csv'], $l), ';');

            $ligne(['REGISTRE DES BONS D\'ENTREE']);
            $ligne(['N° bon', 'Date', 'Client', 'Espèce', 'Infrastructure', 'Poids brut (kg)', 'Montant facturé', 'Statut paiement']);
            foreach ($d['lots'] as $l) {
                $facture = (float) $l->montant_tunnel + ($l->paye_froid ? (float) $l->montant_froid_encaisse : $l->montantFroidEstime());
                $ligne([$l->reference, $l->date_entree->format('d/m/Y'), $l->client->nom, $l->espece, $l->infrastructure->nom, $l->poids_entree, $facture,
                    $l->statut === 'sorti' || ($l->paye_tunnel && $l->paye_froid) ? 'Payé' : ($l->paye_tunnel || $l->paye_froid ? 'Partiel' : 'Impayé')]);
            }
            $ligne([]);
            $ligne(['REGISTRE DES BONS DE SORTIE']);
            $ligne(['N° bon', 'Date', 'Client', 'Espèce', 'Infrastructure', 'Poids contre-pesée (kg)', 'Tunnel', 'Chambre', 'Total']);
            foreach ($d['sorties'] as $s) {
                $ligne([$s['ref'], $s['date']->format('d/m/Y'), $s['client'], $s['espece'], $s['infra'], $s['poids'], $s['tunnel'], $s['chambre'], $s['tunnel'] + $s['chambre']]);
            }
            $ligne([]);
            $ligne(['REGISTRE TRANSPORT']);
            $ligne(['N° mission', 'Date', 'Véhicule', 'Prestation / destination', 'Poids (kg)', 'Montant', 'Statut']);
            foreach ($d['missions'] as $m) {
                $ligne([$m->reference, $m->created_at->format('d/m/Y'), $m->vehicule->nom, $m->prestation . ' ' . ($m->destination ?? $m->zone_urbaine ?? ''), $m->poids_kg, $m->cout, $m->statut]);
            }
            fclose($o);
        }, 'registres_' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

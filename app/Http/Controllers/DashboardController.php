<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Absence;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Employe;
use App\Models\Infrastructure;
use App\Models\Lot;
use App\Models\MissionTransport;
use App\Models\OrdreDepense;
use App\Models\OrganizationEntity;
use App\Models\Recette;
use App\Models\Vehicule;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const MOIS = 6;

    /** Total par mois (clé AAAA-MM) sur les derniers mois, mois sans mouvement à zéro. */
    private function parMois($requete, string $colDate, string $colMontant = 'montant'): array
    {
        $debut = now()->startOfMonth()->subMonths(self::MOIS - 1);
        $lignes = $requete->whereDate($colDate, '>=', $debut)->get([$colDate, $colMontant])
            ->groupBy(fn ($r) => Carbon::parse($r->$colDate)->format('Y-m'))->map->sum($colMontant);

        return collect(range(self::MOIS - 1, 0))->map(fn ($i) => (float) ($lignes[now()->startOfMonth()->subMonths($i)->format('Y-m')] ?? 0))->all();
    }

    private function variation(array $serie): array
    {
        [$prec, $cour] = [$serie[count($serie) - 2] ?? 0, end($serie) ?: 0];
        if ($prec <= 0) {
            return [$cour > 0 ? 'Premiers mouvements ce mois-ci' : 'Aucun mouvement ce mois-ci', $cour > 0];
        }
        $pct = round(($cour - $prec) / $prec * 100);

        return [($pct >= 0 ? '+' : '') . $pct . ' % vs mois précédent', $pct >= 0];
    }

    public function index(Request $request, FinanceService $finance)
    {
        $user = $request->user();
        $mois = collect(range(self::MOIS - 1, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i)->translatedFormat('M Y'))->all();
        $data = ['charts' => [], 'comptes' => []];

        if ($user->can('recettes.voir') || $user->can('depenses.voir') || $user->can('rapports.voir')) {
            $recettes = $this->parMois(Recette::query(), 'date_recette');
            $depenses = $this->parMois(OrdreDepense::where('statut', 'validee'), 'date_depense');
            $cumul = collect($recettes)->map(fn ($r, $i) => $r - $depenses[$i])->all();
            $debut = now()->startOfMonth();

            $data['finance'] = [
                'solde' => $finance->soldeLivres(now()),
                'recettes' => end($recettes), 'recettesVar' => $this->variation($recettes), 'recettesSerie' => $recettes,
                'depenses' => end($depenses), 'depensesVar' => $this->variation($depenses), 'depensesSerie' => $depenses,
                'attente' => OrdreDepense::where('statut', 'en_attente')->count(),
                'attenteMontant' => (float) OrdreDepense::where('statut', 'en_attente')->sum('montant'),
                'resultatSerie' => $cumul,
            ];
            $data['charts']['evolution'] = ['type' => 'line', 'labels' => $mois, 'unit' => 'Ar', 'legend' => true,
                'datasets' => [['label' => 'Recettes', 'data' => $recettes, 'color' => 'brand'], ['label' => 'Dépenses validées', 'data' => $depenses, 'color' => '#ef4444']]];

            $parCat = Recette::with('categorie')->whereDate('date_recette', '>=', now()->startOfMonth()->subMonths(self::MOIS - 1))->get()
                ->groupBy(fn ($r) => $r->categorie->nom)->map->sum('montant')->sortDesc();
            if ($parCat->isNotEmpty()) {
                $data['charts']['repartition'] = ['type' => 'doughnut', 'labels' => $parCat->keys()->all(), 'unit' => 'Ar', 'datasets' => [['data' => $parCat->values()->all()]],
                    'colors' => ['brand', '#6366f1', '#f59e0b', '#10b981', '#ec4899', '#94a3b8']];
            }
            $depCat = OrdreDepense::with('categorie')->where('statut', 'validee')->whereDate('date_depense', '>=', now()->startOfMonth()->subMonths(self::MOIS - 1))->get()
                ->groupBy(fn ($r) => $r->categorie->nom)->map->sum('montant')->sortDesc();
            if ($depCat->isNotEmpty()) {
                $data['charts']['depenses_cat'] = ['type' => 'bar', 'labels' => $depCat->keys()->all(), 'unit' => 'Ar', 'datasets' => [['label' => 'Dépenses validées', 'data' => $depCat->values()->all(), 'color' => '#ef4444']]];
            }
            $data['dernieresRecettes'] = $user->can('recettes.voir') ? Recette::with('categorie')->latest('date_recette')->latest('id')->take(5)->get() : collect();
            $data['dernieresDepenses'] = $user->can('depenses.voir') ? OrdreDepense::with('categorie')->latest('id')->take(5)->get() : collect();
        }

        if ($user->can('employes.voir')) {
            $embauches = Employe::whereDate('date_embauche', '>=', now()->startOfMonth()->subMonths(self::MOIS - 1))->get(['date_embauche'])
                ->groupBy(fn ($e) => $e->date_embauche->format('Y-m'))->map->count();
            $data['rh'] = [
                'effectif' => Employe::count(), 'actifs' => Employe::where('statut', 'actif')->count(),
                'conges' => Employe::where('statut', 'congé')->count(),
                'absents' => Absence::where('statut', 'validee')->whereDate('date_debut', '<=', today())->whereDate('date_fin', '>=', today())->count(),
                'contratsEcheance' => Contrat::where('statut', 'actif')->whereBetween('date_fin', [today(), today()->addDays(30)])->count(),
                'demandes' => Absence::where('statut', 'en_attente')->count(),
                'embauchesSerie' => collect(range(self::MOIS - 1, 0))->map(fn ($i) => (int) ($embauches[now()->startOfMonth()->subMonths($i)->format('Y-m')] ?? 0))->all(),
            ];
            $parEntite = OrganizationEntity::withCount('employes')->get()->where('employes_count', '>', 0)->sortByDesc('employes_count')->take(8)->values();
            if ($parEntite->isNotEmpty()) {
                $data['charts']['effectif'] = ['type' => 'bar', 'labels' => $parEntite->pluck('nom')->all(), 'datasets' => [['label' => 'Employés', 'data' => $parEntite->pluck('employes_count')->all(), 'color' => 'brand']]];
            }
            $jours = Absence::where('statut', 'validee')->whereDate('date_debut', '>=', now()->startOfMonth()->subMonths(self::MOIS - 1))->get(['date_debut', 'jours'])
                ->groupBy(fn ($a) => $a->date_debut->format('Y-m'))->map->sum('jours');
            if ($jours->isNotEmpty()) {
                $data['charts']['absences'] = ['type' => 'bar', 'labels' => $mois, 'unit' => 'j', 'datasets' => [['label' => "Jours d'absence", 'data' => collect(range(self::MOIS - 1, 0))->map(fn ($i) => (float) ($jours[now()->startOfMonth()->subMonths($i)->format('Y-m')] ?? 0))->all(), 'color' => '#f59e0b']]];
            }
            $statuts = Employe::selectRaw('statut, count(*) as n')->groupBy('statut')->pluck('n', 'statut');
            if ($statuts->isNotEmpty()) {
                $data['charts']['statuts'] = ['type' => 'doughnut', 'labels' => $statuts->keys()->map(fn ($s) => ucfirst($s))->all(), 'datasets' => [['data' => $statuts->values()->all()]], 'colors' => ['#10b981', '#f59e0b', '#ef4444', '#94a3b8']];
            }
        }

        if ($user->can('lots.voir')) {
            $enStock = Lot::with('client')->where('statut', 'en_stock')->get();
            $data['stock'] = ['kg' => (float) $enStock->sum('stock_restant'), 'lots' => $enStock->count(), 'creances' => (float) $enStock->sum->solde_estime];
            $infra = Infrastructure::orderBy('type')->orderBy('nom')->get()->map(fn ($i) => ['nom' => $i->nom, 'pct' => round(min(100, $i->occupation / max($i->capacite_kg, 1) * 100), 1)]);
            if ($infra->isNotEmpty()) {
                $data['charts']['occupation'] = ['type' => 'bar', 'labels' => $infra->pluck('nom')->all(), 'unit' => '%', 'datasets' => [['label' => 'Occupation', 'data' => $infra->pluck('pct')->all(), 'color' => '#6366f1']]];
            }
        }

        if ($user->can('transport.voir')) {
            $data['transport'] = ['attente' => MissionTransport::where('statut', 'attente_caisse')->count(), 'encaisse' => (float) MissionTransport::where('statut', 'valide')->sum('cout')];
        }

        // Compteurs « contenu » : seulement les modules auxquels l'utilisateur a accès
        $data['comptes'] = collect([
            ['Employés', 'users', Employe::count(), $user->can('employes.voir') ? route('employes.index') : null],
            ['Clients', 'user', Client::count(), $user->can('clients.voir') ? route('exploitation.clients.index') : null],
            ['Contrats actifs', 'doc', Contrat::where('statut', 'actif')->count(), $user->can('contrats.voir') ? route('rh.contrats.index') : null],
            ['Véhicules', 'truck', Vehicule::count(), $user->can('transport.voir') ? route('exploitation.transport.index') : null],
            ['Entités RH', 'building', OrganizationEntity::where('actif', true)->count(), $user->can('rh.entites.voir') ? route('rh.entites.index') : null],
        ])->filter(fn ($c) => $c[3] !== null)->values();

        $data['activite'] = ActivityLog::with('user')->when(! $user->can('admin.audit'), fn ($q) => $q->where('user_id', $user->id))->latest('id')->take(6)->get();

        return view('dashboard.index', $data + ['nomAffiche' => $user->name]);
    }
}

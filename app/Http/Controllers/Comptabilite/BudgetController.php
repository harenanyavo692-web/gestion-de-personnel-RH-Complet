<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntity;
use App\Models\AccountingExercise;
use App\Services\ExerciceService;
use App\Services\ParametreService;
use Illuminate\Http\Request;

/** Suivi budgétaire : budget de référence des entités comptables contre dépenses validées. */
class BudgetController extends Controller
{
    public function index(Request $request, ParametreService $p, ExerciceService $ex)
    {
        $exercices = AccountingExercise::orderByDesc('date_debut')->get();
        $courant = $request->exercice ?: ($ex->courant()?->id ?? $exercices->first()?->id);
        $seuil = (int) $p->get('compta.securite')['alerte_budget_pct'];

        $lignes = AccountingEntity::with('exercice')->whereNotNull('budget_reference')->where('exercise_id', $courant)
            ->orderBy('code')->get()->map(function ($e) use ($seuil) {
                $conso = $e->depenseConsommee();
                $budget = (float) $e->budget_reference;
                $pct = $budget > 0 ? round($conso / $budget * 100, 1) : ($conso > 0 ? 100 : 0);

                return ['entite' => $e, 'budget' => $budget, 'conso' => $conso, 'reste' => $budget - $conso, 'pct' => $pct,
                    'etat' => $pct >= 100 ? 'depasse' : ($pct >= $seuil ? 'alerte' : 'ok')];
            });

        return view('comptabilite.budgets', [
            'exercices' => $exercices, 'courant' => (int) $courant, 'lignes' => $lignes, 'seuil' => $seuil,
            'totalBudget' => $lignes->where('entite.parent_id', null)->sum('budget'),
            'totalConso' => $lignes->where('entite.parent_id', null)->sum('conso'),
        ]);
    }
}

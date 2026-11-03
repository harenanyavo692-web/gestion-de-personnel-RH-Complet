<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntity;
use App\Models\AccountingExercise;
use App\Models\OrganizationEntity;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EntiteComptableController extends Controller
{
    public function index(Request $request)
    {
        $entites = AccountingEntity::with(['parent', 'organisation', 'responsable', 'exercice'])
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('nom', 'like', "%{$t}%")->orWhere('code', 'like', "%{$t}%")))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->statut !== null && $request->statut !== '', fn ($q) => $q->where('actif', $request->statut === 'actif'))
            ->orderBy('code')->paginate(15)->withQueryString();

        return view('comptabilite.entites.index', ['entites' => $entites, 'types' => AccountingEntity::TYPES]);
    }

    public function create()
    {
        return view('comptabilite.entites.form', $this->donnees(new AccountingEntity(['actif' => true])));
    }

    public function store(Request $request)
    {
        $e = AccountingEntity::create($this->valider($request));
        AuditLogger::log('entite_compta.creee', $e, ['code' => $e->code, 'budget' => (string) $e->budget_reference]);

        return redirect()->route('comptabilite.entites.show', $e)->with('success', 'Entité comptable créée.');
    }

    public function show(AccountingEntity $entite)
    {
        $entite->load(['parent', 'organisation', 'responsable', 'exercice', 'enfants']);
        $consomme = $entite->depenseConsommee();
        $peutVoirOps = auth()->user()->can('recettes.voir') || auth()->user()->can('depenses.voir');

        return view('comptabilite.entites.show', [
            'entite' => $entite, 'consomme' => $consomme, 'peutVoirOps' => $peutVoirOps,
            'recettes' => $peutVoirOps && auth()->user()->can('recettes.voir') ? $entite->recettes()->with('categorie')->latest('date_recette')->take(10)->get() : collect(),
            'depenses' => $peutVoirOps && auth()->user()->can('depenses.voir') ? $entite->depenses()->with('categorie')->latest('date_depense')->take(10)->get() : collect(),
            'totalRecettes' => auth()->user()->can('recettes.voir') ? (float) $entite->recettes()->sum('montant') : null,
        ]);
    }

    public function edit(AccountingEntity $entite)
    {
        return view('comptabilite.entites.form', $this->donnees($entite));
    }

    public function update(Request $request, AccountingEntity $entite)
    {
        $data = $this->valider($request, $entite);
        if ($entite->actif && ! $data['actif'] && $entite->enfants()->where('actif', true)->exists()) {
            throw ValidationException::withMessages(['actif' => "Désactivez d'abord les entités rattachées."]);
        }
        $avant = $entite->only('code', 'budget_reference', 'exercise_id', 'actif');
        $entite->update($data);
        AuditLogger::log('entite_compta.modifiee', $entite, ['avant' => $avant, 'apres' => $entite->only('code', 'budget_reference', 'exercise_id', 'actif')]);

        return redirect()->route('comptabilite.entites.show', $entite)->with('success', 'Entité comptable mise à jour.');
    }

    public function destroy(AccountingEntity $entite)
    {
        $this->authorize('compta.entites.gerer');
        if ($entite->aDesOperations() || $entite->enfants()->exists()) {
            return back()->withErrors(['entite' => "Suppression impossible : des opérations ou des entités filles y sont rattachées. Désactivez l'entité pour conserver l'historique."]);
        }
        AuditLogger::log('entite_compta.supprimee', $entite, ['code' => $entite->code]);
        $entite->delete();

        return redirect()->route('comptabilite.entites.index')->with('success', 'Entité supprimée.');
    }

    private function donnees(AccountingEntity $entite): array
    {
        $interdits = $entite->exists ? array_merge([$entite->id], $this->descendants($entite)) : [];

        return [
            'entite' => $entite, 'types' => AccountingEntity::TYPES,
            'parents' => AccountingEntity::where('actif', true)->whereNotIn('id', $interdits)->orderBy('code')->get(),
            'organisations' => OrganizationEntity::where('actif', true)->orderBy('nom')->get(),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name']),
            'exercices' => AccountingExercise::orderByDesc('date_debut')->get(),
        ];
    }

    private function descendants(AccountingEntity $e): array
    {
        $ids = [];
        $front = [$e->id];
        while ($front) {
            $front = AccountingEntity::whereIn('parent_id', $front)->pluck('id')->all();
            $ids = array_merge($ids, $front);
        }

        return $ids;
    }

    private function valider(Request $request, ?AccountingEntity $entite = null): array
    {
        $this->authorize('compta.entites.gerer');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('accounting_entities', 'code')->ignore($entite)],
            'nom' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(AccountingEntity::TYPES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'exists:accounting_entities,id'],
            'organization_entity_id' => ['nullable', 'exists:organization_entities,id'],
            'responsable_id' => ['nullable', 'exists:users,id'],
            'exercise_id' => ['nullable', 'exists:accounting_exercises,id'],
            'budget_reference' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], ['code.unique' => 'Ce code est déjà utilisé par une autre entité comptable.']);
        $data['code'] = strtoupper($data['code']);
        $data['actif'] = $request->boolean('actif');
        $budget = $data['budget_reference'] ?? null;

        if ($budget !== null && empty($data['exercise_id'])) {
            throw ValidationException::withMessages(['exercise_id' => 'Un budget de référence doit être rattaché à un exercice comptable.']);
        }
        if (! empty($data['exercise_id']) && $budget !== null) {
            $ex = AccountingExercise::find($data['exercise_id']);
            $inchange = $entite && (int) $entite->exercise_id === (int) $ex->id && (float) $entite->budget_reference === (float) $budget;
            if ($ex->statut === 'cloture' && ! $inchange) {
                throw ValidationException::withMessages(['exercise_id' => "L'exercice {$ex->code} est clôturé : son budget ne peut plus être modifié."]);
            }
        }

        if (! empty($data['parent_id'])) {
            $parent = AccountingEntity::findOrFail($data['parent_id']);
            if ($entite && ((int) $parent->id === $entite->id || in_array($parent->id, $this->descendants($entite), true))) {
                throw ValidationException::withMessages(['parent_id' => 'Une entité ne peut pas être sa propre parente, ni créer un cycle.']);
            }
            if ($budget !== null && $parent->budget_reference !== null) {
                if ((int) $parent->exercise_id !== (int) ($data['exercise_id'] ?? 0)) {
                    throw ValidationException::withMessages(['exercise_id' => "L'exercice doit être celui de l'entité parente."]);
                }
                $freres = (float) AccountingEntity::where('parent_id', $parent->id)->when($entite, fn ($q) => $q->where('id', '!=', $entite->id))->sum('budget_reference');
                if ($freres + (float) $budget > (float) $parent->budget_reference) {
                    throw ValidationException::withMessages(['budget_reference' => 'La somme des budgets des entités filles dépasse le budget de l\'entité parente (' . number_format((float) $parent->budget_reference, 0, ',', ' ') . ').']);
                }
            }
        }

        // Un budget ne peut pas être fixé en dessous de ce qui est déjà dépensé.
        if ($entite && $budget !== null && (float) $budget < $entite->depenseConsommee()) {
            throw ValidationException::withMessages(['budget_reference' => 'Le budget est inférieur aux dépenses déjà validées sur cette entité.']);
        }

        return $data;
    }
}

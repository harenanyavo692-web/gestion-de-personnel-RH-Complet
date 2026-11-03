<?php

namespace App\Http\Controllers\RH;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\OrganizationEntity;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EntiteController extends Controller
{
    public function index(Request $request)
    {
        $base = OrganizationEntity::query()
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('nom', 'like', "%{$t}%")->orWhere('code', 'like', "%{$t}%")))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->statut !== null && $request->statut !== '', fn ($q) => $q->where('actif', $request->statut === 'actif'));

        $arbre = $request->vue === 'arbre';

        return view('rh.entites.index', [
            'arbre' => $arbre,
            'entites' => $arbre ? collect() : (clone $base)->with(['parent', 'responsable'])->withCount('employes')->orderBy('code')->paginate(15)->withQueryString(),
            'racines' => $arbre ? OrganizationEntity::with('enfants')->whereNull('parent_id')->orderBy('nom')->get() : collect(),
            'comptes' => $arbre ? OrganizationEntity::withCount('employes')->pluck('employes_count', 'id') : collect(),
            'types' => OrganizationEntity::TYPES,
        ]);
    }

    public function create()
    {
        return view('rh.entites.form', $this->donneesFormulaire(new OrganizationEntity(['actif' => true])));
    }

    public function store(Request $request)
    {
        $data = $this->valider($request);
        $entite = OrganizationEntity::create($data);
        AuditLogger::log('entite_rh.creee', $entite, ['code' => $entite->code, 'type' => $entite->type]);

        return redirect()->route('rh.entites.show', $entite)->with('success', 'Entité créée.');
    }

    public function show(OrganizationEntity $entite)
    {
        return view('rh.entites.show', [
            'entite' => $entite->load(['parent', 'responsable', 'enfants' => fn ($q) => $q->withCount('employes')]),
            'employes' => $entite->employes()->orderBy('nom')->get(),
            'entitesComptables' => $entite->entitesComptables()->get(),
        ]);
    }

    public function edit(OrganizationEntity $entite)
    {
        return view('rh.entites.form', $this->donneesFormulaire($entite));
    }

    public function update(Request $request, OrganizationEntity $entite)
    {
        $data = $this->valider($request, $entite);

        // Désactiver est possible tant qu'aucune entité fille active n'en dépend ; les employés restent rattachés (historique).
        if ($entite->actif && ! $data['actif'] && $entite->enfants()->where('actif', true)->exists()) {
            throw ValidationException::withMessages(['actif' => "Désactivez d'abord les entités rattachées à celle-ci."]);
        }

        $avant = $entite->only('code', 'type', 'parent_id', 'actif');
        $entite->update($data);
        // Le libellé du département affiché sur les fiches suit un renommage.
        if ($entite->wasChanged('nom')) {
            Employe::where('entity_id', $entite->id)->update(['departement' => $entite->nom]);
        }
        AuditLogger::log('entite_rh.modifiee', $entite, ['avant' => $avant, 'apres' => $entite->only('code', 'type', 'parent_id', 'actif')]);

        return redirect()->route('rh.entites.show', $entite)->with('success', 'Entité mise à jour.');
    }

    public function destroy(OrganizationEntity $entite)
    {
        $this->authorize('rh.entites.gerer');

        if ($entite->employes()->exists() || $entite->enfants()->exists() || $entite->entitesComptables()->exists()) {
            return back()->withErrors(['entite' => 'Suppression impossible : des employés, des entités ou des périmètres comptables y sont rattachés. Désactivez-la plutôt.']);
        }
        AuditLogger::log('entite_rh.supprimee', $entite, ['code' => $entite->code]);
        $entite->delete();

        return redirect()->route('rh.entites.index')->with('success', 'Entité supprimée.');
    }

    private function donneesFormulaire(OrganizationEntity $entite): array
    {
        $interdits = $entite->exists ? array_merge([$entite->id], $entite->descendantIds()) : [];

        return [
            'entite' => $entite,
            'types' => OrganizationEntity::TYPES,
            'parents' => OrganizationEntity::where('actif', true)->whereNotIn('id', $interdits)->orderBy('nom')->get(),
            'responsables' => Employe::where('statut', '!=', 'inactif')->orderBy('nom')->get(['id', 'nom', 'prenom']),
        ];
    }

    private function valider(Request $request, ?OrganizationEntity $entite = null): array
    {
        $this->authorize('rh.entites.gerer');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('organization_entities', 'code')->ignore($entite)],
            'nom' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(OrganizationEntity::TYPES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'exists:organization_entities,id'],
            'responsable_id' => ['nullable', 'exists:employes,id'],
            'localisation' => ['nullable', 'string', 'max:150'],
        ], ['code.unique' => 'Ce code est déjà utilisé par une autre entité.']);
        $data['code'] = strtoupper($data['code']);
        $data['actif'] = $request->boolean('actif');

        if ($entite && ! empty($data['parent_id'])) {
            // Une entité ne peut être ni son propre parent, ni rattachée à l'un de ses descendants (cycle).
            if ((int) $data['parent_id'] === $entite->id) {
                throw ValidationException::withMessages(['parent_id' => 'Une entité ne peut pas être sa propre entité parente.']);
            }
            if (in_array((int) $data['parent_id'], $entite->descendantIds(), true)) {
                throw ValidationException::withMessages(['parent_id' => 'Cette entité parente créerait un cycle dans la hiérarchie.']);
            }
        }
        if (! empty($data['parent_id']) && ! OrganizationEntity::whereKey($data['parent_id'])->value('actif')) {
            throw ValidationException::withMessages(['parent_id' => "L'entité parente est inactive."]);
        }

        return $data;
    }
}

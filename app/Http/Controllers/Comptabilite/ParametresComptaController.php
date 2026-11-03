<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use App\Models\AccountingExercise;
use App\Models\AccountingJournal;
use App\Models\CategorieFinanciere;
use App\Models\Permission;
use App\Models\ReferenceItem;
use App\Models\Role;
use App\Services\ParametreService;
use Illuminate\Http\Request;

class ParametresComptaController extends Controller
{
    public const SECTIONS = [
        'general' => 'Général', 'plan' => 'Plan comptable', 'journaux' => 'Journaux',
        'categories' => 'Recettes et dépenses', 'paiements' => 'Paiements et pièces',
        'exercices' => 'Exercices comptables', 'securite' => 'Validations et sécurité',
    ];

    public function index(Request $request, ParametreService $p)
    {
        $section = array_key_exists($request->section, self::SECTIONS) ? $request->section : 'general';
        $q = trim((string) $request->q);
        $like = fn ($query, array $cols) => $q
            ? $query->where(fn ($w) => collect($cols)->each(fn ($c) => $w->orWhere($c, 'like', "%{$q}%")))
            : $query;
        $ref = fn (string $g) => $like(ReferenceItem::groupe($g), ['libelle', 'code'])->get();

        return view('comptabilite.parametres', [
            'section' => $section, 'sections' => self::SECTIONS, 'q' => $q,
            'general' => $p->get('compta.general'), 'securite' => $p->get('compta.securite'),
            'comptes' => $section === 'plan' ? $like(AccountingAccount::query(), ['numero', 'libelle'])
                ->when($request->classe, fn ($w, $c) => $w->where('classe', $c))->orderBy('numero')->paginate(15)->withQueryString() : null,
            'journaux' => $section === 'journaux' ? $like(AccountingJournal::query(), ['code', 'libelle'])->orderBy('code')->get() : collect(),
            'categories' => $section === 'categories' ? $like(CategorieFinanciere::query(), ['nom'])->orderBy('type')->orderBy('nom')->get() : collect(),
            'modes' => $section === 'paiements' ? $ref('mode_paiement') : collect(),
            'pieces' => $section === 'paiements' ? $ref('type_piece') : collect(),
            'exercices' => $section === 'exercices' ? AccountingExercise::orderByDesc('date_debut')->get() : collect(),
            'matrice' => $section === 'securite' ? $this->matrice() : [],
        ]);
    }

    /** Qui peut saisir, valider, administrer : lecture seule ici, la modification passe par « Rôles et permissions ». */
    private function matrice(): array
    {
        $perms = ['recettes.gerer' => 'Saisir des recettes', 'depenses.soumettre' => 'Soumettre des dépenses', 'depenses.valider' => 'Valider les dépenses',
            'depenses.valider_haut' => 'Valider au-dessus du seuil', 'salaires.payer' => 'Payer les salaires', 'regie.emettre' => "Émettre l'ordre de versement",
            'compta.parametres.gerer' => 'Modifier les paramètres', 'compta.entites.gerer' => 'Gérer les entités'];
        $roles = Role::with('permissions:id,slug')->orderBy('libelle')->get();

        return ['perms' => $perms, 'roles' => $roles->map(fn ($r) => [
            'libelle' => $r->libelle, 'a' => collect(array_keys($perms))->mapWithKeys(fn ($s) => [$s => $r->permissions->contains('slug', $s)])->all(),
        ])->filter(fn ($r) => in_array(true, $r['a'], true))->values()->all()];
    }

    public function update(Request $request, ParametreService $p)
    {
        $this->authorize('compta.parametres.gerer');

        if ($request->input('section') === 'securite') {
            $data = $request->validate([
                'seuil_double_validation' => ['required', 'numeric', 'min:0', 'max:999999999999'],
                'alerte_budget_pct' => ['required', 'integer', 'between:1,100'],
            ]);
            $p->set('compta.securite', [
                'seuil_double_validation' => (float) $data['seuil_double_validation'],
                'bloquer_exercice_cloture' => $request->boolean('bloquer_exercice_cloture'),
                'alerte_budget_pct' => (int) $data['alerte_budget_pct'],
            ]);

            return redirect()->route('comptabilite.parametres.index', ['section' => 'securite'])->with('success', 'Règles de validation et de sécurité enregistrées.');
        }

        $data = $request->validate([
            'devise_code' => ['required', 'string', 'size:3', 'alpha'],
            'devise_symbole' => ['required', 'string', 'max:6'],
            'decimales' => ['required', 'integer', 'between:0,2'],
            'prefixes' => ['required', 'array'],
            'prefixes.*' => ['required', 'string', 'max:8', 'regex:/^[A-Za-z0-9]+$/'],
        ], ['prefixes.*.regex' => 'Un préfixe ne contient que des lettres et des chiffres.']);
        $p->set('compta.general', [
            'devise_code' => strtoupper($data['devise_code']), 'devise_symbole' => $data['devise_symbole'], 'decimales' => (int) $data['decimales'],
            'prefixes' => array_map('strtoupper', array_intersect_key($data['prefixes'], ParametreService::DEFAUTS['compta.general']['prefixes'])),
        ]);

        return redirect()->route('comptabilite.parametres.index', ['section' => 'general'])->with('success', 'Paramètres généraux enregistrés.');
    }
}

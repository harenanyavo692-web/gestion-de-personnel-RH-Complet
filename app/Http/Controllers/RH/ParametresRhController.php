<?php

namespace App\Http\Controllers\RH;

use App\Http\Controllers\Controller;
use App\Models\OrganizationEntity;
use App\Models\ReferenceItem;
use App\Services\ParametreService;
use Illuminate\Http\Request;

class ParametresRhController extends Controller
{
    public const SECTIONS = [
        'general' => 'Général', 'personnel' => 'Personnel', 'organisation' => 'Organisation',
        'contrats' => 'Contrats', 'conges' => 'Congés et absences', 'workflow' => 'Workflow de validation',
    ];

    public const PERSONNEL = [
        'categorie_personnel' => 'Catégories de personnel', 'statut_pro' => 'Statuts professionnels',
        'categorie_poste' => 'Catégories de postes', 'grade' => 'Grades / classifications', 'poste' => 'Postes et fonctions',
    ];

    public function index(Request $request, ParametreService $p)
    {
        $section = array_key_exists($request->section, self::SECTIONS) ? $request->section : 'general';
        $q = trim((string) $request->q);

        $liste = fn (string $groupe) => ReferenceItem::groupe($groupe)
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('libelle', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")))->get();

        return view('rh.parametres', [
            'section' => $section, 'sections' => self::SECTIONS, 'q' => $q,
            'general' => $p->get('rh.general'), 'workflow' => $p->get('rh.workflow'),
            'listes' => $section === 'personnel' ? collect(self::PERSONNEL)->map(fn ($l, $g) => ['titre' => $l, 'items' => $liste($g)]) : collect(),
            'contrats' => $section === 'contrats' ? $liste('type_contrat') : collect(),
            'absences' => $section === 'conges' ? $liste('type_absence') : collect(),
            'entites' => $section === 'organisation'
                ? OrganizationEntity::with(['responsable', 'parent'])->withCount('employes')->orderBy('type')->orderBy('nom')->get() : collect(),
        ]);
    }

    public function update(Request $request, ParametreService $p)
    {
        $this->authorize('rh.parametres.gerer');

        if ($request->input('section') === 'workflow') {
            $data = $request->validate([
                'validateur' => ['required', 'in:responsable,rh,direction'],
                'delai_reponse_jours' => ['required', 'integer', 'min:1', 'max:60'],
            ]);
            $p->set('rh.workflow', [
                'validation_requise' => $request->boolean('validation_requise'),
                'validateur' => $data['validateur'],
                'delai_reponse_jours' => (int) $data['delai_reponse_jours'],
                'solde_negatif_autorise' => $request->boolean('solde_negatif_autorise'),
            ]);

            return redirect()->route('rh.parametres.index', ['section' => 'workflow'])->with('success', 'Règles de validation enregistrées.');
        }

        $data = $request->validate([
            'organisation' => ['required', 'string', 'max:200'],
            'adresse' => ['nullable', 'string', 'max:250'],
            'telephone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:150'],
            'annee_reference' => ['nullable', 'integer', 'between:2000,2100'],
            'lignes_par_page' => ['required', 'integer', 'in:10,15,25,50'],
        ]);
        $p->set('rh.general', $data + ['annee_reference' => null]);

        return redirect()->route('rh.parametres.index', ['section' => 'general'])->with('success', 'Paramètres généraux enregistrés.');
    }
}

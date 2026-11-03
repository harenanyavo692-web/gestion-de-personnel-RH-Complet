<?php

namespace App\Http\Controllers;

use App\Models\Employe;
use App\Models\OrganizationEntity;
use App\Models\ReferenceItem;
use Illuminate\Http\Request;

class EmployeController extends Controller
{
    /** Entités sélectionnables pour le rattachement d'un employé (actives, hors direction générale transversale non exclue). */
    private function entites()
    {
        return OrganizationEntity::where('actif', true)->orderBy('nom')->get(['id', 'code', 'nom', 'type']);
    }

    /** Suggestions de postes issues des paramètres RH (saisie libre toujours possible). */
    private function postes()
    {
        return ReferenceItem::groupe('poste')->actifs()->pluck('libelle');
    }

    public function index(Request $request)
    {
        $employes = Employe::nonArchive()->recherche($request->get('q'))
            ->when($request->get('entity_id'), fn ($q, $id) => $q->where('entity_id', $id))
            ->when($request->get('statut'), fn ($q, $statut) => $q->where('statut', $statut))
            ->orderBy('nom')
            ->paginate((int) app(\App\Services\ParametreService::class)->get('rh.general')['lignes_par_page'])
            ->withQueryString();

        $stats = [
            'total' => Employe::nonArchive()->count(),
            'actifs' => Employe::where('statut', 'actif')->count(),
            'conges' => Employe::where('statut', 'congé')->count(),
            'suspendus' => Employe::where('statut', 'suspendu')->count(),
        ];

        return view('employes.index', ['employes' => $employes, 'entites' => OrganizationEntity::orderBy('nom')->get(['id', 'nom']), 'stats' => $stats]);
    }

    public function create()
    {
        return view('employes.create', ['entites' => $this->entites(), 'postes' => $this->postes()]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEmploye($request);
        $data['matricule'] = $data['matricule'] ?? $this->genererMatricule();

        Employe::create($data);

        return redirect()->route('employes.index')->with('success', 'Employé ajouté avec succès.');
    }

    public function edit(Employe $employe)
    {
        return view('employes.edit', ['employe' => $employe, 'entites' => $this->entites(), 'postes' => $this->postes()]);
    }

    public function update(Request $request, Employe $employe)
    {
        $employe->update($this->validateEmploye($request, $employe));

        return redirect()->route('employes.index')->with('success', 'Fiche employé mise à jour.');
    }

    public function destroy(Employe $employe)
    {
        if ($employe->paiementsSalaires()->exists() || $employe->heuresSupplementaires()->exists()) {
            return back()->withErrors(['employe' => 'Suppression impossible : des salaires ou heures supplémentaires sont enregistrés pour cet employé. Passez-le en statut « inactif ».']);
        }
        $employe->delete();

        return redirect()->route('employes.index')->with('success', 'Employé supprimé.');
    }

    private function validateEmploye(Request $request, ?Employe $employe = null): array
    {
        $ignore = $employe?->id;
        $data = $request->validate([
            'matricule' => 'nullable|string|max:20|unique:employes,matricule' . ($ignore ? ",$ignore" : ''),
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'email' => 'required|email|unique:employes,email' . ($ignore ? ",$ignore" : ''),
            'telephone' => 'nullable|string|max:30',
            'poste' => 'required|string|max:100',
            // Le rattachement doit viser une entité active ; l'entité actuelle reste acceptée même désactivée (historique).
            'entity_id' => ['required', \Illuminate\Validation\Rule::exists('organization_entities', 'id')->where(fn ($q) => $q->where('actif', true)->orWhere('id', $employe?->entity_id))],
            'date_embauche' => 'required|date',
            'salaire' => 'nullable|numeric|min:0',
            'statut' => 'required|in:actif,congé,suspendu,inactif',
            'adresse' => 'nullable|string',
        ]);
        // Le libellé est conservé sur la fiche : il survit à une suppression ou à un renommage ultérieur.
        $data['departement'] = OrganizationEntity::whereKey($data['entity_id'])->value('nom');

        return $data;
    }

    private function genererMatricule(): string
    {
        return 'CDPHM-' . now()->format('y') . '-' . str_pad((Employe::max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    }
}

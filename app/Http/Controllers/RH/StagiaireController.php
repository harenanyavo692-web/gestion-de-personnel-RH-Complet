<?php

namespace App\Http\Controllers\RH;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\OrganizationEntity;
use App\Models\Stagiaire;
use App\Services\AuditLogger;
use App\Services\ParametreService;
use App\Support\Fmt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class StagiaireController extends Controller
{
    public function index(Request $request)
    {
        $stagiaires = Stagiaire::with(['entite', 'tuteur'])
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('nom', 'like', "%{$t}%")
                ->orWhere('prenom', 'like', "%{$t}%")->orWhere('etablissement', 'like', "%{$t}%")))
            ->orderByDesc('date_debut')->paginate(15)->withQueryString();

        return view('rh.stagiaires', [
            'stagiaires' => $stagiaires,
            'entites' => OrganizationEntity::where('actif', true)->orderBy('nom')->get(['id', 'nom']),
            'tuteurs' => Employe::where('statut', '!=', 'inactif')->orderBy('nom')->get(),
            'enCours' => Stagiaire::whereDate('date_debut', '<=', today())->whereDate('date_fin', '>=', today())->count(),
        ]);
    }

    public function store(Request $request)
    {
        $s = Stagiaire::create($this->valider($request));
        AuditLogger::log('stagiaire.cree', $s, ['nom' => $s->nom_complet]);

        return back()->with('success', "Stagiaire {$s->nom_complet} ajouté.");
    }

    /** Délivre le certificat : numéro unique attribué une seule fois, stage terminé obligatoire. */
    public function certifier(Request $request, Stagiaire $stagiaire)
    {
        $data = $request->validate([
            'appreciation' => ['nullable', 'in:' . implode(',', array_keys(Stagiaire::APPRECIATIONS))],
            'commentaire_certificat' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($stagiaire, $data, $request) {
            $s = Stagiaire::whereKey($stagiaire->id)->lockForUpdate()->firstOrFail();
            if (! $s->certifiable) {
                throw ValidationException::withMessages(['certificat' => "Le certificat ne peut être délivré qu'une fois le stage terminé."]);
            }
            if ($s->certifie) {
                throw ValidationException::withMessages(['certificat' => 'Un certificat a déjà été délivré à ce stagiaire.']);
            }
            $s->update($data + [
                'certificat_numero' => Fmt::reference('CERT'), 'certificat_le' => today(), 'certificat_par' => $request->user()->id,
            ]);
            AuditLogger::log('stagiaire.certifie', $s, ['nom' => $s->nom_complet, 'numero' => $s->certificat_numero]);
        });

        return redirect()->route('rh.stagiaires.certificat', $stagiaire)->with('success', 'Certificat délivré.');
    }

    public function certificat(Stagiaire $stagiaire, ParametreService $p)
    {
        abort_unless($stagiaire->certifie, 404);

        return view('rh.certificat-stage', ['s' => $stagiaire->load(['entite', 'tuteur']), 'entete' => $p->get('entete')]);
    }

    public function destroy(Stagiaire $stagiaire)
    {
        if ($stagiaire->certifie) {
            return back()->withErrors(['certificat' => 'Suppression impossible : un certificat a été délivré à ce stagiaire.']);
        }

        AuditLogger::log('stagiaire.supprime', $stagiaire, ['nom' => $stagiaire->nom_complet]);
        $stagiaire->delete();

        return back()->with('success', 'Stagiaire supprimé.');
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'etablissement' => ['required', 'string', 'max:150'],
            'filiere' => ['nullable', 'string', 'max:150'],
            'entity_id' => ['nullable', 'exists:organization_entities,id'],
            'tuteur_id' => ['nullable', 'exists:employes,id'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'gratification' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}

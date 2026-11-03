<?php

namespace App\Http\Controllers\RH;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Employe;
use App\Models\ReferenceItem;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContratController extends Controller
{
    public function index(Request $request)
    {
        $contrats = Contrat::nonArchive()->with('employe')
            ->when($request->statut === 'expire', fn ($q) => $q->where('statut', 'actif')->whereDate('date_fin', '<', today()))
            ->when(in_array($request->statut, ['actif', 'termine'], true), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->type, fn ($q, $t) => $q->where('type_code', $t))
            ->when($request->q, fn ($q, $t) => $q->whereHas('employe', fn ($e) => $e->where('nom', 'like', "%{$t}%")->orWhere('prenom', 'like', "%{$t}%")->orWhere('matricule', 'like', "%{$t}%")))
            ->orderByDesc('date_debut')->paginate(15)->withQueryString();

        return view('rh.contrats', [
            'contrats' => $contrats,
            'types' => ReferenceItem::groupe('type_contrat')->get(),
            'employes' => Employe::where('statut', '!=', 'inactif')->orderBy('nom')->get(['id', 'nom', 'prenom', 'matricule']),
            'expirant' => Contrat::where('statut', 'actif')->whereBetween('date_fin', [today(), today()->addDays(30)])->count(),
            'expires' => Contrat::where('statut', 'actif')->whereDate('date_fin', '<', today())->count(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('contrats.gerer');
        $data = $request->validate([
            'employe_id' => ['required', Rule::exists('employes', 'id')->where(fn ($q) => $q->where('statut', '!=', 'inactif'))],
            'type_code' => ['required', Rule::exists('reference_items', 'code')->where('groupe', 'type_contrat')->where('actif', true)],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut', 'required_unless:type_code,CDI'],
            'salaire' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['date_fin.required_unless' => "La date de fin est obligatoire hors contrat à durée indéterminée.", 'date_fin.after_or_equal' => 'La fin ne peut pas précéder le début.']);

        DB::transaction(function () use ($data, $request) {
            // Pas deux contrats actifs qui se chevauchent pour un même employé (fin vide = sans limite).
            $chevauche = Contrat::where('employe_id', $data['employe_id'])->where('statut', 'actif')
                ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $data['date_debut']))
                ->when($data['date_fin'] ?? null, fn ($q, $fin) => $q->whereDate('date_debut', '<=', $fin))
                ->lockForUpdate()->first();
            if ($chevauche) {
                throw ValidationException::withMessages(['date_debut' => 'Cet employé a déjà un contrat actif sur cette période (terminez-le d\'abord).']);
            }
            $contrat = Contrat::create([...$data, 'cree_par' => $request->user()->id]);
            if ($request->boolean('appliquer_salaire') && ! empty($data['salaire'])) {
                Employe::whereKey($data['employe_id'])->update(['salaire' => $data['salaire']]);
            }
            AuditLogger::log('contrat.cree', $contrat, ['employe_id' => $contrat->employe_id, 'type' => $contrat->type_code, 'salaire_applique' => $request->boolean('appliquer_salaire')]);
        });

        return back()->with('success', 'Contrat enregistré.');
    }

    public function terminer(Request $request, Contrat $contrat)
    {
        $this->authorize('contrats.gerer');
        if ($contrat->statut !== 'actif') {
            throw ValidationException::withMessages(['contrat' => 'Ce contrat est déjà terminé.']);
        }
        $data = $request->validate([
            'termine_le' => ['required', 'date', 'after_or_equal:' . $contrat->date_debut->toDateString()],
            'motif_fin' => ['nullable', 'string', 'max:200'],
        ]);
        $contrat->update($data + ['statut' => 'termine']);
        AuditLogger::log('contrat.termine', $contrat, ['employe_id' => $contrat->employe_id, 'le' => $data['termine_le']]);

        return back()->with('success', 'Contrat terminé.');
    }

    public function destroy(Contrat $contrat)
    {
        $this->authorize('contrats.gerer');
        $contrat->delete();

        return back()->with('success', 'Contrat placé dans la corbeille.');
    }
}

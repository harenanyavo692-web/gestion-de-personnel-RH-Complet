<?php

namespace App\Http\Controllers\RAF;

use App\Http\Controllers\Controller;
use App\Models\OrdreDepense;
use App\Services\ValidationDepenseService;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    public function index()
    {
        return view('raf.validations', [
            'enAttente' => OrdreDepense::with(['categorie', 'soumisPar', 'pieces'])
                ->where('statut', 'en_attente')->orderBy('date_depense')->get(),
            'historique' => OrdreDepense::with(['categorie', 'traitePar', 'pieces'])
                ->whereIn('statut', ['validee', 'refusee'])->orderByDesc('traite_le')->paginate(10),
        ]);
    }

    public function decider(Request $request, OrdreDepense $ordre, ValidationDepenseService $service)
    {
        $this->authorize('valider', $ordre);

        $data = $request->validate([
            'decision' => ['required', 'in:valider,refuser'],
            'commentaire' => ['required_if:decision,refuser', 'nullable', 'string', 'max:1000'],
        ], ['commentaire.required_if' => 'Un commentaire est obligatoire pour refuser un ordre.']);

        $service->traiter($ordre, $data['decision'], $data['commentaire'] ?? null, $request->user());

        return back()->with('success', $data['decision'] === 'valider' ? 'Ordre de dépense validé.' : 'Ordre de dépense refusé.');
    }
}

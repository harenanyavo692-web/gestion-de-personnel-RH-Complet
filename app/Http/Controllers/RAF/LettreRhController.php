<?php

namespace App\Http\Controllers\RAF;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\LettreRh;
use App\Services\AuditLogger;
use App\Services\ParametreService;
use App\Support\Fmt;
use Illuminate\Http\Request;

/** Lettres RH officielles : registre des documents délivrés, génération et impression. */
class LettreRhController extends Controller
{
    /** Types disponibles (conservé pour les anciens appels). */
    public const TYPES = LettreRh::TYPES;

    public function index(Request $request)
    {
        $lettres = LettreRh::with('auteur')
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('nom', 'like', "%{$t}%")->orWhere('reference', 'like', "%{$t}%")->orWhere('poste', 'like', "%{$t}%")))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return view('raf.lettres', [
            'lettres' => $lettres, 'types' => LettreRh::TYPES,
            'total' => LettreRh::count(),
            'ceMois' => LettreRh::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'employes' => Employe::orderBy('nom')->get(['id', 'nom', 'prenom', 'poste', 'date_embauche']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:' . implode(',', array_keys(LettreRh::TYPES))],
            'employe_id' => ['nullable', 'exists:employes,id'],
            'nom' => ['required', 'string', 'max:150'],
            'poste' => ['required', 'string', 'max:150'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => array_filter(['nullable', 'date', $request->filled('date_debut') ? 'after_or_equal:date_debut' : null]),
            'motif' => ['nullable', 'string', 'max:1000'],
            'signataire' => ['nullable', 'string', 'max:150'],
        ]);

        $lettre = LettreRh::create($data + ['reference' => Fmt::reference('LET'), 'cree_par' => $request->user()->id]);
        AuditLogger::log('lettre_rh.generee', $lettre, ['type' => $lettre->type, 'employe' => $lettre->nom, 'reference' => $lettre->reference]);

        return redirect()->route('raf.lettres.show', $lettre)->with('success', 'Lettre générée.');
    }

    public function show(LettreRh $lettre, ParametreService $parametres)
    {
        return view('raf.lettre', ['lettre' => $lettre, 'types' => LettreRh::TYPES, 'entete' => $parametres->get('entete')]);
    }

    public function destroy(LettreRh $lettre)
    {
        AuditLogger::log('lettre_rh.supprimee', $lettre, ['reference' => $lettre->reference, 'employe' => $lettre->nom]);
        $lettre->delete();

        return back()->with('success', 'Lettre supprimée du registre.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Elements;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Corbeille : les éléments supprimés restent récupérables. L'administrateur voit tout, le RH uniquement son périmètre ;
 * seule la suppression définitive (route protégée par « donnees.purger ») est réservée au super administrateur.
 */
class CorbeilleController extends Controller
{
    /** Types que l'utilisateur connecté peut gérer (admin.corbeille donne accès à tous). */
    private function autorises(): array
    {
        return Elements::accessibles(auth()->user(), 'admin.corbeille', 'corbeille.rh');
    }

    private function modele(string $type): string
    {
        return ($this->autorises()[$type] ?? abort(404))[0];
    }

    public function index(Request $request)
    {
        $types = $this->autorises();
        $type = array_key_exists($request->type, $types) ? $request->type : array_key_first($types);
        $classe = $this->modele($type);

        return view('admin.corbeille', [
            'type' => $type,
            'types' => collect($types)->map(fn ($d) => ['label' => $d[1], 'nombre' => $d[0]::onlyTrashed()->count()]),
            'elements' => $classe::onlyTrashed()->orderByDesc('deleted_at')->paginate(15)->withQueryString(),
        ]);
    }

    public function restaurer(string $type, int $id)
    {
        $this->modele($type)::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success', 'Élément restauré.');
    }

    public function supprimer(string $type, int $id)
    {
        $element = $this->modele($type)::onlyTrashed()->findOrFail($id);

        try {
            $element->forceDelete();
        } catch (QueryException) {
            return back()->withErrors(['corbeille' => 'Suppression définitive impossible : cet élément est encore référencé par d\'autres données (paiements, lots, contrats…).']);
        }

        return back()->with('success', 'Élément supprimé définitivement.');
    }

    /** Vide la corbeille d'un type (ou de tous ceux autorisés). Les éléments encore référencés sont conservés et signalés. */
    public function vider(Request $request)
    {
        $cible = $request->input('type');
        $autorises = $this->autorises();
        $types = $cible === 'tout' ? array_keys($autorises) : (array_key_exists($cible, $autorises) ? [$cible] : abort(404));
        $supprimes = 0;
        $conserves = 0;

        foreach ($types as $t) {
            foreach ($this->modele($t)::onlyTrashed()->get() as $element) {
                try {
                    $element->forceDelete();
                    $supprimes++;
                } catch (QueryException) {
                    $conserves++;
                }
            }
        }
        AuditLogger::log('corbeille.videe', null, ['type' => $cible, 'supprimes' => $supprimes, 'conserves' => $conserves]);

        return back()->with('success', "Corbeille vidée : {$supprimes} élément(s) supprimé(s) définitivement" . ($conserves ? ", {$conserves} conservé(s) car encore référencé(s)." : '.'));
    }
}

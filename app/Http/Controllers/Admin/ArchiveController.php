<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Elements;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Archives : les éléments terminés sortent des listes courantes sans être supprimés.
 * Archiver et désarchiver relèvent du droit de gestion du module ; la suppression définitive est réservée au super administrateur.
 */
class ArchiveController extends Controller
{
    private function accessibles(): array
    {
        return Elements::accessibles(auth()->user(), 'admin.archives', 'archives.rh');
    }

    private function classe(string $type): string
    {
        return ($this->accessibles()[$type] ?? abort(404))[0];
    }

    public function index(Request $request)
    {
        $types = $this->accessibles();
        $type = array_key_exists($request->type, $types) ? $request->type : array_key_first($types);
        $classe = $this->classe($type);

        return view('admin.archives', [
            'type' => $type,
            'types' => collect($types)->map(fn ($d) => ['label' => $d[1], 'nombre' => $d[0]::archives()->count()]),
            'elements' => $classe::archives()->orderByDesc('archived_at')->paginate(15)->withQueryString(),
            'peutGerer' => Elements::peutGerer(auth()->user(), $type),
        ]);
    }

    /** Archive un élément depuis la liste du module concerné. */
    public function archiver(Request $request, string $type, int $id)
    {
        abort_unless(isset(Elements::TYPES[$type]) && Elements::peutGerer($request->user(), $type), 403);
        $element = Elements::TYPES[$type][0]::findOrFail($id);

        if ($refus = Elements::refusArchivage($type, $element)) {
            return back()->withErrors(['archive' => $refus]);
        }

        $element->forceFill(['archived_at' => now(), 'archived_by' => $request->user()->id])->saveQuietly();
        AuditLogger::log('archive.archive', $element, ['type' => $type, 'libelle' => $element->libelleActivite()]);

        return back()->with('success', '« ' . $element->libelleActivite() . ' » archivé. Retrouvez-le dans Système › Archives.');
    }

    public function restaurer(Request $request, string $type, int $id)
    {
        abort_unless(Elements::peutGerer($request->user(), $type), 403);
        $element = $this->classe($type)::archives()->findOrFail($id);
        $element->forceFill(['archived_at' => null, 'archived_by' => null])->saveQuietly();
        AuditLogger::log('archive.desarchive', $element, ['type' => $type, 'libelle' => $element->libelleActivite()]);

        return back()->with('success', 'Élément remis dans la liste courante.');
    }

    /** Suppression définitive d'un élément archivé : super administrateur uniquement (route protégée par donnees.purger). */
    public function supprimer(string $type, int $id)
    {
        $element = $this->classe($type)::archives()->findOrFail($id);

        try {
            $element->forceDelete();
        } catch (QueryException) {
            return back()->withErrors(['archive' => 'Suppression définitive impossible : cet élément est encore référencé par d\'autres données (paiements, lots, contrats…).']);
        }
        AuditLogger::log('archive.purge', null, ['type' => $type, 'libelle' => $element->libelleActivite()]);

        return back()->with('success', 'Élément supprimé définitivement.');
    }
}

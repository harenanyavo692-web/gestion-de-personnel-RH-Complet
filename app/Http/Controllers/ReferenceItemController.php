<?php

namespace App\Http\Controllers;

use App\Models\ReferenceItem;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion des listes de référence (RH et Comptabilité). Un élément n'est jamais supprimé :
 * on le désactive, ce qui conserve l'historique des fiches qui l'utilisent.
 */
class ReferenceItemController extends Controller
{
    /** Groupes autorisés par domaine, avec la permission d'écriture associée. */
    private const GROUPES = [
        'rh' => ['perm' => 'rh.parametres.gerer', 'groupes' => ['categorie_personnel', 'statut_pro', 'categorie_poste', 'grade', 'poste', 'type_contrat', 'type_absence']],
        'compta' => ['perm' => 'compta.parametres.gerer', 'groupes' => ['mode_paiement', 'type_piece', 'axe_analytique']],
    ];

    private function domaineDuGroupe(string $groupe): string
    {
        foreach (self::GROUPES as $domaine => $def) {
            if (in_array($groupe, $def['groupes'], true)) {
                return $domaine;
            }
        }
        abort(422, 'Groupe inconnu.');
    }

    private function regles(string $groupe): array
    {
        $r = ['libelle' => ['required', 'string', 'max:150']];
        if ($groupe === 'type_absence') {
            $r += ['jours_annuels' => ['nullable', 'integer', 'min:0', 'max:366'], 'justificatif' => ['sometimes', 'boolean']];
        }

        return $r;
    }

    private function meta(Request $request, string $groupe): ?array
    {
        return $groupe === 'type_absence'
            ? ['jours_annuels' => $request->filled('jours_annuels') ? (int) $request->jours_annuels : null, 'justificatif' => $request->boolean('justificatif')]
            : null;
    }

    public function store(Request $request)
    {
        $groupe = (string) $request->input('groupe');
        $domaine = $this->domaineDuGroupe($groupe);
        $this->authorize(self::GROUPES[$domaine]['perm']);

        $data = $request->validate($this->regles($groupe) + [
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('reference_items')->where('groupe', $groupe)],
        ], ['code.unique' => 'Ce code existe déjà dans cette liste.']);

        $item = ReferenceItem::create([
            'domaine' => $domaine, 'groupe' => $groupe, 'code' => strtoupper($data['code']), 'libelle' => $data['libelle'],
            'meta' => $this->meta($request, $groupe), 'ordre' => (int) ReferenceItem::where('groupe', $groupe)->max('ordre') + 1,
        ]);
        AuditLogger::log('reference.cree', $item, ['groupe' => $groupe, 'code' => $item->code]);

        return back()->with('success', 'Élément ajouté.');
    }

    public function update(Request $request, ReferenceItem $item)
    {
        $this->authorize(self::GROUPES[$item->domaine]['perm']);
        $data = $request->validate($this->regles($item->groupe));

        $item->update([
            'libelle' => $data['libelle'],
            'actif' => $request->boolean('actif'),
            'meta' => $this->meta($request, $item->groupe) ?? $item->meta,
        ]);
        AuditLogger::log('reference.modifie', $item, ['groupe' => $item->groupe, 'code' => $item->code, 'actif' => $item->actif]);

        return back()->with('success', 'Élément mis à jour.');
    }
}

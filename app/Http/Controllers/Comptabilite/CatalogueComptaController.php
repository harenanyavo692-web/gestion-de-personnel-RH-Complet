<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use App\Models\AccountingExercise;
use App\Models\AccountingJournal;
use App\Models\CategorieFinanciere;
use App\Models\OrdreDepense;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Plan comptable, journaux, catégories et exercices : tout est désactivable, rien ne se supprime. */
class CatalogueComptaController extends Controller
{
    private function retour(string $section, string $message)
    {
        return redirect()->route('comptabilite.parametres.index', ['section' => $section])->with('success', $message);
    }

    public function compteStore(Request $request)
    {
        $this->authorize('compta.parametres.gerer');
        $data = $request->validate([
            'numero' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/', 'unique:accounting_accounts,numero'],
            'libelle' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(AccountingAccount::TYPES))],
        ], ['numero.regex' => 'Le numéro de compte est numérique.', 'numero.unique' => 'Ce numéro de compte existe déjà.']);
        $compte = AccountingAccount::create($data + ['classe' => (int) substr($data['numero'], 0, 1)]);
        AuditLogger::log('compte.cree', $compte, ['numero' => $compte->numero]);

        return $this->retour('plan', 'Compte ajouté.');
    }

    public function compteUpdate(Request $request, AccountingAccount $compte)
    {
        $this->authorize('compta.parametres.gerer');
        $data = $request->validate(['libelle' => ['required', 'string', 'max:150'], 'type' => ['required', Rule::in(array_keys(AccountingAccount::TYPES))]]);
        $compte->update($data + ['actif' => $request->boolean('actif')]);
        AuditLogger::log('compte.modifie', $compte, ['numero' => $compte->numero, 'actif' => $compte->actif]);

        return $this->retour('plan', 'Compte mis à jour.');
    }

    public function journalStore(Request $request)
    {
        $this->authorize('compta.parametres.gerer');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'alpha_num', 'unique:accounting_journals,code'],
            'libelle' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(AccountingJournal::TYPES))],
        ], ['code.unique' => 'Ce code de journal existe déjà.']);
        $j = AccountingJournal::create([...$data, 'code' => strtoupper($data['code'])]);
        AuditLogger::log('journal.cree', $j, ['code' => $j->code]);

        return $this->retour('journaux', 'Journal ajouté.');
    }

    public function journalUpdate(Request $request, AccountingJournal $journal)
    {
        $this->authorize('compta.parametres.gerer');
        $data = $request->validate(['libelle' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(array_keys(AccountingJournal::TYPES))]]);
        $journal->update($data + ['actif' => $request->boolean('actif')]);
        AuditLogger::log('journal.modifie', $journal, ['code' => $journal->code, 'actif' => $journal->actif]);

        return $this->retour('journaux', 'Journal mis à jour.');
    }

    public function categorieStore(Request $request)
    {
        $this->authorize('compta.parametres.gerer');
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:120', Rule::unique('categories_financieres', 'nom')->where('type', $request->input('type'))],
            'type' => ['required', 'in:recette,depense'],
        ], ['nom.unique' => 'Cette catégorie existe déjà.']);
        $c = CategorieFinanciere::create($data);
        AuditLogger::log('categorie.creee', $c, $data);

        return $this->retour('categories', 'Catégorie ajoutée.');
    }

    /** Les catégories de base (utilisées par la régie, le tunnel, la chambre froide…) ne sont pas renommables. */
    public function categorieUpdate(Request $request, CategorieFinanciere $categorie)
    {
        $this->authorize('compta.parametres.gerer');
        $systeme = in_array($categorie->nom, ['Tunnel de congélation', 'Chambre froide', 'Transport', 'Clients spéciaux'], true);
        if ($systeme && ! $request->boolean('actif')) {
            throw ValidationException::withMessages(['categorie' => 'Cette catégorie est utilisée par les circuits automatiques (régie, lots, transport) : elle ne peut pas être désactivée.']);
        }
        $data = $systeme ? [] : $request->validate(['nom' => ['required', 'string', 'max:120', Rule::unique('categories_financieres', 'nom')->where('type', $categorie->type)->ignore($categorie)]]);
        $categorie->update($data + ['actif' => $request->boolean('actif')]);
        AuditLogger::log('categorie.modifiee', $categorie, ['nom' => $categorie->nom, 'actif' => $categorie->actif]);

        return $this->retour('categories', 'Catégorie mise à jour.');
    }

    public function exerciceStore(Request $request)
    {
        $this->authorize('compta.parametres.gerer');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:accounting_exercises,code'],
            'libelle' => ['required', 'string', 'max:100'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
        ], ['code.unique' => 'Ce code d\'exercice existe déjà.', 'date_fin.after' => 'La fin doit être postérieure au début.']);

        $chevauche = AccountingExercise::whereDate('date_debut', '<=', $data['date_fin'])->whereDate('date_fin', '>=', $data['date_debut'])->first();
        if ($chevauche) {
            throw ValidationException::withMessages(['date_debut' => "Cette période chevauche l'exercice {$chevauche->code}."]);
        }
        $e = AccountingExercise::create($data);
        AuditLogger::log('exercice.cree', $e, ['code' => $e->code]);

        return $this->retour('exercices', 'Exercice créé.');
    }

    public function exerciceCloturer(Request $request, AccountingExercise $exercice)
    {
        $this->authorize('compta.parametres.gerer');
        if ($exercice->statut === 'cloture') {
            throw ValidationException::withMessages(['exercice' => 'Exercice déjà clôturé.']);
        }
        $attente = OrdreDepense::where('statut', 'en_attente')->whereBetween('date_depense', [$exercice->date_debut, $exercice->date_fin])->count();
        if ($attente > 0) {
            throw ValidationException::withMessages(['exercice' => "Clôture impossible : $attente ordre(s) de dépense de la période sont encore en attente de validation."]);
        }
        $exercice->update(['statut' => 'cloture', 'cloture_le' => now(), 'cloture_par' => $request->user()->id]);
        AuditLogger::log('exercice.cloture', $exercice, ['code' => $exercice->code]);

        return $this->retour('exercices', "Exercice {$exercice->code} clôturé.");
    }

    public function exerciceRouvrir(Request $request, AccountingExercise $exercice)
    {
        $this->authorize('compta.parametres.gerer');
        $exercice->update(['statut' => 'ouvert', 'cloture_le' => null, 'cloture_par' => null]);
        AuditLogger::log('exercice.rouvert', $exercice, ['code' => $exercice->code]);

        return $this->retour('exercices', "Exercice {$exercice->code} rouvert.");
    }
}

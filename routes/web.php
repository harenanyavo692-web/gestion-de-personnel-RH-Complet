<?php

use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CorbeilleController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Comptabilite\BudgetController;
use App\Http\Controllers\Comptabilite\CatalogueComptaController;
use App\Http\Controllers\Comptabilite\EntiteComptableController;
use App\Http\Controllers\Comptabilite\ParametresComptaController;
use App\Http\Controllers\Exploitation\TransportController;
use App\Http\Controllers\ReferenceItemController;
use App\Http\Controllers\RH\EntiteController as EntiteRhController;
use App\Http\Controllers\RH\CongeController;
use App\Http\Controllers\RH\ContratController;
use App\Http\Controllers\RH\StagiaireController;
use App\Http\Controllers\RH\ParametresRhController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Comptabilite\OrdreDepenseController;
use App\Http\Controllers\Comptabilite\CompteSpecialController;
use App\Http\Controllers\Comptabilite\PieceController;
use App\Http\Controllers\Comptabilite\RegistreController;
use App\Http\Controllers\Exploitation\ClientController;
use App\Http\Controllers\Exploitation\LotController;
use App\Http\Controllers\Comptabilite\RegieController;
use App\Http\Controllers\Comptabilite\RetardController;
use App\Http\Controllers\Comptabilite\TarifController;
use App\Http\Controllers\RAF\LettreRhController;
use App\Http\Controllers\Comptabilite\RecetteController;
use App\Http\Controllers\Comptabilite\SituationController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeController;
use App\Http\Controllers\ParametresController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\RAF\RapportController;
use App\Http\Controllers\RAF\RapprochementController;
use App\Http\Controllers\RAF\SalaireController;
use App\Http\Controllers\RAF\ValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
| Toutes les routes ci-dessous exigent une session ouverte. Chaque groupe est en plus protégé
| côté serveur par la permission du rôle (middleware "can:module.action") ; les policies
| affinent les règles par ressource.
*/
Route::middleware('auth')->group(function () {

    Route::get('/tableau-de-bord', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/parametres', [ParametresController::class, 'index'])->name('parametres.index');
    Route::post('/parametres', [ParametresController::class, 'update'])->name('parametres.update');
    Route::post('/parametres/avatar', [ParametresController::class, 'avatar'])->name('parametres.avatar');
    Route::delete('/parametres/avatar', [ParametresController::class, 'supprimerAvatar'])->name('parametres.avatar.supprimer');
    Route::get('/avatars/{user}', [ParametresController::class, 'afficherAvatar'])->name('avatars.show');

    // Ressources humaines
    Route::middleware('can:employes.voir')->group(function () {
        Route::get('employes', [EmployeController::class, 'index'])->name('employes.index');
    });
    Route::middleware('can:employes.gerer')->group(function () {
        Route::resource('employes', EmployeController::class)->except(['show', 'index']);
    });
    Route::get('/presences', [PresenceController::class, 'index'])->middleware('can:employes.voir')->name('presences.index');
    Route::post('/presences/{employe}/pointer', [PresenceController::class, 'pointer'])
        ->middleware('can:presences.gerer')->name('presences.pointer');

    // Ressources humaines : paramètres et entités organisationnelles
    Route::prefix('rh')->name('rh.')->group(function () {
        Route::get('stagiaires', [StagiaireController::class, 'index'])->middleware('can:employes.voir')->name('stagiaires.index');
        Route::post('stagiaires', [StagiaireController::class, 'store'])->middleware('can:employes.gerer')->name('stagiaires.store');
        Route::post('stagiaires/{stagiaire}/certificat', [StagiaireController::class, 'certifier'])->middleware('can:employes.gerer')->name('stagiaires.certifier');
        Route::get('stagiaires/{stagiaire}/certificat', [StagiaireController::class, 'certificat'])->middleware('can:employes.voir')->name('stagiaires.certificat');
        Route::delete('stagiaires/{stagiaire}', [StagiaireController::class, 'destroy'])->middleware('can:employes.gerer')->name('stagiaires.destroy');
        Route::get('parametres', [ParametresRhController::class, 'index'])->middleware('can:rh.parametres.voir')->name('parametres.index');
        Route::put('parametres', [ParametresRhController::class, 'update'])->name('parametres.update');

        Route::get('contrats', [ContratController::class, 'index'])->middleware('can:contrats.voir')->name('contrats.index');
        Route::post('contrats', [ContratController::class, 'store'])->name('contrats.store');
        Route::delete('contrats/{contrat}', [ContratController::class, 'destroy'])->name('contrats.destroy');
        Route::post('contrats/{contrat}/terminer', [ContratController::class, 'terminer'])->name('contrats.terminer');

        Route::get('conges', [CongeController::class, 'index'])->defaults('nature', 'conge')->middleware('can:conges.voir')->name('conges.index');
        Route::get('absences', [CongeController::class, 'index'])->defaults('nature', 'absence')->middleware('can:conges.voir')->name('absences.index');
        Route::post('conges', [CongeController::class, 'store'])->name('conges.store');
        Route::post('conges/{absence}/decider', [CongeController::class, 'decider'])->name('conges.decider');
        Route::post('conges/{absence}/annuler', [CongeController::class, 'annuler'])->name('conges.annuler');
        Route::get('conges/{absence}/justificatif', [CongeController::class, 'justificatif'])->name('conges.justificatif');
        // create/edit/store… d'abord : sinon « entites/create » serait pris pour « entites/{entite} » (404).
        Route::resource('entites', EntiteRhController::class)->parameters(['entites' => 'entite'])->except(['index', 'show'])->middleware('can:rh.entites.gerer');
        Route::resource('entites', EntiteRhController::class)->parameters(['entites' => 'entite'])->only(['index', 'show'])->middleware('can:rh.entites.voir');
    });

    // Listes de référence partagées RH / Compta (l'autorisation dépend du domaine, vérifiée dans le contrôleur)
    Route::post('references', [ReferenceItemController::class, 'store'])->name('references.store');
    Route::put('references/{item}', [ReferenceItemController::class, 'update'])->name('references.update');

    // Caisse centrale : encaissements en attente, vigilance client, journal du jour
    Route::get('caisse', [CaisseController::class, 'index'])->middleware('can:caisse.voir')->name('caisse.index');
    Route::get('caisse/export', [CaisseController::class, 'export'])->middleware('can:caisse.voir')->name('caisse.export');

    // Exploitation : clients, lots, transport, infrastructures
    Route::prefix('exploitation')->name('exploitation.')->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->middleware('can:clients.voir')->name('clients.index');
        Route::get('clients/documents', [TransportController::class, 'documentForm'])->defaults('module', 'cdc')->middleware('can:clients.voir')->name('clients.documents');
        Route::post('clients/documents', [TransportController::class, 'documentGenerer'])->defaults('module', 'cdc')->middleware('can:clients.voir')->name('clients.documents.generer');
        Route::get('clients/{client}', [ClientController::class, 'show'])->middleware('can:clients.voir')->name('clients.show');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        Route::get('lots', [LotController::class, 'index'])->middleware('can:lots.voir')->name('lots.index');
        Route::post('lots', [LotController::class, 'store'])->name('lots.store');
        Route::get('lots/{lot}/bon-entree', [LotController::class, 'bon'])->middleware('can:lots.voir')->name('lots.bon');
        Route::post('lots/{lot}/transferer', [LotController::class, 'transferer'])->name('lots.transferer');
        Route::post('lots/{lot}/tunnel', [LotController::class, 'tunnel'])->name('lots.tunnel');
        Route::post('lots/{lot}/froid', [LotController::class, 'froid'])->name('lots.froid');
        Route::post('lots/{lot}/sortie', [LotController::class, 'sortie'])->name('lots.sortie');
        Route::post('lots/{lot}/liberer', [LotController::class, 'liberer'])->name('lots.liberer');
        Route::get('transport', [TransportController::class, 'index'])->middleware('can:transport.voir')->name('transport.index');
        Route::get('transport/documents', [TransportController::class, 'documentForm'])->defaults('module', 'transport')->middleware('can:transport.voir')->name('transport.documents');
        Route::post('transport/documents', [TransportController::class, 'documentGenerer'])->defaults('module', 'transport')->middleware('can:transport.voir')->name('transport.documents.generer');
        Route::post('transport', [TransportController::class, 'store'])->name('transport.store');
        Route::post('transport/{mission}/encaisser', [TransportController::class, 'encaisser'])->name('transport.encaisser');
        Route::post('transport/{mission}/annuler', [TransportController::class, 'annuler'])->name('transport.annuler');
        Route::get('transport/{mission}/ordre-mission', [TransportController::class, 'ordre'])->middleware('can:transport.voir')->name('transport.ordre');
        Route::post('vehicules', [TransportController::class, 'vehiculeStore'])->name('vehicules.store');
        Route::put('vehicules/{vehicule}', [TransportController::class, 'vehiculeStatut'])->name('vehicules.update');
        Route::put('infrastructures/{infrastructure}', [LotController::class, 'infrastructure'])->name('infrastructures.update');
    });

    // Comptabilité
    Route::prefix('comptabilite')->name('comptabilite.')->group(function () {
        Route::get('recettes', [RecetteController::class, 'index'])->middleware('can:recettes.voir')->name('recettes.index');
        Route::post('recettes', [RecetteController::class, 'store'])->name('recettes.store');
        Route::delete('recettes/{recette}', [RecetteController::class, 'destroy'])->name('recettes.destroy');

        Route::get('depenses', [OrdreDepenseController::class, 'index'])->middleware('can:depenses.voir')->name('depenses.index');
        Route::post('depenses', [OrdreDepenseController::class, 'store'])->name('depenses.store');
        Route::delete('depenses/{depense}', [OrdreDepenseController::class, 'destroy'])->name('depenses.destroy');

        Route::get('clients-speciaux', [CompteSpecialController::class, 'index'])->middleware('can:recettes.voir')->name('speciaux.index');
        Route::post('clients-speciaux', [CompteSpecialController::class, 'store'])->name('speciaux.store');
        Route::post('clients-speciaux/{compte}/versements', [CompteSpecialController::class, 'versement'])->name('speciaux.versement');

        Route::get('parametres', [ParametresComptaController::class, 'index'])->middleware('can:compta.parametres.voir')->name('parametres.index');
        Route::put('parametres', [ParametresComptaController::class, 'update'])->name('parametres.update');
        Route::post('parametres/comptes', [CatalogueComptaController::class, 'compteStore'])->name('comptes.store');
        Route::put('parametres/comptes/{compte}', [CatalogueComptaController::class, 'compteUpdate'])->name('comptes.update');
        Route::post('parametres/journaux', [CatalogueComptaController::class, 'journalStore'])->name('journaux.store');
        Route::put('parametres/journaux/{journal}', [CatalogueComptaController::class, 'journalUpdate'])->name('journaux.update');
        Route::post('parametres/categories', [CatalogueComptaController::class, 'categorieStore'])->name('categories.store');
        Route::put('parametres/categories/{categorie}', [CatalogueComptaController::class, 'categorieUpdate'])->name('categories.update');
        Route::post('parametres/exercices', [CatalogueComptaController::class, 'exerciceStore'])->name('exercices.store');
        Route::post('parametres/exercices/{exercice}/cloturer', [CatalogueComptaController::class, 'exerciceCloturer'])->name('exercices.cloturer');
        Route::post('parametres/exercices/{exercice}/rouvrir', [CatalogueComptaController::class, 'exerciceRouvrir'])->name('exercices.rouvrir');
        Route::get('budgets', [BudgetController::class, 'index'])->middleware('can:budgets.voir')->name('budgets.index');
        // create/edit/store… d'abord : sinon « entites/create » serait pris pour « entites/{entite} » (404).
        Route::resource('entites', EntiteComptableController::class)->parameters(['entites' => 'entite'])->except(['index', 'show'])->middleware('can:compta.entites.gerer');
        Route::resource('entites', EntiteComptableController::class)->parameters(['entites' => 'entite'])->only(['index', 'show'])->middleware('can:compta.entites.voir');

        Route::get('regie',[RegieController::class, 'index'])->middleware('can:regie.voir')->name('regie.index');
        Route::get('regie/journal/export', [RegieController::class, 'export'])->middleware('can:regie.voir')->name('regie.export');
        Route::post('regie/cloturer', [RegieController::class, 'cloturer'])->name('regie.cloturer');
        Route::post('regie/{dossier}/verser', [RegieController::class, 'verser'])->name('regie.verser');
        Route::post('regie/{dossier}/recu', [RegieController::class, 'recu'])->name('regie.recu');
        Route::post('regie/{dossier}/transmettre', [RegieController::class, 'transmettre'])->name('regie.transmettre');
        Route::post('regie/{dossier}/emettre', [RegieController::class, 'emettre'])->name('regie.emettre');
        Route::get('regie/{dossier}/quittance', [RegieController::class, 'quittance'])->middleware('can:regie.voir')->name('regie.quittance');
        Route::get('regie/{dossier}/ordre-versement', [RegieController::class, 'ordre'])->middleware('can:regie.voir')->name('regie.ordre');

        Route::get('retards', [RetardController::class, 'index'])->middleware('can:tarifs.voir')->name('retards.index');
        Route::post('retards', [RetardController::class, 'store'])->name('retards.store');
        Route::post('retards/taux', [RetardController::class, 'taux'])->name('retards.taux');

        Route::get('tarifs', [TarifController::class, 'index'])->middleware('can:tarifs.voir')->name('tarifs.index');
        Route::post('tarifs', [TarifController::class, 'update'])->name('tarifs.update');

        Route::get('registres', [RegistreController::class, 'index'])->middleware('can:recettes.voir')->name('registres');
        Route::get('registres/export', [RegistreController::class, 'export'])->middleware('can:recettes.voir')->name('registres.export');
        Route::get('situation/export', [SituationController::class, 'export'])->middleware('can:rapports.voir')->name('situation.export');
        Route::get('situation',[SituationController::class, 'index'])->middleware('can:rapports.voir')->name('situation');
        Route::get('pieces/{piece}', [PieceController::class, 'show'])->name('pieces.show');
    });

    // Espace RAF
    Route::prefix('raf')->name('raf.')->group(function () {
        Route::get('validations', [ValidationController::class, 'index'])->middleware('can:depenses.valider')->name('validations.index');
        Route::post('validations/{ordre}', [ValidationController::class, 'decider'])->name('validations.decider');

        Route::get('salaires', [SalaireController::class, 'index'])->middleware('can:salaires.voir')->name('salaires.index');
        Route::get('salaires/historique', [SalaireController::class, 'historique'])->middleware('can:salaires.voir')->name('salaires.historique');
        Route::get('salaires/etat', [SalaireController::class, 'etat'])->middleware('can:salaires.voir')->name('salaires.etat');
        Route::get('salaires/{paiement}/recu', [SalaireController::class, 'recu'])->middleware('can:salaires.voir')->name('salaires.recu');
        Route::post('salaires/{employe}', [SalaireController::class, 'payer'])->middleware('can:salaires.payer')->name('salaires.payer');
        Route::post('salaires/{employe}/heures-sup', [SalaireController::class, 'ajouterHeuresSup'])->middleware('can:heures_sup.saisir')->name('heures-sup.store');
        Route::post('salaires/{employe}/heures-sup/payer', [SalaireController::class, 'payerHeuresSup'])->middleware('can:salaires.payer')->name('heures-sup.payer');
        Route::delete('heures-sup/{heure}', [SalaireController::class, 'supprimerHeuresSup'])->middleware('can:heures_sup.saisir')->name('heures-sup.destroy');

        Route::middleware('can:rapprochement.gerer')->group(function () {
            Route::get('rapprochements', [RapprochementController::class, 'index'])->name('rapprochements.index');
            Route::post('rapprochements', [RapprochementController::class, 'store'])->name('rapprochements.store');
        });

        Route::middleware('can:rapports.voir')->group(function () {
            Route::get('rapports', [RapportController::class, 'index'])->name('rapports.index');
            Route::get('rapports/export', [RapportController::class, 'export'])->name('rapports.export');
        });
        Route::middleware('can:lettres.generer')->group(function () {
            Route::get('lettres', [LettreRhController::class, 'index'])->name('lettres');
            Route::post('lettres', [LettreRhController::class, 'store'])->name('lettres.store');
            Route::get('lettres/{lettre}', [LettreRhController::class, 'show'])->name('lettres.show');
            Route::delete('lettres/{lettre}', [LettreRhController::class, 'destroy'])->name('lettres.destroy');
        });
        Route::post('rapports/commentaires', [RapportController::class, 'commenter'])
            ->middleware('can:rapports.commenter')->name('rapports.commenter');
    });

    // Administration
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('utilisateurs', UtilisateurController::class)
            ->parameters(['utilisateurs' => 'utilisateur'])->except(['show', 'destroy'])
            ->middleware('can:admin.utilisateurs');
        Route::get('roles', [RoleController::class, 'index'])->middleware('can:admin.roles')->name('roles.index');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('can:admin.roles')->name('roles.update');
        Route::post('roles', [RoleController::class, 'store'])->middleware('can:admin.roles')->name('roles.store');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('can:admin.roles')->name('roles.destroy');
        Route::get('corbeille', [CorbeilleController::class, 'index'])->middleware('can:corbeille')->name('corbeille.index');
        Route::post('corbeille/{type}/{id}/restaurer', [CorbeilleController::class, 'restaurer'])->middleware('can:corbeille')->name('corbeille.restaurer');
        Route::delete('corbeille/{type}/{id}', [CorbeilleController::class, 'supprimer'])->middleware('can:corbeille')->name('corbeille.supprimer');
        Route::delete('corbeille', [CorbeilleController::class, 'vider'])->middleware('can:corbeille')->name('corbeille.vider');
        // Archives : archiver depuis la liste d'un module (droit vérifié dans le contrôleur), consulter / restaurer ici.
        Route::post('archives/{type}/{id}/archiver', [ArchiveController::class, 'archiver'])->name('archives.archiver');
        Route::get('archives', [ArchiveController::class, 'index'])->middleware('can:archives')->name('archives.index');
        Route::post('archives/{type}/{id}/restaurer', [ArchiveController::class, 'restaurer'])->middleware('can:archives')->name('archives.restaurer');
        Route::delete('archives/{type}/{id}', [ArchiveController::class, 'supprimer'])->middleware('can:donnees.purger')->name('archives.supprimer');
        Route::get('audit/export', [AuditController::class, 'export'])->middleware('can:journal')->name('audit.export');
        Route::get('audit',[AuditController::class, 'index'])->middleware('can:journal')->name('audit');
    });
});

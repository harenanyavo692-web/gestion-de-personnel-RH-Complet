<?php

namespace Database\Seeders;

use App\Models\CategorieFinanciere;
use App\Models\Infrastructure;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Données de référence (rôles, permissions, catégories). Idempotent : peut être relancé sans doublon.
 */
class RolePermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'employes.voir' => 'Consulter le personnel',
        'employes.gerer' => 'Créer / modifier / supprimer le personnel',
        'presences.gerer' => 'Pointer les présences et congés',
        'recettes.voir' => 'Consulter les recettes',
        'recettes.gerer' => 'Enregistrer / supprimer des recettes',
        'depenses.voir' => 'Consulter les ordres de dépense',
        'depenses.soumettre' => 'Soumettre des ordres de dépense',
        'depenses.valider' => 'Valider / refuser les dépenses (RAF)',
        'salaires.voir' => 'Consulter les salaires',
        'salaires.payer' => 'Enregistrer le paiement des salaires',
        'heures_sup.saisir' => 'Saisir et supprimer des heures supplémentaires (le paiement reste au RAF)',
        'rapprochement.gerer' => 'Gérer le rapprochement Trésor',
        'rapports.voir' => 'Consulter les rapports financiers',
        'rapports.commenter' => 'Commenter les rapports',
        'admin.utilisateurs' => 'Gérer les utilisateurs et rôles',
        'corbeille.rh' => 'Corbeille — employés et contrats',
        'activite.voir' => 'Journal d\'activité — périmètre RH',
        'admin.corbeille' => 'Gérer la corbeille (restaurer, vider)',
        'admin.audit' => "Consulter le journal d'audit",
        'lettres.generer' => 'Générer des lettres RH',
        'regie.voir' => 'Consulter la régie',
        'regie.gerer' => 'Gérer la régie (journal, versement, reçu Trésor)',
        'regie.emettre' => "Émettre l'ordre de versement",
        'tarifs.voir' => 'Consulter les tarifs et paramètres',
        'tarifs.gerer' => 'Modifier les tarifs et paramètres',
        'majoration.gerer' => 'Gérer la majoration de retard',
        'clients.voir' => 'Consulter les clients',
        'clients.gerer' => 'Créer / modifier les clients',
        'lots.voir' => 'Consulter les lots et le stock',
        'lots.entree' => "Enregistrer des bons d'entrée",
        'lots.transferer' => 'Transférer un lot tunnel → chambre froide',
        'lots.encaisser' => 'Encaisser tunnel / chambre froide (caisse)',
        'lots.sortir' => 'Retraits et libération de lots',
        'infrastructures.gerer' => 'Changer le statut des infrastructures',
        'rh.parametres.voir' => 'Consulter les paramètres RH',
        'rh.parametres.gerer' => 'Modifier les paramètres RH',
        'rh.entites.voir' => 'Consulter les entités RH',
        'rh.entites.gerer' => 'Gérer les entités RH',
        'compta.parametres.voir' => 'Consulter les paramètres comptables',
        'compta.parametres.gerer' => 'Modifier les paramètres comptables',
        'compta.entites.voir' => 'Consulter les entités comptables',
        'compta.entites.gerer' => 'Gérer les entités comptables',
        'budgets.voir' => 'Consulter le suivi budgétaire',
        'contrats.voir' => 'Consulter les contrats',
        'contrats.gerer' => 'Créer / terminer des contrats',
        'conges.voir' => 'Consulter les congés et absences',
        'conges.gerer' => "Saisir et annuler des demandes d'absence",
        'conges.valider' => 'Valider les demandes (service RH)',
        'conges.valider_direction' => 'Valider les demandes (direction, ou suppléance du responsable)',
        'depenses.valider_haut' => 'Valider les dépenses au-delà du seuil de double validation',
        'admin.roles' => 'Gérer les rôles et leurs permissions',
        'transport.voir' => 'Consulter le transport',
        'transport.gerer' => 'Planifier les missions et gérer la flotte',
        'transport.encaisser' => 'Encaisser les missions de transport',
        'admin.archives' => 'Archives — consulter et gérer tous les types',
        'archives.rh' => 'Archives — employés, stagiaires, contrats et lettres RH',
        'donnees.purger' => 'Supprimer définitivement (corbeille et archives) — super administrateur uniquement',
        'caisse.voir' => "Consulter la caisse et les flux d'expéditions",
    ];

    private const ROLES = [
        // Reçoit TOUTES les permissions (voir run()), dont la suppression définitive.
        'superadmin' => ['Super administrateur', []],
        'admin' => ['Administrateur', ['employes.voir', 'employes.gerer', 'presences.gerer', 'admin.utilisateurs', 'admin.audit', 'admin.corbeille', 'lettres.generer', 'tarifs.voir', 'tarifs.gerer', 'infrastructures.gerer', 'admin.roles', 'rh.parametres.voir', 'rh.parametres.gerer', 'rh.entites.voir', 'rh.entites.gerer', 'compta.parametres.voir', 'compta.entites.voir', 'caisse.voir', 'conges.voir', 'admin.archives']],
        'direction' => ['Direction', [
            'employes.voir', 'recettes.voir', 'depenses.voir', 'salaires.voir', 'rapports.voir', 'rapports.commenter', 'admin.audit', 'lettres.generer', 'regie.voir', 'tarifs.voir', 'tarifs.gerer', 'clients.voir', 'lots.voir', 'infrastructures.gerer',
            'depenses.valider', 'depenses.valider_haut', 'contrats.voir', 'conges.voir', 'conges.valider_direction', 'rh.parametres.voir', 'rh.entites.voir', 'compta.parametres.voir', 'compta.parametres.gerer', 'compta.entites.voir', 'compta.entites.gerer', 'budgets.voir', 'transport.voir', 'caisse.voir',
        ]],
        'comptable' => ['Comptable', [
            'recettes.voir', 'recettes.gerer', 'depenses.voir', 'depenses.soumettre', 'rapports.voir',
            'regie.voir', 'regie.emettre', 'tarifs.voir', 'majoration.gerer', 'clients.voir', 'lots.voir',
            'compta.parametres.voir', 'compta.entites.voir', 'compta.entites.gerer', 'budgets.voir', 'transport.voir', 'caisse.voir',
        ]],
        'raf' => ['RAF', [
            'employes.voir', 'recettes.voir', 'depenses.voir', 'depenses.valider', 'salaires.voir', 'salaires.payer', 'heures_sup.saisir',
            'rapprochement.gerer', 'rapports.voir', 'rapports.commenter', 'lettres.generer',
            'regie.voir', 'regie.emettre', 'tarifs.voir', 'majoration.gerer', 'clients.voir', 'lots.voir',
            'compta.parametres.voir', 'compta.entites.voir', 'compta.entites.gerer', 'budgets.voir', 'transport.voir', 'caisse.voir',
        ]],
        'regisseuse' => ['Régisseuse', ['recettes.voir', 'regie.voir', 'regie.gerer', 'tarifs.voir', 'caisse.voir']],
        'cdd' => ['CDD — Quai (entrées)', ['clients.voir', 'lots.voir', 'lots.entree']],
        'magasinier' => ['Magasinier — Usine froid', ['clients.voir', 'lots.voir', 'lots.transferer', 'lots.sortir']],
        'caissier' => ['Caisse — encaissement', ['clients.voir', 'lots.voir', 'lots.encaisser', 'transport.voir', 'transport.encaisser', 'caisse.voir']],
        'logisticien' => ['Logistique — Transport', ['clients.voir', 'lots.voir', 'transport.voir', 'transport.gerer']],
        'cdc' => ['CDC — Gestion clients', ['clients.voir', 'clients.gerer', 'lots.voir']],
        'rh' => ['Ressources humaines', ['employes.voir', 'employes.gerer', 'presences.gerer', 'salaires.voir', 'salaires.payer', 'heures_sup.saisir', 'lettres.generer', 'corbeille.rh', 'activite.voir', 'rh.parametres.voir', 'rh.parametres.gerer', 'rh.entites.voir', 'rh.entites.gerer', 'contrats.voir', 'contrats.gerer', 'conges.voir', 'conges.gerer', 'conges.valider', 'caisse.voir', 'archives.rh']],
    ];

    private const CATEGORIES = [
        'recette' => ['Tunnel de congélation', 'Chambre froide', 'Transport', 'Clients spéciaux', 'Autres recettes'],
        'depense' => [
            'Fournitures & consommables', 'Carburant & énergie', 'Maintenance & réparation',
            'Transport & logistique', 'Divers',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $slug => $libelle) {
            Permission::updateOrCreate(['slug' => $slug], ['libelle' => $libelle]);
        }

        foreach (self::ROLES as $slug => [$libelle, $perms]) {
            $role = Role::updateOrCreate(['slug' => $slug], ['libelle' => $libelle]);
            $role->permissions()->syncWithoutDetaching(Permission::whereIn('slug', $perms)->pluck('id'));
        }

        // Le super administrateur cumule toutes les permissions existantes, y compris celles ajoutées plus tard.
        Role::where('slug', 'superadmin')->first()?->permissions()->sync(Permission::pluck('id'));

        // Infrastructures de l'usine (capacités de la maquette) ; le statut existant n'est pas écrasé.
        foreach ([
            ['Tunnel 1', 'tunnel', 1200], ['Tunnel 2', 'tunnel', 1200],
            ['CHF 1', 'chambre', 30000], ['CHF 2', 'chambre', 30000],
            ['CHF 3', 'chambre', 80000], ['CHF 4', 'chambre', 80000], ['CHF 5', 'chambre', 80000],
        ] as [$nom, $type, $capacite]) {
            Infrastructure::firstOrCreate(['nom' => $nom], ['type' => $type, 'capacite_kg' => $capacite]);
        }

        $this->call(ReferenceDataSeeder::class);

        foreach (self::CATEGORIES as $type => $noms) {
            foreach ($noms as $nom) {
                CategorieFinanciere::firstOrCreate(['nom' => $nom, 'type' => $type]);
            }
        }
    }
}

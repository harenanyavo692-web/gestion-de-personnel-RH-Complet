<?php

namespace Database\Seeders;

use App\Models\AccountingJournal;
use App\Models\Employe;
use App\Models\OrganizationEntity;
use App\Models\ReferenceItem;
use App\Models\Vehicule;
use Illuminate\Database\Seeder;

/**
 * Listes de paramétrage de départ (modifiables ensuite dans l'application). Idempotent :
 * un élément existant n'est jamais écrasé. Aucune donnée financière n'est créée ici.
 */
class ReferenceDataSeeder extends Seeder
{
    private const DEPARTEMENTS = [
        'DIR' => 'Direction', 'CHF' => 'Chambre froide', 'REC' => 'Réception & pesée', 'DIS' => 'Distribution',
        'CPT' => 'Comptabilité', 'RH' => 'Ressources Humaines', 'MNT' => 'Maintenance', 'SEC' => 'Sécurité',
    ];

    /** [domaine, groupe, code, libellé, meta] */
    private const ITEMS = [
        ['rh', 'categorie_personnel', 'CADRE', 'Cadre', null],
        ['rh', 'categorie_personnel', 'AGENT', 'Agent de maîtrise', null],
        ['rh', 'categorie_personnel', 'OUVRIER', 'Ouvrier / agent d\'exécution', null],
        ['rh', 'categorie_personnel', 'STAGIAIRE', 'Stagiaire', null],
        ['rh', 'statut_pro', 'TITULAIRE', 'Titulaire', null],
        ['rh', 'statut_pro', 'CONTRACTUEL', 'Contractuel', null],
        ['rh', 'statut_pro', 'JOURNALIER', 'Journalier', null],
        ['rh', 'categorie_poste', 'ADMIN', 'Administratif', null],
        ['rh', 'categorie_poste', 'TECH', 'Technique', null],
        ['rh', 'categorie_poste', 'OPER', 'Opérationnel', null],
        ['rh', 'type_contrat', 'CDI', 'Contrat à durée indéterminée', null],
        ['rh', 'type_contrat', 'CDD', 'Contrat à durée déterminée', null],
        ['rh', 'type_contrat', 'STAGE', 'Convention de stage', null],
        ['rh', 'type_contrat', 'JOURNALIER', 'Journalier / occasionnel', null],
        ['rh', 'type_absence', 'CONGE_ANNUEL', 'Congé annuel', ['jours_annuels' => 30, 'justificatif' => false]],
        ['rh', 'type_absence', 'MALADIE', 'Maladie', ['jours_annuels' => null, 'justificatif' => true]],
        ['rh', 'type_absence', 'MATERNITE', 'Congé de maternité', ['jours_annuels' => null, 'justificatif' => true, 'nature' => 'conge']],
        ['rh', 'type_absence', 'PATERNITE', 'Congé de paternité', ['jours_annuels' => null, 'justificatif' => true, 'nature' => 'conge']],
        ['rh', 'type_absence', 'CONVALESCENCE', 'Congé de convalescence', ['jours_annuels' => null, 'justificatif' => true, 'nature' => 'conge']],
        ['rh', 'type_absence', 'PERMISSION', 'Permission exceptionnelle', ['jours_annuels' => 10, 'justificatif' => false]],
        ['rh', 'type_absence', 'MISSION', 'Mission', ['jours_annuels' => null, 'justificatif' => false]],
        ['compta', 'mode_paiement', 'ESPECES', 'Espèces', null],
        ['compta', 'mode_paiement', 'VIREMENT', 'Virement bancaire', null],
        ['compta', 'mode_paiement', 'CHEQUE', 'Chèque', null],
        ['compta', 'mode_paiement', 'MOBILE', 'Mobile money', null],
        ['compta', 'type_piece', 'FACTURE', 'Facture', null],
        ['compta', 'type_piece', 'RECU', 'Reçu / quittance', null],
        ['compta', 'type_piece', 'BON', 'Bon de commande / de livraison', null],
        ['compta', 'type_piece', 'DEVIS', 'Devis', null],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $i => [$domaine, $groupe, $code, $libelle, $meta]) {
            ReferenceItem::firstOrCreate(['groupe' => $groupe, 'code' => $code], [
                'domaine' => $domaine, 'libelle' => $libelle, 'meta' => $meta, 'ordre' => $i,
            ]);
        }

        foreach ([['CAI', 'Journal de caisse', 'caisse'], ['BQ', 'Journal de banque / Trésor', 'banque'], ['OD', 'Opérations diverses', 'operations_diverses']] as [$code, $lib, $type]) {
            AccountingJournal::firstOrCreate(['code' => $code], ['libelle' => $lib, 'type' => $type]);
        }

        // Les 8 départements de l'ancien ENUM deviennent des entités ; les employés y sont rattachés.
        foreach (self::DEPARTEMENTS as $code => $nom) {
            $entite = OrganizationEntity::firstOrCreate(['code' => $code], ['nom' => $nom, 'type' => 'departement']);
            Employe::whereNull('entity_id')->where('departement', $nom)->update(['entity_id' => $entite->id]);
        }

        foreach ([['Camion 4 Tonnes Frigo', 'poids_lourd'], ['Pick-up Isotherme P2', 'vehicule_leger'], ['Bateau Collecteur Quai', 'maritime']] as [$nom, $type]) {
            Vehicule::firstOrCreate(['nom' => $nom], ['type' => $type]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Parametre;

/**
 * Paramètres métier repris de la maquette (tarifs, taux, en-tête officielle).
 * Une valeur absente de la base retombe sur la valeur par défaut ci-dessous.
 */
class ParametreService
{
    public const DEFAUTS = [
        // Ar par kg (tunnel : par kg et par jour de 24 h ; stockage : par kg et par jour)
        'tarifs.tunnel' => 450,
        'tarifs.stockage_chambre' => 25,
        'tarifs.stockage_societe' => 15,
        'tarifs.stockage_crevettes' => 50,
        // Ar par kg
        'tarifs.transport_interurbain' => [
            'Mahajanga - Tananarivo' => ['avecGO' => 400, 'sansGO' => 179.50],
            'Mahajanga - Toamasina' => ['avecGO' => 700, 'sansGO' => 344.75],
            'Antananarivo - Toamasina' => ['avecGO' => 375.01, 'sansGO' => 187.51],
            'Antananarivo - Morondava' => ['avecGO' => 637.51, 'sansGO' => 318.75],
            'Mahajanga - Antsiranana' => ['avecGO' => 738, 'sansGO' => 369],
            'Mahajanga - Fianarantsoa' => ['avecGO' => 789.01, 'sansGO' => 394.51],
            'Mahajanga - Antsirabe' => ['avecGO' => 607.50, 'sansGO' => 303.75],
            'M/ga - Port-Bergé - Tana' => ['avecGO' => 577.51, 'sansGO' => 288.76],
            'Tana - Mananjary - Mahajanga' => ['avecGO' => 957.01, 'sansGO' => 478.50],
            'Mahajanga - Moramanga' => ['avecGO' => 528.01, 'sansGO' => 264.01],
            'Local Mahajanga' => ['avecGO' => 0, 'sansGO' => 0],
        ],
        'tarifs.exception_4t_tana' => 350, // Mahajanga - Tananarivo en camion 4 tonnes
        // Ar par tonne
        'tarifs.transport_urbain' => [
            '2km' => ['camion5T' => 116756.64, 'camion8T' => 192367.82],
            '2-10km' => ['camion5T' => 157172.40, 'camion8T' => 210872.97],
            '10-30km' => ['camion5T' => 442328.04, 'camion8T' => 669198.92],
            '30-100km' => ['camion5T' => 1077516.00, 'camion8T' => 1334094.30],
            '100-150km' => ['camion5T' => 1453171.10, 'camion8T' => 1999420.04],
        ],
        // Ar par jour
        'tarifs.location' => ['5T' => 1318615.20, '8T' => 2098693.80],
        'tarifs.eau' => ['poissons_crevettes' => 50, 'crabes_traites' => 100, 'crabes_non_traites' => 200],
        'rh.general' => [
            'organisation' => 'Centre de Distribution des Produits Halieutiques de Mahajanga (CDPHM)',
            'adresse' => 'Quai Barriquand — BP : 1222 Mahajanga',
            'telephone' => '+ (261) 20 62 224 79',
            'email' => 'cdphm@moov.mg',
            'annee_reference' => null, // null = année en cours
            'lignes_par_page' => 10,
        ],
        'rh.workflow' => [
            'validation_requise' => true,        // une demande d'absence doit être validée
            'validateur' => 'responsable',       // responsable | rh | direction
            'delai_reponse_jours' => 3,
            'solde_negatif_autorise' => false,
        ],
        'compta.general' => [
            'devise_code' => 'MGA',
            'devise_symbole' => 'Ar',
            'decimales' => 0,
            'prefixes' => ['REC' => 'REC', 'OD' => 'OD', 'REG' => 'REG', 'CS' => 'CS', 'LOT' => 'LOT', 'SP' => 'SP', 'TR' => 'TR'],
        ],
        'compta.securite' => [
            'seuil_double_validation' => 0,      // Ar ; 0 = désactivé. Au-delà, validation par la direction.
            'bloquer_exercice_cloture' => true,  // refuse toute opération datée dans un exercice clôturé
            'alerte_budget_pct' => 80,
        ],
        'majoration.taux_retard' => 50, // % appliqué aux heures au-delà de 24 h (faute client)
        'entete' => [
            'ministere' => "Ministère de la Pêche et de l'Économie Bleue",
            'organisme' => "Secrétariat Général / Direction Générale de la Pêche et de l'Économie Bleue — Direction Régionale de la Pêche et de l'Économie Bleue",
            'etablissement' => 'Centre de Distribution des Produits Halieutiques de Mahajanga (CDPHM)',
            'nif' => '4000156744',
            'stat' => '63122412003000170',
            'tel' => '+ (261) 20 62 224 79',
            'email' => 'cdphm@moov.mg',
            'adresse' => 'Quai Barriquand — BP : 1222 Mahajanga',
        ],
    ];

    private array $cache = [];

    public function get(string $cle): mixed
    {
        if (! array_key_exists($cle, $this->cache)) {
            $ligne = Parametre::find($cle);
            $defaut = self::DEFAUTS[$cle] ?? null;
            $this->cache[$cle] = $ligne
                ? (is_array($defaut) && is_array($ligne->valeur) ? array_replace_recursive($defaut, $ligne->valeur) : $ligne->valeur)
                : $defaut;
        }

        return $this->cache[$cle];
    }

    public function set(string $cle, mixed $valeur): void
    {
        abort_unless(array_key_exists($cle, self::DEFAUTS), 422);
        $avant = $this->get($cle);
        Parametre::updateOrCreate(['cle' => $cle], ['valeur' => $valeur, 'modifie_par' => auth()->id()]);
        unset($this->cache[$cle]);
        AuditLogger::log('parametre.modifie', null, ['cle' => $cle, 'avant' => $avant, 'apres' => $valeur]);
    }

    /** Tous les paramètres, prêts pour l'affichage et le calculateur. */
    public function tous(): array
    {
        return collect(array_keys(self::DEFAUTS))->mapWithKeys(fn ($k) => [$k => $this->get($k)])->all();
    }

    /** Tarif de stockage par jour et par kg selon l'espèce (règle de la maquette). */
    public function tarifStockage(string $espece): float
    {
        return (float) (preg_match('/Crevettes|Langoustes/i', $espece)
            ? $this->get('tarifs.stockage_crevettes')
            : $this->get('tarifs.stockage_chambre'));
    }
}

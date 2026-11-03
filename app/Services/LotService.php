<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Infrastructure;
use App\Models\Lot;
use App\Models\Recette;
use App\Models\RetardTunnel;
use App\Models\SortiePartielle;
use App\Models\User;
use App\Models\CategorieFinanciere;
use App\Support\Fmt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cycle de vie d'un lot (maquette) : entrée tunnel → transfert chambre froide → encaissements → sorties.
 * Chaque encaissement crée une recette : c'est ce qui alimente la comptabilité, la régie et les créances.
 */
class LotService
{
    public function __construct(private ParametreService $params) {}

    private function erreur(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }

    private function verrouiller(Lot $lot): Lot
    {
        return Lot::whereKey($lot->id)->lockForUpdate()->firstOrFail();
    }

    private function verifierCapacite(Infrastructure $infra, float $poids): void
    {
        if ($infra->statut !== 'actif') {
            $this->erreur('infrastructure_id', "Infrastructure indisponible : {$infra->nom} est en {$infra->statut}.");
        }
        $charge = $infra->occupation;
        if ($charge + $poids > $infra->capacite_kg) {
            $this->erreur('poids_entree', "Capacité insuffisante dans {$infra->nom} : " . number_format($charge, 0, ',', ' ') . ' kg occupés sur ' . number_format($infra->capacite_kg, 0, ',', ' ') . ' kg.');
        }
    }

    public function entree(array $d, User $user): Lot
    {
        return DB::transaction(function () use ($d, $user) {
            $infra = Infrastructure::whereKey($d['infrastructure_id'])->lockForUpdate()->firstOrFail();
            $client = Client::findOrFail($d['client_id']);
            $poids = (float) $d['poids_entree'];
            $this->verifierCapacite($infra, $poids);

            $direct = $infra->type === 'chambre'; // entrée directe : pas de passage au tunnel
            $negocie = $client->special && ! empty($d['tarif_negocie']) ? (float) $d['tarif_negocie'] : null;
            if ($direct && empty($d['motif_entree_directe'])) {
                $this->erreur('motif_entree_directe', "Précisez le motif de l'entrée directe en chambre.");
            }

            $lot = Lot::create([
                'reference' => Fmt::reference('LOT'), 'client_id' => $client->id, 'infrastructure_id' => $infra->id,
                'type_infrastructure' => $infra->type, 'espece' => $d['espece'],
                'nb_filets' => $d['nb_filets'] ?? 0, 'nb_poissons' => $d['nb_poissons'] ?? 0, 'nb_bacs' => $d['nb_bacs'] ?? 0,
                'poids_entree' => $poids, 'stock_restant' => $poids, 'dockers' => $d['dockers'] ?? null,
                'date_entree' => now(), 'date_entree_chambre' => $direct ? now() : null,
                'tarif_negocie' => $negocie, 'tarif_chambre' => $this->params->tarifStockage($d['espece']),
                'jours_prevus' => $direct ? 1 : max(((float) ($d['heures_prevues'] ?? 24)) / 24, 1 / 24),
                'duree_indeterminee' => $direct, 'paye_tunnel' => $direct,
                'motif_entree_directe' => $direct ? $d['motif_entree_directe'] : null,
                'cree_par' => $user->id,
            ]);
            AuditLogger::log('lot.entree', $lot, ['reference' => $lot->reference, 'client' => $client->nom, 'poids' => $poids, 'infrastructure' => $infra->nom]);

            return $lot;
        });
    }

    /** Transfert tunnel → chambre : contre-pesée, responsabilité d'un éventuel dépassement de 24 h, montant tunnel. */
    public function transferer(Lot $lot, array $d, User $user): Lot
    {
        return DB::transaction(function () use ($lot, $d, $user) {
            $lot = $this->verrouiller($lot);
            if ($lot->statut !== 'en_stock' || $lot->type_infrastructure !== 'tunnel') {
                $this->erreur('lot', 'Seul un lot en stock dans un tunnel peut être transféré.');
            }
            $chambre = Infrastructure::whereKey($d['infrastructure_id'])->lockForUpdate()->firstOrFail();
            if ($chambre->type !== 'chambre') {
                $this->erreur('infrastructure_id', 'La destination doit être une chambre froide.');
            }
            $poidsSortie = (float) $d['poids_contre_pesee'];
            if ($poidsSortie > (float) $lot->poids_entree * 1.1) {
                $this->erreur('poids_contre_pesee', "La contre-pesée dépasse de plus de 10 % le poids d'entrée.");
            }
            $this->verifierCapacite($chambre, $poidsSortie);

            $heures = $lot->heures_depuis_entree;
            $resp = $d['responsabilite'] ?? null;
            if ($heures > 24 && ! $resp) {
                $this->erreur('responsabilite', 'Choisissez la responsabilité du dépassement des 24 h.');
            }

            $tarif = (float) $this->params->get('tarifs.tunnel');
            $taux = (float) $this->params->get('majoration.taux_retard');
            $poids = (float) $lot->poids_entree;
            $penalite = 0;
            $montant = round($poids * max($heures / 24, 1) * $tarif);
            if ($heures > 24 && $resp === 'client') {
                $penalite = round($poids * (($heures - 24) / 24) * $tarif * (1 + $taux / 100));
                $montant = round($poids * $tarif) + $penalite;
            }

            $lot->update([
                'infrastructure_id' => $chambre->id, 'type_infrastructure' => 'chambre', 'stock_restant' => $poidsSortie,
                'date_entree_chambre' => now(), 'heures_tunnel' => $heures, 'responsabilite_depassement' => $heures > 24 ? $resp : null,
                'montant_tunnel' => $montant, 'penalite_tunnel' => $penalite,
                'duree_indeterminee' => ! empty($d['duree_indeterminee']),
                'jours_prevus' => max((float) ($d['jours_prevus'] ?? 1), 1),
            ]);

            if ($heures > 24) {
                RetardTunnel::create([
                    'lot' => $lot->reference, 'client' => $lot->client->nom, 'poids_kg' => $poids, 'heures_tunnel' => $heures,
                    'responsabilite' => $resp, 'taux_applique' => $taux, 'montant_tunnel' => $montant, 'penalite' => $penalite,
                    'saisi_par' => $user->id,
                ]);
            }
            AuditLogger::log('lot.transfere', $lot, ['chambre' => $chambre->nom, 'heures_tunnel' => $heures, 'responsabilite' => $resp, 'montant_tunnel' => $montant]);

            return $lot;
        });
    }

    private function enregistrerRecette(Lot $lot, string $categorie, float $montant, string $origine, User $user): Recette
    {
        app(ExerciceService::class)->verifierOuvert(today(), 'lot');

        return Recette::create([
            'reference' => Fmt::reference('REC'), 'numero_bon' => $lot->reference, 'date_recette' => today(),
            'montant' => $montant, 'montant_facture' => $montant,
            'categorie_id' => CategorieFinanciere::firstOrCreate(['nom' => $categorie, 'type' => 'recette'])->id,
            'origine' => $origine, 'client' => $lot->client->nom, 'espece' => $lot->espece,
            'poids_kg' => $lot->poids_entree, 'saisi_par' => $user->id,
        ]);
    }

    public function encaisserTunnel(Lot $lot, User $user): Recette
    {
        return DB::transaction(function () use ($lot, $user) {
            $lot = $this->verrouiller($lot);
            if ($lot->paye_tunnel) {
                $this->erreur('lot', 'Le tunnel de ce lot est déjà encaissé.');
            }
            $montant = (float) ($lot->montant_tunnel
                ?: round((float) $lot->poids_entree * max($lot->heures_depuis_entree / 24, 1) * (float) $this->params->get('tarifs.tunnel')));
            if ($montant <= 0) {
                $this->erreur('lot', 'Montant tunnel invalide.');
            }
            $recette = $this->enregistrerRecette($lot, 'Tunnel de congélation', $montant, 'Paiement prestation tunnel', $user);
            $lot->update(['paye_tunnel' => true, 'montant_tunnel' => $montant]);
            AuditLogger::log('lot.tunnel_encaisse', $lot, ['montant' => $montant]);

            return $recette;
        });
    }

    public function encaisserFroid(Lot $lot, User $user): Recette
    {
        return DB::transaction(function () use ($lot, $user) {
            $lot = $this->verrouiller($lot);
            if ($lot->type_infrastructure !== 'chambre') {
                $this->erreur('lot', "Le lot n'est pas encore en chambre froide.");
            }
            if ($lot->paye_froid) {
                $this->erreur('lot', 'La chambre froide de ce lot est déjà encaissée.');
            }
            $montant = $lot->montantFroidEstime();
            if ($montant <= 0) {
                $this->erreur('lot', 'Montant chambre froide invalide.');
            }
            $recette = $this->enregistrerRecette($lot, 'Chambre froide', $montant, 'Paiement stockage chambre froide', $user);
            $lot->update(['paye_froid' => true, 'montant_froid_encaisse' => $montant]);
            AuditLogger::log('lot.froid_encaisse', $lot, ['montant' => $montant]);

            return $recette;
        });
    }

    public function sortiePartielle(Lot $lot, float $poids, User $user): SortiePartielle
    {
        return DB::transaction(function () use ($lot, $poids, $user) {
            $lot = $this->verrouiller($lot);
            if ($lot->statut !== 'en_stock' || $lot->type_infrastructure !== 'chambre') {
                $this->erreur('poids', 'Retrait possible uniquement depuis une chambre froide.');
            }
            if ($poids <= 0 || $poids > (float) $lot->stock_restant) {
                $this->erreur('poids', 'Le poids retiré doit être positif et ne pas dépasser le stock restant.');
            }
            $jours = round($lot->jours_chambre, 2);
            $tarif = $lot->tarif_stockage;
            $montant = round($poids * $jours * $tarif);

            $reste = round((float) $lot->stock_restant - $poids, 2);
            $recette = $this->enregistrerRecette($lot, 'Chambre froide', $montant, 'Retrait partiel de marchandise', $user);
            $recette->update(['poids_kg' => $poids]);
            $sortie = SortiePartielle::create([
                'reference' => Fmt::reference('SP'), 'lot_id' => $lot->id, 'poids' => $poids, 'jours' => $jours,
                'tarif' => $tarif, 'montant' => $montant, 'recette_id' => $recette->id, 'cree_par' => $user->id,
            ]);
            $lot->update($reste <= 0
                ? ['stock_restant' => 0, 'statut' => 'sorti', 'paye_froid' => true]
                : ['stock_restant' => $reste]);
            AuditLogger::log('lot.sortie_partielle', $lot, ['poids' => $poids, 'montant' => $montant, 'reste' => $reste]);

            return $sortie;
        });
    }

    /** Libération physique : seulement si la chambre froide est encaissée. */
    public function liberer(Lot $lot): Lot
    {
        return DB::transaction(function () use ($lot) {
            $lot = $this->verrouiller($lot);
            if ($lot->statut === 'sorti') {
                $this->erreur('lot', 'Lot déjà sorti.');
            }
            if (! $lot->paye_froid) {
                $this->erreur('lot', "Impossible : le paiement chambre froide n'est pas encore validé en caisse.");
            }
            $lot->update(['statut' => 'sorti']);
            AuditLogger::log('lot.libere', $lot, ['reference' => $lot->reference]);

            return $lot;
        });
    }
}

<?php

namespace App\Services;

use App\Models\CategorieFinanciere;
use App\Models\Lot;
use App\Models\MissionTransport;
use App\Models\Recette;
use App\Models\User;
use App\Models\Vehicule;
use App\Support\Fmt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Missions de transport : calcul du coût (barèmes paramétrés), encaissement, retour de véhicule. */
class TransportService
{
    public function __construct(private ParametreService $params, private ExerciceService $exercices) {}

    /** Coût standard selon les barèmes de la maquette. */
    public function cout(string $prestation, Vehicule $vehicule, float $poidsKg, ?string $liaison, ?string $zone, ?string $carburant): float
    {
        $lourd8 = (bool) preg_match('/8\s*(Tonnes|T)\b/i', $vehicule->nom);

        return round(match ($prestation) {
            'interurbain' => $this->interurbain($vehicule, $poidsKg, $liaison, $carburant),
            'urbain' => ($this->params->get('tarifs.transport_urbain')[$zone][$lourd8 ? 'camion8T' : 'camion5T'] ?? 0) * ($poidsKg / 1000),
            'location' => $this->params->get('tarifs.location')[$lourd8 ? '8T' : '5T'] * ($poidsKg / 1000 ?: 1),
            'exception' => $poidsKg * (float) $this->params->get('tarifs.exception_4t_tana'),
        });
    }

    private function interurbain(Vehicule $v, float $poids, ?string $liaison, ?string $carburant): float
    {
        $tarif = $this->params->get('tarifs.transport_interurbain')[$liaison] ?? null;
        if (! $tarif) {
            return 0;
        }
        if ($liaison === 'Mahajanga - Tananarivo' && stripos($v->nom, '4 Tonnes') !== false) {
            return $poids * (float) $this->params->get('tarifs.exception_4t_tana'); // exception camion 4 T
        }

        return $poids * $tarif[$carburant === 'sansGO' ? 'sansGO' : 'avecGO'];
    }

    public function creer(array $d, User $user): MissionTransport
    {
        return DB::transaction(function () use ($d, $user) {
            $vehicule = Vehicule::whereKey($d['vehicule_id'])->lockForUpdate()->firstOrFail();
            if ($vehicule->statut !== 'disponible') {
                throw ValidationException::withMessages(['vehicule_id' => "Véhicule indisponible : {$vehicule->nom} est « {$vehicule->statut} »."]);
            }
            $lot = ! empty($d['lot_id']) ? Lot::find($d['lot_id']) : null;
            $standard = $this->cout($d['prestation'], $vehicule, (float) $d['poids_kg'], $d['destination'] ?? null, $d['zone_urbaine'] ?? null, $d['option_carburant'] ?? null);
            if ($standard <= 0 && empty($d['cout_negocie'])) {
                throw ValidationException::withMessages(['prestation' => 'Aucun tarif applicable : vérifiez la liaison / la zone, ou saisissez un tarif négocié.']);
            }
            $negocie = ! empty($d['cout_negocie']) && (float) $d['cout_negocie'] !== $standard;

            $mission = MissionTransport::create([
                'reference' => Fmt::reference('TR'), 'vehicule_id' => $vehicule->id, 'lot_id' => $lot?->id,
                'prestation' => $d['prestation'], 'destination' => $d['destination'] ?? null, 'zone_urbaine' => $d['zone_urbaine'] ?? null,
                'option_carburant' => $d['option_carburant'] ?? null, 'lieu_depart' => $d['lieu_depart'] ?? null,
                'lieu_destination' => $d['lieu_destination'] ?? null, 'espece' => $d['espece'] ?? $lot?->espece,
                'poids_kg' => $d['poids_kg'], 'chauffeur' => $d['chauffeur'], 'chauffeur_tel' => $d['chauffeur_tel'] ?? null,
                'chauffeur_cin' => $d['chauffeur_cin'] ?? null, 'destinataire_nom' => $d['destinataire_nom'] ?? null,
                'destinataire_tel' => $d['destinataire_tel'] ?? null, 'agrement' => $d['agrement'] ?? null,
                'cout_standard' => $standard, 'cout' => $negocie ? $d['cout_negocie'] : $standard, 'negocie' => $negocie,
                'cree_par' => $user->id,
            ]);
            AuditLogger::log('transport.mission_creee', $mission, ['reference' => $mission->reference, 'cout' => (string) $mission->cout, 'negocie' => $negocie]);

            return $mission;
        });
    }

    public function encaisser(MissionTransport $mission, User $user): MissionTransport
    {
        return DB::transaction(function () use ($mission, $user) {
            $m = MissionTransport::whereKey($mission->id)->lockForUpdate()->with(['lot.client', 'vehicule'])->firstOrFail();
            if ($m->statut !== 'attente_caisse') {
                throw ValidationException::withMessages(['mission' => 'Cette mission est déjà traitée.']);
            }
            $this->exercices->verifierOuvert(today(), 'mission');

            $recette = Recette::create([
                'reference' => Fmt::reference('REC'), 'numero_bon' => $m->reference, 'date_recette' => today(),
                'montant' => $m->cout, 'montant_facture' => $m->cout,
                'categorie_id' => CategorieFinanciere::firstOrCreate(['nom' => 'Transport', 'type' => 'recette'])->id,
                'origine' => 'Encaissement prestation transport', 'client' => $m->lot?->client?->nom ?? $m->chauffeur,
                'espece' => $m->espece, 'poids_kg' => $m->poids_kg, 'destination' => $m->destination ?? $m->lieu_destination,
                'saisi_par' => $user->id,
            ]);
            $m->update(['statut' => 'valide', 'valide_le' => now(), 'recette_id' => $recette->id]);
            $m->vehicule->update(['statut' => 'en_transit']);
            AuditLogger::log('transport.encaisse', $m, ['reference' => $m->reference, 'montant' => (string) $m->cout]);

            return $m;
        });
    }

    public function annuler(MissionTransport $mission): MissionTransport
    {
        if ($mission->statut !== 'attente_caisse') {
            throw ValidationException::withMessages(['mission' => 'Seule une mission en attente de caisse peut être annulée.']);
        }
        $mission->update(['statut' => 'annule']);
        AuditLogger::log('transport.annulee', $mission, ['reference' => $mission->reference]);

        return $mission;
    }
}

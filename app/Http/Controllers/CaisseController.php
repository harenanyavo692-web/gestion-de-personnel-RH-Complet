<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CompteSpecial;
use App\Models\Lot;
use App\Models\MissionTransport;
use App\Models\VersementSpecial;
use App\Services\ParametreService;
use App\Services\RegieService;
use Illuminate\Http\Request;

/** Caisse centrale : tout ce qui attend d'être encaissé, le journal du jour, les clients spéciaux et la vigilance client. */
class CaisseController extends Controller
{
    public const SECTIONS = ['encaisser', 'journal', 'speciaux', 'fiche'];

    private function date(Request $request): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->date) && strtotime($request->date) ? $request->date : today()->toDateString();
    }

    public function index(Request $request, RegieService $regie, ParametreService $params)
    {
        $date = $this->date($request);
        $tarifTunnel = (float) $params->get('tarifs.tunnel');
        $enStock = Lot::with(['client', 'infrastructure'])->where('statut', 'en_stock')->orderBy('date_entree')->get();

        // Une seule liste « à encaisser » : tunnel, chambre froide et transport, du plus ancien au plus récent.
        $afaire = collect();
        foreach ($enStock->where('paye_tunnel', false) as $l) {
            // Même calcul que LotService::encaisserTunnel : montant figé au transfert, sinon estimation au temps écoulé.
            $montant = (float) ($l->montant_tunnel ?: round((float) $l->poids_entree * max($l->heures_depuis_entree / 24, 1) * $tarifTunnel));
            $afaire->push([
                'type' => 'tunnel', 'date' => $l->date_entree, 'ref' => $l->reference, 'client' => $l->client->nom, 'client_id' => $l->client_id, 'special' => $l->client->special,
                'detail' => number_format($l->poids_entree, 0, ',', ' ') . ' kg · ' . $l->espece . ' · ' . ($l->heures_tunnel ?? $l->heures_depuis_entree) . ' h au tunnel'
                    . ((float) $l->penalite_tunnel ? ' · pénalité incluse' : ''),
                'montant' => $montant, 'url' => route('exploitation.lots.tunnel', $l), 'perm' => 'lots.encaisser',
                'confirm' => "Valider le paiement tunnel du lot {$l->reference} ?",
            ]);
        }
        foreach ($enStock->where('type_infrastructure', 'chambre')->where('paye_froid', false) as $l) {
            $jours = $l->duree_indeterminee ? $l->jours_chambre : max((float) $l->jours_prevus, 1);
            $afaire->push([
                'type' => 'froid', 'date' => $l->date_entree_chambre ?? $l->date_entree, 'ref' => $l->reference, 'client' => $l->client->nom, 'client_id' => $l->client_id, 'special' => $l->client->special,
                'detail' => number_format($l->stock_restant, 0, ',', ' ') . ' kg en ' . $l->infrastructure->nom . ' · ' . rtrim(rtrim(number_format($jours, 1, ',', ''), '0'), ',') . ' j',
                'montant' => $l->montantFroidEstime(), 'url' => route('exploitation.lots.froid', $l), 'perm' => 'lots.encaisser',
                'confirm' => "Valider le paiement chambre froide du lot {$l->reference} ?",
            ]);
        }
        foreach (MissionTransport::with(['vehicule', 'lot.client'])->where('statut', 'attente_caisse')->orderBy('id')->get() as $m) {
            $afaire->push([
                'type' => 'transport', 'date' => $m->created_at, 'ref' => $m->reference, 'client' => $m->lot?->client?->nom ?? $m->chauffeur, 'client_id' => $m->lot?->client_id, 'special' => (bool) $m->lot?->client?->special,
                'detail' => ucfirst($m->prestation) . ' · ' . ($m->destination ?? $m->lieu_destination ?? $m->zone_urbaine ?? '—') . ' · ' . number_format($m->poids_kg, 0, ',', ' ') . ' kg' . ($m->negocie ? ' · tarif négocié' : ''),
                'montant' => (float) $m->cout, 'url' => route('exploitation.transport.encaisser', $m), 'perm' => 'transport.encaisser',
                'confirm' => "Encaisser la mission {$m->reference} et libérer le véhicule ?",
            ]);
        }
        $afaire = $afaire->sortBy('date')->values();
        if ($q = trim((string) $request->q)) {
            $afaire = $afaire->filter(fn ($a) => stripos($a['client'] . ' ' . $a['ref'], $q) !== false)->values();
        }

        $lignes = $regie->lignes($date);
        $section = in_array($request->section, self::SECTIONS, true) ? $request->section : ($request->client_id ? 'fiche' : 'encaisser');
        $comptes = CompteSpecial::withSum('versements', 'montant')->orderByDesc('id')->limit(30)->get();

        return view('caisse.index', [
            'section' => $section, 'date' => $date, 'q' => $q ?? '',
            'afaire' => $afaire, 'totalAfaire' => (float) $afaire->sum('montant'),
            'lignes' => $lignes, 'totalJour' => (float) $lignes->sum('montant'),
            'vigilance' => $this->vigilance($request->client_id, $enStock),
            'clients' => Client::where('actif', true)->orderBy('nom')->get(['id', 'nom']),
            'speciaux' => Client::where('special', true)->where('actif', true)->orderBy('nom')->pluck('nom'),
            'comptes' => $comptes,
            'speciauxDu' => (float) CompteSpecial::sum('montant_convenu') - (float) VersementSpecial::sum('montant'),
            'speciauxPaye' => (float) VersementSpecial::sum('montant'),
        ]);
    }

    /** Fiche de vigilance : créance, lots non soldés et dossiers négociés du client choisi. */
    private function vigilance(mixed $clientId, $enStock): ?array
    {
        $client = $clientId ? Client::find($clientId) : null;
        if (! $client) {
            return null;
        }
        $lots = $enStock->where('client_id', $client->id)->filter(fn (Lot $l) => $l->solde_estime > 0)->values();
        $comptes = CompteSpecial::withSum('versements', 'montant')->where('client', $client->nom)->get();

        return [
            'client' => $client,
            'lots' => $lots,
            'creance' => (float) $lots->sum->solde_estime,
            'speciauxReste' => (float) $comptes->sum->reste,
            'derniere' => Lot::where('client_id', $client->id)->max('date_entree'),
        ];
    }

    public function export(Request $request, RegieService $regie)
    {
        return $regie->exporter($this->date($request));
    }
}

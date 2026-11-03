<?php

namespace App\Services;

use App\Models\DossierRegie;
use App\Models\Recette;
use App\Models\User;
use App\Support\Fmt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Circuit de la régie : clôture du journal → versement au Trésor → reçu → transmission → ordre de versement.
 * Chaque étape n'est possible que depuis l'étape précédente (verrou + contrôle du statut).
 */
class RegieService
{
    private const CATEGORIES = ['tunnel' => 'Tunnel de congélation', 'froid' => 'Chambre froide', 'transport' => 'Transport'];

    /** @return array{tunnel: float, froid: float, transport: float, total: float} */
    public function journal(?string $date = null): array
    {
        $date ??= today()->toDateString();
        $par = Recette::with('categorie')->whereDate('date_recette', $date)->get()
            ->groupBy(fn ($r) => $r->categorie->nom)->map->sum('montant');

        $j = [];
        foreach (self::CATEGORIES as $cle => $nom) {
            $j[$cle] = (float) ($par[$nom] ?? 0);
        }
        $j['total'] = array_sum($j);

        return $j;
    }

    /** Toutes les opérations encaissées un jour donné (tunnel, chambre, transport, clients spéciaux…), dans l'ordre chronologique. */
    public function lignes(?string $date = null)
    {
        return Recette::with('categorie')->whereDate('date_recette', $date ?? today()->toDateString())->orderBy('id')->get();
    }

    /** Export CSV (« ; », BOM UTF-8) de la feuille de caisse d'un jour. */
    public function exporter(string $date): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $lignes = $this->lignes($date);
        AuditLogger::log('journal.exporte', null, ['date' => $date, 'lignes' => $lignes->count()]);

        return response()->streamDownload(function () use ($lignes) {
            $o = fopen('php://output', 'w');
            fwrite($o, chr(0xEF).chr(0xBB).chr(0xBF));
            $ligne = fn (array $l) => fputcsv($o, array_map([Fmt::class, 'csv'], $l), ';');
            $ligne(['N°', 'Heure', 'Type', 'Référence', 'Client', 'Montant (Ar)']);
            foreach ($lignes as $i => $r) {
                $ligne([$i + 1, $r->created_at?->format('H:i'), $r->categorie->nom, $r->numero_bon ?: $r->reference, $r->client, $r->montant]);
            }
            $ligne(['', '', '', '', 'TOTAL', $lignes->sum('montant')]);
            fclose($o);
        }, "journal_caisse_{$date}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function cloturerJournal(User $user): DossierRegie
    {
        return DB::transaction(function () use ($user) {
            if (DossierRegie::whereDate('date_journal', today())->exists()) {
                throw ValidationException::withMessages(['regie' => "La journée d'aujourd'hui a déjà été clôturée (une seule quittance par jour)."]);
            }
            $j = $this->journal();
            if ($j['total'] <= 0) {
                throw ValidationException::withMessages(['regie' => "Aucun encaissement enregistré aujourd'hui — rien à clôturer."]);
            }

            $dossier = DossierRegie::create([
                'reference' => Fmt::reference('REG'), 'date_journal' => today(),
                'tunnel' => $j['tunnel'], 'froid' => $j['froid'], 'transport' => $j['transport'], 'montant' => $j['total'],
                'statut' => 'quittance_emise', 'cree_par' => $user->id,
            ]);
            AuditLogger::log('regie.journal_cloture', $dossier, ['montant' => (string) $dossier->montant]);

            return $dossier;
        });
    }

    /** Fait passer le dossier de $de à $vers en appliquant $champs. */
    public function avancer(DossierRegie $dossier, string $de, string $vers, array $champs, string $action): DossierRegie
    {
        return DB::transaction(function () use ($dossier, $de, $vers, $champs, $action) {
            $d = DossierRegie::whereKey($dossier->id)->lockForUpdate()->firstOrFail();
            if ($d->statut !== $de) {
                throw ValidationException::withMessages(['regie' => "Étape impossible : le dossier est « {$d->statut_libelle} »."]);
            }
            $d->update(['statut' => $vers, ...$champs]);
            AuditLogger::log($action, $d, ['reference' => $d->reference, 'montant' => (string) $d->montant]);

            return $d;
        });
    }
}

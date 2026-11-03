<?php

namespace App\Http\Controllers\Comptabilite;

use App\Http\Controllers\Controller;
use App\Models\CategorieFinanciere;
use App\Models\Client;
use App\Models\CompteSpecial;
use Illuminate\Validation\Rule;
use App\Models\Recette;
use App\Models\VersementSpecial;
use App\Services\AuditLogger;
use App\Services\ExerciceService;
use App\Support\Fmt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Clients spéciaux : tarifs négociés. Chaque versement alimente la recette générale. */
class CompteSpecialController extends Controller
{
    /** La comptabilité et la caisse (qui encaisse) ouvrent les dossiers et reçoivent les versements. */
    private function autoriser(Request $request): void
    {
        abort_unless($request->user()->canAny(['recettes.gerer', 'lots.encaisser']), 403);
    }

    public function index()
    {
        $comptes = CompteSpecial::withSum('versements', 'montant')->orderByDesc('id')->paginate(15);

        return view('comptabilite.comptes-speciaux', [
            'comptes' => $comptes,
            'clients' => Client::where('special', true)->where('actif', true)->orderBy('nom')->pluck('nom'),
            'du' => (float) CompteSpecial::sum('montant_convenu') - (float) VersementSpecial::sum('montant'),
            'paye' => (float) VersementSpecial::sum('montant'),
        ]);
    }

    public function store(Request $request)
    {
        $this->autoriser($request);
        $data = $request->validate([
            'client' => ['required', Rule::exists('clients', 'nom')->where('special', true)],
            'motif' => ['required', 'string', 'max:200'],
            'montant_convenu' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
        ]);

        $compte = CompteSpecial::create([...$data, 'reference' => Fmt::reference('CS'), 'cree_par' => $request->user()->id]);
        AuditLogger::log('compte_special.ouvert', $compte, ['client' => $compte->client, 'montant' => (string) $compte->montant_convenu]);

        return back()->with('success', 'Dossier ouvert.');
    }

    public function versement(Request $request, CompteSpecial $compte)
    {
        $this->autoriser($request);
        $data = $request->validate(['montant' => ['required', 'numeric', 'gt:0', 'max:999999999999']]);

        DB::transaction(function () use ($compte, $data, $request) {
            $compte = CompteSpecial::whereKey($compte->id)->lockForUpdate()->firstOrFail();
            app(ExerciceService::class)->verifierOuvert(today(), 'montant');
            $reste = (float) $compte->montant_convenu - (float) $compte->versements()->sum('montant');

            if ($data['montant'] > $reste + 0.005) {
                throw ValidationException::withMessages(['montant' => 'Le versement dépasse le solde restant (' . Fmt::ar($reste) . ').']);
            }

            $recette = Recette::create([
                'reference' => Fmt::reference('REC'),
                'date_recette' => today(),
                'montant' => $data['montant'],
                'categorie_id' => CategorieFinanciere::firstOrCreate(['nom' => 'Clients spéciaux', 'type' => 'recette'])->id,
                'origine' => 'Compte spécial ' . $compte->reference,
                'client' => $compte->client,
                'saisi_par' => $request->user()->id,
            ]);
            $compte->versements()->create(['recette_id' => $recette->id, 'montant' => $data['montant'], 'date_versement' => today()]);
            AuditLogger::log('compte_special.versement', $compte, ['montant' => (string) $data['montant']]);
        });

        return back()->with('success', 'Versement enregistré et ajouté aux recettes.');
    }
}

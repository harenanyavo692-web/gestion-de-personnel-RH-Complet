<?php

namespace App\Services;

use App\Models\AccountingExercise;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** Règles liées aux exercices comptables. */
class ExerciceService
{
    public function __construct(private ParametreService $params) {}

    /**
     * Refuse une opération datée dans un exercice clôturé (si l'option est active).
     * Sans exercice défini pour cette date, l'opération reste libre.
     */
    public function verifierOuvert(CarbonInterface|string $date, string $champ = 'date'): void
    {
        if (! $this->params->get('compta.securite')['bloquer_exercice_cloture']) {
            return;
        }
        $exercice = AccountingExercise::whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->first();

        if ($exercice && $exercice->statut === 'cloture') {
            throw ValidationException::withMessages([$champ => "L'exercice {$exercice->code} est clôturé : aucune opération ne peut y être enregistrée."]);
        }
    }

    public function courant(): ?AccountingExercise
    {
        return AccountingExercise::where('statut', 'ouvert')->whereDate('date_debut', '<=', today())->whereDate('date_fin', '>=', today())->first();
    }
}

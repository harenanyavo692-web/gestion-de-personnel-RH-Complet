<?php

namespace App\Services;

use App\Models\OrdreDepense;
use App\Models\HeureSupplementaire;
use App\Models\PaiementSalaire;
use App\Models\Recette;
use Carbon\CarbonInterface;

/**
 * Calculs financiers centralisés. Règle comptable :
 * solde = recettes − dépenses validées − salaires versés.
 */
class FinanceService
{
    public function totalRecettes(?CarbonInterface $du = null, ?CarbonInterface $au = null): float
    {
        return (float) Recette::query()
            ->when($du, fn ($q) => $q->whereDate('date_recette', '>=', $du))
            ->when($au, fn ($q) => $q->whereDate('date_recette', '<=', $au))
            ->sum('montant');
    }

    public function totalDepenses(?CarbonInterface $du = null, ?CarbonInterface $au = null): float
    {
        return (float) OrdreDepense::where('statut', 'validee')
            ->when($du, fn ($q) => $q->whereDate('date_depense', '>=', $du))
            ->when($au, fn ($q) => $q->whereDate('date_depense', '<=', $au))
            ->sum('montant');
    }

    public function totalSalaires(?CarbonInterface $du = null, ?CarbonInterface $au = null): float
    {
        $periode = fn ($q) => $q
            ->when($du, fn ($q) => $q->whereDate('date_paiement', '>=', $du))
            ->when($au, fn ($q) => $q->whereDate('date_paiement', '<=', $au));

        // Les heures supplémentaires payées sont une charge de personnel au même titre que le salaire.
        return (float) $periode(PaiementSalaire::query())->sum('montant')
            + (float) $periode(HeureSupplementaire::whereNotNull('date_paiement'))->sum('montant');
    }

    public function resultat(?CarbonInterface $du = null, ?CarbonInterface $au = null): float
    {
        return $this->totalRecettes($du, $au) - $this->totalDepenses($du, $au) - $this->totalSalaires($du, $au);
    }

    /** Solde cumulé selon nos livres jusqu'à une date d'arrêté. */
    public function soldeLivres(CarbonInterface $arrete): float
    {
        return $this->resultat(null, $arrete);
    }
}

{{-- Indicateurs de l'espace RAF (variables fournies par le view composer). --}}
<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
    <x-kpi label="Dépenses en attente" :value="\App\Support\Fmt::ar($rafKpi['attente'])" tone="warning" />
    <x-kpi label="Dépenses validées" :value="\App\Support\Fmt::ar($rafKpi['validees'])" />
    <x-kpi label="Masse salariale mensuelle" :value="\App\Support\Fmt::ar($rafKpi['masse'])" />
    <x-kpi label="Personnel actif" :value="$rafKpi['personnel']" />
    <x-kpi label="Résultat net estimé" :value="\App\Support\Fmt::ar($rafKpi['resultat'])" :tone="$rafKpi['resultat'] < 0 ? 'danger' : 'success'" />
</div>

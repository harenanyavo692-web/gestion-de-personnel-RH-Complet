@extends('layouts.app')
@section('title', 'Registres comptables')

@section('content')
@php
    $q = ['periode' => $periode, 'type' => $type];
    $statutPaiement = function ($l) {
        if ($l->statut === 'sorti' || ($l->paye_tunnel && $l->paye_froid)) return ['Payé', 'success'];
        return $l->paye_tunnel || $l->paye_froid ? ['Partiel', 'warning'] : ['Impayé', 'danger'];
    };
    $ts = ['attente_caisse' => ['En attente caisse', 'warning'], 'valide' => ['Validé & libéré', 'success'], 'annule' => ['Annulé', 'neutral']];
    $prest = ['interurbain' => 'Interurbain', 'urbain' => 'Urbain', 'location' => 'Location', 'exception' => 'Exception 4 T'];
@endphp
<x-page-header title="Registres comptables" subtitle="Flux entrants, flux encaissés, transport, créances et état de synthèse">
    <button onclick="window.print()" class="btn-secondary">Imprimer l'état</button>
    <a href="{{ route('comptabilite.registres.export', $q) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" />CSV</a>
</x-page-header>
@include('comptabilite._onglets-recettes')

<form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-3 print:hidden">
    <div><label class="label">Période</label><select name="periode" class="input" onchange="this.form.submit()">
        @foreach (['tout' => "Tout l'historique", 'semaine' => '7 derniers jours', 'mois' => '30 derniers jours'] as $k => $l)<option value="{{ $k }}" @selected($periode === $k)>{{ $l }}</option>@endforeach</select></div>
    <div><label class="label">Type</label><select name="type" class="input" onchange="this.form.submit()">
        @foreach (['tout' => 'Tous', 'tunnel' => 'Tunnel', 'froid' => 'Chambre froide', 'transport' => 'Transport'] as $k => $l)<option value="{{ $k }}" @selected($type === $k)>{{ $l }}</option>@endforeach</select></div>
    <button class="btn-secondary">Actualiser</button>
</form>

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-6">
    <x-kpi label="Total encaissé" :value="\App\Support\Fmt::ar($kpi['total'])" tone="success" />
    <x-kpi label="Recettes tunnel" :value="\App\Support\Fmt::ar($kpi['tunnel'])" />
    <x-kpi label="Recettes froid" :value="\App\Support\Fmt::ar($kpi['froid'])" />
    <x-kpi label="Recettes transport" :value="\App\Support\Fmt::ar($kpi['transport'])" />
    <x-kpi label="Créances en attente" :value="\App\Support\Fmt::ar($kpi['creances'])" tone="danger" />
    <x-kpi label="Lots non soldés" :value="$kpi['nonSoldes']" tone="warning" />
</div>

<div class="mb-6 grid gap-4 lg:grid-cols-2">
    <div class="card card-pad">
        <h2 class="mb-3 text-sm font-semibold">Répartition des recettes</h2>
        @php $tot = max(1, $kpi['total']); @endphp
        @forelse ($recettes->sortDesc() as $nom => $m)
            <div class="mb-2.5 last:mb-0"><div class="mb-1 flex justify-between text-xs"><span>{{ $nom }}</span><span class="tabular-nums text-slate-600">@ar($m) · {{ round($m / $tot * 100) }} %</span></div>
                <div class="h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-brand" style="width: {{ $m / $tot * 100 }}%"></div></div></div>
        @empty<p class="py-6 text-center text-sm text-slate-400">Aucune recette sur cette période.</p>@endforelse
    </div>
    <div class="card card-pad">
        <h2 class="mb-3 text-sm font-semibold">État financier de synthèse</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt>Recettes</dt><dd class="tabular-nums text-emerald-700">@ar($synthese['recettes'])</dd></div>
            <div class="flex justify-between"><dt>Dépenses validées</dt><dd class="tabular-nums">− @ar($synthese['depenses'])</dd></div>
            <div class="flex justify-between"><dt>Salaires versés</dt><dd class="tabular-nums">− @ar($synthese['salaires'])</dd></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold"><dt>Résultat</dt><dd class="tabular-nums {{ $synthese['resultat'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">@ar($synthese['resultat'])</dd></div>
        </dl>
        <p class="mt-3 text-xs text-slate-400">Détail temporel : <a href="{{ route('comptabilite.situation') }}" class="text-brand hover:underline">Situation financière</a>.</p>
    </div>
</div>

<div class="card mb-6">
    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold">Registre des bons d'entrée (flux entrants)</h2><span class="text-sm tabular-nums text-orange-700">{{ $lots->count() }} bon(s)</span></div>
    <div class="table-wrap"><table class="data">
        <thead><tr><th>N° bon</th><th>Date</th><th>Client</th><th>Espèce</th><th>Infrastructure</th><th class="text-right">Poids brut</th><th class="text-right">Montant facturé</th><th>Paiement</th><th></th></tr></thead>
        <tbody>
        @forelse ($lots as $l)
            @php [$lib, $ton] = $statutPaiement($l); $facture = (float) $l->montant_tunnel + ($l->paye_froid ? (float) $l->montant_froid_encaisse : $l->montantFroidEstime()); @endphp
            <tr><td class="font-mono text-xs">{{ $l->reference }}</td><td class="whitespace-nowrap">{{ $l->date_entree->format('d/m/Y') }}</td><td>{{ $l->client->nom }}</td><td>{{ $l->espece }}</td><td>{{ $l->infrastructure->nom }}</td>
                <td class="text-right tabular-nums">{{ number_format($l->poids_entree, 0, ',', ' ') }} kg</td><td class="text-right tabular-nums">@ar($facture)</td>
                <td><x-badge :tone="$ton">{{ $lib }}</x-badge></td>
                <td class="text-right">@can('lots.voir')<a class="text-xs text-brand hover:underline print:hidden" href="{{ route('exploitation.lots.bon', $l) }}">Imprimer</a>@endcan</td></tr>
        @empty<x-empty :cols="9" message="Aucun bon d'entrée sur cette période." />@endforelse
        </tbody></table></div>
</div>

<div class="card mb-6">
    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold">Registre des bons de sortie (flux encaissés)</h2><span class="text-sm tabular-nums text-emerald-700">@ar($sorties->sum(fn ($s) => $s['tunnel'] + $s['chambre']))</span></div>
    <div class="table-wrap"><table class="data">
        <thead><tr><th>N° bon</th><th>Date sortie</th><th>Client</th><th>Espèce</th><th>Infrastructure</th><th class="text-right">Poids contre-pesée</th><th class="text-right">Tunnel</th><th class="text-right">Chambre</th><th class="text-right">Total</th></tr></thead>
        <tbody>
        @forelse ($sorties as $s)
            <tr><td class="font-mono text-xs">{{ $s['ref'] }}</td><td class="whitespace-nowrap">{{ $s['date']->format('d/m/Y') }}</td><td>{{ $s['client'] }}</td><td>{{ $s['espece'] }}</td><td>{{ $s['infra'] }}</td>
                <td class="text-right tabular-nums">{{ number_format($s['poids'], 0, ',', ' ') }} kg</td><td class="text-right tabular-nums">@ar($s['tunnel'])</td><td class="text-right tabular-nums">@ar($s['chambre'])</td><td class="text-right font-medium tabular-nums">@ar($s['tunnel'] + $s['chambre'])</td></tr>
        @empty<x-empty :cols="9" message="Aucun bon de sortie sur cette période." />@endforelse
        </tbody></table></div>
</div>

<div class="card mb-6">
    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold">Registre transport (ordres de mission)</h2><span class="text-sm tabular-nums text-violet-700">@ar($missions->where('statut', 'valide')->sum('cout'))</span></div>
    <div class="table-wrap"><table class="data">
        <thead><tr><th>N° mission</th><th>Date</th><th>Véhicule</th><th>Prestation / destination</th><th class="text-right">Poids</th><th class="text-right">Montant</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse ($missions as $m)
            <tr><td class="font-mono text-xs">{{ $m->reference }}</td><td class="whitespace-nowrap">{{ $m->created_at->format('d/m/Y') }}</td><td>{{ $m->vehicule->nom }}</td>
                <td>{{ $prest[$m->prestation] }} · {{ $m->destination ?? $m->zone_urbaine ?? $m->lieu_destination ?? '—' }}</td>
                <td class="text-right tabular-nums">{{ number_format($m->poids_kg, 0, ',', ' ') }} kg</td><td class="text-right tabular-nums">@ar($m->cout)</td>
                <td><x-badge :tone="$ts[$m->statut][1]">{{ $ts[$m->statut][0] }}</x-badge></td>
                <td class="text-right">@can('transport.voir')<a class="text-xs text-brand hover:underline print:hidden" href="{{ route('exploitation.transport.ordre', $m) }}">Imprimer</a>@endcan</td></tr>
        @empty<x-empty :cols="8" message="Aucune mission sur cette période." />@endforelse
        </tbody></table></div>
</div>

<div class="card">
    <h2 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Créances en cours (lots non soldés)</h2>
    <div class="table-wrap"><table class="data">
        <thead><tr><th>N° lot</th><th>Client</th><th>Infrastructure</th><th class="text-right">Montant estimé</th><th>Tunnel payé</th><th>Chambre payée</th><th class="text-right">Jours en stock</th></tr></thead>
        <tbody>
        @forelse ($creances as $l)
            <tr><td class="font-mono text-xs">{{ $l->reference }}</td><td>{{ $l->client->nom }}</td><td>{{ $l->infrastructure->nom }}</td><td class="text-right font-medium tabular-nums text-red-700">@ar($l->solde_estime)</td>
                <td><x-badge :tone="$l->paye_tunnel ? 'success' : 'danger'">{{ $l->paye_tunnel ? 'Oui' : 'Non' }}</x-badge></td><td><x-badge :tone="$l->paye_froid ? 'success' : 'danger'">{{ $l->paye_froid ? 'Oui' : 'Non' }}</x-badge></td>
                <td class="text-right tabular-nums">{{ $l->date_entree->diffInDays(now()) }}</td></tr>
        @empty<x-empty :cols="7" message="Aucune créance en cours." />@endforelse
        </tbody></table></div>
</div>
@endsection

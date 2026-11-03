@extends('layouts.app')
@section('title', 'Budgets')

@section('content')
@php $tones = ['ok' => 'success', 'alerte' => 'warning', 'depasse' => 'danger']; $libs = ['ok' => 'Dans le budget', 'alerte' => 'Seuil atteint', 'depasse' => 'Dépassé']; @endphp
<x-page-header title="Suivi budgétaire" subtitle="Budget de référence des entités comptables et dépenses validées">
    <form method="GET"><select name="exercice" class="input" onchange="this.form.submit()">@forelse ($exercices as $e)<option value="{{ $e->id }}" @selected($courant === $e->id)>{{ $e->code }} — {{ $e->libelle }}</option>@empty<option>Aucun exercice</option>@endforelse</select></form>
</x-page-header>
@include('comptabilite._onglets-budgets')

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Budget total (entités de tête)" :value="\App\Support\Fmt::ar($totalBudget)" />
    <x-kpi label="Dépenses validées" :value="\App\Support\Fmt::ar($totalConso)" />
    <x-kpi label="Reste" :value="\App\Support\Fmt::ar($totalBudget - $totalConso)" :tone="$totalBudget - $totalConso < 0 ? 'danger' : 'success'" />
    <x-kpi label="Seuil d'alerte" :value="$seuil . ' %'" />
</div>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Entité</th><th>Type</th><th class="text-right">Budget</th><th class="text-right">Dépensé</th><th class="text-right">Reste</th><th class="w-40">Consommation</th><th>État</th></tr></thead>
        <tbody>
        @forelse ($lignes as $l)
            <tr>
                <td><a href="{{ route('comptabilite.entites.show', $l['entite']) }}" class="font-medium text-brand hover:underline">{{ $l['entite']->code }}</a><p class="text-xs text-slate-400">{{ $l['entite']->nom }}</p></td>
                <td class="text-xs">{{ $l['entite']->type_libelle }}</td>
                <td class="text-right tabular-nums">@ar($l['budget'])</td><td class="text-right tabular-nums">@ar($l['conso'])</td>
                <td class="text-right tabular-nums {{ $l['reste'] < 0 ? 'text-red-700' : '' }}">@ar($l['reste'])</td>
                <td><div class="h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded {{ ['ok' => 'bg-brand', 'alerte' => 'bg-amber-500', 'depasse' => 'bg-red-500'][$l['etat']] }}" style="width: {{ min(100, $l['pct']) }}%"></div></div><p class="mt-0.5 text-xs text-slate-500">{{ $l['pct'] }} %</p></td>
                <td><x-badge :tone="$tones[$l['etat']]">{{ $libs[$l['etat']] }}</x-badge></td>
            </tr>
        @empty
            <x-empty :cols="7" message="Aucun budget défini pour cet exercice. Renseignez le budget de référence dans les entités comptables." />
        @endforelse
        </tbody>
    </table>
</div>
@endsection

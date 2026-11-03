@extends('layouts.app')
@section('title', 'Situation financière')

@section('content')
@php
    $onglets = ['jour' => "Aujourd'hui", 'semaine' => '7 jours', 'mois' => 'Mois en cours', 'annee' => 'Année'];
    $max = max(1, $lignes->max(fn ($l) => max($l['recettes'], $l['depenses'] + $l['salaires'])));
    $libelle = fn ($cle) => strlen($cle) === 7 ? \Carbon\Carbon::createFromFormat('Y-m', $cle)->translatedFormat('F') : \Carbon\Carbon::parse($cle)->format('d/m');
@endphp
<x-page-header title="Situation financière" :subtitle="$debut->format('d/m/Y') . ' → ' . $fin->format('d/m/Y')">
    <div class="inline-flex rounded-md border border-slate-300 bg-white p-0.5 text-sm">
        @foreach ($onglets as $k => $l)
            <a href="{{ route('comptabilite.situation', ['periode' => $k]) }}"
               class="rounded px-3 py-1 {{ $periode === $k ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $l }}</a>
        @endforeach
    </div>
    <a href="{{ route('comptabilite.situation.export', ['periode' => $periode]) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" />CSV</a>
    <button type="button" onclick="window.print()" class="btn-secondary">Imprimer</button>
</x-page-header>
@include('comptabilite._onglets-rapports')

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Recettes" :value="\App\Support\Fmt::ar($totaux['recettes'])" tone="success"
           :hint="$variation === null ? 'Pas de période de comparaison' : ($variation >= 0 ? '+' : '') . $variation . ' % vs période précédente'" />
    <x-kpi label="Dépenses validées" :value="\App\Support\Fmt::ar($totaux['depenses'])" />
    <x-kpi label="Salaires" :value="\App\Support\Fmt::ar($totaux['salaires'])" />
    <x-kpi label="Résultat" :value="\App\Support\Fmt::ar($totaux['resultat'])" :tone="$totaux['resultat'] < 0 ? 'danger' : 'success'" />
</div>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>{{ $periode === 'annee' ? 'Mois' : 'Jour' }}</th><th class="w-1/3">Recettes / sorties</th><th class="text-right">Recettes</th><th class="text-right">Dépenses</th><th class="text-right">Salaires</th><th class="text-right">Résultat</th></tr></thead>
        <tbody>
        @foreach ($lignes as $l)
            @php $res = $l['recettes'] - $l['depenses'] - $l['salaires']; @endphp
            <tr>
                <td class="whitespace-nowrap capitalize">{{ $libelle($l['cle']) }}</td>
                <td>
                    <div class="h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-emerald-500" style="width: {{ $l['recettes'] / $max * 100 }}%"></div></div>
                    <div class="mt-1 h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-slate-500" style="width: {{ ($l['depenses'] + $l['salaires']) / $max * 100 }}%"></div></div>
                </td>
                <td class="text-right tabular-nums">@ar($l['recettes'])</td>
                <td class="text-right tabular-nums">@ar($l['depenses'])</td>
                <td class="text-right tabular-nums">@ar($l['salaires'])</td>
                <td class="text-right font-medium tabular-nums {{ $res < 0 ? 'text-red-700' : '' }}">@ar($res)</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<p class="mt-2 text-xs text-slate-400">Barres : vert = recettes, gris = dépenses validées + salaires.</p>
@endsection

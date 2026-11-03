@extends('layouts.app')
@section('title', 'État financier')

@section('content')
@php
    $barres = function ($titre, $lignes, $couleur) {
        $max = max(1, (float) $lignes->max());
        return compact('titre', 'lignes', 'couleur', 'max');
    };
    $blocs = [$barres('Recettes par catégorie', $recettes, 'bg-emerald-500'), $barres('Dépenses validées par catégorie', $depenses, 'bg-slate-500')];
@endphp
<x-page-header title="État financier & rapports" :subtitle="$du->format('d/m/Y') . ' → ' . $au->format('d/m/Y')">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="date" name="du" value="{{ $du->toDateString() }}" class="input w-auto" aria-label="Du">
        <input type="date" name="au" value="{{ $au->toDateString() }}" class="input w-auto" aria-label="Au">
        <button class="btn-secondary">Filtrer</button>
        <a href="{{ route('raf.rapports.export', ['du' => $du->toDateString(), 'au' => $au->toDateString()]) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" />CSV</a>
        <button type="button" onclick="window.print()" class="btn-secondary">Imprimer</button>
    </form>
</x-page-header>
@include('comptabilite._onglets-rapports')
@include('raf._kpis')

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Recettes" :value="\App\Support\Fmt::ar($totalRecettes)" tone="success" />
    <x-kpi label="Dépenses validées" :value="\App\Support\Fmt::ar($totalDepenses)" />
    <x-kpi label="Salaires versés" :value="\App\Support\Fmt::ar($totalSalaires)" />
    <x-kpi label="Résultat net" :value="\App\Support\Fmt::ar($resultat)" :tone="$resultat < 0 ? 'danger' : 'success'" />
</div>

<div class="mb-6 grid gap-4 lg:grid-cols-2">
    @foreach ($blocs as $b)
    <div class="card card-pad">
        <h2 class="mb-3 text-sm font-semibold">{{ $b['titre'] }}</h2>
        @forelse ($b['lignes'] as $nom => $montant)
            <div class="mb-3 last:mb-0">
                <div class="mb-1 flex justify-between text-xs"><span>{{ $nom }}</span><span class="tabular-nums text-slate-600">@ar($montant)</span></div>
                <div class="h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded {{ $b['couleur'] }}" style="width: {{ $montant / $b['max'] * 100 }}%"></div></div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-slate-400">Aucune opération sur cette période.</p>
        @endforelse
    </div>
    @endforeach
</div>

<div class="card card-pad mb-6">
    <h2 class="mb-3 text-sm font-semibold">Recettes vs dépenses — 7 derniers jours</h2>
    @php $maxJ = max(1, collect($sept)->max(fn ($j) => max($j['recettes'], $j['depenses']))); @endphp
    <div class="grid grid-cols-7 items-end gap-2" style="height: 9rem">
        @foreach ($sept as $j)
            <div class="flex h-full flex-col items-center justify-end gap-1">
                <div class="flex h-full w-full items-end justify-center gap-1">
                    <div class="w-1/2 rounded-t bg-emerald-500" style="height: {{ $j['recettes'] / $maxJ * 100 }}%" title="Recettes : {{ \App\Support\Fmt::ar($j['recettes']) }}"></div>
                    <div class="w-1/2 rounded-t bg-slate-500" style="height: {{ $j['depenses'] / $maxJ * 100 }}%" title="Dépenses : {{ \App\Support\Fmt::ar($j['depenses']) }}"></div>
                </div>
                <span class="text-[10px] text-slate-500">{{ $j['label'] }}</span>
            </div>
        @endforeach
    </div>
    <p class="mt-2 text-xs text-slate-400">Vert : recettes · gris : dépenses validées.</p>
</div>

<div class="card card-pad">
    <h2 class="mb-3 text-sm font-semibold">Commentaires & observations</h2>
    @can('rapports.commenter')
    <form method="POST" action="{{ route('raf.rapports.commenter') }}" class="mb-4 flex flex-col gap-2 sm:flex-row">
        @csrf
        <textarea name="contenu" rows="2" required maxlength="2000" class="input" placeholder="Analyse, remarque, décision à noter…"></textarea>
        <button class="btn-primary sm:self-start">Publier</button>
    </form>
    @endcan
    <ul class="divide-y divide-slate-100">
        @forelse ($commentaires as $c)
            <li class="py-2.5 text-sm"><p>{{ $c->contenu }}</p><p class="mt-0.5 text-xs text-slate-400">{{ $c->auteur->name }} · {{ $c->created_at->format('d/m/Y H:i') }}</p></li>
        @empty
            <li class="py-4 text-center text-sm text-slate-400">Aucun commentaire.</li>
        @endforelse
    </ul>
</div>
@endsection

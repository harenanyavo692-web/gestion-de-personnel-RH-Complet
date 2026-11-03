@extends('layouts.app')
@section('title', 'Entités RH')

@section('content')
@php
    $gerer = auth()->user()->can('rh.entites.gerer');
    $noeud = function ($e, $niveau = 0) use (&$noeud, $comptes) {
        $h = '<li class="' . ($niveau ? 'ml-5 border-l border-slate-200 pl-3' : '') . '">'
            . '<a href="' . route('rh.entites.show', $e) . '" class="inline-flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-slate-100 ' . ($e->actif ? '' : 'text-slate-400 line-through') . '">'
            . '<span class="font-mono text-xs text-slate-400">' . e($e->code) . '</span><span class="font-medium">' . e($e->nom) . '</span>'
            . '<span class="text-xs text-slate-400">' . e($e->type_libelle) . ' · ' . ($comptes[$e->id] ?? 0) . ' emp.</span></a>';
        $enfants = $e->enfants->sortBy('nom');
        if ($enfants->isNotEmpty()) {
            $h .= '<ul class="mt-0.5">' . $enfants->map(fn ($c) => $noeud($c, $niveau + 1))->implode('') . '</ul>';
        }

        return $h . '</li>';
    };
@endphp
<x-page-header title="Entités RH" subtitle="Directions, départements, services, unités et bureaux">
    <div class="inline-flex rounded-md border border-slate-300 bg-white p-0.5 text-sm">
        <a href="{{ route('rh.entites.index') }}" class="rounded px-3 py-1 {{ ! $arbre ? 'bg-brand text-white' : 'text-slate-600' }}">Tableau</a>
        <a href="{{ route('rh.entites.index', ['vue' => 'arbre']) }}" class="rounded px-3 py-1 {{ $arbre ? 'bg-brand text-white' : 'text-slate-600' }}">Hiérarchie</a>
    </div>
    @if ($gerer)<a href="{{ route('rh.entites.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" />Nouvelle entité</a>@endif
    <x-view-toggle group="entites-rh" />
</x-page-header>
@include('rh._onglets-parametres')

@if ($arbre)
    <div class="card card-pad">
        <ul class="space-y-0.5">{!! $racines->map(fn ($r) => $noeud($r))->implode('') !!}</ul>
        @if ($racines->isEmpty())<p class="py-6 text-center text-sm text-slate-400">Aucune entité.</p>@endif
    </div>
@else
<form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-4">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom ou code…" class="input sm:col-span-2">
    <select name="type" class="input"><option value="">Tous types</option>@foreach ($types as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select>
    <div class="flex gap-2"><select name="statut" class="input"><option value="">Tous statuts</option><option value="actif" @selected(request('statut') === 'actif')>Actives</option><option value="inactif" @selected(request('statut') === 'inactif')>Inactives</option></select><button class="btn-secondary">OK</button></div>
</form>
<div data-view-group="entites-rh" data-default="liste">
<div data-view="liste" class="card table-wrap">
    <table class="data">
        <thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Rattachée à</th><th>Responsable</th><th class="text-right">Employés</th><th>Statut</th></tr></thead>
        <tbody>
        @forelse ($entites as $e)
            <tr>
                <td class="font-mono text-xs">{{ $e->code }}</td>
                <td><a href="{{ route('rh.entites.show', $e) }}" class="font-medium text-brand hover:underline">{{ $e->nom }}</a>@if ($e->localisation)<p class="text-xs text-slate-400">{{ $e->localisation }}</p>@endif</td>
                <td>{{ $e->type_libelle }}</td><td class="text-slate-500">{{ $e->parent?->nom ?? '—' }}</td>
                <td>{{ $e->responsable?->nom_complet ?? '—' }}</td><td class="text-right tabular-nums">{{ $e->employes_count }}</td>
                <td><x-badge :tone="$e->actif ? 'success' : 'neutral'">{{ $e->actif ? 'Active' : 'Inactive' }}</x-badge></td>
            </tr>
        @empty
            <x-empty :cols="7" message="Aucune entité ne correspond à ces critères." />
        @endforelse
        </tbody>
    </table>
</div>
<div data-view="grille" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @forelse ($entites as $e)
        <a href="{{ route('rh.entites.show', $e) }}" class="card card-pad block transition hover:shadow-md {{ $e->actif ? '' : 'opacity-60' }}">
            <div class="flex items-start gap-3">
                <span class="tile h-11 w-11"><x-icon name="building" /></span>
                <div class="min-w-0 flex-1"><p class="truncate font-medium">{{ $e->nom }}</p><p class="font-mono text-xs" style="color: var(--faint)">{{ $e->code }} · {{ $e->type_libelle }}</p></div>
                <x-badge :tone="$e->actif ? 'success' : 'neutral'">{{ $e->actif ? 'Active' : 'Inactive' }}</x-badge>
            </div>
            <p class="mt-3 text-xs" style="color: var(--muted)">{{ $e->parent?->nom ? 'Rattachée à ' . $e->parent->nom : 'Niveau supérieur' }}</p>
            <p class="mt-2 border-t pt-2 text-xs" style="border-color: var(--line); color: var(--muted)">{{ $e->responsable?->nom_complet ?? 'Sans responsable' }} · <strong>{{ $e->employes_count }}</strong> employé(s)</p>
        </a>
    @empty
        <div class="card card-pad text-center text-sm sm:col-span-2 xl:col-span-3" style="color: var(--faint)">Aucune entité ne correspond à ces critères.</div>
    @endforelse
</div>
</div>
<div class="mt-4">{{ $entites->links() }}</div>
@endif
@endsection

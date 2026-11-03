@extends('layouts.app')
@section('title', 'Entités comptables')

@section('content')
<x-page-header title="Entités comptables" subtitle="Centres de coûts, sections budgétaires, projets et périmètres">
    @can('compta.entites.gerer')<a href="{{ route('comptabilite.entites.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" />Nouvelle entité</a>@endcan
    <x-view-toggle group="entites-compta" />
</x-page-header>
@include('comptabilite._onglets-budgets')

<form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-4">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Code ou nom…" class="input sm:col-span-2">
    <select name="type" class="input"><option value="">Tous types</option>@foreach ($types as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select>
    <div class="flex gap-2"><select name="statut" class="input"><option value="">Tous statuts</option><option value="actif" @selected(request('statut') === 'actif')>Actives</option><option value="inactif" @selected(request('statut') === 'inactif')>Inactives</option></select><button class="btn-secondary">OK</button></div>
</form>

<div data-view-group="entites-compta" data-default="liste">
<div data-view="liste" class="card table-wrap">
    <table class="data">
        <thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Rattachée à</th><th>Entité RH</th><th>Responsable</th><th class="text-right">Budget</th><th>Statut</th></tr></thead>
        <tbody>
        @forelse ($entites as $e)
            <tr>
                <td class="font-mono text-xs">{{ $e->code }}</td>
                <td><a href="{{ route('comptabilite.entites.show', $e) }}" class="font-medium text-brand hover:underline">{{ $e->nom }}</a></td>
                <td>{{ $e->type_libelle }}</td><td class="text-slate-500">{{ $e->parent?->code ?? '—' }}</td>
                <td class="text-slate-500">{{ $e->organisation?->nom ?? '—' }}</td><td>{{ $e->responsable?->name ?? '—' }}</td>
                <td class="text-right tabular-nums">{{ $e->budget_reference !== null ? \App\Support\Fmt::ar($e->budget_reference) : '—' }}@if ($e->exercice)<p class="text-xs text-slate-400">{{ $e->exercice->code }}</p>@endif</td>
                <td><x-badge :tone="$e->actif ? 'success' : 'neutral'">{{ $e->actif ? 'Active' : 'Inactive' }}</x-badge></td>
            </tr>
        @empty
            <x-empty :cols="8" message="Aucune entité comptable ne correspond à ces critères." />
        @endforelse
        </tbody>
    </table>
</div>
<div data-view="grille" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @forelse ($entites as $e)
        <a href="{{ route('comptabilite.entites.show', $e) }}" class="card card-pad block transition hover:shadow-md {{ $e->actif ? '' : 'opacity-60' }}">
            <div class="flex items-start gap-3">
                <span class="tile h-11 w-11"><x-icon name="wallet" /></span>
                <div class="min-w-0 flex-1"><p class="truncate font-medium">{{ $e->nom }}</p><p class="font-mono text-xs" style="color: var(--faint)">{{ $e->code }} · {{ $e->type_libelle }}</p></div>
                <x-badge :tone="$e->actif ? 'success' : 'neutral'">{{ $e->actif ? 'Active' : 'Inactive' }}</x-badge>
            </div>
            <p class="mt-3 text-sm font-semibold tabular-nums">{{ $e->budget_reference !== null ? \App\Support\Fmt::ar($e->budget_reference) : 'Pas de budget' }}<span class="ml-1 text-xs font-normal" style="color: var(--faint)">{{ $e->exercice?->code }}</span></p>
            <p class="mt-2 border-t pt-2 text-xs" style="border-color: var(--line); color: var(--muted)">{{ $e->responsable?->name ?? 'Sans responsable' }}{{ $e->organisation ? ' · ' . $e->organisation->nom : '' }}</p>
        </a>
    @empty
        <div class="card card-pad text-center text-sm sm:col-span-2 xl:col-span-3" style="color: var(--faint)">Aucune entité comptable ne correspond à ces critères.</div>
    @endforelse
</div>
</div>
<div class="mt-4">{{ $entites->links() }}</div>
@endsection

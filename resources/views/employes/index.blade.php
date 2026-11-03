@extends('layouts.app')
@section('title', 'Personnel')

@section('content')
@php $tones = ['actif' => 'success', 'congé' => 'warning', 'suspendu' => 'danger', 'inactif' => 'neutral']; @endphp

<x-page-header title="Personnel" :subtitle="$stats['total'] . ' employés · ' . $stats['actifs'] . ' actifs'">
    @can('employes.gerer')<a href="{{ route('employes.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" />Nouvel employé</a>@endcan
    <x-view-toggle group="employes" />
</x-page-header>
@include('rh._onglets-personnel', ['actif' => 'employes'])

<form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-4">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom, matricule, poste…" class="input sm:col-span-2">
    <select name="entity_id" class="input">
        <option value="">Toutes les entités</option>
        @foreach ($entites as $en)<option value="{{ $en->id }}" @selected((int) request('entity_id') === $en->id)>{{ $en->nom }}</option>@endforeach
    </select>
    <div class="flex gap-2">
        <select name="statut" class="input">
            <option value="">Tous statuts</option>
            @foreach (array_keys($tones) as $s)<option value="{{ $s }}" @selected(request('statut') === $s)>{{ ucfirst($s) }}</option>@endforeach
        </select>
        <button class="btn-secondary">Filtrer</button>
    </div>
</form>

<div data-view-group="employes" data-default="liste">
<div data-view="liste" class="card table-wrap">
    <table class="data">
        <thead><tr><th>Employé</th><th>Matricule</th><th>Poste</th><th>Département</th><th>Statut</th>@can('employes.gerer')<th class="text-right">Actions</th>@endcan</tr></thead>
        <tbody>
        @forelse ($employes as $e)
            <tr>
                <td><div class="flex items-center gap-3"><x-initials :name="$e->nom_complet" /><div><p class="font-medium">{{ $e->nom_complet }}</p><p class="text-xs text-slate-400">{{ $e->email }}</p></div></div></td>
                <td class="font-mono text-xs">{{ $e->matricule }}</td>
                <td>{{ $e->poste }}</td>
                <td class="text-slate-600">{{ $e->departement }}</td>
                <td><x-badge :tone="$tones[$e->statut] ?? 'neutral'">{{ ucfirst($e->statut) }}</x-badge></td>
                @can('employes.gerer')
                <td class="whitespace-nowrap text-right"><div class="flex justify-end">
                    <x-icon-btn icon="edit" title="Modifier" :href="route('employes.edit', $e)" />
                    <x-archive-btn type="employes" :element="$e" />
                    <form action="{{ route('employes.destroy', $e) }}" method="POST" data-confirm="Mettre {{ $e->nom_complet }} dans la corbeille ?" data-confirm-label="Supprimer">@csrf @method('DELETE')<x-icon-btn icon="trash" tone="danger" title="Supprimer" /></form>
                </div></td>
                @endcan
            </tr>
        @empty
            <x-empty :cols="6" message="Aucun employé trouvé." />
        @endforelse
        </tbody>
    </table>
</div>
<div data-view="grille" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @forelse ($employes as $e)
        <div class="card card-pad">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-semibold" style="background: var(--brand-soft); color: var(--brand)">{{ mb_strtoupper(mb_substr($e->prenom, 0, 1) . mb_substr($e->nom, 0, 1)) }}</span>
                <div class="min-w-0 flex-1"><p class="truncate font-medium">{{ $e->nom_complet }}</p><p class="truncate text-xs" style="color: var(--muted)">{{ $e->poste }}</p><p class="truncate text-xs" style="color: var(--faint)">{{ $e->email }}</p></div>
                <x-badge :tone="$tones[$e->statut] ?? 'neutral'">{{ ucfirst($e->statut) }}</x-badge>
            </div>
            <div class="mt-3 flex items-center justify-between border-t pt-3 text-xs" style="border-color: var(--line)">
                <span style="color: var(--muted)">{{ $e->entite?->nom ?? $e->departement }} · <span class="font-mono">{{ $e->matricule }}</span></span>
                @can('employes.gerer')
                <span class="flex"><x-icon-btn icon="edit" title="Modifier" :href="route('employes.edit', $e)" />
                    <x-archive-btn type="employes" :element="$e" />
                    <form action="{{ route('employes.destroy', $e) }}" method="POST" data-confirm="Mettre {{ $e->nom_complet }} dans la corbeille ?" data-confirm-label="Supprimer">@csrf @method('DELETE')<x-icon-btn icon="trash" tone="danger" title="Supprimer" /></form></span>
                @endcan
            </div>
        </div>
    @empty
        <div class="card card-pad text-center text-sm sm:col-span-2 xl:col-span-3" style="color: var(--faint)">Aucun employé trouvé.</div>
    @endforelse
</div>
</div>
<div class="mt-4">{{ $employes->links() }}</div>
@endsection

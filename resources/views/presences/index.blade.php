@extends('layouts.app')
@section('title', 'Présences')

@section('content')
<x-page-header title="Présences" subtitle="Pointage journalier du personnel">
    <form method="GET"><input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="input"></form>
</x-page-header>
@include('rh._onglets-temps', ['actif' => 'presences'])

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
    <x-kpi label="Présents" :value="$resume['presence']" tone="success" />
    <x-kpi label="Absents" :value="$resume['absence']" :tone="$resume['absence'] ? 'danger' : 'neutral'" />
    <x-kpi label="En congé" :value="$resume['conge']" />
    <x-kpi label="En mission" :value="$resume['mission']" />
    <x-kpi label="Non pointés" :value="$resume['non_pointe']" :tone="$resume['non_pointe'] ? 'warning' : 'neutral'" />
</div>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Employé</th><th>Département</th><th>Statut du jour</th>@can('presences.gerer')<th class="text-right">Pointer</th>@endcan</tr></thead>
        <tbody>
        @forelse ($employes as $e)
            @php $p = $e->presences->first(); @endphp
            <tr>
                <td class="font-medium">{{ $e->nom_complet }}</td>
                <td class="text-slate-600">{{ $e->departement }}</td>
                <td>
                    @if ($p)
                        <x-badge :tone="['présence' => 'success', 'congé' => 'info', 'mission' => 'info', 'absence' => 'danger'][$p->type] ?? 'neutral'">{{ ucfirst($p->type) }}@if ($p->heure_arrivee) · {{ $p->heure_arrivee }}@endif</x-badge>
                    @else
                        <x-badge>Non pointé</x-badge>
                    @endif
                </td>
                @can('presences.gerer')
                <td>
                    @if ($p && str_starts_with((string) $p->note, 'absence #'))
                        <p class="text-right text-xs text-slate-400">Issu d'une demande validée</p>
                    @else
                    <form method="POST" action="{{ route('presences.pointer', $e) }}" class="flex justify-end gap-2">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <select name="type" class="input w-auto py-1.5 text-xs">
                            @foreach (['présence', 'absence'] as $t)<option value="{{ $t }}" @selected($p?->type === $t)>{{ ucfirst($t) }}</option>@endforeach
                        </select>
                        <button class="btn-primary btn-sm">Valider</button>
                    </form>
                    @endif
                </td>
                @endcan
            </tr>
        @empty
            <x-empty :cols="4" message="Aucun employé actif." />
        @endforelse
        </tbody>
    </table>
</div>
@endsection

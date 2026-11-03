@extends('layouts.app')
@section('title', 'Lettres RH')

@section('content')
@php
    $emp = $employes->map(fn ($e) => ['nom' => $e->prenom . ' ' . $e->nom, 'poste' => $e->poste, 'debut' => $e->date_embauche->toDateString()]);
    $tones = ['demission' => 'neutral', 'renvoi' => 'danger', 'conge' => 'info', 'fin_stage' => 'info', 'attestation_travail' => 'success', 'certificat_travail' => 'success', 'note_service' => 'warning', 'avertissement' => 'danger'];
@endphp
<x-page-header title="Lettres RH" subtitle="Registre des lettres officielles délivrées : générer, consulter, imprimer" icon="file" add="form-ajout" add-label="Nouvelle lettre" />
@include('raf._onglets-personnel')

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Lettres délivrées" :value="$total" />
    <x-kpi label="Ce mois-ci" :value="$ceMois" tone="success" />
</div>

<details id="form-ajout" class="form-modal card mb-4" @if ($errors->any()) open @endif>
    <summary>Nouvelle lettre</summary>
    <form method="POST" action="{{ route('raf.lettres.store') }}" class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
        @csrf
        <x-field name="type" label="Type de lettre" type="select" required>
            <option value="">—</option>@foreach ($types as $k => $l)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>@endforeach
        </x-field>
        <div>
            <label class="label" for="employe-select">Employé enregistré (optionnel)</label>
            <select id="employe-select" name="employe_id" class="input"><option value="">— Saisie libre —</option>
                @foreach ($employes as $i => $e)<option value="{{ $e->id }}" data-i="{{ $i }}">{{ $e->prenom }} {{ $e->nom }}</option>@endforeach
            </select>
        </div>
        <x-field name="nom" label="Nom complet" required />
        <x-field name="poste" label="Poste / fonction" required />
        <x-field name="date_debut" label="Date d'entrée / début" type="date" />
        <x-field name="date_fin" label="Date de fin / d'effet" type="date" />
        <x-field name="motif" label="Motif / commentaire" type="textarea" class="sm:col-span-2" />
        <x-field name="signataire" label="Signataire" value="Le Directeur Général" />
        <div class="sm:col-span-2 lg:col-span-3 flex justify-end gap-2"><button type="button" class="btn-secondary" data-modal-close>Annuler</button><button class="btn-primary"><x-icon name="file" class="h-4 w-4" />Générer la lettre</button></div>
    </form>
</details>

<form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-4">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom, poste ou référence…" class="input sm:col-span-2">
    <select name="type" class="input"><option value="">Tous les types</option>@foreach ($types as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select>
    <button class="btn-secondary">Filtrer</button>
</form>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Date</th><th>Référence</th><th>Type</th><th>Personne concernée</th><th>Période</th><th>Signataire</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        @forelse ($lettres as $l)
            <tr>
                <td class="whitespace-nowrap text-sm">{{ $l->created_at->format('d/m/Y') }}<p class="text-xs text-slate-400">{{ $l->auteur?->name }}</p></td>
                <td class="font-mono text-xs">{{ $l->reference }}</td>
                <td><x-badge :tone="$tones[$l->type] ?? 'neutral'">{{ $l->type_libelle }}</x-badge></td>
                <td><div class="flex items-center gap-3"><x-initials :name="$l->nom" /><div><p class="font-medium">{{ $l->nom }}</p><p class="text-xs text-slate-400">{{ $l->poste }}</p></div></div></td>
                <td class="whitespace-nowrap text-xs text-slate-500">{{ $l->date_debut?->format('d/m/Y') ?? '—' }} → {{ $l->date_fin?->format('d/m/Y') ?? '—' }}</td>
                <td class="text-xs text-slate-500">{{ $l->signataire ?: 'La Direction' }}</td>
                <td class="whitespace-nowrap text-right"><div class="flex justify-end">
                    <x-icon-btn icon="eye" title="Consulter" :href="route('raf.lettres.show', $l)" />
                    <x-icon-btn icon="print" title="Imprimer" :href="route('raf.lettres.show', $l)" />
                    <form method="POST" action="{{ route('raf.lettres.destroy', $l) }}" data-confirm="Supprimer la lettre {{ $l->reference }} du registre ?" data-confirm-label="Supprimer">@csrf @method('DELETE')<x-icon-btn icon="trash" tone="danger" title="Supprimer" /></form>
                </div></td>
            </tr>
        @empty
            <x-empty :cols="7" icon="file" message="Aucune lettre délivrée. Cliquez sur « Nouvelle lettre » pour en générer une." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $lettres->links() }}</div>

<script>
    (function () {
        const emp = @json($emp);
        document.getElementById('employe-select')?.addEventListener('change', function () {
            const e = emp[this.selectedOptions[0]?.dataset.i]; if (!e) return;
            document.getElementById('nom').value = e.nom;
            document.getElementById('poste').value = e.poste;
            document.getElementById('date_debut').value = e.debut;
        });
    })();
</script>
@endsection

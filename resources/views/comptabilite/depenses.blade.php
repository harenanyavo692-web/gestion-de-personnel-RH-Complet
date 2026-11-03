@extends('layouts.app')
@section('title', 'Ordres de dépense')

@section('content')
@php $tones = ['validee' => 'success', 'refusee' => 'danger', 'en_attente' => 'warning']; @endphp
<x-page-header add="form-ajout" add-label="Nouvel ordre" title="Ordres de dépense" subtitle="Soumis au RAF pour validation avant tout décaissement" />
@include('comptabilite._onglets-depenses')

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="En attente de validation" :value="\App\Support\Fmt::ar($enAttente)" tone="warning" />
    <x-kpi label="Dépenses validées" :value="\App\Support\Fmt::ar($valide)" />
</div>

@can('depenses.soumettre')
<details id="form-ajout" class="form-modal card mb-4" @if ($errors->any()) open @endif>
    <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium text-brand"><x-icon name="plus" class="h-4 w-4" />Soumettre un ordre de dépense</summary>
    <form method="POST" action="{{ route('comptabilite.depenses.store') }}" enctype="multipart/form-data" class="grid gap-4 border-t border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
        @csrf
        <x-field name="motif" label="Motif" class="sm:col-span-2" required />
        <x-field name="categorie_id" label="Catégorie" type="select" required>
            <option value="">—</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('categorie_id') == $c->id)>{{ $c->nom }}</option>@endforeach
        </x-field>
        <x-field name="montant" label="Montant (Ar)" type="number" min="1" step="1" required />
        <x-field name="date_depense" label="Date" type="date" :value="now()->toDateString()" max="{{ now()->toDateString() }}" required />
        <x-field name="beneficiaire" label="Bénéficiaire / fournisseur" class="sm:col-span-1" />
        <x-field name="justificatif" label="Justificatif (5 Mo max)" type="file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv,.doc,.docx" class="sm:col-span-2" />
        @include('comptabilite._champs-compta')
        <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><button class="btn-primary">Soumettre au RAF</button></div>
    </form>
</details>
@endcan

<form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-4">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Motif, bénéficiaire, référence…" class="input sm:col-span-2">
    <select name="statut" class="input">
        <option value="">Tous statuts</option>
        @foreach (\App\Models\OrdreDepense::STATUTS as $k => $l)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $l }}</option>@endforeach
    </select>
    <div class="flex gap-2">
        <select name="categorie_id" class="input">
            <option value="">Catégories</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('categorie_id') == $c->id)>{{ $c->nom }}</option>@endforeach
        </select>
        <button class="btn-secondary">OK</button>
    </div>
</form>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Date</th><th>Référence</th><th>Motif</th><th>Catégorie</th><th class="text-right">Montant</th><th>Statut</th><th>Pièce</th><th></th></tr></thead>
        <tbody>
        @forelse ($ordres as $o)
            <tr>
                <td class="whitespace-nowrap">{{ $o->date_depense->format('d/m/Y') }}</td>
                <td class="font-mono text-xs">{{ $o->reference }}</td>
                <td>{{ $o->motif }}
                    @if ($o->beneficiaire)<p class="text-xs text-slate-400">{{ $o->beneficiaire }}</p>@endif
                    @if ($o->commentaire_raf)<p class="text-xs text-slate-500">RAF : {{ $o->commentaire_raf }}</p>@endif</td>
                <td><x-badge>{{ $o->categorie->nom }}</x-badge></td>
                <td class="text-right font-medium tabular-nums">@ar($o->montant)</td>
                <td><x-badge :tone="$tones[$o->statut]">{{ $o->statut_libelle }}</x-badge></td>
                <td>@foreach ($o->pieces as $p)<a href="{{ route('comptabilite.pieces.show', $p) }}" class="text-brand" title="{{ $p->nom_original }}"><x-icon name="paperclip" class="h-4 w-4" /></a>@endforeach</td>
                <td class="text-right"><div class="flex justify-end">
                    <x-archive-btn type="depenses" :element="$o" />
                    @can('annuler', $o)
                    <form method="POST" action="{{ route('comptabilite.depenses.destroy', $o) }}" data-confirm="Annuler cet ordre de dépense ?">@csrf @method('DELETE')
                        <x-icon-btn icon="ban" tone="danger" title="Annuler l'ordre" /></form>
                    @endcan
                </div></td>
            </tr>
        @empty
            <x-empty :cols="8" message="Aucun ordre de dépense." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $ordres->links() }}</div>
@endsection

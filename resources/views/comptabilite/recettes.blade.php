@extends('layouts.app')
@section('title', 'Recettes')

@section('content')
@php $q = fn ($extra = []) => array_merge(request()->only('du', 'au', 'q', 'categorie_id'), $extra); @endphp
<x-page-header add="form-ajout" add-label="Nouvelle recette" title="Recettes & registres" subtitle="Encaissements, bons d'entrée / sortie, missions de transport et créances">
    <button type="button" onclick="window.print()" class="btn-secondary">Imprimer</button>
</x-page-header>
@include('comptabilite._onglets-recettes')

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-6">
    <x-kpi label="Total encaissé" :value="\App\Support\Fmt::ar($total)" tone="success" />
    <x-kpi label="Tunnel" :value="\App\Support\Fmt::ar($parCategorie['Tunnel de congélation'] ?? 0)" />
    <x-kpi label="Chambre froide" :value="\App\Support\Fmt::ar($parCategorie['Chambre froide'] ?? 0)" />
    <x-kpi label="Transport" :value="\App\Support\Fmt::ar($parCategorie['Transport'] ?? 0)" />
    <x-kpi label="Créances en attente" :value="\App\Support\Fmt::ar($montantCreances)" tone="danger" />
    <x-kpi label="Lots non soldés" :value="$creances->count() + $lotsNonSoldes" tone="warning" />
</div>

@if ($parCategorie->isNotEmpty())
<div class="card card-pad mb-4">
    <h2 class="mb-3 text-sm font-semibold">Répartition des recettes</h2>
    <div class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
        @foreach ($parCategorie->sortDesc() as $nom => $m)
            <div>
                <div class="mb-1 flex justify-between text-xs"><span>{{ $nom }}</span><span class="tabular-nums text-slate-600">@ar($m) · {{ $total > 0 ? round($m / $total * 100) : 0 }} %</span></div>
                <div class="h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-brand" style="width: {{ $total > 0 ? $m / $total * 100 : 0 }}%"></div></div>
            </div>
        @endforeach
    </div>
</div>
@endif

@can('recettes.gerer')
<details id="form-ajout" class="form-modal card mb-4" @if ($errors->any()) open @endif>
    <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium text-brand"><x-icon name="plus" class="h-4 w-4" />Enregistrer une recette / un bon</summary>
    <form method="POST" action="{{ route('comptabilite.recettes.store') }}" enctype="multipart/form-data" class="grid gap-4 border-t border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
        @csrf
        <x-field name="date_recette" label="Date" type="date" :value="now()->toDateString()" max="{{ now()->toDateString() }}" required />
        <x-field name="categorie_id" label="Catégorie / registre" type="select" required>
            <option value="">—</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('categorie_id') == $c->id)>{{ $c->nom }}</option>@endforeach
        </x-field>
        <x-field name="montant" label="Montant encaissé (Ar)" type="number" min="1" step="1" required />
        <x-field name="montant_facture" label="Montant facturé (si différent)" type="number" min="1" step="1" />
        <x-field name="origine" label="Origine" required />
        <x-field name="client" label="Client" />
        <x-field name="numero_bon" label="N° de bon / mission" />
        <x-field name="espece" label="Espèce" />
        <x-field name="poids_kg" label="Poids (kg)" type="number" min="0" step="0.01" />
        <x-field name="destination" label="Destination / prestation" />
        <x-field name="note" label="Note" class="sm:col-span-2" />
        <x-field name="justificatif" label="Justificatif (5 Mo max)" type="file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv,.doc,.docx" class="sm:col-span-2" />
        @include('comptabilite._champs-compta')
        <p class="text-xs text-slate-400 sm:col-span-2 lg:col-span-4">Si le montant facturé dépasse l'encaissé, la différence apparaît en créance (lot non soldé).</p>
        <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><button class="btn-primary">Enregistrer</button></div>
    </form>
</details>
@endcan

<div class="mb-3 inline-flex flex-wrap rounded-md border border-slate-300 bg-white p-0.5 text-sm">
    <a href="{{ route('comptabilite.recettes.index', $q()) }}" class="rounded px-3 py-1 {{ ! request('registre') ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100' }}">Toutes</a>
    @foreach (['tunnel' => 'Tunnel', 'froid' => 'Chambre froide', 'transport' => 'Transport'] as $k => $l)
        <a href="{{ route('comptabilite.recettes.index', $q(['registre' => $k])) }}" class="rounded px-3 py-1 {{ request('registre') === $k ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $l }}</a>
    @endforeach
</div>

<form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-5">
    <input type="hidden" name="registre" value="{{ request('registre') }}">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Client, n° de bon, référence…" class="input sm:col-span-2">
    <select name="categorie_id" class="input">
        <option value="">Toutes catégories</option>
        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('categorie_id') == $c->id)>{{ $c->nom }}</option>@endforeach
    </select>
    <input type="date" name="du" value="{{ request('du') }}" class="input" title="Du">
    <div class="flex gap-2"><input type="date" name="au" value="{{ request('au') }}" class="input" title="Au"><button class="btn-secondary">OK</button></div>
</form>

<div class="card table-wrap">
    <div class="flex justify-between border-b border-slate-100 px-4 py-2.5 text-sm"><span class="font-medium">Registre</span><span class="font-semibold tabular-nums text-emerald-700">@ar($totalRegistre)</span></div>
    <table class="data">
        <thead><tr><th>Date</th><th>N° bon / réf.</th><th>Client / origine</th><th>Catégorie</th><th>Détails</th><th class="text-right">Facturé</th><th class="text-right">Encaissé</th><th>Pièce</th>@can('recettes.gerer')<th></th>@endcan</tr></thead>
        <tbody>
        @forelse ($recettes as $r)
            @php $reste = $r->montant_facture ? $r->montant_facture - $r->montant : 0; @endphp
            <tr>
                <td class="whitespace-nowrap">{{ $r->date_recette->format('d/m/Y') }}</td>
                <td class="font-mono text-xs">{{ $r->numero_bon ?: $r->reference }}</td>
                <td>{{ $r->client ?: $r->origine }}@if ($r->client)<p class="text-xs text-slate-400">{{ $r->origine }}</p>@endif</td>
                <td><x-badge>{{ $r->categorie->nom }}</x-badge></td>
                <td class="text-xs text-slate-500">{{ collect([$r->espece, $r->poids_kg ? number_format($r->poids_kg, 0, ',', ' ') . ' kg' : null, $r->destination, $r->note])->filter()->implode(' · ') }}</td>
                <td class="text-right tabular-nums">{{ $r->montant_facture ? \App\Support\Fmt::ar($r->montant_facture) : '—' }}</td>
                <td class="text-right font-medium tabular-nums">@ar($r->montant)@if ($reste > 0)<p class="text-xs font-normal text-red-600">reste @ar($reste)</p>@endif</td>
                <td>@foreach ($r->pieces as $p)<a href="{{ route('comptabilite.pieces.show', $p) }}" class="text-brand" title="{{ $p->nom_original }}"><x-icon name="paperclip" class="h-4 w-4" /></a>@endforeach</td>
                @can('recettes.gerer')
                <td class="text-right">
                    <form method="POST" action="{{ route('comptabilite.recettes.destroy', $r) }}" data-confirm="Supprimer cette recette ?">@csrf @method('DELETE')
                        <x-icon-btn icon="trash" tone="danger" title="Supprimer" /></form>
                </td>
                @endcan
            </tr>
        @empty
            <x-empty :cols="9" message="Aucune recette enregistrée pour ces critères." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $recettes->links() }}</div>

@if ($creances->isNotEmpty())
<h2 class="mb-2 mt-6 text-sm font-semibold text-slate-700">Créances en cours (lots non soldés)</h2>
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>N° bon</th><th>Client</th><th>Catégorie</th><th class="text-right">Facturé</th><th class="text-right">Encaissé</th><th class="text-right">Reste dû</th><th>Ancienneté</th></tr></thead>
        <tbody>
        @foreach ($creances->load('categorie') as $r)
            <tr>
                <td class="font-mono text-xs">{{ $r->numero_bon ?: $r->reference }}</td><td>{{ $r->client ?: $r->origine }}</td><td>{{ $r->categorie->nom }}</td>
                <td class="text-right tabular-nums">@ar($r->montant_facture)</td><td class="text-right tabular-nums">@ar($r->montant)</td>
                <td class="text-right font-medium tabular-nums text-red-700">@ar($r->montant_facture - $r->montant)</td>
                <td class="text-xs text-slate-500">{{ $r->date_recette->diffInDays(today()) }} j</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection

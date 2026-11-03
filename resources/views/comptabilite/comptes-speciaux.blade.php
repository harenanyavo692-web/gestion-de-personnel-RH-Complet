@extends('layouts.app')
@section('title', 'Clients spéciaux')

@section('content')
<x-page-header add="form-ajout" add-label="Nouveau dossier" title="Clients spéciaux" subtitle="Tarifs et paiements négociés — chaque versement alimente la recette générale" />
@include('comptabilite._onglets-recettes')

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Reste dû" :value="\App\Support\Fmt::ar($du)" tone="danger" />
    <x-kpi label="Encaissé" :value="\App\Support\Fmt::ar($paye)" tone="success" />
</div>

@can('recettes.gerer')
<details id="form-ajout" class="form-modal card mb-4" @if ($errors->any()) open @endif>
    <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium text-brand"><x-icon name="plus" class="h-4 w-4" />Ouvrir un dossier</summary>
    <form method="POST" action="{{ route('comptabilite.speciaux.store') }}" class="grid gap-4 border-t border-slate-100 p-4 sm:grid-cols-4">
        @csrf
        <x-field name="client" label="Client spécial (fiche ⭐ requise)" type="select" required><option value="">—</option>@foreach ($clients as $n)<option @selected(old("client") === $n)>{{ $n }}</option>@endforeach</x-field>
        <x-field name="motif" label="Motif (tarif négocié, échelonnement…)" class="sm:col-span-2" required />
        <x-field name="montant_convenu" label="Montant convenu (Ar)" type="number" min="1" step="1" required />
        <div class="sm:col-span-4 flex justify-end"><button class="btn-primary">Ouvrir le dossier</button></div>
    </form>
</details>
@endcan

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Dossier</th><th>Client</th><th class="text-right">Convenu</th><th class="text-right">Payé</th><th>Statut</th>@can('recettes.gerer')<th>Encaisser</th>@endcan</tr></thead>
        <tbody>
        @forelse ($comptes as $c)
            <tr>
                <td class="font-mono text-xs">{{ $c->reference }}</td>
                <td>{{ $c->client }}<p class="text-xs text-slate-400">{{ $c->motif }}</p></td>
                <td class="text-right tabular-nums">@ar($c->montant_convenu)</td>
                <td class="text-right tabular-nums">@ar($c->paye)</td>
                <td><x-badge :tone="$c->reste <= 0 ? 'success' : ($c->paye > 0 ? 'warning' : 'danger')">{{ $c->reste <= 0 ? 'Soldé' : ($c->paye > 0 ? 'Partiel' : 'Impayé') }}</x-badge></td>
                @can('recettes.gerer')
                <td>
                    @if ($c->reste > 0)
                    <form method="POST" action="{{ route('comptabilite.speciaux.versement', $c) }}" class="flex gap-2">
                        @csrf
                        <input name="montant" type="number" min="1" max="{{ $c->reste }}" step="1" required class="input w-28 py-1.5 text-xs" placeholder="Montant">
                        <button class="btn-primary btn-sm">OK</button>
                    </form>
                    @endif
                </td>
                @endcan
            </tr>
        @empty
            <x-empty :cols="6" message="Aucun dossier de client spécial." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $comptes->links() }}</div>
@endsection

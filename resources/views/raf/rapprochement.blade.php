@extends('layouts.app')
@section('title', 'Rapprochement Trésor')

@section('content')
<x-page-header title="Rapprochement Trésor" subtitle="Comparer le solde de nos livres au solde communiqué par le Trésor" add="form-ajout" add-label="Nouveau rapprochement" />
@include('comptabilite._onglets-rapports')

<form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-3">
    <x-field name="arrete" label="Date d'arrêté" type="date" :value="$arrete->toDateString()" max="{{ now()->toDateString() }}" />
    <button class="btn-secondary"><x-icon name="undo" class="h-4 w-4" />Recalculer</button>
    <p class="ml-auto text-sm text-slate-500">Solde selon nos livres au {{ $arrete->format('d/m/Y') }} : <strong class="text-lg text-slate-900 tabular-nums">@ar($soldeLivres)</strong></p>
</form>

<details id="form-ajout" class="form-modal card mb-4" @if ($errors->any()) open @endif>
    <summary>Nouveau rapprochement Trésor</summary>
    <form method="POST" action="{{ route('raf.rapprochements.store') }}" class="space-y-4 p-5">
        @csrf
        <input type="hidden" name="date_arrete" value="{{ $arrete->toDateString() }}">
        <p class="text-sm text-slate-500">Date d'arrêté : <strong>{{ $arrete->format('d/m/Y') }}</strong> · Solde selon nos livres : <strong class="tabular-nums">@ar($soldeLivres)</strong></p>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="periode" label="Période" placeholder="Ex : Octobre 2026" required />
            <x-field name="solde_tresor" label="Solde communiqué par le Trésor (Ar)" type="number" step="0.01" required />
            <div class="self-end"><button type="button" id="ecart-add" class="btn-secondary w-full"><x-icon name="plus" class="h-4 w-4" />Ajouter un écart</button></div>
        </div>

        <div id="ecarts-list" class="space-y-2"></div>
        <template id="ecart-template">
            <div data-ecart-row class="grid grid-cols-12 gap-2">
                <input name="ecarts[__i__][libelle]" class="input col-span-7" placeholder="Libellé (ex : versement en transit)">
                <input name="ecarts[__i__][montant]" type="number" step="0.01" class="input col-span-4" placeholder="+ ou − Ar">
                <button type="button" data-ecart-remove class="col-span-1 text-slate-400 hover:text-red-600" aria-label="Retirer"><x-icon name="trash" class="mx-auto h-4 w-4" /></button>
            </div>
        </template>
        <div id="rapp-apercu" data-livres="{{ $soldeLivres }}" class="rounded-md bg-slate-50 p-3 text-sm"></div>
        <p class="text-xs text-slate-400">Écart final = solde livres + écarts justifiés − solde Trésor. Il doit être nul pour que le rapprochement soit complet.</p>
        <div class="flex justify-end gap-2"><button type="button" class="btn-secondary" data-modal-close>Annuler</button><button class="btn-primary"><x-icon name="check" class="h-4 w-4" />Enregistrer le rapprochement</button></div>
    </form>
</details>

<h2 class="mb-2 text-sm font-semibold text-slate-700">Historique</h2>
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Période</th><th>Arrêté</th><th class="text-right">Livres</th><th class="text-right">Trésor</th><th class="text-right">Écart final</th><th>Statut</th><th>Par</th></tr></thead>
        <tbody>
        @forelse ($historique as $r)
            <tr>
                <td class="font-medium">{{ $r->periode }}<p class="text-xs text-slate-400">{{ $r->ecarts_count }} écart(s) justifié(s)</p></td>
                <td>{{ $r->date_arrete->format('d/m/Y') }}</td>
                <td class="text-right tabular-nums">@ar($r->solde_livres)</td>
                <td class="text-right tabular-nums">@ar($r->solde_tresor)</td>
                <td class="text-right tabular-nums">@ar($r->ecart_final)</td>
                <td><x-badge :tone="$r->statut === 'rapproche' ? 'success' : 'danger'">{{ $r->statut === 'rapproche' ? 'Rapproché' : 'Écart' }}</x-badge></td>
                <td class="text-xs text-slate-500">{{ $r->auteur->name }}</td>
            </tr>
        @empty
            <x-empty :cols="7" message="Aucun rapprochement enregistré." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $historique->links() }}</div>
@endsection

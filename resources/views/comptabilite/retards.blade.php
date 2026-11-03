@extends('layouts.app')
@section('title', 'Majoration retard tunnel')

@section('content')
<x-page-header add="form-ajout" add-label="Nouveau dépassement" title="Majoration retard tunnel" subtitle="Le taux ne s'applique que si le dépassement des 24 h est imputé au client ; une faute de l'usine n'est pas majorée mais reste enregistrée" />
@include('comptabilite._onglets-tarifs')

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Dépassements 24 h" :value="$nbTotal" />
    <x-kpi label="Faute client (majoré)" :value="$nbClient" tone="danger" />
    <x-kpi label="Faute usine (non facturé)" :value="$nbUsine" tone="warning" />
    <x-kpi label="Majorations facturées" :value="\App\Support\Fmt::ar($penalites)" />
</div>

@can('majoration.gerer')
<details id="form-ajout" class="form-modal card mb-4" @if ($errors->any()) open @endif>
    <summary>Enregistrer un dépassement de 24 h</summary>
    <form method="POST" action="{{ route('comptabilite.retards.store') }}" class="grid gap-4 p-5 sm:grid-cols-2">
        @csrf
        <x-field name="lot" label="N° de lot" required />
        <x-field name="client" label="Client" required />
        <x-field name="poids_kg" label="Poids d'entrée (kg)" type="number" step="0.01" min="0" required />
        <x-field name="heures_tunnel" label="Heures en tunnel (> 24)" type="number" min="25" step="1" required />
        <x-field name="responsabilite" label="Responsabilité du dépassement" type="select" required class="sm:col-span-2">
            <option value="client" @selected(old('responsabilite') === 'client')>Client (majoration appliquée)</option>
            <option value="usine" @selected(old('responsabilite') === 'usine')>Usine CDPHM — panne, chambre saturée… (sans majoration)</option>
        </x-field>
        <div class="sm:col-span-2 flex justify-end gap-2"><button type="button" class="btn-secondary" data-modal-close>Annuler</button><button class="btn-primary"><x-icon name="plus" class="h-4 w-4" />Calculer et enregistrer</button></div>
    </form>
</details>
@endcan
<div class="mb-4 grid gap-4 lg:grid-cols-3">
    <form method="POST" action="{{ route('comptabilite.retards.taux') }}" class="card card-pad space-y-3">
        @csrf
        <h2 class="text-sm font-semibold">Taux de majoration</h2>
        <div class="flex items-center gap-2">
            <input name="taux" type="number" min="0" max="500" step="1" value="{{ old('taux', $taux) }}" class="input w-24" @cannot('majoration.gerer') disabled @endcannot> <span>%</span>
            @can('majoration.gerer')<button class="btn-primary">Enregistrer</button>@endcan
        </div>
        <p class="text-xs text-slate-500">Tarif tunnel de référence : {{ number_format($tarifTunnel, 0, ',', ' ') }} Ar/kg/jour.</p>
    </form>

</div>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Lot</th><th>Client</th><th class="text-right">Poids</th><th>Heures</th><th>Responsabilité</th><th class="text-right">Montant tunnel</th><th class="text-right">Dont majoration</th></tr></thead>
        <tbody>
        @forelse ($retards as $r)
            <tr>
                <td class="font-mono text-xs">{{ $r->lot }}</td><td>{{ $r->client }}</td>
                <td class="text-right tabular-nums">{{ number_format($r->poids_kg, 0, ',', ' ') }} kg</td><td>{{ $r->heures_tunnel }} h</td>
                <td><x-badge :tone="$r->responsabilite === 'client' ? 'danger' : 'warning'">{{ $r->responsabilite === 'client' ? 'Faute client +' . rtrim(rtrim($r->taux_applique, '0'), '.') . ' %' : 'Faute usine' }}</x-badge></td>
                <td class="text-right tabular-nums">@ar($r->montant_tunnel)</td><td class="text-right tabular-nums">@ar($r->penalite)</td>
            </tr>
        @empty
            <x-empty :cols="7" message="Aucun dépassement de 24 h enregistré." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $retards->links() }}</div>
@endsection

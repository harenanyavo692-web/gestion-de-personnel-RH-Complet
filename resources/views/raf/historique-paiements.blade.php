@extends('layouts.app')
@section('title', 'Historique des paiements')

@section('content')
<x-page-header title="Personnel & salaires" subtitle="Historique des salaires et des heures supplémentaires payés">
    <a href="{{ route('raf.salaires.etat') }}" class="btn-secondary" target="_blank"><x-icon name="print" class="h-4 w-4" />État de paie du mois</a>
</x-page-header>
@include('raf._onglets-personnel')

@php $filtre = request()->filled('h_employe') || request()->filled('h_mois'); @endphp
<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Paiements {{ $filtre ? '(filtrés)' : '' }}" :value="number_format($historique->total(), 0, ',', ' ')" tone="info" />
    <x-kpi label="Total payé {{ $filtre ? '(filtré)' : '' }}" :value="\App\Support\Fmt::ar($historiqueTotal)" tone="success" />
</div>

<form method="GET" action="{{ route('raf.salaires.historique') }}" class="card card-pad mb-4 grid gap-3 sm:grid-cols-3">
    <select name="h_employe" class="input" onchange="this.form.submit()">
        <option value="">Tous les employés</option>
        @foreach ($historiqueEmployes as $emp)<option value="{{ $emp->id }}" @selected((int) request('h_employe') === $emp->id)>{{ $emp->nom_complet }}</option>@endforeach
    </select>
    <input type="month" name="h_mois" value="{{ request('h_mois') }}" class="input" title="Mois payé" onchange="this.form.submit()">
    <div class="flex items-center gap-3">
        <button class="btn-secondary">Filtrer</button>
        @if ($filtre)<a href="{{ route('raf.salaires.historique') }}" class="text-sm text-brand hover:underline">Réinitialiser</a>@endif
    </div>
</form>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Date</th><th>Employé</th><th>Nature</th><th>Mois payé</th><th class="text-right">Montant</th><th>Enregistré par</th><th></th></tr></thead>
        <tbody>
        @forelse ($historique as $l)
            <tr>
                <td class="whitespace-nowrap">{{ $l['date']->format('d/m/Y') }}</td>
                <td class="font-medium">{{ $l['employe']->nom_complet }}</td>
                <td>@if ($l['type'] === 'salaire')<x-badge tone="success">Salaire</x-badge>@else<x-badge tone="info">Heures sup · {{ $l['detail'] }}</x-badge>@endif</td>
                <td class="tabular-nums text-slate-600">{{ $l['periode'] }}</td>
                <td class="text-right tabular-nums">@ar($l['montant'])</td>
                <td class="text-xs text-slate-500">{{ $l['payeur'] ?? '—' }}</td>
                <td class="text-right">@if ($l['paiement'])<a href="{{ route('raf.salaires.recu', $l['paiement']) }}" target="_blank" class="btn-secondary btn-sm"><x-icon name="print" class="h-3.5 w-3.5" />Reçu</a>@endif</td>
            </tr>
        @empty
            <x-empty :cols="7" :message="$filtre ? 'Aucun paiement pour ces critères.' : 'Aucun paiement enregistré pour le moment. Le RAF enregistre un paiement avec le bouton « Payer » de la page Salaires & heures sup.'" />
        @endforelse
        </tbody>
        @if ($historique->total() > 0)
        <tfoot><tr class="font-semibold"><td colspan="4">Total ({{ $historique->total() }} paiement{{ $historique->total() > 1 ? 's' : '' }})</td><td class="text-right tabular-nums">@ar($historiqueTotal)</td><td colspan="2"></td></tr></tfoot>
        @endif
    </table>
</div>
<div class="mt-4">{{ $historique->links() }}</div>
@endsection

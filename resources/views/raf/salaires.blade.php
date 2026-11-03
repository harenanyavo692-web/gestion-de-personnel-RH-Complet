@extends('layouts.app')
@section('title', 'Personnel & salaires')

@section('content')
<x-page-header add="form-hs" add-label="Saisir des heures sup" title="Personnel & salaires" subtitle="Paiement mensuel — un seul paiement par employé et par mois">
    <a href="{{ route('raf.salaires.historique') }}" class="btn-secondary"><x-icon name="log" class="h-4 w-4" />Historique des paiements</a>
    <a href="{{ route('raf.salaires.etat', ['mois' => $mois]) }}" class="btn-secondary" target="_blank"><x-icon name="print" class="h-4 w-4" />État de paie du mois</a>
    <form method="GET"><input type="month" name="mois" value="{{ $mois }}" onchange="this.form.submit()" class="input"></form>
</x-page-header>
@include('raf._onglets-personnel')
@include('raf._kpis')

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Déjà versé" :value="\App\Support\Fmt::ar($verse)" tone="success" />
    <x-kpi label="Reste à verser" :value="\App\Support\Fmt::ar(max(0, $masse - $verse))" tone="warning" />
    <x-kpi label="Heures sup à payer" :value="\App\Support\Fmt::ar($hsDu)" tone="warning" />
</div>

<div class="card table-wrap mb-6">
    <table class="data">
        <thead><tr><th>Employé</th><th>Poste</th><th class="text-right">Salaire</th><th>Statut {{ $mois }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($employes as $e)
            @php $paye = $e->paiementsSalaires->first(); @endphp
            <tr>
                <td class="font-medium">{{ $e->nom_complet }}</td>
                <td class="text-slate-600">{{ $e->poste }}</td>
                <td class="text-right tabular-nums">@if ($e->salaire)@ar($e->salaire)@else<span class="text-slate-400">Non défini</span>@endif</td>
                <td>@if ($paye)<x-badge tone="success">Payé le {{ $paye->date_paiement->format('d/m') }}</x-badge>@else<x-badge tone="warning">À payer</x-badge>@endif</td>
                <td class="text-right">
                    @if ($paye)
                        <a href="{{ route('raf.salaires.recu', $paye) }}" target="_blank" class="btn-secondary btn-sm"><x-icon name="print" class="h-3.5 w-3.5" />Imprimer le reçu</a>
                    @else
                        @can('salaires.payer')
                            <form method="POST" action="{{ route('raf.salaires.payer', $e) }}" data-confirm="Enregistrer le paiement du salaire de {{ $e->nom_complet }} pour {{ $mois }} ?">
                                @csrf<input type="hidden" name="mois" value="{{ $mois }}">
                                <button class="btn-primary btn-sm" @disabled(! (float) $e->salaire)><x-icon name="wallet" class="h-3.5 w-3.5" />Payer</button>
                            </form>
                        @else
                            <span class="text-xs text-slate-400" title="Seul le RAF enregistre les paiements ; le reçu s'imprime ensuite ici.">Paiement par le RAF</span>
                        @endcan
                    @endif
                </td>
            </tr>
        @empty
            <x-empty :cols="5" message="Aucun employé actif. Ajoutez du personnel dans Ressources humaines." />
        @endforelse
        </tbody>
    </table>
</div>

@can('heures_sup.saisir')
<details id="form-hs" class="form-modal card mb-4" @if ($errors->has('heures') || $errors->has('date') || $errors->has('majoration')) open @endif>
    <summary>Saisir des heures supplémentaires</summary>
    <form method="POST" id="form-hs-form" action="{{ route('raf.heures-sup.store', $employes->first() ?? 0) }}" data-action-modele="{{ route('raf.heures-sup.store', '__id__') }}" class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
        @csrf
        <x-field name="employe_hs" label="Employé" type="select" required class="lg:col-span-3">@foreach ($employes as $e)<option value="{{ $e->id }}">{{ $e->nom_complet }}{{ (float) $e->salaire ? '' : ' (salaire non défini)' }}</option>@endforeach</x-field>
        <x-field name="date" label="Date" type="date" :value="now()->toDateString()" max="{{ now()->toDateString() }}" required />
        <x-field name="heures" label="Nombre d'heures" type="number" step="0.25" min="0.25" max="12" required />
        <x-field name="majoration" label="Majoration" type="select" required><option value="30">+30 %</option><option value="50">+50 %</option><option value="100">+100 %</option></x-field>
        <x-field name="motif" label="Motif" maxlength="255" class="sm:col-span-2 lg:col-span-3" />
        <p class="text-xs text-slate-500 sm:col-span-2 lg:col-span-3">Taux horaire = salaire ÷ 173,33 h, majoré selon la tranche. Plafond : 20 h supplémentaires par semaine et par employé.</p>
        <div class="sm:col-span-2 lg:col-span-3 flex justify-end gap-2"><button type="button" class="btn-secondary" data-modal-close>Annuler</button><button class="btn-primary"><x-icon name="plus" class="h-4 w-4" />Enregistrer</button></div>
    </form>
</details>
<script>
    (function () {
        const f = document.getElementById('form-hs-form'), sel = document.getElementById('employe_hs');
        if (!f || !sel) return;
        const maj = () => { f.action = f.dataset.actionModele.replace('__id__', sel.value); };
        sel.addEventListener('change', maj); maj();
        document.addEventListener('click', (e) => { const b = e.target.closest('[data-employe]'); if (b) { sel.value = b.dataset.employe; maj(); } });
    })();
</script>
@endcan

<h2 class="mb-2 text-sm font-semibold text-slate-700">Heures supplémentaires — {{ $mois }}</h2>
<p class="mb-2 text-xs text-slate-500">Taux horaire = salaire ÷ 173,33 h, majoré de 30 %, 50 % ou 100 %. Reste à payer : <strong>@ar($hsDu)</strong></p>
<div class="card table-wrap mb-6">
    <table class="data">
        <thead><tr><th>Employé</th><th class="text-right">Heures</th><th class="text-right">À payer</th><th>Saisie</th><th></th></tr></thead>
        <tbody>
        @foreach ($employes as $e)
            @php
                $lignes = $hsParEmploye->get($e->id, collect());
                $impayees = $lignes->whereNull('date_paiement');
                $du = $impayees->sum(fn ($l) => $l->calculerMontant((float) $e->salaire));
            @endphp
            <tr>
                <td class="font-medium">{{ $e->nom_complet }}</td>
                <td class="text-right tabular-nums">{{ (float) $lignes->sum('heures') }} h</td>
                <td class="text-right tabular-nums">@if ($impayees->isNotEmpty())@ar($du)@else<span class="text-slate-400">{{ $lignes->isEmpty() ? '—' : 'Payé' }}</span>@endif</td>
                <td>@can('heures_sup.saisir')<button type="button" class="btn-secondary btn-sm" data-open="form-hs" data-employe="{{ $e->id }}"><x-icon name="plus" class="h-3.5 w-3.5" />Heures sup</button>@endcan</td>
                <td class="text-right">
                    @can('salaires.payer')
                    @if ($impayees->isNotEmpty())
                        <form method="POST" action="{{ route('raf.heures-sup.payer', $e) }}" data-confirm="Payer les heures supplémentaires de {{ $e->nom_complet }} pour {{ $mois }} ?">
                            @csrf<input type="hidden" name="mois" value="{{ $mois }}">
                            <button class="btn-primary btn-sm" @disabled(! (float) $e->salaire)><x-icon name="wallet" class="h-3.5 w-3.5" />Payer</button>
                        </form>
                    @endif
                    @endcan
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@if ($heuresSup->isNotEmpty())
<div class="card table-wrap mb-6">
    <table class="data">
        <thead><tr><th>Date</th><th>Employé</th><th class="text-right">Heures</th><th>Maj.</th><th>Motif</th><th class="text-right">Montant</th><th></th></tr></thead>
        <tbody>
        @foreach ($heuresSup as $h)
            <tr>
                <td>{{ $h->date->format('d/m/Y') }}</td>
                <td>{{ $h->employe->nom_complet }}</td>
                <td class="text-right tabular-nums">{{ (float) $h->heures }}</td>
                <td>+{{ $h->majoration }} %</td>
                <td class="text-xs text-slate-500">{{ $h->motif }}</td>
                <td class="text-right tabular-nums">@if ($h->date_paiement)@ar($h->montant)@else<x-badge tone="warning">À payer</x-badge>@endif</td>
                <td class="text-right">
                    @can('heures_sup.saisir')
                    @unless ($h->date_paiement)
                        <form method="POST" action="{{ route('raf.heures-sup.destroy', $h) }}" data-confirm="Supprimer cette ligne ?">@csrf @method('DELETE')<x-icon-btn icon="trash" tone="danger" title="Supprimer" /></form>
                    @endunless
                    @endcan
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

<section id="historique" class="scroll-mt-4">
<div class="mb-2 flex flex-wrap items-end justify-between gap-2">
    <div>
        <h2 class="text-sm font-semibold text-slate-700">Derniers paiements</h2>
        <p class="text-xs text-slate-500">Salaires et heures supplémentaires payés, du plus récent au plus ancien.</p>
    </div>
    <a href="{{ route('raf.salaires.historique') }}" class="btn-secondary btn-sm"><x-icon name="log" class="h-3.5 w-3.5" />Voir tout l'historique</a>
</div>
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Date</th><th>Employé</th><th>Nature</th><th>Mois payé</th><th class="text-right">Montant</th><th></th></tr></thead>
        <tbody>
        @forelse ($historique as $l)
            <tr>
                <td class="whitespace-nowrap">{{ $l['date']->format('d/m/Y') }}</td>
                <td class="font-medium">{{ $l['employe']->nom_complet }}</td>
                <td>@if ($l['type'] === 'salaire')<x-badge tone="success">Salaire</x-badge>@else<x-badge tone="info">Heures sup · {{ $l['detail'] }}</x-badge>@endif</td>
                <td class="tabular-nums text-slate-600">{{ $l['periode'] }}</td>
                <td class="text-right tabular-nums">@ar($l['montant'])</td>
                <td class="text-right">@if ($l['paiement'])<a href="{{ route('raf.salaires.recu', $l['paiement']) }}" target="_blank" class="btn-secondary btn-sm"><x-icon name="print" class="h-3.5 w-3.5" />Reçu</a>@endif</td>
            </tr>
        @empty
            <x-empty :cols="6" message="Aucun paiement enregistré pour le moment." />
        @endforelse
        </tbody>
    </table>
</div>
</section>
@endsection

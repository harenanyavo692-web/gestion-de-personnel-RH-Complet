@extends('layouts.app')
@section('title', 'Régie')

@section('content')
@php
    $tones = ['quittance_emise' => 'info', 'verse' => 'warning', 'recu_obtenu' => 'info', 'transmis_raf' => 'warning', 'cloture' => 'success'];
    $aEmettre = $dossiers->getCollection()->where('statut', 'transmis_raf');
@endphp
<x-page-header title="Régie & versement au Trésor" subtitle="Journal du jour → quittance → versement → reçu Trésor → ordre de versement">
    @can('regie.gerer')
    <form method="POST" action="{{ route('comptabilite.regie.cloturer') }}" data-confirm="Clôturer la journée et générer la quittance de régie ?">
        @csrf<button class="btn-primary" @disabled($dejaCloture || $journal['total'] <= 0)>{{ $dejaCloture ? "Journée déjà clôturée" : 'Clôturer la journée' }}</button>
    </form>
    @endcan
</x-page-header>

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Tunnel (aujourd'hui)" :value="\App\Support\Fmt::ar($journal['tunnel'])" />
    <x-kpi label="Chambre froide" :value="\App\Support\Fmt::ar($journal['froid'])" />
    <x-kpi label="Transport" :value="\App\Support\Fmt::ar($journal['transport'])" />
    <x-kpi label="Total à verser" :value="\App\Support\Fmt::ar($journal['total'])" tone="success" />
</div>

<div class="card table-wrap mb-4">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
        <div>
            <h2 class="text-sm font-semibold">Journal détaillé — feuille de caisse</h2>
            <p class="text-xs text-slate-500">Toutes les opérations encaissées du jour (tunnel, chambre froide, transport, clients spéciaux), à contrôler avant la clôture.</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <input type="date" name="date" value="{{ $date }}" max="{{ today()->toDateString() }}" onchange="this.form.submit()" class="input py-1">
            <a href="{{ route('comptabilite.regie.export', ['date' => $date]) }}" class="btn-secondary btn-sm">Exporter CSV</a>
        </form>
    </div>
    <table class="data">
        <thead><tr><th>N°</th><th>Heure</th><th>Type</th><th>Référence</th><th>Client</th><th class="text-right">Montant</th></tr></thead>
        <tbody>
        @forelse ($lignes as $i => $r)
            <tr><td class="text-xs text-slate-400">{{ $i + 1 }}</td><td class="text-xs">{{ $r->created_at?->format('H:i') }}</td><td>{{ $r->categorie->nom }}</td>
                <td class="font-mono text-xs">{{ $r->numero_bon ?: $r->reference }}</td><td>{{ $r->client ?: '—' }}</td><td class="text-right tabular-nums">@ar($r->montant)</td></tr>
        @empty
            <x-empty :cols="6" message="Aucun encaissement ce jour-là." />
        @endforelse
        </tbody>
        @if ($lignes->isNotEmpty())<tfoot><tr class="font-semibold"><td colspan="5" class="text-right">Total</td><td class="text-right tabular-nums">@ar($lignes->sum('montant'))</td></tr></tfoot>@endif
    </table>
</div>

@if ($aEmettre->isNotEmpty())
<div class="card card-pad mb-4">
    <h2 class="mb-1 text-sm font-semibold">Dossiers en attente d'ordre de versement</h2>
    <p class="mb-3 text-xs text-slate-500">Vérifiez le reçu du Trésor puis émettez l'ordre de versement pour clôturer et comptabiliser l'opération.</p>
    @foreach ($aEmettre as $d)
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 py-2 text-sm first:border-0">
            <span><strong>{{ $d->reference }}</strong> · {{ $d->date_journal->format('d/m/Y') }} · reçu n° {{ $d->recu_tresor_num }} · <strong>@ar($d->montant)</strong></span>
            @can('regie.emettre')
            <form method="POST" action="{{ route('comptabilite.regie.emettre', $d) }}" data-confirm="Émettre l'ordre de versement de {{ $d->reference }} ?">@csrf<button class="btn-primary btn-sm">Émettre l'ordre de versement</button></form>
            @endcan
        </div>
    @endforeach
</div>
@endif

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Dossier</th><th>Journal</th><th class="text-right">Montant</th><th>Statut</th><th>Reçu Trésor</th><th>Action</th></tr></thead>
        <tbody>
        @forelse ($dossiers as $d)
            <tr>
                <td class="font-mono text-xs">{{ $d->reference }}</td>
                <td>{{ $d->date_journal->format('d/m/Y') }}</td>
                <td class="text-right font-medium tabular-nums">@ar($d->montant)</td>
                <td><x-badge :tone="$tones[$d->statut]">{{ $d->statut_libelle }}</x-badge></td>
                <td class="text-xs">{{ $d->recu_tresor_num ?: '—' }}</td>
                <td>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-icon-btn icon="print" title="Quittance" :href="route('comptabilite.regie.quittance', $d)" />
                        @if ($d->statut === 'cloture')<x-icon-btn icon="doc" title="Ordre de versement" :href="route('comptabilite.regie.ordre', $d)" />@endif
                        @can('regie.gerer')
                            @if ($d->statut === 'quittance_emise')
                                <form method="POST" action="{{ route('comptabilite.regie.verser', $d) }}" data-confirm="Confirmer le versement physique de {{ number_format($d->montant, 0, ',', ' ') }} Ar au Trésor ?">@csrf<button class="btn-primary btn-sm">Versement effectué</button></form>
                            @elseif ($d->statut === 'verse')
                                <form method="POST" action="{{ route('comptabilite.regie.recu', $d) }}" class="flex gap-1">@csrf
                                    <input name="recu_tresor_num" required maxlength="60" placeholder="N° du reçu" class="input w-32 py-1 text-xs"><button class="btn-primary btn-sm">Enregistrer</button></form>
                            @elseif ($d->statut === 'recu_obtenu')
                                <form method="POST" action="{{ route('comptabilite.regie.transmettre', $d) }}">@csrf<button class="btn-primary btn-sm">Transmettre à la RAF</button></form>
                            @endif
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <x-empty :cols="6" message="Aucun dossier de régie pour l'instant." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $dossiers->links() }}</div>
@endsection

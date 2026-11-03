@extends('layouts.app')
@section('title', 'Caisse & flux expéditions')

@section('content')
@php
    $peutSpeciaux = auth()->user()->canAny(['recettes.gerer', 'lots.encaisser']);
    $types = ['tunnel' => ['Tunnel', 'info', 'box'], 'froid' => ['Chambre froide', 'info', 'building'], 'transport' => ['Transport', 'warning', 'truck']];
    $sections = [
        'encaisser' => 'À encaisser (' . $afaire->count() . ')',
        'journal' => 'Journal du jour',
        'speciaux' => 'Clients spéciaux',
        'fiche' => 'Fiche client',
    ];
@endphp
<x-page-header title="Caisse & flux expéditions" subtitle="Encaissez les prestations préparées par le quai, l'usine froid et la logistique" icon="wallet"
    :add="$section === 'speciaux' && $peutSpeciaux ? 'form-ajout' : null" add-label="Nouveau dossier">
    <a href="{{ route('comptabilite.regie.index') }}" class="btn-secondary"><x-icon name="bank" class="h-4 w-4" />Régie & versement</a>
</x-page-header>

{{-- Parcours d'un lot : où se situe la caisse --}}
<div class="card card-pad mb-4">
    <ol class="grid gap-3 text-sm sm:grid-cols-4">
        @foreach ([
            ['1', 'Quai', "Le CDD enregistre le lot (bon d'entrée)", 'lots.voir', 'exploitation.lots.index'],
            ['2', 'Usine froid', 'Le magasinier transfère en chambre froide', 'lots.voir', 'exploitation.lots.index'],
            ['3', 'Caisse', 'Vous encaissez la facture ci-dessous', null, null],
            ['4', 'Régie', 'La régisseuse clôture la journée et verse au Trésor', 'regie.voir', 'comptabilite.regie.index'],
        ] as [$n, $titre, $texte, $perm, $route])
            @php $etapeActive = $n === '3'; @endphp
            <li class="flex items-start gap-3 rounded-xl p-3 {{ $etapeActive ? 'ring-2 ring-[var(--brand)]' : '' }}" style="background: var(--surface-2)">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $etapeActive ? 'text-white' : '' }}" style="{{ $etapeActive ? 'background: var(--brand)' : 'background: var(--surface); color: var(--muted); border: 1px solid var(--line)' }}">{{ $n }}</span>
                <div class="min-w-0">
                    <p class="font-semibold">@if ($route && $perm && auth()->user()->can($perm))<a href="{{ route($route) }}" class="hover:underline">{{ $titre }}</a>@else{{ $titre }}@endif</p>
                    <p class="text-xs" style="color: var(--muted)">{{ $texte }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</div>

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Reste à encaisser" :value="\App\Support\Fmt::ar($totalAfaire)" tone="warning" :hint="$afaire->count() . ' opération(s)'" />
    <x-kpi :label="'Encaissé (' . \Carbon\Carbon::parse($date)->format('d/m') . ')'" :value="\App\Support\Fmt::ar($totalJour)" tone="success" :hint="$lignes->count() . ' encaissement(s)'" />
    <x-kpi label="Clients spéciaux — dû" :value="\App\Support\Fmt::ar($speciauxDu)" tone="danger" />
    <x-kpi label="Clients spéciaux — encaissé" :value="\App\Support\Fmt::ar($speciauxPaye)" tone="success" />
</div>

<x-tabs :sections="$sections" :actif="$section" route="caisse.index" />

@if ($section === 'encaisser')
<form method="GET" class="card card-pad mb-4 flex flex-wrap items-center gap-3">
    <input type="hidden" name="section" value="encaisser">
    <input type="search" name="q" value="{{ $q }}" placeholder="Rechercher un client ou une référence…" class="input max-w-sm">
    <button class="btn-secondary">Rechercher</button>
    @if ($q)<a href="{{ route('caisse.index') }}" class="text-sm text-brand hover:underline">Effacer</a>@endif
</form>
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Type</th><th>Référence</th><th>Client</th><th>Détail</th><th class="text-right">Montant</th><th class="text-right">Action</th></tr></thead>
        <tbody>
        @forelse ($afaire as $a)
            @php [$libelle, $ton, $icone] = $types[$a['type']]; @endphp
            <tr>
                <td><span class="inline-flex items-center gap-2"><x-icon :name="$icone" class="h-4 w-4 text-slate-400" /><x-badge :tone="$ton">{{ $libelle }}</x-badge></span></td>
                <td class="font-mono text-xs">{{ $a['ref'] }}<p class="font-sans text-slate-400">{{ $a['date']->format('d/m/Y') }}</p></td>
                <td>
                    @if ($a['client_id'])<a href="{{ route('caisse.index', ['section' => 'fiche', 'client_id' => $a['client_id']]) }}" class="font-medium hover:underline" title="Voir la fiche de vigilance">{{ $a['client'] }}</a>@else<span class="font-medium">{{ $a['client'] }}</span>@endif
                    @if ($a['special'])<x-badge tone="info">⭐ Spécial</x-badge>@endif
                </td>
                <td class="text-xs text-slate-500">{{ $a['detail'] }}</td>
                <td class="text-right text-base font-semibold tabular-nums">@ar($a['montant'])</td>
                <td class="text-right">
                    @can($a['perm'])
                    <form method="POST" action="{{ $a['url'] }}" data-confirm="{{ $a['confirm'] }}">@csrf<button class="btn-primary btn-sm"><x-icon name="check" class="h-3.5 w-3.5" />Encaisser</button></form>
                    @else<span class="text-xs text-slate-400">Réservé à la caisse</span>@endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-12 text-center">
                <div class="mx-auto flex max-w-md flex-col items-center gap-2">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><x-icon name="check" class="h-5 w-5" /></span>
                    <p class="font-medium">{{ $q ? 'Aucun résultat pour cette recherche.' : 'Rien à encaisser pour le moment.' }}</p>
                    @unless ($q)<p class="text-sm" style="color: var(--muted)">Les factures apparaissent ici dès qu'un lot est enregistré au quai ou qu'une mission de transport est préparée.</p>@endunless
                    <div class="mt-2 flex gap-2">
                        @can('lots.voir')<a href="{{ route('exploitation.lots.index') }}" class="btn-secondary btn-sm"><x-icon name="box" class="h-3.5 w-3.5" />Lots & stock</a>@endcan
                        @can('transport.voir')<a href="{{ route('exploitation.transport.index') }}" class="btn-secondary btn-sm"><x-icon name="truck" class="h-3.5 w-3.5" />Transport</a>@endcan
                    </div>
                </div>
            </td></tr>
        @endforelse
        </tbody>
        @if ($afaire->isNotEmpty())<tfoot><tr class="font-semibold"><td colspan="4" class="text-right">Total à encaisser</td><td class="text-right tabular-nums">@ar($totalAfaire)</td><td></td></tr></tfoot>@endif
    </table>
</div>

@elseif ($section === 'journal')
<div class="card table-wrap">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3" style="border-color: var(--line)">
        <div><h2 class="text-sm font-semibold">Journal de caisse</h2><p class="text-xs text-slate-500">Tout ce qui a été encaissé à la date choisie (tunnel, chambre froide, transport, clients spéciaux).</p></div>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="section" value="journal">
            <input type="date" name="date" value="{{ $date }}" max="{{ today()->toDateString() }}" onchange="this.form.submit()" class="input py-1">
            <a href="{{ route('caisse.export', ['date' => $date]) }}" class="btn-secondary btn-sm"><x-icon name="download" class="h-3.5 w-3.5" />Exporter CSV</a>
        </form>
    </div>
    <table class="data">
        <thead><tr><th>Heure</th><th>Type</th><th>Référence</th><th>Client</th><th class="text-right">Montant</th></tr></thead>
        <tbody>
        @forelse ($lignes as $r)
            <tr><td class="text-xs">{{ $r->created_at?->format('H:i') }}</td><td><x-badge>{{ $r->categorie->nom }}</x-badge></td><td class="font-mono text-xs">{{ $r->numero_bon ?: $r->reference }}</td><td>{{ $r->client ?: '—' }}</td><td class="text-right tabular-nums">@ar($r->montant)</td></tr>
        @empty
            <x-empty :cols="5" icon="receipt" message="Aucun encaissement ce jour-là." />
        @endforelse
        </tbody>
        @if ($lignes->isNotEmpty())<tfoot><tr class="font-semibold"><td colspan="4" class="text-right">Total</td><td class="text-right tabular-nums">@ar($totalJour)</td></tr></tfoot>@endif
    </table>
</div>

@elseif ($section === 'speciaux')
@if ($peutSpeciaux)
<details id="form-ajout" class="form-modal card mb-4" @if ($errors->any()) open @endif>
    <summary>Nouveau dossier client spécial</summary>
    <form method="POST" action="{{ route('comptabilite.speciaux.store') }}" class="grid gap-4 p-5 sm:grid-cols-4">
        @csrf
        <x-field name="client" label="Client spécial (fiche ⭐ requise)" type="select" required><option value="">—</option>@foreach ($speciaux as $n)<option @selected(old('client') === $n)>{{ $n }}</option>@endforeach</x-field>
        <x-field name="motif" label="Motif (tarif négocié, échelonnement…)" class="sm:col-span-2" required />
        <x-field name="montant_convenu" label="Montant convenu (Ar)" type="number" min="1" step="1" required />
        <div class="sm:col-span-4 flex justify-end"><button class="btn-primary"><x-icon name="plus" class="h-4 w-4" />Ouvrir le dossier</button></div>
    </form>
</details>
@endif
<p class="mb-3 text-sm" style="color: var(--muted)">Chaque paiement reçu, même partiel, entre dans la recette générale ; le solde reste dû par le client.</p>
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Dossier</th><th>Client</th><th class="text-right">Convenu</th><th class="text-right">Payé</th><th>Statut</th>@if ($peutSpeciaux)<th class="text-right">Encaisser</th>@endif</tr></thead>
        <tbody>
        @forelse ($comptes as $cs)
            <tr>
                <td class="font-mono text-xs">{{ $cs->reference }}</td><td class="font-medium">{{ $cs->client }}<p class="text-xs font-normal text-slate-400">{{ $cs->motif }}</p></td>
                <td class="text-right tabular-nums">@ar($cs->montant_convenu)</td><td class="text-right tabular-nums">@ar($cs->paye)</td>
                <td><x-badge :tone="$cs->reste <= 0 ? 'success' : ($cs->paye > 0 ? 'warning' : 'danger')">{{ $cs->reste <= 0 ? 'Soldé' : ($cs->paye > 0 ? 'Partiel' : 'Impayé') }}</x-badge></td>
                @if ($peutSpeciaux)
                <td class="text-right">
                    @if ($cs->reste > 0)
                    <details class="pop">
                        <summary class="btn-primary btn-sm"><x-icon name="wallet" class="h-3.5 w-3.5" />Encaisser</summary>
                        <form method="POST" action="{{ route('comptabilite.speciaux.versement', $cs) }}" class="pop-panel space-y-2">@csrf
                            <p class="text-sm font-semibold">Versement — {{ $cs->client }}</p>
                            <p class="text-xs text-slate-500">Reste dû : <strong>@ar($cs->reste)</strong></p>
                            <x-field name="montant" label="Montant reçu (Ar)" type="number" min="1" max="{{ $cs->reste }}" step="1" required />
                            <button class="btn-primary btn-sm w-full">Enregistrer le versement</button>
                        </form>
                    </details>
                    @endif
                </td>
                @endif
            </tr>
        @empty
            <x-empty :cols="6" icon="star" message="Aucun dossier de client spécial." />
        @endforelse
        </tbody>
    </table>
</div>

@else
<div class="card card-pad">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div><h2 class="text-sm font-semibold">Fiche de vigilance client</h2><p class="text-xs text-slate-500">Avant d'encaisser, vérifiez la créance du client et ses lots non soldés.</p></div>
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="section" value="fiche">
            <select name="client_id" class="input py-1" onchange="this.form.submit()">
                <option value="">— Choisir un client —</option>
                @foreach ($clients as $c)<option value="{{ $c->id }}" @selected((int) request('client_id') === $c->id)>{{ $c->nom }}</option>@endforeach
            </select>
        </form>
    </div>
    @if ($vigilance)
        @php $c = $vigilance['client']; @endphp
        <div class="grid gap-3 text-sm sm:grid-cols-4">
            <div><p class="text-xs text-slate-500">Client</p><p class="font-medium">{{ $c->nom }} @if ($c->special)<x-badge tone="info">⭐ Spécial</x-badge>@endif</p><p class="text-xs text-slate-500">{{ $c->contact }}</p></div>
            <div><p class="text-xs text-slate-500">Créance (lots non soldés)</p><p class="text-lg font-semibold {{ $vigilance['creance'] > 0 ? 'text-red-700' : '' }}">@ar($vigilance['creance'])</p></div>
            <div><p class="text-xs text-slate-500">Dossiers négociés restants</p><p class="text-lg font-semibold">@ar($vigilance['speciauxReste'])</p></div>
            <div><p class="text-xs text-slate-500">Dernière entrée</p><p class="text-lg font-semibold">{{ $vigilance['derniere'] ? \Carbon\Carbon::parse($vigilance['derniere'])->format('d/m/Y') : '—' }}</p></div>
        </div>
        @if ($c->conditions)<p class="mt-3 text-xs text-slate-500">Conditions : {{ $c->conditions }}</p>@endif
        <p class="mt-3 text-sm {{ $vigilance['lots']->isEmpty() ? 'text-emerald-600' : 'text-slate-600' }}">{{ $vigilance['lots']->isNotEmpty() ? 'Lots non soldés : ' . $vigilance['lots']->pluck('reference')->implode(', ') : 'Aucune créance en cours.' }}</p>
    @else
        <p class="py-6 text-center text-sm text-slate-400">Choisissez un client — ou cliquez sur son nom dans l'onglet « À encaisser ».</p>
    @endif
</div>
@endif
@endsection

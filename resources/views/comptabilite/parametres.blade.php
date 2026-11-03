@extends('layouts.app')
@section('title', 'Paramètres comptabilité')

@section('content')
@php
    $gerer = auth()->user()->can('compta.parametres.gerer');
    $statutEx = ['ouvert' => 'success', 'cloture' => 'neutral'];
@endphp
<x-page-header title="Paramètres comptabilité" subtitle="Plan comptable, journaux, exercices et règles de validation">
    <form method="GET" class="flex gap-2">
        <input type="hidden" name="section" value="{{ $section }}">
        <input type="search" name="q" value="{{ $q }}" placeholder="Rechercher dans cette section…" class="input w-56">
        <button class="btn-secondary">Rechercher</button>
    </form>
</x-page-header>
@include('comptabilite._onglets-budgets')
<x-tabs :sections="$sections" :actif="$section" route="comptabilite.parametres.index" />

@if ($section === 'general')
<form method="POST" action="{{ route('comptabilite.parametres.update') }}" class="card card-pad grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
    @csrf @method('PUT')
    <input type="hidden" name="section" value="general">
    <x-field name="devise_code" label="Devise (code ISO)" :value="$general['devise_code']" maxlength="3" required />
    <x-field name="devise_symbole" label="Symbole affiché" :value="$general['devise_symbole']" maxlength="6" required />
    <x-field name="decimales" label="Décimales des montants" type="select" required>
        @foreach ([0, 1, 2] as $n)<option value="{{ $n }}" @selected((int) old('decimales', $general['decimales']) === $n)>{{ $n }}</option>@endforeach
    </x-field>
    <div class="sm:col-span-3 lg:col-span-4">
        <p class="label">Préfixes de numérotation des pièces <span class="font-normal text-slate-400">(ex : REC-261102-AB12C)</span></p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (['REC' => 'Recettes', 'OD' => 'Ordres de dépense', 'REG' => 'Régie', 'CS' => 'Clients spéciaux', 'LOT' => 'Lots', 'SP' => 'Sorties partielles', 'TR' => 'Missions transport'] as $k => $l)
                <div><label class="label" for="pf-{{ $k }}">{{ $l }}</label><input id="pf-{{ $k }}" name="prefixes[{{ $k }}]" value="{{ old("prefixes.$k", $general['prefixes'][$k] ?? $k) }}" maxlength="8" required class="input uppercase" @disabled(! $gerer)></div>
            @endforeach
        </div>
        @error('prefixes.*')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    @if ($gerer)<div class="sm:col-span-3 lg:col-span-4 flex justify-end gap-2"><a href="{{ route('comptabilite.parametres.index', ['section' => 'general']) }}" class="btn-secondary">Annuler</a><button class="btn-primary">Enregistrer</button></div>@endif
</form>

@elseif ($section === 'plan')
<div class="card table-wrap">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
        <h3 class="text-sm font-semibold">Comptes comptables</h3>
        <form method="GET" class="flex gap-2"><input type="hidden" name="section" value="plan"><input type="hidden" name="q" value="{{ $q }}">
            <select name="classe" class="input w-36 py-1.5" onchange="this.form.submit()"><option value="">Toutes classes</option>@foreach (range(1, 8) as $c)<option value="{{ $c }}" @selected((int) request('classe') === $c)>Classe {{ $c }}</option>@endforeach</select></form>
    </div>
    <table class="data">
        <thead><tr><th>N°</th><th>Libellé</th><th>Type</th><th>Actif</th>@if ($gerer)<th></th>@endif</tr></thead>
        <tbody>
        @forelse ($comptes as $c)
            <tr class="{{ $c->actif ? '' : 'opacity-60' }}">
                <td class="font-mono text-xs font-medium">{{ $c->numero }}</td>
                @if ($gerer)
                <td><input form="cp-{{ $c->id }}" name="libelle" value="{{ $c->libelle }}" required class="input py-1"></td>
                <td><select form="cp-{{ $c->id }}" name="type" class="input py-1">@foreach (\App\Models\AccountingAccount::TYPES as $k => $l)<option value="{{ $k }}" @selected($c->type === $k)>{{ $l }}</option>@endforeach</select></td>
                <td><input form="cp-{{ $c->id }}" type="checkbox" name="actif" value="1" @checked($c->actif) class="rounded border-slate-300"></td>
                <td class="text-right"><button form="cp-{{ $c->id }}" class="btn-secondary btn-sm">Enregistrer</button></td>
                @else
                <td>{{ $c->libelle }}</td><td>{{ \App\Models\AccountingAccount::TYPES[$c->type] }}</td><td>{{ $c->actif ? 'Oui' : 'Non' }}</td>
                @endif
            </tr>
        @empty
            <x-empty :cols="5" message="Aucun compte. Ajoutez les comptes de votre plan comptable." />
        @endforelse
        </tbody>
    </table>
    @if ($gerer)@foreach ($comptes as $c)<form id="cp-{{ $c->id }}" method="POST" action="{{ route('comptabilite.comptes.update', $c) }}">@csrf @method('PUT')</form>@endforeach
    <form method="POST" action="{{ route('comptabilite.comptes.store') }}" class="flex flex-wrap items-end gap-2 border-t border-slate-100 p-3">
        @csrf
        <div><label class="label">N° de compte</label><input name="numero" required inputmode="numeric" pattern="[0-9]+" maxlength="20" class="input w-32 py-1.5"></div>
        <div class="min-w-[12rem] flex-1"><label class="label">Libellé</label><input name="libelle" required maxlength="150" class="input py-1.5"></div>
        <div><label class="label">Type</label><select name="type" class="input py-1.5">@foreach (\App\Models\AccountingAccount::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <button class="btn-primary btn-sm self-end py-2"><x-icon name="plus" class="h-3.5 w-3.5" />Ajouter</button>
    </form>@endif
</div>
<div class="mt-4">{{ $comptes->links() }}</div>

@elseif ($section === 'journaux')
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Code</th><th>Libellé</th><th>Type</th><th>Actif</th>@if ($gerer)<th></th>@endif</tr></thead>
        <tbody>
        @forelse ($journaux as $j)
            <tr class="{{ $j->actif ? '' : 'opacity-60' }}">
                <td class="font-mono text-xs font-medium">{{ $j->code }}</td>
                @if ($gerer)
                <td><input form="jr-{{ $j->id }}" name="libelle" value="{{ $j->libelle }}" required class="input py-1"></td>
                <td><select form="jr-{{ $j->id }}" name="type" class="input py-1">@foreach (\App\Models\AccountingJournal::TYPES as $k => $l)<option value="{{ $k }}" @selected($j->type === $k)>{{ $l }}</option>@endforeach</select></td>
                <td><input form="jr-{{ $j->id }}" type="checkbox" name="actif" value="1" @checked($j->actif) class="rounded border-slate-300"></td>
                <td class="text-right"><button form="jr-{{ $j->id }}" class="btn-secondary btn-sm">Enregistrer</button></td>
                @else
                <td>{{ $j->libelle }}</td><td>{{ \App\Models\AccountingJournal::TYPES[$j->type] }}</td><td>{{ $j->actif ? 'Oui' : 'Non' }}</td>
                @endif
            </tr>
        @empty
            <x-empty :cols="5" message="Aucun journal." />
        @endforelse
        </tbody>
    </table>
    @if ($gerer)@foreach ($journaux as $j)<form id="jr-{{ $j->id }}" method="POST" action="{{ route('comptabilite.journaux.update', $j) }}">@csrf @method('PUT')</form>@endforeach
    <form method="POST" action="{{ route('comptabilite.journaux.store') }}" class="flex flex-wrap items-end gap-2 border-t border-slate-100 p-3">
        @csrf
        <div><label class="label">Code</label><input name="code" required maxlength="10" class="input w-28 py-1.5 uppercase"></div>
        <div class="min-w-[12rem] flex-1"><label class="label">Libellé</label><input name="libelle" required maxlength="100" class="input py-1.5"></div>
        <div><label class="label">Type</label><select name="type" class="input py-1.5">@foreach (\App\Models\AccountingJournal::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <button class="btn-primary btn-sm self-end py-2"><x-icon name="plus" class="h-3.5 w-3.5" />Ajouter</button>
    </form>@endif
</div>

@elseif ($section === 'categories')
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Type</th><th>Catégorie</th><th>Actif</th>@if ($gerer)<th></th>@endif</tr></thead>
        <tbody>
        @forelse ($categories as $c)
            @php $systeme = in_array($c->nom, ['Tunnel de congélation', 'Chambre froide', 'Transport', 'Clients spéciaux'], true); @endphp
            <tr class="{{ $c->actif ? '' : 'opacity-60' }}">
                <td><x-badge :tone="$c->type === 'recette' ? 'success' : 'info'">{{ $c->type === 'recette' ? 'Recette' : 'Dépense' }}</x-badge></td>
                @if ($gerer)
                <td>@if ($systeme)<span class="font-medium">{{ $c->nom }}</span> <x-badge>automatique</x-badge>@else<input form="ct-{{ $c->id }}" name="nom" value="{{ $c->nom }}" required class="input py-1">@endif</td>
                <td><input form="ct-{{ $c->id }}" type="checkbox" name="actif" value="1" @checked($c->actif) @disabled($systeme) class="rounded border-slate-300">@if ($systeme)<input form="ct-{{ $c->id }}" type="hidden" name="actif" value="1">@endif</td>
                <td class="text-right"><button form="ct-{{ $c->id }}" class="btn-secondary btn-sm">Enregistrer</button></td>
                @else
                <td>{{ $c->nom }}</td><td>{{ $c->actif ? 'Oui' : 'Non' }}</td>
                @endif
            </tr>
        @empty
            <x-empty :cols="4" message="Aucune catégorie." />
        @endforelse
        </tbody>
    </table>
    @if ($gerer)@foreach ($categories as $c)<form id="ct-{{ $c->id }}" method="POST" action="{{ route('comptabilite.categories.update', $c) }}">@csrf @method('PUT')</form>@endforeach
    <form method="POST" action="{{ route('comptabilite.categories.store') }}" class="flex flex-wrap items-end gap-2 border-t border-slate-100 p-3">
        @csrf
        <div><label class="label">Type</label><select name="type" class="input py-1.5"><option value="recette">Recette</option><option value="depense">Dépense</option></select></div>
        <div class="min-w-[12rem] flex-1"><label class="label">Nom de la catégorie</label><input name="nom" required maxlength="120" class="input py-1.5"></div>
        <button class="btn-primary btn-sm self-end py-2"><x-icon name="plus" class="h-3.5 w-3.5" />Ajouter</button>
    </form>@endif
</div>
<p class="mt-2 text-xs text-slate-500">Les catégories « automatiques » alimentent la régie, les lots et le transport : elles ne peuvent être ni renommées ni désactivées. Désactiver une catégorie la retire des formulaires sans toucher à l'historique.</p>

@elseif ($section === 'paiements')
<div class="grid gap-4 lg:grid-cols-2">
    <x-ref-list titre="Modes de paiement" groupe="mode_paiement" :items="$modes" :peut-gerer="$gerer" />
    <x-ref-list titre="Types de pièces justificatives" groupe="type_piece" :items="$pieces" :peut-gerer="$gerer" />
</div>

@elseif ($section === 'exercices')
@if ($gerer)
<form method="POST" action="{{ route('comptabilite.exercices.store') }}" class="card card-pad mb-4 grid gap-3 sm:grid-cols-5">
    @csrf
    <x-field name="code" label="Code" placeholder="EX2027" required /><x-field name="libelle" label="Libellé" required class="sm:col-span-1" />
    <x-field name="date_debut" label="Début" type="date" required /><x-field name="date_fin" label="Fin" type="date" required />
    <div class="self-end"><button class="btn-primary w-full">Créer l'exercice</button></div>
</form>
@endif
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Code</th><th>Libellé</th><th>Période</th><th>Statut</th>@if ($gerer)<th></th>@endif</tr></thead>
        <tbody>
        @forelse ($exercices as $e)
            <tr>
                <td class="font-mono text-xs font-medium">{{ $e->code }}</td><td>{{ $e->libelle }}</td>
                <td>{{ $e->date_debut->format('d/m/Y') }} → {{ $e->date_fin->format('d/m/Y') }}</td>
                <td><x-badge :tone="$statutEx[$e->statut]">{{ $e->statut === 'ouvert' ? 'Ouvert' : 'Clôturé le ' . $e->cloture_le?->format('d/m/Y') }}</x-badge></td>
                @if ($gerer)
                <td class="text-right">
                    @if ($e->statut === 'ouvert')
                        <form method="POST" action="{{ route('comptabilite.exercices.cloturer', $e) }}" data-confirm="Clôturer l'exercice {{ $e->code }} ? Plus aucune opération ne pourra y être enregistrée.">@csrf<button class="btn-secondary btn-sm">Clôturer</button></form>
                    @else
                        <form method="POST" action="{{ route('comptabilite.exercices.rouvrir', $e) }}" data-confirm="Rouvrir l'exercice {{ $e->code }} ?">@csrf<button class="btn-secondary btn-sm">Rouvrir</button></form>
                    @endif
                </td>
                @endif
            </tr>
        @empty
            <x-empty :cols="5" message="Aucun exercice défini : les opérations ne sont soumises à aucune période." />
        @endforelse
        </tbody>
    </table>
</div>

@else
<div class="grid gap-4 lg:grid-cols-2">
    <form method="POST" action="{{ route('comptabilite.parametres.update') }}" class="card card-pad space-y-4">
        @csrf @method('PUT')
        <input type="hidden" name="section" value="securite">
        <h3 class="text-sm font-semibold">Règles de validation</h3>
        <x-field name="seuil_double_validation" label="Seuil de double validation (0 = désactivé)" type="number" min="0" step="1" :value="$securite['seuil_double_validation']" required />
        <p class="-mt-2 text-xs text-slate-500">Au-delà de ce montant, seuls les rôles disposant du droit « valider au-dessus du seuil » (direction) peuvent valider l'ordre.</p>
        <x-field name="alerte_budget_pct" label="Alerte budgétaire à (% du budget consommé)" type="number" min="1" max="100" :value="$securite['alerte_budget_pct']" required />
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="bloquer_exercice_cloture" value="1" @checked(old('bloquer_exercice_cloture', $securite['bloquer_exercice_cloture'])) @disabled(! $gerer) class="mt-0.5 rounded border-slate-300"> Interdire toute opération datée dans un exercice clôturé</label>
        <p class="text-xs text-slate-500">Toujours actifs : un ordre validé ou refusé n'est plus modifiable ; l'auteur d'un ordre ne peut pas le valider ; chaque action sensible est écrite dans le journal d'audit.</p>
        @if ($gerer)<div class="flex justify-end"><button class="btn-primary">Enregistrer</button></div>@endif
    </form>
    <div class="card table-wrap">
        <h3 class="px-4 pt-4 text-sm font-semibold">Qui peut faire quoi</h3>
        <table class="data mt-2 text-xs">
            <thead><tr><th>Rôle</th>@foreach ($matrice['perms'] as $l)<th class="text-center">{{ $l }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($matrice['roles'] as $r)
                <tr><td class="font-medium">{{ $r['libelle'] }}</td>@foreach ($matrice['perms'] as $s => $_)<td class="text-center">{!! $r['a'][$s] ? '<span class="text-emerald-600">●</span>' : '<span class="text-slate-300">–</span>' !!}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
        @can('admin.roles')<p class="px-4 py-3 text-xs text-slate-500">Modifier : <a class="text-brand hover:underline" href="{{ route('admin.roles.index') }}">Administration › Rôles et permissions</a>.</p>@endcan
    </div>
</div>
@endif
@endsection

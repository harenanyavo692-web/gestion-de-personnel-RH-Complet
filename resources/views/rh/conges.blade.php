@extends('layouts.app')
@section('title', $nature === 'conge' ? 'Congés' : 'Absences')

@section('content')
@php
    $tones = ['en_attente' => 'warning', 'validee' => 'success', 'refusee' => 'danger', 'annulee' => 'neutral'];
    $estConge = $nature === 'conge';
    $libelleEnCours = $estConge ? "En congé aujourd'hui" : "Absents aujourd'hui";
    $routeIndex = $estConge ? 'rh.conges.index' : 'rh.absences.index';
    $nomsValidateur = ['responsable' => "le responsable de l'entité", 'rh' => 'le service RH', 'direction' => 'la direction'];
@endphp
<x-page-header add="form-ajout" :add-label="$estConge ? 'Nouvelle demande' : 'Nouvelle absence'" :title="$estConge ? 'Congés' : 'Absences'" :subtitle="$workflow['validation_requise'] ? 'Validation par ' . $nomsValidateur[$workflow['validateur']] . ' · réponse sous ' . $workflow['delai_reponse_jours'] . ' j' : 'Validation automatique (désactivée dans Paramètres RH)'">
    @if ($estConge)
    <div class="inline-flex rounded-md border border-slate-300 bg-white p-0.5 text-sm">
        <a href="{{ route('rh.conges.index') }}" class="rounded px-3 py-1 {{ $vue === 'demandes' ? 'bg-brand text-white' : 'text-slate-600' }}">Demandes</a>
        <a href="{{ route('rh.conges.index', ['vue' => 'soldes']) }}" class="rounded px-3 py-1 {{ $vue === 'soldes' ? 'bg-brand text-white' : 'text-slate-600' }}">Soldes {{ $annee }}</a>
    </div>
    @endif
</x-page-header>
@include('rh._onglets-temps', ['actif' => $estConge ? 'conges' : 'absences'])

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="En attente de validation" :value="$enAttente" tone="warning" />
    <x-kpi label="En retard de réponse" :value="$enRetard" :tone="$enRetard ? 'danger' : 'neutral'" />
     <x-kpi :label="$libelleEnCours" :value="$enCours" />
</div>

@if ($vue === 'soldes')
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Employé</th>@foreach ($types->where('actif', true)->filter(fn ($t) => ($t->meta['jours_annuels'] ?? null) !== null) as $t)<th class="text-right">{{ $t->libelle }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($soldes as $s)
            <tr><td class="font-medium">{{ $s['employe']->nom_complet }}</td>
                @foreach ($s['lignes'] as $l)<td class="text-right tabular-nums {{ $l['reste'] < 0 ? 'text-red-700' : '' }}">{{ rtrim(rtrim(number_format($l['reste'], 1, ',', ''), '0'), ',') }} <span class="text-xs text-slate-400">/ {{ $l['plafond'] }}</span></td>@endforeach</tr>
        @empty
            <x-empty :cols="3" message="Aucun employé actif." />
        @endforelse
        </tbody>
    </table>
</div>
<p class="mt-2 text-xs text-slate-500">Jours restants / plafond annuel (jours ouvrés, lundi–vendredi). Les demandes en attente sont déjà décomptées. Plafonds : Paramètres RH › Congés et absences.</p>

@else
@can('conges.gerer')
<details id="form-ajout" class="form-modal card mb-4" @if ($errors->any()) open @endif>
    <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium text-brand"><x-icon name="plus" class="h-4 w-4" />{{ $estConge ? 'Nouvelle demande de congé' : 'Nouvelle absence' }}</summary>
    <form method="POST" action="{{ route('rh.conges.store') }}" enctype="multipart/form-data" class="grid gap-4 border-t border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
        @csrf
        <x-field name="employe_id" label="Employé" type="select" required>
            <option value="">—</option>@foreach ($employes as $e)<option value="{{ $e->id }}" @selected(old('employe_id') == $e->id)>{{ $e->nom_complet }}</option>@endforeach
        </x-field>
        <x-field name="type_code" label="Type" type="select" required>
            @foreach ($types->where('actif', true) as $t)<option value="{{ $t->code }}" @selected(old('type_code') === $t->code)>{{ $t->libelle }}{{ ($t->meta['justificatif'] ?? false) ? ' (justificatif)' : '' }}</option>@endforeach
        </x-field>
        <x-field name="date_debut" label="Du" type="date" required /><x-field name="date_fin" label="Au" type="date" required />
        <x-field name="motif" label="Motif" class="sm:col-span-2" />
        <x-field name="justificatif" label="Justificatif (PDF / image, 5 Mo)" type="file" accept=".pdf,.jpg,.jpeg,.png" class="sm:col-span-2" />
        <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><button class="btn-primary">Enregistrer la demande</button></div>
    </form>
</details>
@endcan

<form method="GET" action="{{ route($routeIndex) }}" class="card card-pad mb-4 grid gap-3 sm:grid-cols-4">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Employé…" class="input sm:col-span-2">
    <select name="type" class="input"><option value="">Tous types</option>@foreach ($types as $t)<option value="{{ $t->code }}" @selected(request('type') === $t->code)>{{ $t->libelle }}</option>@endforeach</select>
    <div class="flex gap-2"><select name="statut" class="input"><option value="">Tous statuts</option>@foreach (\App\Models\Absence::STATUTS as $k => $l)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $l }}</option>@endforeach</select><button class="btn-secondary">OK</button></div>
</form>

<div class="space-y-3">
@forelse ($absences as $a)
    @php $retard = $a->statut === 'en_attente' && $a->created_at->lt(now()->subDays($workflow['delai_reponse_jours'])); @endphp
    <div class="card card-pad">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="font-medium">{{ $a->employe->nom_complet }} <span class="text-sm font-normal text-slate-400">· {{ $a->employe->entite?->nom ?? $a->employe->departement }}</span></p>
                <p class="mt-0.5 text-sm">{{ $a->type_libelle }} · {{ $a->date_debut->format('d/m/Y') }} → {{ $a->date_fin->format('d/m/Y') }} · <strong>{{ rtrim(rtrim(number_format($a->jours, 1, ',', ''), '0'), ',') }} j</strong></p>
                @if ($a->motif)<p class="text-xs text-slate-500">{{ $a->motif }}</p>@endif
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <x-badge :tone="$tones[$a->statut]">{{ \App\Models\Absence::STATUTS[$a->statut] }}</x-badge>
                    @if ($retard)<x-badge tone="danger">Réponse en retard</x-badge>@endif
                    @if ($a->justificatif_chemin)@can('conges.voir')<a href="{{ route('rh.conges.justificatif', $a) }}" class="inline-flex items-center gap-1 text-xs text-brand hover:underline"><x-icon name="paperclip" class="h-3.5 w-3.5" />{{ $a->justificatif_nom }}</a>@endcan @endif
                    <span class="text-xs text-slate-400">Saisie par {{ $a->demandeur->name }}{{ $a->traitePar && $a->statut !== 'en_attente' ? ' · traitée par ' . $a->traitePar->name . ' le ' . $a->traite_le->format('d/m/Y') : '' }}</span>
                </div>
                @if ($a->commentaire)<p class="mt-1 text-xs text-slate-500">« {{ $a->commentaire }} »</p>@endif
            </div>
            <div class="flex flex-col gap-2 md:w-72">
                @can('traiter', $a)
                <form method="POST" action="{{ route('rh.conges.decider', $a) }}" class="space-y-2">@csrf
                    <textarea name="commentaire" rows="2" maxlength="300" class="input" placeholder="Commentaire (obligatoire pour un refus)"></textarea>
                    <div class="flex gap-2"><button name="decision" value="valider" class="btn-primary flex-1">Valider</button><button name="decision" value="refuser" class="btn-secondary flex-1 text-red-700">Refuser</button></div>
                </form>
                @endcan
                @can('annuler', $a)
                <form method="POST" action="{{ route('rh.conges.annuler', $a) }}" data-confirm="Annuler cette demande{{ $a->statut === 'validee' ? ' (les jours seront retirés du pointage)' : '' }} ?">@csrf<button class="btn-secondary btn-sm text-red-700"><x-icon name="ban" class="h-3.5 w-3.5" />Annuler la demande</button></form>
                @endcan
            </div>
        </div>
    </div>
@empty
    <div class="card card-pad text-center text-sm text-slate-400">{{ $estConge ? 'Aucune demande de congé' : 'Aucune absence' }} pour ces critères.</div>
@endforelse
</div>
<div class="mt-4">{{ $absences->links() }}</div>
@endif
@endsection

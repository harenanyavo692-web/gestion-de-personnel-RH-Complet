@extends('layouts.app')
@section('title', 'Paramètres RH')

@section('content')
@php $gerer = auth()->user()->can('rh.parametres.gerer'); @endphp
<x-page-header title="Paramètres RH" subtitle="Règles de gestion des ressources humaines">
    <form method="GET" class="flex gap-2">
        <input type="hidden" name="section" value="{{ $section }}">
        <input type="search" name="q" value="{{ $q }}" placeholder="Rechercher dans les listes…" class="input w-56">
        <button class="btn-secondary">Rechercher</button>
    </form>
</x-page-header>
@include('rh._onglets-parametres')
<x-tabs :sections="$sections" :actif="$section" route="rh.parametres.index" />

@if ($section === 'general')
<form method="POST" action="{{ route('rh.parametres.update') }}" class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @csrf @method('PUT')
    <input type="hidden" name="section" value="general">
    <x-field name="organisation" label="Nom de l'organisation" :value="$general['organisation']" class="sm:col-span-2" required />
    <x-field name="adresse" label="Adresse" :value="$general['adresse']" class="sm:col-span-2" />
    <x-field name="telephone" label="Téléphone" :value="$general['telephone']" />
    <x-field name="email" label="E-mail professionnel" type="email" :value="$general['email']" />
    <x-field name="annee_reference" label="Année de référence RH (vide = année en cours)" type="number" min="2000" max="2100" :value="$general['annee_reference']" />
    <x-field name="lignes_par_page" label="Lignes par page dans les tableaux RH" type="select" required>
        @foreach ([10, 15, 25, 50] as $n)<option value="{{ $n }}" @selected((int) old('lignes_par_page', $general['lignes_par_page']) === $n)>{{ $n }}</option>@endforeach
    </x-field>
    @if ($gerer)<div class="sm:col-span-2 lg:col-span-3 flex justify-end gap-2"><a href="{{ route('rh.parametres.index', ['section' => 'general']) }}" class="btn-secondary">Annuler</a><button class="btn-primary">Enregistrer</button></div>@endif
</form>

@elseif ($section === 'personnel')
<div class="grid gap-4 lg:grid-cols-2">
    @foreach ($listes as $groupe => $l)
        <x-ref-list :titre="$l['titre']" :groupe="$groupe" :items="$l['items']" :peut-gerer="$gerer" />
    @endforeach
</div>

@elseif ($section === 'organisation')
<div class="mb-3 flex items-center justify-between">
    <p class="text-sm text-slate-500">Services, départements et unités : responsables, rattachement des employés et activation se gèrent dans <strong>Entités RH</strong>.</p>
    @can('rh.entites.voir')<a href="{{ route('rh.entites.index') }}" class="btn-secondary">Ouvrir Entités RH</a>@endcan
</div>
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Code</th><th>Entité</th><th>Type</th><th>Rattachée à</th><th>Responsable</th><th class="text-right">Employés</th><th>Statut</th></tr></thead>
        <tbody>
        @forelse ($entites as $e)
            <tr><td class="font-mono text-xs">{{ $e->code }}</td><td class="font-medium">{{ $e->nom }}</td><td>{{ $e->type_libelle }}</td><td class="text-slate-500">{{ $e->parent?->nom ?? '—' }}</td>
                <td>{{ $e->responsable?->nom_complet ?? '—' }}</td><td class="text-right tabular-nums">{{ $e->employes_count }}</td>
                <td><x-badge :tone="$e->actif ? 'success' : 'neutral'">{{ $e->actif ? 'Actif' : 'Inactif' }}</x-badge></td></tr>
        @empty
            <x-empty :cols="7" message="Aucune entité." />
        @endforelse
        </tbody>
    </table>
</div>

@elseif ($section === 'contrats')
<div><x-ref-list titre="Types de contrats" groupe="type_contrat" :items="$contrats" :peut-gerer="$gerer" /></div>

@elseif ($section === 'conges')
<div>
    <x-ref-list titre="Types d'absences et de congés" groupe="type_absence" :items="$absences" :peut-gerer="$gerer" :absence="true" />
    <p class="mt-2 text-xs text-slate-500">« Jours / an » vide = non plafonné. Le justificatif indique qu'une pièce (certificat médical…) est exigée.</p>
</div>

@else
<form method="POST" action="{{ route('rh.parametres.update') }}" class="card card-pad space-y-4">
    @csrf @method('PUT')
    <input type="hidden" name="section" value="workflow">
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="validation_requise" value="1" @checked(old('validation_requise', $workflow['validation_requise'])) @disabled(! $gerer) class="rounded border-slate-300"> Les demandes d'absence doivent être validées</label>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="validateur" label="Qui valide une demande" type="select" required>
            @foreach (['responsable' => "Le responsable de l'entité", 'rh' => 'Le service RH', 'direction' => 'La direction'] as $k => $l)<option value="{{ $k }}" @selected(old('validateur', $workflow['validateur']) === $k)>{{ $l }}</option>@endforeach
        </x-field>
        <x-field name="delai_reponse_jours" label="Délai de réponse (jours)" type="number" min="1" max="60" :value="$workflow['delai_reponse_jours']" required />
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="solde_negatif_autorise" value="1" @checked(old('solde_negatif_autorise', $workflow['solde_negatif_autorise'])) @disabled(! $gerer) class="rounded border-slate-300"> Autoriser un solde de congés négatif</label>
    @if ($gerer)<div class="flex justify-end gap-2"><a href="{{ route('rh.parametres.index', ['section' => 'workflow']) }}" class="btn-secondary">Annuler</a><button class="btn-primary">Enregistrer</button></div>@endif
</form>
@endif
@endsection

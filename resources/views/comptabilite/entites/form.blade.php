@extends('layouts.app')
@section('title', $entite->exists ? 'Modifier ' . $entite->nom : 'Nouvelle entité comptable')

@section('content')
<x-page-header :title="$entite->exists ? $entite->nom : 'Nouvelle entité comptable'" subtitle="Périmètre analytique et budgétaire" />

<form method="POST" action="{{ $entite->exists ? route('comptabilite.entites.update', $entite) : route('comptabilite.entites.store') }}" class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @csrf
    @if ($entite->exists) @method('PUT') @endif
    <x-field name="code" label="Code unique" :value="$entite->code" required placeholder="Ex : CC-FROID" />
    <x-field name="nom" label="Nom" :value="$entite->nom" required />
    <x-field name="type" label="Type" type="select" required>
        @foreach ($types as $k => $l)<option value="{{ $k }}" @selected(old('type', $entite->type) === $k)>{{ $l }}</option>@endforeach
    </x-field>
    <x-field name="parent_id" label="Entité comptable parente" type="select">
        <option value="">— Aucune —</option>
        @foreach ($parents as $p)<option value="{{ $p->id }}" @selected((int) old('parent_id', $entite->parent_id) === $p->id)>{{ $p->code }} · {{ $p->nom }}</option>@endforeach
    </x-field>
    <x-field name="organization_entity_id" label="Entité organisationnelle (RH) associée" type="select">
        <option value="">—</option>
        @foreach ($organisations as $o)<option value="{{ $o->id }}" @selected((int) old('organization_entity_id', $entite->organization_entity_id) === $o->id)>{{ $o->code }} · {{ $o->nom }}</option>@endforeach
    </x-field>
    <x-field name="responsable_id" label="Responsable financier" type="select">
        <option value="">—</option>
        @foreach ($responsables as $r)<option value="{{ $r->id }}" @selected((int) old('responsable_id', $entite->responsable_id) === $r->id)>{{ $r->name }}</option>@endforeach
    </x-field>
    <x-field name="exercise_id" label="Exercice du budget" type="select">
        <option value="">—</option>
        @foreach ($exercices as $x)<option value="{{ $x->id }}" @selected((int) old('exercise_id', $entite->exercise_id) === $x->id)>{{ $x->code }} ({{ $x->statut === 'ouvert' ? 'ouvert' : 'clôturé' }})</option>@endforeach
    </x-field>
    <x-field name="budget_reference" label="Budget de référence (Ar)" type="number" min="0" step="1" :value="$entite->budget_reference" />
    <x-field name="description" label="Description" type="textarea" :value="$entite->description" class="sm:col-span-2 lg:col-span-3" />
    <label class="flex items-center gap-2 text-sm sm:col-span-2 lg:col-span-3"><input type="checkbox" name="actif" value="1" @checked(old('actif', $entite->actif)) class="rounded border-slate-300"> Entité active
        <span class="text-xs text-slate-400">(une entité désactivée reste liée à son historique mais n'est plus proposée à la saisie)</span></label>
    <div class="sm:col-span-2 lg:col-span-3 flex justify-end gap-2">
        <a href="{{ $entite->exists ? route('comptabilite.entites.show', $entite) : route('comptabilite.entites.index') }}" class="btn-secondary">Annuler</a>
        <button class="btn-primary">Enregistrer</button>
    </div>
</form>
@endsection

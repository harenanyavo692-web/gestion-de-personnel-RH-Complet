@extends('layouts.app')
@section('title', $entite->exists ? 'Modifier ' . $entite->nom : 'Nouvelle entité RH')

@section('content')
<x-page-header :title="$entite->exists ? $entite->nom : 'Nouvelle entité'" subtitle="Structure organisationnelle du CDPHM" />

<form method="POST" action="{{ $entite->exists ? route('rh.entites.update', $entite) : route('rh.entites.store') }}" class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @csrf
    @if ($entite->exists) @method('PUT') @endif
    <x-field name="code" label="Code unique" :value="$entite->code" required placeholder="Ex : SRV-COMPTA" />
    <x-field name="nom" label="Nom de l'entité" :value="$entite->nom" required />
    <x-field name="type" label="Type" type="select" required>
        @foreach ($types as $k => $l)<option value="{{ $k }}" @selected(old('type', $entite->type) === $k)>{{ $l }}</option>@endforeach
    </x-field>
    <x-field name="parent_id" label="Entité parente" type="select">
        <option value="">— Aucune (niveau supérieur) —</option>
        @foreach ($parents as $p)<option value="{{ $p->id }}" @selected((int) old('parent_id', $entite->parent_id) === $p->id)>{{ $p->code }} · {{ $p->nom }}</option>@endforeach
    </x-field>
    <x-field name="responsable_id" label="Responsable" type="select">
        <option value="">—</option>
        @foreach ($responsables as $r)<option value="{{ $r->id }}" @selected((int) old('responsable_id', $entite->responsable_id) === $r->id)>{{ $r->nom_complet }}</option>@endforeach
    </x-field>
    <x-field name="localisation" label="Localisation" :value="$entite->localisation" />
    <x-field name="description" label="Description" type="textarea" :value="$entite->description" class="sm:col-span-2 lg:col-span-3" />
    <label class="flex items-center gap-2 text-sm sm:col-span-2 lg:col-span-3"><input type="checkbox" name="actif" value="1" @checked(old('actif', $entite->actif)) class="rounded border-slate-300"> Entité active
        <span class="text-xs text-slate-400">(désactiver ne supprime rien : les employés gardent leur rattachement historique)</span></label>
    <div class="sm:col-span-2 lg:col-span-3 flex justify-end gap-2">
        <a href="{{ $entite->exists ? route('rh.entites.show', $entite) : route('rh.entites.index') }}" class="btn-secondary">Annuler</a>
        <button class="btn-primary">Enregistrer</button>
    </div>
</form>
@endsection

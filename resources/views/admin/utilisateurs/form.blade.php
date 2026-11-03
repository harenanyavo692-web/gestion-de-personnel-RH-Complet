@extends('layouts.app')
@section('title', $utilisateur->exists ? 'Modifier un utilisateur' : 'Nouvel utilisateur')

@section('content')
@php $self = $utilisateur->exists && $utilisateur->is(auth()->user()); @endphp
<x-page-header :title="$utilisateur->exists ? $utilisateur->name : 'Nouvel utilisateur'" />

<form method="POST" action="{{ $utilisateur->exists ? route('admin.utilisateurs.update', $utilisateur) : route('admin.utilisateurs.store') }}" class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @csrf
    @if ($utilisateur->exists) @method('PUT') @endif
    <x-field name="name" label="Nom complet" :value="$utilisateur->name" required />
    <x-field name="email" label="E-mail" type="email" :value="$utilisateur->email" required />
    <x-field name="role_id" label="Rôle" type="select" required>
        <option value="">—</option>
        @foreach ($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id', $utilisateur->role_id) == $r->id) @disabled($self && $utilisateur->role_id != $r->id)>{{ $r->libelle }}</option>@endforeach
    </x-field>
    <x-field name="password" label="{{ $utilisateur->exists ? 'Nouveau mot de passe (laisser vide pour conserver)' : 'Mot de passe (8 caractères min.)' }}" type="password" :required="! $utilisateur->exists" autocomplete="new-password" />
    <x-field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" />
    @if ($utilisateur->exists)
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="actif" value="1" @checked(old('actif', $utilisateur->actif)) @disabled($self) class="rounded border-slate-300"> Compte actif @if ($self)<span class="text-xs text-slate-400">(vous ne pouvez pas désactiver votre propre compte)</span>@endif</label>
    @endif
    <div class="flex items-end justify-end gap-2 sm:col-span-2 lg:col-span-3">
        <a href="{{ route('admin.utilisateurs.index') }}" class="btn-secondary">Annuler</a>
        <button class="btn-primary">Enregistrer</button>
    </div>
</form>
@endsection

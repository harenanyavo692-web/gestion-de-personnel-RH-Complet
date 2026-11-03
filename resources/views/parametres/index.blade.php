@extends('layouts.app')
@section('title', 'Mon profil')

@section('content')
<x-page-header title="Mon profil" subtitle="Vos informations, votre photo et votre mot de passe" />

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4">
        <div class="card card-pad text-center">
            <x-avatar :user="$user" class="mx-auto h-24 w-24 text-2xl" />
            <p class="mt-3 font-semibold">{{ $user->name }}</p>
            <p class="text-sm text-slate-500">{{ $user->role?->libelle ?? 'Sans rôle' }}</p>
            <p class="text-xs text-slate-400">{{ $user->email }}</p>

            <form method="POST" action="{{ route('parametres.avatar') }}" enctype="multipart/form-data" class="mt-4 space-y-2">
                @csrf
                <label class="btn-secondary w-full cursor-pointer"><x-icon name="upload" class="h-4 w-4" />{{ $user->avatar_chemin ? 'Changer la photo' : 'Ajouter une photo' }}
                    <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp" class="sr-only" data-autosubmit></label>
                @error('avatar')<p class="field-error">{{ $message }}</p>@enderror
                <p class="text-xs text-slate-400">JPG, PNG ou WebP · 2 Mo max</p>
            </form>
            @if ($user->avatar_chemin)
            <form method="POST" action="{{ route('parametres.avatar.supprimer') }}" class="mt-2" data-confirm="Supprimer votre photo de profil ?" data-confirm-tone="danger" data-confirm-label="Supprimer">
                @csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Supprimer la photo</button></form>
            @endif
        </div>

        <div class="card">
            <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Mon activité récente</h3>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($activite as $l)
                    <li class="px-4 py-2"><x-badge :tone="$l->tone">{{ $l->action }}</x-badge><p class="mt-0.5 text-xs text-slate-400">{{ $l->created_at->diffForHumans() }}</p></li>
                @empty
                    <li class="px-4 py-6 text-center text-slate-400">Aucune activité.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <form method="POST" action="{{ route('parametres.update') }}" class="card card-pad space-y-4 lg:col-span-2" data-confirm="Enregistrer les modifications de votre profil ?" data-confirm-label="Enregistrer" data-confirm-tone="primary">
        @csrf
        <h3 class="text-sm font-semibold">Informations personnelles</h3>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Nom complet" :value="$user->name" required />
            <x-field name="email" label="Adresse e-mail" type="email" :value="$user->email" required />
            <x-field name="telephone" label="Téléphone" :value="$user->telephone" placeholder="+261 34 xx xx xxx" />
        </div>
        <h3 class="border-t border-slate-100 pt-4 text-sm font-semibold">Changer le mot de passe <span class="font-normal text-slate-400">(laisser vide pour le conserver)</span></h3>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="current_password" label="Mot de passe actuel" type="password" autocomplete="current-password" />
            <x-field name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" />
            <x-field name="password_confirmation" label="Confirmation" type="password" autocomplete="new-password" />
        </div>
        <div class="flex justify-end"><button class="btn-primary">Enregistrer</button></div>
    </form>
</div>
@endsection

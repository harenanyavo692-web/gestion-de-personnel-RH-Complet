@extends('layouts.app')
@section('title', 'Rôles et permissions')

@section('content')
<x-page-header add="form-ajout" add-label="Nouveau rôle" title="Rôles et permissions" subtitle="Qui peut saisir, valider et administrer — appliqué côté serveur à chaque requête" />
@include('admin._onglets-acces')

<details id="form-ajout" class="form-modal card mb-4" @if ($errors->has('libelle')) open @endif>
    <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium text-brand"><x-icon name="plus" class="h-4 w-4" />Nouveau rôle</summary>
    <form method="POST" action="{{ route('admin.roles.store') }}" class="grid gap-4 border-t border-slate-100 p-4 sm:grid-cols-3">
        @csrf
        <x-field name="libelle" label="Nom du rôle" required />
        <x-field name="copie_de" label="Copier les permissions de" type="select"><option value="">— Aucune (rôle vide) —</option>@foreach ($roles->reject(fn ($r) => in_array($r->slug, $verrouilles)) as $r)<option value="{{ $r->id }}">{{ $r->libelle }}</option>@endforeach</x-field>
        <div class="self-end"><button class="btn-primary w-full">Créer le rôle</button></div>
    </form>
</details>

<div class="space-y-4">
@foreach ($roles as $role)
    @php $verrou = in_array($role->slug, $verrouilles, true); $a = $role->permissions->pluck('id')->all(); @endphp
    <details class="card" @if ($loop->first && ! $verrou) open @endif>
        <summary class="flex cursor-pointer items-center justify-between gap-2 px-4 py-3 text-sm">
            <span><span class="font-medium">{{ $role->libelle }}</span> <span class="text-slate-400">· {{ $role->users_count }} utilisateur(s) · {{ count($a) }} permission(s)</span></span>
            @if ($verrou)<x-badge>verrouillé</x-badge>@endif
        </summary>
        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="border-t border-slate-100 p-4" data-confirm="Enregistrer les permissions du rôle « {{ $role->libelle }} » ?">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($groupes as $module => $perms)
                    <fieldset><legend class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $module }}</legend>
                        @foreach ($perms as $p)
                            <label class="flex items-start gap-2 py-0.5 text-sm"><input type="checkbox" name="permissions[]" value="{{ $p->id }}" @checked(in_array($p->id, $a)) @disabled($verrou) class="mt-0.5 rounded border-slate-300"> <span>{{ $p->libelle }}<span class="block font-mono text-[10px] text-slate-400">{{ $p->slug }}</span></span></label>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
            @unless ($verrou)<div class="mt-4 flex justify-end"><button class="btn-primary">Enregistrer les permissions</button></div>@endunless
        </form>
        @unless ($verrou)
        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="border-t border-slate-100 px-4 py-2 text-right" data-confirm="Supprimer le rôle « {{ $role->libelle }} » ?" data-confirm-tone="danger" data-confirm-label="Supprimer">
            @csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Supprimer ce rôle</button></form>
        @endunless
    </details>
@endforeach
</div>
@endsection

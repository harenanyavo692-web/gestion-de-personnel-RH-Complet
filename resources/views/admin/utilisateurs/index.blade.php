@extends('layouts.app')
@section('title', 'Utilisateurs')

@section('content')
<x-page-header title="Utilisateurs & rôles" subtitle="Comptes d'accès et droits par rôle">
    <a href="{{ route('admin.utilisateurs.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" />Nouvel utilisateur</a>
</x-page-header>
@include('admin._onglets-acces')

<div class="card table-wrap mb-6">
    <table class="data">
        <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @foreach ($utilisateurs as $u)
            <tr>
                <td class="font-medium">{{ $u->name }}</td>
                <td class="text-slate-600">{{ $u->email }}</td>
                <td>{{ $u->role?->libelle ?? '—' }}</td>
                <td><x-badge :tone="$u->actif ? 'success' : 'neutral'">{{ $u->actif ? 'Actif' : 'Désactivé' }}</x-badge></td>
                <td class="text-right"><x-icon-btn icon="edit" title="Modifier" :href="route('admin.utilisateurs.edit', $u)" /></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mb-6">{{ $utilisateurs->links() }}</div>

<h2 class="mb-2 text-sm font-semibold text-slate-700">Permissions par rôle</h2>
<div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
    @foreach ($roles as $r)
        <div class="card card-pad">
            <p class="text-sm font-medium">{{ $r->libelle }}</p>
            <ul class="mt-2 space-y-0.5 text-xs text-slate-500">
                @foreach ($r->permissions->sortBy('slug') as $p)<li>{{ $p->libelle }}</li>@endforeach
            </ul>
        </div>
    @endforeach
</div>
@endsection

@extends('layouts.app')
@section('title', $entite->nom)

@section('content')
<x-page-header :title="$entite->nom" :subtitle="$entite->chemin">
    <a href="{{ route('rh.entites.index') }}" class="btn-secondary">← Entités</a>
    @can('rh.entites.gerer')
        <a href="{{ route('rh.entites.edit', $entite) }}" class="btn-primary">Modifier</a>
        @if (! $entite->employes()->exists() && ! $entite->enfants->count() && ! $entitesComptables->count())
        <form method="POST" action="{{ route('rh.entites.destroy', $entite) }}" data-confirm="Supprimer définitivement cette entité ?">@csrf @method('DELETE')<button class="btn-secondary text-red-700">Supprimer</button></form>
        @endif
    @endcan
</x-page-header>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="card card-pad lg:col-span-1">
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Code</dt><dd class="font-mono">{{ $entite->code }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Type</dt><dd>{{ $entite->type_libelle }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Statut</dt><dd><x-badge :tone="$entite->actif ? 'success' : 'neutral'">{{ $entite->actif ? 'Active' : 'Inactive' }}</x-badge></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Responsable</dt><dd>{{ $entite->responsable?->nom_complet ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Localisation</dt><dd>{{ $entite->localisation ?: '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Créée le</dt><dd>{{ $entite->created_at->format('d/m/Y') }}</dd></div>
        </dl>
        @if ($entite->description)<p class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-600">{{ $entite->description }}</p>@endif
    </div>

    <div class="space-y-4 lg:col-span-2">
        <div class="card">
            <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Employés rattachés ({{ $employes->count() }})</h3>
            <div class="table-wrap"><table class="data"><tbody>
                @forelse ($employes as $e)
                    <tr><td class="font-medium">{{ $e->nom_complet }}</td><td class="text-slate-500">{{ $e->poste }}</td><td><x-badge :tone="$e->statut === 'actif' ? 'success' : 'neutral'">{{ ucfirst($e->statut) }}</x-badge></td></tr>
                @empty
                    <x-empty :cols="3" message="Aucun employé rattaché." />
                @endforelse
            </tbody></table></div>
        </div>
        @if ($entite->enfants->isNotEmpty())
        <div class="card">
            <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Entités rattachées</h3>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($entite->enfants as $c)<li class="flex justify-between px-4 py-2"><a href="{{ route('rh.entites.show', $c) }}" class="text-brand hover:underline">{{ $c->code }} · {{ $c->nom }}</a><span class="text-slate-400">{{ $c->employes_count }} emp.</span></li>@endforeach
            </ul>
        </div>
        @endif
        @if ($entitesComptables->isNotEmpty())
        <div class="card card-pad text-sm"><span class="font-semibold">Périmètres comptables associés :</span>
            {{ $entitesComptables->map(fn ($c) => $c->code)->implode(', ') }}</div>
        @endif
    </div>
</div>
@endsection

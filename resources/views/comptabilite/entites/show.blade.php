@extends('layouts.app')
@section('title', $entite->nom)

@section('content')
@php $budget = $entite->budget_reference !== null ? (float) $entite->budget_reference : null; $pct = $budget ? min(100, $consomme / $budget * 100) : 0; @endphp
<x-page-header :title="$entite->nom" :subtitle="$entite->code . ' · ' . $entite->type_libelle">
    <a href="{{ route('comptabilite.entites.index') }}" class="btn-secondary">← Entités</a>
    @can('compta.entites.gerer')
        <a href="{{ route('comptabilite.entites.edit', $entite) }}" class="btn-primary">Modifier</a>
        @unless ($entite->aDesOperations() || $entite->enfants->isNotEmpty())
        <form method="POST" action="{{ route('comptabilite.entites.destroy', $entite) }}" data-confirm="Supprimer définitivement cette entité ?">@csrf @method('DELETE')<button class="btn-secondary text-red-700">Supprimer</button></form>
        @endunless
    @endcan
</x-page-header>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4">
        <div class="card card-pad">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Statut</dt><dd><x-badge :tone="$entite->actif ? 'success' : 'neutral'">{{ $entite->actif ? 'Active' : 'Inactive' }}</x-badge></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Parente</dt><dd>{{ $entite->parent?->code ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Entité RH</dt><dd>{{ $entite->organisation?->nom ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Responsable</dt><dd>{{ $entite->responsable?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Exercice</dt><dd>{{ $entite->exercice?->code ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Créée le</dt><dd>{{ $entite->created_at->format('d/m/Y') }}</dd></div>
            </dl>
            @if ($entite->description)<p class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-600">{{ $entite->description }}</p>@endif
        </div>
        @if ($budget !== null)
        <div class="card card-pad">
            <h3 class="text-sm font-semibold">Budget {{ $entite->exercice?->code }}</h3>
            <p class="mt-1 text-lg font-semibold tabular-nums">@ar($consomme) <span class="text-sm font-normal text-slate-400">/ @ar($budget)</span></p>
            <div class="mt-2 h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded {{ $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-brand') }}" style="width: {{ $pct }}%"></div></div>
            <p class="mt-1 text-xs text-slate-500">Reste : @ar($budget - $consomme) · dépenses validées de l'entité et de ses entités filles</p>
        </div>
        @endif
    </div>

    <div class="space-y-4 lg:col-span-2">
        @if (! $peutVoirOps)
            <div class="card card-pad text-sm text-slate-500">Vous n'avez pas accès aux opérations rattachées à cette entité.</div>
        @endif
        @can('recettes.voir')
        <div class="card">
            <div class="flex justify-between border-b border-slate-100 px-4 py-3 text-sm"><h3 class="font-semibold">Recettes rattachées</h3><span class="tabular-nums text-emerald-700">@ar($totalRecettes)</span></div>
            <div class="table-wrap"><table class="data"><tbody>
                @forelse ($recettes as $r)<tr><td>{{ $r->date_recette->format('d/m/Y') }}</td><td>{{ $r->client ?: $r->origine }}</td><td class="text-slate-500">{{ $r->categorie->nom }}</td><td class="text-right tabular-nums">@ar($r->montant)</td></tr>
                @empty<x-empty :cols="4" message="Aucune recette rattachée." />@endforelse
            </tbody></table></div>
        </div>
        @endcan
        @can('depenses.voir')
        <div class="card">
            <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Dépenses rattachées</h3>
            <div class="table-wrap"><table class="data"><tbody>
                @forelse ($depenses as $o)<tr><td>{{ $o->date_depense->format('d/m/Y') }}</td><td>{{ $o->motif }}</td>
                    <td><x-badge :tone="['validee' => 'success', 'refusee' => 'danger', 'en_attente' => 'warning'][$o->statut]">{{ $o->statut_libelle }}</x-badge></td><td class="text-right tabular-nums">@ar($o->montant)</td></tr>
                @empty<x-empty :cols="4" message="Aucune dépense rattachée." />@endforelse
            </tbody></table></div>
        </div>
        @endcan
        @if ($entite->enfants->isNotEmpty())
        <div class="card card-pad text-sm"><span class="font-semibold">Entités filles :</span>
            @foreach ($entite->enfants as $c)<a class="ml-2 text-brand hover:underline" href="{{ route('comptabilite.entites.show', $c) }}">{{ $c->code }}</a>@endforeach</div>
        @endif
    </div>
</div>
@endsection

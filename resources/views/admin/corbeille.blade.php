@extends('layouts.app')
@section('title', 'Corbeille')

@section('content')
@php $total = $types->sum('nombre'); @endphp
<x-page-header title="Corbeille" subtitle="Les éléments supprimés restent récupérables tant que la corbeille n'est pas vidée">
    @if ($total > 0)
    <form method="POST" action="{{ route('admin.corbeille.vider') }}" data-confirm="Vider toute la corbeille ? Les {{ $total }} élément(s) seront supprimés définitivement." data-confirm-tone="danger" data-confirm-label="Tout supprimer">
        @csrf @method('DELETE')<input type="hidden" name="type" value="tout"><button class="btn-secondary text-red-600"><x-icon name="trash" class="h-4 w-4" />Tout vider</button>
    </form>
    @endif
</x-page-header>

<nav class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
    @foreach ($types as $k => $t)
        <a href="{{ route('admin.corbeille.index', ['type' => $k]) }}" class="whitespace-nowrap border-b-2 px-3 py-2 text-sm {{ $type === $k ? 'border-brand font-medium text-brand' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            {{ $t['label'] }} @if ($t['nombre'])<span class="ml-1 rounded-full bg-slate-100 px-1.5 text-xs">{{ $t['nombre'] }}</span>@endif</a>
    @endforeach
</nav>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Élément</th><th>Supprimé le</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        @forelse ($elements as $e)
            <tr>
                <td class="font-medium">{{ $e->libelleActivite() }}
                    @if ($type === 'recettes' || $type === 'depenses')<span class="font-normal text-slate-400"> · @ar($e->montant)</span>@endif</td>
                <td class="text-sm text-slate-500">{{ $e->deleted_at->format('d/m/Y H:i') }} <span class="text-xs">({{ $e->deleted_at->diffForHumans() }})</span></td>
                <td>
                    <div class="flex justify-end gap-2">
                        <form method="POST" action="{{ route('admin.corbeille.restaurer', [$type, $e->id]) }}" data-confirm="Restaurer « {{ $e->libelleActivite() }} » ?" data-confirm-tone="success" data-confirm-label="Restaurer">@csrf<button class="btn-secondary btn-sm"><x-icon name="undo" class="h-3.5 w-3.5" />Restaurer</button></form>
                        <form method="POST" action="{{ route('admin.corbeille.supprimer', [$type, $e->id]) }}" data-confirm="Supprimer définitivement « {{ $e->libelleActivite() }} » ? Cette action est irréversible." data-confirm-tone="danger" data-confirm-label="Supprimer">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" />Supprimer</button></form>
                    </div>
                </td>
            </tr>
        @empty
            <x-empty :cols="3" message="La corbeille est vide pour cette catégorie." />
        @endforelse
        </tbody>
    </table>
</div>
@if ($elements->total() > 0)
<form method="POST" action="{{ route('admin.corbeille.vider') }}" class="mt-3 text-right" data-confirm="Vider la corbeille « {{ $types[$type]['label'] }} » ?" data-confirm-tone="danger" data-confirm-label="Vider">
    @csrf @method('DELETE')<input type="hidden" name="type" value="{{ $type }}"><button class="text-xs text-red-600 hover:underline">Vider cette catégorie</button></form>
@endif
<div class="mt-4">{{ $elements->links() }}</div>
@endsection

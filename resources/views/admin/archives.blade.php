@extends('layouts.app')
@section('title', 'Archives')

@section('content')
<x-page-header title="Archives" subtitle="Les éléments terminés quittent les listes courantes mais restent intacts et comptés dans les états financiers" />

<nav class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
    @foreach ($types as $k => $t)
        <a href="{{ route('admin.archives.index', ['type' => $k]) }}" class="whitespace-nowrap border-b-2 px-3 py-2 text-sm {{ $type === $k ? 'border-brand font-medium text-brand' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            {{ $t['label'] }} @if ($t['nombre'])<span class="ml-1 rounded-full bg-slate-100 px-1.5 text-xs">{{ $t['nombre'] }}</span>@endif</a>
    @endforeach
</nav>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Élément</th><th>Archivé le</th><th>Par</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        @forelse ($elements as $e)
            <tr>
                <td class="font-medium">{{ $e->libelleActivite() }}
                    @if ($type === 'recettes' || $type === 'depenses')<span class="font-normal text-slate-400"> · @ar($e->montant)</span>@endif</td>
                <td class="text-sm text-slate-500">{{ $e->archived_at->format('d/m/Y H:i') }} <span class="text-xs">({{ $e->archived_at->diffForHumans() }})</span></td>
                <td class="text-sm">{{ $auteurs[$e->archived_by] ?? '—' }}</td>
                <td>
                    <div class="flex justify-end gap-2">
                        @if ($peutGerer)
                        <form method="POST" action="{{ route('admin.archives.restaurer', [$type, $e->id]) }}" data-confirm="Remettre « {{ $e->libelleActivite() }} » dans la liste courante ?" data-confirm-tone="success" data-confirm-label="Désarchiver">@csrf<button class="btn-secondary btn-sm"><x-icon name="undo" class="h-3.5 w-3.5" />Désarchiver</button></form>
                        @endif
                        @can('donnees.purger')
                        <form method="POST" action="{{ route('admin.archives.supprimer', [$type, $e->id]) }}" data-confirm="Supprimer définitivement « {{ $e->libelleActivite() }} » ? Cette action est irréversible." data-confirm-tone="danger" data-confirm-label="Supprimer">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" />Supprimer</button></form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <x-empty :cols="4" message="Aucun élément archivé dans cette catégorie." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $elements->links() }}</div>
@endsection

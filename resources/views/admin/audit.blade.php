@extends('layouts.app')
@section('title', "Journal d'activité")

@section('content')
<x-page-header title="Journal d'activité" :subtitle="number_format($total, 0, ',', ' ') . ' événement(s) enregistré(s) — qui a fait quoi, quand et depuis quelle adresse'">
    <a href="{{ route('admin.audit.export', request()->query()) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" />Exporter CSV</a>
</x-page-header>
@include('admin._onglets-journal')
@if ($restreint)<p class="mb-3 rounded-lg px-3 py-2 text-xs" style="background: var(--brand-soft); color: var(--brand)">Vous consultez le journal du périmètre ressources humaines (personnel, contrats, congés, entités, paramètres RH).</p>@endif

<form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher (action, détail, IP)…" class="input lg:col-span-2">
    <select name="user_id" class="input"><option value="">Tous les utilisateurs</option>@foreach ($utilisateurs as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select>
    <select name="module" class="input"><option value="">Tous les modules</option>@foreach ($modules as $m)<option value="{{ $m }}" @selected(request('module') === $m)>{{ str_replace('_', ' ', $m) }}</option>@endforeach</select>
    <input type="date" name="du" value="{{ request('du') }}" class="input" title="Du">
    <div class="flex gap-2"><input type="date" name="au" value="{{ request('au') }}" class="input" title="Au"><button class="btn-secondary">OK</button></div>
</form>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Détails</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($logs as $l)
            <tr>
                <td class="whitespace-nowrap text-xs">{{ $l->created_at->format('d/m/Y H:i:s') }}<p class="text-slate-400">{{ $l->created_at->diffForHumans() }}</p></td>
                <td>{{ $l->user?->name ?? '—' }}</td>
                <td><x-badge :tone="$l->tone">{{ $l->action }}</x-badge></td>
                <td class="max-w-md text-xs text-slate-500">
                    @if ($l->details)
                        @if (! empty($l->details['libelle']))<p class="font-medium text-slate-700">{{ $l->details['libelle'] }}</p>@endif
                        @if (! empty($l->details['changements']))
                            <ul class="mt-0.5">@foreach ($l->details['changements'] as $champ => $c)<li><span class="font-mono">{{ $champ }}</span> : <s class="text-slate-400">{{ is_scalar($c['avant']) || $c['avant'] === null ? ($c['avant'] ?? '∅') : '…' }}</s> → {{ is_scalar($c['apres']) || $c['apres'] === null ? ($c['apres'] ?? '∅') : '…' }}</li>@endforeach</ul>
                        @else
                            <span class="truncate" title="{{ json_encode($l->details, JSON_UNESCAPED_UNICODE) }}">{{ \Illuminate\Support\Str::limit(json_encode(collect($l->details)->except('libelle')->all(), JSON_UNESCAPED_UNICODE), 140) }}</span>
                        @endif
                    @endif
                </td>
                <td class="font-mono text-xs text-slate-400">{{ $l->ip }}</td>
            </tr>
        @empty
            <x-empty :cols="5" message="Aucun événement pour ces critères." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection

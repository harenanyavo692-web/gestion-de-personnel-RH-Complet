@extends('layouts.app')
@section('title', 'Contrats')

@section('content')
@php $gerer = auth()->user()->can('contrats.gerer'); @endphp
<x-page-header add="form-ajout" add-label="Nouveau contrat" title="Contrats" subtitle="Types de contrat paramétrés dans Paramètres RH › Contrats" />
@include('rh._onglets-personnel')

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Échéance sous 30 jours" :value="$expirant" tone="warning" />
    <x-kpi label="Contrats expirés non clôturés" :value="$expires" :tone="$expires ? 'danger' : 'neutral'" />
</div>

@if ($gerer)
<details id="form-ajout" class="form-modal card mb-4" @if ($errors->any()) open @endif>
    <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium text-brand"><x-icon name="plus" class="h-4 w-4" />Nouveau contrat</summary>
    <form method="POST" action="{{ route('rh.contrats.store') }}" class="grid gap-4 border-t border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
        @csrf
        <x-field name="employe_id" label="Employé" type="select" required class="lg:col-span-2">
            <option value="">—</option>@foreach ($employes as $e)<option value="{{ $e->id }}" @selected(old('employe_id') == $e->id)>{{ $e->nom_complet }} ({{ $e->matricule }})</option>@endforeach
        </x-field>
        <x-field name="type_code" label="Type de contrat" type="select" required>
            @foreach ($types->where('actif', true) as $t)<option value="{{ $t->code }}" @selected(old('type_code') === $t->code)>{{ $t->code }} — {{ $t->libelle }}</option>@endforeach
        </x-field>
        <x-field name="salaire" label="Salaire (Ar)" type="number" min="0" step="1" />
        <x-field name="date_debut" label="Début" type="date" required />
        <x-field name="date_fin" label="Fin (vide si durée indéterminée)" type="date" />
        <x-field name="notes" label="Notes" class="sm:col-span-2" />
        <label class="flex items-center gap-2 self-end pb-2 text-sm sm:col-span-2"><input type="checkbox" name="appliquer_salaire" value="1" class="rounded border-slate-300"> Appliquer ce salaire à la fiche de l'employé</label>
        <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><button class="btn-primary">Enregistrer le contrat</button></div>
    </form>
</details>
@endif

<form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-4">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Employé ou matricule…" class="input sm:col-span-2">
    <select name="type" class="input"><option value="">Tous types</option>@foreach ($types as $t)<option value="{{ $t->code }}" @selected(request('type') === $t->code)>{{ $t->code }} — {{ $t->libelle }}</option>@endforeach</select>
    <div class="flex gap-2"><select name="statut" class="input"><option value="">Tous statuts</option>@foreach (['actif' => 'Actifs', 'expire' => 'Expirés', 'termine' => 'Terminés'] as $k => $l)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $l }}</option>@endforeach</select><button class="btn-secondary">OK</button></div>
</form>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Employé</th><th>Type</th><th>Période</th><th class="text-right">Salaire</th><th>Statut</th>@if ($gerer)<th></th>@endif</tr></thead>
        <tbody>
        @forelse ($contrats as $c)
            @php $reste = $c->date_fin ? (int) today()->diffInDays($c->date_fin, false) : null; @endphp
            <tr>
                <td><div class="flex items-center gap-3"><x-initials :name="$c->employe->nom_complet" /><div><p class="font-medium">{{ $c->employe->nom_complet }}</p><p class="font-mono text-xs text-slate-400">{{ $c->employe->matricule }}</p></div></div></td>
                <td><x-badge :tone="$c->type_tone" class="font-semibold">{{ $c->type_code }}</x-badge><p class="mt-1 text-xs text-slate-500">{{ $c->type_libelle }}</p></td>
                <td class="min-w-[13rem]">
                    <p class="whitespace-nowrap text-sm">{{ $c->date_debut->format('d/m/Y') }} → {{ $c->date_fin?->format('d/m/Y') ?? 'indéterminée' }}</p>
                    @if ($c->avancement !== null && $c->statut === 'actif')
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full" style="background: var(--line)"><div class="h-full rounded-full {{ $c->expire ? 'bg-red-500' : ($reste <= 30 ? 'bg-amber-500' : 'bg-[var(--brand)]') }}" style="width: {{ $c->avancement }}%"></div></div>
                        <p class="mt-1 text-xs {{ $c->expire ? 'text-red-600' : 'text-slate-500' }}">{{ $c->expire ? 'Échu depuis ' . abs($reste) . ' j' : ($reste === 0 ? "Se termine aujourd'hui" : 'Reste ' . $reste . ' j') }}</p>
                    @elseif ($c->statut === 'actif')<p class="mt-1 text-xs text-slate-500">Durée indéterminée</p>@endif
                </td>
                <td class="text-right tabular-nums">{{ $c->salaire ? \App\Support\Fmt::ar($c->salaire) : '—' }}</td>
                <td>@if ($c->statut === 'termine')<x-badge>Terminé le {{ $c->termine_le->format('d/m/Y') }}</x-badge>@elseif ($c->expire)<x-badge tone="danger">Expiré</x-badge>@else<x-badge tone="success">Actif</x-badge>@endif
                    @if ($c->motif_fin)<p class="mt-1 text-xs text-slate-400">{{ $c->motif_fin }}</p>@endif</td>
                @if ($gerer)
                <td class="whitespace-nowrap text-right"><div class="flex items-center justify-end">
                    @if ($c->statut === 'actif')
                    <details class="pop">
                        <summary class="icon-btn h-8 w-8" title="Terminer le contrat" aria-label="Terminer le contrat"><x-icon name="ban" class="h-4 w-4" /></summary>
                        <form method="POST" action="{{ route('rh.contrats.terminer', $c) }}" class="pop-panel space-y-2" data-confirm="Terminer ce contrat ?">@csrf
                            <p class="text-sm font-semibold">Terminer le contrat</p>
                            <x-field name="termine_le" label="Date de fin" type="date" :value="today()->toDateString()" min="{{ $c->date_debut->toDateString() }}" required />
                            <x-field name="motif_fin" label="Motif" placeholder="Démission, fin de mission…" maxlength="200" />
                            <button class="btn-primary btn-sm w-full">Confirmer la fin</button>
                        </form>
                    </details>
                    @endif
                    <x-archive-btn type="contrats" :element="$c" />
                    <form method="POST" action="{{ route('rh.contrats.destroy', $c) }}" data-confirm="Mettre ce contrat dans la corbeille ?" data-confirm-label="Supprimer">@csrf @method('DELETE')<x-icon-btn icon="trash" tone="danger" title="Supprimer" /></form>
                </div></td>
                @endif
            </tr>
        @empty
            <x-empty :cols="6" message="Aucun contrat enregistré." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $contrats->links() }}</div>
@endsection

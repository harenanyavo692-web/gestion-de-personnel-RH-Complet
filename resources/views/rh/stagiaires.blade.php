@extends('layouts.app')
@section('title', 'Stagiaires')

@section('content')
<x-page-header add="form-ajout" add-label="Nouveau stagiaire" title="Stagiaires" subtitle="Suivi des stages : établissement, tuteur, période et gratification" />
@include('rh._onglets-personnel', ['actif' => 'stagiaires'])

<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Stagiaires en cours" :value="$enCours" tone="success" />
    <x-kpi label="Total enregistrés" :value="$stagiaires->total()" />
</div>

@can('employes.gerer')
<details id="form-ajout" class="form-modal card mb-4" @if ($errors->any()) open @endif>
    <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium text-brand"><x-icon name="plus" class="h-4 w-4" />Nouveau stagiaire</summary>
    <form method="POST" action="{{ route('rh.stagiaires.store') }}" class="grid gap-4 border-t border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
        @csrf
        <x-field name="nom" label="Nom" required /><x-field name="prenom" label="Prénom" required />
        <x-field name="email" label="Email" type="email" /><x-field name="telephone" label="Téléphone" />
        <x-field name="etablissement" label="Établissement" required /><x-field name="filiere" label="Filière" />
        <x-field name="entity_id" label="Service / entité" type="select">
            <option value="">—</option>@foreach ($entites as $en)<option value="{{ $en->id }}" @selected(old('entity_id') == $en->id)>{{ $en->nom }}</option>@endforeach
        </x-field>
        <x-field name="tuteur_id" label="Tuteur" type="select">
            <option value="">—</option>@foreach ($tuteurs as $t)<option value="{{ $t->id }}" @selected(old('tuteur_id') == $t->id)>{{ $t->nom_complet }}</option>@endforeach
        </x-field>
        <x-field name="date_debut" label="Début" type="date" required /><x-field name="date_fin" label="Fin" type="date" required />
        <x-field name="gratification" label="Gratification mensuelle (Ar)" type="number" step="0.01" min="0" />
        <x-field name="note" label="Note" class="sm:col-span-2" />
        <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><button class="btn-primary">Enregistrer</button></div>
    </form>
</details>
@endcan

<form method="GET" class="card card-pad mb-4 flex gap-2">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom, établissement…" class="input flex-1"><button class="btn-secondary">OK</button>
</form>

<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Stagiaire</th><th>Établissement</th><th>Service / tuteur</th><th>Période</th><th class="text-right">Gratification</th><th>Statut</th><th>Certificat</th><th></th></tr></thead>
        <tbody>
        @forelse ($stagiaires as $s)
            <tr>
                <td><div class="flex items-center gap-3"><x-initials :name="$s->nom_complet" /><div><p class="font-medium">{{ $s->nom_complet }}</p><p class="text-xs text-slate-400">{{ $s->telephone ?: $s->email }}</p></div></div></td>
                <td>{{ $s->etablissement }}<div class="text-xs text-slate-400">{{ $s->filiere }}</div></td>
                <td>{{ $s->entite?->nom ?? '—' }}<div class="text-xs text-slate-400">{{ $s->tuteur?->nom_complet }}</div></td>
                <td class="whitespace-nowrap">{{ $s->date_debut->format('d/m/Y') }} → {{ $s->date_fin->format('d/m/Y') }}</td>
                <td class="text-right tabular-nums">@if ($s->gratification)@ar($s->gratification)@else<span class="text-slate-400">—</span>@endif</td>
                <td><x-badge :tone="['en cours' => 'success', 'à venir' => 'warning', 'terminé' => 'neutral'][$s->statut]">{{ ucfirst($s->statut) }}</x-badge></td>
                <td>
                    @if ($s->certifie)
                        <a href="{{ route('rh.stagiaires.certificat', $s) }}" class="text-xs text-brand hover:underline">{{ $s->certificat_numero }}</a>
                    @elseif ($s->certifiable)
                        @can('employes.gerer')
                        <details>
                            <summary class="cursor-pointer text-xs text-brand">Délivrer</summary>
                            <form method="POST" action="{{ route('rh.stagiaires.certifier', $s) }}" class="mt-2 w-56 space-y-1.5" data-confirm="Délivrer le certificat de {{ $s->nom_complet }} ? Il ne pourra plus être modifié.">@csrf
                                <select name="appreciation" class="input py-1 text-xs"><option value="">Appréciation (facultatif)</option>@foreach (\App\Models\Stagiaire::APPRECIATIONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                                <textarea name="commentaire_certificat" rows="2" maxlength="1000" class="input text-xs" placeholder="Compétences, missions réalisées…"></textarea>
                                <button class="btn-primary btn-sm w-full">Délivrer le certificat</button>
                            </form>
                        </details>
                        @else<span class="text-xs text-slate-400">À délivrer</span>@endcan
                    @else
                        <span class="text-xs text-slate-400">Stage non terminé</span>
                    @endif
                </td>
                <td class="text-right">
                    @can('employes.gerer')
                    <x-archive-btn type="stagiaires" :element="$s" />
                    @unless ($s->certifie)
                    <form method="POST" action="{{ route('rh.stagiaires.destroy', $s) }}" data-confirm="Supprimer {{ $s->nom_complet }} ?">@csrf @method('DELETE')<x-icon-btn icon="trash" tone="danger" title="Supprimer" /></form>
                    @endunless
                    @endcan
                </td>
            </tr>
        @empty
            <x-empty :cols="8" message="Aucun stagiaire enregistré." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $stagiaires->links() }}</div>
@endsection

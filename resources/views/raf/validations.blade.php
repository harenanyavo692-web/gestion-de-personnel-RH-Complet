@extends('layouts.app')
@section('title', 'Validations')

@section('content')
<x-page-header title="Validation des dépenses" subtitle="Examinez la pièce justificative puis validez ou refusez le décaissement" />
@include('comptabilite._onglets-depenses')
@include('raf._kpis')

<div class="mb-6 space-y-3">
    @forelse ($enAttente as $o)
        <div class="card card-pad">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="font-medium">{{ $o->motif }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $o->reference }} · {{ $o->date_depense->format('d/m/Y') }} · {{ $o->categorie->nom }} · soumis par {{ $o->soumisPar->name }}
                        @if ($o->beneficiaire) · {{ $o->beneficiaire }}@endif
                    </p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">@ar($o->montant)</p>
                    <div class="mt-1 text-xs">
                        @forelse ($o->pieces as $p)
                            <a href="{{ route('comptabilite.pieces.show', $p) }}" class="inline-flex items-center gap-1 text-brand hover:underline"><x-icon name="paperclip" class="h-3.5 w-3.5" />{{ $p->nom_original }}</a>
                        @empty
                            <span class="text-amber-700">Aucune pièce justificative jointe</span>
                        @endforelse
                    </div>
                </div>

                @can('valider', $o)
                <form method="POST" action="{{ route('raf.validations.decider', $o) }}" class="w-full space-y-2 md:w-72">
                    @csrf
                    <textarea name="commentaire" rows="2" class="input" placeholder="Commentaire (obligatoire pour un refus)"></textarea>
                    <div class="flex gap-2">
                        <button name="decision" value="valider" class="btn-primary flex-1">Valider</button>
                        <button name="decision" value="refuser" class="btn-secondary flex-1 text-red-700">Refuser</button>
                    </div>
                </form>
                @else
                    <p class="text-xs text-slate-400 md:w-72">Vous avez soumis cet ordre : un autre responsable doit le valider.</p>
                @endcan
            </div>
        </div>
    @empty
        <div class="card card-pad text-center text-sm text-slate-400">Aucun ordre en attente de validation.</div>
    @endforelse
</div>

<h2 class="mb-2 text-sm font-semibold text-slate-700">Historique des ordres traités</h2>
<div class="card table-wrap">
    <table class="data">
        <thead><tr><th>Référence</th><th>Motif</th><th class="text-right">Montant</th><th>Décision</th><th>Traité par</th><th>Commentaire</th></tr></thead>
        <tbody>
        @forelse ($historique as $o)
            <tr>
                <td class="font-mono text-xs">{{ $o->reference }}</td>
                <td>{{ $o->motif }}<p class="text-xs text-slate-400">{{ $o->categorie->nom }}</p></td>
                <td class="text-right tabular-nums">@ar($o->montant)</td>
                <td><x-badge :tone="$o->statut === 'validee' ? 'success' : 'danger'">{{ $o->statut_libelle }}</x-badge></td>
                <td class="text-xs">{{ $o->traitePar?->name }}<p class="text-slate-400">{{ $o->traite_le?->format('d/m/Y H:i') }}</p></td>
                <td class="text-xs text-slate-500">{{ $o->commentaire_raf }}</td>
            </tr>
        @empty
            <x-empty :cols="6" message="Aucun ordre traité pour le moment." />
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $historique->links() }}</div>
@endsection

@extends('layouts.app')
@section('title', 'État de paie ' . $mois)

@section('content')
@php
    $ar = fn ($v) => \App\Support\Fmt::ar($v);
    $totalSalaires = (float) $paiements->sum('montant');
    $totalHs = (float) $heuresSup->flatten()->sum('montant');
@endphp
<div class="print:hidden mb-3 flex justify-between">
    <a href="{{ route('raf.salaires.index', ['mois' => $mois]) }}" class="btn-secondary">← Retour</a>
    <button onclick="window.print()" class="btn-primary"><x-icon name="print" class="h-4 w-4" />Imprimer</button>
</div>

<article class="card mx-auto max-w-4xl p-8 text-sm print:max-w-none print:border-0 print:p-0">
    <header class="mb-6 border-b border-slate-300 pb-4 text-center text-xs leading-relaxed">
        <p class="font-semibold uppercase">{{ $entete['ministere'] }}</p>
        <p>{{ $entete['organisme'] }}</p>
        <p class="mt-1 text-sm font-bold">{{ $entete['etablissement'] }}</p>
        <p class="text-slate-600">NIF : {{ $entete['nif'] }} · STAT : {{ $entete['stat'] }}<br>{{ $entete['adresse'] }} · Tél : {{ $entete['tel'] }} · {{ $entete['email'] }}</p>
    </header>

    <h2 class="mb-1 text-center text-base font-bold uppercase">État de paie du personnel</h2>
    <p class="mb-5 text-center text-sm capitalize">{{ $periode }}</p>

    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="border-y border-slate-300 text-left text-xs uppercase text-slate-500">
                <th class="py-2">N°</th><th class="py-2">Employé</th><th class="py-2">Poste</th>
                <th class="py-2 text-right">Salaire</th><th class="py-2 text-right">Heures sup</th><th class="py-2 text-right">Total</th>
                <th class="py-2">Payé le</th><th class="py-2">Émargement</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($paiements as $i => $p)
            @php $hs = (float) $heuresSup->get($p->employe_id, collect())->sum('montant'); @endphp
            <tr class="border-b border-slate-200">
                <td class="py-2.5 text-slate-400">{{ $i + 1 }}</td>
                <td class="py-2.5 font-medium">{{ $p->employe->nom_complet }}</td>
                <td class="py-2.5 text-slate-600">{{ $p->employe->poste }}</td>
                <td class="py-2.5 text-right tabular-nums">{{ $ar($p->montant) }}</td>
                <td class="py-2.5 text-right tabular-nums">{{ $hs > 0 ? $ar($hs) : '—' }}</td>
                <td class="py-2.5 text-right font-semibold tabular-nums">{{ $ar((float) $p->montant + $hs) }}</td>
                <td class="py-2.5">{{ $p->date_paiement->format('d/m/Y') }}</td>
                <td class="w-28 py-2.5"></td>
            </tr>
        @empty
            <tr><td colspan="8" class="py-6 text-center text-slate-400">Aucun salaire versé pour ce mois.</td></tr>
        @endforelse
        </tbody>
        @if ($paiements->isNotEmpty())
        <tfoot>
            <tr class="border-t-2 border-slate-400 font-bold">
                <td colspan="3" class="py-3">TOTAL ({{ $paiements->count() }} employé{{ $paiements->count() > 1 ? 's' : '' }})</td>
                <td class="py-3 text-right tabular-nums">{{ $ar($totalSalaires) }}</td>
                <td class="py-3 text-right tabular-nums">{{ $ar($totalHs) }}</td>
                <td class="py-3 text-right tabular-nums">{{ $ar($totalSalaires + $totalHs) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        @endif
    </table>

    @if ($nonPayes > 0)
        <p class="mt-3 text-xs text-slate-500">{{ $nonPayes }} employé{{ $nonPayes > 1 ? 's' : '' }} actif{{ $nonPayes > 1 ? 's' : '' }} n'{{ $nonPayes > 1 ? 'ont' : 'a' }} pas encore été payé{{ $nonPayes > 1 ? 's' : '' }} pour ce mois.</p>
    @endif

    <div class="mt-14 grid grid-cols-2 gap-8 text-center text-xs">
        <div><p class="mb-12">Le Responsable administratif et financier</p><p>______________________</p></div>
        <div><p class="mb-12">Le Directeur</p><p>______________________</p></div>
    </div>
</article>
@endsection

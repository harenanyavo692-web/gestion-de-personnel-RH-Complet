@extends('layouts.app')
@section('title', 'Reçu de salaire ' . $numero)

@section('content')
@php
    $ar = fn ($v) => \App\Support\Fmt::ar($v);
    $salaire = (float) $paiement->montant;
    $hs = (float) $heuresSup->sum('montant');
@endphp
<div class="print:hidden mb-3 flex justify-between">
    <a href="{{ route('raf.salaires.index', ['mois' => $paiement->mois]) }}" class="btn-secondary">← Retour</a>
    <button onclick="window.print()" class="btn-primary"><x-icon name="print" class="h-4 w-4" />Imprimer</button>
</div>

<article class="card mx-auto max-w-2xl p-8 text-sm print:max-w-none print:border-0 print:p-0">
    <header class="mb-6 border-b border-slate-300 pb-4 text-center text-xs leading-relaxed">
        <p class="font-semibold uppercase">{{ $entete['ministere'] }}</p>
        <p>{{ $entete['organisme'] }}</p>
        <p class="mt-1 text-sm font-bold">{{ $entete['etablissement'] }}</p>
        <p class="text-slate-600">NIF : {{ $entete['nif'] }} · STAT : {{ $entete['stat'] }}<br>{{ $entete['adresse'] }} · Tél : {{ $entete['tel'] }} · {{ $entete['email'] }}</p>
    </header>

    <h2 class="mb-1 text-center text-base font-bold uppercase">Reçu de paiement de salaire</h2>
    <p class="mb-5 text-center text-sm capitalize">{{ $periode }}</p>

    <dl class="mb-5 space-y-1.5">
        <div class="flex justify-between"><dt class="text-slate-500">N° de reçu</dt><dd class="font-semibold">{{ $numero }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Bénéficiaire</dt><dd class="font-semibold">{{ $paiement->employe->nom_complet }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Matricule</dt><dd>{{ $paiement->employe->matricule ?: '—' }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Poste</dt><dd>{{ $paiement->employe->poste ?: '—' }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Date de paiement</dt><dd>{{ $paiement->date_paiement->format('d/m/Y') }}</dd></div>
    </dl>

    <table class="w-full border-collapse text-sm">
        <thead><tr class="border-y border-slate-300 text-left text-xs uppercase text-slate-500"><th class="py-2">Désignation</th><th class="py-2 text-right">Montant</th></tr></thead>
        <tbody>
            <tr class="border-b border-slate-200"><td class="py-2">Salaire du mois de {{ $periode }}</td><td class="py-2 text-right tabular-nums">{{ $ar($salaire) }}</td></tr>
            @foreach ($heuresSup as $h)
                <tr class="border-b border-slate-200 text-slate-600">
                    <td class="py-1.5 pl-3 text-xs">Heures supplémentaires du {{ $h->date->format('d/m/Y') }} — {{ rtrim(rtrim(number_format((float) $h->heures, 2, ',', ''), '0'), ',') }} h à +{{ $h->majoration }} %{{ $h->motif ? ' (' . $h->motif . ')' : '' }}</td>
                    <td class="py-1.5 text-right text-xs tabular-nums">{{ $ar($h->montant) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr class="border-t-2 border-slate-400 text-base font-bold"><td class="py-3">TOTAL PERÇU</td><td class="py-3 text-right tabular-nums">{{ $ar($salaire + $hs) }}</td></tr></tfoot>
    </table>

    <p class="mt-4 text-xs text-slate-500">Paiement enregistré par {{ $paiement->payeur?->name ?? '—' }}. Ce reçu atteste du versement du salaire pour la période indiquée.</p>

    <div class="mt-14 grid grid-cols-2 gap-8 text-center text-xs">
        <div><p class="mb-12">Le bénéficiaire<br><span class="text-slate-400">(précédé de « Lu et approuvé »)</span></p><p>______________________</p></div>
        <div><p class="mb-12">Le Responsable administratif et financier</p><p>______________________</p></div>
    </div>
</article>
@endsection

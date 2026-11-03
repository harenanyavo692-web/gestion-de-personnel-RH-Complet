@extends('layouts.app')
@section('title', $type === 'ordre' ? 'Ordre de versement' : 'Quittance de régie')

@section('content')
@php $ar = fn ($v) => \App\Support\Fmt::ar($v); $d = fn ($v) => $v?->format('d/m/Y') ?? '—'; @endphp
<div class="print:hidden mb-3 flex justify-between">
    <a href="{{ route('comptabilite.regie.index') }}" class="btn-secondary">← Retour</a>
    <button onclick="window.print()" class="btn-primary">Imprimer</button>
</div>

<article class="card mx-auto max-w-2xl p-8 text-sm print:max-w-none print:border-0 print:p-0">
    <header class="mb-6 border-b border-slate-300 pb-4 text-center text-xs leading-relaxed">
        <p class="font-semibold uppercase">{{ $entete['ministere'] }}</p>
        <p>{{ $entete['organisme'] }}</p>
        <p class="mt-1 text-sm font-bold">{{ $entete['etablissement'] }}</p>
        <p class="text-slate-600">NIF : {{ $entete['nif'] }} · STAT : {{ $entete['stat'] }}<br>{{ $entete['adresse'] }} · Tél : {{ $entete['tel'] }} · {{ $entete['email'] }}</p>
    </header>

    @if ($type === 'quittance')
        <h2 class="mb-4 text-center text-base font-bold">Quittance de régie — Journal du {{ $d($dossier->date_journal) }}</h2>
        <dl class="space-y-1.5">
            <div class="flex justify-between"><dt>N° quittance</dt><dd class="font-semibold">{{ $dossier->reference }}</dd></div>
            <div class="flex justify-between"><dt>Tunnel</dt><dd>{{ $ar($dossier->tunnel) }}</dd></div>
            <div class="flex justify-between"><dt>Chambre froide</dt><dd>{{ $ar($dossier->froid) }}</dd></div>
            <div class="flex justify-between"><dt>Transport</dt><dd>{{ $ar($dossier->transport) }}</dd></div>
            <div class="flex justify-between border-t border-slate-300 pt-2 text-base font-bold"><dt>TOTAL À VERSER</dt><dd>{{ $ar($dossier->montant) }}</dd></div>
        </dl>
        <p class="mt-12">Signature de la régisseuse : ______________________</p>
    @else
        <h2 class="mb-4 text-center text-base font-bold">Ordre de versement</h2>
        <dl class="space-y-1.5">
            <div class="flex justify-between"><dt>N° ordre</dt><dd class="font-semibold">{{ $dossier->ordre_versement_num }}</dd></div>
            <div class="flex justify-between"><dt>Réf. quittance</dt><dd>{{ $dossier->reference }}</dd></div>
            <div class="flex justify-between"><dt>Date du journal</dt><dd>{{ $d($dossier->date_journal) }}</dd></div>
            <div class="flex justify-between"><dt>Date de versement au Trésor</dt><dd>{{ $d($dossier->verse_le) }}</dd></div>
            <div class="flex justify-between"><dt>N° reçu Trésor</dt><dd>{{ $dossier->recu_tresor_num ?: '—' }}</dd></div>
            <div class="flex justify-between border-t border-slate-300 pt-2 text-base font-bold"><dt>MONTANT COMPTABILISÉ</dt><dd>{{ $ar($dossier->montant) }}</dd></div>
        </dl>
        <p class="mt-4 text-xs text-slate-500">Cet ordre atteste la réception du reçu du Trésor et clôture définitivement l'opération dans les comptes du CDPHM.</p>
        <p class="mt-12">Signature du RAF : ______________________</p>
    @endif
</article>
@endsection

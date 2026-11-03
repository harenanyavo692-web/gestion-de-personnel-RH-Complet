@extends('layouts.app')
@section('title', $lettre->type_libelle . ' — ' . $lettre->nom)

@section('content')
@php $d = fn ($v) => $v ? $v->translatedFormat('d F Y') : '…'; @endphp
<div class="print:hidden mb-3 flex justify-between"><a href="{{ route('raf.lettres') }}" class="btn-secondary"><x-icon name="list" class="h-4 w-4" />Registre des lettres</a><button onclick="window.print()" class="btn-primary"><x-icon name="print" class="h-4 w-4" />Imprimer</button></div>
<article class="card mx-auto max-w-3xl p-10 text-[15px] leading-relaxed print:max-w-none print:border-0 print:p-0">
    <header class="mb-8 border-b border-slate-300 pb-3 text-center text-xs leading-relaxed"><p class="font-semibold uppercase">{{ $entete['ministere'] }}</p><p class="font-bold">{{ $entete['etablissement'] }}</p><p class="text-slate-600">NIF : {{ $entete['nif'] }} · STAT : {{ $entete['stat'] }}<br>{{ $entete['adresse'] }} · Tél : {{ $entete['tel'] }}</p></header>
    <p class="text-right">Mahajanga, le {{ $lettre->created_at->translatedFormat('d F Y') }}</p>
    <p class="text-xs text-slate-500">Réf. {{ $lettre->reference }}</p>
    <h2 class="my-8 text-center text-lg font-bold uppercase">{{ $lettre->type_libelle }}</h2>

    @switch($lettre->type)
        @case('demission')
            <p>Je soussigné(e) <strong>{{ $lettre->nom }}</strong>, {{ $lettre->poste }} au CDPHM{{ $lettre->date_debut ? ' depuis le ' . $d($lettre->date_debut) : '' }}, ai l'honneur de vous présenter ma démission de mes fonctions, avec effet au <strong>{{ $d($lettre->date_fin) }}</strong>.</p>
            @break
        @case('renvoi')
            <p>Nous vous informons de la fin de votre contrat de travail en qualité de {{ $lettre->poste }}, <strong>{{ $lettre->nom }}</strong>, à compter du <strong>{{ $d($lettre->date_fin) }}</strong>.</p>
            @break
        @case('conge')
            <p>Il est accordé à <strong>{{ $lettre->nom }}</strong>, {{ $lettre->poste }}, une autorisation de congé du <strong>{{ $d($lettre->date_debut) }}</strong> au <strong>{{ $d($lettre->date_fin) }}</strong>.</p>
            @break
        @case('fin_stage')
            <p>Nous attestons que <strong>{{ $lettre->nom }}</strong> a effectué un stage au sein du CDPHM en qualité de {{ $lettre->poste }}, du <strong>{{ $d($lettre->date_debut) }}</strong> au <strong>{{ $d($lettre->date_fin) }}</strong>.</p>
            @break
        @case('attestation_travail')
            <p>Nous attestons que <strong>{{ $lettre->nom }}</strong> est employé(e) au CDPHM en qualité de {{ $lettre->poste }}{{ $lettre->date_debut ? ' depuis le ' . $d($lettre->date_debut) : '' }}.</p>
            @break
        @case('certificat_travail')
            <p>Nous certifions que <strong>{{ $lettre->nom }}</strong> a travaillé au CDPHM en qualité de {{ $lettre->poste }}, du <strong>{{ $d($lettre->date_debut) }}</strong> au <strong>{{ $d($lettre->date_fin) }}</strong>, et qu'il/elle est libre de tout engagement à la date de ce jour.</p>
            @break
        @case('note_service')
            <p>À l'attention de <strong>{{ $lettre->nom }}</strong>, {{ $lettre->poste }}. Vous êtes convoqué(e) le <strong>{{ $d($lettre->date_debut) }}</strong>.</p>
            @break
        @case('avertissement')
            <p>À l'attention de <strong>{{ $lettre->nom }}</strong>, {{ $lettre->poste }}. Nous vous adressons par la présente un avertissement formel.</p>
            @break
    @endswitch

    @if ($lettre->motif)<p class="mt-4 whitespace-pre-line">Motif : {{ $lettre->motif }}</p>@endif

    <p class="mt-6">Fait pour servir et valoir ce que de droit.</p>
    <p class="mt-16 text-right font-medium">{{ $lettre->signataire ?: 'La Direction' }}</p>
</article>
@endsection

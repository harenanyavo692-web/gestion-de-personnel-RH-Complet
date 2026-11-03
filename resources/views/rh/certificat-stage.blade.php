@extends('layouts.app')
@section('title', 'Certificat de stage ' . $s->certificat_numero)

@section('content')
@php $d = fn ($x) => $x->format('d/m/Y'); $jours = $s->date_debut->diffInDays($s->date_fin) + 1; @endphp
<div class="print:hidden mb-3 flex justify-between"><a href="{{ route('rh.stagiaires.index') }}" class="btn-secondary">← Stagiaires</a><button onclick="window.print()" class="btn-primary">Imprimer</button></div>
<article class="card mx-auto max-w-2xl p-10 text-sm leading-relaxed print:max-w-none print:border-0 print:p-0">
    <header class="mb-8 border-b border-slate-300 pb-4 text-center text-xs leading-relaxed">
        <p class="font-semibold uppercase">{{ $entete['ministere'] }}</p><p class="mt-1 text-sm font-bold">{{ $entete['etablissement'] }}</p>
        <p class="text-slate-600">NIF : {{ $entete['nif'] }} · STAT : {{ $entete['stat'] }}<br>{{ $entete['adresse'] }} · Tél : {{ $entete['tel'] }}</p>
    </header>

    <h2 class="mb-1 text-center text-lg font-bold uppercase tracking-wide">Certificat de stage</h2>
    <p class="mb-8 text-center text-xs text-slate-500">N° {{ $s->certificat_numero }}</p>

    <p>Nous soussignés, certifions que <strong>{{ $s->nom_complet }}</strong>, étudiant(e) à <strong>{{ $s->etablissement }}</strong>{{ $s->filiere ? ' (filière ' . $s->filiere . ')' : '' }},
       a effectué un stage au sein du CDPHM{{ $s->entite ? ', service ' . $s->entite->nom : '' }}, du <strong>{{ $d($s->date_debut) }}</strong> au <strong>{{ $d($s->date_fin) }}</strong>, soit {{ $jours }} jour{{ $jours > 1 ? 's' : '' }}.</p>

    @if ($s->tuteur)<p class="mt-3">Le stage s'est déroulé sous la responsabilité de <strong>{{ $s->tuteur->nom_complet }}</strong>, tuteur.</p>@endif
    @if ($s->appreciation)<p class="mt-3">Appréciation générale : <strong>{{ \App\Models\Stagiaire::APPRECIATIONS[$s->appreciation] }}</strong>.</p>@endif
    @if ($s->commentaire_certificat)<p class="mt-3 whitespace-pre-line">{{ $s->commentaire_certificat }}</p>@endif

    <p class="mt-6">En foi de quoi, le présent certificat lui est délivré pour servir et valoir ce que de droit.</p>
    <p class="mt-8 text-right">Fait à Mahajanga, le {{ $d($s->certificat_le) }}</p>
    <p class="mt-16 text-right font-medium">La Direction</p>
</article>
@endsection

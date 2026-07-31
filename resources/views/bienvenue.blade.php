@extends('layouts.app')
@section('title', 'Bienvenue')

@section('content')

<div class="max-w-3xl mx-auto py-10 md:py-16">

  <div class="text-center mb-14">
    <span class="inline-block text-xs font-medium tracking-widest uppercase text-teal-700 bg-teal-50 border border-teal-100 rounded-full px-3 py-1 mb-5">
      Espace RH
    </span>
    <h1 class="font-display font-bold text-4xl md:text-5xl text-ink leading-tight">
      Bienvenue{{ auth()->user() ? ', ' . auth()->user()->name : '' }}
    </h1>
    <p class="text-slate-500 text-base mt-4 max-w-xl mx-auto">
      Cet espace centralise les dossiers du personnel, les congés et les paramètres de votre équipe.
      Voici comment démarrer.
    </p>
  </div>

  {{-- Parcours en 3 étapes --}}
  <div class="relative">
    <svg class="hidden md:block absolute top-8 left-0 w-full" height="4" preserveAspectRatio="none" viewBox="0 0 100 4">
      <line x1="16" y1="2" x2="84" y2="2" stroke="#99D6CE" stroke-width="1.5" stroke-dasharray="3 3"/>
    </svg>

    <div class="grid md:grid-cols-3 gap-6 relative">

      <div class="rounded-2xl border border-line bg-white shadow-sm p-6">
        <div class="w-10 h-10 rounded-full bg-teal-600 text-white flex items-center justify-center font-display font-bold text-sm mb-4">1</div>
        <h3 class="font-display font-semibold text-ink text-lg mb-1.5">Complétez votre profil</h3>
        <p class="text-slate-500 text-sm leading-relaxed">
          Vérifiez votre nom, votre e-mail et votre mot de passe dans
          <a href="{{ route('parametres') }}" class="text-teal-700 font-medium hover:underline">Paramètres</a>.
        </p>
      </div>

      <div class="rounded-2xl border border-line bg-white shadow-sm p-6">
        <div class="w-10 h-10 rounded-full bg-teal-600 text-white flex items-center justify-center font-display font-bold text-sm mb-4">2</div>
        <h3 class="font-display font-semibold text-ink text-lg mb-1.5">Consultez les dossiers</h3>
        <p class="text-slate-500 text-sm leading-relaxed">
          Retrouvez les fiches de votre équipe, leurs contrats et leurs documents à jour.
        </p>
      </div>

      <div class="rounded-2xl border border-line bg-white shadow-sm p-6">
        <div class="w-10 h-10 rounded-full bg-amber-600 text-white flex items-center justify-center font-display font-bold text-sm mb-4">3</div>
        <h3 class="font-display font-semibold text-ink text-lg mb-1.5">Traitez les demandes</h3>
        <p class="text-slate-500 text-sm leading-relaxed">
          Validez les congés et absences en attente directement depuis le tableau de bord.
        </p>
      </div>

    </div>
  </div>

  {{-- Bon à savoir --}}
  <div class="mt-12 rounded-2xl border border-amber-100 bg-amber-50/60 p-6 flex items-start gap-4">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="mt-0.5 flex-shrink-0">
      <path d="M12 9v4M12 16.5h.01M10.29 3.86l-8.18 14.18A1.5 1.5 0 0 0 3.5 20.5h17a1.5 1.5 0 0 0 1.39-2.46L13.71 3.86a1.5 1.5 0 0 0-2.42 0Z"
            stroke="#B45309" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <p class="text-sm text-amber-900 leading-relaxed">
      <span class="font-semibold">Bon à savoir&nbsp;:</span>
      les modifications de dossiers sont enregistrées automatiquement. Contactez un administrateur en cas d'erreur.
    </p>
  </div>

  <div class="mt-10 flex justify-center">
    <a href="{{ route('dashboard') }}"
       class="rounded-xl bg-teal-600 text-white font-semibold px-8 py-3 text-sm shadow-[0_10px_24px_rgba(13,148,136,0.25)] hover:bg-teal-700 hover:-translate-y-0.5 transition">
      Accéder au tableau de bord
    </a>
  </div>

</div>

@endsection

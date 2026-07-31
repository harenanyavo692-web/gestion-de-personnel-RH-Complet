@extends('layouts.app')
@section('title', 'Paramètres')

@section('content')

<div class="mb-8">
  <h1 class="font-display font-bold text-3xl text-ink">Paramètres</h1>
  <p class="text-slate-500 text-sm mt-1">Compte et préférences de l'espace RH</p>
</div>

<form method="POST" action="{{ route('parametres.update') }}"
      class="rounded-2xl border border-line bg-white shadow-sm p-6 md:p-8 max-w-lg space-y-5">
  @csrf

  @if (session('status'))
    <div class="rounded-xl bg-teal-50 border border-teal-200 text-teal-800 text-sm px-4 py-3">
      {{ session('status') }}
    </div>
  @endif

  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Nom complet</label>
    <input name="name" value="{{ old('name', auth()->user()->name ?? '') }}" required
           class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm text-ink placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">E-mail</label>
    <input type="email" name="email" value="{{ old('email', auth()->user()->email ?? '') }}" required
           class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm text-ink placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
    @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Nouveau mot de passe</label>
    <input type="password" name="password" placeholder="Laisser vide pour ne pas changer"
           class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm text-ink placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
  </div>

  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Confirmer le mot de passe</label>
    <input type="password" name="password_confirmation"
           class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
  </div>

  <div class="flex justify-end pt-2">
    <button type="submit" class="rounded-xl bg-teal-600 text-white font-semibold px-6 py-2.5 text-sm shadow-[0_10px_24px_rgba(13,148,136,0.25)] hover:bg-teal-700 hover:-translate-y-0.5 transition">
      Enregistrer les modifications
    </button>
  </div>
</form>

@endsection

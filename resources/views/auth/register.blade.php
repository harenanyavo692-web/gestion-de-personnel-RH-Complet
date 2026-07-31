<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Inscription - {{ config('app.name', 'Gestion des personnels') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-paper text-ink min-h-screen font-sans antialiased">

        <nav class="border-b border-line bg-white/80 backdrop-blur-sm">
            <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
                <a href="{{ route('bienvenue') }}" class="font-display font-bold text-lg text-ink">
                    Gestion des personnels
                </a>
                <div class="flex items-center gap-4 text-sm">
                    <a href="{{ route('login') }}" class="text-slate-600 hover:text-teal-700 transition">Se connecter</a>
                </div>
            </div>
        </nav>

        <main class="max-w-5xl mx-auto px-6 py-10">
            <div class="max-w-md mx-auto">
                <h1 class="font-display font-bold text-3xl text-ink mb-8">Inscription</h1>

                <form method="POST" action="{{ route('register') }}" class="rounded-2xl border border-line bg-white shadow-sm p-6 space-y-5">
                    @csrf

                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Nom complet</label>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus
                               class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm text-ink placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
                        @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">E-mail</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm text-ink placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
                        @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Mot de passe</label>
                        <input type="password" name="password" required
                               class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm text-ink placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
                        @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Confirmer le mot de passe</label>
                        <input type="password" name="password_confirmation" required
                               class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
                    </div>

                    <div class="flex justify-between items-center">
                        <a href="{{ route('login') }}" class="text-sm text-teal-700 hover:underline">Déjà inscrit ?</a>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="rounded-xl bg-teal-600 text-white font-semibold px-6 py-2.5 text-sm shadow-[0_10px_24px_rgba(13,148,136,0.25)] hover:bg-teal-700 hover:-translate-y-0.5 transition">
                            S'inscrire
                        </button>
                    </div>
                </form>
            </div>
        </main>

    </body>
</html>

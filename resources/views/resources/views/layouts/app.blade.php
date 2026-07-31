<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Gestion des personnels') }} @hasSection('title') — @yield('title') @endif</title>

        <!-- PWA -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#0D9488">
        <link rel="icon" href="/icons/icon-192.png">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Alpine.js : pour l'interactivité des onglets, modales et tableaux -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    </head>
    <body class="bg-paper text-ink min-h-screen font-sans antialiased">

        <!-- Navbar flottante glassmorphism -->
        <nav class="fixed top-4 inset-x-4 md:inset-x-8 z-50 rounded-2xl bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-black/[0.04] px-5 py-3">
            <div class="max-w-6xl mx-auto flex items-center justify-between">
                <a href="{{ route('bienvenue') }}" class="flex items-center gap-2.5 font-display font-bold text-ink">
                    <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-400 to-teal-700 flex items-center justify-center text-white text-xs font-bold">RH</span>
                    <span class="hidden sm:inline">Gestion des personnels</span>
                </a>
                <div class="flex items-center gap-1 text-sm">
                    <a href="{{ route('dashboard') }}"
                       class="px-3.5 py-1.5 rounded-full text-slate-600 hover:text-teal-700 hover:bg-teal-50/80 transition font-medium">
                        Tableau de bord
                    </a>
                    <a href="{{ route('parametres') }}"
                       class="px-3.5 py-1.5 rounded-full text-slate-600 hover:text-teal-700 hover:bg-teal-50/80 transition font-medium">
                        Paramètres
                    </a>
                    <span class="w-px h-5 bg-line mx-1"></span>
                    <a href="{{ route('bienvenue') }}"
                       class="px-3.5 py-1.5 rounded-full text-slate-500 hover:text-rose-600 hover:bg-rose-50/80 transition font-medium">
                        Déconnexion
                    </a>
                </div>
            </div>
        </nav>

        <main class="max-w-6xl mx-auto px-6 pt-28 pb-16">
            @yield('content')
        </main>

        <!-- Service worker -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js');
                });
            }
        </script>
    </body>
</html>

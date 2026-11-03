<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · CDPHM</title>
    <script>
        try { var t = localStorage.getItem('theme'); if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center px-4">
    <button type="button" id="theme-toggle" class="icon-btn fixed right-4 top-4" aria-label="Basculer entre le mode clair et le mode sombre">
        <x-icon name="moon" class="dark:hidden" /><x-icon name="sun" class="hidden dark:block" />
    </button>

    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <span class="tile mx-auto mb-3 h-12 w-12 text-lg font-bold">C</span>
            <h1 class="text-xl font-semibold">CDPHM · ERP</h1>
            <p class="text-sm" style="color: var(--muted)">Connectez-vous à votre espace</p>
        </div>

        <form method="POST" action="{{ route('login.attempt') }}" class="card card-pad space-y-4">
            @csrf
            <x-field name="email" label="Adresse e-mail" type="email" required autofocus autocomplete="username" />
            <x-field name="password" label="Mot de passe" type="password" required autocomplete="current-password" />
            <label class="flex items-center gap-2 text-sm" style="color: var(--muted)">
                <input type="checkbox" name="remember" class="rounded border-slate-300"> Rester connecté
            </label>
            <button type="submit" class="btn-primary w-full">Se connecter</button>
        </form>
    </div>
    <div id="toasts" aria-live="polite"></div>
</body>
</html>

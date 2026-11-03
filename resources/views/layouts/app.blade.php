<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Tableau de bord') · CDPHM</title>
    {{-- Thème, sidebar et skeleton posés avant le premier rendu pour éviter tout clignotement --}}
    <script>
        try {
            var d = document.documentElement, t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) d.classList.add('dark');
            if (localStorage.getItem('sb-collapsed') === '1') d.classList.add('sb-collapsed');
            d.classList.add('is-loading');
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <x-sidebar />

    <div id="main" class="min-h-screen transition-all duration-200 lg:pl-64 print:pl-0">
        <header class="print:hidden sticky top-0 z-20 flex h-16 items-center gap-2 border-b px-4 backdrop-blur md:px-6" style="background: color-mix(in srgb, var(--surface) 88%, transparent); border-color: var(--line)">
            <button type="button" data-sidebar-toggle class="icon-btn" aria-label="Afficher / masquer le menu">
                <x-icon name="menu" class="lg:hidden" /><x-icon name="panel" class="hidden lg:block" />
            </button>

            {{-- Recherche rapide de page --}}
            <div class="relative ml-1 hidden w-full max-w-md sm:block" id="palette">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" style="color: var(--faint)" />
                <input type="search" id="palette-input" autocomplete="off" placeholder="Rechercher une page…" class="input !rounded-xl py-2 pl-9 pr-3" aria-label="Rechercher une page">
                <ul id="palette-list" class="menu absolute left-0 right-0 top-full z-30 mt-1 hidden max-h-80 overflow-y-auto py-1"></ul>
            </div>

            <div class="ml-auto flex items-center gap-1">
                <button type="button" id="fullscreen-toggle" class="icon-btn" aria-label="Plein écran" title="Plein écran">
                    <x-icon name="expand" class="h-[18px] w-[18px]" />
                </button>
                <button type="button" id="theme-toggle" class="icon-btn" aria-label="Basculer entre le mode clair et le mode sombre" title="Mode clair / sombre">
                    <x-icon name="moon" class="h-[18px] w-[18px] dark:hidden" /><x-icon name="sun" class="hidden h-[18px] w-[18px] dark:block" />
                </button>

                {{-- Notifications : tâches en attente que l'utilisateur peut traiter --}}
                <div class="relative">
                    <button type="button" class="icon-btn relative" data-menu="notifs" aria-label="Notifications">
                        <x-icon name="bell" class="h-[18px] w-[18px]" />
                        @if ($notifTotal > 0)<span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{{ $notifTotal }}</span>@endif
                    </button>
                    <div id="menu-notifs" class="menu absolute right-0 top-full z-30 mt-1 hidden w-72 overflow-hidden">
                        <p class="border-b px-3 py-2 text-xs font-semibold uppercase tracking-wide" style="border-color: var(--line); color: var(--faint)">À traiter</p>
                        @forelse ($notifs as $n)
                            <a href="{{ $n['url'] }}"><x-icon :name="$n['icon']" class="h-4 w-4 shrink-0" style="color: var(--brand)" /><span class="flex-1">{{ $n['label'] }}</span><span class="rounded-full bg-red-500/10 px-2 text-xs font-semibold text-red-500">{{ $n['count'] }}</span></a>
                        @empty
                            <p class="px-3 py-5 text-center text-sm" style="color: var(--faint)">Rien en attente.</p>
                        @endforelse
                    </div>
                </div>

                <div class="relative ml-1">
                    <button type="button" class="flex items-center gap-2 rounded-xl py-1 pl-1 pr-2 transition hover:opacity-80" data-menu="user" aria-label="Menu du compte">
                        <x-avatar :user="auth()->user()" class="h-9 w-9 text-sm" />
                        <span class="hidden text-left leading-tight md:block"><span class="block text-sm font-semibold">{{ auth()->user()->name }}</span><span class="block text-[11px]" style="color: var(--faint)">{{ auth()->user()->role?->libelle ?? 'Sans rôle' }}</span></span>
                        <x-icon name="chevron" class="hidden h-4 w-4 md:block" style="color: var(--faint)" />
                    </button>
                    <div id="menu-user" class="menu absolute right-0 top-full z-30 mt-1 hidden w-56 py-1">
                        <a href="{{ route('parametres.index') }}"><x-icon name="user" class="h-4 w-4" />Mon profil</a>
                        <form method="POST" action="{{ route('logout') }}" data-confirm="Voulez-vous vraiment vous déconnecter ?" data-confirm-label="Se déconnecter" data-confirm-tone="danger">
                            @csrf<button type="submit" class="menu-item"><x-icon name="logout" class="h-4 w-4" />Déconnexion</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="w-full px-4 py-5 md:px-6">
            {{-- Messages : affichés en toasts par le JavaScript ; version statique si JavaScript est désactivé --}}
            @php $flash = collect([['success', session('success')], ['error', session('error')]])->filter(fn ($m) => $m[1])->values(); @endphp
            <div id="flash" hidden data-flash="{{ json_encode($flash) }}"></div>
            <noscript>@foreach ($flash as [$t, $m])<div class="mb-4 rounded-md border px-4 py-2.5 text-sm">{{ $m }}</div>@endforeach</noscript>
            @if ($errors->any() && ! request()->routeIs('login'))
                <div role="alert" data-form-errors class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
                    <p class="font-medium">Veuillez corriger les points suivants :</p>
                    <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Squelette affiché pendant le chargement et les changements de page --}}
            <div id="page-skeleton" aria-hidden="true">
                <div class="skeleton mb-2 h-7 w-56"></div><div class="skeleton mb-6 h-4 w-80"></div>
                <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">@for ($i = 0; $i < 4; $i++)<div class="card card-pad"><div class="skeleton mb-3 h-3 w-24"></div><div class="skeleton mb-2 h-7 w-32"></div><div class="skeleton h-3 w-20"></div></div>@endfor</div>
                <div class="card"><div class="p-4"><div class="skeleton mb-4 h-4 w-40"></div>@for ($i = 0; $i < 6; $i++)<div class="skeleton mb-3 h-9 w-full"></div>@endfor</div></div>
            </div>

            <div id="page-content">@yield('content')</div>
        </main>
    </div>

    <div id="toasts" aria-live="polite"></div>
    <script>
        document.querySelectorAll('[data-open]').forEach((b) => { if (document.getElementById(b.dataset.open)) b.hidden = false; });
        document.addEventListener('click', (e) => {
            const b = e.target.closest('[data-open]');
            const cible = b && document.getElementById(b.dataset.open);
            if (!cible) return;
            cible.open = true;
            cible.scrollIntoView({ behavior: 'smooth', block: 'start' });
            cible.querySelector('input:not([type=hidden]), select, textarea')?.focus({ preventScroll: true });
        });
        // Formulaires d'ajout en fenêtre : titre + bouton de fermeture, Échap, clic sur le fond.
        document.querySelectorAll('details.form-modal').forEach((d) => {
            const titre = d.querySelector('summary')?.textContent.trim() || 'Nouveau';
            const panneau = document.createElement('div');
            panneau.className = 'modal-panel';
            panneau.innerHTML = '<div class="modal-head"><h2></h2><button type="button" class="icon-btn" aria-label="Fermer" data-modal-close><svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>';
            panneau.querySelector('h2').textContent = titre;
            [...d.children].filter((c) => c.tagName !== 'SUMMARY').forEach((c) => panneau.append(c));
            d.append(panneau);
            d.addEventListener('toggle', () => document.documentElement.classList.toggle('modal-open', !!document.querySelector('details.form-modal[open]')));
            d.addEventListener('click', (e) => { if (e.target === d || e.target.closest('[data-modal-close]')) d.open = false; });
            if (d.open) document.documentElement.classList.add('modal-open');
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.querySelectorAll('details.form-modal[open], details.pop[open]').forEach((d) => { d.open = false; }); });
        document.addEventListener('click', (e) => document.querySelectorAll('details.pop[open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; }));
        document.getElementById('fullscreen-toggle')?.addEventListener('click', () => {
            if (!document.fullscreenEnabled) return;
            document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen();
        });
    </script>
    <div id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
        <div class="box p-5">
            <div class="flex gap-3">
                <span id="confirm-icon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-500/10 text-red-500"><x-icon name="alert" /></span>
                <div><h3 id="confirm-title" class="font-semibold">Confirmation</h3><p id="confirm-message" class="mt-1 text-sm" style="color: var(--muted)"></p></div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" id="confirm-cancel" class="btn-secondary">Annuler</button>
                <button type="button" id="confirm-ok" class="btn-danger btn-sm !px-4 !py-2 rounded-lg text-sm font-medium">Confirmer</button>
            </div>
        </div>
    </div>
</body>
</html>

@php
    $user = auth()->user();

    // Chaque entrée : [libellé, route ou [route => permission, …], motifs de route actifs, permission, icône, libellés des pages fusionnées]
    // Une entrée fusionnée regroupe plusieurs pages (onglets) : elle mène à la première que l'utilisateur peut ouvrir.
    $groupes = [
        ['label' => 'Ressources humaines', 'items' => [
            ['Personnel', ['employes.index' => 'employes.voir', 'rh.stagiaires.index' => 'employes.voir', 'rh.contrats.index' => 'contrats.voir'], ['employes.*', 'rh.stagiaires.*', 'rh.contrats.*'], null, 'users', ['Employés', 'Stagiaires', 'Contrats']],
            ['Temps de travail', ['presences.index' => 'employes.voir', 'rh.conges.index' => 'conges.voir', 'rh.absences.index' => 'conges.voir'], ['presences.*', 'rh.conges.*', 'rh.absences.*'], null, 'clock', ['Présences', 'Congés', 'Absences']],
            ['Salaires & lettres', ['raf.salaires.index' => 'salaires.voir', 'raf.lettres' => 'lettres.generer'], ['raf.salaires.*', 'raf.lettres*'], null, 'receipt', ['Salaires & heures sup', 'Lettres RH']],
            ['Paramètres RH', ['rh.parametres.index' => 'rh.parametres.voir', 'rh.entites.index' => 'rh.entites.voir'], ['rh.parametres.*', 'rh.entites.*'], null, 'sliders', ['Paramètres RH', 'Entités RH']],
        ]],
        ['label' => 'Exploitation', 'items' => [
            ['Clients', 'exploitation.clients.index', 'exploitation.clients.*', 'clients.voir', 'user'],
            ['Lots & stock', 'exploitation.lots.index', 'exploitation.lots.*', 'lots.voir', 'box'],
            ['Transport', 'exploitation.transport.index', 'exploitation.transport.*', 'transport.voir', 'truck'],
        ]],
        ['label' => 'Comptabilité & caisse', 'items' => [
            ['Caisse & flux expéditions', 'caisse.index', 'caisse.*', 'caisse.voir', 'wallet'],
            ['Recettes & registres', ['comptabilite.recettes.index' => 'recettes.voir'], ['comptabilite.recettes.*', 'comptabilite.registres*', 'comptabilite.speciaux.*'], null, 'arrow-down', ['Recettes', 'Registres', 'Clients spéciaux']],
            ['Dépenses', ['comptabilite.depenses.index' => 'depenses.voir', 'raf.validations.index' => 'depenses.valider'], ['comptabilite.depenses.*', 'raf.validations.*'], null, 'arrow-up', ['Ordres de dépense', 'Validations']],
            ['Régie & Trésor', 'comptabilite.regie.index', 'comptabilite.regie.*', 'regie.voir', 'bank'],
            ['Tarifs & majoration', ['comptabilite.tarifs.index' => 'tarifs.voir'], ['comptabilite.tarifs.*', 'comptabilite.retards.*'], null, 'tag', ['Tarifs & paramètres', 'Majoration retard']],
            ['Rapports financiers', ['comptabilite.situation' => 'rapports.voir', 'raf.rapports.index' => 'rapports.voir', 'raf.rapprochements.index' => 'rapprochement.gerer'], ['comptabilite.situation*', 'raf.rapports.*', 'raf.rapprochements.*'], null, 'chart', ['Situation financière', 'État financier', 'Rapprochement Trésor']],
            ['Entités & budgets', ['comptabilite.entites.index' => 'compta.entites.voir', 'comptabilite.budgets.index' => 'budgets.voir', 'comptabilite.parametres.index' => 'compta.parametres.voir'], ['comptabilite.entites.*', 'comptabilite.budgets.*', 'comptabilite.parametres.*'], null, 'building', ['Entités comptables', 'Budgets', 'Paramètres comptabilité']],
        ]],
        ['label' => 'Système', 'items' => [
            ['Utilisateurs & rôles', ['admin.utilisateurs.index' => 'admin.utilisateurs', 'admin.roles.index' => 'admin.roles'], ['admin.utilisateurs.*', 'admin.roles.*'], null, 'users', ['Utilisateurs', 'Rôles & permissions']],
            ["Journal & corbeille", ['admin.audit' => 'journal', 'admin.corbeille.index' => 'corbeille'], ['admin.audit*', 'admin.corbeille.*'], null, 'log', ["Journal d'activité", 'Corbeille']],
        ]],
    ];

    $groupes = collect($groupes)->map(function ($g) use ($user) {
        // Une entrée fusionnée liste les routes possibles (route => permission) : elle mène à la première autorisée.
        $g['items'] = collect($g['items'])->map(function ($i) use ($user) {
            $routes = is_array($i[1]) ? $i[1] : [$i[1] => $i[3]];
            $ouvertes = collect($routes)->filter(fn ($perm) => $user->can($perm))->keys();
            if ($ouvertes->isEmpty()) {
                return null;
            }
            $libelles = $i[5] ?? [];
            $pages = $ouvertes->mapWithKeys(fn ($r) => [$r => $libelles[array_search($r, array_keys($routes), true)] ?? $i[0]]);

            return [$i[0], $ouvertes->first(), $i[2], null, $i[4], $pages];
        })->filter()->values();

        return $g;
    })->filter(fn ($g) => $g['items']->isNotEmpty());
@endphp

<div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"></div>

<aside id="sidebar" class="print:hidden fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r transition-all duration-200 lg:translate-x-0" style="background: var(--surface); border-color: var(--line)" aria-label="Navigation principale">
    <div class="flex h-16 shrink-0 items-center gap-2.5 px-4">
        <span class="tile h-9 w-9 rounded-lg text-sm font-bold">C</span>
        <div class="sb-label leading-tight"><p class="text-sm font-bold">CDPHM</p><p class="text-[11px]" style="color: var(--faint)">ERP · Mahajanga</p></div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 pb-3" id="sidebar-nav">
        <a href="{{ route('dashboard') }}" class="nav-link mt-1" @if (request()->routeIs('dashboard')) aria-current="page" @endif title="Tableau de bord" data-nav-label="Tableau de bord">
            <x-icon name="home" class="h-[18px] w-[18px]" /><span class="sb-label">Tableau de bord</span>
        </a>

        @foreach ($groupes as $g)
            @php $actif = $g['items']->contains(fn ($i) => request()->routeIs($i[2])); @endphp
            <details class="nav-group" @if ($actif || $loop->first) open @endif>
                <summary><span class="sb-label">{{ $g['label'] }}</span><x-icon name="chevron" class="sb-chevron h-3.5 w-3.5 transition group-open:rotate-180" /></summary>
                <ul class="sb-sub space-y-0.5">
                    @foreach ($g['items'] as [$libelle, $route, $motif, $perm, $icone, $pages])
                        <li>
                            <a href="{{ route($route) }}" class="nav-link" @if (request()->routeIs($motif)) aria-current="page" @endif title="{{ $libelle }}" data-nav-label="{{ $libelle }}">
                                <x-icon :name="$icone" class="h-[18px] w-[18px]" /><span class="sb-label">{{ $libelle }}</span>
                            </a>
                            {{-- Pages fusionnées : cherchables dans la recherche rapide --}}
                            @foreach ($pages as $r => $p)@if ($p !== $libelle)<a hidden href="{{ route($r) }}" data-nav-label="{{ $p }}"></a>@endif @endforeach
                        </li>
                    @endforeach
                </ul>
            </details>
        @endforeach
    </nav>

    <div class="shrink-0 border-t p-3" style="border-color: var(--line)">
        <a href="{{ route('parametres.index') }}" class="flex items-center gap-2.5 rounded-xl border p-2 transition hover:opacity-90" style="border-color: var(--line); background: var(--surface-2)" title="Mon profil">
            <x-avatar :user="$user" class="h-8 w-8 shrink-0 text-xs" />
            <div class="sb-label min-w-0 leading-tight">
                <p class="truncate text-xs font-semibold">{{ $user->name }}</p>
                <p class="flex items-center gap-1 text-[10px]" style="color: var(--faint)"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>En ligne</p>
            </div>
        </a>
    </div>
</aside>

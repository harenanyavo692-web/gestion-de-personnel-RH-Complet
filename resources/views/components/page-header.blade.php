@props(['title', 'subtitle' => null, 'icon' => null, 'add' => null, 'addLabel' => 'Ajouter'])
@php
    // Icône déduite de la section courante, sauf si la page en impose une.
    $icone = $icon ?? collect([
        'employes' => 'users', 'rh.stagiaires' => 'users', 'presences' => 'clock', 'rh.conges' => 'calendar', 'rh.absences' => 'alert',
        'rh.contrats' => 'doc', 'rh.entites' => 'building', 'rh.parametres' => 'sliders',
        'exploitation.clients' => 'user', 'exploitation.lots' => 'box', 'exploitation.transport' => 'truck',
        'caisse' => 'wallet', 'comptabilite.recettes' => 'arrow-down', 'comptabilite.registres' => 'book', 'comptabilite.depenses' => 'arrow-up',
        'comptabilite.regie' => 'bank', 'comptabilite.retards' => 'alert', 'comptabilite.tarifs' => 'tag', 'comptabilite.speciaux' => 'star',
        'comptabilite.situation' => 'chart', 'comptabilite.budgets' => 'wallet', 'comptabilite.entites' => 'building', 'comptabilite.parametres' => 'sliders',
        'raf.validations' => 'check', 'raf.salaires' => 'receipt', 'raf.rapprochements' => 'scale', 'raf.rapports' => 'chart', 'raf.lettres' => 'file',
        'admin.utilisateurs' => 'users', 'admin.roles' => 'key', 'admin.audit' => 'log', 'admin.corbeille' => 'trash', 'dashboard' => 'home', 'parametres' => 'user',
    ])->first(fn ($i, $prefixe) => request()->routeIs($prefixe, $prefixe . '.*'), 'list');
@endphp
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="flex items-center gap-3">
        <span class="tile h-11 w-11 print:hidden"><x-icon :name="$icone" class="h-5 w-5" /></span>
        <div>
            <h1 class="text-xl font-semibold text-slate-900">{{ $title }}</h1>
            @if ($subtitle)<p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>@endif
        </div>
    </div>
    @if (! $slot->isEmpty() || $add)
    <div class="flex flex-wrap items-center gap-2">
        {{ $slot }}
        {{-- Ouvre le formulaire d'ajout de la page ; masqué par le layout si l'utilisateur n'a pas le droit d'ajouter (formulaire absent). --}}
        @if ($add)<button type="button" class="btn-primary" data-open="{{ $add }}" hidden><x-icon name="plus" class="h-4 w-4" />{{ $addLabel }}</button>@endif
    </div>
    @endif
</div>

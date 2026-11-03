@props(['items'])
{{-- Onglets de navigation entre pages d'un même thème : [libellé, route, permission, icône, motif de route active]. --}}
<nav class="print:hidden mb-4 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Sections">
    @foreach ($items as [$libelle, $route, $perm, $icone, $motif])
        @can($perm)
        <a href="{{ route($route) }}" @if (request()->routeIs($motif)) aria-current="page" @endif
           class="inline-flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-sm {{ request()->routeIs($motif) ? 'border-brand font-medium text-brand' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <x-icon :name="$icone" class="h-4 w-4" />{{ $libelle }}
        </a>
        @endcan
    @endforeach
</nav>

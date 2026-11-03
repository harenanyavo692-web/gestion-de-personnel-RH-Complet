@props(['sections', 'actif', 'route'])
<nav class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Sections">
    @foreach ($sections as $k => $l)
        <a href="{{ route($route, ['section' => $k]) }}" @if ($actif === $k) aria-current="page" @endif
           class="whitespace-nowrap border-b-2 px-3 py-2 text-sm {{ $actif === $k ? 'border-brand font-medium text-brand' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $l }}</a>
    @endforeach
</nav>

@props(['key', 'title', 'subtitle' => null, 'charts' => [], 'height' => 'h-56', 'empty' => 'Pas encore de données à afficher.'])
{{-- Carte de graphique : affiche le canvas Chart.js, ou un état vide soigné quand il n'y a rien à tracer. --}}
<div {{ $attributes->merge(['class' => 'card card-pad']) }}>
    <h2 class="font-semibold">{{ $title }}</h2>
    @if ($subtitle)<p class="mb-3 text-xs" style="color: var(--faint)">{{ $subtitle }}</p>@endif
    @if (isset($charts[$key]))
        <div class="{{ $height }}"><canvas data-chart="{{ $key }}"></canvas></div>
    @else
        <div class="{{ $height }} flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed text-center" style="border-color: var(--line); background: var(--surface-2)">
            <span class="tile h-10 w-10 opacity-60"><x-icon name="chart" /></span>
            <p class="max-w-[16rem] text-sm" style="color: var(--muted)">{{ $empty }}</p>
        </div>
    @endif
</div>

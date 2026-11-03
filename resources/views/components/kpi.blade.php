@props(['label', 'value', 'hint' => null, 'tone' => 'neutral', 'icon' => null, 'spark' => null, 'trend' => null, 'trendUp' => true])
@php
    $color = ['success' => 'text-emerald-600', 'danger' => 'text-red-600', 'warning' => 'text-amber-600', 'neutral' => ''][$tone] ?? '';

    // Mini courbe (sparkline) : points normalisés dans un cadre 100 × 32.
    $points = '';
    if (is_array($spark) && count($spark) > 1) {
        $max = max($spark); $min = min($spark); $etendue = max($max - $min, 1e-9);
        $n = count($spark) - 1;
        $points = collect($spark)->map(fn ($v, $i) => round($i / $n * 100, 1) . ',' . round(30 - (($v - $min) / $etendue) * 26, 1))->implode(' ');
    }
@endphp
@if ($icon)
<div {{ $attributes->merge(['class' => 'card card-pad']) }}>
    <div class="flex items-start gap-3">
        <span class="tile"><x-icon :name="$icon" class="h-5 w-5" /></span>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ $label }}</p>
            <p class="mt-0.5 text-2xl font-semibold tabular-nums {{ $color }}">{{ $value }}</p>
        </div>
        @if ($points)
        <svg viewBox="0 0 100 32" class="mt-1 h-9 w-24 shrink-0" preserveAspectRatio="none" aria-hidden="true">
            <polyline points="{{ $points }}" fill="none" stroke="var(--brand)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
        </svg>
        @endif
    </div>
    @if ($trend)<p class="mt-2 text-xs font-medium {{ $trendUp ? 'text-emerald-600' : 'text-red-500' }}">{{ $trendUp ? '↑' : '↓' }} {{ $trend }}</p>@endif
    @if ($hint)<p class="{{ $trend ? '' : 'mt-2' }} text-[11px]" style="color: var(--faint)">{{ $hint }}</p>@endif
</div>
@else
@php $liseret = ['success' => 'bg-emerald-500', 'danger' => 'bg-red-500', 'warning' => 'bg-amber-500'][$tone] ?? 'bg-[var(--brand)]'; @endphp
<div {{ $attributes->merge(['class' => 'card card-pad relative overflow-hidden']) }}>
    <span class="absolute inset-y-0 left-0 w-1 {{ $liseret }}"></span>
    <p class="text-xs font-medium" style="color: var(--muted)">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums {{ $color }}">{{ $value }}</p>
    @if ($hint)<p class="mt-0.5 text-xs" style="color: var(--faint)">{{ $hint }}</p>@endif
</div>
@endif

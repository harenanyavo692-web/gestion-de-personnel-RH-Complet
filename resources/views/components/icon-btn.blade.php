@props(['icon', 'title', 'href' => null, 'tone' => 'default', 'type' => 'submit'])
@php
    $classes = 'icon-btn h-8 w-8 ' . match ($tone) {
        'danger' => 'hover:!bg-red-50 hover:!text-red-600 dark:hover:!bg-red-500/10',
        'brand' => '!text-[var(--brand)]',
        'success' => '!text-emerald-600 hover:!bg-emerald-50 dark:hover:!bg-emerald-500/10',
        default => '',
    };
@endphp
{{-- Action d'une ligne de tableau : icône seule, libellé en infobulle et pour les lecteurs d'écran. --}}
@if ($href)
    <a href="{{ $href }}" title="{{ $title }}" aria-label="{{ $title }}" {{ $attributes->merge(['class' => $classes]) }}><x-icon :name="$icon" class="h-4 w-4" /></a>
@else
    <button type="{{ $type }}" title="{{ $title }}" aria-label="{{ $title }}" {{ $attributes->merge(['class' => $classes]) }}><x-icon :name="$icon" class="h-4 w-4" /></button>
@endif

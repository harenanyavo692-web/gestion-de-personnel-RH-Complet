@props(['tone' => 'neutral'])
@php
    $classes = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'danger' => 'bg-red-50 text-red-700 ring-red-200',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'neutral' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ][$tone] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset $classes"]) }}><span class="mr-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-70"></span>{{ $slot }}</span>

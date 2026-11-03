@props(['name'])
{{-- Pastille d'initiales (2 lettres) pour identifier une personne dans un tableau. --}}
@php $mots = preg_split('/\s+/', trim($name)); $ini = mb_strtoupper(mb_substr($mots[0] ?? '', 0, 1) . mb_substr($mots[1] ?? '', 0, 1)); @endphp
<span {{ $attributes->merge(['class' => 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-semibold text-brand']) }} aria-hidden="true">{{ $ini }}</span>

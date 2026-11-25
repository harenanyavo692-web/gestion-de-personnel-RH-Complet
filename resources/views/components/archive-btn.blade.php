@props(['type', 'element'])
{{-- Archiver depuis la liste d'un module. Visible pour qui gère le module ; grisé, avec la raison en infobulle, quand l'élément ne peut pas encore l'être. --}}
@if (\App\Support\Elements::peutGerer(auth()->user(), $type))
    @if ($refus = \App\Support\Elements::refusArchivage($type, $element))
        <span class="inline-flex" title="{{ $refus }}"><button type="button" disabled aria-label="Archivage impossible : {{ $refus }}" class="icon-btn h-8 w-8 cursor-not-allowed opacity-40"><x-icon name="archive" class="h-4 w-4" /></button></span>
    @else
        <form method="POST" action="{{ route('admin.archives.archiver', [$type, $element->id]) }}" data-confirm="Archiver « {{ $element->libelleActivite() }} » ? Il quittera cette liste mais restera consultable dans Système › Archives." data-confirm-tone="success" data-confirm-label="Archiver">@csrf<x-icon-btn icon="archive" title="Archiver" /></form>
    @endif
@endif

@props(['type', 'element'])
{{-- Archiver depuis la liste d'un module : visible seulement si l'utilisateur gère ce module et que l'élément peut être archivé. --}}
@if (\App\Support\Elements::peutGerer(auth()->user(), $type) && ! \App\Support\Elements::refusArchivage($type, $element))
    <form method="POST" action="{{ route('admin.archives.archiver', [$type, $element->id]) }}" data-confirm="Archiver « {{ $element->libelleActivite() }} » ? Il quittera cette liste mais restera consultable dans Système › Archives." data-confirm-tone="success" data-confirm-label="Archiver">@csrf<x-icon-btn icon="archive" title="Archiver" /></form>
@endif

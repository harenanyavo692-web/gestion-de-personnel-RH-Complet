@props(['group'])
{{-- Bascule liste / grille ; le choix est mémorisé par navigateur (voir resources/js/app.js). --}}
<div class="inline-flex rounded-lg border p-0.5 print:hidden" style="border-color: var(--line); background: var(--surface)" role="group" aria-label="Affichage">
    <button type="button" data-view-mode="liste" data-view-target="{{ $group }}" class="rounded-md p-1.5" aria-label="Affichage en liste" title="Liste"><x-icon name="rows" class="h-4 w-4" /></button>
    <button type="button" data-view-mode="grille" data-view-target="{{ $group }}" class="rounded-md p-1.5" aria-label="Affichage en grille" title="Grille"><x-icon name="grid" class="h-4 w-4" /></button>
</div>

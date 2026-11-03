@props(['cols' => 1, 'message' => 'Aucune donnée.', 'icon' => 'list'])
<tr><td colspan="{{ $cols }}" class="px-4 py-12 text-center">
    <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
        <span class="flex h-12 w-12 items-center justify-center rounded-full" style="background: var(--surface-2); color: var(--faint)"><x-icon :name="$icon" class="h-5 w-5" /></span>
        <p class="text-sm" style="color: var(--muted)">{{ $message }}</p>
    </div>
</td></tr>

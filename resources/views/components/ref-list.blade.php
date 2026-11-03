@props(['titre', 'groupe', 'items', 'peutGerer' => false, 'absence' => false])
<div class="card">
    <div class="border-b border-slate-100 px-4 py-3"><h3 class="text-sm font-semibold">{{ $titre }}</h3></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Code</th><th>Libellé</th>@if ($absence)<th>Jours / an</th><th>Justificatif</th>@endif<th>Actif</th>@if ($peutGerer)<th></th>@endif</tr></thead>
            <tbody>
            @forelse ($items as $it)
                <tr class="{{ $it->actif ? '' : 'opacity-60' }}">
                    @if ($peutGerer)
                    <td class="font-mono text-xs">{{ $it->code }}</td>
                    <td><input form="ref-{{ $it->id }}" name="libelle" value="{{ $it->libelle }}" required maxlength="150" class="input py-1"></td>
                    @if ($absence)
                        <td><input form="ref-{{ $it->id }}" name="jours_annuels" type="number" min="0" max="366" value="{{ $it->meta['jours_annuels'] ?? '' }}" placeholder="—" class="input w-20 py-1"></td>
                        <td><input form="ref-{{ $it->id }}" type="checkbox" name="justificatif" value="1" @checked($it->meta['justificatif'] ?? false) class="rounded border-slate-300"></td>
                    @endif
                    <td><input form="ref-{{ $it->id }}" type="checkbox" name="actif" value="1" @checked($it->actif) class="rounded border-slate-300"></td>
                    <td class="text-right"><button form="ref-{{ $it->id }}" class="btn-secondary btn-sm">Enregistrer</button></td>
                    @else
                    <td class="font-mono text-xs">{{ $it->code }}</td><td>{{ $it->libelle }}</td>
                    @if ($absence)<td>{{ $it->meta['jours_annuels'] ?? '—' }}</td><td>{{ ($it->meta['justificatif'] ?? false) ? 'Oui' : 'Non' }}</td>@endif
                    <td><x-badge :tone="$it->actif ? 'success' : 'neutral'">{{ $it->actif ? 'Actif' : 'Inactif' }}</x-badge></td>
                    @endif
                </tr>
            @empty
                <x-empty :cols="6" message="Aucun élément." />
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($peutGerer)
    @foreach ($items as $it)
        <form id="ref-{{ $it->id }}" method="POST" action="{{ route('references.update', $it) }}">@csrf @method('PUT')</form>
    @endforeach
    @endif
    @if ($peutGerer)
    <form method="POST" action="{{ route('references.store') }}" class="flex flex-wrap items-end gap-2 border-t border-slate-100 p-3">
        @csrf
        <input type="hidden" name="groupe" value="{{ $groupe }}">
        <div><label class="label">Code</label><input name="code" required maxlength="40" pattern="[A-Za-z0-9_\-]+" class="input w-32 py-1.5" placeholder="EX_CODE"></div>
        <div class="min-w-[12rem] flex-1"><label class="label">Libellé</label><input name="libelle" required maxlength="150" class="input py-1.5"></div>
        @if ($absence)
            <div><label class="label">Jours / an</label><input name="jours_annuels" type="number" min="0" max="366" class="input w-24 py-1.5"></div>
            <label class="flex items-center gap-1.5 pb-2 text-sm"><input type="checkbox" name="justificatif" value="1" class="rounded border-slate-300">Justificatif</label>
        @endif
        <button class="btn-primary btn-sm self-end py-2"><x-icon name="plus" class="h-3.5 w-3.5" />Ajouter</button>
    </form>
    @endif
</div>

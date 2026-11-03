{{-- Rattachement comptable facultatif : variables fournies par le view composer (AppServiceProvider). --}}
<x-field name="accounting_entity_id" label="Entité comptable / centre de coûts" type="select">
    <option value="">—</option>@foreach ($entitesCompta as $e)<option value="{{ $e->id }}" @selected((int) old('accounting_entity_id') === $e->id)>{{ $e->code }} · {{ $e->nom }}</option>@endforeach
</x-field>
<x-field name="journal_id" label="Journal" type="select">
    <option value="">—</option>@foreach ($journauxCompta as $j)<option value="{{ $j->id }}" @selected((int) old('journal_id') === $j->id)>{{ $j->code }} · {{ $j->libelle }}</option>@endforeach
</x-field>
<x-field name="account_id" label="Compte comptable" type="select">
    <option value="">—</option>@foreach ($comptesCompta as $c)<option value="{{ $c->id }}" @selected((int) old('account_id') === $c->id)>{{ $c->numero }} · {{ $c->libelle }}</option>@endforeach
</x-field>
<x-field name="mode_paiement" label="Mode de paiement" type="select">
    <option value="">—</option>@foreach ($modesPaiement as $m)<option value="{{ $m->code }}" @selected(old('mode_paiement') === $m->code)>{{ $m->libelle }}</option>@endforeach
</x-field>

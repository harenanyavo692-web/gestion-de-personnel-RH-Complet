@php $e = $employe ?? null; @endphp
<div class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
    <x-field name="prenom" label="Prénom" :value="$e?->prenom" required />
    <x-field name="nom" label="Nom" :value="$e?->nom" required />
    <x-field name="email" label="E-mail" type="email" :value="$e?->email" required />
    <x-field name="telephone" label="Téléphone" :value="$e?->telephone" />
    <x-field name="poste" label="Poste" :value="$e?->poste" required list="liste-postes" />
    <datalist id="liste-postes">@foreach ($postes as $p)<option value="{{ $p }}">@endforeach</datalist>
    <x-field name="entity_id" label="Entité de rattachement (service / département)" type="select" required>
        <option value="">—</option>
        @foreach ($entites as $en)<option value="{{ $en->id }}" @selected((int) old('entity_id', $e?->entity_id) === $en->id)>{{ $en->code }} · {{ $en->nom }}</option>@endforeach
    </x-field>
    <x-field name="date_embauche" label="Date d'embauche" type="date" :value="$e?->date_embauche?->format('Y-m-d')" required />
    <x-field name="salaire" label="Salaire mensuel (Ar)" type="number" step="1" min="0" :value="$e?->salaire" />
    <x-field name="statut" label="Statut" type="select" required>
        @foreach (['actif', 'congé', 'suspendu', 'inactif'] as $s)<option value="{{ $s }}" @selected(old('statut', $e?->statut ?? 'actif') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </x-field>
    <x-field name="matricule" label="Matricule (auto si vide)" :value="$e?->matricule" />
    <x-field name="adresse" label="Adresse" type="textarea" :value="$e?->adresse" class="sm:col-span-2 lg:col-span-3 2xl:col-span-4" />
</div>
<div class="mt-4 flex justify-end gap-2">
    <a href="{{ route('employes.index') }}" class="btn-secondary">Annuler</a>
    <button type="submit" class="btn-primary">Enregistrer</button>
</div>

@extends('layouts.app')
@section('title', 'Tableau de bord')

@section('content')
@php
    $salut = now()->hour >= 18 || now()->hour < 5 ? 'Bonsoir' : 'Bonjour';
    $aucun = ! isset($rh) && ! isset($finance) && ! isset($stock) && ! isset($transport);
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-3xl font-light">{{ $salut }}, <strong class="font-bold">{{ $nomAffiche }}</strong></h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Voici un aperçu général de votre espace CDPHM.</p>
    </div>
    <div class="text-right"><p class="text-sm capitalize" style="color: var(--muted)" data-clock-date>{{ now()->translatedFormat('l j F Y') }}</p><p class="text-3xl font-bold tabular-nums" data-clock>{{ now()->format('H:i') }}</p></div>
</div>

@if ($aucun)
    <div class="card card-pad text-sm" style="color: var(--muted)">Aucun module n'est associé à votre rôle. Contactez un administrateur.</div>
@endif

{{-- Cartes de statistiques --}}
<div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @isset($finance)
        <x-kpi icon="wallet" label="Solde disponible" :value="\App\Support\Fmt::ar($finance['solde'])" :tone="$finance['solde'] < 0 ? 'danger' : 'neutral'" :spark="$finance['resultatSerie']" hint="Recettes − dépenses − salaires" />
        <x-kpi icon="arrow-down" label="Recettes du mois" :value="\App\Support\Fmt::ar($finance['recettes'])" :spark="$finance['recettesSerie']" :trend="$finance['recettesVar'][0]" :trend-up="$finance['recettesVar'][1]" />
        <x-kpi icon="arrow-up" label="Dépenses validées (mois)" :value="\App\Support\Fmt::ar($finance['depenses'])" :spark="$finance['depensesSerie']" :trend="$finance['depensesVar'][0]" :trend-up="! $finance['depensesVar'][1]" />
        <x-kpi icon="check" label="Validations en attente" :value="$finance['attente']" :hint="\App\Support\Fmt::ar($finance['attenteMontant']) . ' à valider'" :tone="$finance['attente'] ? 'warning' : 'neutral'" />
    @endisset
    @isset($rh)
        <x-kpi icon="users" label="Effectif" :value="$rh['effectif']" :spark="$rh['embauchesSerie']" :hint="$rh['actifs'] . ' actifs'" />
        <x-kpi icon="calendar" label="Absents aujourd'hui" :value="$rh['absents']" :hint="$rh['conges'] . ' en congé (statut)'" />
        <x-kpi icon="doc" label="Contrats à échéance (30 j)" :value="$rh['contratsEcheance']" :tone="$rh['contratsEcheance'] ? 'warning' : 'neutral'" hint="Contrats actifs arrivant à terme" />
        <x-kpi icon="clock" label="Demandes de congé en attente" :value="$rh['demandes']" :tone="$rh['demandes'] ? 'warning' : 'neutral'" hint="À valider selon le workflow RH" />
    @endisset
    @isset($stock)
        <x-kpi icon="box" label="Stock actuel" :value="number_format($stock['kg'], 0, ',', ' ') . ' kg'" :hint="$stock['lots'] . ' lot(s) en stock'" />
        <x-kpi icon="alert" label="Créances estimées" :value="\App\Support\Fmt::ar($stock['creances'])" tone="danger" hint="Lots non soldés" />
    @endisset
    @isset($transport)
        <x-kpi icon="truck" label="Missions à encaisser" :value="$transport['attente']" :hint="\App\Support\Fmt::ar($transport['encaisse']) . ' déjà encaissés'" />
    @endisset
</div>

<div class="mb-4 grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        @if (isset($finance))
        <x-chart-card key="evolution" title="Évolution des recettes et dépenses" subtitle="6 derniers mois · dépenses validées uniquement" :charts="$charts" height="h-64" empty="Les recettes et dépenses validées apparaîtront ici dès leur première saisie." />
        @endif
        <div class="grid gap-4 md:grid-cols-2">
            @isset($finance)
            <x-chart-card key="repartition" title="Recettes par catégorie" subtitle="6 derniers mois" :charts="$charts" empty="Aucune recette enregistrée sur la période." />
            <x-chart-card key="depenses_cat" title="Dépenses par catégorie" subtitle="6 derniers mois" :charts="$charts" empty="Aucune dépense validée sur la période." />
            @endisset
            @isset($rh)
            <x-chart-card key="effectif" title="Effectif par entité" subtitle="Employés rattachés à une entité" :charts="$charts" empty="Rattachez vos employés à une entité (Entités RH) pour voir leur répartition." />
            <x-chart-card key="statuts" title="Statuts du personnel" subtitle="Répartition actuelle" :charts="$charts" empty="Ajoutez des employés pour voir la répartition des statuts." />
            <x-chart-card key="absences" title="Jours d'absence validés" subtitle="6 derniers mois" :charts="$charts" class="md:col-span-2" empty="Aucune absence validée sur la période." />
            @endisset
            @isset($stock)
            <x-chart-card key="occupation" title="Occupation des infrastructures" subtitle="% de la capacité utilisée" :charts="$charts" class="md:col-span-2" empty="Aucune infrastructure à afficher." />
            @endisset
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <div class="mb-3 flex items-center justify-between"><h2 class="flex items-center gap-2 font-semibold"><span class="h-2 w-2 rounded-full" style="background: var(--brand)"></span>Activité récente</h2>
                @can('admin.audit')<a href="{{ route('admin.audit') }}" class="text-xs font-medium" style="color: var(--brand)">Voir tout</a>@endcan</div>
            <ul class="divide-y divide-slate-100">
                @forelse ($activite as $l)
                    <li class="flex items-start gap-3 py-2.5">
                        <x-avatar :user="$l->user ?? auth()->user()" class="h-9 w-9 shrink-0 text-xs" />
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium">{{ $l->details['libelle'] ?? $l->action }}</p>
                            <p class="truncate text-xs" style="color: var(--muted)">{{ $l->user?->name ?? 'Système' }} · <x-badge :tone="$l->tone">{{ $l->action }}</x-badge></p>
                            <p class="text-[11px]" style="color: var(--faint)">{{ $l->created_at->diffForHumans() }}</p></div>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm" style="color: var(--faint)">Aucune activité.</li>
                @endforelse
            </ul>
        </div>

        @if ($comptes->isNotEmpty())
        <div class="card card-pad">
            <h2 class="mb-3 font-semibold">Aperçu</h2>
            <ul class="space-y-2">
                @foreach ($comptes as [$libelle, $icone, $nombre, $url])
                    <li><a href="{{ $url }}" class="flex items-center gap-3 rounded-lg p-1.5 transition hover:bg-[var(--surface-2)]">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg" style="background: var(--brand-soft); color: var(--brand)"><x-icon :name="$icone" class="h-[18px] w-[18px]" /></span>
                        <span class="flex-1 text-sm">{{ $libelle }}</span><span class="text-sm font-semibold tabular-nums">{{ $nombre }}</span></a></li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>

@isset($finance)
<div class="grid gap-4 lg:grid-cols-2">
    @can('recettes.voir')
    <div class="card">
        <div class="flex items-center justify-between border-b px-4 py-3" style="border-color: var(--line)"><h3 class="text-sm font-semibold">Dernières recettes</h3><a href="{{ route('comptabilite.recettes.index') }}" class="text-xs font-medium" style="color: var(--brand)">Tout voir</a></div>
        <div class="table-wrap"><table class="data"><tbody>
            @forelse ($dernieresRecettes as $r)
                <tr><td>{{ $r->date_recette->format('d/m/Y') }}</td><td>{{ $r->client ?: $r->origine }}<p class="text-xs" style="color: var(--faint)">{{ $r->categorie->nom }}</p></td><td class="text-right font-medium tabular-nums">@ar($r->montant)</td></tr>
            @empty
                <x-empty :cols="3" message="Aucune recette enregistrée." />
            @endforelse
        </tbody></table></div>
    </div>
    @endcan
    @can('depenses.voir')
    <div class="card">
        <div class="flex items-center justify-between border-b px-4 py-3" style="border-color: var(--line)"><h3 class="text-sm font-semibold">Derniers ordres de dépense</h3><a href="{{ route('comptabilite.depenses.index') }}" class="text-xs font-medium" style="color: var(--brand)">Tout voir</a></div>
        <div class="table-wrap"><table class="data"><tbody>
            @forelse ($dernieresDepenses as $o)
                <tr>
                    <td>{{ $o->motif }}<p class="text-xs" style="color: var(--faint)">{{ $o->date_depense->format('d/m/Y') }} · {{ $o->categorie->nom }}</p></td>
                    <td><x-badge :tone="['validee' => 'success', 'refusee' => 'danger', 'en_attente' => 'warning'][$o->statut]">{{ $o->statut_libelle }}</x-badge></td>
                    <td class="text-right font-medium tabular-nums">@ar($o->montant)</td>
                </tr>
            @empty
                <x-empty :cols="3" message="Aucun ordre de dépense." />
            @endforelse
        </tbody></table></div>
    </div>
    @endcan
</div>
@endisset

@if (! empty($charts))
<script type="application/json" id="chart-data">{!! json_encode($charts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif
@endsection

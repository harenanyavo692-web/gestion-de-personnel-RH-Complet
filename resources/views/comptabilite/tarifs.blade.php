@extends('layouts.app')
@section('title', 'Tarifs & paramètres')

@section('content')
@php
    $edit = auth()->user()->can('tarifs.gerer');
    $calc = ['tunnel' => $p['tarifs.tunnel'], 'chambre' => $p['tarifs.stockage_chambre'], 'societe' => $p['tarifs.stockage_societe'],
        'crevettes' => $p['tarifs.stockage_crevettes'], 'inter' => $p['tarifs.transport_interurbain'], 'urbain' => $p['tarifs.transport_urbain'],
        'loc' => $p['tarifs.location'], 'ex4t' => $p['tarifs.exception_4t_tana']];
    $num = fn ($name, $val, $step = '0.01') => '<input type="number" name="' . $name . '" value="' . e($val) . '" min="0" step="' . $step . '" class="input w-28 py-1 text-right text-xs tabular-nums" ' . ($edit ? '' : 'disabled') . ' required>';
@endphp
<x-page-header title="Tarifs & paramètres" subtitle="Barèmes de facturation, calculateur et en-tête officielle des documents" />
@include('comptabilite._onglets-tarifs')

{{-- Calculateur --}}
<div class="card card-pad mb-6">
    <h2 class="mb-3 text-sm font-semibold">Calculateur de facturation</h2>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" id="calc">
        <div><label class="label">Prestation</label>
            <select id="c-type" class="input">
                <option value="tunnel">Tunnel</option><option value="stockage">Stockage chambre froide</option>
                <option value="interurbain">Transport interurbain</option><option value="urbain">Transport urbain</option><option value="location">Location camion</option>
            </select></div>
        <div data-for="tunnel stockage interurbain urbain"><label class="label">Poids (kg)</label><input id="c-poids" type="number" min="0" value="1000" class="input"></div>
        <div data-for="tunnel stockage location"><label class="label" id="c-duree-l">Durée</label><input id="c-duree" type="number" min="0" step="0.5" value="1" class="input"></div>
        <div data-for="stockage"><label class="label">Espèce</label>
            <select id="c-espece" class="input"><option value="chambre">Poisson / crabes / autres</option><option value="crevettes">Crevettes / langoustes</option><option value="societe">Tarif société</option></select></div>
        <div data-for="interurbain"><label class="label">Liaison</label><select id="c-liaison" class="input">@foreach ($p['tarifs.transport_interurbain'] as $k => $_)<option>{{ $k }}</option>@endforeach</select></div>
        <div data-for="interurbain"><label class="label">Carburant</label><select id="c-go" class="input"><option value="avecGO">Avec gasoil</option><option value="sansGO">Sans gasoil</option></select></div>
        <div data-for="interurbain urbain location"><label class="label">Camion</label><select id="c-camion" class="input"><option value="4T">4 tonnes</option><option value="5T">5 tonnes</option><option value="8T">8 tonnes</option></select></div>
        <div data-for="urbain"><label class="label">Zone</label><select id="c-zone" class="input">@foreach ($p['tarifs.transport_urbain'] as $k => $_)<option>{{ $k }}</option>@endforeach</select></div>
    </div>
    <p class="mt-4 text-lg font-semibold tabular-nums">Estimation : <span id="c-res" class="text-brand">—</span></p>
    <p class="text-xs text-slate-500" id="c-detail"></p>
</div>

<form method="POST" action="{{ route('comptabilite.tarifs.update') }}" class="space-y-6">
    @csrf
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-semibold">Tunnel & stockage (Ar / kg)</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between"><dt>Tunnel (par kg et par 24 h)</dt><dd>{!! $num('tunnel', $p['tarifs.tunnel']) !!}</dd></div>
                <div class="flex items-center justify-between"><dt>Stockage chambre (par kg et par jour)</dt><dd>{!! $num('stockage_chambre', $p['tarifs.stockage_chambre']) !!}</dd></div>
                <div class="flex items-center justify-between"><dt>Stockage société</dt><dd>{!! $num('stockage_societe', $p['tarifs.stockage_societe']) !!}</dd></div>
                <div class="flex items-center justify-between"><dt>Stockage crevettes / langoustes</dt><dd>{!! $num('stockage_crevettes', $p['tarifs.stockage_crevettes']) !!}</dd></div>
                <div class="flex items-center justify-between"><dt>Exception camion 4 T Mahajanga – Tananarivo (Ar/kg)</dt><dd>{!! $num('exception_4t_tana', $p['tarifs.exception_4t_tana']) !!}</dd></div>
            </dl>
        </div>
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-semibold">Location de camion (Ar / jour) et eau (Ar)</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between"><dt>Camion 5 T</dt><dd>{!! $num('location[5T]', $p['tarifs.location']['5T']) !!}</dd></div>
                <div class="flex items-center justify-between"><dt>Camion 8 T</dt><dd>{!! $num('location[8T]', $p['tarifs.location']['8T']) !!}</dd></div>
                <div class="flex items-center justify-between"><dt>Eau — poissons / crevettes</dt><dd>{!! $num('eau[poissons_crevettes]', $p['tarifs.eau']['poissons_crevettes']) !!}</dd></div>
                <div class="flex items-center justify-between"><dt>Eau — crabes traités</dt><dd>{!! $num('eau[crabes_traites]', $p['tarifs.eau']['crabes_traites']) !!}</dd></div>
                <div class="flex items-center justify-between"><dt>Eau — crabes non traités</dt><dd>{!! $num('eau[crabes_non_traites]', $p['tarifs.eau']['crabes_non_traites']) !!}</dd></div>
                <div class="flex items-center justify-between border-t border-slate-100 pt-2"><dt>Taux de majoration retard</dt><dd><a href="{{ route('comptabilite.retards.index') }}" class="text-brand hover:underline">{{ $p['majoration.taux_retard'] }} % — gérer</a></dd></div>
            </dl>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card table-wrap">
            <h2 class="px-4 pt-4 text-sm font-semibold">Transport interurbain (Ar / kg)</h2>
            <table class="data mt-2"><thead><tr><th>Liaison</th><th class="text-right">Avec gasoil</th><th class="text-right">Sans gasoil</th></tr></thead><tbody>
                @foreach ($p['tarifs.transport_interurbain'] as $k => $t)
                    <tr><td>{{ $k }}</td><td class="text-right">{!! $num("interurbain[" . $k . "][avecGO]", $t['avecGO']) !!}</td><td class="text-right">{!! $num("interurbain[" . $k . "][sansGO]", $t['sansGO']) !!}</td></tr>
                @endforeach
            </tbody></table>
        </div>
        <div class="card table-wrap">
            <h2 class="px-4 pt-4 text-sm font-semibold">Transport urbain / périphérique (Ar / tonne)</h2>
            <table class="data mt-2"><thead><tr><th>Zone</th><th class="text-right">Camion 5 T</th><th class="text-right">Camion 8 T</th></tr></thead><tbody>
                @foreach ($p['tarifs.transport_urbain'] as $k => $t)
                    <tr><td>{{ $k }}</td><td class="text-right">{!! $num("urbain[" . $k . "][camion5T]", $t['camion5T']) !!}</td><td class="text-right">{!! $num("urbain[" . $k . "][camion8T]", $t['camion8T']) !!}</td></tr>
                @endforeach
            </tbody></table>
        </div>
    </div>

    <div class="card card-pad">
        <h2 class="mb-3 text-sm font-semibold">En-tête officielle des documents imprimés</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach (['ministere' => 'Ministère', 'organisme' => 'Organisme', 'etablissement' => 'Établissement', 'nif' => 'NIF', 'stat' => 'STAT', 'tel' => 'Téléphone', 'email' => 'E-mail', 'adresse' => 'Adresse'] as $k => $l)
                <div><label class="label">{{ $l }}</label><input name="entete[{{ $k }}]" value="{{ old("entete.$k", $p['entete'][$k]) }}" class="input" @disabled(! $edit)></div>
            @endforeach
        </div>
    </div>

    @if ($edit)<div class="flex justify-end"><button class="btn-primary">Enregistrer les tarifs et paramètres</button></div>@endif
</form>

<script>
(function () {
    const P = {!! json_encode($calc) !!};
    const $ = (id) => document.getElementById(id);
    const fmt = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n) + ' Ar';
    function calc() {
        const t = $('c-type').value, kg = +$('c-poids').value || 0, d = +$('c-duree').value || 0;
        document.querySelectorAll('#calc [data-for]').forEach((el) => el.hidden = !el.dataset.for.split(' ').includes(t));
        $('c-duree-l').textContent = t === 'tunnel' ? 'Durée en tunnel (heures)' : (t === 'location' ? 'Jours de location' : 'Jours de stockage');
        let r = 0, det = '';
        if (t === 'tunnel') { r = kg * Math.max(d / 24, 1) * P.tunnel; det = `${kg} kg × ${Math.max(d / 24, 1).toFixed(2)} j × ${P.tunnel} Ar (hors majoration de retard)`; }
        if (t === 'stockage') { const k = $('c-espece').value, tarif = P[k]; r = kg * d * tarif; det = `${kg} kg × ${d} j × ${tarif} Ar`; }
        if (t === 'interurbain') {
            const l = $('c-liaison').value, tarif = P.inter[l][$('c-go').value];
            const ex = l === 'Mahajanga - Tananarivo' && $('c-camion').value === '4T';
            r = kg * (ex ? P.ex4t : tarif); det = `${kg} kg × ${ex ? P.ex4t + ' Ar (exception 4 T)' : tarif + ' Ar/kg'}`;
        }
        if (t === 'urbain') { const c = $('c-camion').value === '8T' ? 'camion8T' : 'camion5T'; r = P.urbain[$('c-zone').value][c] * (kg / 1000); det = `${P.urbain[$('c-zone').value][c]} Ar/t × ${(kg / 1000).toFixed(2)} t`; }
        if (t === 'location') { const c = $('c-camion').value === '8T' ? '8T' : '5T'; r = P.loc[c] * d; det = `${P.loc[c]} Ar/j × ${d} j`; }
        $('c-res').textContent = fmt(r); $('c-detail').textContent = det;
    }
    document.querySelectorAll('#calc select, #calc input').forEach((e) => e.addEventListener('input', calc));
    calc();
})();
</script>
@endsection

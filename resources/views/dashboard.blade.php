@extends('layouts.app')
@section('title', 'Tableau de bord')

@section('content')

{{-- Safelist Tailwind : classes complètes nécessaires au build, appliquées dynamiquement via Alpine plus bas.
     Ne pas supprimer ce bloc, sinon les badges couleur perdront leur style après un build de prod. --}}
<div class="hidden">
    bg-sky-50 text-sky-700 border-sky-200
    bg-violet-50 text-violet-700 border-violet-200
    bg-teal-50 text-teal-700 border-teal-200
    bg-amber-50 text-amber-700 border-amber-200
    bg-rose-50 text-rose-700 border-rose-200
    bg-lime-50 text-lime-700 border-lime-200
    bg-indigo-50 text-indigo-700 border-indigo-200
    bg-slate-50 text-slate-700 border-slate-200
</div>

<div
    x-data="dashboardApp({
        departements: {{ Illuminate\Support\Js::from($departements) }},
        employesInit: {{ Illuminate\Support\Js::from($employes) }},
        congesInit: {{ Illuminate\Support\Js::from($conges) }},
        presencesInit: {{ Illuminate\Support\Js::from($presences) }},
        salairesInit: {{ Illuminate\Support\Js::from($salaires) }},
        performancesInit: {{ Illuminate\Support\Js::from($performances) }},
        rhEvenementsInit: {{ Illuminate\Support\Js::from($rhEvenements) }},
        stagiairesInit: {{ Illuminate\Support\Js::from($stagiaires) }},
        blacklistInit: {{ Illuminate\Support\Js::from($blacklist) }}
    })"
    x-cloak
>
    {{-- En-tête --}}
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display font-bold text-3xl text-ink">Tableau de bord</h1>
            <p class="text-slate-500 text-sm mt-1">Gestion complète du personnel — CDPHM</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <div class="rounded-xl bg-white border border-line px-4 py-2.5 text-center min-w-[92px]">
                <div class="text-lg font-display font-bold text-ink" x-text="employes.length"></div>
                <div class="text-[10px] uppercase tracking-wide text-slate-500">Employés</div>
            </div>
            <div class="rounded-xl bg-white border border-line px-4 py-2.5 text-center min-w-[92px]">
                <div class="text-lg font-display font-bold text-amber-600" x-text="congesEnAttente"></div>
                <div class="text-[10px] uppercase tracking-wide text-slate-500">Cong. en attente</div>
            </div>
            <div class="rounded-xl bg-white border border-line px-4 py-2.5 text-center min-w-[92px]">
                <div class="text-lg font-display font-bold text-sky-600" x-text="stagiaires.filter(s => s.statut === 'en_cours').length"></div>
                <div class="text-[10px] uppercase tracking-wide text-slate-500">Stagiaires actifs</div>
            </div>
        </div>
    </div>

    {{-- Onglets --}}
    <div class="flex flex-wrap rounded-2xl bg-white border border-line p-1 mb-6 gap-1">
        <button @click="tab = 'personnel'" :class="tab === 'personnel' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-paper'" class="px-4 py-2 rounded-full text-sm font-medium transition">Personnel</button>
        <button @click="tab = 'conges'" :class="tab === 'conges' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-paper'" class="px-4 py-2 rounded-full text-sm font-medium transition">Congés</button>
        <button @click="tab = 'presence'" :class="tab === 'presence' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-paper'" class="px-4 py-2 rounded-full text-sm font-medium transition">Présence</button>
        <button @click="tab = 'salaires'" :class="tab === 'salaires' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-paper'" class="px-4 py-2 rounded-full text-sm font-medium transition">Salaires</button>
        <button @click="tab = 'performance'" :class="tab === 'performance' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-paper'" class="px-4 py-2 rounded-full text-sm font-medium transition">Performance</button>
        <button @click="tab = 'rh'" :class="tab === 'rh' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-paper'" class="px-4 py-2 rounded-full text-sm font-medium transition">Démission &amp; Renvoi</button>
        <button @click="tab = 'lettres'" :class="tab === 'lettres' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-paper'" class="px-4 py-2 rounded-full text-sm font-medium transition">Lettres &amp; Blacklist</button>
        <button @click="tab = 'stagiaires'" :class="tab === 'stagiaires' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-paper'" class="px-4 py-2 rounded-full text-sm font-medium transition">Stagiaires</button>
        <button @click="tab = 'apercu'" :class="tab === 'apercu' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-paper'" class="px-4 py-2 rounded-full text-sm font-medium transition">Vue d'ensemble</button>
    </div>

    {{-- ONGLET : PERSONNEL --}}
    <div x-show="tab === 'personnel'" x-transition.opacity>
        <div class="rounded-2xl border border-line bg-white shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 p-5 border-b border-line">
                <div class="flex items-center gap-3">
                    <input type="text" x-model="search" placeholder="Rechercher un employé..." class="rounded-xl bg-paper border border-line px-4 py-2 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <select x-model="filtreDept" class="rounded-xl bg-paper border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <option value="">Tous les départements</option>
                        <template x-for="(dep, key) in departements" :key="key">
                            <option :value="key" x-text="dep.label"></option>
                        </template>
                    </select>
                </div>
                <button @click="showEmployeModal = true" class="rounded-xl bg-teal-600 text-white font-semibold px-4 py-2 text-sm shadow-[0_10px_24px_rgba(13,148,136,0.25)] hover:bg-teal-700 transition">+ Nouvel employé</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Nom</th>
                            <th class="px-5 py-3 font-medium">Département</th>
                            <th class="px-5 py-3 font-medium">Poste</th>
                            <th class="px-5 py-3 font-medium">Contact</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="employe in employesFiltres" :key="employe.nom + employe.email">
                            <tr class="border-t border-line hover:bg-paper/60 transition">
                                <td class="px-5 py-3 font-medium text-ink" x-text="employe.nom"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border" :class="departements[employe.departement]?.badge" x-text="departements[employe.departement]?.label"></span>
                                </td>
                                <td class="px-5 py-3 text-slate-600" x-text="employe.poste"></td>
                                <td class="px-5 py-3 text-slate-500 text-xs">
                                    <div x-text="employe.email"></div>
                                    <div x-text="employe.telephone"></div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border" :class="badgeStatutEmploye(employe.statut)" x-text="labelStatutEmploye(employe.statut)"></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="employesFiltres.length === 0">
                            <td colspan="5" class="px-5 py-8 text-center text-slate-400 text-sm">Aucun employé ne correspond à la recherche.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ONGLET : CONGÉS & PERMISSIONS --}}
    <div x-show="tab === 'conges'" x-transition.opacity>
        <div class="rounded-2xl border border-line bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between gap-3 p-5 border-b border-line">
                <h2 class="font-display font-semibold text-ink">Demandes de congés &amp; permissions</h2>
                <button @click="showCongeModal = true" class="rounded-xl bg-teal-600 text-white font-semibold px-4 py-2 text-sm shadow-[0_10px_24px_rgba(13,148,136,0.25)] hover:bg-teal-700 transition">+ Nouvelle demande</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Employé</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Période</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(conge, index) in conges" :key="conge.employe + conge.debut">
                            <tr class="border-t border-line hover:bg-paper/60 transition">
                                <td class="px-5 py-3 font-medium text-ink" x-text="conge.employe"></td>
                                <td class="px-5 py-3 text-slate-600" x-text="conge.type"></td>
                                <td class="px-5 py-3 text-slate-500 text-xs" x-text="conge.debut + ' → ' + conge.fin"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border" :class="{ 'bg-teal-50 text-teal-700 border-teal-200': conge.statut === 'valide', 'bg-amber-50 text-amber-700 border-amber-200': conge.statut === 'attente', 'bg-rose-50 text-rose-700 border-rose-200': conge.statut === 'refuse' }" x-text="conge.statut === 'valide' ? 'Validé' : (conge.statut === 'attente' ? 'En attente' : 'Refusé')"></span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <template x-if="conge.statut === 'attente'">
                                        <div class="inline-flex gap-2">
                                            <button @click="conges[index].statut = 'valide'" class="rounded-lg bg-teal-50 text-teal-700 border border-teal-200 px-3 py-1.5 text-xs font-semibold hover:bg-teal-100 transition">Valider</button>
                                            <button @click="conges[index].statut = 'refuse'" class="rounded-lg bg-rose-50 text-rose-700 border border-rose-200 px-3 py-1.5 text-xs font-semibold hover:bg-rose-100 transition">Refuser</button>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ONGLET : PRÉSENCE --}}
    <div x-show="tab === 'presence'" x-transition.opacity>
        <div class="rounded-2xl border border-line bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between gap-3 p-5 border-b border-line">
                <h2 class="font-display font-semibold text-ink">Fiche de présence</h2>
                <button @click="showPresenceModal = true" class="rounded-xl bg-teal-600 text-white font-semibold px-4 py-2 text-sm shadow-[0_10px_24px_rgba(13,148,136,0.25)] hover:bg-teal-700 transition">+ Enregistrer un pointage</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Employé</th>
                            <th class="px-5 py-3 font-medium">Date</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium">Arrivée</th>
                            <th class="px-5 py-3 font-medium">Départ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(p, index) in [...presences].reverse()" :key="index">
                            <tr class="border-t border-line hover:bg-paper/60 transition">
                                <td class="px-5 py-3 font-medium text-ink" x-text="p.employe"></td>
                                <td class="px-5 py-3 text-slate-500 text-xs" x-text="p.date"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border" :class="{ 'bg-teal-50 text-teal-700 border-teal-200': p.statut === 'present', 'bg-amber-50 text-amber-700 border-amber-200': p.statut === 'retard', 'bg-rose-50 text-rose-700 border-rose-200': p.statut === 'absent' }" x-text="p.statut === 'present' ? 'Présent' : (p.statut === 'retard' ? 'Retard' : 'Absent')"></span>
                                </td>
                                <td class="px-5 py-3 text-slate-600 text-xs" x-text="p.arrivee"></td>
                                <td class="px-5 py-3 text-slate-600 text-xs" x-text="p.depart"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ONGLET : SALAIRES --}}
    <div x-show="tab === 'salaires'" x-transition.opacity>
        <div class="rounded-2xl border border-line bg-white shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 p-5 border-b border-line">
                <div>
                    <h2 class="font-display font-semibold text-ink">Paiement de salaire</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Masse salariale nette du mois : <span class="font-semibold text-ink" x-text="formatAr(masseSalariale)"></span></p>
                </div>
                <button @click="showSalaireModal = true" class="rounded-xl bg-teal-600 text-white font-semibold px-4 py-2 text-sm shadow-[0_10px_24px_rgba(13,148,136,0.25)] hover:bg-teal-700 transition">+ Nouvelle fiche de paie</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Employé</th>
                            <th class="px-5 py-3 font-medium">Mois</th>
                            <th class="px-5 py-3 font-medium">Base</th>
                            <th class="px-5 py-3 font-medium">Primes</th>
                            <th class="px-5 py-3 font-medium">Retenues</th>
                            <th class="px-5 py-3 font-medium">Net à payer</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(s, index) in salaires" :key="s.employe + s.mois">
                            <tr class="border-t border-line hover:bg-paper/60 transition">
                                <td class="px-5 py-3 font-medium text-ink" x-text="s.employe"></td>
                                <td class="px-5 py-3 text-slate-500 text-xs" x-text="s.mois"></td>
                                <td class="px-5 py-3 text-slate-600 text-xs" x-text="formatAr(s.salaireBase)"></td>
                                <td class="px-5 py-3 text-teal-700 text-xs" x-text="'+ ' + formatAr(s.primes)"></td>
                                <td class="px-5 py-3 text-rose-600 text-xs" x-text="'- ' + formatAr(s.retenues)"></td>
                                <td class="px-5 py-3 font-semibold text-ink text-xs" x-text="formatAr(s.salaireBase + s.primes - s.retenues)"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border" :class="s.statut === 'paye' ? 'bg-teal-50 text-teal-700 border-teal-200' : 'bg-amber-50 text-amber-700 border-amber-200'" x-text="s.statut === 'paye' ? 'Payé' : 'En attente'"></span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button x-show="s.statut === 'attente'" @click="salaires[index].statut = 'paye'" class="rounded-lg bg-teal-50 text-teal-700 border border-teal-200 px-3 py-1.5 text-xs font-semibold hover:bg-teal-100 transition">Marquer payé</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ONGLET : PERFORMANCE --}}
    <div x-show="tab === 'performance'" x-transition.opacity>
        <div class="rounded-2xl border border-line bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between gap-3 p-5 border-b border-line">
                <h2 class="font-display font-semibold text-ink">Fiche de performance</h2>
                <button @click="showPerformanceModal = true" class="rounded-xl bg-teal-600 text-white font-semibold px-4 py-2 text-sm shadow-[0_10px_24px_rgba(13,148,136,0.25)] hover:bg-teal-700 transition">+ Nouvelle évaluation</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Employé</th>
                            <th class="px-5 py-3 font-medium">Période</th>
                            <th class="px-5 py-3 font-medium">Ponctualité</th>
                            <th class="px-5 py-3 font-medium">Objectifs atteints</th>
                            <th class="px-5 py-3 font-medium">Note globale</th>
                            <th class="px-5 py-3 font-medium">Commentaire</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="perf in performances" :key="perf.employe + perf.periode">
                            <tr class="border-t border-line hover:bg-paper/60 transition align-top">
                                <td class="px-5 py-3 font-medium text-ink" x-text="perf.employe"></td>
                                <td class="px-5 py-3 text-slate-500 text-xs" x-text="perf.periode"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border"
                                          :class="ponctualite(perf.employe) >= 80 ? 'bg-teal-50 text-teal-700 border-teal-200' : (ponctualite(perf.employe) >= 50 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200')"
                                          x-text="ponctualite(perf.employe) + '% (auto, via présences)'"></span>
                                </td>
                                <td class="px-5 py-3 text-slate-600 text-xs" x-text="perf.objectifs + '%'"></td>
                                <td class="px-5 py-3 text-amber-500 text-sm" x-text="'★'.repeat(Math.round(perf.note)) + '☆'.repeat(5 - Math.round(perf.note)) + ' (' + perf.note + '/5)'"></td>
                                <td class="px-5 py-3 text-slate-500 text-xs max-w-xs" x-text="perf.commentaire"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ONGLET : DÉMISSION & RENVOI --}}
    <div x-show="tab === 'rh'" x-transition.opacity>
        <div class="rounded-2xl border border-line bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between gap-3 p-5 border-b border-line">
                <h2 class="font-display font-semibold text-ink">Démissions &amp; renvois</h2>
                <button @click="showRhModal = true" class="rounded-xl bg-teal-600 text-white font-semibold px-4 py-2 text-sm shadow-[0_10px_24px_rgba(13,148,136,0.25)] hover:bg-teal-700 transition">+ Nouvelle procédure</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Employé</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Date</th>
                            <th class="px-5 py-3 font-medium">Justification</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(r, index) in rhEvenements" :key="r.employe + r.date">
                            <tr class="border-t border-line hover:bg-paper/60 transition align-top">
                                <td class="px-5 py-3 font-medium text-ink" x-text="r.employe"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border" :class="r.type === 'demission' ? 'bg-slate-50 text-slate-700 border-slate-200' : 'bg-rose-50 text-rose-700 border-rose-200'" x-text="r.type === 'demission' ? 'Démission' : 'Renvoi'"></span>
                                </td>
                                <td class="px-5 py-3 text-slate-500 text-xs" x-text="r.date"></td>
                                <td class="px-5 py-3 text-slate-500 text-xs max-w-xs" x-text="r.motif"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border" :class="r.statut === 'finalise' ? 'bg-teal-50 text-teal-700 border-teal-200' : 'bg-amber-50 text-amber-700 border-amber-200'" x-text="r.statut === 'finalise' ? 'Finalisé' : 'En cours'"></span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button x-show="r.statut === 'en_cours'" @click="finaliserRh(index)" class="rounded-lg bg-teal-50 text-teal-700 border border-teal-200 px-3 py-1.5 text-xs font-semibold hover:bg-teal-100 transition">Finaliser</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ONGLET : LETTRES & BLACKLIST --}}
    <div x-show="tab === 'lettres'" x-transition.opacity>
        <div class="grid lg:grid-cols-2 gap-6">

            {{-- Lettre de recommandation --}}
            <div class="rounded-2xl border border-line bg-white shadow-sm p-6">
                <h2 class="font-display font-semibold text-ink mb-4">Lettre de recommandation</h2>
                <form @submit.prevent="genererRecommandation()" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Employé</label>
                        <select x-model="recommandationForm.employe" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="">— Choisir —</option>
                            <template x-for="e in employes" :key="e.nom">
                                <option :value="e.nom" x-text="e.nom"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Appréciation</label>
                        <textarea x-model="recommandationForm.appreciation" rows="3" required placeholder="Ex : a fait preuve de sérieux, de rigueur et d'un excellent esprit d'équipe tout au long de sa collaboration avec le CDPHM."
                                  class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                    </div>
                    <button type="submit" class="rounded-xl bg-teal-600 text-white font-semibold px-5 py-2.5 text-sm hover:bg-teal-700 transition">Générer la lettre</button>
                </form>

                <div class="mt-6 space-y-2" x-show="lettres.length > 0">
                    <p class="text-xs uppercase tracking-wide text-slate-500 font-medium">Historique</p>
                    <template x-for="(l, index) in [...lettres].reverse()" :key="index">
                        <div class="flex items-center justify-between rounded-xl bg-paper border border-line px-4 py-2.5">
                            <div class="text-sm">
                                <span class="font-medium text-ink" x-text="l.titre"></span>
                                <span class="text-slate-400 text-xs"> · </span>
                                <span class="text-slate-500 text-xs" x-text="l.date"></span>
                            </div>
                            <button @click="lettreActive = l" class="text-xs font-semibold text-teal-700 hover:underline">Aperçu / Imprimer</button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Liste noire --}}
            <div class="rounded-2xl border border-line bg-white shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-display font-semibold text-ink">Liste noire</h2>
                    <button @click="showBlacklistModal = true" class="rounded-xl bg-rose-50 text-rose-700 border border-rose-200 font-semibold px-3 py-1.5 text-xs hover:bg-rose-100 transition">+ Ajouter</button>
                </div>
                <div class="space-y-2">
                    <template x-for="b in [...blacklist].reverse()" :key="b.nom + b.date">
                        <div class="rounded-xl bg-rose-50/50 border border-rose-100 px-4 py-3">
                            <div class="flex justify-between items-baseline">
                                <span class="font-medium text-ink text-sm" x-text="b.nom"></span>
                                <span class="text-xs text-slate-500" x-text="b.date"></span>
                            </div>
                            <p class="text-xs text-slate-600 mt-1" x-text="b.motif"></p>
                        </div>
                    </template>
                    <p x-show="blacklist.length === 0" class="text-sm text-slate-400">Aucune entrée pour le moment.</p>
                </div>
            </div>

        </div>
    </div>

    {{-- ONGLET : STAGIAIRES --}}
    <div x-show="tab === 'stagiaires'" x-transition.opacity>
        <div class="rounded-2xl border border-line bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between gap-3 p-5 border-b border-line">
                <h2 class="font-display font-semibold text-ink">Stagiaires</h2>
                <button @click="showStagiaireModal = true" class="rounded-xl bg-teal-600 text-white font-semibold px-4 py-2 text-sm shadow-[0_10px_24px_rgba(13,148,136,0.25)] hover:bg-teal-700 transition">+ Nouveau stagiaire</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Nom</th>
                            <th class="px-5 py-3 font-medium">Département</th>
                            <th class="px-5 py-3 font-medium">Tuteur</th>
                            <th class="px-5 py-3 font-medium">Période</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(st, index) in stagiaires" :key="st.nom">
                            <tr class="border-t border-line hover:bg-paper/60 transition">
                                <td class="px-5 py-3 font-medium text-ink" x-text="st.nom"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border" :class="departements[st.departement]?.badge" x-text="departements[st.departement]?.label"></span>
                                </td>
                                <td class="px-5 py-3 text-slate-600 text-xs" x-text="st.tuteur"></td>
                                <td class="px-5 py-3 text-slate-500 text-xs" x-text="st.debut + ' → ' + st.fin"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border" :class="st.statut === 'en_cours' ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-teal-50 text-teal-700 border-teal-200'" x-text="st.statut === 'en_cours' ? 'En cours' : 'Terminé'"></span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button @click="genererAttestationStage(index)" class="rounded-lg bg-teal-50 text-teal-700 border border-teal-200 px-3 py-1.5 text-xs font-semibold hover:bg-teal-100 transition">Attestation de fin de stage</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ONGLET : VUE D'ENSEMBLE --}}
    <div x-show="tab === 'apercu'" x-transition.opacity>
        <div class="grid md:grid-cols-2 gap-6">
            <div class="rounded-2xl border border-line bg-white shadow-sm p-6">
                <h2 class="font-display font-semibold text-ink mb-4">Répartition des effectifs</h2>
                <div class="space-y-3">
                    <template x-for="(dep, key) in departements" :key="key">
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-600 font-medium" x-text="dep.label"></span>
                                <span class="text-slate-400" x-text="compteParDepartement(key) + ' employé(s)'"></span>
                            </div>
                            <div class="h-2 rounded-full bg-paper overflow-hidden">
                                <div class="h-full rounded-full bg-teal-600 transition-all" :style="`width: ${employes.length ? (compteParDepartement(key) / employes.length * 100) : 0}%`"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="rounded-2xl border border-line bg-white shadow-sm p-6">
                <h2 class="font-display font-semibold text-ink mb-4">Congés en attente de validation</h2>
                <div class="space-y-2">
                    <template x-for="conge in conges.filter(c => c.statut === 'attente')" :key="conge.employe + conge.debut">
                        <div class="flex items-center justify-between rounded-xl bg-amber-50/60 border border-amber-100 px-4 py-3">
                            <div>
                                <div class="text-sm font-medium text-ink" x-text="conge.employe"></div>
                                <div class="text-xs text-slate-500" x-text="conge.type + ' · ' + conge.debut"></div>
                            </div>
                            <button @click="tab = 'conges'" class="text-xs font-semibold text-teal-700 hover:underline">Traiter →</button>
                        </div>
                    </template>
                    <p x-show="conges.filter(c => c.statut === 'attente').length === 0" class="text-sm text-slate-400">Aucune demande en attente. Tout est à jour 🎉</p>
                </div>
            </div>

            <div class="rounded-2xl border border-line bg-white shadow-sm p-6">
                <h2 class="font-display font-semibold text-ink mb-4">Masse salariale (mois en cours)</h2>
                <p class="text-3xl font-display font-bold text-ink" x-text="formatAr(masseSalariale)"></p>
                <p class="text-xs text-slate-500 mt-1" x-text="salaires.filter(s => s.statut === 'attente').length + ' fiche(s) en attente de paiement'"></p>
            </div>

            <div class="rounded-2xl border border-line bg-white shadow-sm p-6">
                <h2 class="font-display font-semibold text-ink mb-4">Mouvements RH récents</h2>
                <div class="space-y-2">
                    <template x-for="r in [...rhEvenements].reverse().slice(0, 4)" :key="r.employe + r.date">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-ink" x-text="r.employe"></span>
                            <span class="text-xs text-slate-500" x-text="(r.type === 'demission' ? 'Démission' : 'Renvoi') + ' · ' + r.date"></span>
                        </div>
                    </template>
                    <p x-show="rhEvenements.length === 0" class="text-sm text-slate-400">Aucun mouvement récent.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== MODALES ========== --}}

    {{-- Nouvel employé --}}
    <div x-show="showEmployeModal" x-transition.opacity class="fixed inset-0 z-[60] bg-ink/40 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div @click.outside="showEmployeModal = false" class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
            <h3 class="font-display font-semibold text-lg text-ink mb-4">Nouvel employé</h3>
            <form @submit.prevent="ajouterEmploye()" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Nom complet</label>
                    <input x-model="nouvelEmploye.nom" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Département</label>
                    <select x-model="nouvelEmploye.departement" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <template x-for="(dep, key) in departements" :key="key">
                            <option :value="key" x-text="dep.label"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Poste</label>
                    <input x-model="nouvelEmploye.poste" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">E-mail</label>
                        <input x-model="nouvelEmploye.email" type="email" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Téléphone</label>
                        <input x-model="nouvelEmploye.telephone" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showEmployeModal = false" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-paper transition">Annuler</button>
                    <button type="submit" class="rounded-xl bg-teal-600 text-white font-semibold px-5 py-2.5 text-sm hover:bg-teal-700 transition">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Nouvelle demande de congé --}}
    <div x-show="showCongeModal" x-transition.opacity class="fixed inset-0 z-[60] bg-ink/40 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div @click.outside="showCongeModal = false" class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
            <h3 class="font-display font-semibold text-lg text-ink mb-4">Nouvelle demande</h3>
            <form @submit.prevent="ajouterConge()" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Employé</label>
                    <select x-model="nouveauConge.employe" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <template x-for="employe in employes" :key="employe.nom">
                            <option :value="employe.nom" x-text="employe.nom"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Type</label>
                    <select x-model="nouveauConge.type" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <option>Congé annuel</option>
                        <option>Permission</option>
                        <option>Congé maladie</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Début</label>
                        <input x-model="nouveauConge.debut" type="date" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Fin</label>
                        <input x-model="nouveauConge.fin" type="date" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showCongeModal = false" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-paper transition">Annuler</button>
                    <button type="submit" class="rounded-xl bg-teal-600 text-white font-semibold px-5 py-2.5 text-sm hover:bg-teal-700 transition">Envoyer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Nouveau pointage --}}
    <div x-show="showPresenceModal" x-transition.opacity class="fixed inset-0 z-[60] bg-ink/40 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div @click.outside="showPresenceModal = false" class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
            <h3 class="font-display font-semibold text-lg text-ink mb-4">Enregistrer un pointage</h3>
            <form @submit.prevent="ajouterPresence()" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Employé</label>
                    <select x-model="nouvellePresence.employe" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <template x-for="e in employes" :key="e.nom">
                            <option :value="e.nom" x-text="e.nom"></option>
                        </template>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Date</label>
                        <input x-model="nouvellePresence.date" type="date" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Statut</label>
                        <select x-model="nouvellePresence.statut" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="present">Présent</option>
                            <option value="retard">Retard</option>
                            <option value="absent">Absent</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Heure d'arrivée</label>
                        <input x-model="nouvellePresence.arrivee" type="time" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Heure de départ</label>
                        <input x-model="nouvellePresence.depart" type="time" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showPresenceModal = false" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-paper transition">Annuler</button>
                    <button type="submit" class="rounded-xl bg-teal-600 text-white font-semibold px-5 py-2.5 text-sm hover:bg-teal-700 transition">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Nouvelle fiche de paie --}}
    <div x-show="showSalaireModal" x-transition.opacity class="fixed inset-0 z-[60] bg-ink/40 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div @click.outside="showSalaireModal = false" class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
            <h3 class="font-display font-semibold text-lg text-ink mb-4">Nouvelle fiche de paie</h3>
            <form @submit.prevent="ajouterSalaire()" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Employé</label>
                    <select x-model="nouveauSalaire.employe" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <template x-for="e in employes" :key="e.nom">
                            <option :value="e.nom" x-text="e.nom"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Mois</label>
                    <input x-model="nouveauSalaire.mois" placeholder="Ex : Août 2026" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Base (Ar)</label>
                        <input x-model.number="nouveauSalaire.salaireBase" type="number" min="0" required class="w-full rounded-xl bg-paper border border-line px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Primes (Ar)</label>
                        <input x-model.number="nouveauSalaire.primes" type="number" min="0" class="w-full rounded-xl bg-paper border border-line px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Retenues (Ar)</label>
                        <input x-model.number="nouveauSalaire.retenues" type="number" min="0" class="w-full rounded-xl bg-paper border border-line px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showSalaireModal = false" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-paper transition">Annuler</button>
                    <button type="submit" class="rounded-xl bg-teal-600 text-white font-semibold px-5 py-2.5 text-sm hover:bg-teal-700 transition">Créer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Nouvelle évaluation de performance --}}
    <div x-show="showPerformanceModal" x-transition.opacity class="fixed inset-0 z-[60] bg-ink/40 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div @click.outside="showPerformanceModal = false" class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
            <h3 class="font-display font-semibold text-lg text-ink mb-4">Nouvelle évaluation</h3>
            <form @submit.prevent="ajouterPerformance()" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Employé</label>
                    <select x-model="nouvellePerformance.employe" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <template x-for="e in employes" :key="e.nom">
                            <option :value="e.nom" x-text="e.nom"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Période</label>
                    <input x-model="nouvellePerformance.periode" placeholder="Ex : T4 2026" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Objectifs atteints (%)</label>
                        <input x-model.number="nouvellePerformance.objectifs" type="number" min="0" max="100" required class="w-full rounded-xl bg-paper border border-line px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Note globale (/5)</label>
                        <input x-model.number="nouvellePerformance.note" type="number" min="0" max="5" step="0.1" required class="w-full rounded-xl bg-paper border border-line px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Commentaire</label>
                    <textarea x-model="nouvellePerformance.commentaire" rows="2" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showPerformanceModal = false" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-paper transition">Annuler</button>
                    <button type="submit" class="rounded-xl bg-teal-600 text-white font-semibold px-5 py-2.5 text-sm hover:bg-teal-700 transition">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Nouvelle procédure démission / renvoi --}}
    <div x-show="showRhModal" x-transition.opacity class="fixed inset-0 z-[60] bg-ink/40 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div @click.outside="showRhModal = false" class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
            <h3 class="font-display font-semibold text-lg text-ink mb-4">Nouvelle procédure</h3>
            <form @submit.prevent="ajouterRhEvenement()" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Employé</label>
                    <select x-model="nouveauRh.employe" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <template x-for="e in employes" :key="e.nom">
                            <option :value="e.nom" x-text="e.nom"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Type</label>
                    <select x-model="nouveauRh.type" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <option value="demission">Démission</option>
                        <option value="renvoi">Renvoi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Date</label>
                    <input x-model="nouveauRh.date" type="date" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Justification</label>
                    <textarea x-model="nouveauRh.motif" rows="3" required placeholder="Motif détaillé (obligatoire, à conserver au dossier)"
                              class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showRhModal = false" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-paper transition">Annuler</button>
                    <button type="submit" class="rounded-xl bg-teal-600 text-white font-semibold px-5 py-2.5 text-sm hover:bg-teal-700 transition">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Nouvelle entrée liste noire --}}
    <div x-show="showBlacklistModal" x-transition.opacity class="fixed inset-0 z-[60] bg-ink/40 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div @click.outside="showBlacklistModal = false" class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
            <h3 class="font-display font-semibold text-lg text-ink mb-4">Ajouter à la liste noire</h3>
            <form @submit.prevent="ajouterBlacklist()" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Nom</label>
                    <input x-model="nouveauBlacklist.nom" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Motif</label>
                    <textarea x-model="nouveauBlacklist.motif" rows="3" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showBlacklistModal = false" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-paper transition">Annuler</button>
                    <button type="submit" class="rounded-xl bg-rose-600 text-white font-semibold px-5 py-2.5 text-sm hover:bg-rose-700 transition">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Nouveau stagiaire --}}
    <div x-show="showStagiaireModal" x-transition.opacity class="fixed inset-0 z-[60] bg-ink/40 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div @click.outside="showStagiaireModal = false" class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
            <h3 class="font-display font-semibold text-lg text-ink mb-4">Nouveau stagiaire</h3>
            <form @submit.prevent="ajouterStagiaire()" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Nom complet</label>
                    <input x-model="nouveauStagiaire.nom" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Département</label>
                    <select x-model="nouveauStagiaire.departement" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <template x-for="(dep, key) in departements" :key="key">
                            <option :value="key" x-text="dep.label"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Tuteur</label>
                    <select x-model="nouveauStagiaire.tuteur" class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <template x-for="e in employes" :key="e.nom">
                            <option :value="e.nom" x-text="e.nom"></option>
                        </template>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Début</label>
                        <input x-model="nouveauStagiaire.debut" type="date" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Fin</label>
                        <input x-model="nouveauStagiaire.fin" type="date" required class="w-full rounded-xl bg-paper border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showStagiaireModal = false" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-paper transition">Annuler</button>
                    <button type="submit" class="rounded-xl bg-teal-600 text-white font-semibold px-5 py-2.5 text-sm hover:bg-teal-700 transition">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Aperçu / impression de lettre (partagé : recommandation + attestation de stage) --}}
    <div x-show="lettreActive" x-transition.opacity class="fixed inset-0 z-[70] bg-ink/50 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-line print:hidden">
                <h3 class="font-display font-semibold text-ink">Aperçu du document</h3>
                <div class="flex gap-2">
                    <button @click="window.print()" class="rounded-lg bg-teal-600 text-white text-sm font-semibold px-4 py-2 hover:bg-teal-700 transition">Imprimer / Exporter PDF</button>
                    <button @click="lettreActive = null" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-paper transition">Fermer</button>
                </div>
            </div>
            <div class="lettre-imprimable p-10" x-show="lettreActive">
                <div class="flex justify-between items-start mb-10">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-lg bg-gradient-to-br from-teal-400 to-teal-700 flex items-center justify-center text-white text-xs font-bold">RH</span>
                        <div>
                            <div class="font-display font-bold text-ink text-sm">CDPHM</div>
                            <div class="text-[11px] text-slate-500">Gestion des personnels</div>
                        </div>
                    </div>
                    <div class="text-xs text-slate-500 text-right" x-text="lettreActive?.date"></div>
                </div>
                <h1 class="font-display font-bold text-xl text-ink text-center mb-8" x-text="lettreActive?.titre"></h1>
                <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line" x-text="lettreActive?.corps"></p>
                <div class="mt-16 text-right text-sm text-slate-700">
                    <p>Fait à Mahajanga, le <span x-text="lettreActive?.date"></span></p>
                    <p class="mt-8 font-medium">Le Responsable RH</p>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function dashboardApp({ departements, employesInit, congesInit, presencesInit, salairesInit, performancesInit, rhEvenementsInit, stagiairesInit, blacklistInit }) {
    return {
        tab: 'personnel',
        departements: departements,
        employes: employesInit,
        conges: congesInit,
        presences: presencesInit,
        salaires: salairesInit,
        performances: performancesInit,
        rhEvenements: rhEvenementsInit,
        stagiaires: stagiairesInit,
        blacklist: blacklistInit,
        lettres: [],
        lettreActive: null,

        search: '',
        filtreDept: '',

        showEmployeModal: false,
        showCongeModal: false,
        showPresenceModal: false,
        showSalaireModal: false,
        showPerformanceModal: false,
        showRhModal: false,
        showBlacklistModal: false,
        showStagiaireModal: false,

        nouvelEmploye: { nom: '', departement: 'rh', poste: '', email: '', telephone: '', statut: 'actif' },
        nouveauConge: { employe: '', type: 'Congé annuel', debut: '', fin: '' },
        nouvellePresence: { employe: '', date: '', statut: 'present', arrivee: '', depart: '' },
        nouveauSalaire: { employe: '', mois: '', salaireBase: 0, primes: 0, retenues: 0, statut: 'attente' },
        nouvellePerformance: { employe: '', periode: '', objectifs: 80, note: 4, commentaire: '' },
        nouveauRh: { employe: '', type: 'demission', date: '', motif: '', statut: 'en_cours' },
        nouveauBlacklist: { nom: '', motif: '', date: '' },
        nouveauStagiaire: { nom: '', departement: 'rh', tuteur: '', debut: '', fin: '', statut: 'en_cours' },
        recommandationForm: { employe: '', appreciation: '' },

        get employesFiltres() {
            return this.employes.filter(e => {
                const matchNom = e.nom.toLowerCase().includes(this.search.toLowerCase());
                const matchDept = this.filtreDept === '' || e.departement === this.filtreDept;
                return matchNom && matchDept;
            });
        },

        get congesEnAttente() {
            return this.conges.filter(c => c.statut === 'attente').length;
        },

        get masseSalariale() {
            return this.salaires.reduce((total, s) => total + (s.salaireBase + s.primes - s.retenues), 0);
        },

        compteParDepartement(key) {
            return this.employes.filter(e => e.departement === key).length;
        },

        badgeStatutEmploye(statut) {
            if (statut === 'actif') return 'bg-teal-50 text-teal-700 border-teal-200';
            if (statut === 'conge') return 'bg-amber-50 text-amber-700 border-amber-200';
            return 'bg-slate-50 text-slate-700 border-slate-200';
        },

        labelStatutEmploye(statut) {
            if (statut === 'actif') return 'Actif';
            if (statut === 'conge') return 'En congé';
            return 'Parti';
        },

        // Ponctualité calculée en direct à partir des pointages de présence (logique métier connectée)
        ponctualite(nomEmploye) {
            const registres = this.presences.filter(p => p.employe === nomEmploye);
            if (registres.length === 0) return 0;
            const presents = registres.filter(p => p.statut === 'present').length;
            return Math.round((presents / registres.length) * 100);
        },

        formatAr(montant) {
            return new Intl.NumberFormat('fr-FR').format(montant) + ' Ar';
        },

        dateAujourdhui() {
            return new Date().toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
        },

        ajouterEmploye() {
            this.employes.push({ ...this.nouvelEmploye });
            this.nouvelEmploye = { nom: '', departement: 'rh', poste: '', email: '', telephone: '', statut: 'actif' };
            this.showEmployeModal = false;
        },

        ajouterConge() {
            this.conges.unshift({ ...this.nouveauConge, statut: 'attente' });
            this.nouveauConge = { employe: '', type: 'Congé annuel', debut: '', fin: '' };
            this.showCongeModal = false;
        },

        ajouterPresence() {
            this.presences.push({ ...this.nouvellePresence });
            this.nouvellePresence = { employe: '', date: '', statut: 'present', arrivee: '', depart: '' };
            this.showPresenceModal = false;
        },

        ajouterSalaire() {
            this.salaires.push({ ...this.nouveauSalaire });
            this.nouveauSalaire = { employe: '', mois: '', salaireBase: 0, primes: 0, retenues: 0, statut: 'attente' };
            this.showSalaireModal = false;
        },

        ajouterPerformance() {
            this.performances.push({ ...this.nouvellePerformance });
            this.nouvellePerformance = { employe: '', periode: '', objectifs: 80, note: 4, commentaire: '' };
            this.showPerformanceModal = false;
        },

        ajouterRhEvenement() {
            this.rhEvenements.push({ ...this.nouveauRh, statut: 'en_cours' });
            this.nouveauRh = { employe: '', type: 'demission', date: '', motif: '', statut: 'en_cours' };
            this.showRhModal = false;
        },

        // Finaliser une démission/renvoi met aussi à jour le statut de l'employé concerné (logique métier connectée)
        finaliserRh(index) {
            this.rhEvenements[index].statut = 'finalise';
            const emp = this.employes.find(e => e.nom === this.rhEvenements[index].employe);
            if (emp) emp.statut = 'parti';
        },

        ajouterBlacklist() {
            this.blacklist.push({ ...this.nouveauBlacklist, date: this.dateAujourdhui() });
            this.nouveauBlacklist = { nom: '', motif: '', date: '' };
            this.showBlacklistModal = false;
        },

        ajouterStagiaire() {
            this.stagiaires.push({ ...this.nouveauStagiaire, statut: 'en_cours' });
            this.nouveauStagiaire = { nom: '', departement: 'rh', tuteur: '', debut: '', fin: '', statut: 'en_cours' };
            this.showStagiaireModal = false;
        },

        genererRecommandation() {
            const emp = this.employes.find(e => e.nom === this.recommandationForm.employe);
            const corps = `Le CDPHM atteste que ${this.recommandationForm.employe}${emp ? ', occupant le poste de ' + emp.poste : ''}, ${this.recommandationForm.appreciation}\n\nCette lettre est délivrée à la demande de l'intéressé(e) pour faire valoir ce que de droit.`;
            const lettre = { type: 'recommandation', titre: 'Lettre de recommandation — ' + this.recommandationForm.employe, corps: corps, date: this.dateAujourdhui() };
            this.lettres.push(lettre);
            this.lettreActive = lettre;
            this.recommandationForm = { employe: '', appreciation: '' };
        },

        genererAttestationStage(index) {
            const st = this.stagiaires[index];
            st.statut = 'termine';
            const corps = `Le CDPHM atteste que ${st.nom} a effectué un stage au sein du département ${this.departements[st.departement]?.label} du ${st.debut} au ${st.fin}, sous l'encadrement de ${st.tuteur}.\n\nDurant cette période, le/la stagiaire a fait preuve d'assiduité et d'implication dans les missions qui lui ont été confiées.\n\nCette attestation est délivrée pour faire valoir ce que de droit.`;
            const lettre = { type: 'attestation_stage', titre: 'Attestation de fin de stage — ' + st.nom, corps: corps, date: this.dateAujourdhui() };
            this.lettres.push(lettre);
            this.lettreActive = lettre;
        },
    };
}
</script>

<style>
    [x-cloak] { display: none !important; }

    @media print {
        body * { visibility: hidden; }
        .lettre-imprimable, .lettre-imprimable * { visibility: visible; }
        .lettre-imprimable { position: fixed; inset: 0; padding: 2.5cm; background: white; }
    }
</style>

@endsection

import './bootstrap';

const root = document.documentElement;
const safe = (fn) => { try { return fn(); } catch (e) { return null; } };
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

/* ---------- Squelette de chargement ---------- */
const finChargement = () => root.classList.remove('is-loading');
document.addEventListener('DOMContentLoaded', () => requestAnimationFrame(() => setTimeout(finChargement, 160)));
window.addEventListener('pageshow', finChargement); // retour arrière (cache du navigateur)

const debutChargement = () => {
    root.classList.add('is-loading');
    setTimeout(finChargement, 10000); // filet de sécurité : ne jamais laisser la page masquée
};

// Navigation interne : affiche le squelette jusqu'à l'arrivée de la nouvelle page
document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || a.target === '_blank' || a.hasAttribute('download') || 'noSkeleton' in a.dataset) return;
    const url = new URL(a.href, location.href);
    if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search) || /export|justificatif|pieces|avatars|print/.test(url.pathname)) return;
    debutChargement();
});

/* ---------- Thème clair / sombre ---------- */
const appliquerTheme = (sombre) => {
    root.classList.toggle('dark', sombre);
    safe(() => localStorage.setItem('theme', sombre ? 'dark' : 'light'));
    window.dispatchEvent(new CustomEvent('themechange'));
};
$('#theme-toggle')?.addEventListener('click', () => appliquerTheme(!root.classList.contains('dark')));

/* ---------- Toasts ---------- */
const ICONES = { success: '✓', error: '✕', warning: '!', info: 'i' };
window.toast = (tone, message, duree = 4800) => {
    const zone = $('#toasts');
    if (!zone) return;
    const t = document.createElement('div');
    t.className = 'toast';
    t.dataset.tone = tone;
    t.setAttribute('role', tone === 'error' ? 'alert' : 'status');
    const ic = document.createElement('span');
    ic.className = 'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white';
    ic.style.background = { success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: 'var(--brand)' }[tone] || 'var(--brand)';
    ic.textContent = ICONES[tone] || 'i';
    const txt = document.createElement('p');
    txt.className = 'flex-1 text-sm';
    txt.textContent = message; // textContent : jamais de HTML injecté
    const x = document.createElement('button');
    x.type = 'button'; x.className = 'text-slate-400 hover:text-slate-600'; x.setAttribute('aria-label', 'Fermer'); x.textContent = '×';
    const fermer = () => { t.classList.add('out'); setTimeout(() => t.remove(), 260); };
    x.addEventListener('click', fermer);
    t.append(ic, txt, x);
    zone.append(t);
    setTimeout(fermer, duree);
};
safe(() => JSON.parse($('#flash')?.dataset.flash || '[]')).forEach(([tone, msg]) => window.toast(tone, msg));
if ($('[data-form-errors]')) window.toast('error', 'Veuillez corriger les champs signalés.');

/* ---------- Fenêtre de confirmation ---------- */
const modal = $('#confirm-modal');
let retour = null;
const fermerModal = (ok) => { modal.classList.remove('open'); retour?.(ok); retour = null; };
const confirmer = ({ message, label = 'Confirmer', tone = 'danger', titre = 'Confirmation' }) => new Promise((resolve) => {
    if (!modal) return resolve(window.confirm(message)); // pas de modale : repli sur la confirmation du navigateur
    $('#confirm-title').textContent = titre;
    $('#confirm-message').textContent = message;
    const ok = $('#confirm-ok');
    ok.textContent = label;
    ok.className = (tone === 'danger' ? 'btn-danger' : 'btn-primary') + ' rounded-lg px-4 py-2 text-sm font-medium';
    $('#confirm-icon').className = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full ' + (tone === 'danger' ? 'bg-red-500/10 text-red-500' : 'bg-sky-500/10 text-sky-500');
    modal.classList.add('open');
    ok.focus();
    retour = resolve;
});
$('#confirm-ok')?.addEventListener('click', () => fermerModal(true));
$('#confirm-cancel')?.addEventListener('click', () => fermerModal(false));
modal?.addEventListener('click', (e) => { if (e.target === modal) fermerModal(false); });

// Toute soumission sensible (suppression, modification, déconnexion…) passe par la modale.
document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
    let message = form.dataset.confirm, tone = form.dataset.confirmTone, label = form.dataset.confirmLabel;
    if (!message && !('noConfirm' in form.dataset)) {
        const methode = (form.querySelector('input[name=_method]')?.value || '').toUpperCase();
        if (methode === 'DELETE') { message = 'Supprimer cet élément ?'; tone ??= 'danger'; label ??= 'Supprimer'; }
        else if (methode === 'PUT' || methode === 'PATCH') { message = 'Confirmer cette modification ?'; tone ??= 'primary'; label ??= 'Enregistrer'; }
    }
    if (!message) return;
    e.preventDefault();
    if (await confirmer({ message, tone: tone || 'danger', label: label || 'Confirmer' })) {
        form.dataset.confirmed = '1';
        e.submitter ? form.requestSubmit(e.submitter) : form.requestSubmit();
    }
}, true);

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { if (modal?.classList.contains('open')) fermerModal(false); fermerMenus(); }
});

// Évite les doubles envois et affiche le squelette pendant l'enregistrement
document.addEventListener('submit', (e) => {
    if (e.defaultPrevented) return;
    const f = e.target;
    $$('button[type=submit], button:not([type])', f).forEach((b) => setTimeout(() => { b.disabled = true; }, 0));
    if ((f.method || '').toLowerCase() === 'post' && !/export|justificatif/.test(f.action)) debutChargement();
});

/* ---------- Menus déroulants (notifications, compte) ---------- */
const fermerMenus = () => $$('[id^=menu-]').forEach((m) => m.classList.add('hidden'));
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-menu]');
    const cible = btn ? $('#menu-' + btn.dataset.menu) : null;
    const ouvert = cible && !cible.classList.contains('hidden');
    if (!e.target.closest('[id^=menu-]')) fermerMenus();
    if (cible && !ouvert) cible.classList.remove('hidden');
    if (!e.target.closest('#palette')) $('#palette-list')?.classList.add('hidden');
});

/* ---------- Sidebar : réduite (ordinateur) ou tiroir (mobile) ---------- */
const sidebar = $('#sidebar');
const overlay = $('#sidebar-overlay');
const tiroir = (ouvert) => { sidebar?.classList.toggle('-translate-x-full', !ouvert); overlay?.classList.toggle('hidden', !ouvert); };
$$('[data-sidebar-toggle]').forEach((btn) => btn.addEventListener('click', () => {
    if (window.matchMedia('(min-width: 1024px)').matches) {
        const reduite = root.classList.toggle('sb-collapsed');
        safe(() => localStorage.setItem('sb-collapsed', reduite ? '1' : '0'));
    } else {
        tiroir(sidebar.classList.contains('-translate-x-full'));
    }
}));
overlay?.addEventListener('click', () => tiroir(false));

/* ---------- Recherche rapide de page ---------- */
const champ = $('#palette-input');
const liste = $('#palette-list');
if (champ && liste) {
    const entrees = $$('#sidebar-nav a[data-nav-label]').map((a) => ({ label: a.dataset.navLabel, href: a.href, groupe: a.closest('details')?.querySelector('summary .sb-label')?.textContent || 'Navigation' }));
    let actif = 0;
    const rendre = () => {
        const q = champ.value.trim().toLowerCase();
        const res = entrees.filter((x) => !q || (x.label + ' ' + x.groupe).toLowerCase().includes(q)).slice(0, 10);
        liste.replaceChildren(...res.map((x, i) => {
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = x.href;
            a.className = i === actif ? 'bg-[var(--surface-2)]' : '';
            a.innerHTML = '<span class="flex-1"></span><span class="text-xs" style="color:var(--faint)"></span>';
            a.children[0].textContent = x.label; a.children[1].textContent = x.groupe;
            li.append(a);
            return li;
        }));
        if (!res.length) { const li = document.createElement('li'); li.className = 'px-3 py-3 text-sm'; li.style.color = 'var(--faint)'; li.textContent = 'Aucune page trouvée.'; liste.replaceChildren(li); }
        liste.classList.remove('hidden');
    };
    champ.addEventListener('input', () => { actif = 0; rendre(); });
    champ.addEventListener('focus', rendre);
    champ.addEventListener('keydown', (e) => {
        const items = $$('a', liste);
        if (e.key === 'ArrowDown') { actif = Math.min(actif + 1, items.length - 1); rendre(); e.preventDefault(); }
        if (e.key === 'ArrowUp') { actif = Math.max(actif - 1, 0); rendre(); e.preventDefault(); }
        if (e.key === 'Enter' && items[actif]) { e.preventDefault(); items[actif].click(); }
        if (e.key === 'Escape') { liste.classList.add('hidden'); champ.blur(); }
    });
}

/* ---------- Mode grille / liste ---------- */
$$('[data-view-group]').forEach((groupe) => {
    const cle = 'view:' + groupe.dataset.viewGroup;
    const appliquer = (mode) => {
        groupe.dataset.mode = mode;
        $$('[data-view-mode]').filter((b) => b.dataset.viewTarget === groupe.dataset.viewGroup).forEach((b) => {
            const on = b.dataset.viewMode === mode;
            b.setAttribute('aria-pressed', on);
            b.style.background = on ? 'var(--brand)' : 'transparent';
            b.style.color = on ? '#fff' : 'var(--muted)';
        });
    };
    appliquer(safe(() => localStorage.getItem(cle)) || groupe.dataset.default || 'liste');
    $$('[data-view-mode]').filter((b) => b.dataset.viewTarget === groupe.dataset.viewGroup).forEach((b) =>
        b.addEventListener('click', () => { appliquer(b.dataset.viewMode); safe(() => localStorage.setItem(cle, b.dataset.viewMode)); }));
});

/* ---------- Divers ---------- */
$$('input[data-autosubmit]').forEach((i) => i.addEventListener('change', () => i.form.requestSubmit()));

// Horloge du tableau de bord
const horloge = $('[data-clock]');
if (horloge) {
    const maj = () => {
        const n = new Date();
        horloge.textContent = n.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const d = $('[data-clock-date]');
        if (d) d.textContent = n.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    };
    maj(); setInterval(maj, 15000);
}

// Lignes d'écarts dynamiques (rapprochement)
const tpl = $('#ecart-template');
const lst = $('#ecarts-list');
let idx = 0;
$('#ecart-add')?.addEventListener('click', () => { lst.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', idx++)); });
lst?.addEventListener('click', (e) => { if (e.target.closest('[data-ecart-remove]')) e.target.closest('[data-ecart-row]').remove(); });

// Aperçu en direct du rapprochement (le calcul définitif est refait côté serveur)
const apercu = $('#rapp-apercu');
if (apercu) {
    const form = apercu.closest('form');
    const fmt = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n) + ' Ar';
    const refresh = () => {
        const livres = parseFloat(apercu.dataset.livres) || 0;
        const tresor = parseFloat(form.elements['solde_tresor'].value) || 0;
        const ecarts = $$('input[name$="[montant]"]', form).reduce((s, i) => s + (parseFloat(i.value) || 0), 0);
        const final = Math.round((livres + ecarts - tresor) * 100) / 100;
        apercu.innerHTML = `Livres ${fmt(livres)} + écarts ${fmt(ecarts)} − Trésor ${fmt(tresor)} = <strong class="${final === 0 ? 'text-emerald-700' : 'text-red-700'}">${fmt(final)}</strong> ${final === 0 ? '— rapproché' : '— écart à justifier'}`;
    };
    form.addEventListener('input', refresh);
    form.addEventListener('click', () => setTimeout(refresh, 0));
    refresh();
}

/* ---------- Graphiques (Chart.js chargé à la demande, uniquement si la page en contient) ---------- */
const donnees = safe(() => JSON.parse($('#chart-data')?.textContent || 'null'));
if (donnees) {
    import('chart.js/auto').then(({ default: Chart }) => {
        let instances = [];
        const css = (v) => getComputedStyle(root).getPropertyValue(v).trim();
        const rendre = () => {
            instances.forEach((c) => c.destroy());
            instances = [];
            const texte = css('--muted'), grille = css('--line'), marque = css('--brand');
            Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
            Chart.defaults.color = texte;
            $$('canvas[data-chart]').forEach((cv) => {
                const cfg = donnees[cv.dataset.chart];
                if (!cfg) return;
                const ctx = cv.getContext('2d');
                const couleur = (c) => (c === 'brand' ? marque : c);
                const jeux = cfg.datasets.map((d, i) => {
                    const c = couleur(d.color || (i ? '#94a3b8' : 'brand'));
                    if (cfg.type === 'doughnut') return { data: d.data, backgroundColor: cfg.colors.map(couleur), borderWidth: 0 };
                    if (cfg.type === 'line') {
                        const g = ctx.createLinearGradient(0, 0, 0, cv.height || 260);
                        g.addColorStop(0, c + '55'); g.addColorStop(1, c + '00');
                        return { label: d.label, data: d.data, borderColor: c, backgroundColor: g, fill: true, tension: 0.4, pointRadius: 3, pointBackgroundColor: c, borderWidth: 2 };
                    }
                    return { label: d.label, data: d.data, backgroundColor: c, borderRadius: 6, maxBarThickness: 34 };
                });
                instances.push(new Chart(cv, {
                    type: cfg.type,
                    data: { labels: cfg.labels, datasets: jeux },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: cfg.legend ?? cfg.type === 'doughnut', position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } },
                            tooltip: { callbacks: { label: (c) => `${c.dataset.label ? c.dataset.label + ' : ' : (c.label + ' : ')}${new Intl.NumberFormat('fr-FR').format(c.parsed.y ?? c.parsed)}${cfg.unit ? ' ' + cfg.unit : ''}` } } },
                        scales: cfg.type === 'doughnut' ? {} : { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: grille }, border: { display: false }, ticks: { maxTicksLimit: 5, callback: (v) => new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(v) } } },
                        cutout: cfg.type === 'doughnut' ? '68%' : undefined,
                    },
                }));
            });
        };
        rendre();
        window.addEventListener('themechange', rendre);
    });
}

/* ---------- Justificatifs : taille max + obligatoire selon le type d'absence ---------- */
$$('input[type=file][data-max]').forEach((input) => {
    const max = Number(input.dataset.max);
    input.addEventListener('change', () => {
        const f = input.files[0];
        if (f && f.size > max) { input.value = ''; window.toast('error', `Fichier trop volumineux (${max / 1048576} Mo max).`); }
    });
    const type = input.form && $('select[name=type_code]', input.form);
    if (!type) return;
    const etoile = $('[data-req-star]', input.parentElement);
    const maj = () => { input.required = type.selectedOptions[0]?.dataset.justif === '1'; if (etoile) etoile.hidden = !input.required; };
    type.addEventListener('change', maj);
    maj();
});

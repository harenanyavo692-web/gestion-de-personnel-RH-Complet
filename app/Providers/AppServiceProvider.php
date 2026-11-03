<?php

namespace App\Providers;

use App\Models\Absence;
use App\Models\AccountingAccount;
use App\Models\DossierRegie;
use App\Models\MissionTransport;
use App\Models\AccountingEntity;
use App\Models\AccountingJournal;
use App\Models\Employe;
use App\Models\OrdreDepense;
use App\Models\ReferenceItem;
use App\Services\FinanceService;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Un seul service de paramètres par requête : le cache évite de relire la base à chaque montant affiché.
        $this->app->singleton(\App\Services\ParametreService::class);
    }

    public function boot(): void
    {
        // Corbeille et journal : accès complet (admin) ou limité au périmètre RH (permissions dédiées).
        Gate::define('corbeille', fn (User $u) => $u->hasPermission('admin.corbeille') || $u->hasPermission('corbeille.rh'));
        Gate::define('archives', fn (User $u) => $u->hasPermission('admin.archives') || $u->hasPermission('archives.rh'));
        Gate::define('journal', fn (User $u) => $u->hasPermission('admin.audit') || $u->hasPermission('activite.voir'));

        // Dates relatives et noms de mois en français (« il y a 2 jours », « oct. 2026 »).
        \Carbon\Carbon::setLocale('fr');

        // Les capacités "module.action" (ex. depenses.valider) découlent du rôle de l'utilisateur.
        // Aucun contournement global : le rôle admin reçoit ses permissions via le seeder, comme les autres.
        Gate::before(function (User $user, string $ability) {
            return str_contains($ability, '.') && $user->hasPermission($ability) ? true : null;
        });

        // Listes actives proposées dans les formulaires de saisie comptable.
        View::composer('comptabilite._champs-compta', function ($view) {
            $view->with([
                'entitesCompta' => AccountingEntity::where('actif', true)->orderBy('code')->get(),
                'journauxCompta' => AccountingJournal::where('actif', true)->orderBy('code')->get(),
                'comptesCompta' => AccountingAccount::where('actif', true)->orderBy('numero')->get(),
                'modesPaiement' => ReferenceItem::groupe('mode_paiement')->actifs()->get(),
            ]);
        });

        View::composer('raf._kpis', function ($view) {
            $finance = app(FinanceService::class);
            $view->with('rafKpi', [
                'attente' => (float) OrdreDepense::where('statut', 'en_attente')->sum('montant'),
                'validees' => $finance->totalDepenses(),
                'masse' => (float) Employe::where('statut', '!=', 'inactif')->sum('salaire'),
                'personnel' => Employe::where('statut', 'actif')->count(),
                'resultat' => $finance->resultat(),
            ]);
        });

        // Notifications de l'en-tête : tâches en attente que l'utilisateur connecté peut traiter.
        View::composer('layouts.app', function ($view) {
            $u = auth()->user();
            $n = [];
            if ($u && $u->can('depenses.valider') && ($c = OrdreDepense::where('statut', 'en_attente')->count())) {
                $n[] = ['label' => 'Dépenses à valider', 'count' => $c, 'url' => route('raf.validations.index'), 'icon' => 'check'];
            }
            if ($u && ($u->can('conges.valider') || $u->can('conges.valider_direction'))) {
                $attente = Absence::where('statut', 'en_attente')->pluck('type_code')->countBy();
                $types = ReferenceItem::groupe('type_absence')->get()->groupBy(fn ($t) => Absence::natureDuType($t));
                foreach (['conge' => ['Demandes de congé', 'rh.conges.index', 'calendar'], 'absence' => ['Absences à valider', 'rh.absences.index', 'alert']] as $nature => [$label, $route, $icon]) {
                    $c = $attente->only(($types[$nature] ?? collect())->pluck('code')->all())->sum();
                    if ($c) {
                        $n[] = ['label' => $label, 'count' => $c, 'url' => route($route), 'icon' => $icon];
                    }
                }
            }
            if ($u && $u->can('transport.encaisser') && ($c = MissionTransport::where('statut', 'attente_caisse')->count())) {
                $n[] = ['label' => 'Missions à encaisser', 'count' => $c, 'url' => route('exploitation.transport.index'), 'icon' => 'truck'];
            }
            if ($u && $u->can('regie.emettre') && ($c = DossierRegie::where('statut', 'transmis_raf')->count())) {
                $n[] = ['label' => 'Ordres de versement à émettre', 'count' => $c, 'url' => route('comptabilite.regie.index'), 'icon' => 'bank'];
            }
            $view->with(['notifs' => $n, 'notifTotal' => array_sum(array_column($n, 'count'))]);
        });

        Blade::directive('ar', fn ($expr) => "<?php echo \\App\\Support\\Fmt::ar($expr); ?>");
    }
}

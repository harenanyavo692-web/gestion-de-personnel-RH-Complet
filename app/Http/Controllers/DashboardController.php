<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Départements du CDPHM avec une couleur d'accent pour les badges
    public static function departements(): array
    {
        return [
            'distribution' => ['label' => 'Distribution', 'color' => 'sky',    'badge' => 'bg-sky-50 text-sky-700 border-sky-200'],
            'commercial'   => ['label' => 'Commercial',   'color' => 'violet', 'badge' => 'bg-violet-50 text-violet-700 border-violet-200'],
            'rh'           => ['label' => 'RH',            'color' => 'teal',  'badge' => 'bg-teal-50 text-teal-700 border-teal-200'],
            'comptable'    => ['label' => 'Comptable',     'color' => 'amber', 'badge' => 'bg-amber-50 text-amber-700 border-amber-200'],
            'caissier'     => ['label' => 'Caissier',      'color' => 'rose',  'badge' => 'bg-rose-50 text-rose-700 border-rose-200'],
            'magazinier'   => ['label' => 'Magasinier',    'color' => 'lime',  'badge' => 'bg-lime-50 text-lime-700 border-lime-200'],
            'direction'    => ['label' => 'Direction',     'color' => 'indigo','badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
        ];
    }

    // Employés de démonstration — à remplacer par le modèle Employe une fois la BDD branchée
    public static function employesDemo(): array
    {
        return [
            ['nom' => 'Rakoto Andry',        'departement' => 'rh',           'poste' => 'Responsable RH',        'email' => 'a.rakoto@cdphm.mg',   'telephone' => '034 12 345 67', 'statut' => 'actif'],
            ['nom' => 'Rasoa Voahangy',       'departement' => 'comptable',    'poste' => 'Comptable principal',   'email' => 'v.rasoa@cdphm.mg',    'telephone' => '032 11 222 33', 'statut' => 'actif'],
            ['nom' => 'Andriamanana Fy',      'departement' => 'commercial',   'poste' => 'Chargé de clientèle',   'email' => 'f.andriamanana@cdphm.mg', 'telephone' => '033 44 556 78', 'statut' => 'actif'],
            ['nom' => 'Razafy Miora',         'departement' => 'distribution', 'poste' => 'Chef de tournée',       'email' => 'm.razafy@cdphm.mg',   'telephone' => '034 98 765 43', 'statut' => 'conge'],
            ['nom' => 'Rabe Tojo',            'departement' => 'caissier',     'poste' => 'Caissier',              'email' => 't.rabe@cdphm.mg',     'telephone' => '032 55 667 89', 'statut' => 'actif'],
            ['nom' => 'Ravaka Nomena',        'departement' => 'magazinier',   'poste' => 'Magasinier',            'email' => 'n.ravaka@cdphm.mg',   'telephone' => '033 22 334 56', 'statut' => 'actif'],
            ['nom' => 'Randria Solofo',       'departement' => 'direction',    'poste' => 'Directeur général',     'email' => 's.randria@cdphm.mg',  'telephone' => '034 00 111 22', 'statut' => 'actif'],
            ['nom' => 'Zafy Hary',            'departement' => 'commercial',   'poste' => 'Commercial terrain',    'email' => 'h.zafy@cdphm.mg',     'telephone' => '032 77 889 90', 'statut' => 'actif'],
        ];
    }

    // Demandes de congés / permissions de démonstration
    public static function congesDemo(): array
    {
        return [
            ['employe' => 'Razafy Miora',    'type' => 'Congé annuel', 'debut' => '2026-08-04', 'fin' => '2026-08-15', 'statut' => 'valide'],
            ['employe' => 'Rabe Tojo',       'type' => 'Permission',   'debut' => '2026-08-01', 'fin' => '2026-08-01', 'statut' => 'attente'],
            ['employe' => 'Zafy Hary',       'type' => 'Congé maladie','debut' => '2026-07-28', 'fin' => '2026-07-30', 'statut' => 'attente'],
            ['employe' => 'Ravaka Nomena',   'type' => 'Permission',   'debut' => '2026-08-06', 'fin' => '2026-08-06', 'statut' => 'refuse'],
        ];
    }

    // Pointage / présence de démonstration (quelques jours, quelques employés)
    public static function presencesDemo(): array
    {
        return [
            ['employe' => 'Rakoto Andry',   'date' => '2026-07-27', 'statut' => 'present', 'arrivee' => '07:58', 'depart' => '16:32'],
            ['employe' => 'Rakoto Andry',   'date' => '2026-07-28', 'statut' => 'present', 'arrivee' => '08:01', 'depart' => '16:30'],
            ['employe' => 'Rakoto Andry',   'date' => '2026-07-29', 'statut' => 'retard',  'arrivee' => '09:15', 'depart' => '16:30'],
            ['employe' => 'Rasoa Voahangy', 'date' => '2026-07-27', 'statut' => 'present', 'arrivee' => '07:50', 'depart' => '16:40'],
            ['employe' => 'Rasoa Voahangy', 'date' => '2026-07-28', 'statut' => 'present', 'arrivee' => '07:55', 'depart' => '16:35'],
            ['employe' => 'Rasoa Voahangy', 'date' => '2026-07-29', 'statut' => 'present', 'arrivee' => '07:52', 'depart' => '16:38'],
            ['employe' => 'Zafy Hary',      'date' => '2026-07-27', 'statut' => 'absent',  'arrivee' => '-', 'depart' => '-'],
            ['employe' => 'Zafy Hary',      'date' => '2026-07-28', 'statut' => 'present', 'arrivee' => '08:10', 'depart' => '16:20'],
            ['employe' => 'Zafy Hary',      'date' => '2026-07-29', 'statut' => 'retard',  'arrivee' => '09:40', 'depart' => '16:20'],
            ['employe' => 'Rabe Tojo',      'date' => '2026-07-27', 'statut' => 'present', 'arrivee' => '07:59', 'depart' => '16:31'],
            ['employe' => 'Rabe Tojo',      'date' => '2026-07-28', 'statut' => 'present', 'arrivee' => '08:00', 'depart' => '16:33'],
            ['employe' => 'Rabe Tojo',      'date' => '2026-07-29', 'statut' => 'present', 'arrivee' => '07:57', 'depart' => '16:29'],
        ];
    }

    // Paie de démonstration (mois en cours)
    public static function salairesDemo(): array
    {
        return [
            ['employe' => 'Rakoto Andry',   'mois' => 'Juillet 2026', 'salaireBase' => 900000, 'primes' => 50000, 'retenues' => 20000, 'statut' => 'paye'],
            ['employe' => 'Rasoa Voahangy', 'mois' => 'Juillet 2026', 'salaireBase' => 850000, 'primes' => 30000, 'retenues' => 15000, 'statut' => 'paye'],
            ['employe' => 'Andriamanana Fy','mois' => 'Juillet 2026', 'salaireBase' => 700000, 'primes' => 0,     'retenues' => 10000, 'statut' => 'attente'],
            ['employe' => 'Razafy Miora',   'mois' => 'Juillet 2026', 'salaireBase' => 650000, 'primes' => 20000, 'retenues' => 10000, 'statut' => 'attente'],
            ['employe' => 'Rabe Tojo',      'mois' => 'Juillet 2026', 'salaireBase' => 600000, 'primes' => 10000, 'retenues' => 8000,  'statut' => 'paye'],
            ['employe' => 'Ravaka Nomena',  'mois' => 'Juillet 2026', 'salaireBase' => 620000, 'primes' => 0,     'retenues' => 8000,  'statut' => 'paye'],
            ['employe' => 'Randria Solofo', 'mois' => 'Juillet 2026', 'salaireBase' => 1800000,'primes' => 100000,'retenues' => 40000, 'statut' => 'paye'],
            ['employe' => 'Zafy Hary',      'mois' => 'Juillet 2026', 'salaireBase' => 700000, 'primes' => 0,     'retenues' => 10000, 'statut' => 'attente'],
        ];
    }

    // Évaluations de performance de démonstration (la ponctualité se calcule en direct depuis les présences côté vue)
    public static function performancesDemo(): array
    {
        return [
            ['employe' => 'Rakoto Andry',   'periode' => 'T3 2026', 'objectifs' => 90, 'note' => 4.5, 'commentaire' => 'Excellent suivi des dossiers RH, très fiable.'],
            ['employe' => 'Rasoa Voahangy', 'periode' => 'T3 2026', 'objectifs' => 95, 'note' => 4.8, 'commentaire' => 'Rigueur exemplaire sur la clôture comptable.'],
            ['employe' => 'Zafy Hary',      'periode' => 'T3 2026', 'objectifs' => 55, 'note' => 2.8, 'commentaire' => 'Retards répétés, objectifs commerciaux non atteints.'],
            ['employe' => 'Rabe Tojo',      'periode' => 'T3 2026', 'objectifs' => 88, 'note' => 4.2, 'commentaire' => 'Caisse toujours juste, bon relationnel client.'],
        ];
    }

    // Démissions / renvois de démonstration
    public static function rhEvenementsDemo(): array
    {
        return [
            ['employe' => 'Andriamanana Fy', 'type' => 'demission', 'date' => '2026-07-15', 'motif' => 'Opportunité professionnelle dans une autre entreprise.', 'statut' => 'finalise'],
        ];
    }

    // Stagiaires de démonstration
    public static function stagiairesDemo(): array
    {
        return [
            ['nom' => 'Miora Rasolofo', 'departement' => 'comptable', 'tuteur' => 'Rasoa Voahangy', 'debut' => '2026-06-01', 'fin' => '2026-08-31', 'statut' => 'en_cours'],
            ['nom' => 'Tiana Andria',   'departement' => 'rh',        'tuteur' => 'Rakoto Andry',   'debut' => '2026-03-01', 'fin' => '2026-06-30', 'statut' => 'termine'],
        ];
    }

    // Liste noire de démonstration (anciens employés/stagiaires non recommandés)
    public static function blacklistDemo(): array
    {
        return [
            ['nom' => 'Herimanana Faly', 'motif' => 'Absences répétées non justifiées et vol de matériel constaté.', 'date' => '2025-11-02'],
        ];
    }

    public function index()
    {
        $departements = self::departements();
        $employes = self::employesDemo();
        $conges = self::congesDemo();
        $presences = self::presencesDemo();
        $salaires = self::salairesDemo();
        $performances = self::performancesDemo();
        $rhEvenements = self::rhEvenementsDemo();
        $stagiaires = self::stagiairesDemo();
        $blacklist = self::blacklistDemo();

        // Répartition des effectifs par département pour la vue d'ensemble
        $repartition = [];
        foreach ($departements as $key => $dep) {
            $repartition[$key] = [
                'label' => $dep['label'],
                'color' => $dep['color'],
                'count' => count(array_filter($employes, fn ($e) => $e['departement'] === $key)),
            ];
        }

        $totalEmployes = count($employes);
        $congesEnAttente = count(array_filter($conges, fn ($c) => $c['statut'] === 'attente'));
        $enConge = count(array_filter($employes, fn ($e) => $e['statut'] === 'conge'));

        return view('dashboard', compact(
            'departements',
            'employes',
            'conges',
            'repartition',
            'totalEmployes',
            'congesEnAttente',
            'enConge',
            'presences',
            'salaires',
            'performances',
            'rhEvenements',
            'stagiaires',
            'blacklist'
        ));
    }
}

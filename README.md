# ERP CDPHM — RH, Comptabilité & Espace RAF

Laravel 12 · MySQL · Blade · Tailwind CSS 4 (Vite). Référence fonctionnelle : `document/ERP_CDPHM_v8_corrige.html`.

## Installation

```bash
composer install
npm install
cp .env.example .env          # puis régler DB_* (MySQL) et, optionnellement, SEED_PASSWORD
php artisan key:generate
# créer la base MySQL (ex. cdphm_rh, utf8mb4) puis :
php artisan migrate           # migrations additives : les tables employes/presences/users sont conservées
php artisan db:seed           # rôles, permissions, catégories, comptes de démarrage
npm run build                 # ou "npm run dev" en développement
php artisan serve
```

Comptes créés par le seeder (mot de passe = `SEED_PASSWORD`, sinon `ChangeMoi123!` — **à changer**) :
`admin@cdphm.mg`, `rindra@cdphm.mg` (RH). En environnement `local` seulement : `direction@`, `comptable@`, `raf@cdphm.mg`.

Tests (SQLite en mémoire, sans toucher à MySQL) : `php artisan test`

## Rôles et permissions (contrôlés côté serveur)

| Rôle | Accès |

| admin | Utilisateurs, journal d'audit, RH |
| direction | Lecture de tout le périmètre, commentaires de rapport, audit |
| comptable | Recettes, soumission d'ordres de dépense, situation financière |
| raf | Validation des dépenses, salaires, rapprochement Trésor, rapports — **pas** d'administration |
| rh | Personnel, présences, lecture des salaires |

Mécanisme : tables `roles` / `permissions`, middleware `can:module.action` sur les routes, `OrdreDepensePolicy`
(un utilisateur ne peut pas valider un ordre qu'il a soumis), Form Requests, transactions (`DB::transaction` + verrou)
pour la validation d'une dépense et le paiement d'un salaire. Les actions sensibles écrivent dans `audit_logs`.

## Règle de calcul

`solde = recettes − dépenses validées − salaires versés` (`App\Services\FinanceService`). Un ordre de dépense en
attente ou refusé n'affecte aucun solde. Les salaires passent par *Personnel & salaires*, pas par les ordres de dépense.

## Structure ajoutée

- `app/Http/Controllers/{Comptabilite,RAF,Admin}/` — contrôleurs par module
- `app/Services/` — `FinanceService`, `ValidationDepenseService`, `PaiementSalaireService`, `JustificatifService`, `AuditLogger`
- `app/Policies/OrdreDepensePolicy.php`, `app/Http/Requests/`
- `database/migrations/2026_10_27_*` — rôles/permissions, recettes, ordres de dépense, pièces, salaires, rapprochements, audit
- `resources/views/components/` — `sidebar`, `kpi`, `badge`, `field`, `page-header`, `empty`, `icon`
- Justificatifs : `storage/app/private/justificatifs`, servis uniquement via une route authentifiée

## Modules terrain et compléments

Désormais implémentés : lots et bons d'entrée/sortie, clients et clients spéciaux, transport, régie, majoration de
retard, tarifs, caisse centrale, registres comptables, lettres RH, contrats, congés et absences, heures
supplémentaires, stagiaires et certificats de stage, corbeille / archives (suppression douce).

Les boutons « vider / réinitialiser les données » ont été volontairement supprimés.
**Sauvegarde** : planifier un `mysqldump` régulier de la base et copier `storage/app/private`.

## Avant un déploiement

- `APP_DEBUG=false`, `APP_ENV=production`, un mot de passe MySQL non vide et un utilisateur dédié (pas `root`).
- Changer `SEED_PASSWORD` et les mots de passe des comptes créés par le seeder.
- `php artisan config:cache && php artisan route:cache` après avoir vérifié que `php artisan test` passe.

## Déploiement Docker (MySQL)

`Dockerfile` (PHP 8.2 + Apache, extension `pdo_mysql`) et `docker-entrypoint.sh` (cache de config, `migrate --force`
au démarrage). Fournir une base MySQL et les variables `APP_KEY`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD` à l'hébergeur. L'entrypoint ne lance pas le seeder : exécuter une seule fois `php artisan db:seed`
pour créer rôles, permissions et comptes de démarrage. Aucun déploiement automatique n'est configuré.

## Paramètres, entités et transport (ajouts)

- **Ressources humaines** : *Paramètres RH* (général, personnel, organisation, contrats, congés/absences, workflow) et *Entités RH*
  (direction › département › service › unité › bureau, hiérarchie sans cycle). Le département d'un employé est désormais
  rattaché à une entité (`employes.entity_id`) ; le libellé reste copié sur la fiche pour l'historique.
- **Comptabilité** : *Paramètres comptabilité* (devise et préfixes, plan comptable, journaux, catégories, modes de paiement,
  exercices, double validation) et *Entités comptables* (centres de coûts, sections, projets) avec *Budgets* ; une entité
  utilisée par des opérations n'est jamais supprimée, seulement désactivée.
- **Listes de référence** (`reference_items`) : un seul mécanisme pour catégories, statuts, grades, postes, contrats, absences,
  modes de paiement et types de pièces.
- **Transport** : flotte, missions (interurbain / urbain / location / exception 4 T), ordre de mission imprimable, encaissement en caisse.
- **Rôles** : matrice éditable dans Administration › Rôles et permissions (le rôle admin est verrouillé). Relancer
  `php artisan db:seed` ajoute les nouvelles permissions par défaut sans retirer celles que vous avez modifiées.

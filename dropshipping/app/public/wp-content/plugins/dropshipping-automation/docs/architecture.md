# Architecture du plugin

## État actuel

Le plugin est `wp-content/plugins/dropshipping-automation`, namespace `DSA\`, préfixe WordPress `dsa_` et domaine de traduction `dsa`. Il n’utilise pas Composer: `src/autoload.php` fournit un autoloader PSR-4 minimal. Le point d’entrée `dropshipping-automation.php` définit les constantes, charge l’autoloader, ajoute le hook de désactivation et démarre `DSA\Plugin` sur `plugins_loaded`.

| Couche | Responsabilité et point d’entrée |
| --- | --- |
| `src/Admin/Menu.php` | Menu wp-admin, rendu des vues et assets admin limités aux pages du plugin. |
| `src/Frontend/Storefront.php` | Tableau de bord propriétaire servi sur la page d’accueil du site; ressources front seulement sur cette page. Les vues métier et journaux sont réservés à `manage_options`. |
| `src/REST/` | API `dsa/v1`: tableau de bord, tendances, providers, workflows, actions démo, notifications et journaux. Les routes vérifient `manage_options` et le nonce REST `wp_rest`. |
| `src/Scheduler/` | `CronSchedule` calcule les occurrences; `WorkflowRuntime` ne fait encore que simuler les workflows produits/tendances. |
| `src/Notifications/` | Réglages et centre stockés dans des options WordPress, digest HTML, `wp_mail`, hooks d’événements et purge quotidienne. |
| `src/Integrations/` | Réglages de recherche marketplace, envoi webhook n8n par Action Scheduler et stockage temporaire des 100 derniers runs. URL/tokens uniquement par constantes `wp-config.php`. |
| `src/Storage/Repositories/` | Interfaces de lecture et implémentations `Demo*Repository` basées sur `DemoDataset`; aucune implémentation SQL de production. |
| `views/`, `assets/` | Vues PHP, CSS/JS séparés. Les fichiers `notifications.*` ne sont chargés que dans le dashboard du plugin ou son écran de journaux. |

La route wp-admin `admin.php?page=dsa-logs` et la route propriétaire `/?dsa_page=dsa-logs` rendent le même écran. La seconde refuse les utilisateurs sans capacité propriétaire; elle ne transforme pas le site public en surface d’administration.

## Contrats des repositories

Les méthodes ci-dessous sont les signatures actuellement exigées par les interfaces. Les lignes représentent des tableaux associatifs documentés dans `data-model.md`; une implémentation Database doit conserver ces clés ou introduire un DTO/adaptateur sans modifier les consommateurs.

| Interface | Méthodes attendues |
| --- | --- |
| `CategoryRepository` | `all(): array`, `find(int $id): ?array` |
| `ProductRepository` | `all(): array`, `find(int $id): ?array`, `filter(array $filters = array()): array` |
| `PriceSnapshotRepository` | `all(): array`, `find(int $id): ?array`, `for_product(int $product_id): array`, `for_category(int $category_id): array` |
| `TrendSignalRepository` | `all(): array`, `find(int $id): ?array`, `for_platform(string $platform): array` |
| `RunRepository` | `all(): array`, `find(int $id): ?array` |
| `OrderRepository` | `all(): array`, `find(int $id): ?array`, `for_product(int $product_id): array` |
| `ReviewRepository` | `all(): array`, `find(int $id): ?array`, `for_product(int $product_id): array` |
| `LogRepository` | `all(): array`, `find(int $id): ?array`, `for_run(int $run_id): array` |

`RepositoryFactory` expose actuellement produits, prix, catégories, commandes, runs et signaux. Il n’expose pas encore `reviews()` ni `logs()`; certaines vues lisent directement `DemoDataset`. Les contrats `all()` ne sont pas paginés. Avant d’y brancher des tables volumineuses, ajouter des méthodes paginées/filtres ou des requêtes d’agrégat, puis migrer les appels consommateurs.

## Remplacement Demo vers Database

1. Figer les clés/valeurs de tableaux du modèle de `data-model.md` et les tests de contrat; décider quels objets relèvent plutôt de WooCommerce ou des plugins installés.
2. Ajouter un schéma versionné, des migrations `dbDelta` idempotentes et des repositories SQL propres au plugin. Préfixer les tables avec `$wpdb->prefix . 'dsa_'` et préparer toutes les valeurs SQL.
3. Implémenter chaque interface, puis les méthodes de pagination/agrégat nécessaires; ne pas charger toute une table en mémoire pour une page.
4. Rendre `RepositoryFactory` configurable/injectable et basculer les implémentations par une option/migration explicite. Conserver les `Demo*Repository` comme source de fixtures de tests, pas comme fallback silencieux en production.
5. Retirer les accès directs à `DemoDataset` dans `BusinessDemoController`, `NotificationsController` et les vues; remplacer les actions démo par les commandes métier correspondantes.
6. Ajouter des tests de contrat identiques pour Demo et Database, puis des tests d’intégration WordPress/WooCommerce/Action Scheduler. Importer uniquement des données explicitement choisies; ne jamais confondre fixtures et données réelles.

## Options et rétention actuelles

- `dsa_provider_settings`: réglages providers; les clés gérées par `ProviderSettings::save()` sont chiffrées avec une clé dérivée de `wp_salt('auth')`.
- `dsa_notification_settings`: adresse/canaux/événements/rétention; les URL webhook ne sont jamais renvoyées à l’interface.
- `dsa_notifications` et `dsa_notification_sent_events`: centre borné et clés d’anti-flood.
- `dsa_workflow_schedules`, `dsa_workflow_demo_runs`, `dsa_business_demo_state`: configuration et état de démonstration.

Les options du centre ne sont pas transactionnelles et ne remplacent pas une table de notifications en production. Aucun uninstall destructif n’est effectué; l’option d’effacement des données à la désinstallation reste à concevoir et à présenter au propriétaire.

Depuis 0.7.0, le workflow Produits appelle n8n quand `DSA_N8N_LIVE_ENABLED` est explicitement `true`; le workflow Tendances reste en démonstration. Le détail du contrat, du callback et des limites de cette étape se trouve dans `n8n-integration.md`.

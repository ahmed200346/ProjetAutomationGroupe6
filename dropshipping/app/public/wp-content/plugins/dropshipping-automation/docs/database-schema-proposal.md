# Proposition de schéma SQL

Cette proposition est une cible de conception, pas un schéma installé. Le plugin 0.6.0 n’a pas de couche SQL; les données métier affichées proviennent de fixtures. Toute migration doit utiliser `dbDelta`, une version de schéma propre au plugin et des tests sur la version MySQL/MariaDB de Local.

Préfixe fixe: `$wpdb->prefix . 'dsa_'`. Utiliser InnoDB et `$wpdb->get_charset_collate()`. Les identifiants/relations ci-dessous sont conceptuels; les contraintes doivent être compatibles avec `dbDelta` et le moteur installé. Stocker les dates en UTC.

| Table proposée | Colonnes et types principaux | Index/notes |
| --- | --- | --- |
| `dsa_categories` | `id BIGINT UNSIGNED`, `name VARCHAR(191)`, `slug VARCHAR(191)` | PK `id`, unique `slug`. |
| `dsa_products` | `id BIGINT UNSIGNED`, `source VARCHAR(32)`, `source_key VARCHAR(191)`, `category_id BIGINT UNSIGNED`, `title VARCHAR(255)`, `purchase_price DECIMAL(12,2)`, `sale_price DECIMAL(12,2)`, `margin_pct DECIMAL(5,2)`, scores `TINYINT UNSIGNED`, `status VARCHAR(24)`, `created_at DATETIME` | unique `(source, source_key)`; index `(status, created_at)`, `category_id`. La publication réelle reste dans WooCommerce via CRUD; cette table contient candidats/métadonnées, pas des lignes `wp_posts`/`wp_postmeta`. |
| `dsa_price_snapshots` | `id BIGINT UNSIGNED`, `product_id BIGINT UNSIGNED`, `category_id BIGINT UNSIGNED`, `price DECIMAL(12,2)`, `observed_at DATETIME` | index `(product_id, observed_at)` et `(category_id, observed_at)`. |
| `dsa_trend_signals` | `id BIGINT UNSIGNED`, `platform VARCHAR(32)`, `source_id VARCHAR(191)`, `product_key VARCHAR(191)`, `product_name VARCHAR(255)`, `demand_score DECIMAL(5,4)`, `mentions INT UNSIGNED`, `observed_at DATETIME`, `provenance_json LONGTEXT` | unique `(platform, source_id)`; index `(observed_at, platform)`. Pas de texte social brut ou donnée personnelle non nécessaire. |
| `dsa_runs` | `id BIGINT UNSIGNED`, `run_uuid CHAR(36)`, `workflow VARCHAR(48)`, `trigger VARCHAR(16)`, `status VARCHAR(24)`, `current_stage VARCHAR(48)`, `cursor_json LONGTEXT`, compteurs `INT UNSIGNED`, `started_at DATETIME`, `finished_at DATETIME`, `attempt INT UNSIGNED` | unique `run_uuid`; index `(status, started_at)`, `(workflow, started_at)`. |
| `dsa_run_stages` | `id BIGINT UNSIGNED`, `run_id BIGINT UNSIGNED`, `stage VARCHAR(48)`, `status VARCHAR(24)`, `started_at DATETIME`, `finished_at DATETIME`, `duration_ms INT UNSIGNED`, `error_code VARCHAR(64)`, `error_summary VARCHAR(255)`, `result_json LONGTEXT` | unique `(run_id, stage)` ou `(run_id, stage, attempt)` si les tentatives sont historisées; index `(status, started_at)`. Aucun stack trace/réponse distante brute. |
| `dsa_logs` | `id BIGINT UNSIGNED`, `run_id BIGINT UNSIGNED NULL`, `level VARCHAR(16)`, `module VARCHAR(48)`, `code VARCHAR(64)`, `message VARCHAR(255)`, `created_at DATETIME` | index `(created_at)`, `(run_id, created_at)`, `(level, module, created_at)`. Messages allowlistés, sans secret/PII. |
| `dsa_publications` | `id BIGINT UNSIGNED`, `source_key VARCHAR(191)`, `product_id BIGINT UNSIGNED`, `status VARCHAR(24)`, `created_at DATETIME`, `updated_at DATETIME` | unique `source_key`; index `(product_id)`. `product_id` référence WooCommerce logiquement. Publication auto limitée à `draft`/`pending`. |
| `dsa_notifications` | `id BIGINT UNSIGNED`, `event_key VARCHAR(191)`, `event_type VARCHAR(40)`, `level VARCHAR(16)`, `title VARCHAR(120)`, `summary VARCHAR(240)`, `created_at DATETIME`, `read_at DATETIME NULL` | unique `event_key` pour déduplication; index `(read_at, created_at)`, `(created_at)`. |
| `dsa_notification_deliveries` | `id BIGINT UNSIGNED`, `notification_id BIGINT UNSIGNED`, `channel VARCHAR(24)`, `status VARCHAR(24)`, `attempted_at DATETIME NULL`, `sent_at DATETIME NULL` | index `(notification_id, channel)`, `(status, attempted_at)`. Ne pas stocker le contenu du webhook ni les destinataires dans le journal. |

## Données non possédées par le plugin

Ne pas dupliquer les commandes WooCommerce, clients, paiements, expéditions, tickets de support ou messages e-mail dans des tables `dsa_*`. Les contrats `OrderRepository`/`ReviewRepository` doivent être implémentés par des adaptateurs vers les API documentées des plugins concernés, avec des projections minimales et agrégées. Les valeurs `orders`/`reviews` du jeu démo ne sont pas un modèle d’autorité production.

## Migration

Créer les tables plugin-owned dans une migration versionnée et transactionnelle quand le moteur le permet; prévoir reprise après interruption et index unique avant d’activer les retries. Ne pas créer de table à chaque boot. La désinstallation ne supprime options/tables/actions qu’après un choix explicite du propriétaire.

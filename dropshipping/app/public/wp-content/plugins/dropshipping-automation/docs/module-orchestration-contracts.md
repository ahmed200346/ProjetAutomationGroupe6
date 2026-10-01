# Contrats d’orchestration pour les modules

Ce document distingue les hooks/actions présents dans 0.7.0 des points d’intégration proposés. Les hooks de simulation restants ne sont pas des handlers métier; ne pas brancher de scraping ou de publication réelle dessus sans stockage/idempotence.

## Points présents

| Type | Nom | Arguments/effet | État |
| --- | --- | --- | --- |
| Action Scheduler single | `dsa_workflow_schedule` | `workflow` (`products` ou `trends`); déclenche le run Produits via n8n si opt-in/config actifs, mais laisse Tendances en simulation; planifie ensuite l’occurrence suivante. Groupe `dsa-workflows`. | Implémenté en prototype. |
| Action Scheduler async | `dsa_workflow_simulate` | `workflow`, `source` (`manual` ou `scheduled`); écrit l’historique démo et émet un événement de fin/échec. Groupe `dsa-workflows`. | Implémenté, aucune étape métier. |
| Action Scheduler async | `dsa_n8n_dispatch_run` | `run_id`, `products`, `source`; envoie query/marketplace/limit et callback URL au webhook n8n. Groupe `dsa-n8n-workflows`. | Implémenté; activé uniquement avec opt-in serveur et credentials configurés. |
| Action Scheduler recurring (daily) | `dsa_journal_purge` | Purge les notifications après la rétention. Groupe `dsa-maintenance`; repli WP-Cron quotidien. | Implémenté. |
| Événement | `dsa_run_completed` | Tableau normalisé avec `id`, `workflow`, `status`, `started_at`, `finished_at`, `counts`, et `demo` booléen. | Émis par le runtime démo; consommé par `EventDispatcher`. |
| Événement | `dsa_run_failed` | Même forme; les erreurs détaillées ne doivent pas quitter le stockage sécurisé. | Émis par le runtime démo; consommé par `EventDispatcher`. |
| Événement | `dsa_product_pending` | Prévoir `product_id`/`candidate_id` stable et résumé public seulement. | Consommateur enregistré; aucun émetteur métier actuellement. |
| Événement | `dsa_provider_unavailable` | Prévoir `provider` parmi les identifiants allowlistés, code d’erreur générique et compteur de répétitions. | Consommateur enregistré; aucun émetteur métier actuellement. |

À la désactivation, seules les actions récurrentes/planifiées du plugin sont annulées. L’historique n’est pas supprimé. L’Action Scheduler n’est pas requis pour le chargement du plugin, mais les workflows ne seront pas exécutés si le runner est absent.

## Contrat cible pour les prochaines exécutions réelles

Arguments Action Scheduler petits et sérialisables; tous les traitements retrouvent l’état par `run_id` dans un repository plugin-owned:

| Hook/action futur | Arguments proposés | Propriétaire fonctionnel |
| --- | --- | --- |
| `dsa_run_stage` | `run_id`, `stage` allowlisté | Orchestrateur; lot borné, cursor persisté, transition idempotente. Pas encore enregistré. |
| Stage `scrape` | `run_id`, `source_id` allowlisté | Module de demande sociale: API officielle/approuvée, taux partagé, timeout et curseur. |
| Stage `score` | `run_id` | Scoring/déduplication; écrit scores et raisons sans inventer de données absentes. |
| Stage `source` | `run_id` | Adaptateur fournisseur existant; disponibilité/prix récupérés, pas supposés. |
| Stage `content` | `run_id` | Provider IA existant; contenu attribuable et erreur générique journalisée. |
| Stage `product_review` | `run_id` | CRUD WooCommerce idempotent; statut uniquement `draft` ou `pending`; journal de publication avant retries. |
| `dsa_send_run_digest` | `run_id` | Job distinct des effets produit; mail en échec ne relance pas la publication. Pas encore enregistré. |

Contrat de fin: enregistrer l’état et les compteurs avant de déclencher `dsa_run_completed` ou `dsa_run_failed`. Chaque stage consomme un lot et une durée bornés, sauvegarde le curseur, puis programme le prochain job. Retries transitoires bornés et backoff; aucun retry automatique des refus permanents/termes d’utilisation.

## Répartition de travail

1. Module scraping: source approuvée, normalisation/provenance, `scrape`.
2. Module scoring: déduplication et retour agrégé vente/retour/SAV, `score`.
3. Module fournisseur: adapter documenté, stage `source`.
4. Module contenu: adapter provider IA, stage `content`.
5. Module catalogue: candidat et publication WooCommerce en brouillon.
6. Intégrations opérations: commandes, fulfillment, support et e-mail restent aux plugins existants.
7. Orchestration: run repository, transitions, batch/cursor, retries et Action Scheduler.
8. Notifications/observabilité: logs, digest, événements et alertes, sans PII ni secrets.

Ces numéros sont un découpage technique proposé pour les intégrations ultérieures; seuls les hooks listés « présents » existent aujourd’hui. Pour le contrat n8n courant et son callback, voir `n8n-integration.md`.

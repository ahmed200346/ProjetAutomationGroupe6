# Modèle de données de démonstration

Les repositories de `DSA\Storage\Repositories` exposent les contrats de lecture utilisés par l’interface. Les classes `Demo\Demo*Repository` les implémentent à partir d’un générateur partagé. Le module SQL pourra remplacer ces implémentations sans modifier les consommateurs. Les identifiants sont des entiers stables dans le jeu de démonstration; les dates sont au format SQL `Y-m-d` ou `Y-m-d H:i:s`, calculées en UTC.

| Table | Colonnes | Contraintes et relations |
| --- | --- | --- |
| `categories` | `id`, `nom`, `slug` | `slug` unique. |
| `products` | `id`, `titre`, `category_id`, `source` (`amazon`, `alibaba`, `aliexpress`), `prix_achat`, `prix_vente`, `marge_pct`, `score_demande`, `score_tendance`, `score_concurrence`, `score_global`, `statut` (`shortlist`, `pending`, `draft`, `published`, `rejected`), `delai_livraison_jours`, `note_moyenne`, `nb_avis`, `created_at` | `category_id` référence `categories.id`; scores bornés à 0–100; la marge est `(prix_vente - prix_achat) / prix_vente * 100`. |
| `price_snapshots` | `id`, `product_id`, `category_id`, `prix`, `date` | Références vers `products.id` et `categories.id`; une observation par catégorie et jour dans le jeu démo. |
| `trend_signals` | `id`, `produit_detecte`, `plateforme` (`x`, `facebook`, `instagram`), `nb_mentions`, `sentiment`, `date` | Mentions positives; sentiment borné à -1–1. |
| `runs` | `id`, `workflow`, `debut`, `fin`, `statut`, `produits_trouves`, `produits_ajoutes`, `erreurs` | Horodatages UTC et compteurs non négatifs. |
| `run_stages` | `id`, `run_id`, `stage`, `start_at`, `end_at`, `duration_seconds`, `status`, `error_summary` | Trois étapes ordonnées par exécution; `run_id` référence `runs.id`. Les durées et erreurs sont déterministes; les détails d’erreur restent génériques et ne contiennent ni réponse distante ni donnée personnelle. |
| `orders` | `id`, `product_id`, `statut`, `numero_suivi`, `fournisseur`, `date` | `product_id` référence `products.id`; fournisseur issu de la liste `source`. |
| `reviews` | `id`, `product_id`, `note`, `commentaire`, `date` | `product_id` référence `products.id`; note de 1 à 5. |
| `logs` | `id`, `niveau`, `module`, `message`, `run_id`, `date` | `run_id` référence `runs.id`; niveaux `info`, `warning`, `error`. |

Le générateur emploie une graine SHA-256 versionnée (`dsa-demo-seed-v1`) et produit 8 catégories, 80 produits, 720 observations de prix (90 jours par catégorie), 48 signaux, 20 runs, 60 étapes d’exécution, 24 commandes, 30 avis et 40 logs. Il n’utilise pas le générateur aléatoire global PHP. Les prix suivent une pente déterministe différente par catégorie, une saisonnalité légère et un bruit borné afin de rendre les séries crédibles et reproductibles pour une journée donnée.

## Notifications (phase prototype)

Les réglages sont stockés dans l’option `dsa_notification_settings`; les notifications non lues/lues dans `dsa_notifications`. Les webhooks Slack/Telegram acceptent uniquement des URL HTTPS et leurs valeurs ne sont jamais renvoyées à l’interface; aucun appel webhook n’est encore effectué. Les événements `dsa_run_completed` et `dsa_run_failed` sont émis par le runtime de démonstration, mais les événements marqués `demo` ne peuvent pas envoyer de courriel. Le test explicite utilise `wp_mail` avec des données fictives.

La maintenance quotidienne supprime les notifications persistées après le nombre de jours configuré, via Action Scheduler ou WP-Cron en repli. Les lignes d’exécution visibles restent des fixtures déterministes et ne sont pas purgées; cette limite disparaîtra quand le repository des runs de production remplacera les repositories de démo.

Le jeu est fictif et réservé aux écrans activés en mode démo. Il ne constitue ni une collecte sociale réelle, ni une preuve de disponibilité fournisseur, ni une donnée de commande client.
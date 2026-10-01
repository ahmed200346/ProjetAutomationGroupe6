# Intégration du workflow n8n Produits

## État de cette première intégration

Le plugin sait maintenant:

- conserver la recherche marketplace du workflow Produits dans `dsa_n8n_workflow_settings`;
- créer un run depuis le bouton Produits ou le calendrier WordPress, puis envoyer le POST n8n depuis Action Scheduler;
- protéger l’appel WordPress -> n8n avec un token d’en-tête;
- accepter le résultat n8n sur `POST /wp-json/dsa/v1/workflows/callback`, protégé par un second token;
- stocker les 100 derniers états/counts dans l’option `dsa_n8n_workflow_runs` et déclencher les événements de notification existants.

Ce n’est pas encore une intégration production complète: les runs WordPress sont dans une option bornée (pas encore un repository SQL), le workflow exporté V2 sur disque a des noms de champs/mapping d’entrée à corriger, le credential API Playwright doit être attaché au node HTTP n8n, et l’accès marketplace doit être explicitement autorisé. La sortie n8n ne crée pas de produit WooCommerce; la validation humaine et la publication en brouillon restent un module futur.

## Clé API du service Playwright

Le conteneur exige désormais `PLAYWRIGHT_API_KEY` (au moins 32 caractères); le serveur refuse de démarrer sans cette clé. Le port host Playwright est limité à `127.0.0.1`; n8n utilise le réseau Docker privé `n8n_automation`. Le endpoint `/health` reste public pour le health-check du lanceur. Le `.env` local est ignoré par `.gitignore`; ne jamais committer sa valeur.

Dans PowerShell, depuis le dossier `Automation_for_Business_Project-scrapper`, générer la clé, l’écrire dans `.env` et la copier dans le presse-papiers sans l’afficher:

```powershell
$bytes = New-Object byte[] 32
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes)
$key = [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+', '-').Replace('/', '_')
Set-Content -LiteralPath '.env' -Value "PLAYWRIGHT_API_KEY=$key" -Encoding ascii -NoNewline
Set-Clipboard -Value $key
$rng.Dispose()
[Array]::Clear($bytes, 0, $bytes.Length)
Remove-Variable key, bytes, rng
Write-Host 'Cle ecrite dans .env et copiee dans le presse-papiers.'
```

Dans n8n, créer un credential **Header Auth** avec le nom `x-api-key` et coller la valeur depuis le presse-papiers. Attacher ce credential au nœud `Aliexpress Scrapper`. Ce credential est distinct des deux credentials n8n Webhook et Callback, et distinct du token que WordPress envoie à son webhook d’entrée.

## Secrets dans `wp-config.php`

Ne pas mettre ces valeurs dans Git, le JSON n8n ou les options WordPress. L’URL doit être la **Production URL** du Webhook n8n (le chemin `/webhook/...`, pas `/webhook-test/...`). En local, `http://localhost:5678/...` est accepté; hors local, HTTPS est requis. Utiliser deux tokens différents de 32 caractères aléatoires minimum.

```php
define( 'DSA_N8N_WEBHOOK_URL', 'http://localhost:5678/webhook/REMPLACER_PAR_LE_PATH_PRODUCTION' );
define( 'DSA_N8N_WEBHOOK_TOKEN', 'REMPLACER_PAR_UN_SECRET_ALEATOIRE_LONG' );
define( 'DSA_N8N_CALLBACK_URL', 'http://host.docker.internal:PORT/wp-json/dsa/v1/workflows/callback' );
define( 'DSA_N8N_CALLBACK_TOKEN', 'REMPLACER_PAR_UN_AUTRE_SECRET_ALEATOIRE_LONG' );
define( 'DSA_N8N_LIVE_ENABLED', false );
```

`DSA_N8N_CALLBACK_URL` doit être joignable **depuis le conteneur n8n**; `PORT` doit être remplacé par le port host réellement utilisé par Local. Vérifier cette URL depuis le conteneur avant l’essai; `localhost` dans n8n désigne le conteneur n8n, pas Windows.

`DSA_N8N_LIVE_ENABLED` reste `false` jusqu’à ce que le workflow n8n de test, la callback, la protection API Playwright et l’autorisation d’accès à la marketplace aient été vérifiés. Pour exécuter un essai contrôlé, le propriétaire peut ensuite le mettre à `true`; les secrets ne doivent jamais être transmis à l’UI du dashboard.

## Configuration n8n nécessaire

Le Webhook existant doit être `POST`, `Header Auth`, `Respond Immediately`; une réponse `200` est acceptée. Le credential Header Auth doit porter `X-DSA-Webhook-Token` avec la même valeur que `DSA_N8N_WEBHOOK_TOKEN`.

Entrée attendue:

```json
{
  "run_id": "uuid fourni par WordPress",
  "query": "terme de recherche enregistré dans le dashboard",
  "marketplace": "aliexpress",
  "limit": 10
}
```

Dans `Set`, utiliser exactement les noms `product_name`, `marketplace`, `limit`, `run_id` (sans `=` dans le nom et sans espace final). Dans `Aliexpress Scrapper`, construire le corps JSON en mode Expression à partir de `$json.product_name`, `$json.marketplace`, `$json.limit`. L’appel HTTP vers Playwright doit avoir une authentification distincte; ne pas confondre le token du webhook n8n et la clé `API_KEY` du service Node.

Le fichier exporté `worflows/AliExpress_Scrapper+scoreV2.json` présent au moment de l’intégration n’est pas aligné avec ce contrat: son Set porte `=marketplace` et `run_id ` (espace final), et le mapping `limit` n’est pas une expression d’objet claire. Corriger le workflow dans n8n, puis réexporter le JSON afin que le fichier versionné corresponde à la version active.

### Callback finale

Ajouter à la fin du workflow un HTTP Request `POST` vers la route WordPress callback. Le n8n doit pouvoir joindre l’hôte WordPress depuis son conteneur; définir une URL appropriée à l’environnement, pas nécessairement `localhost` (dans un conteneur, `localhost` signifie le conteneur n8n lui-même). Configurer Header Auth `X-DSA-Callback-Token` avec `DSA_N8N_CALLBACK_TOKEN`.

Ajouter un nœud HTTP Request après `Final Product Output`. Son URL vient de `Webhook.body.callback_url`; réutiliser `run_id` du Webhook et créer un credential Header Auth nommé `X-DSA-Callback-Token`. Contrat JSON minimal:

```json
{
  "run_id": "reprendre exactement le run_id du Webhook",
  "status": "completed",
  "counts": {
    "found": 10,
    "scored": 4,
    "created": 0,
    "duplicates": 0,
    "errors": 0
  }
}
```

`created` reste zéro tant que le workflow ne crée pas un produit par l’API WooCommerce en statut `draft`/`pending`. Pour le statut `failed`, renvoyer `errors` supérieur à zéro. Ne pas envoyer les clés API, les textes complets d’avis, le HTML de pages ou les messages bruts d’exception dans cette callback.

### Enregistrer les résultats produits complets

Le callback ci-dessus ne stocke que l’état du run et les compteurs. Pour conserver chaque résultat JSON complet, le plugin expose aussi `POST /wp-json/dsa/v1/workflows/results`, protégé par le même header `X-DSA-Callback-Token`. À son premier démarrage après mise à jour, il crée la table `$wpdb->prefix . 'dsa_workflow_results'`; chaque ligne conserve le JSON original dans `payload_json`, plus le rang, le titre, la source, la recherche et le run ID. Le run ID est facultatif pour un test manuel. Ces lignes sont des résultats de recherche, pas des produits WooCommerce.

Le service Playwright expose `POST /results`; il vérifie sa clé `x-api-key`, accepte le produit directement, sous `body` ou `product_result`, puis le relaie à WordPress. Ajouter ces valeurs au `.env` local du projet scraper, sans committer les secrets:

```dotenv
WORDPRESS_RESULTS_URL=http://host.docker.internal:PORT/wp-json/dsa/v1/workflows/results
WORDPRESS_RESULTS_TOKEN=la_meme_valeur_que_DSA_N8N_CALLBACK_TOKEN
```

Remplacer `PORT` par le port HTTP du site Local accessible depuis Windows. Recréer le conteneur après la modification de Compose ou de `.env`:

```powershell
docker compose up -d --build playwright
```

Dans n8n, le nœud HTTP Request peut appeler le relais avec `POST http://playwright:3000/results`, Header Auth `x-api-key` configuré avec `PLAYWRIGHT_API_KEY`, et le JSON Body en mode Expression:

```js
={{ { product_result: $json, run_id: $('Webhook').item.json.body.run_id || '' } }}
```

Pour éviter le relais, n8n peut appeler directement `http://host.docker.internal:PORT/wp-json/dsa/v1/workflows/results` avec le header `X-DSA-Callback-Token` configuré avec `DSA_N8N_CALLBACK_TOKEN` et le même JSON Body. Depuis un conteneur, `localhost` ne désigne pas Windows. Ne pas utiliser `http://playwright:3000` sans chemin: la route de résultats est `/results`.

## Test manuel sans scraper réel

1. Laisser `DSA_N8N_LIVE_ENABLED` à `false` pendant la préparation. Corriger le mapping V2 (Set `marketplace` sans `=`; `run_id` sans espace; `limit` en expression), ajouter la callback finale et créer trois credentials n8n distincts: webhook entrant, callback sortante, API Playwright.
2. Dupliquer le workflow n8n pour les tests. Dans cette copie, remplacer le nœud Playwright par des données fixtures et retirer les appels OpenRouter si le but est seulement de tester le transport; aucun appel marketplace réel.
3. Configurer les quatre constantes URL/tokens en local dans `wp-config.php`, générer le `.env` Playwright et recréer le conteneur scraper; garder l’opt-in à `false` jusqu’à ce que l’accès autorisé aux sources soit validé.
4. Quand la copie fixture et ses deux directions d’authentification sont vérifiées, mettre `DSA_N8N_LIVE_ENABLED` à `true`, recharger le site puis saisir une recherche dans **Dropshipping > Workflows** et cliquer **Enregistrer la recherche**.
5. Cliquer **Exécuter maintenant** pour Produits. Dans l’admin Action Scheduler, confirmer `dsa_n8n_dispatch_run`; dans l’onglet Executions n8n, confirmer la réception du même `run_id`; enfin confirmer que le callback fait passer le run WordPress de `running` à `completed`/`failed`.
6. Désactiver tout lancement n8n planifié autonome. Le calendrier WordPress/Action Scheduler est l’unique maître des horaires. Tester d’abord un lancement manuel, puis une occurrence calendrier courte, puis DST/fuseau avant production.

Si le workflow n8n échoue avant d’atteindre le nœud callback, WordPress restera en `running`; créer ensuite un Error Workflow n8n qui renvoie le `run_id` et `status=failed` sans données d’erreur brutes.

Les tests PHP du plugin utilisent un transport HTTP injecté et ne doivent jamais appeler n8n, OpenRouter ou une marketplace réelle.

# Providers IA

La configuration Gemini et Ollama est stockée dans l’option WordPress `dsa_provider_settings`.

La clé Gemini est d’abord lue depuis `DSA_GEMINI_API_KEY`, à définir dans `wp-config.php` ou dans l’environnement du serveur. Si cette constante n’est pas renseignée, la clé saisie est chiffrée côté serveur avec OpenSSL AES-256-CBC ; la clé de chiffrement dérive du salt `auth` propre au site. Elle n’est jamais renvoyée au navigateur : l’interface ne reçoit qu’un masque.

Les listes de modèles sont mises en cache 15 minutes dans un transient. Le bouton « Rafraîchir » force un nouvel appel. Les appels sortants sont effectués par le serveur WordPress avec un délai maximal de 10 secondes ; pour Ollama, `localhost` désigne donc la machine qui héberge WordPress.

## Gemini

La détection utilise `GET https://generativelanguage.googleapis.com/v1beta/models`, le header `x-goog-api-key`, `pageSize=1000` et `nextPageToken`. Seuls les modèles dont `supportedGenerationMethods` contient `generateContent` sont proposés.

Références vérifiées le 29 septembre 2026 : [API Models](https://ai.google.dev/api/models), [API keys](https://ai.google.dev/gemini-api/docs/api-key), [catalogue des modèles](https://ai.google.dev/gemini-api/docs/models).

Le logo Ollama affiché dans l’interface est l’asset officiel [ollama-logo.svg](https://github.com/ollama/ollama/blob/main/docs/ollama-logo.svg), embarqué localement dans le plugin ; aucun appel d’image externe n’est effectué.

OpenRouter utilise son catalogue officiel `GET https://openrouter.ai/api/v1/models` avec `Authorization: Bearer`. NVIDIA NIM utilise l’API compatible OpenAI `GET https://integrate.api.nvidia.com/v1/models` avec `Authorization: Bearer`. Les deux réponses sont normalisées depuis leur tableau `data`.

Google indique que les clés standard sans restriction peuvent être refusées et que les nouvelles clés AI Studio doivent être liées ou restreintes au Gemini API. Le message d’erreur renvoyé par Google est maintenant affiché dans l’interface sans exposer la clé.

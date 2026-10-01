CONTEXTE COMMUN DU DASHBOARD "Dropshipping Automation" (WordPress)

Objectif : construire un dashboard d'administration WordPress moderne, clair et user-friendly, qui deviendra
l'orchestrateur (modules 9 et 10) du pipeline de dropshipping décrit dans les PDF du projet.
Le projet est un e-commerce de dropshipping sans paiement en ligne : le dashboard sert le propriétaire
(vendeur), et la boutique sert les acheteurs.

RÈGLES TECHNIQUES
- Un seul plugin, préfixe unique `dsa_` (fonctions/options/hooks) et namespace PHP `DSA\`.
- Une page de menu principale "Dropshipping" avec sous-pages ; l'UI est rendue par des vues PHP + CSS/JS propres
  au plugin, chargés uniquement sur nos pages admin (jamais sur tout wp-admin).
- Aucune librairie via CDN : tout asset (ex. Chart.js) est embarqué dans le plugin.
- Design system : variables CSS (couleurs, rayons, ombres, espacements), support du mode sombre
  (prefers-color-scheme + bascule manuelle), composants réutilisables (carte, badge, bouton, tableau, onglets,
  modale, toast), responsive, accessible (labels, focus visible, contrastes, navigation clavier).
- Sécurité : nonces, current_user_can('manage_options'), sanitization à l'entrée, escaping à la sortie,
  $wpdb->prepare, endpoints REST avec permission_callback.
- Toutes les chaînes UI en français via i18n (text domain `dsa`).
- Les données affichées passent autant que possible par des interfaces de "repository" afin d'isoler
  les fixtures de démonstration des sources réelles. Le modèle et les contrats évolueront avec les modules.
- Ne modifie pas le core WordPress ni les plugins tiers.

À la fin de chaque phase : liste les fichiers créés/modifiés, comment tester manuellement, et les limites connues.

ÉVOLUTION DU SQUELETTE ET INTÉGRATION DES MODULES
- Le dashboard actuel est un prototype de l'intention produit, pas un contrat d'interface à préserver. Une page,
  une vue ou une fonctionnalité de démonstration peut être remplacée, profondément modifiée ou supprimée si les
  vrais modules, leurs données ou le parcours propriétaire l'exigent.
- Préserver l'objectif général : un espace de pilotage clair pour le propriétaire, observable, sécurisé et adapté
  aux opérations réelles. Ne pas conserver une page uniquement parce qu'elle existe dans le squelette.
- Lorsqu'un module réel arrive, examiner d'abord ses données et interfaces, puis adapter le repository, les vues,
  routes, assets, tests et documentation concernés. Garder les changements ciblés et éviter de refaire les autres
  pages sans nécessité.
- Les contrats de démonstration ne doivent pas contraindre le modèle de production. Garder les fixtures et écrans
  démo uniquement s'ils restent utiles aux tests ou à la validation; retirer proprement les références devenues
  inutiles (navigation, endpoints, assets et documentation compris).
- Si une modification de schéma change l'affichage, mettre à jour ensemble le contrat du repository, les données
  de test, les états de chargement/vide/erreur et les vues impactées. Ne pas promettre une migration sans toucher
  à l'UI lorsque le vrai modèle révèle un besoin différent.
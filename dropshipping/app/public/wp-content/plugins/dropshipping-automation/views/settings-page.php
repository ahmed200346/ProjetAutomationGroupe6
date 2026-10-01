<?php

defined( 'ABSPATH' ) || exit;

$navigation_pages = isset( $this->pages ) ? $this->pages : ( new DSA\Admin\Menu() )->get_pages();
$is_public_settings = ! is_admin();
?>
<div class="wrap dsa-shell dsa-settings-shell">
	<aside class="dsa-sidebar" aria-label="<?php esc_attr_e( 'Navigation Dropshipping', 'dsa' ); ?>">
		<a class="dsa-brand" href="<?php echo esc_url( $is_public_settings ? home_url( '/' ) : admin_url( 'admin.php?page=dsa-dashboard' ) ); ?>">
			<span class="dsa-brand-mark" aria-hidden="true"><span class="dashicons dashicons-chart-area"></span></span>
			<span class="dsa-brand-name"><?php esc_html_e( 'Dropshipping', 'dsa' ); ?><small><?php esc_html_e( 'AUTOMATION', 'dsa' ); ?></small></span>
		</a>
		<nav class="dsa-navigation" aria-label="<?php esc_attr_e( 'Sections du tableau de bord', 'dsa' ); ?>">
			<?php foreach ( $navigation_pages as $slug => $navigation_page ) : ?>
				<a class="dsa-nav-link<?php echo $slug === $current_slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( $is_public_settings ? add_query_arg( 'dsa_page', $slug, home_url( '/' ) ) : menu_page_url( $slug, false ) ); ?>">
					<span class="dashicons <?php echo esc_attr( $navigation_page['icon'] ); ?>" aria-hidden="true"></span><span><?php echo esc_html( $navigation_page['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="dsa-sidebar-footer"><span class="dsa-status-dot" aria-hidden="true"></span><span><?php esc_html_e( 'Espace de travail', 'dsa' ); ?></span><span class="dsa-version">v<?php echo esc_html( DSA_VERSION ); ?></span></div>
	</aside>
	<main class="dsa-main">
		<header class="dsa-header">
			<div class="dsa-heading"><p class="dsa-eyebrow"><?php esc_html_e( 'RÉGLAGES', 'dsa' ); ?></p><h1><?php echo esc_html( $page['label'] ); ?></h1><p class="dsa-page-description"><?php esc_html_e( 'Connectez les modèles utilisés pour préparer vos contenus.', 'dsa' ); ?></p><?php require DSA_PLUGIN_DIR . 'views/partials/demo-badge.php'; ?></div>
			<div class="dsa-header-actions"><button class="dsa-theme-toggle" type="button" aria-label="<?php esc_attr_e( 'Activer le thème sombre', 'dsa' ); ?>" aria-pressed="false" data-label-light="<?php esc_attr_e( 'Activer le thème clair', 'dsa' ); ?>" data-label-dark="<?php esc_attr_e( 'Activer le thème sombre', 'dsa' ); ?>"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><span class="dsa-theme-label"><?php esc_html_e( 'Apparence', 'dsa' ); ?></span></button><?php require DSA_PLUGIN_DIR . 'views/partials/header-tools.php'; ?></div>
		</header>
		<section class="dsa-content dsa-provider-settings" aria-labelledby="dsa-providers-title">
			<div class="dsa-general-settings">
				<div><p class="dsa-eyebrow"><?php esc_html_e( 'GÉNÉRAL', 'dsa' ); ?></p><h2><?php esc_html_e( 'Mode démo', 'dsa' ); ?></h2></div>
				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
					<?php if ( function_exists( 'settings_fields' ) ) : ?>
						<?php settings_fields( 'dsa_general_settings' ); ?>
					<?php else : ?>
						<input type="hidden" name="option_page" value="dsa_general_settings">
						<input type="hidden" name="action" value="update">
						<?php wp_nonce_field( 'dsa_general_settings-options' ); ?>
					<?php endif; ?>
					<input type="hidden" name="dsa_demo_mode" value="0">
					<label class="dsa-demo-toggle">
						<input type="checkbox" role="switch" name="dsa_demo_mode" value="1" <?php checked( DSA\Storage\DemoMode::enabled() ); ?>>
						<span><?php esc_html_e( 'Utiliser les données de démonstration', 'dsa' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Le catalogue affiche un jeu de données fictives stable au lieu des produits WooCommerce.', 'dsa' ); ?></p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'dsa' ); ?></button>
				</form>
			</div>
			<?php
			$provider_settings = new DSA\Storage\ProviderSettings();
			$providers = array(
				'gemini'     => array( 'label' => 'Gemini', 'icon' => '✦' ),
				'ollama'     => array( 'label' => 'Ollama', 'icon' => '◉' ),
				'openrouter' => array( 'label' => 'OpenRouter', 'icon' => 'OR' ),
				'nvidia'     => array( 'label' => 'NVIDIA NIM', 'icon' => 'NVIDIA' ),
			);
			?>
			<section class="dsa-settings-health" aria-labelledby="dsa-settings-health-title">
				<div class="dsa-overview-intro">
					<div><p class="dsa-eyebrow"><?php esc_html_e( 'SANTÉ DU PIPELINE', 'dsa' ); ?></p><h2 id="dsa-settings-health-title"><?php esc_html_e( 'Providers IA', 'dsa' ); ?></h2></div>
					<a class="button button-primary" href="#dsa-providers-title"><?php esc_html_e( 'Configurer les providers', 'dsa' ); ?></a>
				</div>
				<div class="dsa-overview-provider-grid">
					<?php foreach ( $providers as $provider_id => $provider ) : ?>
						<?php
						$settings      = $provider_settings->get( $provider_id );
						$configured    = 'ollama' === $provider_id ? ! empty( $settings['models'] ) : ! empty( $settings['has_key'] );
						$enabled_count = count( $settings['enabled'] );
						?>
						<article class="dsa-overview-provider">
							<div class="dsa-overview-provider-top"><span class="dsa-provider-logo dsa-provider-logo-<?php echo esc_attr( $provider_id ); ?>" aria-hidden="true"><?php echo esc_html( $provider['icon'] ); ?></span><div><h3><?php echo esc_html( $provider['label'] ); ?></h3><span class="dsa-status-badge<?php echo $configured ? ' is-success' : ''; ?>"><?php echo $configured ? esc_html__( 'Configuré', 'dsa' ) : esc_html__( 'Non configuré', 'dsa' ); ?></span></div></div>
							<p><?php echo $enabled_count ? esc_html( sprintf( _n( '%d modèle activé', '%d modèles activés', $enabled_count, 'dsa' ), $enabled_count ) ) : esc_html__( 'Aucun modèle activé', 'dsa' ); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
				<div class="dsa-overview-next"><span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span><div><strong><?php esc_html_e( 'Prochaine étape', 'dsa' ); ?></strong><p><?php esc_html_e( 'Configurez au moins un modèle activé pour permettre au pipeline de préparer ses contenus.', 'dsa' ); ?></p></div></div>
			</section>
			<div class="dsa-provider-intro">
				<div class="dsa-provider-intro-copy"><p class="dsa-eyebrow"><?php esc_html_e( 'CATALOGUE IA', 'dsa' ); ?></p><h2 id="dsa-providers-title"><?php esc_html_e( 'Connectez vos providers', 'dsa' ); ?></h2><p><?php esc_html_e( 'Testez une connexion, choisissez les modèles utiles au pipeline et définissez un modèle par défaut.', 'dsa' ); ?></p></div>
				<div class="dsa-provider-security"><span class="dashicons dashicons-lock" aria-hidden="true"></span><span><?php esc_html_e( 'Clés conservées sur le serveur WordPress', 'dsa' ); ?><small><?php esc_html_e( 'Jamais affichées en clair', 'dsa' ); ?></small></span></div>
			</div>
			<div class="dsa-provider-grid">
				<article class="dsa-provider-card" data-provider-card="gemini">
					<header class="dsa-provider-card-header"><div><span class="dsa-provider-logo dsa-provider-logo-gemini" aria-hidden="true">✦</span><h3>Gemini</h3><p><?php esc_html_e( 'Modèles Google disponibles via votre clé API.', 'dsa' ); ?></p></div><span class="dsa-status-badge" data-status>Non configuré</span></header>
					<div class="dsa-provider-fields"><label for="dsa-gemini-key"><?php esc_html_e( 'Clé API', 'dsa' ); ?></label><div class="dsa-secret-field"><input id="dsa-gemini-key" type="password" autocomplete="new-password" data-api-key><button type="button" class="button-link" data-toggle-secret><?php esc_html_e( 'Afficher', 'dsa' ); ?></button></div><div class="dsa-key-actions"><button type="button" class="button-link" data-replace-key><?php esc_html_e( 'Remplacer', 'dsa' ); ?></button><button type="button" class="button-link dsa-danger-link" data-remove-key hidden><?php esc_html_e( 'Supprimer', 'dsa' ); ?></button></div><p class="description" data-key-state></p></div>
					<div class="dsa-provider-actions"><button type="button" class="button button-primary" data-discover><?php esc_html_e( 'Vérifier et charger les modèles', 'dsa' ); ?></button><button type="button" class="button" data-refresh><?php esc_html_e( 'Rafraîchir', 'dsa' ); ?></button></div>
					<div class="dsa-model-panel" data-model-panel hidden><div class="dsa-model-toolbar"><label class="screen-reader-text" for="dsa-gemini-search"><?php esc_html_e( 'Rechercher un modèle Gemini', 'dsa' ); ?></label><input id="dsa-gemini-search" type="search" placeholder="<?php esc_attr_e( 'Rechercher un modèle', 'dsa' ); ?>" data-model-search><span data-model-count></span></div><div class="dsa-model-list" data-model-list></div><button type="button" class="button button-primary" data-save><?php esc_html_e( 'Enregistrer la sélection', 'dsa' ); ?></button></div>
				</article>
				<article class="dsa-provider-card" data-provider-card="ollama">
					<header class="dsa-provider-card-header"><div><span class="dsa-provider-logo dsa-provider-logo-ollama" aria-hidden="true">◉</span><h3>Ollama</h3><p><?php esc_html_e( 'Modèles locaux servis par votre instance Ollama.', 'dsa' ); ?></p></div><span class="dsa-status-badge" data-status>Non configuré</span></header>
					<div class="dsa-provider-fields"><label for="dsa-ollama-host"><?php esc_html_e( 'URL de l’hôte', 'dsa' ); ?></label><input id="dsa-ollama-host" type="url" value="http://localhost:11434" data-host><p class="description"><?php esc_html_e( 'L’appel part du serveur WordPress : localhost désigne la machine qui héberge WordPress.', 'dsa' ); ?></p></div>
					<div class="dsa-provider-actions"><button type="button" class="button button-primary" data-discover><?php esc_html_e( 'Tester la connexion et charger les modèles', 'dsa' ); ?></button><button type="button" class="button" data-refresh><?php esc_html_e( 'Rafraîchir', 'dsa' ); ?></button></div>
					<p class="dsa-provider-help"><?php esc_html_e( 'Si WordPress n’est pas sur la même machine, utilisez une URL joignable sur votre réseau local ou via un tunnel sécurisé.', 'dsa' ); ?></p>
					<div class="dsa-model-panel" data-model-panel hidden><div class="dsa-model-toolbar"><label class="screen-reader-text" for="dsa-ollama-search"><?php esc_html_e( 'Rechercher un modèle Ollama', 'dsa' ); ?></label><input id="dsa-ollama-search" type="search" placeholder="<?php esc_attr_e( 'Rechercher un modèle', 'dsa' ); ?>" data-model-search><span data-model-count></span></div><div class="dsa-model-list" data-model-list></div><button type="button" class="button button-primary" data-save><?php esc_html_e( 'Enregistrer la sélection', 'dsa' ); ?></button></div>
				</article>
				<article class="dsa-provider-card" data-provider-card="openrouter">
					<header class="dsa-provider-card-header"><div><span class="dsa-provider-logo dsa-provider-logo-openrouter" aria-hidden="true">OR</span><h3>OpenRouter</h3><p><?php esc_html_e( 'Catalogue multi-modèles via une API compatible OpenAI.', 'dsa' ); ?></p></div><span class="dsa-status-badge" data-status>Non configuré</span></header>
					<div class="dsa-provider-fields"><label for="dsa-openrouter-key"><?php esc_html_e( 'Clé API', 'dsa' ); ?></label><div class="dsa-secret-field"><input id="dsa-openrouter-key" type="password" autocomplete="new-password" data-api-key><button type="button" class="button-link" data-toggle-secret><?php esc_html_e( 'Afficher', 'dsa' ); ?></button></div><div class="dsa-key-actions"><button type="button" class="button-link" data-replace-key><?php esc_html_e( 'Remplacer', 'dsa' ); ?></button><button type="button" class="button-link dsa-danger-link" data-remove-key hidden><?php esc_html_e( 'Supprimer', 'dsa' ); ?></button></div><p class="description" data-key-state></p></div>
					<div class="dsa-provider-actions"><button type="button" class="button button-primary" data-discover><?php esc_html_e( 'Vérifier et charger les modèles', 'dsa' ); ?></button><button type="button" class="button" data-refresh><?php esc_html_e( 'Rafraîchir', 'dsa' ); ?></button></div>
					<div class="dsa-model-panel" data-model-panel hidden><div class="dsa-model-toolbar"><label class="screen-reader-text" for="dsa-openrouter-search"><?php esc_html_e( 'Rechercher un modèle OpenRouter', 'dsa' ); ?></label><input id="dsa-openrouter-search" type="search" placeholder="<?php esc_attr_e( 'Rechercher un modèle', 'dsa' ); ?>" data-model-search><span data-model-count></span></div><div class="dsa-model-list" data-model-list></div><button type="button" class="button button-primary" data-save><?php esc_html_e( 'Enregistrer la sélection', 'dsa' ); ?></button></div>
				</article>
				<article class="dsa-provider-card" data-provider-card="nvidia">
					<header class="dsa-provider-card-header"><div><span class="dsa-provider-logo dsa-provider-logo-nvidia" aria-hidden="true">NVIDIA</span><h3>NVIDIA NIM</h3><p><?php esc_html_e( 'Modèles NVIDIA servis par l’API NIM Cloud.', 'dsa' ); ?></p></div><span class="dsa-status-badge" data-status>Non configuré</span></header>
					<div class="dsa-provider-fields"><label for="dsa-nvidia-key"><?php esc_html_e( 'Clé API NVIDIA', 'dsa' ); ?></label><div class="dsa-secret-field"><input id="dsa-nvidia-key" type="password" autocomplete="new-password" data-api-key><button type="button" class="button-link" data-toggle-secret><?php esc_html_e( 'Afficher', 'dsa' ); ?></button></div><div class="dsa-key-actions"><button type="button" class="button-link" data-replace-key><?php esc_html_e( 'Remplacer', 'dsa' ); ?></button><button type="button" class="button-link dsa-danger-link" data-remove-key hidden><?php esc_html_e( 'Supprimer', 'dsa' ); ?></button></div><p class="description" data-key-state></p></div>
					<div class="dsa-provider-actions"><button type="button" class="button button-primary" data-discover><?php esc_html_e( 'Vérifier et charger les modèles', 'dsa' ); ?></button><button type="button" class="button" data-refresh><?php esc_html_e( 'Rafraîchir', 'dsa' ); ?></button></div>
					<div class="dsa-model-panel" data-model-panel hidden><div class="dsa-model-toolbar"><label class="screen-reader-text" for="dsa-nvidia-search"><?php esc_html_e( 'Rechercher un modèle NVIDIA NIM', 'dsa' ); ?></label><input id="dsa-nvidia-search" type="search" placeholder="<?php esc_attr_e( 'Rechercher un modèle', 'dsa' ); ?>" data-model-search><span data-model-count></span></div><div class="dsa-model-list" data-model-list></div><button type="button" class="button button-primary" data-save><?php esc_html_e( 'Enregistrer la sélection', 'dsa' ); ?></button></div>
				</article>
			</div>
		</section>
	</main><div class="dsa-toast-region" role="status" aria-live="polite" aria-atomic="false"></div>
</div>

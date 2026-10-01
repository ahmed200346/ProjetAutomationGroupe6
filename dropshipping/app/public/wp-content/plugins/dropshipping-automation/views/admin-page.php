<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap dsa-shell">
	<aside class="dsa-sidebar" aria-label="<?php esc_attr_e( 'Navigation Dropshipping', 'dsa' ); ?>">
		<a class="dsa-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=dsa-dashboard' ) ); ?>">
			<span class="dsa-brand-mark" aria-hidden="true"><span class="dashicons dashicons-chart-area"></span></span>
			<span class="dsa-brand-name"><?php esc_html_e( 'Dropshipping', 'dsa' ); ?><small><?php esc_html_e( 'AUTOMATION', 'dsa' ); ?></small></span>
		</a>
		<nav class="dsa-navigation" aria-label="<?php esc_attr_e( 'Sections du tableau de bord', 'dsa' ); ?>">
			<?php foreach ( $this->pages as $slug => $navigation_page ) : ?>
				<a class="dsa-nav-link<?php echo $slug === $current_slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( menu_page_url( $slug, false ) ); ?>"<?php echo $slug === $current_slug ? ' aria-current="page"' : ''; ?>>
					<span class="dashicons <?php echo esc_attr( $navigation_page['icon'] ); ?>" aria-hidden="true"></span>
					<span><?php echo esc_html( $navigation_page['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="dsa-sidebar-footer">
			<span class="dsa-status-dot" aria-hidden="true"></span>
			<span><?php esc_html_e( 'Espace de travail', 'dsa' ); ?></span>
			<span class="dsa-version">v<?php echo esc_html( DSA_VERSION ); ?></span>
		</div>
	</aside>

	<main class="dsa-main">
		<header class="dsa-header">
			<div class="dsa-heading">
				<p class="dsa-eyebrow"><?php esc_html_e( 'TABLEAU DE BORD', 'dsa' ); ?></p>
				<h1><?php echo esc_html( $page['label'] ); ?></h1>
				<p class="dsa-page-description"><?php echo esc_html( $page['description'] ); ?></p>
				<?php require DSA_PLUGIN_DIR . 'views/partials/demo-badge.php'; ?>
			</div>
			<div class="dsa-header-actions"><button class="dsa-theme-toggle" type="button" aria-label="<?php esc_attr_e( 'Activer le thème sombre', 'dsa' ); ?>" aria-pressed="false" data-label-light="<?php esc_attr_e( 'Activer le thème clair', 'dsa' ); ?>" data-label-dark="<?php esc_attr_e( 'Activer le thème sombre', 'dsa' ); ?>"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><span class="dsa-theme-label"><?php esc_html_e( 'Apparence', 'dsa' ); ?></span></button><?php require DSA_PLUGIN_DIR . 'views/partials/header-tools.php'; ?></div>
		</header>

		<section class="dsa-content"<?php echo in_array( $current_slug, array( 'dsa-products', 'dsa-workflows', 'dsa-trends' ), true ) ? '' : ' aria-labelledby="dsa-empty-title"'; ?>>
			<?php if ( 'dsa-products' === $current_slug ) : ?>
				<?php require DSA_PLUGIN_DIR . 'views/business-pages.php'; ?>
			<?php elseif ( 'dsa-trends' === $current_slug ) : ?>
				<?php require DSA_PLUGIN_DIR . 'views/trends-page.php'; ?>
			<?php elseif ( 'dsa-workflows' === $current_slug ) : ?>
				<?php require DSA_PLUGIN_DIR . 'views/workflows-page.php'; ?>
			<?php elseif ( in_array( $current_slug, array( 'dsa-suppliers', 'dsa-orders', 'dsa-support' ), true ) ) : ?>
				<?php require DSA_PLUGIN_DIR . 'views/business-pages.php'; ?>
			<?php else : ?>
			<div class="dsa-empty-state">
				<div class="dsa-empty-art" aria-hidden="true">
					<span class="dsa-empty-ring dsa-empty-ring-one"></span>
					<span class="dsa-empty-ring dsa-empty-ring-two"></span>
					<span class="dsa-empty-icon dashicons <?php echo esc_attr( $page['icon'] ); ?>"></span>
				</div>
				<p class="dsa-eyebrow"><?php esc_html_e( 'ESPACE EN PRÉPARATION', 'dsa' ); ?></p>
				<h2 id="dsa-empty-title"><?php echo esc_html( $page['empty_title'] ); ?></h2>
				<p><?php echo esc_html( $page['empty_description'] ); ?></p>
			</div>
			<?php endif; ?>
		</section>
	</main>
	<div class="dsa-toast-region" role="status" aria-live="polite" aria-atomic="false"></div>
</div>
<?php

defined( 'ABSPATH' ) || exit;
?>
<section class="dsa-public-settings" aria-labelledby="dsa-public-settings-title">
	<div class="dsa-empty-art" aria-hidden="true">
		<span class="dsa-empty-ring dsa-empty-ring-one"></span>
		<span class="dsa-empty-ring dsa-empty-ring-two"></span>
		<span class="dsa-empty-icon dashicons dashicons-admin-generic"></span>
	</div>
	<p class="dsa-eyebrow"><?php esc_html_e( 'ESPACE ADMINISTRATEUR', 'dsa' ); ?></p>
	<h2 id="dsa-public-settings-title"><?php esc_html_e( 'Les réglages sont disponibles dans WP admin', 'dsa' ); ?></h2>
	<p><?php esc_html_e( 'La configuration des providers IA et des clés API est réservée au propriétaire du site. Aucune clé ou donnée sensible n’est exposée sur la boutique publique.', 'dsa' ); ?></p>
	<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<a class="button button-primary dsa-public-settings-link" href="<?php echo esc_url( admin_url( 'admin.php?page=dsa-settings' ) ); ?>"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e( 'Ouvrir les réglages administrateur', 'dsa' ); ?></a>
	<?php else : ?>
		<a class="button dsa-public-settings-link" href="<?php echo esc_url( wp_login_url( admin_url( 'admin.php?page=dsa-settings' ) ) ); ?>"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><?php esc_html_e( 'Se connecter en tant qu’administrateur', 'dsa' ); ?></a>
	<?php endif; ?>
</section>

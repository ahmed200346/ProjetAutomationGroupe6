<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="dsa-header-tools">
	<a class="dsa-open-site" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer">
		<span class="dashicons dashicons-external" aria-hidden="true"></span><?php esc_html_e( 'Ouvrir le site', 'dsa' ); ?>
	</a>
	<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<div class="dsa-notification-control" data-dsa-notifications>
			<button class="dsa-icon-button dsa-notification-bell" type="button" data-dsa-bell aria-label="<?php esc_attr_e( 'Ouvrir les notifications', 'dsa' ); ?>" aria-expanded="false" aria-controls="dsa-notification-panel">
				<span class="dashicons dashicons-bell" aria-hidden="true"></span><span class="dsa-notification-count" data-dsa-unread hidden></span>
			</button>
			<section class="dsa-notification-panel" id="dsa-notification-panel" data-dsa-notification-panel hidden aria-label="<?php esc_attr_e( 'Notifications récentes', 'dsa' ); ?>">
				<header><strong><?php esc_html_e( 'Notifications', 'dsa' ); ?></strong><button type="button" class="dsa-text-button" data-dsa-read-all><?php esc_html_e( 'Tout marquer comme lu', 'dsa' ); ?></button></header>
				<ul data-dsa-notification-list><li class="is-empty"><?php esc_html_e( 'Chargement…', 'dsa' ); ?></li></ul>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsa-logs' ) ); ?>"><?php esc_html_e( 'Journaux & réglages', 'dsa' ); ?></a>
			</section>
		</div>
	<?php endif; ?>
</div>
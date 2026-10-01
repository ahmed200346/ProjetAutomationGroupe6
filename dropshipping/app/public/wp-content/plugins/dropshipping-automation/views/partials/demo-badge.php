<?php

defined( 'ABSPATH' ) || exit;

if ( DSA\Storage\DemoMode::enabled() ) :
	?>
	<span class="dsa-demo-badge"><span class="dashicons dashicons-database" aria-hidden="true"></span><?php esc_html_e( 'Données de démonstration', 'dsa' ); ?></span>
	<?php
endif;
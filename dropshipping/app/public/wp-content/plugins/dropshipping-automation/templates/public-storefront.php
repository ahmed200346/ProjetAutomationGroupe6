<?php

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php wp_head(); ?>
	</head>
	<body <?php body_class( 'dsa-storefront-body' ); ?>>
		<?php wp_body_open(); ?>
		<main id="primary" class="dsa-storefront-main">
			<?php ( new DSA\Frontend\Storefront() )->render_dashboard(); ?>
		</main>
		<?php wp_footer(); ?>
	</body>
</html>

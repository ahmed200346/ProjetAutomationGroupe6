<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="dsa-storefront">
	<section class="dsa-market-intro" aria-labelledby="dsa-market-title">
		<div class="dsa-market-copy">
			<p class="dsa-market-kicker"><?php esc_html_e( 'SÉLECTION INDÉPENDANTE', 'dsa' ); ?></p>
			<h1 id="dsa-market-title"><?php esc_html_e( 'Le bon produit, au bon prix.', 'dsa' ); ?></h1>
			<p><?php esc_html_e( 'Parcourez des produits sélectionnés et comparez les offres selon votre budget et vos critères.', 'dsa' ); ?></p>
		</div>
		<div class="dsa-market-note">
			<span class="dsa-market-note-mark" aria-hidden="true">01</span>
			<span><?php esc_html_e( 'Des offres à découvrir', 'dsa' ); ?></span>
		</div>
	</section>

	<section class="dsa-catalog-section" aria-labelledby="dsa-catalog-title">
		<div class="dsa-catalog-heading">
			<div>
				<p class="dsa-market-kicker"><?php esc_html_e( 'CATALOGUE', 'dsa' ); ?></p>
				<h2 id="dsa-catalog-title"><?php esc_html_e( 'Trouver votre prochain favori', 'dsa' ); ?></h2>
			</div>
			<span class="dsa-result-count"><?php echo esc_html( sprintf( _n( '%d offre', '%d offres', $product_count, 'dsa' ), $product_count ) ); ?></span>
		</div>

		<form class="dsa-product-filters" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="hidden" name="dsa_page" value="dsa-products">
			<label class="dsa-filter-search">
				<span><?php esc_html_e( 'Rechercher', 'dsa' ); ?></span>
				<input type="search" name="dsa_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Nom du produit…', 'dsa' ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Catégorie', 'dsa' ); ?></span>
				<select name="dsa_category">
					<option value=""><?php esc_html_e( 'Toutes', 'dsa' ); ?></option>
					<?php foreach ( $categories as $product_category ) : ?>
						<option value="<?php echo esc_attr( $product_category->slug ); ?>" <?php selected( $category, $product_category->slug ); ?>><?php echo esc_html( $product_category->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Pays d’origine', 'dsa' ); ?></span>
				<select name="dsa_country">
					<option value=""><?php esc_html_e( 'Tous les pays', 'dsa' ); ?></option>
					<?php foreach ( $allowed_countries as $country_code => $country_name ) : ?>
						<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( $country, $country_code ); ?>><?php echo esc_html( $country_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Qualité minimale', 'dsa' ); ?></span>
				<select name="dsa_quality">
					<option value=""><?php esc_html_e( 'Toutes les notes', 'dsa' ); ?></option>
					<?php foreach ( $quality_options as $quality_score => $quality_label ) : ?>
						<option value="<?php echo esc_attr( $quality_score ); ?>" <?php selected( $quality, $quality_score ); ?>><?php echo esc_html( $quality_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="dsa-filter-price">
				<span><?php esc_html_e( 'Budget maximum', 'dsa' ); ?></span>
				<span class="dsa-price-input"><span aria-hidden="true">€</span><input type="number" name="dsa_max_price" min="0" step="0.01" value="<?php echo $max_price > 0 ? esc_attr( (string) $max_price ) : ''; ?>" placeholder="<?php esc_attr_e( 'Sans limite', 'dsa' ); ?>"></span>
			</label>
			<button class="dsa-filter-submit" type="submit"><?php esc_html_e( 'Voir les offres', 'dsa' ); ?><span aria-hidden="true">→</span></button>
		</form>

		<?php if ( ! $woocommerce_available ) : ?>
			<div class="dsa-catalog-empty" role="status">
				<span class="dsa-empty-symbol" aria-hidden="true">＋</span>
				<h3><?php esc_html_e( 'Le catalogue se prépare', 'dsa' ); ?></h3>
				<p><?php esc_html_e( 'Les produits apparaîtront ici dès que la boutique sera connectée et que des offres auront été publiées.', 'dsa' ); ?></p>
			</div>
		<?php elseif ( empty( $products ) ) : ?>
			<div class="dsa-catalog-empty" role="status">
				<span class="dsa-empty-symbol" aria-hidden="true">⌕</span>
				<h3><?php esc_html_e( 'Aucune offre pour ces critères', 'dsa' ); ?></h3>
				<p><?php esc_html_e( 'Essayez d’élargir votre recherche ou revenez bientôt pour découvrir de nouvelles sélections.', 'dsa' ); ?></p>
			</div>
		<?php else : ?>
			<div class="dsa-product-grid">
				<?php foreach ( $products as $product ) : ?>
					<?php $is_demo_product = is_array( $product ); ?>
					<article class="dsa-product-card">
						<?php if ( $is_demo_product ) : ?>
							<div class="dsa-product-image" aria-hidden="true"><span class="dashicons dashicons-products"></span></div>
						<?php else : ?>
							<a class="dsa-product-image" href="<?php echo esc_url( $product->get_permalink() ); ?>" aria-label="<?php echo esc_attr( $product->get_name() ); ?>"><?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?></a>
						<?php endif; ?>
						<div class="dsa-product-details">
							<h3><?php echo $is_demo_product ? esc_html( $product['titre'] ) : '<a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a>'; ?></h3>
							<p class="dsa-product-price"><?php echo $is_demo_product ? ( function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $product['prix_vente'] ) ) : esc_html( number_format_i18n( $product['prix_vente'], 2 ) . ' €' ) ) : wp_kses_post( $product->get_price_html() ); ?></p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<footer class="dsa-market-footer">
		<p><?php esc_html_e( 'La disponibilité et les délais peuvent varier selon le pays et le fournisseur.', 'dsa' ); ?></p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Retour en haut', 'dsa' ); ?> <span aria-hidden="true">↑</span></a>
	</footer>
</div>
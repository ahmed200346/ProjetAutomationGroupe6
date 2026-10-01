<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="dsa-trends" data-trends aria-busy="true">
	<section class="dsa-trends-toolbar" aria-label="<?php esc_attr_e( 'Filtres des tendances', 'dsa' ); ?>">
		<label class="dsa-trend-filter">
			<span><?php esc_html_e( 'Période', 'dsa' ); ?></span>
			<select data-filter="period">
				<option value="7"><?php esc_html_e( '7 derniers jours', 'dsa' ); ?></option>
				<option value="30" selected><?php esc_html_e( '30 derniers jours', 'dsa' ); ?></option>
				<option value="90"><?php esc_html_e( '90 derniers jours', 'dsa' ); ?></option>
			</select>
		</label>
		<label class="dsa-trend-filter">
			<span><?php esc_html_e( 'Catégories', 'dsa' ); ?></span>
			<select data-filter="categories" multiple size="1" aria-label="<?php esc_attr_e( 'Sélectionner une ou plusieurs catégories', 'dsa' ); ?>"></select>
		</label>
		<label class="dsa-trend-filter">
			<span><?php esc_html_e( 'Plateforme sociale', 'dsa' ); ?></span>
			<select data-filter="platform">
				<option value=""><?php esc_html_e( 'Toutes les plateformes', 'dsa' ); ?></option>
				<option value="x">X</option>
				<option value="facebook">Facebook</option>
				<option value="instagram">Instagram</option>
			</select>
		</label>
		<label class="dsa-trend-filter">
			<span><?php esc_html_e( 'Source fournisseur', 'dsa' ); ?></span>
			<select data-filter="source">
				<option value=""><?php esc_html_e( 'Toutes les sources', 'dsa' ); ?></option>
			</select>
		</label>
		<button class="dsa-button dsa-button-primary" type="button" data-apply-filters>
			<span class="dashicons dashicons-filter" aria-hidden="true"></span><?php esc_html_e( 'Appliquer', 'dsa' ); ?>
		</button>
	</section>

	<section class="dsa-scrape-latest" aria-label="<?php esc_attr_e( 'Dernière exécution du scraping', 'dsa' ); ?>">
		<div class="dsa-scrape-icon" aria-hidden="true"><span class="dashicons dashicons-update"></span></div>
		<div class="dsa-scrape-copy">
			<p class="dsa-eyebrow"><?php esc_html_e( 'DERNIÈRE EXÉCUTION DU SCRAPING', 'dsa' ); ?></p>
			<strong data-last-run-date><?php esc_html_e( 'Chargement…', 'dsa' ); ?></strong>
			<span data-last-run-label></span>
		</div>
		<span class="dsa-run-status" data-last-run-status><?php esc_html_e( 'En attente', 'dsa' ); ?></span>
		<a class="dsa-button dsa-button-secondary" data-run-log href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=dsa-logs' ) : add_query_arg( 'dsa_page', 'dsa-logs', home_url( '/' ) ) ); ?>">
			<span class="dashicons dashicons-list-view" aria-hidden="true"></span><?php esc_html_e( 'Ouvrir le journal', 'dsa' ); ?>
		</a>
	</section>

	<div class="dsa-trend-chart-grid">
		<section class="dsa-trend-panel dsa-trend-panel-wide" data-chart-card="price">
			<header class="dsa-trend-panel-heading">
				<div><p class="dsa-eyebrow"><?php esc_html_e( 'PRIX MOYEN', 'dsa' ); ?></p><h2><?php esc_html_e( 'Évolution par catégorie', 'dsa' ); ?></h2></div>
				<div class="dsa-price-changes" data-price-changes></div>
			</header>
			<div class="dsa-trend-chart-area"><p class="dsa-trend-state" data-chart-state role="status"><?php esc_html_e( 'Chargement des données…', 'dsa' ); ?></p><canvas data-chart-canvas hidden aria-label="<?php esc_attr_e( 'Évolution du prix moyen par catégorie', 'dsa' ); ?>"></canvas></div>
		</section>
		<section class="dsa-trend-panel" data-chart-card="categories">
			<header class="dsa-trend-panel-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'SIGNAUX', 'dsa' ); ?></p><h2><?php esc_html_e( 'Catégories les plus demandées', 'dsa' ); ?></h2></div></header>
			<div class="dsa-trend-chart-area"><p class="dsa-trend-state" data-chart-state role="status"><?php esc_html_e( 'Chargement des données…', 'dsa' ); ?></p><canvas data-chart-canvas hidden aria-label="<?php esc_attr_e( 'Volume de signaux par catégorie', 'dsa' ); ?>"></canvas></div>
		</section>
		<section class="dsa-trend-panel dsa-trend-panel-donut" data-chart-card="platforms">
			<header class="dsa-trend-panel-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'ORIGINE DES SIGNAUX', 'dsa' ); ?></p><h2><?php esc_html_e( 'Plateformes sociales', 'dsa' ); ?></h2></div></header>
			<div class="dsa-trend-chart-area"><p class="dsa-trend-state" data-chart-state role="status"><?php esc_html_e( 'Chargement des données…', 'dsa' ); ?></p><canvas data-chart-canvas hidden aria-label="<?php esc_attr_e( 'Répartition des signaux par plateforme', 'dsa' ); ?>"></canvas></div>
		</section>
	</div>

	<section class="dsa-trend-panel dsa-trend-table-panel">
		<header class="dsa-trend-panel-heading">
			<div><p class="dsa-eyebrow"><?php esc_html_e( 'CLASSEMENT', 'dsa' ); ?></p><h2><?php esc_html_e( 'Produits tendance', 'dsa' ); ?></h2></div>
			<button class="dsa-button dsa-button-secondary" type="button" data-export="products"><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Exporter CSV', 'dsa' ); ?></button>
		</header>
		<div class="dsa-table-scroll"><table class="dsa-trends-table" data-table="products">
			<thead><tr>
				<th scope="col"><button type="button" data-sort="product"><?php esc_html_e( 'Produit', 'dsa' ); ?></button></th>
				<th scope="col"><button type="button" data-sort="category"><?php esc_html_e( 'Catégorie', 'dsa' ); ?></button></th>
				<th scope="col"><button type="button" data-sort="mentions"><?php esc_html_e( 'Mentions', 'dsa' ); ?></button></th>
				<th scope="col"><button type="button" data-sort="growth"><?php esc_html_e( 'Croissance', 'dsa' ); ?></button></th>
				<th scope="col"><button type="button" data-sort="average_price"><?php esc_html_e( 'Prix moyen', 'dsa' ); ?></button></th>
				<th scope="col"><button type="button" data-sort="margin"><?php esc_html_e( 'Marge estimée', 'dsa' ); ?></button></th>
				<th scope="col"><button type="button" data-sort="score"><?php esc_html_e( 'Score', 'dsa' ); ?></button></th>
				<th scope="col"><?php esc_html_e( 'Tendance', 'dsa' ); ?></th>
			</tr></thead>
			<tbody data-table-body="products"><tr><td colspan="8" data-table-state><?php esc_html_e( 'Chargement des données…', 'dsa' ); ?></td></tr></tbody>
		</table></div>
	</section>

	<section class="dsa-trend-panel dsa-trend-table-panel">
		<header class="dsa-trend-panel-heading">
			<div><p class="dsa-eyebrow"><?php esc_html_e( '30 DERNIERS JOURS', 'dsa' ); ?></p><h2><?php esc_html_e( 'Produits demandés par catégorie', 'dsa' ); ?></h2></div>
			<button class="dsa-button dsa-button-secondary" type="button" data-export="demanded"><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Exporter CSV', 'dsa' ); ?></button>
		</header>
		<div class="dsa-table-scroll"><table class="dsa-trends-table" data-table="demanded">
			<thead><tr><th scope="col"><?php esc_html_e( 'Catégorie', 'dsa' ); ?></th><th scope="col"><?php esc_html_e( 'Produit demandé', 'dsa' ); ?></th><th scope="col"><?php esc_html_e( 'Signaux', 'dsa' ); ?></th><th scope="col"><?php esc_html_e( 'Mentions', 'dsa' ); ?></th></tr></thead>
			<tbody data-table-body="demanded"><tr><td colspan="4" data-table-state><?php esc_html_e( 'Chargement des données…', 'dsa' ); ?></td></tr></tbody>
		</table></div>
	</section>
</div>
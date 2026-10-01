<?php

defined( 'ABSPATH' ) || exit;

use DSA\Storage\ProviderSettings;
use DSA\Storage\Repositories\Demo\DemoDataset;
use DSA\Storage\Repositories\RepositoryFactory;

$repositories = new RepositoryFactory();
$products = $repositories->products()->all();
$categories = $repositories->categories()->all();
$orders = $repositories->orders()->all();
$reviews = DemoDataset::table( 'reviews' );
$snapshots = DemoDataset::table( 'price_snapshots' );
$demo_state = get_transient( DSA\REST\BusinessDemoController::STATE_KEY );
$demo_state = is_array( $demo_state ) ? $demo_state : array();
$product_states = isset( $demo_state['products'] ) && is_array( $demo_state['products'] ) ? $demo_state['products'] : array();
$order_states = isset( $demo_state['orders'] ) && is_array( $demo_state['orders'] ) ? $demo_state['orders'] : array();
$supplier_states = isset( $demo_state['suppliers'] ) && is_array( $demo_state['suppliers'] ) ? $demo_state['suppliers'] : array();
$support_states = isset( $demo_state['support'] ) && is_array( $demo_state['support'] ) ? $demo_state['support'] : array();
$category_names = array();
foreach ( $categories as $category ) {
	$category_names[ $category['id'] ] = $category['nom'];
}
$review_groups = array();
foreach ( $reviews as $review ) {
	$review_groups[ $review['product_id'] ][] = $review;
}
$price_groups = array();
foreach ( $snapshots as $snapshot ) {
	$price_groups[ $snapshot['product_id'] ][] = $snapshot;
}
?>
<?php if ( 'dsa-products' === $current_slug ) : ?>
	<?php
	$visible_products = array();
	foreach ( $products as $product ) {
		$status = $product_states[ $product['id'] ] ?? $product['statut'];
		$status = 'draft' === $status ? 'pending' : $status;
		$visible_products[] = array_merge( $product, array( 'statut' => $status ) );
	}
	?>
	<div class="dsa-business-page dsa-products-page" data-business-page="products">
		<header class="dsa-business-heading">
			<div><p class="dsa-eyebrow"><?php esc_html_e( 'GARDE-FOU HUMAIN', 'dsa' ); ?></p><h2><?php esc_html_e( 'Validation des produits', 'dsa' ); ?></h2><p><?php esc_html_e( 'Les candidats restent en attente tant que vous ne les avez pas validés.', 'dsa' ); ?></p></div>
			<span class="dsa-badge"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e( 'Aucune publication automatique', 'dsa' ); ?></span>
		</header>
		<div class="dsa-tabs dsa-business-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Statut des produits', 'dsa' ); ?>">
			<button class="dsa-tab" type="button" role="tab" aria-selected="true" data-product-tab="shortlist"><?php esc_html_e( 'Shortlist', 'dsa' ); ?></button>
			<button class="dsa-tab" type="button" role="tab" aria-selected="false" data-product-tab="pending"><?php esc_html_e( 'En attente de validation', 'dsa' ); ?></button>
			<button class="dsa-tab" type="button" role="tab" aria-selected="false" data-product-tab="published"><?php esc_html_e( 'Publiés', 'dsa' ); ?></button>
			<button class="dsa-tab" type="button" role="tab" aria-selected="false" data-product-tab="rejected"><?php esc_html_e( 'Rejetés', 'dsa' ); ?></button>
		</div>
		<div class="dsa-business-toolbar">
			<label class="dsa-business-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Rechercher un produit', 'dsa' ); ?></span><input type="search" placeholder="<?php esc_attr_e( 'Rechercher un produit…', 'dsa' ); ?>" data-product-search></label>
			<label><span class="screen-reader-text"><?php esc_html_e( 'Filtrer par catégorie', 'dsa' ); ?></span><select data-product-category><option value=""><?php esc_html_e( 'Toutes les catégories', 'dsa' ); ?></option><?php foreach ( $categories as $category ) : ?><option value="<?php echo esc_attr( $category['id'] ); ?>"><?php echo esc_html( $category['nom'] ); ?></option><?php endforeach; ?></select></label>
			<label><span class="screen-reader-text"><?php esc_html_e( 'Trier les produits', 'dsa' ); ?></span><select data-product-sort><option value="score_global"><?php esc_html_e( 'Score : plus élevé', 'dsa' ); ?></option><option value="marge_pct"><?php esc_html_e( 'Marge : plus élevée', 'dsa' ); ?></option><option value="titre"><?php esc_html_e( 'Nom : A à Z', 'dsa' ); ?></option></select></label>
			<span class="dsa-business-result-count" data-product-count></span>
		</div>
		<div class="dsa-business-bulk" data-product-bulk hidden><span data-selected-count></span><button type="button" class="dsa-button" data-bulk-action="approve"><?php esc_html_e( 'Valider', 'dsa' ); ?></button><button type="button" class="dsa-button" data-bulk-action="reject"><?php esc_html_e( 'Rejeter', 'dsa' ); ?></button><button type="button" class="dsa-button" data-bulk-action="rescore"><?php esc_html_e( 'Renvoyer au scoring', 'dsa' ); ?></button></div>
		<div class="dsa-table-wrap dsa-business-table-wrap"><table class="dsa-table dsa-business-table"><thead><tr><th><input type="checkbox" data-select-visible aria-label="<?php esc_attr_e( 'Sélectionner les produits visibles', 'dsa' ); ?>"></th><th><button type="button" data-product-sort-key="titre"><?php esc_html_e( 'Produit', 'dsa' ); ?></button></th><th><?php esc_html_e( 'Catégorie', 'dsa' ); ?></th><th><button type="button" data-product-sort-key="score_global"><?php esc_html_e( 'Score', 'dsa' ); ?></button></th><th><?php esc_html_e( 'Demande', 'dsa' ); ?></th><th><?php esc_html_e( 'Marge', 'dsa' ); ?></th><th><?php esc_html_e( 'Tendance', 'dsa' ); ?></th><th><?php esc_html_e( 'Concurrence', 'dsa' ); ?></th><th><?php esc_html_e( 'Statut', 'dsa' ); ?></th><th><?php esc_html_e( 'Détail', 'dsa' ); ?></th></tr></thead><tbody data-product-rows>
		<?php foreach ( $visible_products as $product ) : ?>
			<?php
			$detail = array(
				'id' => $product['id'],
				'name' => $product['titre'],
				'category' => $category_names[ $product['category_id'] ] ?? '',
				'source' => $product['source'],
				'purchase' => $product['prix_achat'],
				'sale' => $product['prix_vente'],
				'margin' => $product['marge_pct'],
				'delivery' => $product['delai_livraison_jours'],
				'rating' => $product['note_moyenne'],
				'reviews_count' => $product['nb_avis'],
				'reviews' => array_slice( $review_groups[ $product['id'] ] ?? array(), 0, 3 ),
				'prices' => array_slice( $price_groups[ $product['id'] ] ?? array(), -30 ),
			);
			?>
			<tr data-product-row data-id="<?php echo esc_attr( $product['id'] ); ?>" data-status="<?php echo esc_attr( $product['statut'] ); ?>" data-category="<?php echo esc_attr( $product['category_id'] ); ?>" data-name="<?php echo esc_attr( $product['titre'] ); ?>" data-score="<?php echo esc_attr( $product['score_global'] ); ?>" data-margin="<?php echo esc_attr( $product['marge_pct'] ); ?>">
				<td><input type="checkbox" data-product-select aria-label="<?php echo esc_attr( sprintf( __( 'Sélectionner %s', 'dsa' ), $product['titre'] ) ); ?>"></td><td><strong><?php echo esc_html( $product['titre'] ); ?></strong><small>#<?php echo esc_html( $product['id'] ); ?> · <?php echo esc_html( ucfirst( $product['source'] ) ); ?></small></td><td><?php echo esc_html( $category_names[ $product['category_id'] ] ?? '' ); ?></td><td><strong><?php echo esc_html( $product['score_global'] ); ?></strong><span class="dsa-score-meter"><i style="width:<?php echo esc_attr( $product['score_global'] ); ?>%"></i></span></td><td><?php echo esc_html( $product['score_demande'] ); ?></td><td><?php echo esc_html( number_format_i18n( $product['marge_pct'], 1 ) ); ?>%</td><td><?php echo esc_html( $product['score_tendance'] ); ?></td><td><?php echo esc_html( $product['score_concurrence'] ); ?></td><td><span class="dsa-demo-status is-<?php echo esc_attr( $product['statut'] ); ?>"><?php echo esc_html( array( 'shortlist' => __( 'Shortlist', 'dsa' ), 'pending' => __( 'À valider', 'dsa' ), 'published' => __( 'Publié en démo', 'dsa' ), 'rejected' => __( 'Rejeté', 'dsa' ) )[ $product['statut'] ] ?? $product['statut'] ); ?></span></td><td><button class="dsa-icon-button" type="button" data-product-detail="<?php echo esc_attr( wp_json_encode( $detail ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Détails de %s', 'dsa' ), $product['titre'] ) ); ?>"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<p class="dsa-business-empty" data-product-empty hidden><?php esc_html_e( 'Aucun produit ne correspond à ces filtres.', 'dsa' ); ?></p>
		<aside class="dsa-side-panel" data-product-panel aria-labelledby="dsa-product-panel-title" aria-hidden="true" inert><button class="dsa-icon-button dsa-panel-close" type="button" data-panel-close aria-label="<?php esc_attr_e( 'Fermer le panneau', 'dsa' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button><p class="dsa-eyebrow"><?php esc_html_e( 'FICHE PRODUIT', 'dsa' ); ?></p><h2 id="dsa-product-panel-title" data-detail-name></h2><div data-product-detail-content></div></aside>
	</div>
<?php elseif ( 'dsa-suppliers' === $current_slug ) : ?>
	<div class="dsa-business-page" data-business-page="suppliers">
		<header class="dsa-business-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'SOURCING', 'dsa' ); ?></p><h2><?php esc_html_e( 'Fournisseurs & sourcing', 'dsa' ); ?></h2><p><?php esc_html_e( 'Comparaison des sources de démonstration, sans connexion externe.', 'dsa' ); ?></p></div><span class="dsa-badge"><?php esc_html_e( 'Données simulées', 'dsa' ); ?></span></header>
		<div class="dsa-supplier-grid">
		<?php foreach ( array( 'amazon' => 'Amazon', 'alibaba' => 'Alibaba', 'aliexpress' => 'AliExpress' ) as $supplier_id => $supplier_name ) : ?>
			<?php
			$sourced_products = array_values( array_filter( $products, static function ( $product ) use ( $supplier_id ) { return $product['source'] === $supplier_id; } ) );
			$average_margin = count( $sourced_products ) ? array_sum( array_column( $sourced_products, 'marge_pct' ) ) / count( $sourced_products ) : 0;
			$average_delivery = count( $sourced_products ) ? array_sum( array_column( $sourced_products, 'delai_livraison_jours' ) ) / count( $sourced_products ) : 0;
			$connected = isset( $supplier_states[ $supplier_id ] ) ? (bool) $supplier_states[ $supplier_id ] : false;
			?>
			<article class="dsa-supplier-row"><div class="dsa-supplier-brand"><span class="dsa-supplier-mark"><?php echo esc_html( strtoupper( substr( $supplier_name, 0, 1 ) ) ); ?></span><div><h3><?php echo esc_html( $supplier_name ); ?></h3><span class="dsa-demo-status is-<?php echo $connected ? 'connected' : 'disconnected'; ?>" data-supplier-status><?php echo $connected ? esc_html__( 'Connecté en démo', 'dsa' ) : esc_html__( 'Non connecté', 'dsa' ); ?></span></div></div><dl class="dsa-supplier-metrics"><div><dt><?php esc_html_e( 'Produits appariés', 'dsa' ); ?></dt><dd><?php echo esc_html( count( $sourced_products ) ); ?></dd></div><div><dt><?php esc_html_e( 'Marge moyenne', 'dsa' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $average_margin, 1 ) ); ?>%</dd></div><div><dt><?php esc_html_e( 'Livraison moyenne', 'dsa' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $average_delivery, 1 ) ); ?> <?php esc_html_e( 'jours', 'dsa' ); ?></dd></div><div><dt><?php esc_html_e( 'Commande minimale', 'dsa' ); ?></dt><dd><?php echo esc_html( 'alibaba' === $supplier_id ? '2' : '1' ); ?> <?php esc_html_e( 'unité', 'dsa' ); ?></dd></div></dl><button type="button" class="dsa-button" data-supplier-action data-supplier="<?php echo esc_attr( $supplier_id ); ?>" data-connected="<?php echo $connected ? '1' : '0'; ?>"><?php echo $connected ? esc_html__( 'Simuler la déconnexion', 'dsa' ) : esc_html__( 'Simuler la connexion', 'dsa' ); ?></button></article>
		<?php endforeach; ?>
		</div>
		<p class="dsa-inline-note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'Les indicateurs sont issus du jeu de données local. Aucun fournisseur n’est contacté.', 'dsa' ); ?></p>
	</div>
<?php elseif ( 'dsa-orders' === $current_slug ) : ?>
	<div class="dsa-business-page" data-business-page="orders">
		<header class="dsa-business-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'SUIVI', 'dsa' ); ?></p><h2><?php esc_html_e( 'Commandes & suivi', 'dsa' ); ?></h2><p><?php esc_html_e( 'Suivi de démonstration uniquement : aucun paiement ou envoi réel.', 'dsa' ); ?></p></div><span class="dsa-badge"><?php echo esc_html( count( $orders ) ); ?> <?php esc_html_e( 'commandes', 'dsa' ); ?></span></header>
		<div class="dsa-table-wrap dsa-business-table-wrap"><table class="dsa-table dsa-business-table dsa-orders-table"><thead><tr><th><?php esc_html_e( 'Commande', 'dsa' ); ?></th><th><?php esc_html_e( 'Produit', 'dsa' ); ?></th><th><?php esc_html_e( 'Fournisseur', 'dsa' ); ?></th><th><?php esc_html_e( 'Suivi', 'dsa' ); ?></th><th><?php esc_html_e( 'Date', 'dsa' ); ?></th><th><?php esc_html_e( 'Statut', 'dsa' ); ?></th><th><?php esc_html_e( 'Chronologie', 'dsa' ); ?></th></tr></thead><tbody>
		<?php foreach ( $orders as $order ) : ?>
			<?php $product = $repositories->products()->find( $order['product_id'] ); $status = $order_states[ $order['id'] ] ?? $order['statut']; $order_detail = array( 'id' => $order['id'], 'number' => $order['numero_suivi'], 'date' => $order['date'], 'status' => $status, 'supplier' => $order['fournisseur'], 'product' => $product['titre'] ?? __( 'Produit', 'dsa' ) ); ?>
			<tr><td><strong>#<?php echo esc_html( $order['id'] + 7000 ); ?></strong></td><td><?php echo esc_html( $product['titre'] ?? '' ); ?></td><td><?php echo esc_html( ucfirst( $order['fournisseur'] ) ); ?></td><td><code><?php echo esc_html( $order['numero_suivi'] ); ?></code></td><td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $order['date'] . ' 12:00:00 UTC' ) ) ); ?></td><td><span class="dsa-demo-status is-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( array( 'pending' => __( 'Reçue', 'dsa' ), 'processing' => __( 'Transmise au fournisseur', 'dsa' ), 'shipped' => __( 'Expédiée', 'dsa' ), 'delivered' => __( 'Livrée', 'dsa' ) )[ $status ] ?? $status ); ?></span></td><td><button class="dsa-button dsa-button-quiet" type="button" data-order-detail="<?php echo esc_attr( wp_json_encode( $order_detail ) ); ?>"><?php esc_html_e( 'Voir', 'dsa' ); ?></button></td></tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<aside class="dsa-side-panel" data-order-panel aria-labelledby="dsa-order-panel-title" aria-hidden="true" inert><button class="dsa-icon-button dsa-panel-close" type="button" data-panel-close aria-label="<?php esc_attr_e( 'Fermer le panneau', 'dsa' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button><p class="dsa-eyebrow"><?php esc_html_e( 'SUIVI DE COMMANDE', 'dsa' ); ?></p><h2 id="dsa-order-panel-title" data-order-title></h2><div data-order-detail-content></div></aside>
	</div>
<?php elseif ( 'dsa-support' === $current_slug ) : ?>
	<div class="dsa-business-page" data-business-page="support">
		<header class="dsa-business-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'RELATION CLIENT', 'dsa' ); ?></p><h2><?php esc_html_e( 'SAV & avis', 'dsa' ); ?></h2><p><?php esc_html_e( 'Aperçu simulé des questions, remboursements et retours produits.', 'dsa' ); ?></p></div><span class="dsa-badge"><?php esc_html_e( 'Mode démo', 'dsa' ); ?></span></header>
		<section class="dsa-support-overview"><div class="dsa-support-summary"><p class="dsa-eyebrow"><?php esc_html_e( 'BOÎTE DE RÉCEPTION', 'dsa' ); ?></p><h3><?php esc_html_e( 'Questions clients', 'dsa' ); ?></h3><div class="dsa-support-counts"><div><strong>12</strong><span><?php esc_html_e( 'FAQ auto-répondues', 'dsa' ); ?></span></div><div><strong>4</strong><span><?php esc_html_e( 'À traiter', 'dsa' ); ?></span></div></div></div><div class="dsa-support-policy"><p class="dsa-eyebrow"><?php esc_html_e( 'REMBOURSEMENTS', 'dsa' ); ?></p><h3><?php esc_html_e( 'Politique fournisseur', 'dsa' ); ?></h3><p><?php esc_html_e( 'Les demandes restent à vérifier selon les conditions de chaque source avant toute décision.', 'dsa' ); ?></p><span class="dsa-demo-status is-pending"><?php esc_html_e( 'Aucune demande envoyée', 'dsa' ); ?></span></div></section>
		<section class="dsa-business-section"><header class="dsa-section-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'QUESTIONS', 'dsa' ); ?></p><h3><?php esc_html_e( 'À traiter', 'dsa' ); ?></h3></div><span class="dsa-badge"><?php esc_html_e( 'Exemples', 'dsa' ); ?></span></header><div class="dsa-question-list">
		<?php
		$questions = array(
			array( 1, __( 'Quand ma commande sera-t-elle expédiée ?', 'dsa' ), __( 'Commande #7008 · reçue il y a 2 h', 'dsa' ), 'reply' ),
			array( 2, __( 'Le produit est-il compatible avec mon appareil ?', 'dsa' ), __( 'Support magnétique · reçue hier', 'dsa' ), 'faq' ),
			array( 3, __( 'Je souhaite demander un remboursement.', 'dsa' ), __( 'Commande #7012 · fournisseur à vérifier', 'dsa' ), 'refund' ),
		);
		foreach ( $questions as $question ) :
			$resolved = isset( $support_states[ $question[0] ] );
			?>
			<article class="dsa-question-row"><div><strong><?php echo esc_html( $question[1] ); ?></strong><span><?php echo esc_html( $question[2] ); ?></span></div><span class="dsa-demo-status is-<?php echo $resolved ? 'connected' : 'pending'; ?>"><?php echo $resolved ? esc_html__( 'Action simulée', 'dsa' ) : esc_html__( 'À traiter', 'dsa' ); ?></span><button type="button" class="dsa-button" data-support-action="<?php echo esc_attr( $question[3] ); ?>" data-support-id="<?php echo esc_attr( $question[0] ); ?>"><?php echo esc_html( array( 'reply' => __( 'Répondre', 'dsa' ), 'faq' => __( 'Marquer FAQ', 'dsa' ), 'refund' => __( 'Examiner', 'dsa' ) )[ $question[3] ] ); ?></button></article>
		<?php endforeach; ?>
		</div></section>
		<section class="dsa-business-section"><header class="dsa-section-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'QUALITÉ PRODUIT', 'dsa' ); ?></p><h3><?php esc_html_e( 'Notes moyennes', 'dsa' ); ?></h3></div><span class="dsa-badge"><?php esc_html_e( 'Données de démonstration', 'dsa' ); ?></span></header><div class="dsa-rating-grid">
		<?php foreach ( $products as $product ) : ?>
			<div class="dsa-rating-row"><span><?php echo esc_html( $product['titre'] ); ?></span><strong><span aria-hidden="true">★</span> <?php echo esc_html( number_format_i18n( $product['note_moyenne'], 1 ) ); ?></strong><small><?php echo esc_html( $product['nb_avis'] ); ?> <?php esc_html_e( 'avis', 'dsa' ); ?></small></div>
		<?php endforeach; ?>
		</div></section>
		<aside class="dsa-feedback-loop"><div class="dsa-feedback-icon"><span class="dashicons dashicons-chart-line" aria-hidden="true"></span></div><div><p class="dsa-eyebrow"><?php esc_html_e( 'BOUCLE DE FEEDBACK', 'dsa' ); ?></p><h3><?php esc_html_e( 'Les avis affinent le score futur', 'dsa' ); ?></h3><p><?php esc_html_e( 'Les notes et motifs de retour sont agrégés par produit : ils modulent la qualité perçue et le risque dans le scoring. Aucun profil client n’est utilisé.', 'dsa' ); ?></p></div><div class="dsa-feedback-steps"><span><?php esc_html_e( 'Avis', 'dsa' ); ?></span><span><?php esc_html_e( 'Qualité', 'dsa' ); ?></span><span><?php esc_html_e( 'Score futur', 'dsa' ); ?></span></div></aside>
	</div>
<?php endif; ?>

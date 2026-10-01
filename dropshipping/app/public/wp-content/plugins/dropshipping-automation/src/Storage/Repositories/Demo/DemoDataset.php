<?php

namespace DSA\Storage\Repositories\Demo;

defined( 'ABSPATH' ) || exit;

final class DemoDataset {
	private static $tables;

	public static function table( string $name ): array {
		if ( null === self::$tables ) {
			self::$tables = self::generate();
		}

		return self::$tables[ $name ] ?? array();
	}

	private static function generate(): array {
		$category_names = array(
			1 => array( 'nom' => 'Gadgets maison', 'slug' => 'gadgets-maison' ),
			2 => array( 'nom' => 'Accessoires téléphone', 'slug' => 'accessoires-telephone' ),
			3 => array( 'nom' => 'Fitness & bien-être', 'slug' => 'fitness-bien-etre' ),
			4 => array( 'nom' => 'Beauté & soins', 'slug' => 'beaute-soins' ),
			5 => array( 'nom' => 'Animaux', 'slug' => 'animaux' ),
			6 => array( 'nom' => 'Cuisine pratique', 'slug' => 'cuisine-pratique' ),
			7 => array( 'nom' => 'Rangement & bureau', 'slug' => 'rangement-bureau' ),
			8 => array( 'nom' => 'Loisirs plein air', 'slug' => 'loisirs-plein-air' ),
		);
		$names = array(
			1 => array( 'Lampe connectée', 'Capteur de porte', 'Mini aspirateur', 'Diffuseur compact', 'Veilleuse tactile', 'Station météo', 'Prise intelligente', 'Purificateur de bureau', 'Ruban lumineux', 'Détecteur de fuite' ),
			2 => array( 'Support magnétique', 'Chargeur pliable', 'Coque renforcée', 'Trépied de poche', 'Câble tressé', 'Batterie compacte', 'Support de voiture', 'Anneau lumineux', 'Pochette étanche', 'Station multiport' ),
			3 => array( 'Bande de résistance', 'Rouleau de massage', 'Corde à sauter', 'Bouteille graduée', 'Sangle d’étirement', 'Compteur de répétitions', 'Tapis pliable', 'Poignée de musculation', 'Coussin d’équilibre', 'Sac de sport léger' ),
			4 => array( 'Brosse nettoyante', 'Miroir lumineux', 'Rouleau visage', 'Trousse de voyage', 'Bandeau soin', 'Organiseur maquillage', 'Masseur compact', 'Boîte de rangement', 'Pinceau doux', 'Flacon rechargeable' ),
			5 => array( 'Distributeur d’eau', 'Jouet interactif', 'Harnais ajustable', 'Brosse anti-poils', 'Gamelle pliable', 'Tapis de fouille', 'Porte-sac pratique', 'Coussin lavable', 'Laisse réfléchissante', 'Fontaine compacte' ),
			6 => array( 'Balance de précision', 'Boîte hermétique', 'Tapis de cuisson', 'Presse-agrumes manuel', 'Support à épices', 'Moule réutilisable', 'Minuteur aimanté', 'Coupe-légumes compact', 'Bocal doseur', 'Égouttoir pliable' ),
			7 => array( 'Organiseur de câbles', 'Support d’ordinateur', 'Lampe de lecture', 'Repose-poignet', 'Porte-documents', 'Boîte à tiroirs', 'Tableau effaçable', 'Support de casque', 'Range-stylos', 'Porte-étiquettes' ),
			8 => array( 'Lampe de camping', 'Sac étanche', 'Gourde filtrante', 'Hamac compact', 'Boussole de randonnée', 'Pochette solaire', 'Couverture de pique-nique', 'Mousqueton sécurisé', 'Poncho léger', 'Kit de réparation' ),
		);
		$categories = array();
		foreach ( $category_names as $id => $category ) {
			$categories[] = array_merge( array( 'id' => $id ), $category );
		}

		$products = array();
		$sources = array( 'amazon', 'alibaba', 'aliexpress' );
		$statuses = array( 'shortlist', 'pending', 'draft', 'published', 'rejected' );
		for ( $id = 1; $id <= 80; $id++ ) {
			$category_id = ( ( $id - 1 ) % 8 ) + 1;
			$purchase_price = round( 7 + ( self::number( 'purchase-' . $id, 0, 4800 ) / 100 ), 2 );
			$target_margin = self::number( 'margin-' . $id, 25, 58 ) / 100;
			$sale_price = round( $purchase_price / ( 1 - $target_margin ), 2 );
			$demand = self::number( 'demand-' . $id, 25, 98 );
			$trend = self::number( 'trend-' . $id, 20, 97 );
			$competition = self::number( 'competition-' . $id, 12, 88 );
			$created = ( new \DateTimeImmutable( 'today', new \DateTimeZone( 'UTC' ) ) )->modify( '-' . self::number( 'created-' . $id, 0, 179 ) . ' days' );
			$products[] = array(
				'id' => $id,
				'titre' => $names[ $category_id ][ (int) floor( ( $id - 1 ) / 8 ) ],
				'category_id' => $category_id,
				'source' => $sources[ ( $id - 1 ) % count( $sources ) ],
				'prix_achat' => $purchase_price,
				'prix_vente' => $sale_price,
				'marge_pct' => round( ( ( $sale_price - $purchase_price ) / $sale_price ) * 100, 2 ),
				'score_demande' => $demand,
				'score_tendance' => $trend,
				'score_concurrence' => $competition,
				'score_global' => (int) round( ( $demand * 0.35 ) + ( $trend * 0.35 ) + ( ( 100 - $competition ) * 0.3 ) ),
				'statut' => $statuses[ ( $id - 1 ) % count( $statuses ) ],
				'delai_livraison_jours' => self::number( 'delivery-' . $id, 4, 28 ),
				'note_moyenne' => self::number( 'rating-' . $id, 35, 50 ) / 10,
				'nb_avis' => self::number( 'reviews-' . $id, 8, 980 ),
				'created_at' => $created->format( 'Y-m-d H:i:s' ),
			);
		}

		$today = new \DateTimeImmutable( 'today', new \DateTimeZone( 'UTC' ) );
		$snapshots = array();
		$category_trends = array( 1 => 0.08, 2 => -0.04, 3 => 0.03, 4 => -0.07, 5 => 0.05, 6 => 0.02, 7 => -0.02, 8 => 0.09 );
		foreach ( $category_names as $category_id => $category ) {
			$sample_product = $products[ $category_id - 1 ];
			for ( $day = 89; $day >= 0; $day-- ) {
				$progress = ( 89 - $day ) / 89;
				$seasonality = sin( ( $progress * 2 * M_PI ) + $category_id ) * 0.025;
				$noise = ( self::number( 'price-' . $category_id . '-' . $day, 0, 1000 ) / 10000 ) - 0.05;
				$price = round( $sample_product['prix_vente'] * ( 1 + ( $category_trends[ $category_id ] * $progress ) + $seasonality + $noise ), 2 );
				$snapshots[] = array(
					'id' => count( $snapshots ) + 1,
					'product_id' => $sample_product['id'],
					'category_id' => $category_id,
					'prix' => max( 0.01, $price ),
					'date' => $today->modify( '-' . $day . ' days' )->format( 'Y-m-d' ),
				);
			}
		}

		$platforms = array( 'x', 'facebook', 'instagram' );
		$signals = array();
		for ( $id = 1; $id <= 48; $id++ ) {
			$signals[] = array(
				'id' => $id,
				'produit_detecte' => $products[ ( $id * 7 ) % count( $products ) ]['titre'],
				'plateforme' => $platforms[ ( $id - 1 ) % count( $platforms ) ],
				'nb_mentions' => self::number( 'mentions-' . $id, 12, 2400 ),
				'sentiment' => round( ( self::number( 'sentiment-' . $id, 0, 200 ) - 100 ) / 100, 2 ),
				'date' => $today->modify( '-' . self::number( 'signal-date-' . $id, 0, 89 ) . ' days' )->format( 'Y-m-d' ),
			);
		}

		$runs = array();
		for ( $id = 1; $id <= 20; $id++ ) {
			$start = $today->modify( '-' . ( $id * 3 ) . ' days' )->setTime( 7 + ( $id % 10 ), 15 );
			$runs[] = array(
				'id' => $id,
				'workflow' => array( 'social_scan', 'product_scoring', 'catalog_sync' )[ ( $id - 1 ) % 3 ],
				'debut' => $start->format( 'Y-m-d H:i:s' ),
				'fin' => $start->modify( '+' . self::number( 'run-duration-' . $id, 1, 12 ) . ' minutes' )->format( 'Y-m-d H:i:s' ),
				'statut' => array( 'completed', 'completed', 'completed', 'failed' )[ ( $id - 1 ) % 4 ],
				'produits_trouves' => self::number( 'run-found-' . $id, 8, 64 ),
				'produits_ajoutes' => self::number( 'run-added-' . $id, 1, 18 ),
				'score_moyen' => self::number( 'run-score-' . $id, 42, 91 ),
				'erreurs' => ( 0 === $id % 4 ) ? 1 : 0,
			);
		}

		$run_stages = array();
		foreach ( $runs as $run ) {
			$run_start = new \DateTimeImmutable( $run['debut'], new \DateTimeZone( 'UTC' ) );
			$total_seconds = max( 60, strtotime( $run['fin'] . ' UTC' ) - strtotime( $run['debut'] . ' UTC' ) );
			$durations = array(
				max( 10, (int) floor( $total_seconds * 0.45 ) ),
				max( 10, (int) floor( $total_seconds * 0.35 ) ),
				max( 10, $total_seconds - (int) floor( $total_seconds * 0.80 ) ),
			);
			foreach ( array( 'scraper', 'scoring', 'catalogue' ) as $index => $stage_name ) {
				$stage_start = $run_start;
				for ( $previous = 0; $previous < $index; $previous++ ) {
					$stage_start = $stage_start->modify( '+' . $durations[ $previous ] . ' seconds' );
				}
				$status = 'completed';
				$error = '';
				if ( 'failed' === $run['statut'] && 1 === $index ) {
					$status = 'failed';
					$error = 'Source temporairement indisponible.';
				} elseif ( 'failed' === $run['statut'] && 2 === $index ) {
					$status = 'skipped';
					$durations[ $index ] = 0;
				}
				$stage_end = $stage_start->modify( '+' . $durations[ $index ] . ' seconds' );
				$run_stages[] = array(
					'id' => count( $run_stages ) + 1,
					'run_id' => $run['id'],
					'stage' => $stage_name,
					'start_at' => $stage_start->format( 'Y-m-d H:i:s' ),
					'end_at' => $stage_end->format( 'Y-m-d H:i:s' ),
					'duration_seconds' => $durations[ $index ],
					'status' => $status,
					'error_summary' => $error,
				);
			}
		}

		$orders = array();
		for ( $id = 1; $id <= 24; $id++ ) {
			$orders[] = array(
				'id' => $id,
				'product_id' => ( ( $id * 11 ) % 80 ) + 1,
				'statut' => array( 'processing', 'shipped', 'delivered', 'pending' )[ ( $id - 1 ) % 4 ],
				'numero_suivi' => 'DSA-DEMO-' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT ),
				'fournisseur' => $sources[ ( $id - 1 ) % count( $sources ) ],
				'date' => $today->modify( '-' . self::number( 'order-date-' . $id, 0, 89 ) . ' days' )->format( 'Y-m-d' ),
			);
		}

		$reviews = array();
		for ( $id = 1; $id <= 30; $id++ ) {
			$reviews[] = array(
				'id' => $id,
				'product_id' => ( ( $id * 13 ) % 80 ) + 1,
				'note' => self::number( 'review-rating-' . $id, 3, 5 ),
				'commentaire' => array( 'Pratique au quotidien.', 'Conforme à la description.', 'Bon rapport qualité-prix.', 'Livraison correcte et produit utile.' )[ ( $id - 1 ) % 4 ],
				'date' => $today->modify( '-' . self::number( 'review-date-' . $id, 0, 89 ) . ' days' )->format( 'Y-m-d' ),
			);
		}

		$logs = array();
		for ( $id = 1; $id <= 40; $id++ ) {
			$logs[] = array(
				'id' => $id,
				'niveau' => array( 'info', 'info', 'warning', 'error' )[ ( $id - 1 ) % 4 ],
				'module' => array( 'scraper', 'scoring', 'catalogue', 'notification' )[ ( $id - 1 ) % 4 ],
				'message' => array( 'Collecte terminée.', 'Produit évalué.', 'Doublon ignoré.', 'Source temporairement indisponible.' )[ ( $id - 1 ) % 4 ],
				'run_id' => ( ( $id - 1 ) % count( $runs ) ) + 1,
				'date' => $today->modify( '-' . self::number( 'log-date-' . $id, 0, 89 ) . ' days' )->format( 'Y-m-d H:i:s' ),
			);
		}

		return array(
			'categories' => $categories,
			'products' => $products,
			'price_snapshots' => $snapshots,
			'trend_signals' => $signals,
			'runs' => $runs,
			'run_stages' => $run_stages,
			'orders' => $orders,
			'reviews' => $reviews,
			'logs' => $logs,
		);
	}

	private static function number( string $key, int $minimum, int $maximum ): int {
		$value = hexdec( substr( hash( 'sha256', 'dsa-demo-seed-v1:' . $key ), 0, 8 ) );
		return $minimum + ( (int) $value % ( $maximum - $minimum + 1 ) );
	}
}
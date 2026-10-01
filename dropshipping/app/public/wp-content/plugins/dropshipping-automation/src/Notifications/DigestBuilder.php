<?php

namespace DSA\Notifications;

defined( 'ABSPATH' ) || exit;

final class DigestBuilder {
	public function build( array $run ) {
		$counts = isset( $run['counts'] ) && is_array( $run['counts'] ) ? $run['counts'] : array();
		$stages = isset( $run['stages'] ) && is_array( $run['stages'] ) ? $run['stages'] : array();
		$errors = array();
		foreach ( $stages as $stage ) {
			if ( ! is_array( $stage ) || ! in_array( $stage['status'] ?? '', array( 'failed', 'error' ), true ) ) {
				continue;
			}
			$errors[] = array(
				'stage' => sanitize_key( $stage['stage'] ?? 'workflow' ),
				'label' => $this->stage_label( $stage['stage'] ?? '' ),
			);
		}

		return array(
			'id'          => sanitize_text_field( (string) ( $run['id'] ?? '' ) ),
			'workflow'    => $this->workflow_label( $run['workflow'] ?? '' ),
			'status'      => in_array( $run['status'] ?? '', array( 'completed', 'failed', 'simulated' ), true ) ? $run['status'] : 'completed',
			'started_at'  => sanitize_text_field( (string) ( $run['started_at'] ?? $run['debut'] ?? '' ) ),
			'finished_at' => sanitize_text_field( (string) ( $run['finished_at'] ?? $run['fin'] ?? '' ) ),
			'counts'      => array(
				'found'         => absint( $counts['found'] ?? $run['produits_trouves'] ?? 0 ),
				'scored'        => absint( $counts['scored'] ?? 0 ),
				'created'       => absint( $counts['created'] ?? $run['produits_ajoutes'] ?? 0 ),
				'duplicates'    => absint( $counts['duplicates'] ?? 0 ),
				'errors'        => absint( $counts['errors'] ?? $run['erreurs'] ?? count( $errors ) ),
				'average_score' => max( 0, min( 100, (float) ( $counts['average_score'] ?? $run['score_moyen'] ?? 0 ) ) ),
			),
			'errors'      => $errors,
		);
	}

	public function render_html( array $digest ) {
		$digest = $this->build( $digest );
		$labels = array(
			'found' => __( 'Produits trouvés', 'dsa' ),
			'scored' => __( 'Produits évalués', 'dsa' ),
			'created' => __( 'Ajoutés pour validation', 'dsa' ),
			'duplicates' => __( 'Doublons ignorés', 'dsa' ),
			'errors' => __( 'Erreurs', 'dsa' ),
			'average_score' => __( 'Score moyen', 'dsa' ),
		);
		$rows = '';
		foreach ( $labels as $key => $label ) {
			$rows .= '<tr><th align="left" style="padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#3f4a56;font-weight:600">' . esc_html( $label ) . '</th><td align="right" style="padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#14212b">' . esc_html( (string) $digest['counts'][ $key ] ) . '</td></tr>';
		}
		$error_html = '';
		if ( $digest['errors'] ) {
			$error_html = '<p style="margin:18px 0 0;color:#8a342c;font-size:14px">' . esc_html( __( 'Étapes en erreur : ', 'dsa' ) ) . esc_html( implode( ', ', array_column( $digest['errors'], 'label' ) ) ) . '</p>';
		}
		return '<!doctype html><html><body style="margin:0;padding:24px;background:#f4f6f7;font-family:Arial,Helvetica,sans-serif;color:#14212b"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-collapse:collapse"><tr><td style="padding:24px 26px;background:#183b3b;color:#ffffff"><p style="margin:0 0 8px;font-size:12px;letter-spacing:1px;text-transform:uppercase">' . esc_html( __( 'Dropshipping Automation', 'dsa' ) ) . '</p><h1 style="margin:0;font-size:22px;line-height:1.3">' . esc_html( __( 'Résumé d’exécution', 'dsa' ) ) . '</h1></td></tr><tr><td style="padding:22px 26px"><p style="margin:0 0 6px;font-size:15px"><strong>' . esc_html( __( 'Workflow : ', 'dsa' ) ) . '</strong>' . esc_html( $digest['workflow'] ) . '</p><p style="margin:0;color:#5a6570;font-size:13px">' . esc_html( __( 'Exécution : ', 'dsa' ) ) . esc_html( $digest['id'] ) . '</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px;border-collapse:collapse">' . $rows . '</table>' . $error_html . '</td></tr><tr><td style="padding:16px 26px;background:#f4f6f7;color:#66717a;font-size:12px">' . esc_html( __( 'Aucune donnée client ni information sensible n’est incluse dans ce message.', 'dsa' ) ) . '</td></tr></table></body></html>';
	}

	private function workflow_label( $workflow ) {
		$labels = array( 'social_scan' => __( 'Collecte sociale', 'dsa' ), 'product_scoring' => __( 'Évaluation produits', 'dsa' ), 'catalog_sync' => __( 'Synchronisation catalogue', 'dsa' ), 'products' => __( 'Produits', 'dsa' ), 'trends' => __( 'Tendances', 'dsa' ) );
		return $labels[ $workflow ] ?? __( 'Workflow', 'dsa' );
	}

	private function stage_label( $stage ) {
		$labels = array( 'scraping' => __( 'Collecte sociale', 'dsa' ), 'scoring' => __( 'Évaluation', 'dsa' ), 'catalog' => __( 'Catalogue', 'dsa' ), 'notification' => __( 'Notification', 'dsa' ) );
		return $labels[ $stage ] ?? __( 'Étape du workflow', 'dsa' );
	}
}
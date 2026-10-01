<?php

defined( 'ABSPATH' ) || exit;

$dashboard_is_admin = is_admin();
$dashboard_logs_url = $dashboard_is_admin
	? admin_url( 'admin.php?page=dsa-logs' )
	: add_query_arg( 'dsa_page', 'dsa-logs', home_url( '/' ) );

?>
<div class="dsa-overview" data-dsa-dashboard aria-busy="true">
	<div class="dsa-overview-toolbar">
		<label class="dsa-period-control" for="dsa-dashboard-period"><?php esc_html_e( 'Période', 'dsa' ); ?>
			<select id="dsa-dashboard-period" data-dashboard-period>
				<option value="7"><?php esc_html_e( '7 jours', 'dsa' ); ?></option>
				<option value="30" selected><?php esc_html_e( '30 jours', 'dsa' ); ?></option>
				<option value="90"><?php esc_html_e( '90 jours', 'dsa' ); ?></option>
			</select>
		</label>
		<button class="dsa-button dsa-run-button" type="button" data-dashboard-run><span class="dashicons dashicons-controls-play" aria-hidden="true"></span><?php esc_html_e( 'Exécuter maintenant', 'dsa' ); ?></button>
	</div>
	<p class="screen-reader-text" data-dashboard-live role="status" aria-live="polite"><?php esc_html_e( 'Chargement des indicateurs.', 'dsa' ); ?></p>

	<section class="dsa-kpi-grid" data-dashboard-kpis aria-label="<?php esc_attr_e( 'Indicateurs clés', 'dsa' ); ?>">
		<?php for ( $index = 0; $index < 6; ++$index ) : ?>
			<div class="dsa-kpi-skeleton" aria-hidden="true"><span></span><strong></strong><small></small></div>
		<?php endfor; ?>
	</section>

	<section class="dsa-overview-section" aria-labelledby="dsa-pipeline-title">
		<div class="dsa-section-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'ORCHESTRATION', 'dsa' ); ?></p><h2 id="dsa-pipeline-title"><?php esc_html_e( 'État du pipeline', 'dsa' ); ?></h2></div></div>
		<ol class="dsa-pipeline" data-dashboard-pipeline aria-label="<?php esc_attr_e( 'Étapes du pipeline', 'dsa' ); ?>"></ol>
	</section>

	<div class="dsa-overview-columns">
		<section class="dsa-overview-section dsa-chart-section" aria-labelledby="dsa-chart-title">
			<div class="dsa-section-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'ACTIVITÉ', 'dsa' ); ?></p><h2 id="dsa-chart-title"><?php esc_html_e( 'Signaux détectés', 'dsa' ); ?></h2></div></div>
			<figure class="dsa-signal-chart">
				<div class="dsa-chart-bars" data-dashboard-chart role="img" aria-label="<?php esc_attr_e( 'Graphique de répartition des signaux détectés', 'dsa' ); ?>"></div>
				<figcaption><?php esc_html_e( 'Répartition sur la période sélectionnée', 'dsa' ); ?></figcaption>
			</figure>
			<table class="screen-reader-text" data-dashboard-chart-table>
				<caption><?php esc_html_e( 'Données du graphique des signaux détectés', 'dsa' ); ?></caption>
				<thead><tr><th><?php esc_html_e( 'Période', 'dsa' ); ?></th><th><?php esc_html_e( 'Signaux', 'dsa' ); ?></th></tr></thead>
				<tbody></tbody>
			</table>
		</section>
		<section class="dsa-overview-section" aria-labelledby="dsa-scheduled-title">
			<div class="dsa-section-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'À VENIR', 'dsa' ); ?></p><h2 id="dsa-scheduled-title"><?php esc_html_e( 'Prochaines exécutions', 'dsa' ); ?></h2></div></div>
			<ul class="dsa-scheduled-list" data-dashboard-scheduled></ul>
		</section>
	</div>

	<div class="dsa-overview-columns dsa-overview-lower">
		<section class="dsa-overview-section dsa-runs-section" aria-labelledby="dsa-runs-title">
			<div class="dsa-section-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'HISTORIQUE', 'dsa' ); ?></p><h2 id="dsa-runs-title"><?php esc_html_e( 'Dernières exécutions', 'dsa' ); ?></h2></div><a class="dsa-section-link" href="<?php echo esc_url( $dashboard_logs_url ); ?>"><?php esc_html_e( 'Tout voir', 'dsa' ); ?></a></div>
			<div class="dsa-table-wrap"><table class="dsa-overview-table">
				<thead><tr><th><?php esc_html_e( 'Workflow', 'dsa' ); ?></th><th><?php esc_html_e( 'Début', 'dsa' ); ?></th><th><?php esc_html_e( 'Durée', 'dsa' ); ?></th><th><?php esc_html_e( 'Statut', 'dsa' ); ?></th><th><?php esc_html_e( 'Trouvés / ajoutés', 'dsa' ); ?></th></tr></thead>
				<tbody data-dashboard-runs><tr><td colspan="5"><span class="dsa-inline-skeleton" aria-hidden="true"></span></td></tr></tbody>
			</table></div>
		</section>
		<section class="dsa-overview-section" aria-labelledby="dsa-alerts-title">
			<div class="dsa-section-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'À TRAITER', 'dsa' ); ?></p><h2 id="dsa-alerts-title"><?php esc_html_e( 'Alertes', 'dsa' ); ?></h2></div></div>
			<ul class="dsa-alert-list" data-dashboard-alerts></ul>
		</section>
	</div>
</div>
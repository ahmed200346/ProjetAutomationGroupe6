<?php

defined( 'ABSPATH' ) || exit;

$current_timezone = wp_timezone_string() ?: 'UTC';
$timezones = \DateTimeZone::listIdentifiers();
if ( ! in_array( $current_timezone, $timezones, true ) ) {
	$timezones[] = $current_timezone;
}
if ( ! in_array( 'UTC', $timezones, true ) ) {
	$timezones[] = 'UTC';
}
sort( $timezones );
$weekday_labels = array(
	1 => __( 'L', 'dsa' ),
	2 => __( 'M', 'dsa' ),
	3 => __( 'M', 'dsa' ),
	4 => __( 'J', 'dsa' ),
	5 => __( 'V', 'dsa' ),
	6 => __( 'S', 'dsa' ),
	0 => __( 'D', 'dsa' ),
);
$enabled_models = array();
$n8n_product_settings = \DSA\Integrations\N8nWorkflowSettings::get();
$provider_settings = new \DSA\Storage\ProviderSettings();
foreach ( array( 'gemini', 'ollama', 'openrouter', 'nvidia' ) as $provider ) {
	$provider_config = $provider_settings->get( $provider );
	foreach ( $provider_config['models'] as $model ) {
		if ( in_array( $model['id'], $provider_config['enabled'], true ) ) {
			$enabled_models[] = array( 'id' => $model['id'], 'name' => $model['name'] ?? $model['id'], 'provider' => $provider );
		}
	}
}
?>
<div class="dsa-planner" data-workflow-planner>
	<div class="dsa-planner-heading">
		<div>
			<p class="dsa-eyebrow"><?php esc_html_e( 'ORCHESTRATION', 'dsa' ); ?></p>
			<h2><?php esc_html_e( 'Planificateur', 'dsa' ); ?></h2>
			<p><?php esc_html_e( 'Chaque workflow conserve son propre calendrier et son historique.', 'dsa' ); ?></p>
		</div>
		<div class="dsa-planner-controls">
			<label class="dsa-switch">
				<input type="checkbox" data-schedule-enabled>
				<span class="dsa-switch-track" aria-hidden="true"></span>
				<span data-enabled-label><?php esc_html_e( 'Désactivé', 'dsa' ); ?></span>
			</label>
			<button class="dsa-button" type="button" data-run-now><span class="dashicons dashicons-controls-play" aria-hidden="true"></span><?php esc_html_e( 'Exécuter maintenant', 'dsa' ); ?></button>
		</div>
	</div>
	<p class="dsa-planner-notice"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'Produits : le lancement est transmis à n8n lorsque le webhook est configuré et explicitement activé. Tendances reste simulé. Confirmez les autorisations marketplace avant toute collecte réelle; aucun produit WooCommerce n’est créé par cette intégration.', 'dsa' ); ?></p>
	<section class="dsa-n8n-product-inputs" aria-labelledby="dsa-n8n-product-title">
		<div><p class="dsa-eyebrow"><?php esc_html_e( 'ENTRÉE DU WORKFLOW PRODUITS', 'dsa' ); ?></p><h3 id="dsa-n8n-product-title"><?php esc_html_e( 'Recherche marketplace', 'dsa' ); ?></h3><p><?php esc_html_e( 'La cadence reste pilotée ici par WordPress. Le lancement Produits transmet ces paramètres au webhook n8n.', 'dsa' ); ?></p></div>
		<div class="dsa-n8n-product-fields">
			<label class="dsa-field"><span><?php esc_html_e( 'Recherche produit', 'dsa' ); ?></span><input type="text" maxlength="120" value="<?php echo esc_attr( $n8n_product_settings['query'] ); ?>" data-n8n-query placeholder="<?php esc_attr_e( 'Ex. écouteurs sans fil', 'dsa' ); ?>"></label>
			<label class="dsa-field"><span><?php esc_html_e( 'Marketplace', 'dsa' ); ?></span><select data-n8n-marketplace><?php foreach ( array( 'aliexpress' => 'AliExpress', 'amazon' => 'Amazon' ) as $marketplace => $label ) : ?><option value="<?php echo esc_attr( $marketplace ); ?>"<?php selected( $n8n_product_settings['marketplace'], $marketplace ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<button class="dsa-button dsa-button-secondary" type="button" data-save-n8n-product-input><?php esc_html_e( 'Enregistrer la recherche', 'dsa' ); ?></button>
		</div>
		<p class="dsa-inline-note" data-n8n-product-status role="status"><?php esc_html_e( 'La clé et l’URL du webhook sont configurées côté serveur; aucune clé n’est affichée ici.', 'dsa' ); ?></p>
	</section>

	<section class="dsa-workflow-cards" aria-label="<?php esc_attr_e( 'Workflows de démonstration', 'dsa' ); ?>">
		<?php foreach ( array( 'products' => __( 'Scraping produits', 'dsa' ), 'trends' => __( 'Scraping tendances', 'dsa' ) ) as $workflow_id => $workflow_label ) : ?>
			<article class="dsa-workflow-card" data-workflow-card="<?php echo esc_attr( $workflow_id ); ?>">
				<header class="dsa-workflow-card-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'WORKFLOW', 'dsa' ); ?></p><h3><?php echo esc_html( $workflow_label ); ?></h3></div><span class="dsa-badge" data-card-schedule-state><?php esc_html_e( 'Chargement…', 'dsa' ); ?></span></header>
				<p class="dsa-workflow-card-summary" data-card-schedule-summary><?php esc_html_e( 'Chargement du calendrier…', 'dsa' ); ?></p>
				<dl class="dsa-workflow-card-parameters"><div><dt><?php esc_html_e( 'Plateformes', 'dsa' ); ?></dt><dd data-card-platforms><?php esc_html_e( 'À configurer', 'dsa' ); ?></dd></div><div><dt><?php esc_html_e( 'Catégories', 'dsa' ); ?></dt><dd data-card-categories><?php esc_html_e( 'À configurer', 'dsa' ); ?></dd></div><div><dt><?php esc_html_e( 'Fournisseurs', 'dsa' ); ?></dt><dd data-card-suppliers><?php esc_html_e( 'À configurer', 'dsa' ); ?></dd></div><div><dt><?php esc_html_e( 'Limite · score minimum', 'dsa' ); ?></dt><dd data-card-scoring><?php esc_html_e( '10 produits · 60', 'dsa' ); ?></dd></div><div><dt><?php esc_html_e( 'Modèle IA', 'dsa' ); ?></dt><dd data-card-model><?php esc_html_e( 'Aucun modèle', 'dsa' ); ?></dd></div></dl>
				<p class="dsa-workflow-card-next"><span><?php esc_html_e( 'Prochaine exécution', 'dsa' ); ?></span><strong data-card-next><?php esc_html_e( 'Non planifiée', 'dsa' ); ?></strong></p>
				<div class="dsa-workflow-card-history"><div class="dsa-workflow-card-history-heading"><h4><?php esc_html_e( '10 dernières exécutions', 'dsa' ); ?></h4><span data-card-history-count>0</span></div><ol data-card-history="<?php echo esc_attr( $workflow_id ); ?>"><li><?php esc_html_e( 'Aucune exécution enregistrée.', 'dsa' ); ?></li></ol></div>
				<footer class="dsa-workflow-card-actions"><button class="dsa-button dsa-button-quiet dsa-workflow-tab<?php echo 'products' === $workflow_id ? ' is-active' : ''; ?>" type="button" data-workflow="<?php echo esc_attr( $workflow_id ); ?>" aria-pressed="<?php echo 'products' === $workflow_id ? 'true' : 'false'; ?>"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e( 'Configurer', 'dsa' ); ?></button><button class="dsa-button dsa-button-primary" type="button" data-workflow-run="<?php echo esc_attr( $workflow_id ); ?>"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span><?php esc_html_e( 'Exécuter maintenant', 'dsa' ); ?></button></footer>
			</article>
		<?php endforeach; ?>
	</section>
	<section class="dsa-workflow-parameters" aria-label="<?php esc_attr_e( 'Paramètres du workflow', 'dsa' ); ?>" data-workflow-parameters>
		<div class="dsa-workflow-parameter-group"><p class="dsa-eyebrow"><?php esc_html_e( 'SOURCES', 'dsa' ); ?></p><fieldset><legend><?php esc_html_e( 'Plateformes à surveiller', 'dsa' ); ?></legend><?php foreach ( array( 'x' => 'X', 'facebook' => 'Facebook', 'instagram' => 'Instagram' ) as $platform => $label ) : ?><label><input type="checkbox" value="<?php echo esc_attr( $platform ); ?>" data-workflow-platform><span><?php echo esc_html( $label ); ?></span></label><?php endforeach; ?></fieldset><fieldset><legend><?php esc_html_e( 'Catégories cibles', 'dsa' ); ?></legend><?php foreach ( array( 1 => __( 'Gadgets maison', 'dsa' ), 2 => __( 'Accessoires téléphone', 'dsa' ), 3 => __( 'Fitness & bien-être', 'dsa' ), 4 => __( 'Beauté & soins', 'dsa' ), 5 => __( 'Animaux', 'dsa' ), 6 => __( 'Cuisine pratique', 'dsa' ), 7 => __( 'Rangement & bureau', 'dsa' ), 8 => __( 'Loisirs plein air', 'dsa' ) ) as $category_id => $label ) : ?><label><input type="checkbox" value="<?php echo esc_attr( $category_id ); ?>" data-workflow-category><span><?php echo esc_html( $label ); ?></span></label><?php endforeach; ?></fieldset></div>
		<div class="dsa-workflow-parameter-group"><p class="dsa-eyebrow"><?php esc_html_e( 'SCORING', 'dsa' ); ?></p><fieldset><legend><?php esc_html_e( 'Fournisseurs', 'dsa' ); ?></legend><?php foreach ( array( 'amazon' => 'Amazon', 'alibaba' => 'Alibaba', 'aliexpress' => 'AliExpress' ) as $supplier => $label ) : ?><label><input type="checkbox" value="<?php echo esc_attr( $supplier ); ?>" data-workflow-supplier><span><?php echo esc_html( $label ); ?></span></label><?php endforeach; ?></fieldset><label class="dsa-field"><span><?php esc_html_e( 'Produits par exécution', 'dsa' ); ?></span><input type="number" min="1" max="100" value="10" data-workflow-limit></label><label class="dsa-field"><span><?php esc_html_e( 'Score minimum', 'dsa' ); ?></span><input type="number" min="0" max="100" value="60" data-workflow-score></label><label class="dsa-field"><span><?php esc_html_e( 'Modèle IA activé', 'dsa' ); ?></span><select data-workflow-model><option value=""><?php esc_html_e( 'Aucun modèle', 'dsa' ); ?></option><?php foreach ( $enabled_models as $model ) : ?><option value="<?php echo esc_attr( $model['id'] ); ?>"><?php echo esc_html( $model['name'] . ' · ' . $model['provider'] ); ?></option><?php endforeach; ?></select></label><?php if ( ! $enabled_models ) : ?><p class="dsa-inline-note"><?php esc_html_e( 'Activez un modèle dans Réglages pour le sélectionner ici.', 'dsa' ); ?></p><?php endif; ?></div>
		<div class="dsa-workflow-parameter-actions"><p class="dsa-planner-error" role="alert" data-workflow-error hidden></p><button class="dsa-button" type="button" data-save-workflow><?php esc_html_e( 'Enregistrer les paramètres', 'dsa' ); ?></button></div>
	</section>

	<div class="dsa-planner-layout">
		<section class="dsa-planner-editor" aria-labelledby="dsa-planner-editor-title">
			<div class="dsa-planner-section-heading">
				<div><p class="dsa-eyebrow"><?php esc_html_e( 'CONFIGURATION', 'dsa' ); ?></p><h3 id="dsa-planner-editor-title" data-workflow-title><?php esc_html_e( 'Scraping produits', 'dsa' ); ?></h3></div>
				<span class="dsa-badge" data-schedule-badge><?php esc_html_e( 'Manuel', 'dsa' ); ?></span>
			</div>

			<div class="dsa-planner-modes" role="tablist" aria-label="<?php esc_attr_e( 'Mode de planification', 'dsa' ); ?>">
				<?php
				$modes = array(
					'manual'   => __( 'Manuel', 'dsa' ),
					'interval' => __( 'Intervalle', 'dsa' ),
					'daily'    => __( 'Quotidien', 'dsa' ),
					'weekly'   => __( 'Hebdomadaire', 'dsa' ),
					'monthly'  => __( 'Mensuel', 'dsa' ),
					'once'     => __( 'Date unique', 'dsa' ),
					'advanced' => __( 'Avancé', 'dsa' ),
				);
				foreach ( $modes as $mode => $label ) :
					?>
					<button class="dsa-mode-tab" type="button" role="tab" aria-selected="<?php echo 'manual' === $mode ? 'true' : 'false'; ?>" data-mode="<?php echo esc_attr( $mode ); ?>"><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>

			<div class="dsa-mode-panel" data-mode-panel="manual">
				<p class="dsa-mode-note"><?php esc_html_e( 'Aucune exécution planifiée. Le workflow démarre uniquement avec « Exécuter maintenant ».', 'dsa' ); ?></p>
			</div>
			<div class="dsa-mode-panel" data-mode-panel="interval" hidden>
				<label class="dsa-field"><span><?php esc_html_e( 'Exécuter toutes les', 'dsa' ); ?></span><span class="dsa-inline-fields"><input type="number" min="1" max="59" value="1" data-field="amount"><select data-field="unit"><option value="minutes"><?php esc_html_e( 'minutes', 'dsa' ); ?></option><option value="hours"><?php esc_html_e( 'heures', 'dsa' ); ?></option><option value="days"><?php esc_html_e( 'jours', 'dsa' ); ?></option></select></span></label>
			</div>
			<div class="dsa-mode-panel" data-mode-panel="daily" hidden>
				<label class="dsa-field"><span><?php esc_html_e( 'Heure locale', 'dsa' ); ?></span><input type="time" value="02:00" data-field="time"></label>
			</div>
			<div class="dsa-mode-panel" data-mode-panel="weekly" hidden>
				<fieldset class="dsa-field dsa-weekday-field"><legend><?php esc_html_e( 'Jours de la semaine', 'dsa' ); ?></legend>
					<div class="dsa-weekday-list">
						<?php foreach ( $weekday_labels as $day => $label ) : ?>
							<label class="dsa-weekday"><input type="checkbox" value="<?php echo esc_attr( $day ); ?>" data-weekday><span><?php echo esc_html( $label ); ?></span></label>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<label class="dsa-field"><span><?php esc_html_e( 'Heure locale', 'dsa' ); ?></span><input type="time" value="09:00" data-field="time"></label>
			</div>
			<div class="dsa-mode-panel" data-mode-panel="monthly" hidden>
				<div class="dsa-choice-row">
					<label><input type="radio" name="dsa-monthly-type" value="day" checked data-monthly-type> <?php esc_html_e( 'Jour du mois', 'dsa' ); ?></label>
					<label><input type="radio" name="dsa-monthly-type" value="weekday" data-monthly-type> <?php esc_html_e( 'N-ième jour de semaine', 'dsa' ); ?></label>
				</div>
				<div class="dsa-inline-fields" data-monthly-day-fields><label class="dsa-field"><span><?php esc_html_e( 'Jour', 'dsa' ); ?></span><input type="number" min="1" max="31" value="1" data-field="day"></label><label class="dsa-check-field"><input type="checkbox" data-field="last_day"><span><?php esc_html_e( 'Dernier jour du mois', 'dsa' ); ?></span></label></div>
				<div class="dsa-inline-fields" data-monthly-weekday-fields hidden><label class="dsa-field"><span><?php esc_html_e( 'Rang', 'dsa' ); ?></span><select data-field="ordinal"><option value="1"><?php esc_html_e( 'Premier', 'dsa' ); ?></option><option value="2"><?php esc_html_e( 'Deuxième', 'dsa' ); ?></option><option value="3"><?php esc_html_e( 'Troisième', 'dsa' ); ?></option><option value="4"><?php esc_html_e( 'Quatrième', 'dsa' ); ?></option><option value="5"><?php esc_html_e( 'Cinquième', 'dsa' ); ?></option></select></label><label class="dsa-field"><span><?php esc_html_e( 'Jour', 'dsa' ); ?></span><select data-field="weekday"><option value="1"><?php esc_html_e( 'Lundi', 'dsa' ); ?></option><option value="2"><?php esc_html_e( 'Mardi', 'dsa' ); ?></option><option value="3"><?php esc_html_e( 'Mercredi', 'dsa' ); ?></option><option value="4"><?php esc_html_e( 'Jeudi', 'dsa' ); ?></option><option value="5"><?php esc_html_e( 'Vendredi', 'dsa' ); ?></option><option value="6"><?php esc_html_e( 'Samedi', 'dsa' ); ?></option><option value="0"><?php esc_html_e( 'Dimanche', 'dsa' ); ?></option></select></label></div>
				<label class="dsa-field"><span><?php esc_html_e( 'Heure locale', 'dsa' ); ?></span><input type="time" value="03:00" data-field="time"></label>
			</div>
			<div class="dsa-mode-panel" data-mode-panel="once" hidden>
				<label class="dsa-field"><span><?php esc_html_e( 'Date et heure', 'dsa' ); ?></span><input type="datetime-local" data-field="once_at"></label>
			</div>
			<div class="dsa-mode-panel" data-mode-panel="advanced" hidden>
				<label class="dsa-field"><span><?php esc_html_e( 'Expression cron à cinq champs', 'dsa' ); ?></span><input type="text" inputmode="text" placeholder="30 8 * * 1,4" autocomplete="off" data-field="cron" aria-describedby="dsa-cron-explanation"></label>
				<p class="dsa-cron-explanation" id="dsa-cron-explanation" data-cron-explanation><?php esc_html_e( 'Exemple : 30 8 * * 1,4 correspond au lundi et jeudi à 08:30.', 'dsa' ); ?></p>
			</div>

			<div class="dsa-schedule-common">
				<label class="dsa-field dsa-timezone-field"><span><?php esc_html_e( 'Fuseau horaire', 'dsa' ); ?></span><select data-field="timezone">
					<?php foreach ( $timezones as $timezone ) : ?>
						<option value="<?php echo esc_attr( $timezone ); ?>"<?php selected( $current_timezone, $timezone ); ?>><?php echo esc_html( str_replace( '_', ' ', $timezone ) ); ?></option>
					<?php endforeach; ?>
				</select></label>
				<div class="dsa-date-range">
					<label class="dsa-field"><span><?php esc_html_e( 'Début (facultatif)', 'dsa' ); ?></span><input type="datetime-local" data-field="start_at"></label>
					<label class="dsa-field"><span><?php esc_html_e( 'Fin (facultatif)', 'dsa' ); ?></span><input type="datetime-local" data-field="end_at"></label>
				</div>
			</div>

			<div class="dsa-presets" aria-label="<?php esc_attr_e( 'Presets de planification', 'dsa' ); ?>">
				<span><?php esc_html_e( 'Raccourcis', 'dsa' ); ?></span>
				<button type="button" data-preset="hourly"><?php esc_html_e( 'Toutes les heures', 'dsa' ); ?></button>
				<button type="button" data-preset="daily"><?php esc_html_e( 'Chaque jour · 02:00', 'dsa' ); ?></button>
				<button type="button" data-preset="monday"><?php esc_html_e( 'Chaque lundi · 09:00', 'dsa' ); ?></button>
				<button type="button" data-preset="month-start"><?php esc_html_e( 'Le 1er · 03:00', 'dsa' ); ?></button>
			</div>

			<div class="dsa-planner-actions">
				<p class="dsa-planner-error" role="alert" data-schedule-error hidden></p>
				<button class="dsa-button dsa-button-primary" type="button" data-save-schedule><?php esc_html_e( 'Enregistrer la planification', 'dsa' ); ?></button>
			</div>
		</section>

		<aside class="dsa-planner-preview" aria-label="<?php esc_attr_e( 'Aperçu et statut', 'dsa' ); ?>">
			<section class="dsa-preview-block">
				<p class="dsa-eyebrow"><?php esc_html_e( 'RÈGLE ACTIVE', 'dsa' ); ?></p>
				<p class="dsa-schedule-summary" data-schedule-summary><?php esc_html_e( 'Exécution uniquement à la demande.', 'dsa' ); ?></p>
				<p class="dsa-cron-expression"><span><?php esc_html_e( 'Expression interne', 'dsa' ); ?></span><code data-cron-expression>@manual</code></p>
			</section>
			<section class="dsa-preview-block">
				<div class="dsa-preview-title"><div><p class="dsa-eyebrow"><?php esc_html_e( 'APERÇU', 'dsa' ); ?></p><h3><?php esc_html_e( 'Prochaines exécutions', 'dsa' ); ?></h3></div><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span></div>
				<ol class="dsa-occurrence-list" data-occurrences><li class="is-empty"><?php esc_html_e( 'Aucune date planifiée.', 'dsa' ); ?></li></ol>
			</section>
			<section class="dsa-preview-block dsa-run-status">
				<p class="dsa-eyebrow"><?php esc_html_e( 'DERNIÈRE EXÉCUTION', 'dsa' ); ?></p>
				<p class="dsa-last-run" data-last-run><?php esc_html_e( 'Aucune exécution enregistrée.', 'dsa' ); ?></p>
				<p class="dsa-last-result" data-last-result></p>
			</section>
		</aside>
	</div>

	<section class="dsa-cron-health" data-cron-health>
		<div class="dsa-health-icon"><span class="dashicons dashicons-clock" aria-hidden="true"></span></div>
		<div class="dsa-health-copy"><p class="dsa-eyebrow"><?php esc_html_e( 'INFRASTRUCTURE', 'dsa' ); ?></p><h3><?php esc_html_e( 'Santé du cron', 'dsa' ); ?></h3><p data-cron-health-message><?php esc_html_e( 'Vérification du déclenchement…', 'dsa' ); ?></p><code data-cron-command>wp action-scheduler run --quiet</code></div>
		<span class="dsa-health-badge" data-cron-health-badge><?php esc_html_e( 'Vérification', 'dsa' ); ?></span>
	</section>

	<section class="dsa-workflow-history">
		<div class="dsa-planner-section-heading"><div><p class="dsa-eyebrow"><?php esc_html_e( 'TRAÇABILITÉ', 'dsa' ); ?></p><h3><?php esc_html_e( 'Exécutions récentes', 'dsa' ); ?></h3></div><span class="dsa-badge" data-history-count>0</span></div>
		<div class="dsa-table-wrap"><table class="dsa-table"><thead><tr><th><?php esc_html_e( 'Workflow', 'dsa' ); ?></th><th><?php esc_html_e( 'Déclenchement', 'dsa' ); ?></th><th><?php esc_html_e( 'Date', 'dsa' ); ?></th><th><?php esc_html_e( 'Résultat', 'dsa' ); ?></th></tr></thead><tbody data-history><tr><td colspan="4"><?php esc_html_e( 'Aucune exécution simulée pour le moment.', 'dsa' ); ?></td></tr></tbody></table></div>
	</section>
</div>
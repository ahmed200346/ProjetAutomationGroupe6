<?php
if ( ! defined( 'WPGETAPIDIR' ) ) {
	die( 'No direct access allowed' );
}

if ( ! class_exists( 'Updraft_Notices_1_2' ) ) {
	require_once WPGETAPIDIR . 'vendor/team-updraft/common-libs/src/updraft-notices/updraft-notices.php';
}

class WPGetAPI_Notices extends Updraft_Notices_1_2 {

	/**
	 * Initialize notice.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Notices content.
	 *
	 * @var array
	 */
	protected $notices_content = array();

	/**
	 * Returns array_merge of notices from parent and notices in $child_notice_content.
	 *
	 * @return array
	 */
	protected function populate_notices_content() {
		$parent_notice_content = parent::populate_notices_content();

		$child_notice_content = array(
			'rate_plugin' => array(
				// translators: %1s represents the plugin support link value.
				'text'                => sprintf( htmlspecialchars( __( 'Hey - We noticed WPGetAPI has working fine in your site.', 'wpgetapi' ) . ' ' . __( 'If you like us, please consider leaving a positive review to spread the word.', 'wpgetapi' ) . ' ' . __( 'Or if you have any issues or questions please leave us a support message %s.', 'wpgetapi' ) ), '<a href="https://wordpress.org/support/plugin/wpgetapi/" target="_blank">' . __( 'here', 'wpgetapi' ) . '</a>' ) . '<br>' . __( 'Thank you so much.', 'wpgetapi' ) . '<br><br>- <b>' . htmlspecialchars( __( 'Team WPGetAPI', 'wpgetapi' ) ) . '</b>',
				'image'               => 'plugin-logos/wpgetapi-sm.png',
				'button_link'         => 'https://wordpress.org/support/plugin/wpgetapi/reviews/?rate=5#new-post',
				'button_meta'         => 'review',
				'dismiss_time'        => 'wpgetapi_dismiss_review_notice',
				'supported_positions' => $this->dashboard_top,
				'validity_function'   => 'show_rate_notice',
			),
		);

		return array_merge( $parent_notice_content, $child_notice_content );
	}

	/**
	 * Call this method to setup the notices.
	 *
	 * @return void
	 */
	public function notices_init() {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized     = true;
		$this->notices_content = $this->populate_notices_content();
	}

	/**
	 * Get WPGetAPI Plugin installation timestamp.
	 *
	 * @return int WPGetAPI Plugin installation timestamp.
	 */
	public function get_wpgetapi_plugin_installed_timestamp() {
		$installed_at = @filemtime( WPGETAPIDIR . 'index.php' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- ignore warning as we handle it below
		$installed_at = apply_filters( 'wpgetapi_plugin_installed_timestamp', $installed_at );
		return $installed_at;
	}

	/**
	 * This function will check if we should display the rate notice or not.
	 *
	 * @return bool - to indicate if we should show the notice or not
	 */
	protected function show_rate_notice() {
		$installed_at  = $this->get_wpgetapi_plugin_installed_timestamp();
		$time_now      = $this->get_time_now();
		$installed_for = $time_now - $installed_at;

		if ( $installed_at && $installed_for > 28 * 86400 ) {
			return true;
		}

		return false;
	}

	/**
	 * Determines whether to prepare a seasonal notice(returns true) or not(returns false).
	 *
	 * @param  array $notice_data - all data for the notice
	 * @return bool
	 */
	protected function skip_seasonal_notices( $notice_data ) {
		$time_now   = $this->get_time_now();
		$valid_from = strtotime( $notice_data['valid_from'] );
		$valid_to   = strtotime( $notice_data['valid_to'] );
		$dismiss    = $this->check_notice_dismissed( $notice_data['dismiss_time'] );
		if ( ( $time_now >= $valid_from && $time_now <= $valid_to ) && ! $dismiss ) {
			return true;
		}
		return false;
	}

	/**
	 * Get timestamp that is considered as current timestamp for notice.
	 *
	 * @return int timestamp that should be consider as a current time.
	 */
	public function get_time_now() {
		$time_now = defined( 'WPGETAPI_NOTICES_FORCE_TIME' ) ? WPGETAPI_NOTICES_FORCE_TIME : time();
		return $time_now;
	}

	/**
	 * Checks whether a notice is dismissed(returns true) or not(returns false).
	 *
	 * @param  string $dismiss_time - dismiss time id for the notice
	 * @return bool
	 */
	protected function check_notice_dismissed( $dismiss_time ) {
		$time_now = $this->get_time_now();

		$dismiss = ( $time_now < get_option( $dismiss_time, 0 ) );

		return $dismiss;
	}

	/**
	 * Renders or returns a notice.
	 *
	 * @param  bool|string $advert_information     - all data for the notice
	 * @param  bool        $return_instead_of_echo - whether to return the notice(true) or render it to the page(false)
	 * @param  string      $position               - notice position
	 * @return void|string
	 */
	protected function render_specified_notice( $advert_information, $return_instead_of_echo = false, $position = 'top' ) {

		if ( 'bottom' == $position ) {
			$template_file = 'bottom-notice.php';
		} elseif ( 'report' == $position ) {
			$template_file = 'report.php';
		} elseif ( 'report-plain' == $position ) {
			$template_file = 'report-plain.php';
		} else {
			$template_file = 'horizontal-notice.php';
		}

		return WpGetApi_Admin_Options()->include_template( 'notices/' . $template_file, $return_instead_of_echo, $advert_information );
	}
}

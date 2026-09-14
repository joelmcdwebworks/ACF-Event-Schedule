<?php
/**
 * Plugin dependency checks.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies that ACF Pro is available.
 */
class Dependencies {

	/**
	 * Whether ACF Pro is loaded and usable.
	 *
	 * @return bool
	 */
	public static function is_ready() {
		return class_exists( 'ACF' )
			&& function_exists( 'acf_add_local_field_group' )
			&& defined( 'ACF_PRO' )
			&& ACF_PRO;
	}

	/**
	 * Show an admin notice when ACF Pro is missing.
	 *
	 * @return void
	 */
	public static function admin_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'ACF Event Schedule requires Advanced Custom Fields Pro to be installed and active.', 'acf-event-schedule' );
		echo '</p></div>';
	}
}

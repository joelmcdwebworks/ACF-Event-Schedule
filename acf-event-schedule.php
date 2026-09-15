<?php
/**
 * Plugin Name:       ACF Event Schedule
 * Plugin URI:        https://github.com/joelmcdwebworks/ACF-Event-Schedule
 * Description:       Create sessions, speakers, and event schedules with Advanced Custom Fields.
 * Version:           1.1.19
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Requires Plugins:  advanced-custom-fields-pro
 * Author:            Joel McD Web Works
 * Author URI:        https://github.com/joelmcdwebworks
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       acf-event-schedule
 * Domain Path:       /languages
 *
 * @package ACF_Event_Schedule
 */

defined( 'ABSPATH' ) || exit;

define( 'AES_VERSION', '1.1.19' );
define( 'AES_PLUGIN_FILE', __FILE__ );
define( 'AES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AES_PLUGIN_DIR . 'includes/class-dependencies.php';
require_once AES_PLUGIN_DIR . 'includes/class-plugin.php';
require_once AES_PLUGIN_DIR . 'includes/class-update-checker.php';

add_action( 'plugins_loaded', array( 'ACF_Event_Schedule\\Update_Checker', 'init' ), 5 );

/**
 * Load the plugin after ACF Pro is available.
 *
 * @return void
 */
function aes_bootstrap() {
	if ( ! ACF_Event_Schedule\Dependencies::is_ready() ) {
		add_action( 'admin_notices', array( 'ACF_Event_Schedule\\Dependencies', 'admin_notice' ) );
		return;
	}

	ACF_Event_Schedule\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'aes_bootstrap', 20 );

/**
 * Register post types and flush rewrite rules on activation.
 *
 * @return void
 */
function aes_activate() {
	require_once AES_PLUGIN_DIR . 'includes/class-field.php';
	require_once AES_PLUGIN_DIR . 'includes/class-helpers.php';
	require_once AES_PLUGIN_DIR . 'includes/post-types/class-session-post-type.php';
	require_once AES_PLUGIN_DIR . 'includes/post-types/class-speaker-post-type.php';
	require_once AES_PLUGIN_DIR . 'includes/post-types/class-event-taxonomy.php';

	ACF_Event_Schedule\Session_Post_Type::register();
	ACF_Event_Schedule\Speaker_Post_Type::register();
	ACF_Event_Schedule\Event_Taxonomy::register();
	flush_rewrite_rules();
}
register_activation_hook( AES_PLUGIN_FILE, 'aes_activate' );

/**
 * Flush rewrite rules on deactivation.
 *
 * @return void
 */
function aes_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( AES_PLUGIN_FILE, 'aes_deactivate' );

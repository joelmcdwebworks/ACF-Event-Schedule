<?php
/**
 * Plugin loader.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Boots plugin modules.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}

	/**
	 * Load files and initialize modules.
	 *
	 * @return void
	 */
	public function init() {
		$this->includes();
		$this->load_textdomain();

		Settings::init();
		Importer::init();
		Session_Post_Type::init();
		Speaker_Post_Type::init();
		Event_Taxonomy::init();
		Field_Groups::init();
		Dynamic_Fields::init();
		Migrator::init();
		Status_Sync::init();
		Assets::init();
		Shortcode::init();
		Block::init();
	}

	/**
	 * Require plugin class files.
	 *
	 * @return void
	 */
	private function includes() {
		require_once AES_PLUGIN_DIR . 'includes/class-field.php';
		require_once AES_PLUGIN_DIR . 'includes/class-helpers.php';
		require_once AES_PLUGIN_DIR . 'includes/admin/class-settings.php';
		require_once AES_PLUGIN_DIR . 'includes/admin/class-importer.php';
		require_once AES_PLUGIN_DIR . 'includes/post-types/class-session-post-type.php';
		require_once AES_PLUGIN_DIR . 'includes/post-types/class-speaker-post-type.php';
		require_once AES_PLUGIN_DIR . 'includes/post-types/class-event-taxonomy.php';
		require_once AES_PLUGIN_DIR . 'includes/acf/class-field-groups.php';
		require_once AES_PLUGIN_DIR . 'includes/acf/class-dynamic-fields.php';
		require_once AES_PLUGIN_DIR . 'includes/class-migrator.php';
		require_once AES_PLUGIN_DIR . 'includes/class-status-sync.php';
		require_once AES_PLUGIN_DIR . 'includes/frontend/class-assets.php';
		require_once AES_PLUGIN_DIR . 'includes/frontend/class-schedule-query.php';
		require_once AES_PLUGIN_DIR . 'includes/frontend/class-schedule-renderer.php';
		require_once AES_PLUGIN_DIR . 'includes/frontend/class-shortcode.php';
		require_once AES_PLUGIN_DIR . 'includes/frontend/class-block.php';
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'acf-event-schedule',
			false,
			dirname( plugin_basename( AES_PLUGIN_FILE ) ) . '/languages'
		);
	}
}

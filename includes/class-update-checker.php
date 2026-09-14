<?php
/**
 * GitHub plugin update checker.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Loads Plugin Update Checker against the public GitHub repository.
 */
class Update_Checker {

	/**
	 * GitHub repository URL used for update checks.
	 *
	 * @var string
	 */
	const REPO_URL = 'https://github.com/joelmcdwebworks/ACF-Event-Schedule/';

	/**
	 * Plugin directory slug. Must match the installed folder name.
	 *
	 * @var string
	 */
	const SLUG = 'ACF-Event-Schedule';

	/**
	 * Register the update checker.
	 *
	 * Runs independently of ACF Pro so sites can still receive updates.
	 *
	 * @return void
	 */
	public static function init() {
		$loader = AES_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $loader ) ) {
			return;
		}

		require_once $loader;

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			self::REPO_URL,
			AES_PLUGIN_FILE,
			self::SLUG
		);

		$checker->getVcsApi()->enableReleaseAssets( '/\.zip($|[?&#])/i' );
	}
}

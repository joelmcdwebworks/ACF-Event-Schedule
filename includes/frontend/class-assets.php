<?php
/**
 * Script and style registration.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin and front-end assets.
 */
class Assets {

	const FRONTEND_HANDLE = 'aes-frontend';
	const ADMIN_HANDLE    = 'aes-admin';

	/**
	 * Hook asset registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register_frontend' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue_singular' ) );
		add_action( 'acf/input/admin_enqueue_scripts', array( self::class, 'enqueue_admin' ) );
	}

	/**
	 * Register front-end CSS so shortcode and block can enqueue it.
	 *
	 * @return void
	 */
	public static function register_frontend() {
		wp_register_style(
			self::FRONTEND_HANDLE,
			AES_PLUGIN_URL . 'assets/frontend.css',
			array(),
			AES_VERSION
		);
	}

	/**
	 * Enqueue the front-end stylesheet.
	 *
	 * @return void
	 */
	public static function enqueue_frontend() {
		wp_enqueue_style( self::FRONTEND_HANDLE );
	}

	/**
	 * Load front-end CSS on session and speaker singles.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_singular() {
		if ( is_singular( array( Session_Post_Type::SLUG, Speaker_Post_Type::SLUG ) ) ) {
			self::enqueue_frontend();
		}
	}

	/**
	 * Enqueue admin scripts on ACF field screens.
	 *
	 * @return void
	 */
	public static function enqueue_admin() {
		wp_enqueue_style(
			self::ADMIN_HANDLE,
			AES_PLUGIN_URL . 'assets/admin.css',
			array(),
			AES_VERSION
		);

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			AES_PLUGIN_URL . 'assets/admin.js',
			array( 'acf-input', 'jquery' ),
			AES_VERSION,
			true
		);

		wp_localize_script(
			self::ADMIN_HANDLE,
			'aesAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aes_admin' ),
			)
		);
	}
}

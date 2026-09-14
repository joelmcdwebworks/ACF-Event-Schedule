<?php
/**
 * Event Schedule Gutenberg block.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the dynamic schedule block.
 */
class Block {

	const NAME = 'acf-event-schedule/schedule';

	/**
	 * Hook block registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public static function register() {
		wp_register_script(
			'aes-schedule-block',
			AES_PLUGIN_URL . 'blocks/event-schedule/index.js',
			array(
				'wp-blocks',
				'wp-block-editor',
				'wp-components',
				'wp-element',
				'wp-i18n',
				'wp-server-side-render',
			),
			AES_VERSION,
			true
		);

		wp_set_script_translations( 'aes-schedule-block', 'acf-event-schedule', AES_PLUGIN_DIR . 'languages' );

		register_block_type(
			AES_PLUGIN_DIR . 'blocks/event-schedule',
			array(
				'editor_script'   => 'aes-schedule-block',
				'style'           => Assets::FRONTEND_HANDLE,
				'editor_style'    => Assets::FRONTEND_HANDLE,
				'render_callback' => array( self::class, 'render' ),
			)
		);
	}

	/**
	 * Server-side block render callback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Block content.
	 * @param \WP_Block|null       $block      Block instance.
	 * @return string
	 */
	public static function render( $attributes, $content = '', $block = null ) {
		unset( $content );
		return Schedule_Renderer::from_block( is_array( $attributes ) ? $attributes : array(), $block );
	}
}

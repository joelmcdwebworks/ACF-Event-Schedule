<?php
/**
 * [event-schedule] shortcode.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the event schedule shortcode.
 */
class Shortcode {

	/**
	 * Hook the shortcode.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'event-schedule', array( self::class, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
				'date'    => '',
			),
			$atts,
			'event-schedule'
		);

		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		return Schedule_Renderer::render( $post_id, $atts['date'] );
	}
}

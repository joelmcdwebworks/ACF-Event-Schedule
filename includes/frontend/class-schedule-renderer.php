<?php
/**
 * Schedule HTML renderer.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Renders schedule markup from query data.
 */
class Schedule_Renderer {

	/**
	 * Render a schedule for shortcode or block attributes.
	 *
	 * @param int    $post_id Event post ID.
	 * @param string $date    Optional Y-m-d filter.
	 * @return string
	 */
	public static function render( $post_id, $date = '' ) {
		$post_id = absint( $post_id );
		$date    = Helpers::sanitize_date( $date );

		if ( ! $post_id || ! Helpers::can_display_post( $post_id ) ) {
			return self::empty_message();
		}

		$schedule = ( new Schedule_Query() )->get_schedule( $post_id, $date );

		if ( empty( $schedule['days'] ) ) {
			return self::empty_message();
		}

		Assets::enqueue_frontend();

		ob_start();
		$template = Helpers::locate_template( 'schedule.php' );
		if ( file_exists( $template ) ) {
			include $template;
		}
		return (string) ob_get_clean();
	}

	/**
	 * Render from block attributes.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param \WP_Block|null       $block      Block instance.
	 * @return string
	 */
	public static function from_block( $attributes, $block = null ) {
		$post_id = isset( $attributes['postId'] ) ? absint( $attributes['postId'] ) : 0;
		$date    = isset( $attributes['date'] ) ? (string) $attributes['date'] : '';

		if ( ! $post_id && $block && isset( $block->context['postId'] ) ) {
			$post_id = absint( $block->context['postId'] );
		}

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		return self::render( $post_id, $date );
	}

	/**
	 * Empty state for missing or unpublished schedules.
	 *
	 * @return string
	 */
	private static function empty_message() {
		return '<p class="aes-schedule__empty">' . esc_html__( 'No schedule found for this event.', 'acf-event-schedule' ) . '</p>';
	}

	/**
	 * Include the session card template.
	 *
	 * @param array<string, mixed> $session Session data.
	 * @return void
	 */
	public static function session_cell( $session ) {
		$template = Helpers::locate_template( 'session-cell.php' );
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}

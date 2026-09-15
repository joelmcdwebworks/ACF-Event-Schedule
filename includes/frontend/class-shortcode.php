<?php
/**
 * Front-end shortcodes.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Registers event and session shortcodes.
 */
class Shortcode {

	/**
	 * Hook the shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'event-schedule', array( self::class, 'render' ) );
		add_shortcode( 'session-times', array( self::class, 'render_session_times' ) );
		add_shortcode( 'session-speakers', array( self::class, 'render_session_speakers' ) );
		add_shortcode( 'session-space', array( self::class, 'render_session_space' ) );
	}

	/**
	 * Render the event schedule shortcode.
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

	/**
	 * Render a session's start and end times.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_session_times( $atts ) {
		$session_id = self::get_session_id( $atts, 'session-times' );
		if ( ! $session_id ) {
			return '';
		}

		$event_id = Helpers::normalize_id( get_field( Field::SESSION_EVENT, $session_id ) );
		$block_id = (string) get_field( Field::SESSION_TIME_BLOCK, $session_id );
		$block    = Helpers::get_time_block( $event_id, $block_id );

		if ( ! $block ) {
			return '';
		}

		$label = Helpers::format_session_times_label(
			$block['date'] ?? '',
			$block['start'] ?? '',
			$block['end'] ?? ''
		);

		if ( ! $label ) {
			return '';
		}

		$datetime = Helpers::datetime_attribute( $block['date'] ?? '', $block['start'] ?? '' );

		$html = '<span class="aes-session-times">';
		if ( $datetime ) {
			$html .= '<time datetime="' . esc_attr( $datetime ) . '">' . esc_html( $label ) . '</time>';
		} else {
			$html .= esc_html( $label );
		}
		$html .= '</span>';

		return $html;
	}

	/**
	 * Render a session's linked speakers as a comma-separated list.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_session_speakers( $atts ) {
		$session_id = self::get_session_id( $atts, 'session-speakers' );
		if ( ! $session_id ) {
			return '';
		}

		$speakers_raw = get_field( Field::SESSION_SPEAKERS, $session_id );
		if ( ! is_array( $speakers_raw ) ) {
			return '';
		}

		$links = array();

		foreach ( $speakers_raw as $speaker ) {
			$speaker_id = Helpers::normalize_id( $speaker );
			if ( ! $speaker_id || ! Helpers::can_display_post( $speaker_id ) ) {
				continue;
			}

			$title = get_the_title( $speaker_id );
			$url   = get_permalink( $speaker_id );
			if ( ! $title || ! $url ) {
				continue;
			}

			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
		}

		if ( empty( $links ) ) {
			return '';
		}

		return '<span class="aes-session-speakers">' . implode( ', ', $links ) . '</span>';
	}

	/**
	 * Render a session's space name.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_session_space( $atts ) {
		$session_id = self::get_session_id( $atts, 'session-space' );
		if ( ! $session_id ) {
			return '';
		}

		$event_id = Helpers::normalize_id( get_field( Field::SESSION_EVENT, $session_id ) );
		$space_id = (string) get_field( Field::SESSION_SPACE, $session_id );
		$label    = Helpers::get_space_name( $event_id, $space_id );

		if ( '' === $label ) {
			return '';
		}

		return '<span class="aes-session-space">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Resolve a readable session ID from shortcode attributes.
	 *
	 * @param array<string, string>|string $atts      Shortcode attributes.
	 * @param string                       $shortcode Shortcode tag.
	 * @return int
	 */
	private static function get_session_id( $atts, $shortcode ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
			),
			$atts,
			$shortcode
		);

		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id ) {
			$post_id = absint( get_the_ID() );
		}

		if ( ! $post_id || Session_Post_Type::SLUG !== get_post_type( $post_id ) ) {
			return 0;
		}

		if ( ! Helpers::can_display_post( $post_id ) ) {
			return 0;
		}

		return $post_id;
	}
}

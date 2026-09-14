<?php
/**
 * Shared helpers.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Utility methods used across admin and front end.
 */
class Helpers {

	const OPTION_EVENT_POST_TYPES = 'aes_event_post_types';
	const SPACE_ALL               = 'all';

	/**
	 * Post types that cannot be used as events.
	 *
	 * @return string[]
	 */
	public static function excluded_post_types() {
		return array( 'attachment', 'session', 'speaker' );
	}

	/**
	 * Public post types that may be selected as events.
	 *
	 * @return \WP_Post_Type[]
	 */
	public static function available_event_post_types() {
		$types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		foreach ( self::excluded_post_types() as $excluded ) {
			unset( $types[ $excluded ] );
		}

		return $types;
	}

	/**
	 * Saved event post type slugs.
	 *
	 * Does not require the post type to be registered yet. ACF builds field
	 * group locations on `acf/include_fields`, which runs before many CPTs
	 * (including ACF-registered ones) are available.
	 *
	 * @return string[]
	 */
	public static function get_event_post_types() {
		$saved = get_option( self::OPTION_EVENT_POST_TYPES, array() );

		if ( ! is_array( $saved ) ) {
			return array();
		}

		$excluded = self::excluded_post_types();
		$clean    = array();

		foreach ( $saved as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( $slug && ! in_array( $slug, $excluded, true ) ) {
				$clean[] = $slug;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Whether a post type is configured as an event type.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function is_event_post_type( $post_type ) {
		return in_array( $post_type, self::get_event_post_types(), true );
	}

	/**
	 * Generate a stable unique ID.
	 *
	 * @return string
	 */
	public static function generate_id() {
		return wp_generate_uuid4();
	}

	/**
	 * Sanitize an ID for use as a CSS custom ident.
	 *
	 * @param string $id Raw ID.
	 * @return string
	 */
	public static function css_ident( $id ) {
		$ident = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $id );
		return 'id-' . $ident;
	}

	/**
	 * Current ACF post ID from form data or request.
	 *
	 * @return int
	 */
	public static function get_acf_post_id() {
		$post_id = 0;

		if ( function_exists( 'acf_maybe_get_POST' ) ) {
			$post_id = acf_maybe_get_POST( 'post_id' );
		}

		if ( ! $post_id && function_exists( 'acf_get_form_data' ) ) {
			$post_id = acf_get_form_data( 'post_id' );
		}

		if ( ! $post_id && isset( $_POST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_id = wp_unslash( $_POST['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		return is_numeric( $post_id ) ? absint( $post_id ) : 0;
	}

	/**
	 * Normalize an ACF post object/ID value to an integer.
	 *
	 * @param mixed $value Post object, ID, or array.
	 * @return int
	 */
	public static function normalize_id( $value ) {
		if ( $value instanceof \WP_Post ) {
			return (int) $value->ID;
		}

		if ( is_array( $value ) && isset( $value['ID'] ) ) {
			return absint( $value['ID'] );
		}

		return absint( $value );
	}

	/**
	 * Event ID submitted with the current ACF form.
	 *
	 * @param string $event_field_key Field key for the event post object.
	 * @return int
	 */
	public static function get_submitted_event_id( $event_field_key ) {
		if ( isset( $_POST['acf'][ $event_field_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return self::normalize_id( wp_unslash( $_POST['acf'][ $event_field_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$post_id = self::get_acf_post_id();
		if ( $post_id ) {
			return self::normalize_id( get_field( Field::SESSION_EVENT, $post_id ) );
		}

		return 0;
	}

	/**
	 * Time blocks stored on an event post.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<int, array<string, string>>
	 */
	public static function get_time_blocks( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return array();
		}

		$days = get_field( Field::EVENT_DATES, $event_id );
		if ( is_array( $days ) && ! empty( $days ) ) {
			return self::flatten_event_dates( $days );
		}

		return self::get_legacy_time_blocks( $event_id );
	}

	/**
	 * Flatten nested date / time-block rows into the schedule data shape.
	 *
	 * @param array<int, array<string, mixed>> $days Event date rows.
	 * @return array<int, array<string, string>>
	 */
	public static function flatten_event_dates( $days ) {
		$blocks = array();

		foreach ( $days as $day ) {
			$date  = isset( $day[ Field::EVENT_DATE ] ) ? (string) $day[ Field::EVENT_DATE ] : '';
			$rows  = isset( $day[ Field::TIME_BLOCKS ] ) && is_array( $day[ Field::TIME_BLOCKS ] ) ? $day[ Field::TIME_BLOCKS ] : array();

			foreach ( $rows as $row ) {
				$block = self::normalize_time_block_row( $row, $date );
				if ( $block ) {
					$blocks[] = $block;
				}
			}
		}

		return $blocks;
	}

	/**
	 * Read the pre-nested time block repeater from post meta.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<int, array<string, string>>
	 */
	public static function get_legacy_time_blocks( $event_id ) {
		$count = get_post_meta( $event_id, Field::TIME_BLOCKS, true );
		if ( ! is_numeric( $count ) || (int) $count < 1 ) {
			return array();
		}

		$blocks = array();
		$count  = (int) $count;

		for ( $i = 0; $i < $count; $i++ ) {
			$prefix = Field::TIME_BLOCKS . '_' . $i . '_';
			$row    = array(
				Field::TIME_BLOCK_ID    => (string) get_post_meta( $event_id, $prefix . Field::TIME_BLOCK_ID, true ),
				Field::TIME_BLOCK_TITLE => (string) get_post_meta( $event_id, $prefix . Field::TIME_BLOCK_TITLE, true ),
				Field::TIME_BLOCK_DATE  => (string) get_post_meta( $event_id, $prefix . Field::TIME_BLOCK_DATE, true ),
				Field::TIME_BLOCK_START => (string) get_post_meta( $event_id, $prefix . Field::TIME_BLOCK_START, true ),
				Field::TIME_BLOCK_END   => (string) get_post_meta( $event_id, $prefix . Field::TIME_BLOCK_END, true ),
			);

			$date  = $row[ Field::TIME_BLOCK_DATE ];
			$block = self::normalize_time_block_row( $row, $date );
			if ( $block ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Normalize a time block row.
	 *
	 * @param array<string, mixed> $row  Repeater row.
	 * @param string               $date Y-m-d date.
	 * @return array<string, string>|null
	 */
	private static function normalize_time_block_row( $row, $date ) {
		$id = isset( $row[ Field::TIME_BLOCK_ID ] ) ? (string) $row[ Field::TIME_BLOCK_ID ] : '';
		if ( '' === $id ) {
			return null;
		}

		return array(
			'id'     => $id,
			'title'  => isset( $row[ Field::TIME_BLOCK_TITLE ] ) ? (string) $row[ Field::TIME_BLOCK_TITLE ] : '',
			'date'   => self::normalize_acf_date( $date ),
			'start'  => isset( $row[ Field::TIME_BLOCK_START ] ) ? (string) $row[ Field::TIME_BLOCK_START ] : '',
			'end'    => isset( $row[ Field::TIME_BLOCK_END ] ) ? (string) $row[ Field::TIME_BLOCK_END ] : '',
			'css_id' => self::css_ident( $id ),
		);
	}

	/**
	 * Spaces stored on an event post.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<int, array<string, string>>
	 */
	public static function get_spaces( $event_id ) {
		$spaces = array();

		foreach ( self::space_rows( $event_id ) as $space ) {
			if ( self::is_all_space( $space['id'], $space['name'] ) ) {
				continue;
			}
			$spaces[] = $space;
		}

		return $spaces;
	}

	/**
	 * Display name for a stored space value.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $space_id Space ID or All keyword.
	 * @return string
	 */
	public static function get_space_name( $event_id, $space_id ) {
		$space_id = (string) $space_id;
		if ( self::is_all_space( $space_id ) ) {
			return self::all_space_label();
		}

		foreach ( self::space_rows( $event_id ) as $space ) {
			if ( $space['id'] !== $space_id ) {
				continue;
			}

			return self::is_all_space( $space['id'], $space['name'] )
				? self::all_space_label()
				: $space['name'];
		}

		return '';
	}

	/**
	 * Whether a session space spans every column in its time block.
	 *
	 * @param string $space_id Stored space value.
	 * @param int    $event_id Event post ID.
	 * @return bool
	 */
	public static function session_spans_spaces( $space_id, $event_id = 0 ) {
		if ( self::is_all_space( $space_id ) ) {
			return true;
		}

		if ( ! $event_id ) {
			return false;
		}

		foreach ( self::space_rows( $event_id ) as $space ) {
			if ( $space['id'] === (string) $space_id ) {
				return self::is_all_space( $space['id'], $space['name'] );
			}
		}

		return false;
	}

	/**
	 * Raw space rows stored on an event, including reserved names.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<int, array<string, string>>
	 */
	private static function space_rows( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return array();
		}

		$rows = get_field( Field::SPACES, $event_id );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$spaces = array();

		foreach ( $rows as $row ) {
			$id = isset( $row[ Field::SPACE_ID ] ) ? (string) $row[ Field::SPACE_ID ] : '';
			if ( '' === $id ) {
				continue;
			}

			$spaces[] = array(
				'id'     => $id,
				'name'   => isset( $row[ Field::SPACE_NAME ] ) ? (string) $row[ Field::SPACE_NAME ] : '',
				'css_id' => self::css_ident( $id ),
			);
		}

		return $spaces;
	}

	/**
	 * Label for the built-in All space.
	 *
	 * @return string
	 */
	public static function all_space_label() {
		return __( 'All', 'acf-event-schedule' );
	}

	/**
	 * Whether a space value means the session spans every space in the time block.
	 *
	 * @param string $space_id   Stored space ID or keyword.
	 * @param string $space_name Optional space name.
	 * @return bool
	 */
	public static function is_all_space( $space_id, $space_name = '' ) {
		$id = strtolower( trim( (string) $space_id ) );
		if ( self::SPACE_ALL === $id ) {
			return true;
		}

		return self::SPACE_ALL === strtolower( trim( (string) $space_name ) );
	}

	/**
	 * Select choices for an event's time blocks.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string, string>
	 */
	public static function get_time_block_choices( $event_id ) {
		$choices = array();

		foreach ( self::get_time_blocks( $event_id ) as $block ) {
			$choices[ $block['id'] ] = self::format_time_block_label( $block );
		}

		return $choices;
	}

	/**
	 * Select choices for an event's spaces.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string, string>
	 */
	public static function get_space_choices( $event_id ) {
		$choices = array(
			self::SPACE_ALL => self::all_space_label(),
		);

		foreach ( self::get_spaces( $event_id ) as $space ) {
			$label = $space['name'] ? $space['name'] : $space['id'];
			$choices[ $space['id'] ] = $label;
		}

		return $choices;
	}

	/**
	 * Human-readable time block label.
	 *
	 * @param array<string, string> $block Time block data.
	 * @return string
	 */
	public static function format_time_block_label( $block ) {
		$range = self::format_time_range(
			$block['date'] ?? '',
			$block['start'] ?? '',
			$block['end'] ?? ''
		);

		$title = isset( $block['title'] ) ? trim( $block['title'] ) : '';

		if ( $title && $range ) {
			return $title . ' — ' . $range;
		}

		if ( $title ) {
			return $title;
		}

		return $range ? $range : __( '(Untitled time block)', 'acf-event-schedule' );
	}

	/**
	 * Format a date and time range using site formats.
	 *
	 * @param string $date  Y-m-d date.
	 * @param string $start Time string.
	 * @param string $end   Time string.
	 * @return string
	 */
	public static function format_time_range( $date, $start, $end ) {
		$date_label = '';
		$start_label = '';
		$end_label   = '';

		if ( $date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$timestamp = strtotime( $date . ' 00:00:00' );
			if ( $timestamp ) {
				$date_label = wp_date( get_option( 'date_format' ), $timestamp );
			}
		}

		if ( $start ) {
			$start_ts = strtotime( ( $date ? $date . ' ' : '1970-01-01 ' ) . $start );
			if ( $start_ts ) {
				$start_label = wp_date( get_option( 'time_format' ), $start_ts );
			}
		}

		if ( $end ) {
			$end_ts = strtotime( ( $date ? $date . ' ' : '1970-01-01 ' ) . $end );
			if ( $end_ts ) {
				$end_label = wp_date( get_option( 'time_format' ), $end_ts );
			}
		}

		$time = $start_label;
		if ( $start_label && $end_label ) {
			/* translators: 1: start time, 2: end time */
			$time = sprintf( __( '%1$s – %2$s', 'acf-event-schedule' ), $start_label, $end_label );
		}

		if ( $date_label && $time ) {
			return $date_label . ', ' . $time;
		}

		return $date_label ? $date_label : $time;
	}

	/**
	 * Format a time range without the date.
	 *
	 * @param string $date  Y-m-d date.
	 * @param string $start Time string.
	 * @param string $end   Time string.
	 * @return string
	 */
	public static function format_time_only_range( $date, $start, $end ) {
		$start_label = '';
		$end_label   = '';

		if ( $start ) {
			$start_ts = strtotime( ( $date ? $date . ' ' : '1970-01-01 ' ) . $start );
			if ( $start_ts ) {
				$start_label = wp_date( get_option( 'time_format' ), $start_ts );
			}
		}

		if ( $end ) {
			$end_ts = strtotime( ( $date ? $date . ' ' : '1970-01-01 ' ) . $end );
			if ( $end_ts ) {
				$end_label = wp_date( get_option( 'time_format' ), $end_ts );
			}
		}

		if ( $start_label && $end_label ) {
			/* translators: 1: start time, 2: end time */
			return sprintf( __( '%1$s – %2$s', 'acf-event-schedule' ), $start_label, $end_label );
		}

		return $start_label ? $start_label : $end_label;
	}

	/**
	 * Format a single time using the site time format.
	 *
	 * @param string $date Y-m-d date.
	 * @param string $time Time string.
	 * @return string
	 */
	public static function format_time_only( $date, $time ) {
		if ( ! $time ) {
			return '';
		}

		$timestamp = strtotime( ( $date ? $date . ' ' : '1970-01-01 ' ) . $time );
		return $timestamp ? wp_date( get_option( 'time_format' ), $timestamp ) : '';
	}

	/**
	 * datetime attribute value for a date and time, with timezone offset.
	 *
	 * @param string $date Y-m-d date.
	 * @param string $time Time string.
	 * @return string
	 */
	public static function datetime_attribute( $date, $time ) {
		if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		$normalized = $time ? $time : '00:00:00';
		if ( preg_match( '/^\d{2}:\d{2}$/', $normalized ) ) {
			$normalized .= ':00';
		}

		if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $normalized ) ) {
			$normalized = self::normalize_acf_time( $normalized );
		}

		if ( ! $normalized ) {
			return '';
		}

		$datetime = date_create( $date . ' ' . $normalized, wp_timezone() );
		if ( ! $datetime ) {
			return $date . 'T' . $normalized;
		}

		return $datetime->format( 'c' );
	}

	/**
	 * Locate a template, allowing theme overrides.
	 *
	 * @param string $template Template filename.
	 * @return string
	 */
	public static function locate_template( $template ) {
		$theme = locate_template( array( 'acf-event-schedule/' . $template ) );
		if ( $theme ) {
			return $theme;
		}

		return AES_PLUGIN_DIR . 'templates/' . $template;
	}

	/**
	 * Whether a user can read a post on the front end.
	 *
	 * Editors can preview unpublished and password-protected events. Visitors
	 * only see published posts that are not password-gated.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function can_display_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( current_user_can( 'edit_post', $post_id ) ) {
			return true;
		}

		if ( 'publish' === $post->post_status && ! post_password_required( $post ) ) {
			return true;
		}

		return current_user_can( 'read_post', $post_id );
	}

	/**
	 * Whether the current user should see unpublished sessions on a schedule.
	 *
	 * @param int $event_id Event post ID.
	 * @return bool
	 */
	public static function can_preview_event_schedule( $event_id ) {
		$event_id = absint( $event_id );
		return $event_id && current_user_can( 'edit_post', $event_id );
	}

	/**
	 * Session/speaker statuses to include in a schedule query.
	 *
	 * @param int $event_id Event post ID.
	 * @return string[]
	 */
	public static function schedule_post_statuses( $event_id ) {
		if ( self::can_preview_event_schedule( $event_id ) ) {
			return array( 'publish', 'draft', 'pending', 'future', 'private' );
		}

		return array( 'publish' );
	}

	/**
	 * Normalize an ACF date value to Y-m-d.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	public static function normalize_acf_date( $date ) {
		$date = is_string( $date ) ? trim( $date ) : '';

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $date;
		}

		if ( preg_match( '/^\d{8}$/', $date ) ) {
			return substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
		}

		return $date;
	}

	/**
	 * Normalize a time string to H:i:s.
	 *
	 * @param string $time Time string.
	 * @return string Empty string when invalid.
	 */
	public static function normalize_acf_time( $time ) {
		$time = is_string( $time ) ? trim( $time ) : '';

		if ( '' === $time ) {
			return '';
		}

		if ( preg_match( '/^\d{1,2}:\d{2}:\d{2}$/', $time ) ) {
			$parts = explode( ':', $time );
			return sprintf( '%02d:%02d:%02d', (int) $parts[0], (int) $parts[1], (int) $parts[2] );
		}

		if ( preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
			$parts = explode( ':', $time );
			return sprintf( '%02d:%02d:00', (int) $parts[0], (int) $parts[1] );
		}

		$timestamp = strtotime( $time );
		if ( ! $timestamp ) {
			return '';
		}

		return gmdate( 'H:i:s', $timestamp );
	}

	/**
	 * Validate a Y-m-d date string.
	 *
	 * @param string $date Date string.
	 * @return string Empty string when invalid.
	 */
	public static function sanitize_date( $date ) {
		$date = is_string( $date ) ? trim( $date ) : '';

		if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		$parts = explode( '-', $date );
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			return '';
		}

		return $date;
	}
}

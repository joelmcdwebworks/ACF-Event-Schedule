<?php
/**
 * Dynamic ACF field behavior.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Cascading choices, IDs, validation, and AJAX.
 */
class Dynamic_Fields {

	/**
	 * Hook ACF filters and AJAX.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'acf/load_field/key=' . Field::KEY_SESSION_EVENT, array( self::class, 'filter_event_post_types' ) );
		add_filter( 'acf/load_field/key=' . Field::KEY_SPEAKER_EVENT, array( self::class, 'filter_event_post_types' ) );
		add_filter( 'acf/load_field/key=' . Field::KEY_SESSION_TIME_BLOCK, array( self::class, 'load_time_block_choices' ) );
		add_filter( 'acf/load_field/key=' . Field::KEY_SESSION_SPACE, array( self::class, 'load_space_choices' ) );

		add_filter( 'acf/prepare_field/key=' . Field::KEY_TIME_BLOCK_ID, array( self::class, 'make_readonly' ) );
		add_filter( 'acf/prepare_field/key=' . Field::KEY_SPACE_ID, array( self::class, 'make_readonly' ) );

		add_filter( 'acf/update_value/key=' . Field::KEY_TIME_BLOCK_ID, array( self::class, 'ensure_unique_id' ) );
		add_filter( 'acf/update_value/key=' . Field::KEY_SPACE_ID, array( self::class, 'ensure_unique_id' ) );

		add_filter( 'acf/fields/relationship/query/key=' . Field::KEY_SPEAKER_SESSIONS, array( self::class, 'filter_sessions_by_event' ), 10, 3 );
		add_filter( 'acf/fields/relationship/query/key=' . Field::KEY_SESSION_SPEAKERS, array( self::class, 'filter_speakers_by_event' ), 10, 3 );

		add_filter( 'acf/validate_value/key=' . Field::KEY_SESSION_TIME_BLOCK, array( self::class, 'validate_time_block' ), 10, 4 );
		add_filter( 'acf/validate_value/key=' . Field::KEY_SESSION_SPACE, array( self::class, 'validate_space' ), 10, 4 );
		add_filter( 'acf/validate_value/key=' . Field::KEY_SPEAKER_SESSIONS, array( self::class, 'validate_speaker_sessions' ), 10, 4 );
		add_filter( 'acf/validate_value/key=' . Field::KEY_SESSION_SPEAKERS, array( self::class, 'validate_session_speakers' ), 10, 4 );

		add_action( 'acf/save_post', array( self::class, 'maybe_flag_session_conflict' ), 20 );
		add_action( 'admin_notices', array( self::class, 'conflict_notice' ) );

		add_action( 'wp_ajax_aes_get_event_schedule_options', array( self::class, 'ajax_event_schedule_options' ) );
	}

	/**
	 * Limit event post object fields to the configured post types.
	 *
	 * @param array<string, mixed> $field Field array.
	 * @return array<string, mixed>
	 */
	public static function filter_event_post_types( $field ) {
		$types = Helpers::get_event_post_types();
		$field['post_type'] = ! empty( $types ) ? $types : array( 'aes_no_event_type' );
		return $field;
	}

	/**
	 * Populate time block choices from the current session's event.
	 *
	 * @param array<string, mixed> $field Field array.
	 * @return array<string, mixed>
	 */
	public static function load_time_block_choices( $field ) {
		$event_id = Helpers::get_submitted_event_id( Field::KEY_SESSION_EVENT );
		$field['choices'] = Helpers::get_time_block_choices( $event_id );
		return $field;
	}

	/**
	 * Populate space choices from the current session's event.
	 *
	 * @param array<string, mixed> $field Field array.
	 * @return array<string, mixed>
	 */
	public static function load_space_choices( $field ) {
		$event_id = Helpers::get_submitted_event_id( Field::KEY_SESSION_EVENT );
		$field['choices'] = Helpers::get_space_choices( $event_id );
		return $field;
	}

	/**
	 * Force ID fields to stay read-only in the admin UI.
	 *
	 * @param array<string, mixed> $field Field array.
	 * @return array<string, mixed>
	 */
	public static function make_readonly( $field ) {
		$field['readonly'] = 1;
		$field['disabled'] = 0;
		return $field;
	}

	/**
	 * Generate an ID when a repeater row is saved without one.
	 *
	 * @param mixed $value Field value.
	 * @return string
	 */
	public static function ensure_unique_id( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return Helpers::generate_id();
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Restrict speaker session choices to the selected event.
	 *
	 * @param array<string, mixed> $args    WP_Query args.
	 * @param array<string, mixed> $field   Field array.
	 * @param int|string           $post_id Current post ID.
	 * @return array<string, mixed>
	 */
	public static function filter_sessions_by_event( $args, $field, $post_id ) {
		$event_id = self::request_event_id();

		if ( ! $event_id && is_numeric( $post_id ) ) {
			$event_id = Helpers::normalize_id( get_field( Field::SPEAKER_EVENT, $post_id ) );
		}

		if ( $event_id ) {
			$args['meta_query'][] = array(
				'key'   => Field::SESSION_EVENT,
				'value' => $event_id,
			);
		} else {
			$args['post__in'] = array( 0 );
		}

		return $args;
	}

	/**
	 * Restrict session speaker choices to speakers for the same event.
	 *
	 * @param array<string, mixed> $args    WP_Query args.
	 * @param array<string, mixed> $field   Field array.
	 * @param int|string           $post_id Current post ID.
	 * @return array<string, mixed>
	 */
	public static function filter_speakers_by_event( $args, $field, $post_id ) {
		$event_id = self::request_event_id();

		if ( ! $event_id && is_numeric( $post_id ) ) {
			$event_id = Helpers::normalize_id( get_field( Field::SESSION_EVENT, $post_id ) );
		}

		if ( $event_id ) {
			$args['meta_query'][] = array(
				'key'   => Field::SPEAKER_EVENT,
				'value' => $event_id,
			);
		} else {
			$args['post__in'] = array( 0 );
		}

		return $args;
	}

	/**
	 * Event ID passed with relationship AJAX or ACF form data.
	 *
	 * @return int
	 */
	private static function request_event_id() {
		if ( isset( $_POST['aes_event_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return absint( wp_unslash( $_POST['aes_event_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		return Helpers::get_submitted_event_id( Field::KEY_SESSION_EVENT )
			?: Helpers::get_submitted_event_id( Field::KEY_SPEAKER_EVENT );
	}

	/**
	 * Ensure the time block belongs to the selected event.
	 *
	 * @param bool|string          $valid Whether the value is valid.
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field array.
	 * @param string               $input Input name.
	 * @return bool|string
	 */
	public static function validate_time_block( $valid, $value, $field, $input ) {
		unset( $field, $input );

		if ( true !== $valid || ! $value ) {
			return $valid;
		}

		$event_id = Helpers::get_submitted_event_id( Field::KEY_SESSION_EVENT );
		$ids      = array_keys( Helpers::get_time_block_choices( $event_id ) );

		if ( ! in_array( (string) $value, $ids, true ) ) {
			return __( 'The selected time block is not part of this event.', 'acf-event-schedule' );
		}

		return $valid;
	}

	/**
	 * Ensure the space belongs to the selected event.
	 *
	 * @param bool|string          $valid Whether the value is valid.
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field array.
	 * @param string               $input Input name.
	 * @return bool|string
	 */
	public static function validate_space( $valid, $value, $field, $input ) {
		unset( $field, $input );

		if ( true !== $valid || ! $value ) {
			return $valid;
		}

		$event_id = Helpers::get_submitted_event_id( Field::KEY_SESSION_EVENT );
		$ids      = array_keys( Helpers::get_space_choices( $event_id ) );

		if ( ! in_array( (string) $value, $ids, true ) ) {
			return __( 'The selected space is not part of this event.', 'acf-event-schedule' );
		}

		return $valid;
	}

	/**
	 * Ensure speaker sessions belong to the speaker's event.
	 *
	 * @param bool|string          $valid Whether the value is valid.
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field array.
	 * @param string               $input Input name.
	 * @return bool|string
	 */
	public static function validate_speaker_sessions( $valid, $value, $field, $input ) {
		unset( $field, $input );

		if ( true !== $valid || empty( $value ) || ! is_array( $value ) ) {
			return $valid;
		}

		$event_id = Helpers::get_submitted_event_id( Field::KEY_SPEAKER_EVENT );
		if ( ! $event_id ) {
			return __( 'Select an event before assigning sessions.', 'acf-event-schedule' );
		}

		foreach ( $value as $session_id ) {
			$session_id = absint( $session_id );
			if ( ! $session_id || Session_Post_Type::SLUG !== get_post_type( $session_id ) || 'trash' === get_post_status( $session_id ) ) {
				return __( 'One or more sessions are not valid.', 'acf-event-schedule' );
			}

			$session_event = Helpers::normalize_id( get_field( Field::SESSION_EVENT, $session_id ) );
			if ( $session_event !== $event_id ) {
				return __( 'One or more sessions do not belong to the selected event.', 'acf-event-schedule' );
			}
		}

		return $valid;
	}

	/**
	 * Ensure session speakers belong to the session's event.
	 *
	 * @param bool|string          $valid Whether the value is valid.
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field array.
	 * @param string               $input Input name.
	 * @return bool|string
	 */
	public static function validate_session_speakers( $valid, $value, $field, $input ) {
		unset( $field, $input );

		if ( true !== $valid || empty( $value ) || ! is_array( $value ) ) {
			return $valid;
		}

		$event_id = Helpers::get_submitted_event_id( Field::KEY_SESSION_EVENT );
		if ( ! $event_id ) {
			return __( 'Select an event before assigning speakers.', 'acf-event-schedule' );
		}

		foreach ( $value as $speaker_id ) {
			$speaker_id = absint( $speaker_id );
			if ( ! $speaker_id || Speaker_Post_Type::SLUG !== get_post_type( $speaker_id ) || 'trash' === get_post_status( $speaker_id ) ) {
				return __( 'One or more speakers are not valid.', 'acf-event-schedule' );
			}

			$speaker_event = Helpers::normalize_id( get_field( Field::SPEAKER_EVENT, $speaker_id ) );
			if ( $speaker_event !== $event_id ) {
				return __( 'One or more speakers do not belong to the selected event.', 'acf-event-schedule' );
			}
		}

		return $valid;
	}

	/**
	 * Warn when another session uses the same event, time block, and space.
	 *
	 * @param int|string $post_id Saved post ID.
	 * @return void
	 */
	public static function maybe_flag_session_conflict( $post_id ) {
		if ( ! is_numeric( $post_id ) || Session_Post_Type::SLUG !== get_post_type( $post_id ) ) {
			return;
		}

		$event_id  = Helpers::normalize_id( get_field( Field::SESSION_EVENT, $post_id ) );
		$block_id  = (string) get_field( Field::SESSION_TIME_BLOCK, $post_id );
		$space_id  = (string) get_field( Field::SESSION_SPACE, $post_id );

		if ( ! $event_id || ! $block_id || ! $space_id ) {
			return;
		}

		$space_query = Helpers::session_spans_spaces( $space_id, $event_id )
			? array()
			: array(
				'relation' => 'OR',
				array(
					'key'   => Field::SESSION_SPACE,
					'value' => $space_id,
				),
				array(
					'key'   => Field::SESSION_SPACE,
					'value' => Helpers::SPACE_ALL,
				),
			);

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'   => Field::SESSION_EVENT,
				'value' => $event_id,
			),
			array(
				'key'   => Field::SESSION_TIME_BLOCK,
				'value' => $block_id,
			),
		);

		if ( $space_query ) {
			$meta_query[] = $space_query;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => Session_Post_Type::SLUG,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
				'post__not_in'   => array( (int) $post_id ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => $meta_query,
			)
		);

		if ( ! $query->have_posts() ) {
			return;
		}

		set_transient(
			self::conflict_transient_key(),
			sprintf(
				/* translators: %s: conflicting session title */
				__( 'Another session (%s) already uses this event, time block, and space.', 'acf-event-schedule' ),
				get_the_title( $query->posts[0] )
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Display the session conflict notice.
	 *
	 * @return void
	 */
	public static function conflict_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Session_Post_Type::SLUG !== $screen->post_type ) {
			return;
		}

		$key     = self::conflict_transient_key();
		$message = get_transient( $key );

		if ( ! $message ) {
			return;
		}

		delete_transient( $key );

		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Transient key for the current user.
	 *
	 * @return string
	 */
	private static function conflict_transient_key() {
		return 'aes_session_conflict_' . get_current_user_id();
	}

	/**
	 * AJAX: time blocks and spaces for a selected event.
	 *
	 * @return void
	 */
	public static function ajax_event_schedule_options() {
		check_ajax_referer( 'aes_admin', 'nonce' );

		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'acf-event-schedule' ) ), 403 );
		}

		if ( ! $event_id ) {
			wp_send_json_success(
				array(
					'time_blocks' => array(),
					'spaces'      => array(),
				)
			);
		}

		if ( ! Helpers::is_event_post_type( (string) get_post_type( $event_id ) ) || ! current_user_can( 'edit_post', $event_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'acf-event-schedule' ) ), 403 );
		}

		wp_send_json_success(
			array(
				'time_blocks' => Helpers::get_time_block_choices( $event_id ),
				'spaces'      => Helpers::get_space_choices( $event_id ),
			)
		);
	}
}

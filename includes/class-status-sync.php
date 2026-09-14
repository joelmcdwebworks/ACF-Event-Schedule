<?php
/**
 * Keep session and speaker statuses in sync with their event.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Cascades event status to linked sessions and speakers.
 */
class Status_Sync {

	/**
	 * Statuses that cascade from an event to linked posts.
	 *
	 * @var string[]
	 */
	const SYNCED_STATUSES = array( 'draft', 'pending', 'private', 'publish', 'future', 'trash' );

	/**
	 * Whether a cascade is already running.
	 *
	 * @var bool
	 */
	private static $syncing = false;

	/**
	 * Hook status cascade, child clamping, and editor notices.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'transition_post_status', array( self::class, 'on_transition_post_status' ), 20, 3 );
		add_filter( 'wp_insert_post_data', array( self::class, 'clamp_child_data' ), 20, 2 );
		add_action( 'acf/save_post', array( self::class, 'on_acf_save_child' ), 30 );
		add_action( 'admin_notices', array( self::class, 'status_notice' ) );
	}

	/**
	 * When an event's status changes, apply it to linked sessions and speakers.
	 *
	 * @param string   $new  New status.
	 * @param string   $old  Previous status.
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function on_transition_post_status( $new, $old, $post ) {
		if ( self::$syncing || ! $post instanceof \WP_Post || $new === $old ) {
			return;
		}

		if ( ! Helpers::is_event_post_type( $post->post_type ) ) {
			return;
		}

		if ( in_array( $new, array( 'auto-draft', 'inherit', 'new' ), true ) ) {
			return;
		}

		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		self::sync_linked_posts( $post );
	}

	/**
	 * Align every linked session and speaker with the event.
	 *
	 * @param \WP_Post $event Event post.
	 * @return void
	 */
	public static function sync_linked_posts( $event ) {
		if ( ! $event instanceof \WP_Post ) {
			return;
		}

		self::$syncing = true;

		foreach ( self::linked_ids( (int) $event->ID ) as $post_id ) {
			self::apply_status( $post_id, $event );
		}

		self::$syncing = false;
	}

	/**
	 * One-time alignment of existing linked posts.
	 *
	 * @return void
	 */
	public static function backfill() {
		$types = Helpers::get_event_post_types();
		if ( empty( $types ) ) {
			return;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => self::SYNCED_STATUSES,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $event_id ) {
			$event = get_post( $event_id );
			if ( $event instanceof \WP_Post ) {
				self::sync_linked_posts( $event );
			}
		}
	}

	/**
	 * Force a session or speaker to match its event on save.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param array<string, mixed> $postarr Raw post data.
	 * @return array<string, mixed>
	 */
	public static function clamp_child_data( $data, $postarr ) {
		if ( self::$syncing ) {
			return $data;
		}

		$type = isset( $data['post_type'] ) ? $data['post_type'] : '';
		if ( Session_Post_Type::SLUG !== $type && Speaker_Post_Type::SLUG !== $type ) {
			return $data;
		}

		$status = isset( $data['post_status'] ) ? $data['post_status'] : '';
		if ( in_array( $status, array( 'auto-draft', 'inherit' ), true ) ) {
			return $data;
		}

		$event_id = self::event_id_for_child( $postarr );
		if ( ! $event_id ) {
			return $data;
		}

		$event = get_post( $event_id );
		if ( ! $event instanceof \WP_Post || ! Helpers::is_event_post_type( $event->post_type ) ) {
			return $data;
		}

		$target = $event->post_status;
		if ( ! in_array( $target, self::SYNCED_STATUSES, true ) ) {
			return $data;
		}

		if ( 'trash' === $status ) {
			return $data;
		}

		$data['post_status'] = $target;

		if ( 'future' === $target ) {
			$data['post_date']     = $event->post_date;
			$data['post_date_gmt'] = $event->post_date_gmt;
		}

		return $data;
	}

	/**
	 * After ACF writes the Event field, align the child with that event.
	 *
	 * @param int|string $post_id Saved post ID.
	 * @return void
	 */
	public static function on_acf_save_child( $post_id ) {
		if ( self::$syncing || ! is_numeric( $post_id ) ) {
			return;
		}

		$type = get_post_type( $post_id );
		if ( Session_Post_Type::SLUG !== $type && Speaker_Post_Type::SLUG !== $type ) {
			return;
		}

		$event_id = Helpers::normalize_id( get_field( Field::SESSION_EVENT, $post_id ) );
		if ( ! $event_id ) {
			return;
		}

		$event = get_post( $event_id );
		if ( ! $event instanceof \WP_Post || ! Helpers::is_event_post_type( $event->post_type ) ) {
			return;
		}

		self::$syncing = true;
		self::apply_status( (int) $post_id, $event );
		self::$syncing = false;
	}

	/**
	 * Explain that session/speaker status follows the event.
	 *
	 * @return void
	 */
	public static function status_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, array( Session_Post_Type::SLUG, Speaker_Post_Type::SLUG ), true ) ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $post_id ) {
			return;
		}

		$event_id = Helpers::normalize_id( get_post_meta( $post_id, Field::SESSION_EVENT, true ) );
		if ( ! $event_id ) {
			return;
		}

		$event = get_post( $event_id );
		if ( ! $event instanceof \WP_Post ) {
			return;
		}

		$status_label = self::status_label( $event->post_status );
		$event_title  = get_the_title( $event );
		$kind         = Session_Post_Type::SLUG === $screen->post_type
			? __( 'session', 'acf-event-schedule' )
			: __( 'speaker', 'acf-event-schedule' );

		if ( 'publish' === $event->post_status ) {
			$message = sprintf(
				/* translators: 1: session or speaker, 2: event title */
				__( 'This %1$s stays published while its event (%2$s) is published. Changing the event status also updates its sessions and speakers.', 'acf-event-schedule' ),
				$kind,
				$event_title
			);
		} else {
			$message = sprintf(
				/* translators: 1: session or speaker, 2: post status, 3: event title */
				__( 'This %1$s is %2$s because its event (%3$s) is %2$s. Publishing the event will publish its sessions and speakers.', 'acf-event-schedule' ),
				$kind,
				$status_label,
				$event_title
			);
		}

		echo '<div class="notice notice-info"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Apply an event's status to a linked post.
	 *
	 * @param int      $post_id Linked post ID.
	 * @param \WP_Post $event   Event post.
	 * @return void
	 */
	private static function apply_status( $post_id, $event ) {
		$post_id = absint( $post_id );
		$current = get_post_status( $post_id );
		$target  = $event->post_status;

		if ( ! $post_id || ! $current || ! in_array( $target, self::SYNCED_STATUSES, true ) ) {
			return;
		}

		if ( 'trash' === $target ) {
			if ( 'trash' !== $current ) {
				wp_trash_post( $post_id );
			}
			return;
		}

		if ( 'trash' === $current ) {
			wp_untrash_post( $post_id );
		}

		$args = array(
			'ID'          => $post_id,
			'post_status' => $target,
		);

		if ( 'future' === $target ) {
			$args['post_date']     = $event->post_date;
			$args['post_date_gmt'] = $event->post_date_gmt;
		}

		wp_update_post( $args );
	}

	/**
	 * Session and speaker IDs linked to an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return int[]
	 */
	private static function linked_ids( $event_id ) {
		$ids = array();

		foreach ( array( Session_Post_Type::SLUG, Speaker_Post_Type::SLUG ) as $post_type ) {
			$query = new \WP_Query(
				array(
					'post_type'              => $post_type,
					'post_status'            => self::SYNCED_STATUSES,
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array(
						array(
							'key'   => Field::SESSION_EVENT,
							'value' => $event_id,
						),
					),
				)
			);

			$ids = array_merge( $ids, $query->posts );
		}

		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Event ID for a session or speaker being saved.
	 *
	 * @param array<string, mixed> $postarr Raw post data.
	 * @return int
	 */
	private static function event_id_for_child( $postarr ) {
		$id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
		if ( $id ) {
			$event_id = Helpers::normalize_id( get_post_meta( $id, Field::SESSION_EVENT, true ) );
			if ( $event_id ) {
				return $event_id;
			}
		}

		return Helpers::get_submitted_event_id( Field::KEY_SESSION_EVENT )
			?: Helpers::get_submitted_event_id( Field::KEY_SPEAKER_EVENT );
	}

	/**
	 * Post status (and scheduled dates) to use when creating a linked post.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string, string>
	 */
	public static function insert_args_for_event( $event_id ) {
		$event = get_post( absint( $event_id ) );
		$args  = array(
			'post_status' => 'draft',
		);

		if ( ! $event instanceof \WP_Post ) {
			return $args;
		}

		if ( in_array( $event->post_status, array( 'draft', 'pending', 'private', 'publish', 'future' ), true ) ) {
			$args['post_status'] = $event->post_status;
		}

		if ( 'future' === $args['post_status'] ) {
			$args['post_date']     = $event->post_date;
			$args['post_date_gmt'] = $event->post_date_gmt;
		}

		return $args;
	}

	/**
	 * Human-readable status label.
	 *
	 * @param string $status Post status.
	 * @return string
	 */
	private static function status_label( $status ) {
		$object = get_post_status_object( $status );
		if ( $object && ! empty( $object->label ) ) {
			return $object->label;
		}

		return $status;
	}
}

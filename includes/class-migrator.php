<?php
/**
 * One-time data migrations.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates nested schedule field structures.
 */
class Migrator {

	const OPTION       = 'aes_schema_version';
	const VERSION      = 5;
	const FLUSH_OPTION = 'aes_flush_rewrites';

	/**
	 * Hook the migration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'acf/init', array( self::class, 'maybe_migrate' ), 20 );
		add_action( 'init', array( self::class, 'maybe_migrate_linked_event_terms' ), 20 );
		add_action( 'admin_init', array( self::class, 'maybe_flush_rewrites' ) );
		add_action( 'admin_init', array( self::class, 'maybe_migrate_linked_statuses' ) );
	}

	/**
	 * Run pending schema migrations.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		if ( ! function_exists( 'update_field' ) ) {
			return;
		}

		$current = (int) get_option( self::OPTION, 1 );

		if ( $current < 2 ) {
			foreach ( self::posts_with_legacy_time_blocks() as $post_id ) {
				self::migrate_event_dates( $post_id );
			}

			update_option( self::OPTION, 2, false );
			$current = 2;
		}

		if ( $current < 3 ) {
			self::migrate_event_speakers_to_speakers();
			update_option( self::OPTION, 3, false );
		}
	}

	/**
	 * Backfill aes_linked_event terms after the taxonomy is registered.
	 *
	 * Runs on `init` priority 20 so sessions, speakers, and the taxonomy exist.
	 * Earlier schema steps stay on `acf/init` because they call `update_field()`.
	 *
	 * @return void
	 */
	public static function maybe_migrate_linked_event_terms() {
		$current = (int) get_option( self::OPTION, 1 );

		if ( $current >= 3 && $current < 4 ) {
			Event_Taxonomy::backfill();
			update_option( self::FLUSH_OPTION, 1, false );
			update_option( self::OPTION, 4, false );
		}
	}

	/**
	 * Flush rewrite rules once from wp-admin after a schema bump that needs it.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrites() {
		if ( ! get_option( self::FLUSH_OPTION ) ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( self::FLUSH_OPTION );
	}

	/**
	 * Align session and speaker statuses with their events.
	 *
	 * @return void
	 */
	public static function maybe_migrate_linked_statuses() {
		$current = (int) get_option( self::OPTION, 1 );
		if ( $current < 4 || $current >= 5 ) {
			return;
		}

		Status_Sync::backfill();
		update_option( self::OPTION, 5, false );
	}

	/**
	 * Event post types to scan during migrations.
	 *
	 * @return string[]
	 */
	private static function event_post_types_for_query() {
		$post_types = Helpers::get_event_post_types();

		if ( empty( $post_types ) ) {
			$post_types = array_keys( Helpers::available_event_post_types() );
		}

		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		return $post_types;
	}

	/**
	 * Find posts that still have the flat time-block repeater.
	 *
	 * @return int[]
	 */
	private static function posts_with_legacy_time_blocks() {
		$post_types = self::event_post_types_for_query();

		$query = new \WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => Field::TIME_BLOCKS . '_0_' . Field::TIME_BLOCK_DATE,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return array_map( 'absint', $query->posts );
	}

	/**
	 * Convert one event's flat time blocks into dated groups.
	 *
	 * @param int $post_id Event post ID.
	 * @return void
	 */
	private static function migrate_event_dates( $post_id ) {
		$existing = get_field( Field::EVENT_DATES, $post_id );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			self::delete_legacy_time_block_meta( $post_id );
			return;
		}

		$legacy = Helpers::get_legacy_time_blocks( $post_id );
		if ( empty( $legacy ) ) {
			return;
		}

		$grouped = array();

		foreach ( $legacy as $block ) {
			$date = ! empty( $block['date'] ) ? $block['date'] : '';
			if ( ! isset( $grouped[ $date ] ) ) {
				$grouped[ $date ] = array();
			}
			$grouped[ $date ][] = $block;
		}

		ksort( $grouped );

		$dates = array();

		foreach ( $grouped as $date => $blocks ) {
			$rows = array();

			foreach ( $blocks as $block ) {
				$rows[] = array(
					Field::TIME_BLOCK_ID    => $block['id'],
					Field::TIME_BLOCK_TITLE => $block['title'],
					Field::TIME_BLOCK_START => $block['start'],
					Field::TIME_BLOCK_END   => $block['end'],
				);
			}

			$dates[] = array(
				Field::EVENT_DATE  => $date,
				Field::TIME_BLOCKS => $rows,
			);
		}

		$updated = update_field( Field::KEY_EVENT_DATES, $dates, $post_id );
		if ( $updated ) {
			self::delete_legacy_time_block_meta( $post_id );
		}
	}

	/**
	 * Remove the old top-level time block repeater meta.
	 *
	 * @param int $post_id Event post ID.
	 * @return void
	 */
	private static function delete_legacy_time_block_meta( $post_id ) {
		$meta = get_post_meta( $post_id );

		if ( ! is_array( $meta ) ) {
			return;
		}

		$prefix = Field::TIME_BLOCKS;

		foreach ( array_keys( $meta ) as $key ) {
			$plain = ltrim( (string) $key, '_' );
			if ( $plain === $prefix || 0 === strpos( $plain, $prefix . '_' ) ) {
				delete_post_meta( $post_id, $key );
			}
		}
	}

	/**
	 * Copy event Speakers relationships onto speaker Event fields, then remove the event-side field data.
	 *
	 * @return void
	 */
	private static function migrate_event_speakers_to_speakers() {
		foreach ( self::posts_with_event_speakers() as $event_id ) {
			if ( Session_Post_Type::SLUG === get_post_type( $event_id ) ) {
				continue;
			}

			$speaker_ids = self::relationship_ids( get_post_meta( $event_id, Field::EVENT_SPEAKERS, true ) );

			foreach ( $speaker_ids as $speaker_id ) {
				if ( Speaker_Post_Type::SLUG !== get_post_type( $speaker_id ) ) {
					continue;
				}

				$existing = Helpers::normalize_id( get_post_meta( $speaker_id, Field::SPEAKER_EVENT, true ) );
				if ( $existing ) {
					continue;
				}

				update_field( Field::KEY_SPEAKER_EVENT, $event_id, $speaker_id );
			}

			$field_key = (string) get_post_meta( $event_id, '_' . Field::EVENT_SPEAKERS, true );
			if ( '' !== $field_key && Field::KEY_EVENT_SPEAKERS !== $field_key ) {
				continue;
			}

			delete_post_meta( $event_id, Field::EVENT_SPEAKERS );
			delete_post_meta( $event_id, '_' . Field::EVENT_SPEAKERS );
		}
	}

	/**
	 * Posts that still store the removed event Speakers relationship.
	 *
	 * @return int[]
	 */
	private static function posts_with_event_speakers() {
		$by_value = self::query_post_ids(
			array(
				'post_type'  => self::event_post_types_for_query(),
				'meta_query' => array(
					array(
						'key'     => Field::EVENT_SPEAKERS,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$by_key = self::query_post_ids(
			array(
				'post_type'  => 'any',
				'meta_query' => array(
					array(
						'key'   => '_' . Field::EVENT_SPEAKERS,
						'value' => Field::KEY_EVENT_SPEAKERS,
					),
				),
			)
		);

		$ids = array_values( array_unique( array_merge( $by_value, $by_key ) ) );

		return array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return Session_Post_Type::SLUG !== get_post_type( $id );
				}
			)
		);
	}

	/**
	 * Query post IDs for a migration scan.
	 *
	 * @param array<string, mixed> $args WP_Query args.
	 * @return int[]
	 */
	private static function query_post_ids( $args ) {
		$query = new \WP_Query(
			array_merge(
				array(
					'post_status'            => 'any',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				),
				$args
			)
		);

		return array_map( 'absint', $query->posts );
	}

	/**
	 * Normalize an ACF relationship value to post IDs.
	 *
	 * @param mixed $value Relationship value.
	 * @return int[]
	 */
	private static function relationship_ids( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		if ( ! is_array( $value ) ) {
			$id = Helpers::normalize_id( $value );
			return $id ? array( $id ) : array();
		}

		$ids = array();

		foreach ( $value as $item ) {
			$id = Helpers::normalize_id( $item );
			if ( $id ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}
}

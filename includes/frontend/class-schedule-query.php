<?php
/**
 * Schedule data query.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Loads sessions and groups them into date grids.
 */
class Schedule_Query {

	/**
	 * Build schedule data for an event.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $date     Optional Y-m-d filter.
	 * @return array<string, mixed>
	 */
	public function get_schedule( $event_id, $date = '' ) {
		$event_id = absint( $event_id );
		$date     = Helpers::sanitize_date( $date );
		$spaces   = Helpers::get_spaces( $event_id );
		$blocks   = Helpers::get_time_blocks( $event_id );
		$sessions = $this->query_sessions( $event_id );
		$indexed  = $this->index_sessions( $sessions, $spaces, $event_id );

		$days = array();

		foreach ( $blocks as $block ) {
			if ( ! $block['date'] ) {
				continue;
			}

			if ( $date && $block['date'] !== $date ) {
				continue;
			}

			if ( ! isset( $days[ $block['date'] ] ) ) {
				$days[ $block['date'] ] = array(
					'date'        => $block['date'],
					'date_label'  => $this->format_date_heading( $block['date'] ),
					'time_blocks' => array(),
				);
			}

			$block_sessions = isset( $indexed[ $block['id'] ] ) ? $indexed[ $block['id'] ] : array();
			$span_sessions  = array();

			foreach ( $block_sessions as $space_id => $space_sessions ) {
				if ( ! Helpers::session_spans_spaces( (string) $space_id, $event_id ) ) {
					continue;
				}

				$span_sessions = array_merge( $span_sessions, $space_sessions );
				unset( $block_sessions[ $space_id ] );
			}

			$has_sessions = ! empty( $block_sessions ) || ! empty( $span_sessions );
			$is_break     = ! $has_sessions && '' !== trim( $block['title'] );

			$days[ $block['date'] ]['time_blocks'][] = array(
				'block'         => $block,
				'sessions'      => $block_sessions,
				'span_sessions' => $span_sessions,
				'is_break'      => $is_break,
			);
		}

		ksort( $days );

		foreach ( $days as $day_date => $day ) {
			usort(
				$days[ $day_date ]['time_blocks'],
				static function ( $a, $b ) {
					return strcmp( $a['block']['start'], $b['block']['start'] );
				}
			);

			$days[ $day_date ]['grid_style'] = $this->grid_style( $spaces, $days[ $day_date ]['time_blocks'] );
		}

		$schedule = array(
			'event_id'    => $event_id,
			'event_title' => $event_id ? get_the_title( $event_id ) : '',
			'spaces'      => $spaces,
			'days'        => $days,
		);

		/**
		 * Filter the assembled schedule data.
		 *
		 * @param array<string, mixed> $schedule Schedule data.
		 * @param int                  $event_id Event post ID.
		 * @param string               $date     Date filter.
		 */
		return apply_filters( 'aes_schedule_data', $schedule, $event_id, $date );
	}

	/**
	 * Query sessions for an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return \WP_Post[]
	 */
	private function query_sessions( $event_id ) {
		if ( ! $event_id ) {
			return array();
		}

		$args = array(
			'post_type'      => Session_Post_Type::SLUG,
			'post_status'    => Helpers::schedule_post_statuses( $event_id ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'   => Field::SESSION_EVENT,
					'value' => $event_id,
				),
			),
		);

		/**
		 * Filter the session query arguments.
		 *
		 * @param array<string, mixed> $args     WP_Query args.
		 * @param int                  $event_id Event post ID.
		 */
		$args = apply_filters( 'aes_schedule_query_args', $args, $event_id );

		$query = new \WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Index sessions by time block and space.
	 *
	 * @param \WP_Post[]                       $sessions Session posts.
	 * @param array<int, array<string, string>> $spaces   Event spaces.
	 * @param int                               $event_id Event post ID.
	 * @return array<string, array<string, array<int, array<string, mixed>>>>
	 */
	private function index_sessions( $sessions, $spaces, $event_id ) {
		$space_names = array();
		foreach ( $spaces as $space ) {
			$space_names[ $space['id'] ] = $space['name'];
		}

		$indexed = array();

		foreach ( $sessions as $session ) {
			$block_id = (string) get_field( Field::SESSION_TIME_BLOCK, $session->ID );
			$space_id = (string) get_field( Field::SESSION_SPACE, $session->ID );

			if ( ! $block_id || ! $space_id ) {
				continue;
			}

			$indexed[ $block_id ][ $space_id ][] = $this->map_session( $session, $space_id, $space_names, $event_id );
		}

		return $indexed;
	}

	/**
	 * Normalize a session for templates.
	 *
	 * @param \WP_Post             $session     Session post.
	 * @param string               $space_id    Space ID.
	 * @param array<string, string> $space_names Space ID => name.
	 * @param int                  $event_id    Event post ID.
	 * @return array<string, mixed>
	 */
	private function map_session( $session, $space_id, $space_names, $event_id ) {
		$speakers_raw = get_field( Field::SESSION_SPEAKERS, $session->ID );
		$speakers     = array();

		if ( is_array( $speakers_raw ) ) {
			foreach ( $speakers_raw as $speaker ) {
				$speaker_id = $speaker instanceof \WP_Post ? $speaker->ID : absint( $speaker );
				if ( ! $speaker_id || ! self::speaker_is_visible( $speaker_id, $event_id ) ) {
					continue;
				}

				$speakers[] = array(
					'id'    => $speaker_id,
					'title' => get_the_title( $speaker_id ),
					'url'   => get_permalink( $speaker_id ),
				);
			}
		}

		$spans_all = Helpers::session_spans_spaces( $space_id, $event_id );

		return array(
			'id'         => $session->ID,
			'title'      => get_the_title( $session ),
			'url'        => get_permalink( $session ),
			'speakers'   => $speakers,
			'space_id'   => $space_id,
			'space_name' => $spans_all
				? __( 'All', 'acf-event-schedule' )
				: ( isset( $space_names[ $space_id ] ) ? $space_names[ $space_id ] : '' ),
			'spans_all'  => $spans_all,
		);
	}

	/**
	 * Whether a speaker should appear on this schedule for the current user.
	 *
	 * @param int $speaker_id Speaker post ID.
	 * @param int $event_id   Event post ID.
	 * @return bool
	 */
	private function speaker_is_visible( $speaker_id, $event_id ) {
		$status = get_post_status( $speaker_id );
		return $status && in_array( $status, Helpers::schedule_post_statuses( $event_id ), true );
	}

	/**
	 * Date heading using the site date format.
	 *
	 * @param string $date Y-m-d date.
	 * @return string
	 */
	private function format_date_heading( $date ) {
		$label = Helpers::format_date_label( $date );
		return $label ? $label : $date;
	}

	/**
	 * Inline grid-template styles for a day.
	 *
	 * @param array<int, array<string, string>> $spaces Spaces.
	 * @param array<int, array<string, mixed>>  $blocks Time blocks for the day.
	 * @return string
	 */
	private function grid_style( $spaces, $blocks ) {
		$space_count = count( $spaces );
		$block_count = count( $blocks );

		if ( $space_count < 1 ) {
			$space_count = 1;
		}

		$columns = 'auto repeat(' . (int) $space_count . ', minmax(8rem, 1fr))';
		$rows    = 'auto repeat(' . (int) $block_count . ', auto)';

		return 'grid-template-columns: ' . $columns . '; grid-template-rows: ' . $rows . ';';
	}
}

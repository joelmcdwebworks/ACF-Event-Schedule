<?php
/**
 * CSV schedule import.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Imports an initial event schedule from CSV.
 */
class Importer {

	const ACTION_IMPORT   = 'aes_import_schedule_csv';
	const ACTION_EXPORT   = 'aes_export_schedule_csv';
	const ACTION_TEMPLATE = 'aes_download_schedule_csv';
	const NOTICE_KEY      = 'aes_import_notice_';
	const MAX_BYTES       = 2097152;
	const MAX_ROWS        = 2000;

	/**
	 * Hook import and template actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_IMPORT, array( self::class, 'handle_import' ) );
		add_action( 'admin_post_' . self::ACTION_EXPORT, array( self::class, 'handle_export' ) );
		add_action( 'admin_post_' . self::ACTION_TEMPLATE, array( self::class, 'handle_template' ) );
		add_action( 'admin_notices', array( self::class, 'render_notice' ) );
	}

	/**
	 * Canonical CSV columns and descriptions.
	 *
	 * @return array<string, string>
	 */
	public static function columns() {
		return array(
			'date'             => __( 'Event date (Y-m-d, such as 2026-09-19).', 'acf-event-schedule' ),
			'start_time'       => __( 'Time block start (24-hour H:i or 12-hour g:i a).', 'acf-event-schedule' ),
			'end_time'         => __( 'Time block end (same formats as start_time).', 'acf-event-schedule' ),
			'time_block_title' => __( 'Time block title. Shown on the schedule when the block has no sessions.', 'acf-event-schedule' ),
			'time_block_id'    => __( 'Optional time block ID from export. When set, that time block is updated instead of creating a new one.', 'acf-event-schedule' ),
			'space'            => __( 'Room or track name, or All for a session that spans every space. Required when session_title is set.', 'acf-event-schedule' ),
			'space_id'         => __( 'Optional space ID from export. When set, that space is updated instead of creating a new one. Leave empty when space is All.', 'acf-event-schedule' ),
			'session_title'    => __( 'Creates a session post when present. Leave empty for breaks.', 'acf-event-schedule' ),
			'session_id'       => __( 'Optional session post ID from export. When set, that session is updated instead of creating a new one.', 'acf-event-schedule' ),
			'session_content'  => __( 'Optional session post content.', 'acf-event-schedule' ),
			'speakers'         => __( 'Speaker names separated by semicolons. Each name becomes a speaker post title.', 'acf-event-schedule' ),
			'speaker_ids'      => __( 'Optional speaker post IDs from export, semicolon-separated in the same order as speakers. When set, those speakers are updated.', 'acf-event-schedule' ),
		);
	}

	/**
	 * Settings page import UI.
	 *
	 * @return void
	 */
	public static function render_section() {
		$events = self::event_choices();

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Import schedule', 'acf-event-schedule' ) . '</h2>';
		echo '<p>';
		echo esc_html__( 'Upload a CSV to create dates, time blocks, spaces, sessions, and speakers for an existing event. Matching time blocks and spaces are reused. Existing session and speaker posts are reused when the title already belongs to this event. Optional ID columns update the matching row or post when present.', 'acf-event-schedule' );
		echo '</p>';

		echo '<p><a class="button" href="' . esc_url( self::template_url() ) . '">';
		echo esc_html__( 'Download CSV template', 'acf-event-schedule' );
		echo '</a></p>';

		echo '<h3>' . esc_html__( 'Columns', 'acf-event-schedule' ) . '</h3>';
		echo '<table class="widefat striped aes-import-columns" style="max-width:880px">';
		echo '<thead><tr><th>' . esc_html__( 'Column', 'acf-event-schedule' ) . '</th><th>' . esc_html__( 'Description', 'acf-event-schedule' ) . '</th></tr></thead><tbody>';
		foreach ( self::columns() as $name => $description ) {
			printf(
				'<tr><td><code>%s</code></td><td>%s</td></tr>',
				esc_html( $name ),
				esc_html( $description )
			);
		}
		echo '</tbody></table>';

		echo '<form class="aes-import-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" style="margin-top:1.5em">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_IMPORT ) . '" />';
		wp_nonce_field( self::ACTION_IMPORT );

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="aes_import_event_id">' . esc_html__( 'Event', 'acf-event-schedule' ) . '</label></th><td>';

		if ( empty( $events ) ) {
			echo '<p class="description">';
			echo esc_html__( 'Select at least one event post type above and create an event post before importing.', 'acf-event-schedule' );
			echo '</p>';
		} else {
			echo '<select name="event_id" id="aes_import_event_id" required>';
			echo '<option value="">' . esc_html__( 'Select an event', 'acf-event-schedule' ) . '</option>';
			foreach ( $events as $event ) {
				printf(
					'<option value="%d">%s</option>',
					(int) $event['id'],
					esc_html( $event['label'] )
				);
			}
			echo '</select>';
		}

		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="aes_import_file">' . esc_html__( 'CSV file', 'acf-event-schedule' ) . '</label></th><td>';
		echo '<input type="file" name="csv" id="aes_import_file" accept=".csv,text/csv" required />';
		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button( __( 'Import schedule', 'acf-event-schedule' ), 'primary', 'submit', false, empty( $events ) ? array( 'disabled' => 'disabled' ) : array() );
		echo '</form>';
	}

	/**
	 * Settings page export UI.
	 *
	 * @return void
	 */
	public static function render_export_section() {
		$events = self::event_choices();

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Export schedule', 'acf-event-schedule' ) . '</h2>';
		echo '<p>';
		echo esc_html__( 'Download an event schedule as a CSV that uses the same columns as import, including unique IDs for editing and reimport.', 'acf-event-schedule' );
		echo '</p>';

		echo '<form class="aes-import-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_EXPORT ) . '" />';
		wp_nonce_field( self::ACTION_EXPORT );

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="aes_export_event_id">' . esc_html__( 'Event', 'acf-event-schedule' ) . '</label></th><td>';

		if ( empty( $events ) ) {
			echo '<p class="description">';
			echo esc_html__( 'Select at least one event post type above and create an event post before exporting.', 'acf-event-schedule' );
			echo '</p>';
		} else {
			echo '<select name="event_id" id="aes_export_event_id" required>';
			echo '<option value="">' . esc_html__( 'Select an event', 'acf-event-schedule' ) . '</option>';
			foreach ( $events as $event ) {
				printf(
					'<option value="%d">%s</option>',
					(int) $event['id'],
					esc_html( $event['label'] )
				);
			}
			echo '</select>';
		}

		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button( __( 'Download CSV', 'acf-event-schedule' ), 'secondary', 'submit', false, empty( $events ) ? array( 'disabled' => 'disabled' ) : array() );
		echo '</form>';
	}

	/**
	 * Download the template CSV.
	 *
	 * @return void
	 */
	public static function handle_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'acf-event-schedule' ), 403 );
		}

		check_admin_referer( self::ACTION_TEMPLATE );

		$filename = 'event-schedule-template.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		if ( ! $output ) {
			wp_die( esc_html__( 'Could not generate the template.', 'acf-event-schedule' ) );
		}

		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $output, array_keys( self::columns() ) );

		foreach ( self::template_rows() as $row ) {
			fputcsv( $output, array_map( array( self::class, 'csv_cell' ), $row ) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Download a schedule CSV for the selected event.
	 *
	 * @return void
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'acf-event-schedule' ), 403 );
		}

		check_admin_referer( self::ACTION_EXPORT );

		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

		if ( ! $event_id || ! Helpers::is_event_post_type( (string) get_post_type( $event_id ) ) ) {
			self::redirect_with_notice( 'error', array( __( 'Select a valid event.', 'acf-event-schedule' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			self::redirect_with_notice( 'error', array( __( 'You are not allowed to export that event.', 'acf-event-schedule' ) ) );
		}

		$filename = self::export_filename( $event_id );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		if ( ! $output ) {
			wp_die( esc_html__( 'Could not generate the export.', 'acf-event-schedule' ) );
		}

		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $output, array_keys( self::columns() ) );

		foreach ( self::export_rows( $event_id ) as $row ) {
			fputcsv( $output, array_map( array( self::class, 'csv_cell' ), $row ) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Process an uploaded schedule CSV.
	 *
	 * @return void
	 */
	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'acf-event-schedule' ), 403 );
		}

		check_admin_referer( self::ACTION_IMPORT );

		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

		if ( ! $event_id || ! Helpers::is_event_post_type( (string) get_post_type( $event_id ) ) ) {
			self::redirect_with_notice( 'error', array( __( 'Select a valid event.', 'acf-event-schedule' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			self::redirect_with_notice( 'error', array( __( 'You are not allowed to edit that event.', 'acf-event-schedule' ) ) );
		}

		if ( empty( $_FILES['csv'] ) || ! is_array( $_FILES['csv'] ) ) {
			self::redirect_with_notice( 'error', array( __( 'Choose a CSV file to import.', 'acf-event-schedule' ) ) );
		}

		$file = $_FILES['csv']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! empty( $file['error'] ) ) {
			self::redirect_with_notice( 'error', array( __( 'The file could not be uploaded.', 'acf-event-schedule' ) ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size < 1 || $size > self::MAX_BYTES ) {
			self::redirect_with_notice( 'error', array( __( 'The CSV must be between 1 byte and 2 MB.', 'acf-event-schedule' ) ) );
		}

		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		if ( 'csv' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			self::redirect_with_notice( 'error', array( __( 'Upload a .csv file.', 'acf-event-schedule' ) ) );
		}

		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( ! $tmp || ! is_uploaded_file( $tmp ) ) {
			self::redirect_with_notice( 'error', array( __( 'The file could not be uploaded.', 'acf-event-schedule' ) ) );
		}

		$parsed = self::parse_file( $tmp );
		if ( is_wp_error( $parsed ) ) {
			self::redirect_with_notice( 'error', $parsed->get_error_messages() );
		}

		$result = self::import_rows( $event_id, $parsed );
		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( 'error', $result->get_error_messages() );
		}

		self::redirect_with_notice( 'success', self::summary_messages( $result ) );
	}

	/**
	 * Show the import result on the settings page.
	 *
	 * @return void
	 */
	public static function render_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( Settings::PAGE_SLUG, 'settings_page_' . Settings::PAGE_SLUG ), true ) ) {
			return;
		}

		$notice = get_transient( self::notice_key() );
		if ( ! is_array( $notice ) || empty( $notice['messages'] ) ) {
			return;
		}

		delete_transient( self::notice_key() );

		$type     = ( isset( $notice['type'] ) && 'success' === $notice['type'] ) ? 'success' : 'error';
		$messages = array_map( 'strval', (array) $notice['messages'] );

		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible">';
		foreach ( $messages as $message ) {
			echo '<p>' . esc_html( $message ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Parse and validate a CSV file.
	 *
	 * @param string $path Uploaded file path.
	 * @return array<int, array<string, string>>|\WP_Error
	 */
	public static function parse_file( $path ) {
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) {
			return new \WP_Error( 'aes_csv_open', __( 'The CSV file could not be read.', 'acf-event-schedule' ) );
		}

		$header = fgetcsv( $handle );
		if ( ! is_array( $header ) || empty( $header ) ) {
			fclose( $handle );
			return new \WP_Error( 'aes_csv_header', __( 'The CSV file is missing a header row.', 'acf-event-schedule' ) );
		}

		$map = self::header_map( $header );
		if ( is_wp_error( $map ) ) {
			fclose( $handle );
			return $map;
		}

		$rows   = array();
		$errors = array();
		$index  = 1;

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			++$index;

			if ( $index - 1 > self::MAX_ROWS ) {
				$errors[] = sprintf(
					/* translators: %d: maximum row count */
					__( 'The CSV has more than %d data rows.', 'acf-event-schedule' ),
					self::MAX_ROWS
				);
				break;
			}

			if ( self::row_is_empty( $data ) ) {
				continue;
			}

			$parsed = self::parse_row( $data, $map, $index );
			if ( is_wp_error( $parsed ) ) {
				$errors = array_merge( $errors, $parsed->get_error_messages() );
				continue;
			}

			$rows[] = $parsed;
		}

		fclose( $handle );

		if ( $errors ) {
			return self::error_from_messages( $errors );
		}

		if ( empty( $rows ) ) {
			return new \WP_Error( 'aes_csv_empty', __( 'The CSV did not contain any schedule rows.', 'acf-event-schedule' ) );
		}

		$errors = self::validate_schedule( $rows );
		if ( $errors ) {
			return self::error_from_messages( $errors );
		}

		return $rows;
	}

	/**
	 * Create event structure, sessions, and speakers from parsed rows.
	 *
	 * @param int                              $event_id Event post ID.
	 * @param array<int, array<string, mixed>> $rows     Parsed rows.
	 * @return array<string, int>|\WP_Error
	 */
	public static function import_rows( $event_id, $rows ) {
		if ( ! function_exists( 'update_field' ) ) {
			return new \WP_Error( 'aes_csv_acf', __( 'Advanced Custom Fields is required to import a schedule.', 'acf-event-schedule' ) );
		}

		$event_id = absint( $event_id );
		$id_errors = self::validate_ids_for_event( $event_id, $rows );
		if ( $id_errors ) {
			return self::error_from_messages( $id_errors );
		}

		$blocks = self::sync_event_structure( $event_id, $rows );
		if ( is_wp_error( $blocks ) ) {
			return $blocks;
		}

		$counts = array(
			'dates'             => $blocks['dates_added'],
			'time_blocks'       => $blocks['blocks_added'],
			'spaces'            => $blocks['spaces_added'],
			'sessions_created'  => 0,
			'sessions_existing' => 0,
			'speakers_created'  => 0,
			'speakers_existing' => 0,
		);

		$speaker_cache = array();

		foreach ( $rows as $row ) {
			if ( '' === $row['session_title'] ) {
				continue;
			}

			$speaker_ids = array();
			foreach ( $row['speakers'] as $i => $speaker_name ) {
				$speaker_id = isset( $row['speaker_ids'][ $i ] ) ? (int) $row['speaker_ids'][ $i ] : 0;
				$cache_key  = $speaker_id ? 'id:' . $speaker_id : strtolower( $speaker_name );
				$was_cached = isset( $speaker_cache[ $cache_key ] );
				$speaker    = self::ensure_speaker( $speaker_name, $event_id, $speaker_cache, $speaker_id );
				if ( is_wp_error( $speaker ) ) {
					return $speaker;
				}

				$speaker_ids[] = $speaker['id'];
				if ( $was_cached ) {
					continue;
				}

				if ( $speaker['created'] ) {
					++$counts['speakers_created'];
				} else {
					++$counts['speakers_existing'];
				}
			}

			$session = self::ensure_session(
				$event_id,
				$row,
				$blocks['block_ids'][ $row['block_key'] ],
				$blocks['space_ids'][ $row['space_key'] ],
				$speaker_ids
			);

			if ( is_wp_error( $session ) ) {
				return $session;
			}

			if ( $session['created'] ) {
				++$counts['sessions_created'];
			} else {
				++$counts['sessions_existing'];
			}
		}

		return $counts;
	}

	/**
	 * Template download URL.
	 *
	 * @return string
	 */
	private static function template_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION_TEMPLATE ),
			self::ACTION_TEMPLATE
		);
	}

	/**
	 * Example rows for the downloadable template.
	 *
	 * @return array<int, array<int, string>>
	 */
	private static function template_rows() {
		return array(
			array( '2026-09-19', '09:00', '10:00', 'Opening Keynote', '', 'All', '', 'Welcome Address', '', 'Opening remarks for the event.', 'Alex Rivera', '' ),
			array( '2026-09-19', '10:00', '11:00', 'Morning Sessions', '', 'Room A', '', 'Building Better Schedules', '', 'A practical session on planning rooms and time blocks.', 'Jordan Lee; Sam Patel', '' ),
			array( '2026-09-19', '10:00', '11:00', 'Morning Sessions', '', 'Room B', '', 'Community Lightning Talks', '', '', '', '' ),
			array( '2026-09-19', '11:00', '12:00', 'Lunch', '', '', '', '', '', '', '', '' ),
		);
	}

	/**
	 * Build import-compatible CSV rows for an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<int, array<int, string>>
	 */
	public static function export_rows( $event_id ) {
		$event_id = absint( $event_id );
		$blocks   = Helpers::get_time_blocks( $event_id );
		$grouped  = self::export_sessions_by_block( $event_id );
		$rows     = array();

		usort(
			$blocks,
			static function ( $a, $b ) {
				$date = strcmp( $a['date'], $b['date'] );
				return 0 !== $date ? $date : strcmp( $a['start'], $b['start'] );
			}
		);

		foreach ( $blocks as $block ) {
			$block_sessions = isset( $grouped[ $block['id'] ] ) ? $grouped[ $block['id'] ] : array();
			$wrote_session  = false;

			foreach ( $block_sessions as $space_id => $sessions ) {
				$space = Helpers::session_spans_spaces( (string) $space_id, $event_id )
					? 'All'
					: Helpers::get_space_name( $event_id, (string) $space_id );

				foreach ( $sessions as $session ) {
					$rows[]        = self::export_session_row( $block, $space, (string) $space_id, $session );
					$wrote_session = true;
				}
			}

			if ( ! $wrote_session ) {
				$rows[] = array(
					$block['date'],
					self::export_time( $block['start'] ),
					self::export_time( $block['end'] ),
					$block['title'],
					$block['id'],
					'',
					'',
					'',
					'',
					'',
					'',
					'',
				);
			}
		}

		return $rows;
	}

	/**
	 * CSV filename for an event export.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	private static function export_filename( $event_id ) {
		$slug = get_post_field( 'post_name', $event_id );
		if ( ! $slug ) {
			$slug = 'event-' . $event_id;
		}

		return sanitize_file_name( 'event-schedule-' . $slug . '.csv' );
	}

	/**
	 * Sessions grouped by time block and space.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string, array<string, array<int, array<string, mixed>>>>
	 */
	private static function export_sessions_by_block( $event_id ) {
		$query = new \WP_Query(
			array(
				'post_type'              => Session_Post_Type::SLUG,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => Field::SESSION_EVENT,
						'value' => $event_id,
					),
				),
			)
		);

		$grouped = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$block_id = (string) get_field( Field::SESSION_TIME_BLOCK, $post->ID );
			$space_id = (string) get_field( Field::SESSION_SPACE, $post->ID );
			if ( ! $block_id || ! $space_id ) {
				continue;
			}

			$speakers = self::export_speakers( $post->ID );

			$grouped[ $block_id ][ $space_id ][] = array(
				'id'          => (int) $post->ID,
				'title'       => self::export_plain_text( get_the_title( $post ) ),
				'content'     => (string) $post->post_content,
				'speakers'    => $speakers['names'],
				'speaker_ids' => $speakers['ids'],
			);
		}

		return $grouped;
	}

	/**
	 * One CSV data row for a session.
	 *
	 * @param array<string, string> $block    Time block.
	 * @param string                $space    Space name or All.
	 * @param string                $space_id Space ID or all.
	 * @param array<string, mixed>  $session  Session data.
	 * @return array<int, string>
	 */
	private static function export_session_row( $block, $space, $space_id, $session ) {
		$export_space_id = Helpers::is_all_space( $space_id, $space ) ? '' : $space_id;

		return array(
			$block['date'],
			self::export_time( $block['start'] ),
			self::export_time( $block['end'] ),
			$block['title'],
			isset( $block['id'] ) ? (string) $block['id'] : '',
			$space,
			$export_space_id,
			isset( $session['title'] ) ? (string) $session['title'] : '',
			isset( $session['id'] ) ? (string) (int) $session['id'] : '',
			isset( $session['content'] ) ? (string) $session['content'] : '',
			isset( $session['speakers'] ) ? (string) $session['speakers'] : '',
			isset( $session['speaker_ids'] ) ? (string) $session['speaker_ids'] : '',
		);
	}

	/**
	 * Speaker titles and IDs for a session, semicolon-separated.
	 *
	 * @param int $session_id Session post ID.
	 * @return array{names:string,ids:string}
	 */
	private static function export_speakers( $session_id ) {
		$speakers = get_field( Field::SESSION_SPEAKERS, $session_id );
		if ( ! is_array( $speakers ) ) {
			return array(
				'names' => '',
				'ids'   => '',
			);
		}

		$names = array();
		$ids   = array();

		foreach ( $speakers as $speaker ) {
			$speaker_id = $speaker instanceof \WP_Post ? $speaker->ID : absint( $speaker );
			if ( ! $speaker_id || 'trash' === get_post_status( $speaker_id ) ) {
				continue;
			}

			$title = self::export_plain_text( get_the_title( $speaker_id ) );
			if ( $title ) {
				$names[] = $title;
				$ids[]   = (string) (int) $speaker_id;
			}
		}

		return array(
			'names' => implode( '; ', $names ),
			'ids'   => implode( '; ', $ids ),
		);
	}

	/**
	 * Decode HTML entities for CSV text cells.
	 *
	 * @param string $text Raw or filtered title.
	 * @return string
	 */
	private static function export_plain_text( $text ) {
		return html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Neutralize CSV formula injection on user-derived cells.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	private static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && preg_match( '/^[=+\-@\t\r]/', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Format a stored time for CSV export.
	 *
	 * @param string $time H:i:s time.
	 * @return string
	 */
	private static function export_time( $time ) {
		$time = Helpers::normalize_acf_time( $time );
		if ( preg_match( '/^(\d{2}:\d{2}):00$/', $time, $matches ) ) {
			return $matches[1];
		}

		return $time;
	}

	/**
	 * Events available for import.
	 *
	 * @return array<int, array{id:int,label:string}>
	 */
	private static function event_choices() {
		$types = Helpers::get_event_post_types();
		if ( empty( $types ) ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$choices = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$label = $post->post_title ? $post->post_title : __( '(no title)', 'acf-event-schedule' );
			if ( 'publish' !== $post->post_status ) {
				$label .= ' (' . $post->post_status . ')';
			}

			$choices[] = array(
				'id'    => (int) $post->ID,
				'label' => $label,
			);
		}

		return $choices;
	}

	/**
	 * Map header cells to canonical column names.
	 *
	 * @param array<int, mixed> $header Header row.
	 * @return array<string, int>|\WP_Error
	 */
	private static function header_map( $header ) {
		$aliases = array(
			'date'             => 'date',
			'start_time'       => 'start_time',
			'start'            => 'start_time',
			'end_time'         => 'end_time',
			'end'              => 'end_time',
			'time_block_title' => 'time_block_title',
			'block_title'      => 'time_block_title',
			'time_block'       => 'time_block_title',
			'time_block_id'    => 'time_block_id',
			'block_id'         => 'time_block_id',
			'space'            => 'space',
			'space_name'       => 'space',
			'space_id'         => 'space_id',
			'session_title'    => 'session_title',
			'session'          => 'session_title',
			'session_id'       => 'session_id',
			'session_content'  => 'session_content',
			'content'          => 'session_content',
			'description'      => 'session_content',
			'speakers'         => 'speakers',
			'speaker'          => 'speakers',
			'speaker_ids'      => 'speaker_ids',
			'speaker_id'       => 'speaker_ids',
		);

		$map = array();

		foreach ( $header as $index => $label ) {
			$key = self::normalize_header( $label );
			if ( '' === $key || ! isset( $aliases[ $key ] ) ) {
				continue;
			}

			$canonical = $aliases[ $key ];
			if ( ! isset( $map[ $canonical ] ) ) {
				$map[ $canonical ] = (int) $index;
			}
		}

		foreach ( array( 'date', 'start_time', 'end_time' ) as $required ) {
			if ( ! isset( $map[ $required ] ) ) {
				return new \WP_Error(
					'aes_csv_columns',
					sprintf(
						/* translators: %s: column name */
						__( 'The CSV is missing the required %s column.', 'acf-event-schedule' ),
						$required
					)
				);
			}
		}

		return $map;
	}

	/**
	 * Normalize a header label.
	 *
	 * @param mixed $label Header cell.
	 * @return string
	 */
	private static function normalize_header( $label ) {
		$label = is_string( $label ) ? $label : '';
		$label = preg_replace( '/^\xEF\xBB\xBF/', '', $label );
		$label = strtolower( trim( (string) $label ) );
		$label = preg_replace( '/[\s-]+/', '_', $label );
		return is_string( $label ) ? $label : '';
	}

	/**
	 * Whether a CSV row has no values.
	 *
	 * @param mixed $data Raw fgetcsv row.
	 * @return bool
	 */
	private static function row_is_empty( $data ) {
		if ( ! is_array( $data ) ) {
			return true;
		}

		foreach ( $data as $cell ) {
			if ( '' !== trim( (string) $cell ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Parse one CSV data row.
	 *
	 * @param array<int, mixed>    $data  Raw cells.
	 * @param array<string, int>   $map   Header map.
	 * @param int                  $index 1-based file line number.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function parse_row( $data, $map, $index ) {
		$get = static function ( $key ) use ( $data, $map ) {
			if ( ! isset( $map[ $key ], $data[ $map[ $key ] ] ) ) {
				return '';
			}
			return trim( (string) $data[ $map[ $key ] ] );
		};

		$errors = array();
		$date   = self::parse_date( $get( 'date' ) );
		$start  = Helpers::normalize_acf_time( $get( 'start_time' ) );
		$end    = Helpers::normalize_acf_time( $get( 'end_time' ) );

		if ( ! $date ) {
			$errors[] = 'date';
		}
		if ( ! $start ) {
			$errors[] = 'start_time';
		}
		if ( ! $end ) {
			$errors[] = 'end_time';
		}

		$session_title = sanitize_text_field( $get( 'session_title' ) );
		$space         = sanitize_text_field( $get( 'space' ) );
		if ( Helpers::is_all_space( '', $space ) ) {
			$space = Helpers::all_space_label();
		}
		$block_title    = sanitize_text_field( $get( 'time_block_title' ) );
		$content        = $get( 'session_content' );
		$time_block_id  = sanitize_text_field( $get( 'time_block_id' ) );
		$session_id     = self::parse_post_id( $get( 'session_id' ) );
		$space_id       = sanitize_text_field( $get( 'space_id' ) );
		$speakers_raw   = $get( 'speakers' );
		$speaker_ids_raw = $get( 'speaker_ids' );

		if ( -1 === $session_id ) {
			$errors[] = 'session_id';
			$session_id = 0;
		}

		if ( Helpers::is_all_space( '', $space ) || '' === $space ) {
			$space_id = '';
		}

		$speaker_ids = array();
		if ( '' !== $speaker_ids_raw ) {
			$speakers    = self::parse_speaker_segments( $speakers_raw );
			$speaker_ids = self::parse_speaker_id_segments( $speaker_ids_raw );
			if ( is_wp_error( $speaker_ids ) ) {
				return new \WP_Error(
					'aes_csv_speaker_ids',
					sprintf(
						/* translators: %d: CSV line number */
						__( 'Row %d has an invalid speaker_ids value.', 'acf-event-schedule' ),
						$index
					)
				);
			}

			if ( count( $speakers ) !== count( $speaker_ids ) ) {
				return new \WP_Error(
					'aes_csv_speaker_ids',
					sprintf(
						/* translators: %d: CSV line number */
						__( 'Row %d: speaker_ids must have the same number of values as speakers.', 'acf-event-schedule' ),
						$index
					)
				);
			}

			$clean_names = array();
			$clean_ids   = array();
			foreach ( $speakers as $i => $name ) {
				$id = isset( $speaker_ids[ $i ] ) ? (int) $speaker_ids[ $i ] : 0;
				if ( '' === $name && ! $id ) {
					continue;
				}
				if ( '' === $name ) {
					return new \WP_Error(
						'aes_csv_speaker_ids',
						sprintf(
							/* translators: %d: CSV line number */
							__( 'Row %d: speaker_ids require a matching speakers name.', 'acf-event-schedule' ),
							$index
						)
					);
				}
				$clean_names[] = $name;
				$clean_ids[]   = $id;
			}
			$speakers    = $clean_names;
			$speaker_ids = $clean_ids;
		} else {
			$speakers = self::parse_speakers( $speakers_raw );
		}

		if ( $session_title && '' === $space ) {
			return new \WP_Error(
				'aes_csv_space',
				sprintf(
					/* translators: %d: CSV line number */
					__( 'Row %d: session_title requires a space.', 'acf-event-schedule' ),
					$index
				)
			);
		}

		if ( ! $session_title && $speakers ) {
			return new \WP_Error(
				'aes_csv_speakers',
				sprintf(
					/* translators: %d: CSV line number */
					__( 'Row %d: speakers require a session_title.', 'acf-event-schedule' ),
					$index
				)
			);
		}

		if ( ! $session_title && $speaker_ids ) {
			return new \WP_Error(
				'aes_csv_speaker_ids',
				sprintf(
					/* translators: %d: CSV line number */
					__( 'Row %d: speaker_ids require a session_title.', 'acf-event-schedule' ),
					$index
				)
			);
		}

		if ( ! $session_title && $session_id ) {
			return new \WP_Error(
				'aes_csv_session_id',
				sprintf(
					/* translators: %d: CSV line number */
					__( 'Row %d: session_id requires a session_title.', 'acf-event-schedule' ),
					$index
				)
			);
		}

		if ( $errors ) {
			return new \WP_Error(
				'aes_csv_row',
				sprintf(
					/* translators: 1: CSV line number, 2: column names */
					__( 'Row %1$d has an invalid %2$s.', 'acf-event-schedule' ),
					$index,
					implode( ', ', $errors )
				)
			);
		}

		$block_key = $date . '|' . $start . '|' . $end;
		$space_key = Helpers::is_all_space( '', $space ) ? Helpers::SPACE_ALL : ( $space ? strtolower( $space ) : '' );

		return array(
			'line'            => $index,
			'date'            => $date,
			'start'           => $start,
			'end'             => $end,
			'block_title'     => $block_title,
			'block_key'       => $block_key,
			'time_block_id'   => $time_block_id,
			'space'           => $space,
			'space_key'       => $space_key,
			'space_id'        => $space_id,
			'session_title'   => $session_title,
			'session_id'      => $session_id,
			'session_content' => $content,
			'speakers'        => $speakers,
			'speaker_ids'     => $speaker_ids,
		);
	}

	/**
	 * Parse a CSV date into Y-m-d.
	 *
	 * @param string $value Raw date cell.
	 * @return string
	 */
	private static function parse_date( $value ) {
		$value      = trim( (string) $value );
		$normalized = Helpers::sanitize_date( Helpers::normalize_acf_date( $value ) );
		if ( $normalized ) {
			return $normalized;
		}

		$formats = array( 'Y-n-j', 'n/j/Y', 'm/d/Y', 'n/j/y', 'm/d/y', 'Y/n/j' );

		foreach ( $formats as $format ) {
			$parsed = \DateTime::createFromFormat( '!' . $format, $value );
			if ( ! $parsed instanceof \DateTime ) {
				continue;
			}

			$errors = \DateTime::getLastErrors();
			if ( is_array( $errors ) && ( $errors['error_count'] > 0 || $errors['warning_count'] > 0 ) ) {
				continue;
			}

			if ( (int) $parsed->format( 'Y' ) < 1000 ) {
				continue;
			}

			return $parsed->format( 'Y-m-d' );
		}

		return '';
	}

	/**
	 * Split a speakers cell into unique names.
	 *
	 * @param string $value Raw speakers cell.
	 * @return string[]
	 */
	private static function parse_speakers( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return array();
		}

		$parts = preg_split( '/[;|]/', $value );
		$names = array();

		foreach ( (array) $parts as $part ) {
			$name = sanitize_text_field( trim( (string) $part ) );
			if ( '' === $name ) {
				continue;
			}
			$names[ strtolower( $name ) ] = $name;
		}

		return array_values( $names );
	}

	/**
	 * Split a speakers cell into ordered name segments.
	 *
	 * @param string $value Raw speakers cell.
	 * @return string[]
	 */
	private static function parse_speaker_segments( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return array();
		}

		$parts = preg_split( '/[;|]/', $value );
		$names = array();

		foreach ( (array) $parts as $part ) {
			$names[] = sanitize_text_field( trim( (string) $part ) );
		}

		return $names;
	}

	/**
	 * Split a speaker_ids cell into ordered post IDs.
	 *
	 * Empty segments become 0 (create or reuse by name).
	 *
	 * @param string $value Raw speaker IDs cell.
	 * @return int[]|\WP_Error
	 */
	private static function parse_speaker_id_segments( $value ) {
		$parts = preg_split( '/[;|]/', $value );
		$ids   = array();

		foreach ( (array) $parts as $part ) {
			$part = trim( (string) $part );
			if ( '' === $part ) {
				$ids[] = 0;
				continue;
			}

			$id = self::parse_post_id( $part );
			if ( $id < 1 ) {
				return new \WP_Error( 'aes_csv_speaker_ids', 'invalid' );
			}

			$ids[] = $id;
		}

		return $ids;
	}

	/**
	 * Parse a post ID cell.
	 *
	 * @param string $value Raw ID cell.
	 * @return int 0 when empty, -1 when invalid, otherwise a positive ID.
	 */
	private static function parse_post_id( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}

		if ( ! ctype_digit( $value ) || (int) $value < 1 ) {
			return -1;
		}

		return (int) $value;
	}

	/**
	 * Validate time-block titles and session/space conflicts.
	 *
	 * @param array<int, array<string, mixed>> $rows Parsed rows.
	 * @return string[]
	 */
	private static function validate_schedule( $rows ) {
		$errors         = array();
		$block_title    = array();
		$occupied       = array();
		$block_by_id    = array();
		$slot_block_id  = array();
		$space_by_id    = array();
		$session_ids    = array();
		$speaker_by_id  = array();

		foreach ( $rows as $row ) {
			$key = $row['block_key'];
			if ( isset( $block_title[ $key ] ) && $row['block_title'] && $block_title[ $key ] && $block_title[ $key ] !== $row['block_title'] ) {
				$errors[] = sprintf(
					/* translators: 1: date, 2: start time, 3: end time */
					__( 'Time block %1$s %2$s–%3$s has more than one title.', 'acf-event-schedule' ),
					$row['date'],
					$row['start'],
					$row['end']
				);
			} elseif ( $row['block_title'] ) {
				$block_title[ $key ] = $row['block_title'];
			}

			if ( $row['time_block_id'] ) {
				$id = $row['time_block_id'];
				if ( isset( $block_by_id[ $id ] ) ) {
					$prev = $block_by_id[ $id ];
					if ( $prev['date'] !== $row['date'] || $prev['start'] !== $row['start'] || $prev['end'] !== $row['end'] ) {
						$errors[] = sprintf(
							/* translators: %s: time block ID */
							__( 'Time block ID %s is used with more than one date or time.', 'acf-event-schedule' ),
							$id
						);
					} elseif ( $prev['title'] && $row['block_title'] && $prev['title'] !== $row['block_title'] ) {
						$errors[] = sprintf(
							/* translators: %s: time block ID */
							__( 'Time block ID %s has more than one title.', 'acf-event-schedule' ),
							$id
						);
					}
				} else {
					$block_by_id[ $id ] = array(
						'date'  => $row['date'],
						'start' => $row['start'],
						'end'   => $row['end'],
						'title' => $row['block_title'],
					);
				}

				if ( isset( $slot_block_id[ $key ] ) && $slot_block_id[ $key ] !== $id ) {
					$errors[] = sprintf(
						/* translators: 1: date, 2: start time, 3: end time */
						__( 'Time block %1$s %2$s–%3$s has more than one ID.', 'acf-event-schedule' ),
						$row['date'],
						$row['start'],
						$row['end']
					);
				} else {
					$slot_block_id[ $key ] = $id;
				}
			}

			if ( $row['space_id'] && Helpers::SPACE_ALL !== $row['space_key'] && '' !== $row['space_key'] ) {
				$space_id = $row['space_id'];
				if ( isset( $space_by_id[ $space_id ] ) && $space_by_id[ $space_id ] !== $row['space'] ) {
					$errors[] = sprintf(
						/* translators: %s: space ID */
						__( 'Space ID %s is used with more than one name.', 'acf-event-schedule' ),
						$space_id
					);
				} else {
					$space_by_id[ $space_id ] = $row['space'];
				}
			}

			if ( $row['session_id'] ) {
				$session_id = (int) $row['session_id'];
				if ( isset( $session_ids[ $session_id ] ) ) {
					$errors[] = sprintf(
						/* translators: %d: session post ID */
						__( 'Session ID %d appears more than once.', 'acf-event-schedule' ),
						$session_id
					);
				} else {
					$session_ids[ $session_id ] = $row['session_title'];
				}
			}

			foreach ( $row['speakers'] as $i => $speaker_name ) {
				$speaker_id = isset( $row['speaker_ids'][ $i ] ) ? (int) $row['speaker_ids'][ $i ] : 0;
				if ( ! $speaker_id ) {
					continue;
				}
				if ( isset( $speaker_by_id[ $speaker_id ] ) && strcasecmp( $speaker_by_id[ $speaker_id ], $speaker_name ) !== 0 ) {
					$errors[] = sprintf(
						/* translators: %d: speaker post ID */
						__( 'Speaker ID %d is used with more than one name.', 'acf-event-schedule' ),
						$speaker_id
					);
				} else {
					$speaker_by_id[ $speaker_id ] = $speaker_name;
				}
			}

			if ( '' === $row['session_title'] ) {
				continue;
			}

			$is_all = Helpers::SPACE_ALL === $row['space_key'];
			$slot   = $key . '|' . $row['space_key'];

			if ( $is_all ) {
				foreach ( $occupied as $taken_slot => $title ) {
					if ( 0 !== strpos( $taken_slot, $key . '|' ) || $title === $row['session_title'] ) {
						continue;
					}

					$errors[] = sprintf(
						/* translators: 1: date, 2: start time */
						__( 'An All-spaces session conflicts with another session at %1$s %2$s.', 'acf-event-schedule' ),
						$row['date'],
						$row['start']
					);
					break;
				}
			} elseif ( isset( $occupied[ $key . '|' . Helpers::SPACE_ALL ] ) && $occupied[ $key . '|' . Helpers::SPACE_ALL ] !== $row['session_title'] ) {
				$errors[] = sprintf(
					/* translators: 1: date, 2: start time */
					__( 'An All-spaces session already occupies %1$s %2$s.', 'acf-event-schedule' ),
					$row['date'],
					$row['start']
				);
			}

			if ( isset( $occupied[ $slot ] ) && $occupied[ $slot ] !== $row['session_title'] ) {
				$errors[] = sprintf(
					/* translators: 1: space name, 2: date, 3: start time */
					__( 'Space “%1$s” already has a session at %2$s %3$s.', 'acf-event-schedule' ),
					$row['space'],
					$row['date'],
					$row['start']
				);
			} else {
				$occupied[ $slot ] = $row['session_title'];
			}
		}

		return array_values( array_unique( $errors ) );
	}

	/**
	 * Merge imported dates, time blocks, and spaces onto the event.
	 *
	 * @param int                              $event_id Event post ID.
	 * @param array<int, array<string, mixed>> $rows     Parsed rows.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function sync_event_structure( $event_id, $rows ) {
		$dates   = self::existing_dates( $event_id );
		$summary = array(
			'dates_added'  => 0,
			'blocks_added' => 0,
			'spaces_added' => 0,
			'block_ids'    => array(),
			'space_ids'    => array(),
		);

		$blocks_by_id = array();
		foreach ( $dates as $date => $day ) {
			foreach ( $day['blocks'] as $inner => $block ) {
				$blocks_by_id[ $block['id'] ] = array(
					'date'  => $date,
					'inner' => $inner,
				);
				$summary['block_ids'][ $date . '|' . $block['start'] . '|' . $block['end'] ] = $block['id'];
			}
		}

		$space_list     = array();
		$spaces_by_id   = array();
		$spaces_by_name = array();
		foreach ( Helpers::get_spaces( $event_id ) as $space ) {
			$name = trim( $space['name'] );
			if ( '' === $name ) {
				continue;
			}

			$index        = count( $space_list );
			$space_list[] = array(
				'id'   => $space['id'],
				'name' => $name,
			);
			$spaces_by_id[ $space['id'] ]         = $index;
			$spaces_by_name[ strtolower( $name ) ] = $space['id'];
			$summary['space_ids'][ strtolower( $name ) ] = $space['id'];
		}

		foreach ( $rows as $row ) {
			if ( ! $row['time_block_id'] ) {
				continue;
			}

			$id  = $row['time_block_id'];
			$loc = $blocks_by_id[ $id ];
			$old_date  = $loc['date'];
			$old_inner = $loc['inner'];
			$block     = $dates[ $old_date ]['blocks'][ $old_inner ];
			$old_key   = $old_date . '|' . $block['start'] . '|' . $block['end'];
			$new_title = '' !== $row['block_title'] ? $row['block_title'] : $block['title'];
			$new_inner = $row['start'] . '|' . $row['end'];
			$same_slot = ( $row['date'] === $old_date && $new_inner === $old_inner );

			if ( $same_slot ) {
				if ( $new_title !== $block['title'] ) {
					$dates[ $old_date ]['blocks'][ $old_inner ]['title'] = $new_title;
				}
				$summary['block_ids'][ $row['block_key'] ] = $id;
				continue;
			}

			if ( isset( $dates[ $row['date'] ]['blocks'][ $new_inner ] ) && $dates[ $row['date'] ]['blocks'][ $new_inner ]['id'] !== $id ) {
				return new \WP_Error(
					'aes_csv_block',
					sprintf(
						/* translators: 1: date, 2: start time, 3: end time */
						__( 'Time block %1$s %2$s–%3$s already exists on this event.', 'acf-event-schedule' ),
						$row['date'],
						$row['start'],
						$row['end']
					)
				);
			}

			unset( $dates[ $old_date ]['blocks'][ $old_inner ] );
			unset( $summary['block_ids'][ $old_key ] );

			if ( ! isset( $dates[ $row['date'] ] ) ) {
				$dates[ $row['date'] ] = array(
					'date'   => $row['date'],
					'blocks' => array(),
				);
				++$summary['dates_added'];
			}

			$dates[ $row['date'] ]['blocks'][ $new_inner ] = array(
				'id'    => $id,
				'title' => $new_title,
				'start' => $row['start'],
				'end'   => $row['end'],
			);

			$blocks_by_id[ $id ] = array(
				'date'  => $row['date'],
				'inner' => $new_inner,
			);
			$summary['block_ids'][ $row['block_key'] ] = $id;
		}

		foreach ( $rows as $row ) {
			$date = $row['date'];
			if ( ! isset( $dates[ $date ] ) ) {
				$dates[ $date ] = array(
					'date'   => $date,
					'blocks' => array(),
				);
				++$summary['dates_added'];
			}

			if ( $row['time_block_id'] ) {
				$summary['block_ids'][ $row['block_key'] ] = $row['time_block_id'];
			} elseif ( ! isset( $summary['block_ids'][ $row['block_key'] ] ) ) {
				$block_id = Helpers::generate_id();
				$dates[ $date ]['blocks'][ $row['start'] . '|' . $row['end'] ] = array(
					'id'    => $block_id,
					'title' => $row['block_title'],
					'start' => $row['start'],
					'end'   => $row['end'],
				);
				$summary['block_ids'][ $row['block_key'] ] = $block_id;
				++$summary['blocks_added'];
			} elseif ( $row['block_title'] ) {
				$inner = $row['start'] . '|' . $row['end'];
				if ( isset( $dates[ $date ]['blocks'][ $inner ] ) && '' === $dates[ $date ]['blocks'][ $inner ]['title'] ) {
					$dates[ $date ]['blocks'][ $inner ]['title'] = $row['block_title'];
				}
			}

			if ( '' === $row['space_key'] ) {
				continue;
			}

			if ( Helpers::SPACE_ALL === $row['space_key'] ) {
				$summary['space_ids'][ Helpers::SPACE_ALL ] = Helpers::SPACE_ALL;
				continue;
			}

			if ( $row['space_id'] ) {
				$id      = $row['space_id'];
				$index   = $spaces_by_id[ $id ];
				$current = $space_list[ $index ];
				$new_key = $row['space_key'];
				$old_key = strtolower( $current['name'] );

				if ( $current['name'] !== $row['space'] ) {
					if ( isset( $spaces_by_name[ $new_key ] ) && $spaces_by_name[ $new_key ] !== $id ) {
						return new \WP_Error(
							'aes_csv_space',
							sprintf(
								/* translators: %s: space name */
								__( 'Space “%s” already exists on this event.', 'acf-event-schedule' ),
								$row['space']
							)
						);
					}

					$space_list[ $index ]['name'] = $row['space'];
					unset( $spaces_by_name[ $old_key ] );
					unset( $summary['space_ids'][ $old_key ] );
					$spaces_by_name[ $new_key ] = $id;
				}

				$summary['space_ids'][ $new_key ] = $id;
				continue;
			}

			if ( ! isset( $summary['space_ids'][ $row['space_key'] ] ) ) {
				$space_id = Helpers::generate_id();
				$index    = count( $space_list );
				$space_list[] = array(
					'id'   => $space_id,
					'name' => $row['space'],
				);
				$spaces_by_id[ $space_id ]           = $index;
				$spaces_by_name[ $row['space_key'] ] = $space_id;
				$summary['space_ids'][ $row['space_key'] ] = $space_id;
				++$summary['spaces_added'];
			}
		}

		ksort( $dates );

		$date_rows = array();
		foreach ( $dates as $day ) {
			if ( empty( $day['blocks'] ) ) {
				continue;
			}

			ksort( $day['blocks'] );
			$block_rows = array();
			foreach ( $day['blocks'] as $block ) {
				$block_rows[] = array(
					Field::KEY_TIME_BLOCK_ID    => $block['id'],
					Field::KEY_TIME_BLOCK_TITLE => $block['title'],
					Field::KEY_TIME_BLOCK_START => $block['start'],
					Field::KEY_TIME_BLOCK_END   => $block['end'],
				);
			}

			$date_rows[] = array(
				Field::KEY_EVENT_DATE  => $day['date'],
				Field::KEY_TIME_BLOCKS => $block_rows,
			);
		}

		$space_rows = array();
		foreach ( $space_list as $space ) {
			$space_rows[] = array(
				Field::KEY_SPACE_ID   => $space['id'],
				Field::KEY_SPACE_NAME => $space['name'],
			);
		}

		if ( $date_rows ) {
			delete_field( Field::KEY_EVENT_DATES, $event_id );
			update_field( Field::KEY_EVENT_DATES, $date_rows, $event_id );
		}

		if ( $space_rows ) {
			delete_field( Field::KEY_SPACES, $event_id );
			update_field( Field::KEY_SPACES, $space_rows, $event_id );
		}

		if ( $date_rows && ! get_field( Field::EVENT_DATES, $event_id, false ) ) {
			return new \WP_Error( 'aes_csv_dates', __( 'The event dates could not be saved.', 'acf-event-schedule' ) );
		}

		return $summary;
	}

	/**
	 * Reject IDs that do not belong to the selected event.
	 *
	 * @param int                              $event_id Event post ID.
	 * @param array<int, array<string, mixed>> $rows     Parsed rows.
	 * @return string[]
	 */
	private static function validate_ids_for_event( $event_id, $rows ) {
		$errors       = array();
		$known_blocks = array();
		$known_spaces = array();

		foreach ( self::existing_dates( $event_id ) as $day ) {
			foreach ( $day['blocks'] as $block ) {
				$known_blocks[ $block['id'] ] = true;
			}
		}

		foreach ( self::existing_spaces( $event_id ) as $space ) {
			$known_spaces[ $space['id'] ] = true;
		}

		$checked_blocks   = array();
		$checked_spaces   = array();
		$checked_sessions = array();
		$checked_speakers = array();

		foreach ( $rows as $row ) {
			if ( $row['time_block_id'] && ! isset( $checked_blocks[ $row['time_block_id'] ] ) ) {
				$checked_blocks[ $row['time_block_id'] ] = true;
				if ( ! isset( $known_blocks[ $row['time_block_id'] ] ) ) {
					$errors[] = sprintf(
						/* translators: 1: CSV line number, 2: time block ID */
						__( 'Row %1$d: time_block_id %2$s was not found on this event.', 'acf-event-schedule' ),
						$row['line'],
						$row['time_block_id']
					);
				}
			}

			if ( $row['space_id'] && Helpers::SPACE_ALL !== $row['space_key'] && '' !== $row['space_key'] && ! isset( $checked_spaces[ $row['space_id'] ] ) ) {
				$checked_spaces[ $row['space_id'] ] = true;
				if ( ! isset( $known_spaces[ $row['space_id'] ] ) ) {
					$errors[] = sprintf(
						/* translators: 1: CSV line number, 2: space ID */
						__( 'Row %1$d: space_id %2$s was not found on this event.', 'acf-event-schedule' ),
						$row['line'],
						$row['space_id']
					);
				}
			}

			$session_id = isset( $row['session_id'] ) ? (int) $row['session_id'] : 0;
			if ( $session_id && ! isset( $checked_sessions[ $session_id ] ) ) {
				$checked_sessions[ $session_id ] = true;
				$post = get_post( $session_id );
				$event_match = $post ? Helpers::normalize_id( get_field( Field::SESSION_EVENT, $session_id ) ) : 0;
				if ( ! $post || Session_Post_Type::SLUG !== $post->post_type || 'trash' === $post->post_status || (int) $event_match !== (int) $event_id ) {
					$errors[] = sprintf(
						/* translators: 1: CSV line number, 2: session post ID */
						__( 'Row %1$d: session_id %2$d was not found on this event.', 'acf-event-schedule' ),
						$row['line'],
						$session_id
					);
				}
			}

			foreach ( $row['speakers'] as $i => $speaker_name ) {
				$speaker_id = isset( $row['speaker_ids'][ $i ] ) ? (int) $row['speaker_ids'][ $i ] : 0;
				if ( ! $speaker_id || isset( $checked_speakers[ $speaker_id ] ) ) {
					continue;
				}

				$checked_speakers[ $speaker_id ] = true;
				$post = get_post( $speaker_id );
				$event_match = $post ? Helpers::normalize_id( get_field( Field::SPEAKER_EVENT, $speaker_id ) ) : 0;
				if ( ! $post || Speaker_Post_Type::SLUG !== $post->post_type || 'trash' === $post->post_status || (int) $event_match !== (int) $event_id ) {
					$errors[] = sprintf(
						/* translators: 1: CSV line number, 2: speaker post ID */
						__( 'Row %1$d: speaker_ids value %2$d was not found on this event.', 'acf-event-schedule' ),
						$row['line'],
						$speaker_id
					);
				}
			}
		}

		return $errors;
	}

	/**
	 * Existing event dates keyed by Y-m-d.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string, array<string, mixed>>
	 */
	private static function existing_dates( $event_id ) {
		$dates = array();

		foreach ( Helpers::get_time_blocks( $event_id ) as $block ) {
			$date  = Helpers::sanitize_date( $block['date'] ?? '' );
			$id    = isset( $block['id'] ) ? (string) $block['id'] : '';
			$start = Helpers::normalize_acf_time( $block['start'] ?? '' );
			$end   = Helpers::normalize_acf_time( $block['end'] ?? '' );
			if ( ! $date || ! $id || ! $start || ! $end ) {
				continue;
			}

			if ( ! isset( $dates[ $date ] ) ) {
				$dates[ $date ] = array(
					'date'   => $date,
					'blocks' => array(),
				);
			}

			$dates[ $date ]['blocks'][ $start . '|' . $end ] = array(
				'id'    => $id,
				'title' => isset( $block['title'] ) ? (string) $block['title'] : '',
				'start' => $start,
				'end'   => $end,
			);
		}

		return $dates;
	}

	/**
	 * Existing spaces keyed by lowercase name.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string, array{id:string,name:string}>
	 */
	private static function existing_spaces( $event_id ) {
		$spaces = array();

		foreach ( Helpers::get_spaces( $event_id ) as $space ) {
			$name = trim( $space['name'] );
			if ( '' === $name ) {
				continue;
			}
			$spaces[ strtolower( $name ) ] = array(
				'id'   => $space['id'],
				'name' => $name,
			);
		}

		return $spaces;
	}

	/**
	 * Find or create a speaker for the event.
	 *
	 * @param string             $title      Speaker post title.
	 * @param int                $event_id   Event post ID.
	 * @param array<string, int> $cache      Title and ID cache.
	 * @param int                $speaker_id Optional speaker post ID to update.
	 * @return array{id:int,created:bool,updated:bool}|\WP_Error
	 */
	private static function ensure_speaker( $title, $event_id, &$cache, $speaker_id = 0 ) {
		$speaker_id = absint( $speaker_id );
		if ( $speaker_id ) {
			$cache_key = 'id:' . $speaker_id;
			if ( isset( $cache[ $cache_key ] ) ) {
				return array(
					'id'      => $cache[ $cache_key ],
					'created' => false,
					'updated' => false,
				);
			}

			$updated = wp_update_post(
				array(
					'ID'         => $speaker_id,
					'post_title' => $title,
				),
				true
			);

			if ( is_wp_error( $updated ) ) {
				return $updated;
			}

			$names = self::split_speaker_name( $title );
			update_field( Field::KEY_SPEAKER_FIRST, $names['first'], $speaker_id );
			update_field( Field::KEY_SPEAKER_LAST, $names['last'], $speaker_id );

			$cache[ $cache_key ]         = $speaker_id;
			$cache[ strtolower( $title ) ] = $speaker_id;

			return array(
				'id'      => $speaker_id,
				'created' => false,
				'updated' => true,
			);
		}

		$key = strtolower( $title );
		if ( isset( $cache[ $key ] ) ) {
			return array(
				'id'      => $cache[ $key ],
				'created' => false,
				'updated' => false,
			);
		}

		$existing = self::find_speaker( $title, $event_id );
		if ( $existing ) {
			$cache[ $key ] = $existing;
			return array(
				'id'      => $existing,
				'created' => false,
				'updated' => false,
			);
		}

		$post_id = wp_insert_post(
			array_merge(
				array(
					'post_type'  => Speaker_Post_Type::SLUG,
					'post_title' => $title,
				),
				Status_Sync::insert_args_for_event( $event_id )
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$names = self::split_speaker_name( $title );
		update_field( Field::KEY_SPEAKER_FIRST, $names['first'], $post_id );
		update_field( Field::KEY_SPEAKER_LAST, $names['last'], $post_id );
		update_field( Field::KEY_SPEAKER_EVENT, $event_id, $post_id );

		$cache[ $key ] = (int) $post_id;

		return array(
			'id'      => (int) $post_id,
			'created' => true,
			'updated' => false,
		);
	}

	/**
	 * Find a speaker already assigned to the event.
	 *
	 * @param string $title    Speaker title.
	 * @param int    $event_id Event post ID.
	 * @return int
	 */
	private static function find_speaker( $title, $event_id ) {
		$query = new \WP_Query(
			array(
				'post_type'              => Speaker_Post_Type::SLUG,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => 50,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => Field::SPEAKER_EVENT,
						'value' => $event_id,
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			if ( strcasecmp( get_the_title( $post_id ), $title ) === 0 ) {
				return (int) $post_id;
			}
		}

		return 0;
	}

	/**
	 * Guess first and last names from a speaker title.
	 *
	 * @param string $title Speaker title.
	 * @return array{first:string,last:string}
	 */
	private static function split_speaker_name( $title ) {
		$base = $title;
		if ( false !== strpos( $title, ',' ) ) {
			$base = trim( (string) strstr( $title, ',', true ) );
		}

		$parts = preg_split( '/\s+/', $base );
		$first = $parts ? (string) array_shift( $parts ) : '';
		$last  = $parts ? implode( ' ', $parts ) : '';

		return array(
			'first' => $first,
			'last'  => $last,
		);
	}

	/**
	 * Find or create a session and assign relationships.
	 *
	 * @param int                  $event_id    Event post ID.
	 * @param array<string, mixed> $row         Parsed row.
	 * @param string               $block_id    Time block ID.
	 * @param string               $space_id    Space ID.
	 * @param int[]                $speaker_ids Speaker post IDs.
	 * @return array{id:int,created:bool,updated:bool}|\WP_Error
	 */
	private static function ensure_session( $event_id, $row, $block_id, $space_id, $speaker_ids ) {
		$session_id = isset( $row['session_id'] ) ? absint( $row['session_id'] ) : 0;
		$created    = false;
		$updated    = false;

		if ( $session_id ) {
			$post_id = wp_update_post(
				array(
					'ID'           => $session_id,
					'post_title'   => $row['session_title'],
					'post_content' => wp_kses_post( $row['session_content'] ),
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			$updated = true;
		} else {
			$existing = self::find_session( $event_id, $row['session_title'], $block_id, $space_id );

			if ( $existing ) {
				$post_id = $existing;
			} else {
				$post_id = wp_insert_post(
					array_merge(
						array(
							'post_type'    => Session_Post_Type::SLUG,
							'post_title'   => $row['session_title'],
							'post_content' => wp_kses_post( $row['session_content'] ),
						),
						Status_Sync::insert_args_for_event( $event_id )
					),
					true
				);

				if ( is_wp_error( $post_id ) ) {
					return $post_id;
				}

				$created = true;
			}
		}

		update_field( Field::KEY_SESSION_EVENT, $event_id, $post_id );
		update_field( Field::KEY_SESSION_TIME_BLOCK, $block_id, $post_id );
		update_field( Field::KEY_SESSION_SPACE, $space_id, $post_id );
		update_field( Field::KEY_SESSION_SPEAKERS, $speaker_ids, $post_id );

		return array(
			'id'      => (int) $post_id,
			'created' => $created,
			'updated' => $updated,
		);
	}

	/**
	 * Find a session already in this event slot.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $title    Session title.
	 * @param string $block_id Time block ID.
	 * @param string $space_id Space ID.
	 * @return int
	 */
	private static function find_session( $event_id, $title, $block_id, $space_id ) {
		$query = new \WP_Query(
			array(
				'post_type'              => Session_Post_Type::SLUG,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => 20,
				'fields'                 => 'ids',
				'title'                  => $title,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'   => Field::SESSION_EVENT,
						'value' => $event_id,
					),
					array(
						'key'   => Field::SESSION_TIME_BLOCK,
						'value' => $block_id,
					),
					array(
						'key'   => Field::SESSION_SPACE,
						'value' => $space_id,
					),
				),
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Human-readable import summary.
	 *
	 * @param array<string, int> $counts Import counts.
	 * @return string[]
	 */
	private static function summary_messages( $counts ) {
		$messages = array(
			__( 'Schedule imported.', 'acf-event-schedule' ),
		);

		$created_dates    = (int) $counts['dates'];
		$created_blocks   = (int) $counts['time_blocks'];
		$created_spaces   = (int) $counts['spaces'];
		$created_sessions = (int) $counts['sessions_created'];
		$created_speakers = (int) $counts['speakers_created'];

		if ( $created_dates || $created_blocks || $created_spaces || $created_sessions || $created_speakers ) {
			$messages[] = sprintf(
				/* translators: 1: dates created, 2: time blocks created, 3: spaces created, 4: sessions created, 5: speakers created */
				__( 'Created %1$d date(s), %2$d time block(s), %3$d space(s), %4$d session(s), and %5$d speaker(s).', 'acf-event-schedule' ),
				$created_dates,
				$created_blocks,
				$created_spaces,
				$created_sessions,
				$created_speakers
			);
		}

		$existing_sessions = (int) $counts['sessions_existing'];
		$existing_speakers = (int) $counts['speakers_existing'];

		if ( $existing_sessions && $existing_speakers ) {
			$messages[] = sprintf(
				/* translators: 1: existing sessions saved, 2: existing speakers saved */
				__( 'Saved %1$d existing session(s) and %2$d existing speaker(s).', 'acf-event-schedule' ),
				$existing_sessions,
				$existing_speakers
			);
		} elseif ( $existing_sessions ) {
			$messages[] = sprintf(
				/* translators: %d: existing sessions saved */
				__( 'Saved %d existing session(s).', 'acf-event-schedule' ),
				$existing_sessions
			);
		} elseif ( $existing_speakers ) {
			$messages[] = sprintf(
				/* translators: %d: existing speakers saved */
				__( 'Saved %d existing speaker(s).', 'acf-event-schedule' ),
				$existing_speakers
			);
		}

		return $messages;
	}

	/**
	 * Store a notice and return to settings.
	 *
	 * @param string   $type     success or error.
	 * @param string[] $messages Notice lines.
	 * @return void
	 */
	private static function redirect_with_notice( $type, $messages ) {
		$lines = array();
		foreach ( (array) $messages as $message ) {
			foreach ( preg_split( '/\R/', (string) $message ) as $line ) {
				$line = trim( (string) $line );
				if ( '' !== $line ) {
					$lines[] = $line;
				}
			}
		}

		set_transient(
			self::notice_key(),
			array(
				'type'     => $type,
				'messages' => $lines,
			),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Transient key for the current user.
	 *
	 * @return string
	 */
	private static function notice_key() {
		return self::NOTICE_KEY . get_current_user_id();
	}

	/**
	 * Build a WP_Error with one message per line.
	 *
	 * @param string[] $messages Error messages.
	 * @return \WP_Error
	 */
	private static function error_from_messages( $messages ) {
		$error = new \WP_Error();
		foreach ( $messages as $message ) {
			$error->add( 'aes_csv', $message );
		}
		return $error;
	}
}

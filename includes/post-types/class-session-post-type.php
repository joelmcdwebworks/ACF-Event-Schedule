<?php
/**
 * Session custom post type.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the session post type.
 */
class Session_Post_Type {

	const SLUG = 'session';

	/**
	 * Hook post type registration and admin columns.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'manage_' . self::SLUG . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . self::SLUG . '_posts_custom_column', array( self::class, 'column_content' ), 10, 2 );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register() {
		$labels = array(
			'name'                     => _x( 'Sessions', 'post type general name', 'acf-event-schedule' ),
			'singular_name'            => _x( 'Session', 'post type singular name', 'acf-event-schedule' ),
			'menu_name'                => _x( 'Sessions', 'admin menu', 'acf-event-schedule' ),
			'name_admin_bar'           => _x( 'Session', 'add new on admin bar', 'acf-event-schedule' ),
			'add_new'                  => _x( 'Add New', 'session', 'acf-event-schedule' ),
			'add_new_item'             => __( 'Add New Session', 'acf-event-schedule' ),
			'new_item'                 => __( 'New Session', 'acf-event-schedule' ),
			'edit_item'                => __( 'Edit Session', 'acf-event-schedule' ),
			'view_item'                => __( 'View Session', 'acf-event-schedule' ),
			'all_items'                => __( 'All Sessions', 'acf-event-schedule' ),
			'search_items'             => __( 'Search Sessions', 'acf-event-schedule' ),
			'parent_item_colon'        => __( 'Parent Sessions:', 'acf-event-schedule' ),
			'not_found'                => __( 'No sessions found.', 'acf-event-schedule' ),
			'not_found_in_trash'       => __( 'No sessions found in Trash.', 'acf-event-schedule' ),
			'archives'                 => __( 'Session Archives', 'acf-event-schedule' ),
			'attributes'               => __( 'Session Attributes', 'acf-event-schedule' ),
			'insert_into_item'         => __( 'Insert into session', 'acf-event-schedule' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this session', 'acf-event-schedule' ),
			'filter_items_list'        => __( 'Filter sessions list', 'acf-event-schedule' ),
			'items_list_navigation'    => __( 'Sessions list navigation', 'acf-event-schedule' ),
			'items_list'               => __( 'Sessions list', 'acf-event-schedule' ),
			'item_published'           => __( 'Session published.', 'acf-event-schedule' ),
			'item_published_privately' => __( 'Session published privately.', 'acf-event-schedule' ),
			'item_reverted_to_draft'   => __( 'Session reverted to draft.', 'acf-event-schedule' ),
			'item_scheduled'           => __( 'Session scheduled.', 'acf-event-schedule' ),
			'item_updated'             => __( 'Session updated.', 'acf-event-schedule' ),
		);

		register_post_type(
			self::SLUG,
			array(
				'labels'              => $labels,
				'public'              => true,
				'has_archive'         => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-schedule',
				'menu_position'       => 21,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'rewrite'             => array(
					'slug' => apply_filters( 'aes_session_rewrite_slug', 'session' ),
				),
				'capability_type'     => 'post',
				'exclude_from_search' => false,
			)
		);
	}

	/**
	 * Add admin list columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['aes_event']      = __( 'Event', 'acf-event-schedule' );
				$new['aes_time_block'] = __( 'Time block', 'acf-event-schedule' );
				$new['aes_space']      = __( 'Space', 'acf-event-schedule' );
			}
		}

		return $new;
	}

	/**
	 * Render admin list column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'aes_event' === $column ) {
			$event_id = Helpers::normalize_id( get_field( Field::SESSION_EVENT, $post_id ) );
			if ( $event_id ) {
				echo esc_html( get_the_title( $event_id ) );
			} else {
				echo '—';
			}
			return;
		}

		if ( 'aes_time_block' === $column ) {
			$event_id = Helpers::normalize_id( get_field( Field::SESSION_EVENT, $post_id ) );
			$block_id = (string) get_field( Field::SESSION_TIME_BLOCK, $post_id );
			$label    = '';

			foreach ( Helpers::get_time_blocks( $event_id ) as $block ) {
				if ( $block['id'] === $block_id ) {
					$label = Helpers::format_time_block_label( $block );
					break;
				}
			}

			echo $label ? esc_html( $label ) : '—';
			return;
		}

		if ( 'aes_space' === $column ) {
			$event_id = Helpers::normalize_id( get_field( Field::SESSION_EVENT, $post_id ) );
			$space_id = (string) get_field( Field::SESSION_SPACE, $post_id );
			$label    = Helpers::get_space_name( $event_id, $space_id );

			echo $label ? esc_html( $label ) : '—';
		}
	}
}

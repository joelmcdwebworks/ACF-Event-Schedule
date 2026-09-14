<?php
/**
 * Speaker custom post type.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the speaker post type.
 */
class Speaker_Post_Type {

	const SLUG = 'speaker';

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
			'name'                     => _x( 'Speakers', 'post type general name', 'acf-event-schedule' ),
			'singular_name'            => _x( 'Speaker', 'post type singular name', 'acf-event-schedule' ),
			'menu_name'                => _x( 'Speakers', 'admin menu', 'acf-event-schedule' ),
			'name_admin_bar'           => _x( 'Speaker', 'add new on admin bar', 'acf-event-schedule' ),
			'add_new'                  => _x( 'Add New', 'speaker', 'acf-event-schedule' ),
			'add_new_item'             => __( 'Add New Speaker', 'acf-event-schedule' ),
			'new_item'                 => __( 'New Speaker', 'acf-event-schedule' ),
			'edit_item'                => __( 'Edit Speaker', 'acf-event-schedule' ),
			'view_item'                => __( 'View Speaker', 'acf-event-schedule' ),
			'all_items'                => __( 'All Speakers', 'acf-event-schedule' ),
			'search_items'             => __( 'Search Speakers', 'acf-event-schedule' ),
			'parent_item_colon'        => __( 'Parent Speakers:', 'acf-event-schedule' ),
			'not_found'                => __( 'No speakers found.', 'acf-event-schedule' ),
			'not_found_in_trash'       => __( 'No speakers found in Trash.', 'acf-event-schedule' ),
			'archives'                 => __( 'Speaker Archives', 'acf-event-schedule' ),
			'attributes'               => __( 'Speaker Attributes', 'acf-event-schedule' ),
			'insert_into_item'         => __( 'Insert into speaker', 'acf-event-schedule' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this speaker', 'acf-event-schedule' ),
			'filter_items_list'        => __( 'Filter speakers list', 'acf-event-schedule' ),
			'items_list_navigation'    => __( 'Speakers list navigation', 'acf-event-schedule' ),
			'items_list'               => __( 'Speakers list', 'acf-event-schedule' ),
			'item_published'           => __( 'Speaker published.', 'acf-event-schedule' ),
			'item_published_privately' => __( 'Speaker published privately.', 'acf-event-schedule' ),
			'item_reverted_to_draft'   => __( 'Speaker reverted to draft.', 'acf-event-schedule' ),
			'item_scheduled'           => __( 'Speaker scheduled.', 'acf-event-schedule' ),
			'item_updated'             => __( 'Speaker updated.', 'acf-event-schedule' ),
		);

		register_post_type(
			self::SLUG,
			array(
				'labels'              => $labels,
				'public'              => true,
				'has_archive'         => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-groups',
				'menu_position'       => 22,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'rewrite'             => array(
					'slug' => apply_filters( 'aes_speaker_rewrite_slug', 'speaker' ),
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
				$new['aes_first_name'] = __( 'First name', 'acf-event-schedule' );
				$new['aes_last_name']  = __( 'Last name', 'acf-event-schedule' );
				$new['aes_event']      = __( 'Event', 'acf-event-schedule' );
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
		if ( 'aes_first_name' === $column ) {
			$value = get_field( Field::SPEAKER_FIRST, $post_id );
			echo $value ? esc_html( $value ) : '—';
			return;
		}

		if ( 'aes_last_name' === $column ) {
			$value = get_field( Field::SPEAKER_LAST, $post_id );
			echo $value ? esc_html( $value ) : '—';
			return;
		}

		if ( 'aes_event' === $column ) {
			$event_id = Helpers::normalize_id( get_field( Field::SPEAKER_EVENT, $post_id ) );
			echo $event_id ? esc_html( get_the_title( $event_id ) ) : '—';
		}
	}
}

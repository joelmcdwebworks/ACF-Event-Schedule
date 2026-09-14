<?php
/**
 * Hidden event taxonomy mirrored from the ACF Event field.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Registers aes_linked_event and keeps terms in sync with aes_event.
 *
 * Editors pick the Event field only. The taxonomy is a read-only projection
 * for PHP templates, the Site Editor, and page builders.
 */
class Event_Taxonomy {

	const SLUG               = 'aes_linked_event';
	const REWRITE_SLUG       = 'aes-event';
	const REST_BASE          = 'aes-event';
	const TERM_META_EVENT_ID = 'aes_event_id';

	/**
	 * Hook registration, sync, and editor UI hiding.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'save_post', array( self::class, 'on_save_post' ), 20, 2 );
		add_action( 'before_delete_post', array( self::class, 'on_before_delete_post' ) );
		add_action( 'rest_after_insert_' . Session_Post_Type::SLUG, array( self::class, 'on_rest_after_insert' ), 20, 2 );
		add_action( 'rest_after_insert_' . Speaker_Post_Type::SLUG, array( self::class, 'on_rest_after_insert' ), 20, 2 );
		add_filter( 'rest_pre_dispatch', array( self::class, 'strip_linked_event_from_rest_request' ), 10, 3 );
		add_filter( 'acf/update_value/key=' . Field::KEY_SESSION_EVENT, array( self::class, 'on_update_event_field' ), 20, 3 );
		add_filter( 'acf/update_value/key=' . Field::KEY_SPEAKER_EVENT, array( self::class, 'on_update_event_field' ), 20, 3 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor' ) );
		add_filter( 'get_terms', array( self::class, 'filter_public_terms' ), 10, 4 );
		add_filter( 'rest_prepare_' . self::SLUG, array( self::class, 'filter_rest_prepare_term' ), 10, 3 );
		add_action( 'template_redirect', array( self::class, 'maybe_404_private_term' ) );
	}

	/**
	 * Register the taxonomy on sessions and speakers.
	 *
	 * @return void
	 */
	public static function register() {
		$labels = array(
			'name'                       => _x( 'Schedule Events', 'taxonomy general name', 'acf-event-schedule' ),
			'singular_name'              => _x( 'Schedule Event', 'taxonomy singular name', 'acf-event-schedule' ),
			'search_items'               => __( 'Search Schedule Events', 'acf-event-schedule' ),
			'popular_items'              => __( 'Popular Schedule Events', 'acf-event-schedule' ),
			'all_items'                  => __( 'All Schedule Events', 'acf-event-schedule' ),
			'edit_item'                  => __( 'Edit Schedule Event', 'acf-event-schedule' ),
			'view_item'                  => __( 'View Schedule Event', 'acf-event-schedule' ),
			'update_item'                => __( 'Update Schedule Event', 'acf-event-schedule' ),
			'add_new_item'               => __( 'Add New Schedule Event', 'acf-event-schedule' ),
			'new_item_name'              => __( 'New Schedule Event Name', 'acf-event-schedule' ),
			'separate_items_with_commas' => __( 'Separate schedule events with commas', 'acf-event-schedule' ),
			'add_or_remove_items'        => __( 'Add or remove schedule events', 'acf-event-schedule' ),
			'choose_from_most_used'      => __( 'Choose from the most used schedule events', 'acf-event-schedule' ),
			'not_found'                  => __( 'No schedule events found.', 'acf-event-schedule' ),
			'no_terms'                   => __( 'No schedule events', 'acf-event-schedule' ),
			'items_list_navigation'      => __( 'Schedule events list navigation', 'acf-event-schedule' ),
			'items_list'                 => __( 'Schedule events list', 'acf-event-schedule' ),
			'back_to_items'              => __( '&larr; Back to Schedule Events', 'acf-event-schedule' ),
		);

		register_taxonomy(
			self::SLUG,
			array( Session_Post_Type::SLUG, Speaker_Post_Type::SLUG ),
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'hierarchical'       => false,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_nav_menus'  => true,
				'show_in_rest'       => true,
				'show_tagcloud'      => false,
				'show_in_quick_edit' => false,
				'show_admin_column'  => false,
				'meta_box_cb'        => false,
				'query_var'          => true,
				'rewrite'            => array(
					'slug' => self::REWRITE_SLUG,
				),
				'rest_base'          => self::REST_BASE,
				'capabilities'       => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					// edit_posts so REST/Site Editor/Kadence can list the taxonomy
					// (context=edit hides taxonomies the user cannot assign).
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Create or update the term for an event post, then assign session/speaker terms.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function on_save_post( $post_id, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( Helpers::is_event_post_type( $post->post_type ) ) {
			self::ensure_term_for_event( (int) $post_id );
			return;
		}

		if ( self::is_linked_post_type( $post->post_type ) ) {
			self::sync_post_terms( (int) $post_id, self::event_id_from_post( (int) $post_id ) );
		}
	}

	/**
	 * Delete the mirrored term when its event is permanently deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_before_delete_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$type = get_post_type( $post_id );
		if ( self::is_linked_post_type( $type ) ) {
			return;
		}

		$term = self::get_term_for_event( $post_id );
		if ( $term ) {
			wp_delete_term( (int) $term->term_id, self::SLUG );
		}
	}

	/**
	 * Re-apply terms after a REST save so Gutenberg cannot leave them empty.
	 *
	 * @param \WP_Post         $post    Inserted or updated post.
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public static function on_rest_after_insert( $post, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		self::sync_post_terms( (int) $post->ID, self::event_id_from_post( (int) $post->ID ) );
	}

	/**
	 * Drop taxonomy terms from session/speaker REST writes so the block editor
	 * cannot desync the mirrored terms, even though assign_terms is edit_posts
	 * (required for Site Editor and page builders to list the taxonomy).
	 *
	 * @param mixed            $result  Response to replace, or null.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Current request.
	 * @return mixed
	 */
	public static function strip_linked_event_from_rest_request( $result, $server, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- REST filter signature.
		if ( ! $request instanceof \WP_REST_Request ) {
			return $result;
		}

		$route = $request->get_route();
		if ( ! preg_match( '#/wp/v2/(session|speaker)(/|$)#', $route ) ) {
			return $result;
		}

		foreach ( array( self::REST_BASE, self::SLUG ) as $key ) {
			if ( $request->offsetExists( $key ) ) {
				$request->offsetUnset( $key );
			}
		}

		return $result;
	}

	/**
	 * Sync terms when the ACF Event field is written (editor and importer).
	 *
	 * @param mixed $value   Field value.
	 * @param mixed $post_id ACF post ID.
	 * @param array $field   Field array.
	 * @return mixed
	 */
	public static function on_update_event_field( $value, $post_id, $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_numeric( $post_id ) ) {
			return $value;
		}

		self::sync_post_terms( absint( $post_id ), Helpers::normalize_id( $value ) );

		return $value;
	}

	/**
	 * Hide the Gutenberg taxonomy panel on session and speaker screens.
	 *
	 * @return void
	 */
	public static function enqueue_editor() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! self::is_linked_post_type( $screen->post_type ) ) {
			return;
		}

		wp_enqueue_script(
			'aes-editor',
			AES_PLUGIN_URL . 'assets/editor.js',
			array( 'wp-data', 'wp-dom-ready', 'wp-edit-post' ),
			AES_VERSION,
			true
		);

		wp_localize_script(
			'aes-editor',
			'aesEditor',
			array(
				'taxonomy' => self::SLUG,
			)
		);
	}

	/**
	 * Ensure a term exists for every event and assign sessions/speakers.
	 *
	 * @return void
	 */
	public static function backfill() {
		if ( ! taxonomy_exists( self::SLUG ) ) {
			return;
		}

		wp_defer_term_counting( true );

		$event_types = Helpers::get_event_post_types();
		if ( empty( $event_types ) ) {
			$event_types = array_keys( Helpers::available_event_post_types() );
		}

		if ( ! empty( $event_types ) ) {
			foreach ( self::query_post_ids( $event_types ) as $event_id ) {
				if ( 'auto-draft' === get_post_status( $event_id ) ) {
					continue;
				}
				self::ensure_term_for_event( $event_id );
			}
		}

		foreach ( array( Session_Post_Type::SLUG, Speaker_Post_Type::SLUG ) as $post_type ) {
			foreach ( self::query_post_ids( array( $post_type ) ) as $post_id ) {
				if ( 'auto-draft' === get_post_status( $post_id ) ) {
					continue;
				}
				self::sync_post_terms( $post_id, self::event_id_from_post( $post_id ) );
			}
		}

		wp_defer_term_counting( false );
	}

	/**
	 * Create or update the term that mirrors an event post.
	 *
	 * @param int $event_id Event post ID.
	 * @return int Term ID, or 0 on failure.
	 */
	public static function ensure_term_for_event( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id || ! taxonomy_exists( self::SLUG ) ) {
			return 0;
		}

		$post = get_post( $event_id );
		if ( ! $post instanceof \WP_Post || self::is_linked_post_type( $post->post_type ) ) {
			return 0;
		}

		$name = self::public_term_name( $post );
		$preferred_slug = self::public_term_slug( $post );

		$term = self::get_term_for_event( $event_id );
		if ( $term ) {
			$slug = self::unique_slug( $preferred_slug, $event_id, (int) $term->term_id );
			$args = array();

			if ( $term->name !== $name ) {
				$args['name'] = $name;
			}
			if ( $term->slug !== $slug ) {
				$args['slug'] = $slug;
			}

			if ( ! empty( $args ) ) {
				wp_update_term( (int) $term->term_id, self::SLUG, $args );
			}

			return (int) $term->term_id;
		}

		$result = wp_insert_term(
			$name,
			self::SLUG,
			array(
				'slug' => self::unique_slug( $preferred_slug, $event_id, 0 ),
			)
		);

		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				$existing_id = (int) $result->get_error_data();
				if ( $existing_id ) {
					update_term_meta( $existing_id, self::TERM_META_EVENT_ID, $event_id );
					return $existing_id;
				}
			}

			return 0;
		}

		$term_id = (int) $result['term_id'];
		update_term_meta( $term_id, self::TERM_META_EVENT_ID, $event_id );

		return $term_id;
	}

	/**
	 * Replace a session or speaker's terms to match an event, or clear them.
	 *
	 * @param int $post_id  Session or speaker ID.
	 * @param int $event_id Event post ID, or 0 to clear.
	 * @return void
	 */
	public static function sync_post_terms( $post_id, $event_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! taxonomy_exists( self::SLUG ) ) {
			return;
		}

		if ( ! self::is_linked_post_type( get_post_type( $post_id ) ) ) {
			return;
		}

		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			wp_set_object_terms( $post_id, array(), self::SLUG );
			return;
		}

		$term_id = self::ensure_term_for_event( $event_id );
		if ( ! $term_id ) {
			wp_set_object_terms( $post_id, array(), self::SLUG );
			return;
		}

		wp_set_object_terms( $post_id, array( $term_id ), self::SLUG );
	}

	/**
	 * Term that stores this event post ID, if any.
	 *
	 * @param int $event_id Event post ID.
	 * @return \WP_Term|null
	 */
	public static function get_term_for_event( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id || ! taxonomy_exists( self::SLUG ) ) {
			return null;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::SLUG,
				'hide_empty' => false,
				'number'     => 1,
				'meta_key'   => self::TERM_META_EVENT_ID,
				'meta_value' => $event_id,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return $terms[0];
	}

	/**
	 * Whether an event's title/slug may appear on public taxonomy terms.
	 *
	 * @param int $event_id Event post ID.
	 * @return bool
	 */
	public static function event_is_publicly_listed( $event_id ) {
		$post = get_post( absint( $event_id ) );
		return $post instanceof \WP_Post && is_post_publicly_viewable( $post );
	}

	/**
	 * Term name: real title when the event is public, otherwise an opaque label.
	 *
	 * @param \WP_Post $post Event post.
	 * @return string
	 */
	private static function public_term_name( $post ) {
		if ( self::event_is_publicly_listed( $post->ID ) && $post->post_title ) {
			return $post->post_title;
		}

		return sprintf(
			/* translators: %d: event post ID */
			__( 'Event %d', 'acf-event-schedule' ),
			(int) $post->ID
		);
	}

	/**
	 * Term slug: event slug when public, otherwise event-{id}.
	 *
	 * @param \WP_Post $post Event post.
	 * @return string
	 */
	private static function public_term_slug( $post ) {
		if ( self::event_is_publicly_listed( $post->ID ) && $post->post_name ) {
			return $post->post_name;
		}

		return 'event-' . (int) $post->ID;
	}

	/**
	 * Whether the current user may see unpublished event terms.
	 *
	 * @return bool
	 */
	private static function user_can_see_private_terms() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Hide terms for unpublished events from public term lists.
	 *
	 * @param array<int, mixed> $terms      Terms.
	 * @param string[]          $taxonomies Taxonomies queried.
	 * @param array<string, mixed> $args    Query args.
	 * @param \WP_Term_Query    $term_query Query object.
	 * @return array<int, mixed>
	 */
	public static function filter_public_terms( $terms, $taxonomies, $args, $term_query ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::user_can_see_private_terms() || ! is_array( $terms ) ) {
			return $terms;
		}

		$taxonomies = (array) $taxonomies;
		if ( ! in_array( self::SLUG, $taxonomies, true ) ) {
			return $terms;
		}

		$filtered = array();

		foreach ( $terms as $term ) {
			$term_id = 0;
			$is_ours = false;

			if ( $term instanceof \WP_Term ) {
				$term_id = (int) $term->term_id;
				$is_ours = self::SLUG === $term->taxonomy;
			} elseif ( is_numeric( $term ) ) {
				$term_id = (int) $term;
				$is_ours = true;
			}

			if ( $is_ours && $term_id ) {
				$event_id = (int) get_term_meta( $term_id, self::TERM_META_EVENT_ID, true );
				if ( $event_id && ! self::event_is_publicly_listed( $event_id ) ) {
					continue;
				}
			}

			$filtered[] = $term;
		}

		return $filtered;
	}

	/**
	 * Hide a single unpublished-event term from public REST responses.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @param \WP_Term          $term     Term.
	 * @param \WP_REST_Request  $request  Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function filter_rest_prepare_term( $response, $term, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::user_can_see_private_terms() || ! $term instanceof \WP_Term ) {
			return $response;
		}

		$event_id = (int) get_term_meta( $term->term_id, self::TERM_META_EVENT_ID, true );
		if ( $event_id && ! self::event_is_publicly_listed( $event_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view this term.', 'acf-event-schedule' ),
				array( 'status' => 404 )
			);
		}

		return $response;
	}

	/**
	 * 404 taxonomy archives for unpublished events.
	 *
	 * @return void
	 */
	public static function maybe_404_private_term() {
		if ( ! is_tax( self::SLUG ) || self::user_can_see_private_terms() ) {
			return;
		}

		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$event_id = (int) get_term_meta( $term->term_id, self::TERM_META_EVENT_ID, true );
		if ( ! $event_id || self::event_is_publicly_listed( $event_id ) ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Whether the post type receives the mirrored taxonomy.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private static function is_linked_post_type( $post_type ) {
		return in_array( $post_type, array( Session_Post_Type::SLUG, Speaker_Post_Type::SLUG ), true );
	}

	/**
	 * Event ID stored on a session or speaker.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private static function event_id_from_post( $post_id ) {
		return Helpers::normalize_id( get_post_meta( $post_id, Field::SESSION_EVENT, true ) );
	}

	/**
	 * Slug from the event post name, uniqued with the event ID on collision.
	 *
	 * @param string $base            Preferred slug.
	 * @param int    $event_id        Event post ID.
	 * @param int    $exclude_term_id Term to ignore when checking collisions.
	 * @return string
	 */
	private static function unique_slug( $base, $event_id, $exclude_term_id ) {
		$slug = sanitize_title( $base );
		if ( '' === $slug ) {
			$slug = 'event-' . $event_id;
		}

		if ( ! self::slug_taken( $slug, $exclude_term_id ) ) {
			return $slug;
		}

		$candidate = $slug . '-' . $event_id;
		if ( ! self::slug_taken( $candidate, $exclude_term_id ) ) {
			return $candidate;
		}

		$i = 2;
		while ( self::slug_taken( $candidate . '-' . $i, $exclude_term_id ) ) {
			++$i;
		}

		return $candidate . '-' . $i;
	}

	/**
	 * Whether another term already uses this slug.
	 *
	 * @param string $slug            Term slug.
	 * @param int    $exclude_term_id Term to ignore.
	 * @return bool
	 */
	private static function slug_taken( $slug, $exclude_term_id ) {
		$existing = get_term_by( 'slug', $slug, self::SLUG );
		if ( ! $existing || is_wp_error( $existing ) ) {
			return false;
		}

		return (int) $existing->term_id !== (int) $exclude_term_id;
	}

	/**
	 * Post IDs for a backfill query.
	 *
	 * @param string[] $post_types Post type slugs.
	 * @return int[]
	 */
	private static function query_post_ids( $post_types ) {
		$query = new \WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array_map( 'absint', $query->posts );
	}
}

<?php
/**
 * Event post type allowlist settings.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Event Schedule.
 */
class Settings {

	const PAGE_SLUG = 'aes-event-schedule';
	const GROUP     = 'aes_event_schedule';

	/**
	 * Hook settings registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'register_page' ) );
		add_action( 'admin_init', array( self::class, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Add the settings page.
	 *
	 * @return void
	 */
	public static function register_page() {
		add_options_page(
			__( 'Event Schedule', 'acf-event-schedule' ),
			__( 'Event Schedule', 'acf-event-schedule' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	/**
	 * Register the allowlist option.
	 *
	 * @return void
	 */
	public static function register_setting() {
		register_setting(
			self::GROUP,
			Helpers::OPTION_EVENT_POST_TYPES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_post_types' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'aes_event_post_types_section',
			__( 'Event post types', 'acf-event-schedule' ),
			array( self::class, 'render_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'aes_event_post_types_field',
			__( 'Load schedule fields on', 'acf-event-schedule' ),
			array( self::class, 'render_field' ),
			self::PAGE_SLUG,
			'aes_event_post_types_section'
		);
	}

	/**
	 * Sanitize selected post types.
	 *
	 * WordPress sanitizes the option in both `update_option()` and `add_option()`.
	 * The first pass may convert a checkbox map (`event => 1`) into a list of slugs
	 * (`[0 => 'event']`). The second pass must accept that list without emptying it.
	 *
	 * @param mixed $value Submitted value.
	 * @return string[]
	 */
	public static function sanitize_post_types( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		unset( $value['__none__'] );

		$allowed = array_keys( Helpers::available_event_post_types() );
		$clean   = array();

		foreach ( $value as $key => $item ) {
			if ( is_int( $key ) || ( is_string( $key ) && ctype_digit( $key ) ) ) {
				$slug = sanitize_key( (string) $item );
			} else {
				if ( ! $item ) {
					continue;
				}
				$slug = sanitize_key( (string) $key );
			}

			if ( '' === $slug || '__none__' === $slug ) {
				continue;
			}

			if ( in_array( $slug, $allowed, true ) ) {
				$clean[] = $slug;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Section description.
	 *
	 * @return void
	 */
	public static function render_section() {
		echo '<p>';
		echo esc_html__( 'Choose which post types should have Dates, Time Blocks, and Spaces fields. Use this to attach schedules to Pie Calendar events or any other event post type.', 'acf-event-schedule' );
		echo '</p>';
	}

	/**
	 * Checkbox list of public post types.
	 *
	 * @return void
	 */
	public static function render_field() {
		$selected = Helpers::get_event_post_types();
		$types    = Helpers::available_event_post_types();

		echo '<input type="hidden" name="' . esc_attr( Helpers::OPTION_EVENT_POST_TYPES ) . '[]" value="" />';
		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Event post types', 'acf-event-schedule' ) . '</legend>';

		if ( empty( $types ) ) {
			echo '<p>' . esc_html__( 'No public post types are available.', 'acf-event-schedule' ) . '</p>';
			echo '</fieldset>';
			return;
		}

		foreach ( $types as $slug => $type ) {
			$checked = in_array( $slug, $selected, true );
			printf(
				'<label style="display:block;margin:0 0 8px;"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s <code>%2$s</code></label>',
				esc_attr( Helpers::OPTION_EVENT_POST_TYPES ),
				esc_attr( $slug ),
				checked( $checked, true, false ),
				esc_html( $type->labels->singular_name )
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Settings page markup.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<form action="options.php" method="post">';
		settings_fields( self::GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';
		Importer::render_section();
		Importer::render_export_section();
		echo '</div>';
	}

	/**
	 * Settings page styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'aes-admin',
			AES_PLUGIN_URL . 'assets/admin.css',
			array(),
			AES_VERSION
		);
	}
}

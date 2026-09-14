<?php
/**
 * PHP-registered ACF field groups.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Registers local ACF field groups.
 */
class Field_Groups {

	/**
	 * Hook field registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'acf/location/rule_types', array( self::class, 'location_rule_types' ) );
		add_filter( 'acf/location/rule_values/aes_event_schedule', array( self::class, 'location_rule_values' ) );
		add_filter( 'acf/location/rule_match/aes_event_schedule', array( self::class, 'location_rule_match' ), 10, 4 );
		add_action( 'acf/include_fields', array( self::class, 'register' ) );
	}

	/**
	 * Register all local field groups.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( self::event_group() );
		acf_add_local_field_group( self::session_group() );
		acf_add_local_field_group( self::speaker_group() );
	}

	/**
	 * Event post types for post object fields.
	 *
	 * @return string[]
	 */
	private static function event_post_types() {
		$types = Helpers::get_event_post_types();
		return ! empty( $types ) ? $types : array( 'post' );
	}

	/**
	 * Add a custom location type evaluated when a post is edited.
	 *
	 * @param array<string, array<string, string>> $choices Location rule types.
	 * @return array<string, array<string, string>>
	 */
	public static function location_rule_types( $choices ) {
		$choices[ __( 'Event Schedule', 'acf-event-schedule' ) ]['aes_event_schedule'] = __( 'Selected event post types', 'acf-event-schedule' );
		return $choices;
	}

	/**
	 * Values for the custom location rule.
	 *
	 * @return array<string, string>
	 */
	public static function location_rule_values() {
		return array(
			'1' => __( 'Selected event post types', 'acf-event-schedule' ),
		);
	}

	/**
	 * Match the current screen against the event post type allowlist.
	 *
	 * @param bool                 $result      Default match result.
	 * @param array<string, mixed> $rule        Location rule.
	 * @param array<string, mixed> $screen      Current ACF screen args.
	 * @param array<string, mixed> $field_group Field group.
	 * @return bool
	 */
	public static function location_rule_match( $result, $rule, $screen, $field_group = array() ) {
		unset( $result, $field_group );

		$post_type = isset( $screen['post_type'] ) ? (string) $screen['post_type'] : '';

		if ( ! $post_type && ! empty( $screen['post_id'] ) ) {
			$post_type = (string) get_post_type( $screen['post_id'] );
		}

		$matched = $post_type && Helpers::is_event_post_type( $post_type );

		if ( isset( $rule['operator'] ) && '!=' === $rule['operator'] ) {
			return ! $matched;
		}

		return $matched;
	}

	/**
	 * Location rules for selected event post types.
	 *
	 * @return array<int, array<int, array<string, string>>>
	 */
	private static function event_locations() {
		return array(
			array(
				array(
					'param'    => 'aes_event_schedule',
					'operator' => '==',
					'value'    => '1',
				),
			),
		);
	}

	/**
	 * Event schedule field group.
	 *
	 * @return array<string, mixed>
	 */
	private static function event_group() {
		return array(
			'key'                   => Field::GROUP_EVENT,
			'title'                 => __( 'Event Schedule', 'acf-event-schedule' ),
			'fields'                => array(
				array(
					'key'          => Field::KEY_EVENT_DATES,
					'label'        => __( 'Dates', 'acf-event-schedule' ),
					'name'         => Field::EVENT_DATES,
					'type'         => 'repeater',
					'instructions' => __( 'Add each event date, then define the time blocks for that day. Use a time block title for breaks or meals that should appear on the schedule without a session.', 'acf-event-schedule' ),
					'layout'       => 'block',
					'button_label' => __( 'Add Date', 'acf-event-schedule' ),
					'collapsed'    => Field::KEY_EVENT_DATE,
					'sub_fields'   => array(
						array(
							'key'            => Field::KEY_EVENT_DATE,
							'label'          => __( 'Date', 'acf-event-schedule' ),
							'name'           => Field::EVENT_DATE,
							'type'           => 'date_picker',
							'display_format' => 'F j, Y',
							'return_format'  => 'Y-m-d',
							'first_day'      => 1,
							'required'       => 1,
						),
						array(
							'key'          => Field::KEY_TIME_BLOCKS,
							'label'        => __( 'Time Blocks', 'acf-event-schedule' ),
							'name'         => Field::TIME_BLOCKS,
							'type'         => 'repeater',
							'layout'       => 'block',
							'button_label' => __( 'Add Time Block', 'acf-event-schedule' ),
							'collapsed'    => Field::KEY_TIME_BLOCK_TITLE,
							'sub_fields'   => array(
								array(
									'key'          => Field::KEY_TIME_BLOCK_ID,
									'label'        => __( 'Time Block ID', 'acf-event-schedule' ),
									'name'         => Field::TIME_BLOCK_ID,
									'type'         => 'text',
									'readonly'     => 1,
									'wrapper'      => array(
										'class' => 'aes-auto-id acf-hidden',
									),
								),
								array(
									'key'          => Field::KEY_TIME_BLOCK_TITLE,
									'label'        => __( 'Title', 'acf-event-schedule' ),
									'name'         => Field::TIME_BLOCK_TITLE,
									'type'         => 'text',
									'instructions' => __( 'Shown on the schedule when this time block has no sessions (for example, lunch or a break).', 'acf-event-schedule' ),
								),
								array(
									'key'            => Field::KEY_TIME_BLOCK_START,
									'label'          => __( 'Start Time', 'acf-event-schedule' ),
									'name'           => Field::TIME_BLOCK_START,
									'type'           => 'time_picker',
									'display_format' => 'g:i a',
									'return_format'  => 'H:i:s',
									'wrapper'        => array(
										'width' => '50',
									),
								),
								array(
									'key'            => Field::KEY_TIME_BLOCK_END,
									'label'          => __( 'End Time', 'acf-event-schedule' ),
									'name'           => Field::TIME_BLOCK_END,
									'type'           => 'time_picker',
									'display_format' => 'g:i a',
									'return_format'  => 'H:i:s',
									'wrapper'        => array(
										'width' => '50',
									),
								),
							),
						),
					),
				),
				array(
					'key'          => Field::KEY_SPACES,
					'label'        => __( 'Spaces', 'acf-event-schedule' ),
					'name'         => Field::SPACES,
					'type'         => 'repeater',
					'instructions' => __( 'Rooms or tracks used as columns on the schedule.', 'acf-event-schedule' ),
					'layout'       => 'table',
					'button_label' => __( 'Add Space', 'acf-event-schedule' ),
					'sub_fields'   => array(
						array(
							'key'          => Field::KEY_SPACE_ID,
							'label'        => __( 'Space ID', 'acf-event-schedule' ),
							'name'         => Field::SPACE_ID,
							'type'         => 'text',
							'instructions' => __( 'Generated automatically.', 'acf-event-schedule' ),
							'readonly'     => 1,
							'wrapper'      => array(
								'class' => 'aes-auto-id acf-hidden',
							),
						),
						array(
							'key'   => Field::KEY_SPACE_NAME,
							'label' => __( 'Space Name', 'acf-event-schedule' ),
							'name'  => Field::SPACE_NAME,
							'type'  => 'text',
						),
					),
				),
			),
			'location'              => self::event_locations(),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'show_in_rest'          => 0,
		);
	}

	/**
	 * Session field group.
	 *
	 * @return array<string, mixed>
	 */
	private static function session_group() {
		return array(
			'key'                   => Field::GROUP_SESSION,
			'title'                 => __( 'Session Details', 'acf-event-schedule' ),
			'fields'                => array(
				array(
					'key'           => Field::KEY_SESSION_EVENT,
					'label'         => __( 'Event', 'acf-event-schedule' ),
					'name'          => Field::SESSION_EVENT,
					'type'          => 'post_object',
					'instructions'  => __( 'The event this session belongs to.', 'acf-event-schedule' ),
					'post_type'     => self::event_post_types(),
					'return_format' => 'id',
					'ui'            => 1,
					'required'      => 1,
				),
				array(
					'key'           => Field::KEY_SESSION_TIME_BLOCK,
					'label'         => __( 'Time Block', 'acf-event-schedule' ),
					'name'          => Field::SESSION_TIME_BLOCK,
					'type'          => 'select',
					'instructions'  => __( 'Time blocks from the selected event.', 'acf-event-schedule' ),
					'choices'       => array(),
					'allow_null'    => 1,
					'ui'            => 0,
					'return_format' => 'value',
					'required'      => 1,
				),
				array(
					'key'           => Field::KEY_SESSION_SPACE,
					'label'         => __( 'Space', 'acf-event-schedule' ),
					'name'          => Field::SESSION_SPACE,
					'type'          => 'select',
					'instructions'  => __( 'Spaces from the selected event. Choose All for a session that spans every space in this time block.', 'acf-event-schedule' ),
					'choices'       => array(),
					'allow_null'    => 1,
					'ui'            => 0,
					'return_format' => 'value',
					'required'      => 1,
				),
				array(
					'key'                  => Field::KEY_SESSION_SPEAKERS,
					'label'                => __( 'Speakers', 'acf-event-schedule' ),
					'name'                 => Field::SESSION_SPEAKERS,
					'type'                 => 'relationship',
					'instructions'         => __( 'Speakers for this session. Only speakers assigned to the selected event are listed.', 'acf-event-schedule' ),
					'post_type'            => array( Speaker_Post_Type::SLUG ),
					'filters'              => array( 'search' ),
					'return_format'        => 'object',
					'bidirectional'        => 1,
					'bidirectional_target' => array( Field::KEY_SPEAKER_SESSIONS ),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => Session_Post_Type::SLUG,
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'show_in_rest'          => 0,
		);
	}

	/**
	 * Speaker field group.
	 *
	 * @return array<string, mixed>
	 */
	private static function speaker_group() {
		return array(
			'key'                   => Field::GROUP_SPEAKER,
			'title'                 => __( 'Speaker Details', 'acf-event-schedule' ),
			'fields'                => array(
				array(
					'key'   => Field::KEY_SPEAKER_FIRST,
					'label' => __( 'First Name', 'acf-event-schedule' ),
					'name'  => Field::SPEAKER_FIRST,
					'type'  => 'text',
					'wrapper' => array(
						'width' => '50',
					),
				),
				array(
					'key'   => Field::KEY_SPEAKER_LAST,
					'label' => __( 'Last Name', 'acf-event-schedule' ),
					'name'  => Field::SPEAKER_LAST,
					'type'  => 'text',
					'wrapper' => array(
						'width' => '50',
					),
				),
				array(
					'key'           => Field::KEY_SPEAKER_EVENT,
					'label'         => __( 'Event', 'acf-event-schedule' ),
					'name'          => Field::SPEAKER_EVENT,
					'type'          => 'post_object',
					'instructions'  => __( 'The event this speaker is appearing at.', 'acf-event-schedule' ),
					'post_type'     => self::event_post_types(),
					'return_format' => 'id',
					'ui'            => 1,
					'required'      => 0,
				),
				array(
					'key'                  => Field::KEY_SPEAKER_SESSIONS,
					'label'                => __( 'Sessions', 'acf-event-schedule' ),
					'name'                 => Field::SPEAKER_SESSIONS,
					'type'                 => 'relationship',
					'instructions'         => __( 'Sessions for the selected event. The list updates when the event changes.', 'acf-event-schedule' ),
					'post_type'            => array( Session_Post_Type::SLUG ),
					'filters'              => array( 'search' ),
					'return_format'        => 'object',
					'bidirectional'        => 1,
					'bidirectional_target' => array( Field::KEY_SESSION_SPEAKERS ),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => Speaker_Post_Type::SLUG,
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'show_in_rest'          => 0,
		);
	}
}

<?php
/**
 * Uninstall ACF Event Schedule.
 *
 * @package ACF_Event_Schedule
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'aes_event_post_types' );
delete_option( 'aes_schema_version' );
delete_option( 'aes_flush_rewrites' );

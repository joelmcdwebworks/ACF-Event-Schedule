<?php
/**
 * ACF field names and keys.
 *
 * @package ACF_Event_Schedule
 */

namespace ACF_Event_Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * Stable ACF identifiers.
 */
final class Field {

	public const GROUP_EVENT   = 'group_aes_event';
	public const GROUP_SESSION = 'group_aes_session';
	public const GROUP_SPEAKER = 'group_aes_speaker';

	public const EVENT_DATES       = 'aes_event_dates';
	public const EVENT_DATE        = 'aes_event_date';
	public const TIME_BLOCKS       = 'aes_time_blocks';
	public const TIME_BLOCK_ID     = 'aes_time_block_id';
	public const TIME_BLOCK_TITLE  = 'aes_time_block_title';
	public const TIME_BLOCK_DATE   = 'aes_time_block_date';
	public const TIME_BLOCK_START  = 'aes_time_block_start';
	public const TIME_BLOCK_END    = 'aes_time_block_end';
	public const SPACES            = 'aes_spaces';
	public const SPACE_ID          = 'aes_space_id';
	public const SPACE_NAME        = 'aes_space_name';
	public const EVENT_SPEAKERS    = 'aes_speakers';
	public const SESSION_EVENT     = 'aes_event';
	public const SESSION_TIME_BLOCK = 'aes_time_block';
	public const SESSION_SPACE     = 'aes_space';
	public const SESSION_SPEAKERS  = 'aes_speakers';
	public const SPEAKER_FIRST     = 'aes_first_name';
	public const SPEAKER_LAST      = 'aes_last_name';
	public const SPEAKER_EVENT     = 'aes_event';
	public const SPEAKER_SESSIONS  = 'aes_sessions';

	public const KEY_EVENT_DATES        = 'field_aes_event_dates';
	public const KEY_EVENT_DATE         = 'field_aes_event_date';
	public const KEY_TIME_BLOCKS        = 'field_aes_time_blocks';
	public const KEY_TIME_BLOCK_ID      = 'field_aes_time_block_id';
	public const KEY_TIME_BLOCK_TITLE   = 'field_aes_time_block_title';
	public const KEY_TIME_BLOCK_DATE    = 'field_aes_time_block_date';
	public const KEY_TIME_BLOCK_START   = 'field_aes_time_block_start';
	public const KEY_TIME_BLOCK_END     = 'field_aes_time_block_end';
	public const KEY_SPACES             = 'field_aes_spaces';
	public const KEY_SPACE_ID           = 'field_aes_space_id';
	public const KEY_SPACE_NAME         = 'field_aes_space_name';
	public const KEY_EVENT_SPEAKERS     = 'field_aes_event_speakers';
	public const KEY_SESSION_EVENT      = 'field_aes_session_event';
	public const KEY_SESSION_TIME_BLOCK = 'field_aes_session_time_block';
	public const KEY_SESSION_SPACE      = 'field_aes_session_space';
	public const KEY_SESSION_SPEAKERS   = 'field_aes_session_speakers';
	public const KEY_SPEAKER_FIRST      = 'field_aes_speaker_first_name';
	public const KEY_SPEAKER_LAST       = 'field_aes_speaker_last_name';
	public const KEY_SPEAKER_EVENT      = 'field_aes_speaker_event';
	public const KEY_SPEAKER_SESSIONS   = 'field_aes_speaker_sessions';
}

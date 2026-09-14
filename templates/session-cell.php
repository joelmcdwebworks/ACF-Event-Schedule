<?php
/**
 * Single session card.
 *
 * @package ACF_Event_Schedule
 *
 * @var array<string, mixed> $session Session data.
 */

defined( 'ABSPATH' ) || exit;

use ACF_Event_Schedule\Helpers;

$start_text = ! empty( $session['start'] ) ? Helpers::format_time_only( $session['date'], $session['start'] ) : '';
$end_text   = ! empty( $session['end'] ) ? Helpers::format_time_only( $session['date'], $session['end'] ) : '';
$start_dt   = ! empty( $session['start'] ) ? Helpers::datetime_attribute( $session['date'], $session['start'] ) : '';
$end_dt     = ! empty( $session['end'] ) ? Helpers::datetime_attribute( $session['date'], $session['end'] ) : '';
$space_name = isset( $session['space_name'] ) ? $session['space_name'] : '';
?>
<article class="aes-schedule__session">
	<h4 class="aes-schedule__session-title">
		<a href="<?php echo esc_url( $session['url'] ); ?>">
			<?php echo esc_html( $session['title'] ); ?>
		</a>
	</h4>

	<?php if ( $start_text ) : ?>
		<p class="aes-schedule__session-time">
			<time datetime="<?php echo esc_attr( $start_dt ); ?>"><?php echo esc_html( $start_text ); ?></time>
			<?php if ( $end_text ) : ?>
				<span aria-hidden="true"> – </span>
				<span class="aes-schedule__sr-only"><?php esc_html_e( 'to', 'acf-event-schedule' ); ?></span>
				<time datetime="<?php echo esc_attr( $end_dt ); ?>"><?php echo esc_html( $end_text ); ?></time>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $session['speakers'] ) ) : ?>
		<div class="aes-schedule__session-speakers">
			<p class="aes-schedule__sr-only"><?php esc_html_e( 'Speakers', 'acf-event-schedule' ); ?></p>
			<ul>
				<?php foreach ( $session['speakers'] as $index => $speaker ) : ?>
					<li>
						<a href="<?php echo esc_url( $speaker['url'] ); ?>"><?php echo esc_html( $speaker['title'] ); ?></a><?php echo ( $index < count( $session['speakers'] ) - 1 ) ? '<span aria-hidden="true">, </span>' : ''; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $space_name ) : ?>
		<dl class="aes-schedule__session-space">
			<dt class="aes-schedule__sr-only"><?php esc_html_e( 'Space', 'acf-event-schedule' ); ?></dt>
			<dd><?php echo esc_html( $space_name ); ?></dd>
		</dl>
	<?php endif; ?>
</article>

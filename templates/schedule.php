<?php
/**
 * Schedule grid template.
 *
 * @package ACF_Event_Schedule
 *
 * @var array<string, mixed> $schedule Schedule data from Schedule_Query.
 */

defined( 'ABSPATH' ) || exit;

use ACF_Event_Schedule\Helpers;
use ACF_Event_Schedule\Schedule_Renderer;

$spaces      = isset( $schedule['spaces'] ) ? $schedule['spaces'] : array();
$days        = isset( $schedule['days'] ) ? $schedule['days'] : array();
?>
<div class="aes-schedule">
	<?php foreach ( $days as $day ) : ?>
		<section class="aes-schedule__date-group">
			<?php
			$date_id = isset( $day['date'] ) ? Helpers::sanitize_date( $day['date'] ) : '';
			?>
			<h3 class="aes-schedule__date"<?php echo $date_id ? ' id="' . esc_attr( $date_id ) . '"' : ''; ?>>
				<?php echo esc_html( $day['date_label'] ); ?>
			</h3>

			<div
				class="aes-schedule__scroller"
				tabindex="0"
				role="region"
				aria-label="<?php echo esc_attr( sprintf( /* translators: %s: date */ __( 'Schedule for %s, scroll horizontally', 'acf-event-schedule' ), $day['date_label'] ) ); ?>"
			>
				<div class="aes-schedule__day" style="<?php echo esc_attr( $day['grid_style'] ); ?>">
					<span class="aes-schedule__column-header is-column-time" style="grid-column: 1; grid-row: 1;">
						<?php esc_html_e( 'Time', 'acf-event-schedule' ); ?>
					</span>

					<?php foreach ( $spaces as $space_index => $space ) : ?>
						<span class="aes-schedule__column-header" style="grid-column: <?php echo (int) ( $space_index + 2 ); ?>; grid-row: 1;">
							<?php echo esc_html( $space['name'] ); ?>
						</span>
					<?php endforeach; ?>

					<?php if ( empty( $spaces ) ) : ?>
						<span class="aes-schedule__column-header" style="grid-column: 2; grid-row: 1;">
							<?php esc_html_e( 'Schedule', 'acf-event-schedule' ); ?>
						</span>
					<?php endif; ?>

					<?php foreach ( $day['time_blocks'] as $block_index => $item ) : ?>
						<?php
						$block     = $item['block'];
						$datetime  = Helpers::datetime_attribute( $block['date'], $block['start'] );
						$time_text = Helpers::format_time_only( $block['date'], $block['start'] );
						$row       = (int) $block_index + 2;
						?>
						<p class="aes-schedule__time-slot" style="grid-column: 1; grid-row: <?php echo esc_attr( (string) $row ); ?>;">
							<?php if ( $datetime ) : ?>
								<time datetime="<?php echo esc_attr( $datetime ); ?>"><?php echo esc_html( $time_text ); ?></time>
							<?php else : ?>
								<?php echo esc_html( $time_text ); ?>
							<?php endif; ?>
						</p>

						<?php if ( ! empty( $item['is_break'] ) ) : ?>
							<div class="aes-schedule__cell aes-schedule__cell--span" style="grid-column: 2 / -1; grid-row: <?php echo esc_attr( (string) $row ); ?>;">
								<div class="aes-schedule__session aes-schedule__session--break">
									<p class="aes-schedule__session-title"><?php echo esc_html( $block['title'] ); ?></p>
									<?php
									$break_start_text = Helpers::format_time_only( $block['date'], $block['start'] );
									$break_end_text   = Helpers::format_time_only( $block['date'], $block['end'] );
									$break_end_dt     = Helpers::datetime_attribute( $block['date'], $block['end'] );
									if ( $break_start_text ) :
										?>
										<p class="aes-schedule__session-time">
											<time datetime="<?php echo esc_attr( $datetime ); ?>"><?php echo esc_html( $break_start_text ); ?></time>
											<?php if ( $break_end_text ) : ?>
												<span aria-hidden="true"> – </span>
												<span class="aes-schedule__sr-only"><?php esc_html_e( 'to', 'acf-event-schedule' ); ?></span>
												<time datetime="<?php echo esc_attr( $break_end_dt ); ?>"><?php echo esc_html( $break_end_text ); ?></time>
											<?php endif; ?>
										</p>
									<?php endif; ?>
								</div>
							</div>
						<?php elseif ( ! empty( $item['span_sessions'] ) ) : ?>
							<div class="aes-schedule__cell aes-schedule__cell--span aes-schedule__cell--all" style="grid-column: 2 / -1; grid-row: <?php echo esc_attr( (string) $row ); ?>;">
								<?php foreach ( $item['span_sessions'] as $session ) : ?>
									<?php
									$session['date']  = $block['date'];
									$session['start'] = $block['start'];
									$session['end']   = $block['end'];
									if ( empty( $session['space_name'] ) ) {
										$session['space_name'] = __( 'All', 'acf-event-schedule' );
									}
									Schedule_Renderer::session_cell( $session );
									?>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<?php foreach ( $spaces as $space_index => $space ) : ?>
								<?php
								$cell_sessions = isset( $item['sessions'][ $space['id'] ] ) ? $item['sessions'][ $space['id'] ] : array();
								$cell_class    = empty( $cell_sessions ) ? 'aes-schedule__cell aes-schedule__cell--empty' : 'aes-schedule__cell';
								$column        = (int) $space_index + 2;
								?>
								<div class="<?php echo esc_attr( $cell_class ); ?>" style="grid-column: <?php echo esc_attr( (string) $column ); ?>; grid-row: <?php echo esc_attr( (string) $row ); ?>;">
									<?php foreach ( $cell_sessions as $session ) : ?>
										<?php
										$session['date']  = $block['date'];
										$session['start'] = $block['start'];
										$session['end']   = $block['end'];
										Schedule_Renderer::session_cell( $session );
										?>
									<?php endforeach; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endforeach; ?>
</div>

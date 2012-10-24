<?php
/**
 * @for Week Grid Template
 * This file contains the hook logic required to create an effective week grid view.
 *
 * @package TribeEventsCalendarPro
 * @since  2.1
 * @author Modern Tribe Inc.
 *
 */
 
 // TODO: apply_filters('edit_post_link', '__return_null');

if ( !defined('ABSPATH') ) { die('-1'); }

if( !class_exists('Tribe_Events_Week_Template')){
	class Tribe_Events_Week_Template extends Tribe_Template_Factory {

		public static function init(){
			// Start list template
			add_filter( 'tribe_events_week_before_template', array( __CLASS__, 'before_template' ), 1, 1 );

			// List pagination
			add_filter( 'tribe_events_week_before_pagination', array( __CLASS__, 'before_pagination' ), 1, 1 );
			add_filter( 'tribe_events_week_the_header', array( __CLASS__, 'the_header' ), 1, 1 );
			add_filter( 'tribe_events_week_after_pagination', array( __CLASS__, 'after_pagination' ), 1, 1 );

			// Start list loop
			add_filter( 'tribe_events_week_before_loop', array( __CLASS__, 'before_loop' ), 1, 1 );
			add_filter( 'tribe_events_week_inside_before_loop', array( __CLASS__, 'inside_before_loop' ), 1, 1 );

			add_filter( 'tribe_events_week_the_grid', array( __CLASS__, 'the_grid' ), 1, 1 );
	
			// End list loop
			add_filter( 'tribe_events_week_inside_after_loop', array( __CLASS__, 'inside_after_loop' ), 1, 1 );
			add_filter( 'tribe_events_week_after_loop', array( __CLASS__, 'after_loop' ), 1, 1 );
	
				// End list template
			add_filter( 'tribe_events_week_after_template', array( __CLASS__, 'after_template' ), 1, 2 );
		}
		// Start List Template
		public function before_template( $post_id ){
			ob_start();
			// This title is here for ajax loading – do not remove if you want ajax switching between month views
			?>
			<div id="tribe-events-content" class="tribe-events-week-grid">
				<title><?php wp_title(); ?></title>
			<?php
			$html = ob_get_clean();
			return apply_filters('tribe_template_factory_debug', $html, 'tribe_events_week_before_template');
		}
		// Start List Loop
		public function before_loop( $post_id ){
			$html = '';
			return apply_filters('tribe_template_factory_debug', $html, 'tribe_events_week_before_loop');
		}
		public function inside_before_loop( $post_id ){
			$html = '';
			return apply_filters('tribe_template_factory_debug', $html, 'tribe_events_week_inside_before_loop');
		}

		public function the_grid() {

		// Tooltips (see how implemented in calendar.php, /hooks/calendar.php, public/template-tags/calendar.php)
		// jQuery bits for placing event height/top & all day centering (basically all styles that are hardcoded
		// in this view need to be done by jQuery)
		// Think about classes/design for recurring events like in prev/next week, etc, highlight today, etc
		// Thinking the "th" bookmarks could link to their dayview counterparts

			global $wp_query;
			$tribe_ecp = TribeEvents::instance();
			$start_of_week = tribe_get_first_week_day( $wp_query->get('start_date'));
			$week_length = 7; // days of the week
			$today = Date('Y-m-d',strtotime('today'));
			$events->all_day = array();
			$events->daily = array();
			$events->hours = array('start'=>null,'end'=>null);
			foreach($wp_query->posts as $event){
				if( $event->tribe_is_allday ){
					$events->all_day[] = $event;
				} else {
					$start_hour = Date('G',strtotime($event->EventStartDate));
					$end_hour = Date('G',strtotime($event->EventEndDate));
					if( is_null($events->hours['start']) || $start_hour < $events->hours['start'] ) {
						$events->hours['start'] = $start_hour;
					}
					if( is_null($events->hours['end']) || $end_hour > $events->hours['end'] ){
						$events->hours['end'] = $end_hour;
					}
					$events->daily[] = $event;
				}
			}

			ob_start();
?>
<div class="tribe-week-wrapper">
	<div class="tribe-grid-allday">
		<div scope="col" class="column tribe-grid-first"><span><?php _e('Hours', 'tribe-events-calendar-pro'); ?></span></div>
		<?php
			for( $n = 0; $n < $week_length; $n++ ) {
				$day = Date('Y-m-d', strtotime($start_of_week . " +$n days"));
				$header_class = ($day == $today) ? 'tribe-week-today' : '';
				printf('<div title="%s" scope="col" class="column %s"><a href="%s" rel="bookmark">%s</a></div>',
					$day,
					$header_class,
					trailingslashit( get_site_url() ) . trailingslashit( $tribe_ecp->rewriteSlug ) . trailingslashit( Date('Y-m-d', strtotime($start_of_week . " +$n days") ) ),
					Date('l jS', strtotime($start_of_week . " +$n days"))
					);
			}
		?>
	</div>
	<div class="tribe-grid-allday">
		<div scope="col" class="column tribe-grid-first"><span><?php _e('All Day', 'tribe-events-calendar-pro'); ?></span></div>
		<?php
			$placeholder = 0;
			for( $n = 0; $n < $week_length; $n++ ) {
				$day = Date('Y-m-d', strtotime($start_of_week . " +$n days"));
				$header_class = ($day == $today) ? 'tribe-week-today' : '';
				printf('<div title="%s" scope="col" class="column %s">',
					Date('Y-m-d', strtotime($start_of_week . " +$n days")),
					$header_class
					);
				if( $placeholder > 0 ) {
					for( $placeholder_i = 0; $placeholder_i <= $placeholder; $placeholder_i++ ) {
						echo '<div class="tribe-event-placeholder">placeholder</div>';
					}
				}
				foreach( $events->all_day as $event ){
					if( Date('Y-m-d',strtotime($event->EventStartDate)) == $day ){
						$span_class = '';
						$days_between = tribe_get_days_between($event->EventStartDate, $event->EventEndDate);
						if($days_between > 0) {
							$day_span_length = $days_between > ($week_length - $n) ? ($week_length - $n) : $days_between;
							$span_class = 'tribe-dayspan' . $day_span_length;
						}
						printf('<div class="%s"><h3 class="entry-title summary"><a href="%s" class="url" rel="bookmark">%s</a></h3></div>',
							'hentry vevent ' . $span_class,
							get_permalink( $event->ID ),
							$event->post_title
							);
					}
				}

				echo '</div>';
			}
		?>
	</div>
	<div class="tribe-grid-daily">
		<div class="column tribe-week-grid-hours">
			<?php 

			for( $hour = $events->hours['start']; $hour <= $events->hours['end']; $hour++ ) {
				printf( '<div>%s</div>', Date('gA',mktime($hour)) );
			}

			?>
		</div>
		<?php
			for( $n = 0; $n < $week_length; $n++ ) {
				$day = Date('Y-m-d', strtotime($start_of_week . " +$n days"));
				$header_class = ($day == $today) ? 'tribe-week-today' : '';
				printf('<div title="%s" scope="col" class="column %s">',
					Date('Y-m-d', strtotime($start_of_week . " +$n days")),
					$header_class
					);

				foreach( $events->daily as $event ){
					if( Date('Y-m-d',strtotime($event->EventStartDate)) == $day ){
						printf('<div class="hentry vevent" duration="%s"><h3 class="entry-title summary"><a href="%s" class="url" rel="bookmark">%s</a></h3></div>',
							($event->EventDuration / 60),
							get_permalink( $event->ID ),
							$event->post_title
							);
					}
				}

				echo '</div>';
			}
		?>
	</div>
</div>
	<table cellspacing="0" cellpadding="0" class="tribe-events-grid">
		<thead>
			<tr>
				<th scope="col" class="tribe-grid-first"><span><?php _e('Hours', 'tribe-events-calendar-pro'); ?></span></th>
				<?php
					for( $n = 0; $n < $week_length; $n++ ) {
						$header_class = (Date('Y-m-d',strtotime($start_of_week . " +$n days")) == $today) ? 'tribe-week-today' : '';
						printf('<th title="%s" scope="col" class="%s"><a href="%s" rel="bookmark">%s</a></th>',
							Date('Y-m-d', strtotime($start_of_week . " +$n days")),
							$header_class,
							trailingslashit( get_site_url() ) . trailingslashit( $tribe_ecp->rewriteSlug ) . trailingslashit( Date('Y-m-d', strtotime($start_of_week . " +$n days") ) ),
							Date('l jS', strtotime($start_of_week . " +$n days"))
							);
					}
				?>
			</tr>
		</thead>
		<tbody>
			<?php // our dummy row for all day events ?>
			<tr class="tribe-week-dummy-row" style="height: 72px;">
				<?php 
				
				// For our hours
				echo '<td></td>';
				
				for( $n = 0; $n < $week_length; $n++ ) {
					$header_class = (Date('Y-m-d',strtotime($start_of_week . " +$n days")) == $today) ? 'tribe-week-today' : '';
					printf( '<td class="%s"></td>', $header_class );
				}

				?>
			</tr><!-- .tribe-week-dummy-row -->
		
			<?php // our all day events row ?>
			<tr class="tribe-week-allday-row">
				<td class="tribe-week-allday-th"><div style="margin-top: -72px;">All Day</div></td>
				<td colspan="7">
					<div style="margin-top: -72px;">
						<table cellpadding="0" cellspacing="0">
							<tbody class="hfeed">
								<tr>
									<td colspan="2">
										<div class="hentry vevent"><h3 class="entry-title summary"><a href="" class="url" rel="bookmark">Multiday all day event</a></h3></div>
									</td>
									<td colspan="4">
										<div class="hentry vevent"><h3 class="entry-title summary"><a href="" class="entry-title summary url" rel="bookmark">Multiday all day event</a></h3></div>
									</td>
									<td>
										<div class="hentry vevent"><h3 class="entry-title summary"><a href="" class="entry-title summary url" rel="bookmark">All day event</a></h3></div>
									</td>
								</tr>
							</tbody><!-- .hfeed -->
						</table>
					</div>
				</td>
			</tr><!-- .tribe-week-allday-row -->
		
			<?php // our week grid ?>
			<tr class="tribe-week-grid-bgd">
				<td></td>
				<td colspan="7">
					<div class="tribe-week-grid-outer-wrap">
						<div class="tribe-week-grid-inner-wrap">
							<div class="tribe-week-grid-block"><div></div></div>
							<div class="tribe-week-grid-block"><div></div></div>
							<div class="tribe-week-grid-block"><div></div></div>
							<div class="tribe-week-grid-block"><div></div></div>
						</div><!-- .tribe-week-grid-inner-wrap -->
					</div><!-- .tribe-week-grid-outer-wrap -->
				</td>
			</tr><!-- .tribe-week-grid-bgd -->
			
			<?php // our actual week grid columns, hours, and events ?>
			<tr class="tribe-week-events-row hfeed">
				<td class="tribe-week-grid-hours">
					<div>7am</div>
					<div>8am</div>
					<div>9am</div>
					<div>10am</div>
				</td><!-- .tribe-week-grid-hours -->
				<td class="tribe-week-grid-col tribe-week-today"></td><!-- .tribe-week-grid-col -->
				<td class="tribe-week-grid-col">
					<div class="tribe-week-grid-col-inner-wrap">
						<div class="tribe-week-grid-event-wrap hentry vevent">
							<div id="tribe-events-event-1">
								<h3 class="entry-title summary"><a href="" class="url" rel="bookmark">MIT Theme Structure Long title</a></h3>
								
								
								<div id="tribe-events-tooltip-1" class="tribe-events-tooltip">
									<h4 class="entry-title summary">MIT Theme Structure Long title</h4>
									<div class="tribe-events-event-body">
										<div class="duration">
											<abbr class="tribe-events-abbr updated published dtstart" title="">
												Start Date 
											</abbr><!-- .dtstart -->
											<abbr class="tribe-events-abbr dtend" title="">
												End Date
											</abbr><!-- .dtend -->
										</div><!-- .duration -->
										<p class="entry-summary description">Test Description</p>
									</div><!-- .tribe-events-event-body -->
									<span class="tribe-events-arrow"></span>
								</div><!-- .tribe-events-tooltip -->
							
								
								
								
							</div>
						</div><!-- .tribe-week-grid-event-wrap -->
					</div><!-- .tribe-week-grid-col-inner-wrap -->
				</td><!-- .tribe-week-grid-col -->
				<td class="tribe-week-grid-col"></td><!-- .tribe-week-grid-col -->
				<td class="tribe-week-grid-col">
					<div class="tribe-week-grid-col-inner-wrap">
						<div class="tribe-week-grid-event-wrap hentry vevent">
							<div  id="tribe-events-event-2" style="height: 60px;">
								<h3 class="entry-title summary"><a href="" class="entry-title summary url" rel="bookmark">MIT Theme Structure Long title</a></h3>
							</div>
						</div><!-- .tribe-week-grid-event-wrap -->
						
						<div style="width: 90%; top: 28px; right: 0; left: auto;" class="tribe-week-grid-event-wrap tribe-event-overlapping tribe-event-o-1 hentry vevent">
							<div  id="tribe-events-event-3" style="height: 50px;">
								<h3 class="entry-title summary"><a href="" class="entry-title summary url" rel="bookmark">Short Title</a></h3>
							</div>
						</div><!-- .tribe-week-grid-event-wrap -->
						
						<div style="width: 80%; top: 48px; right: 0; left: auto;" class="tribe-week-grid-event-wrap tribe-event-overlapping tribe-event-o-2 hentry vevent">
							<div  id="tribe-events-event-4" style="height: 50px;">
								<h3 class="entry-title summary"><a href="" class="entry-title summary url" rel="bookmark">Short Title</a></h3>
							</div>
						</div><!-- .tribe-week-grid-event-wrap -->
					</div><!-- .tribe-week-grid-col-inner-wrap -->
				</td><!-- .tribe-week-grid-col -->
				<td class="tribe-week-grid-col"></td><!-- .tribe-week-grid-col -->
				<td class="tribe-week-grid-col"></td><!-- .tribe-week-grid-col -->
				<td class="tribe-week-grid-col">
					<div class="tribe-week-grid-col-inner-wrap">
						<div style="width: 80%;" class="tribe-week-grid-event-wrap tribe-event-same-time hentry vevent">
							<div  id="tribe-events-event-5" style="height: 60px;">
								<h3 class="entry-title summary"><a href="" class="entry-title summary url" rel="bookmark">MIT Theme Structure Long title</a></h3>
							</div>
						</div><!-- .tribe-week-grid-event-wrap -->
						
						<div style="width: 80%; right: 0; left: auto;" class="tribe-week-grid-event-wrap tribe-event-same-time tribe-event-st-1 hentry vevent">
							<div  id="tribe-events-event-6" style="height: 60px;">
								<h3 class="entry-title summary"><a href="" class="entry-title summary url" rel="bookmark">Short Title</a></h3>
							</div>
						</div><!-- .tribe-week-grid-event-wrap -->
					</div><!-- .tribe-week-grid-col-inner-wrap -->
				</td><!-- .tribe-week-grid-col -->
			</tr><!-- .tribe-week-events-row -->
					
		</tbody>
		
	</table><!-- .tribe-events-grid -->
		
    <?php 
			$html = ob_get_clean();
			return apply_filters('tribe_template_factory_debug', $html, 'tribe_events_week_the_grid');
		}

		// End List Loop
		public function inside_after_loop( $post_id ){
			$html = '';
			return apply_filters('tribe_template_factory_debug', $html, 'tribe_events_week_inside_after_loop');
		}
		public function after_loop( $post_id ){
			$html = '';
			return apply_filters('tribe_template_factory_debug', $html, 'tribe_events_week_after_loop');
		}

		public function before_header( $post_id ){
			$html = '';
			return apply_filters('tribe_template_factory_debug', $html, 'tribe_events_week_before_pagination');
		}
		public function the_header( $post_id ){
			global $wp_query;
			// echo '<pre>';
			// print_r($wp_query->posts);
			// echo '</pre>';
			$current_week = tribe_get_first_week_day( $wp_query->get('start_date') );
			ob_start();
			tribe_month_year_dropdowns( "tribe-events-" );
			$dropdown = ob_get_clean();

			// Display Week Navigation
			$html = sprintf('<div id="tribe-events-header"><h3 class="tribe-events-visuallyhidden">%s</h3><ul class="tribe-events-sub-nav"><li class="tribe-events-nav-prev"><a href="%s" rel="pref">%s</a></li><li>%s</li><li class="tribe-events-nav-next"><a href="%s" rel="next">%s</a><img src="%s" class="ajax-loading" id="ajax-loading" alt="Loading events" /></li></ul></div>',
								__( 'Week Navigation', 'tribe-events-calendar' ),
								tribe_get_last_week_permalink( $current_week ),
								'&#x2190;' . tribe_get_previous_month_text(),
								$dropdown,
								tribe_get_next_week_permalink( $current_week ),
								tribe_get_next_month_text()  . '&#x2192;',
								esc_url( admin_url( 'images/wpspin_light.gif' ) )
								);

			return apply_filters('tribe_template_factory_debug', $html, 'tribe_events_week_pagination');
		}
		public function after_header( $post_id ){
			$html = '';
			return apply_filters('tribe_template_factory_debug', $html, 'tribe_events_week_after_pagination');
		}
		// End List Template
		public function after_template( $post_id ){
			$html = '';

			// iCal import button
			if( function_exists( 'tribe_get_ical_link' ) ){
				$html .= sprintf('<a class="tribe-events-ical tribe-events-button-grey" title="%s" href="%s">%s</a>',
					esc_attr( 'iCal Import', 'tribe-events-calendar' ),
					tribe_get_ical_link(),
					__( 'iCal Import', 'tribe-events-calendar' )
					);
			}
			$html .= '</div><!-- #tribe-events-content -->';
			return apply_filters('tribe_template_factory_debug', $html, 'tribe_events_week_after_template');		
		}
	}
	Tribe_Events_Week_Template::init();
}
<?php
/**
 * Queue Filter Builder Class
 *
 * Converts filter configuration to WP_Query arguments
 *
 * @package     WP_HelpDesk
 * @subpackage  Features/Queue_Filters
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHD_Queue_Filter_Builder
 *
 * Builds WP_Query arguments from filter configuration.
 *
 * @since 1.0.0
 */
class WPHD_Queue_Filter_Builder {

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    WPHD_Queue_Filter_Builder
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @since  1.0.0
	 * @return WPHD_Queue_Filter_Builder
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Build WP_Query args from filter configuration.
	 *
	 * @since  1.0.0
	 * @param  array  $filter_config Decoded JSON configuration.
	 * @param  string $sort_field    Sort field.
	 * @param  string $sort_order    ASC or DESC.
	 * @return array  WP_Query arguments.
	 */
	public static function build_query_args( $filter_config, $sort_field = 'date', $sort_order = 'DESC' ) {
		$args = array(
			'post_type'      => 'wphd_ticket',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		// Apply organization-based visibility
		if ( class_exists( 'WPHD_Organization_Permissions' ) ) {
			$visible_ticket_ids = WPHD_Organization_Permissions::get_visible_ticket_ids();
			if ( 'all' !== $visible_ticket_ids && is_array( $visible_ticket_ids ) && ! empty( $visible_ticket_ids ) ) {
				$args['post__in'] = $visible_ticket_ids;
			}
		}

		$meta_query = array( 'relation' => 'AND' );

		// Status filter
		if ( ! empty( $filter_config['status'] ) && is_array( $filter_config['status'] ) ) {
			$meta_query[] = array(
				'key'     => '_wphd_status',
				'value'   => $filter_config['status'],
				'compare' => 'IN',
			);
		}

		// Priority filter
		if ( ! empty( $filter_config['priority'] ) && is_array( $filter_config['priority'] ) ) {
			$meta_query[] = array(
				'key'     => '_wphd_priority',
				'value'   => $filter_config['priority'],
				'compare' => 'IN',
			);
		}

		// Category filter
		if ( ! empty( $filter_config['category'] ) && is_array( $filter_config['category'] ) ) {
			$meta_query[] = array(
				'key'     => '_wphd_category',
				'value'   => $filter_config['category'],
				'compare' => 'IN',
			);
		}

		// Assignee filter
		if ( ! empty( $filter_config['assignee_type'] ) ) {
			switch ( $filter_config['assignee_type'] ) {
				case 'me':
					$meta_query[] = array(
						'key'   => '_wphd_assignee',
						'value' => get_current_user_id(),
					);
					break;
				case 'specific':
					if ( ! empty( $filter_config['assignee_ids'] ) && is_array( $filter_config['assignee_ids'] ) ) {
						$meta_query[] = array(
							'key'     => '_wphd_assignee',
							'value'   => $filter_config['assignee_ids'],
							'compare' => 'IN',
						);
					}
					break;
				case 'unassigned':
					$meta_query[] = array(
						'relation' => 'OR',
						array(
							'key'     => '_wphd_assignee',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'   => '_wphd_assignee',
							'value' => '',
						),
						array(
							'key'   => '_wphd_assignee',
							'value' => '0',
						),
					);
					break;
			}
		}

		// Reporter filter
		if ( ! empty( $filter_config['reporter_ids'] ) && is_array( $filter_config['reporter_ids'] ) ) {
			$args['author__in'] = $filter_config['reporter_ids'];
		}

		// Date filters
		if ( ! empty( $filter_config['date_created'] ) ) {
			$date_query = self::build_date_query( $filter_config['date_created'] );
			if ( $date_query ) {
				$args['date_query'] = $date_query;
			}
		}

		// Search phrase
		if ( ! empty( $filter_config['search_phrase'] ) ) {
			// Use wp_unslash to remove WordPress slashes, then basic sanitization
			$search = wp_unslash( $filter_config['search_phrase'] );
			// Remove any potentially dangerous characters while preserving search operators
			$search = preg_replace( '/[<>\{\}\[\]]/', '', $search );
			$args['s'] = trim( $search );
		}

		// Organization filter
		if ( ! empty( $filter_config['organization_ids'] ) && is_array( $filter_config['organization_ids'] ) && class_exists( 'WPHD_Organizations' ) ) {
			$org_user_ids = array();
			foreach ( $filter_config['organization_ids'] as $org_id ) {
				$org_users = WPHD_Organizations::get_organization_user_ids( $org_id );
				if ( is_array( $org_users ) ) {
					$org_user_ids = array_merge( $org_user_ids, $org_users );
				}
			}
			if ( ! empty( $org_user_ids ) ) {
				// Merge with existing author filter if present
				if ( isset( $args['author__in'] ) ) {
					$args['author__in'] = array_intersect( $args['author__in'], array_unique( $org_user_ids ) );
				} else {
					$args['author__in'] = array_unique( $org_user_ids );
				}
			}
		}

		// Apply meta query if we have conditions
		if ( ! empty( $meta_query ) && count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}

		// Sorting
		$args['orderby'] = self::get_orderby_field( $sort_field );
		$args['order']   = strtoupper( $sort_order ) === 'ASC' ? 'ASC' : 'DESC';

		return $args;
	}

	/**
	 * Build date query from filter config.
	 *
	 * @since  1.0.0
	 * @param  array $date_config Date configuration.
	 * @return array|null Date query array or null.
	 */
	private static function build_date_query( $date_config ) {
		if ( empty( $date_config['operator'] ) ) {
			return null;
		}

		$operator = $date_config['operator'];
		$date_query = array();

		switch ( $operator ) {
			case 'today':
				$date_query = array(
					'year'  => gmdate( 'Y' ),
					'month' => gmdate( 'm' ),
					'day'   => gmdate( 'd' ),
				);
				break;

			case 'yesterday':
				$yesterday = strtotime( '-1 day' );
				$date_query = array(
					'year'  => gmdate( 'Y', $yesterday ),
					'month' => gmdate( 'm', $yesterday ),
					'day'   => gmdate( 'd', $yesterday ),
				);
				break;

			case 'this_week':
				$week_start = strtotime( 'monday this week' );
				$date_query = array(
					'after' => gmdate( 'Y-m-d', $week_start ),
				);
				break;

			case 'last_week':
				$week_start = strtotime( 'monday last week' );
				$week_end   = strtotime( 'sunday last week' );
				$date_query = array(
					'after'  => gmdate( 'Y-m-d', $week_start ),
					'before' => gmdate( 'Y-m-d', $week_end ),
					'inclusive' => true,
				);
				break;

			case 'this_month':
				$date_query = array(
					'year'  => gmdate( 'Y' ),
					'month' => gmdate( 'm' ),
				);
				break;

			case 'last_month':
				$last_month = strtotime( '-1 month' );
				$date_query = array(
					'year'  => gmdate( 'Y', $last_month ),
					'month' => gmdate( 'm', $last_month ),
				);
				break;

			case 'this_year':
				$date_query = array(
					'year' => gmdate( 'Y' ),
				);
				break;

			case 'between':
				if ( ! empty( $date_config['start'] ) && ! empty( $date_config['end'] ) ) {
					$date_query = array(
						'after'     => sanitize_text_field( $date_config['start'] ),
						'before'    => sanitize_text_field( $date_config['end'] ),
						'inclusive' => true,
					);
				}
				break;

			case 'before':
				if ( ! empty( $date_config['start'] ) ) {
					$date_query = array(
						'before'    => sanitize_text_field( $date_config['start'] ),
						'inclusive' => true,
					);
				}
				break;

			case 'after':
				if ( ! empty( $date_config['start'] ) ) {
					$date_query = array(
						'after'     => sanitize_text_field( $date_config['start'] ),
						'inclusive' => true,
					);
				}
				break;
		}

		return ! empty( $date_query ) ? $date_query : null;
	}

	/**
	 * Get orderby field for WP_Query.
	 *
	 * @since  1.0.0
	 * @param  string $sort_field Sort field.
	 * @return string Orderby value for WP_Query.
	 */
	private static function get_orderby_field( $sort_field ) {
		$map = array(
			'date'     => 'date',
			'title'    => 'title',
			'modified' => 'modified',
			'author'   => 'author',
		);
		return isset( $map[ $sort_field ] ) ? $map[ $sort_field ] : 'date';
	}

	/**
	 * Get tickets using filter.
	 *
	 * @since  1.0.0
	 * @param  array  $filter_config Filter configuration.
	 * @param  string $sort_field    Sort field.
	 * @param  string $sort_order    Sort order.
	 * @param  int    $per_page      Posts per page (-1 for all).
	 * @param  int    $page          Page number.
	 * @return array  Array with tickets, total, and pages.
	 */
	public static function get_tickets( $filter_config, $sort_field = 'date', $sort_order = 'DESC', $per_page = -1, $page = 1 ) {
		$args                   = self::build_query_args( $filter_config, $sort_field, $sort_order );
		$args['posts_per_page'] = absint( $per_page );
		$args['paged']          = absint( $page );

		$query   = new WP_Query( $args );
		$tickets = $query->posts;

		// Post-filter for SLA if needed
		if ( ! empty( $filter_config['sla_first_response'] ) || ! empty( $filter_config['sla_resolution'] ) ) {
			$tickets = self::filter_by_sla( $tickets, $filter_config );
		}

		return array(
			'tickets' => $tickets,
			'total'   => $query->found_posts,
			'pages'   => $query->max_num_pages,
		);
	}

	/**
	 * Filter tickets by SLA status.
	 *
	 * @since  1.0.0
	 * @param  array $tickets        Array of WP_Post objects.
	 * @param  array $filter_config  Filter configuration.
	 * @return array Filtered tickets.
	 */
	private static function filter_by_sla( $tickets, $filter_config ) {
		global $wpdb;
		$sla_table = $wpdb->prefix . 'wphd_sla_log';

		$filtered = array();

		foreach ( $tickets as $ticket ) {
			$include = true;

			// Get SLA log for ticket
			$sla_log = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM $sla_table WHERE ticket_id = %d", $ticket->ID )
			);

			if ( ! $sla_log ) {
				// If no SLA log and filter is for 'none', include it
				if ( ( ! empty( $filter_config['sla_first_response'] ) && 'none' === $filter_config['sla_first_response'] ) ||
					 ( ! empty( $filter_config['sla_resolution'] ) && 'none' === $filter_config['sla_resolution'] ) ) {
					$include = true;
				} else {
					$include = false;
				}
			} else {
				// Check first response SLA
				if ( ! empty( $filter_config['sla_first_response'] ) ) {
					$fr_match = self::check_sla_match( $sla_log, 'first_response', $filter_config['sla_first_response'] );
					$include  = $include && $fr_match;
				}

				// Check resolution SLA
				if ( ! empty( $filter_config['sla_resolution'] ) ) {
					$res_match = self::check_sla_match( $sla_log, 'resolution', $filter_config['sla_resolution'] );
					$include   = $include && $res_match;
				}
			}

			if ( $include ) {
				$filtered[] = $ticket;
			}
		}

		return $filtered;
	}

	/**
	 * Check if SLA matches the filter criteria.
	 *
	 * @since  1.0.0
	 * @param  object $sla_log  SLA log object.
	 * @param  string $sla_type 'first_response' or 'resolution'.
	 * @param  string $criteria Filter criteria (met, breached, at_risk, any, none).
	 * @return bool
	 */
	private static function check_sla_match( $sla_log, $sla_type, $criteria ) {
		if ( 'any' === $criteria ) {
			return true;
		}

		if ( 'none' === $criteria ) {
			return false; // Already handled by caller if no SLA log
		}

		if ( 'first_response' === $sla_type ) {
			$breached_field = 'first_response_breached';
			$due_field      = 'first_response_due';
			$at_field       = 'first_response_at';
		} else {
			$breached_field = 'resolution_breached';
			$due_field      = 'resolution_due';
			$at_field       = 'resolved_at';
		}

		switch ( $criteria ) {
			case 'breached':
				return ! empty( $sla_log->$breached_field );

			case 'met':
				return empty( $sla_log->$breached_field ) && ! empty( $sla_log->$at_field );

			case 'at_risk':
				// At risk means: not breached, not met, and due date is close
				if ( ! empty( $sla_log->$breached_field ) || ! empty( $sla_log->$at_field ) ) {
					return false;
				}

				if ( empty( $sla_log->$due_field ) ) {
					return false;
				}

				$due_time = strtotime( $sla_log->$due_field );
				$now      = current_time( 'timestamp' );

				// At risk if within 1 hour of due time
				return ( $due_time - $now ) <= 3600 && ( $due_time - $now ) > 0;

			default:
				return false;
		}
	}
}

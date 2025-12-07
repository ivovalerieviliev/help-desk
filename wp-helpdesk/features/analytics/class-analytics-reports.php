<?php
/**
 * Analytics Reports Class
 *
 * Handles comprehensive report generation with user-specific views.
 *
 * @package     WP_HelpDesk
 * @subpackage  Analytics
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPHD_Analytics_Reports
 */
class WPHD_Analytics_Reports {

    /**
     * Instance of this class.
     *
     * @var WPHD_Analytics_Reports
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return WPHD_Analytics_Reports
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get comprehensive report data.
     *
     * @param array $filters Report filters.
     * @return array Report data.
     */
    public function get_report_data( $filters = array() ) {
        $defaults = array(
            'date_start'  => date( 'Y-m-d', strtotime( '-30 days' ) ),
            'date_end'    => date( 'Y-m-d' ),
            'status'      => '',
            'priority'    => '',
            'category'    => '',
            'assignee'    => '',
            'user_id'     => 0,
        );

        $filters = wp_parse_args( $filters, $defaults );

        $data = array(
            'summary' => $this->get_summary_statistics( $filters ),
            'tickets_over_time' => $this->get_tickets_over_time( $filters ),
            'tickets_by_status' => $this->get_tickets_by_status( $filters ),
            'tickets_by_priority' => $this->get_tickets_by_priority( $filters ),
            'tickets_by_category' => $this->get_tickets_by_category( $filters ),
            'agent_performance' => $this->get_agent_performance( $filters ),
            'resolution_time_trend' => $this->get_resolution_time_trend( $filters ),
            'ticket_details' => $this->get_ticket_details( $filters ),
            'sla_statistics' => $this->get_sla_statistics( $filters ),
            'comment_statistics' => $this->get_comment_statistics( $filters ),
        );

        // If user-specific view, add comparison data
        if ( ! empty( $filters['user_id'] ) ) {
            $data['user_comparison'] = $this->get_user_comparison( $filters['user_id'], $filters );
        }

        return $data;
    }

    /**
     * Get summary statistics.
     *
     * @param array $filters Filters.
     * @return array Statistics.
     */
    public function get_summary_statistics( $filters ) {
        global $wpdb;

        $where_clauses = $this->build_where_clauses( $filters );
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        // Total tickets
        $total_tickets = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket' 
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                {$where_sql}",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        // Get statuses
        $statuses = get_option( 'wphd_statuses', array() );
        $closed_statuses = array();
        foreach ( $statuses as $status ) {
            if ( ! empty( $status['is_closed'] ) ) {
                $closed_statuses[] = $status['slug'];
            }
        }

        // Open tickets (not in closed statuses)
        $open_tickets = $this->count_tickets_by_status_type( $filters, $closed_statuses, false );

        // Closed/Resolved tickets
        $closed_tickets = $this->count_tickets_by_status_type( $filters, $closed_statuses, true );

        // Average resolution time (in hours)
        $avg_resolution_time = $this->get_average_resolution_time( $filters );

        // SLA compliance rate
        $sla_compliance = $this->get_sla_compliance_rate( $filters );

        // Average first response time (in hours)
        $avg_first_response = $this->get_average_first_response_time( $filters );

        return array(
            'total_tickets' => intval( $total_tickets ),
            'open_tickets' => intval( $open_tickets ),
            'closed_tickets' => intval( $closed_tickets ),
            'avg_resolution_time' => round( $avg_resolution_time, 1 ),
            'sla_compliance_rate' => round( $sla_compliance, 1 ),
            'avg_first_response_time' => round( $avg_first_response, 1 ),
        );
    }

    /**
     * Get tickets over time.
     *
     * @param array $filters Filters.
     * @return array Time series data.
     */
    public function get_tickets_over_time( $filters ) {
        global $wpdb;

        $where_clauses = $this->build_where_clauses( $filters );
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        // Determine grouping based on date range
        $days_diff = ( strtotime( $filters['date_end'] ) - strtotime( $filters['date_start'] ) ) / DAY_IN_SECONDS;
        
        if ( $days_diff <= 7 ) {
            $group_by = "DATE(p.post_date)";
            $date_format = "%Y-%m-%d";
        } elseif ( $days_diff <= 90 ) {
            $group_by = "DATE(p.post_date)";
            $date_format = "%Y-%m-%d";
        } else {
            $group_by = "DATE_FORMAT(p.post_date, '%Y-%m')";
            $date_format = "%Y-%m";
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(p.post_date, %s) as date, COUNT(DISTINCT p.ID) as count
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                {$where_sql}
                GROUP BY {$group_by}
                ORDER BY p.post_date ASC",
                $date_format,
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        $data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => __( 'Tickets Created', 'wp-helpdesk' ),
                    'data' => array(),
                ),
            ),
        );

        foreach ( $results as $row ) {
            $data['labels'][] = $row->date;
            $data['datasets'][0]['data'][] = intval( $row->count );
        }

        return $data;
    }

    /**
     * Get tickets by status.
     *
     * @param array $filters Filters.
     * @return array Status breakdown.
     */
    public function get_tickets_by_status( $filters ) {
        global $wpdb;

        $where_clauses = $this->build_where_clauses( $filters, false ); // Don't include status in where
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value as status, COUNT(DISTINCT p.ID) as count
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wphd_status'
                LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                {$where_sql}
                GROUP BY pm.meta_value",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        $statuses = get_option( 'wphd_statuses', array() );
        $status_map = array();
        foreach ( $statuses as $status ) {
            $status_map[ $status['slug'] ] = array(
                'name' => $status['name'],
                'color' => $status['color'],
            );
        }

        $data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'data' => array(),
                    'backgroundColor' => array(),
                ),
            ),
        );

        foreach ( $results as $row ) {
            $status_info = isset( $status_map[ $row->status ] ) ? $status_map[ $row->status ] : array(
                'name' => ucfirst( str_replace( '-', ' ', $row->status ) ),
                'color' => '#3498db',
            );
            $data['labels'][] = $status_info['name'];
            $data['datasets'][0]['data'][] = intval( $row->count );
            $data['datasets'][0]['backgroundColor'][] = $status_info['color'];
        }

        return $data;
    }

    /**
     * Get tickets by priority.
     *
     * @param array $filters Filters.
     * @return array Priority breakdown.
     */
    public function get_tickets_by_priority( $filters ) {
        global $wpdb;

        $where_clauses = $this->build_where_clauses( $filters, true, false ); // Don't include priority
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value as priority, COUNT(DISTINCT p.ID) as count
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wphd_priority'
                LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                {$where_sql}
                GROUP BY pm.meta_value
                ORDER BY count DESC",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        $priorities = get_option( 'wphd_priorities', array() );
        $priority_map = array();
        foreach ( $priorities as $priority ) {
            $priority_map[ $priority['slug'] ] = array(
                'name' => $priority['name'],
                'color' => $priority['color'],
            );
        }

        $data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => __( 'Tickets', 'wp-helpdesk' ),
                    'data' => array(),
                    'backgroundColor' => array(),
                ),
            ),
        );

        foreach ( $results as $row ) {
            $priority_info = isset( $priority_map[ $row->priority ] ) ? $priority_map[ $row->priority ] : array(
                'name' => ucfirst( $row->priority ),
                'color' => '#f39c12',
            );
            $data['labels'][] = $priority_info['name'];
            $data['datasets'][0]['data'][] = intval( $row->count );
            $data['datasets'][0]['backgroundColor'][] = $priority_info['color'];
        }

        return $data;
    }

    /**
     * Get tickets by category.
     *
     * @param array $filters Filters.
     * @return array Category breakdown.
     */
    public function get_tickets_by_category( $filters ) {
        global $wpdb;

        $where_clauses = $this->build_where_clauses( $filters, true, true, false ); // Don't include category
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value as category, COUNT(DISTINCT p.ID) as count
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wphd_category'
                LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                AND pm.meta_value != ''
                {$where_sql}
                GROUP BY pm.meta_value
                ORDER BY count DESC",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        $categories = get_option( 'wphd_categories', array() );
        $category_map = array();
        foreach ( $categories as $category ) {
            $category_map[ $category['slug'] ] = array(
                'name' => $category['name'],
                'color' => $category['color'],
            );
        }

        $data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => __( 'Tickets', 'wp-helpdesk' ),
                    'data' => array(),
                    'backgroundColor' => array(),
                ),
            ),
        );

        foreach ( $results as $row ) {
            $category_info = isset( $category_map[ $row->category ] ) ? $category_map[ $row->category ] : array(
                'name' => ucfirst( str_replace( '-', ' ', $row->category ) ),
                'color' => '#3498db',
            );
            $data['labels'][] = $category_info['name'];
            $data['datasets'][0]['data'][] = intval( $row->count );
            $data['datasets'][0]['backgroundColor'][] = $category_info['color'];
        }

        return $data;
    }

    /**
     * Get agent performance data.
     *
     * @param array $filters Filters.
     * @return array Agent performance metrics.
     */
    public function get_agent_performance( $filters ) {
        global $wpdb;

        $where_clauses = $this->build_where_clauses( $filters, true, true, true, false ); // Don't include assignee
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';
        $comments_table = $wpdb->prefix . 'wphd_comments';

        // Get agents with tickets and their comment counts
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value as user_id, 
                COUNT(DISTINCT p.ID) as total_tickets,
                (SELECT COUNT(c.id) 
                 FROM {$comments_table} c 
                 WHERE c.user_id = pm.meta_value 
                 AND c.created_at BETWEEN %s AND %s) as comment_count
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wphd_assignee'
                LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                AND pm.meta_value != '0'
                AND pm.meta_value != ''
                {$where_sql}
                GROUP BY pm.meta_value
                ORDER BY total_tickets DESC
                LIMIT 10",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59',
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        $data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => __( 'Tickets Assigned', 'wp-helpdesk' ),
                    'data' => array(),
                    'backgroundColor' => '#2271b1',
                ),
            ),
            'agent_details' => array(),
        );

        foreach ( $results as $row ) {
            $user = get_userdata( $row->user_id );
            if ( $user ) {
                $data['labels'][] = $user->display_name;
                $data['datasets'][0]['data'][] = intval( $row->total_tickets );
                $data['agent_details'][] = array(
                    'user_id' => $row->user_id,
                    'user_name' => $user->display_name,
                    'total_tickets' => intval( $row->total_tickets ),
                    'total_comments' => intval( $row->comment_count ),
                    'avg_comments_per_ticket' => $row->total_tickets > 0 ? round( $row->comment_count / $row->total_tickets, 1 ) : 0,
                );
            }
        }

        return $data;
    }

    /**
     * Get resolution time trend.
     *
     * @param array $filters Filters.
     * @return array Resolution time data.
     */
    public function get_resolution_time_trend( $filters ) {
        global $wpdb;
        
        $sla_table = $wpdb->prefix . 'wphd_sla_log';
        $where_clauses = $this->build_where_clauses( $filters );
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        // Get resolution times grouped by date
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(p.post_date) as date, 
                AVG(TIMESTAMPDIFF(HOUR, p.post_date, sla.resolved_at)) as avg_hours
                FROM {$wpdb->posts} p
                INNER JOIN {$sla_table} sla ON p.ID = sla.ticket_id
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                AND sla.resolved_at IS NOT NULL
                {$where_sql}
                GROUP BY DATE(p.post_date)
                ORDER BY p.post_date ASC",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        $data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => __( 'Avg Resolution Time (hours)', 'wp-helpdesk' ),
                    'data' => array(),
                    'borderColor' => '#2271b1',
                    'fill' => false,
                ),
            ),
        );

        foreach ( $results as $row ) {
            $data['labels'][] = $row->date;
            $data['datasets'][0]['data'][] = round( floatval( $row->avg_hours ), 1 );
        }

        return $data;
    }

    /**
     * Get ticket details for table.
     *
     * @param array $filters Filters.
     * @return array Ticket details.
     */
    public function get_ticket_details( $filters ) {
        global $wpdb;

        $where_clauses = $this->build_where_clauses( $filters );
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID, p.post_title, p.post_date,
                MAX(CASE WHEN pm.meta_key = '_wphd_status' THEN pm.meta_value END) as status,
                MAX(CASE WHEN pm.meta_key = '_wphd_priority' THEN pm.meta_value END) as priority,
                MAX(CASE WHEN pm.meta_key = '_wphd_category' THEN pm.meta_value END) as category,
                MAX(CASE WHEN pm.meta_key = '_wphd_assignee' THEN pm.meta_value END) as assignee
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                {$where_sql}
                GROUP BY p.ID
                ORDER BY p.post_date DESC
                LIMIT 100",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        $sla_table = $wpdb->prefix . 'wphd_sla_log';
        
        $tickets = array();
        foreach ( $results as $row ) {
            // Get SLA info
            $sla = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT resolved_at, TIMESTAMPDIFF(HOUR, %s, resolved_at) as resolution_hours
                    FROM {$sla_table}
                    WHERE ticket_id = %d",
                    $row->post_date,
                    $row->ID
                )
            );

            $assignee_name = __( 'Unassigned', 'wp-helpdesk' );
            if ( ! empty( $row->assignee ) ) {
                $user = get_userdata( $row->assignee );
                if ( $user ) {
                    $assignee_name = $user->display_name;
                }
            }

            $tickets[] = array(
                'id' => $row->ID,
                'subject' => $row->post_title,
                'status' => $row->status,
                'priority' => $row->priority,
                'category' => $row->category,
                'assignee' => $assignee_name,
                'created_date' => $row->post_date,
                'resolved_date' => $sla ? $sla->resolved_at : null,
                'resolution_time' => $sla && $sla->resolution_hours ? round( $sla->resolution_hours, 1 ) : null,
            );
        }

        return $tickets;
    }

    /**
     * Get SLA statistics.
     *
     * @param array $filters Filters.
     * @return array SLA data.
     */
    public function get_sla_statistics( $filters ) {
        global $wpdb;
        
        $sla_table = $wpdb->prefix . 'wphd_sla_log';
        $where_clauses = $this->build_where_clauses( $filters );
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        // Total tickets with SLA
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$sla_table} sla ON p.ID = sla.ticket_id
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                {$where_sql}",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        // Tickets meeting first response SLA
        $first_response_met = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$sla_table} sla ON p.ID = sla.ticket_id
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                AND sla.first_response_at IS NOT NULL
                AND sla.first_response_at <= sla.first_response_due
                {$where_sql}",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        // Tickets meeting resolution SLA
        $resolution_met = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$sla_table} sla ON p.ID = sla.ticket_id
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                AND sla.resolved_at IS NOT NULL
                AND sla.resolved_at <= sla.resolution_due
                {$where_sql}",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        return array(
            'total' => intval( $total ),
            'first_response_met' => intval( $first_response_met ),
            'resolution_met' => intval( $resolution_met ),
            'first_response_rate' => $total > 0 ? round( ( $first_response_met / $total ) * 100, 1 ) : 0,
            'resolution_rate' => $total > 0 ? round( ( $resolution_met / $total ) * 100, 1 ) : 0,
        );
    }

    /**
     * Get user-specific report with comparison.
     *
     * @param int   $user_id User ID.
     * @param array $filters Filters.
     * @return array User report data.
     */
    public function get_user_comparison( $user_id, $filters ) {
        // Get user's stats
        $user_filters = array_merge( $filters, array( 'assignee' => $user_id ) );
        $user_stats = $this->get_summary_statistics( $user_filters );

        // Get team average (all users)
        $team_filters = $filters;
        $team_filters['assignee'] = ''; // Remove assignee filter for team average
        $team_stats = $this->get_summary_statistics( $team_filters );

        return array(
            'user_stats' => $user_stats,
            'team_stats' => $team_stats,
            'comparison' => array(
                'tickets_diff' => $user_stats['total_tickets'] - ( $team_stats['total_tickets'] / max( 1, $this->get_agent_count( $filters ) ) ),
                'resolution_diff' => $user_stats['avg_resolution_time'] - $team_stats['avg_resolution_time'],
                'sla_diff' => $user_stats['sla_compliance_rate'] - $team_stats['sla_compliance_rate'],
            ),
        );
    }

    /**
     * Export report data to CSV.
     *
     * @param array $filters Filters.
     * @return string CSV data.
     */
    public function export_csv( $filters ) {
        $tickets = $this->get_ticket_details( $filters );
        
        $output = fopen( 'php://temp', 'r+' );
        
        // Add header
        fputcsv( $output, array(
            __( 'Ticket ID', 'wp-helpdesk' ),
            __( 'Subject', 'wp-helpdesk' ),
            __( 'Status', 'wp-helpdesk' ),
            __( 'Priority', 'wp-helpdesk' ),
            __( 'Category', 'wp-helpdesk' ),
            __( 'Assignee', 'wp-helpdesk' ),
            __( 'Created Date', 'wp-helpdesk' ),
            __( 'Resolved Date', 'wp-helpdesk' ),
            __( 'Resolution Time (hours)', 'wp-helpdesk' ),
        ) );

        // Add data
        foreach ( $tickets as $ticket ) {
            fputcsv( $output, array(
                $ticket['id'],
                $ticket['subject'],
                $ticket['status'],
                $ticket['priority'],
                $ticket['category'],
                $ticket['assignee'],
                $ticket['created_date'],
                $ticket['resolved_date'] ? $ticket['resolved_date'] : '-',
                $ticket['resolution_time'] ? $ticket['resolution_time'] : '-',
            ) );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }

    /**
     * Build WHERE clauses for queries.
     *
     * @param array $filters Filters.
     * @param bool  $include_status Include status filter.
     * @param bool  $include_priority Include priority filter.
     * @param bool  $include_category Include category filter.
     * @param bool  $include_assignee Include assignee filter.
     * @return array WHERE clauses.
     */
    private function build_where_clauses( $filters, $include_status = true, $include_priority = true, $include_category = true, $include_assignee = true ) {
        global $wpdb;
        $clauses = array();

        if ( $include_status && ! empty( $filters['status'] ) ) {
            $clauses[] = $wpdb->prepare(
                "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm_status WHERE pm_status.post_id = p.ID AND pm_status.meta_key = '_wphd_status' AND pm_status.meta_value = %s)",
                $filters['status']
            );
        }

        if ( $include_priority && ! empty( $filters['priority'] ) ) {
            $clauses[] = $wpdb->prepare(
                "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm_priority WHERE pm_priority.post_id = p.ID AND pm_priority.meta_key = '_wphd_priority' AND pm_priority.meta_value = %s)",
                $filters['priority']
            );
        }

        if ( $include_category && ! empty( $filters['category'] ) ) {
            $clauses[] = $wpdb->prepare(
                "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm_category WHERE pm_category.post_id = p.ID AND pm_category.meta_key = '_wphd_category' AND pm_category.meta_value = %s)",
                $filters['category']
            );
        }

        if ( $include_assignee && ! empty( $filters['assignee'] ) ) {
            $clauses[] = $wpdb->prepare(
                "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm_assignee WHERE pm_assignee.post_id = p.ID AND pm_assignee.meta_key = '_wphd_assignee' AND pm_assignee.meta_value = %s)",
                $filters['assignee']
            );
        }

        // User-specific filter
        if ( ! empty( $filters['user_id'] ) ) {
            $clauses[] = $wpdb->prepare(
                "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm_user WHERE pm_user.post_id = p.ID AND pm_user.meta_key = '_wphd_assignee' AND pm_user.meta_value = %s)",
                $filters['user_id']
            );
        }

        return $clauses;
    }

    /**
     * Count tickets by status type.
     *
     * @param array $filters Filters.
     * @param array $status_list Status list.
     * @param bool  $in_list Include or exclude list.
     * @return int Count.
     */
    private function count_tickets_by_status_type( $filters, $status_list, $in_list = true ) {
        global $wpdb;

        $where_clauses = $this->build_where_clauses( $filters, false );
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        if ( empty( $status_list ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $status_list ), '%s' ) );
        $operator = $in_list ? 'IN' : 'NOT IN';

        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wphd_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'wphd_ticket'
            AND p.post_status = 'publish'
            AND p.post_date BETWEEN %s AND %s
            AND pm.meta_value {$operator} ({$placeholders})
            {$where_sql}",
            array_merge(
                array( $filters['date_start'] . ' 00:00:00', $filters['date_end'] . ' 23:59:59' ),
                $status_list
            )
        );

        return $wpdb->get_var( $query );
    }

    /**
     * Get average resolution time.
     *
     * @param array $filters Filters.
     * @return float Average hours.
     */
    private function get_average_resolution_time( $filters ) {
        global $wpdb;
        
        $sla_table = $wpdb->prefix . 'wphd_sla_log';
        $where_clauses = $this->build_where_clauses( $filters );
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        $avg = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(TIMESTAMPDIFF(HOUR, p.post_date, sla.resolved_at))
                FROM {$wpdb->posts} p
                INNER JOIN {$sla_table} sla ON p.ID = sla.ticket_id
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                AND sla.resolved_at IS NOT NULL
                {$where_sql}",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        return floatval( $avg );
    }

    /**
     * Get SLA compliance rate.
     *
     * @param array $filters Filters.
     * @return float Percentage.
     */
    private function get_sla_compliance_rate( $filters ) {
        $sla_stats = $this->get_sla_statistics( $filters );
        return $sla_stats['resolution_rate'];
    }

    /**
     * Get average first response time.
     *
     * @param array $filters Filters.
     * @return float Average hours.
     */
    private function get_average_first_response_time( $filters ) {
        global $wpdb;
        
        $sla_table = $wpdb->prefix . 'wphd_sla_log';
        $where_clauses = $this->build_where_clauses( $filters );
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        $avg = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(TIMESTAMPDIFF(HOUR, p.post_date, sla.first_response_at))
                FROM {$wpdb->posts} p
                INNER JOIN {$sla_table} sla ON p.ID = sla.ticket_id
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                AND sla.first_response_at IS NOT NULL
                {$where_sql}",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        return floatval( $avg );
    }

    /**
     * Get count of agents.
     *
     * @param array $filters Filters.
     * @return int Count.
     */
    private function get_agent_count( $filters ) {
        global $wpdb;

        $where_clauses = $this->build_where_clauses( $filters, true, true, true, false );
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT pm.meta_value)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wphd_assignee'
                LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                AND pm.meta_value != '0'
                AND pm.meta_value != ''
                {$where_sql}",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        return max( 1, intval( $count ) );
    }

    /**
     * Get comment statistics.
     *
     * @param array $filters Filters.
     * @return array Comment statistics.
     */
    public function get_comment_statistics( $filters ) {
        global $wpdb;
        
        $comments_table = $wpdb->prefix . 'wphd_comments';
        $where_clauses = $this->build_where_clauses( $filters );
        $where_sql = ! empty( $where_clauses ) ? ' AND ' . implode( ' AND ', $where_clauses ) : '';

        // Total comments in date range
        $total_comments = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(c.id)
                FROM {$comments_table} c
                INNER JOIN {$wpdb->posts} p ON c.ticket_id = p.ID
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND c.created_at BETWEEN %s AND %s
                {$where_sql}",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        // Comments by user (top 10)
        $comments_by_user = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.user_id, COUNT(c.id) as comment_count
                FROM {$comments_table} c
                INNER JOIN {$wpdb->posts} p ON c.ticket_id = p.ID
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND c.created_at BETWEEN %s AND %s
                {$where_sql}
                GROUP BY c.user_id
                ORDER BY comment_count DESC
                LIMIT 10",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        // Format comments by user with user names
        $formatted_comments_by_user = array();
        foreach ( $comments_by_user as $row ) {
            $user = get_userdata( $row->user_id );
            if ( $user ) {
                $formatted_comments_by_user[] = array(
                    'user_id' => $row->user_id,
                    'user_name' => $user->display_name,
                    'comment_count' => intval( $row->comment_count ),
                );
            }
        }

        // Average comments per ticket
        $tickets_with_comments = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT c.ticket_id)
                FROM {$comments_table} c
                INNER JOIN {$wpdb->posts} p ON c.ticket_id = p.ID
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wphd_ticket'
                AND p.post_status = 'publish'
                AND c.created_at BETWEEN %s AND %s
                {$where_sql}",
                $filters['date_start'] . ' 00:00:00',
                $filters['date_end'] . ' 23:59:59'
            )
        );

        $avg_comments_per_ticket = $tickets_with_comments > 0 ? $total_comments / $tickets_with_comments : 0;

        return array(
            'total_comments' => intval( $total_comments ),
            'comments_by_user' => $formatted_comments_by_user,
            'avg_comments_per_ticket' => round( $avg_comments_per_ticket, 1 ),
            'tickets_with_comments' => intval( $tickets_with_comments ),
        );
    }
}

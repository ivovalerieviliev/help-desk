<?php
/**
 * Admin Pages Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Admin_Pages {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function render_dashboard() {
        echo '<div class="wrap wphd-wrap">';
        echo '<h1>' . esc_html__('Help Desk Dashboard', 'wp-helpdesk') . '</h1>';
        echo '<div class="wphd-dashboard-widgets">';
        
        // Overview Stats
        echo '<div class="wphd-widget wphd-overview-widget">';
        echo '<h2>' . esc_html__('Overview', 'wp-helpdesk') . '</h2>';
        $stats = self::get_quick_stats();
        echo '<div class="wphd-stats-grid">';
        echo '<div class="wphd-stat-box"><span class="wphd-stat-number">' . intval($stats['total']) . '</span><span class="wphd-stat-label">Total Tickets</span></div>';
        echo '<div class="wphd-stat-box"><span class="wphd-stat-number">' . intval($stats['open']) . '</span><span class="wphd-stat-label">Open</span></div>';
        echo '<div class="wphd-stat-box"><span class="wphd-stat-number">' . intval($stats['in_progress']) . '</span><span class="wphd-stat-label">In Progress</span></div>';
        echo '<div class="wphd-stat-box"><span class="wphd-stat-number">' . intval($stats['resolved']) . '</span><span class="wphd-stat-label">Resolved</span></div>';
        echo '</div>';
        echo '</div>';
        
        // Recent Tickets
        echo '<div class="wphd-widget wphd-recent-tickets-widget">';
        echo '<h2>' . esc_html__('Recent Tickets', 'wp-helpdesk') . '</h2>';
        self::render_recent_tickets();
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    private static function get_quick_stats() {
        $total = wp_count_posts('wphd_ticket');
        return array(
            'total' => isset($total->publish) ? $total->publish : 0,
            'open' => self::count_tickets_by_status('open'),
            'in_progress' => self::count_tickets_by_status('in_progress'),
            'resolved' => self::count_tickets_by_status('resolved')
        );
    }
    
    private static function count_tickets_by_status($status) {
        $args = array(
            'post_type' => 'wphd_ticket',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_wphd_status',
                    'value' => $status
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    private static function render_recent_tickets() {
        $tickets = get_posts(array(
            'post_type' => 'wphd_ticket',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (empty($tickets)) {
            echo '<p>' . esc_html__('No tickets found.', 'wp-helpdesk') . '</p>';
            return;
        }
        
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>ID</th><th>Subject</th><th>Status</th><th>Priority</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        foreach ($tickets as $ticket) {
            $status = get_post_meta($ticket->ID, '_wphd_status', true);
            $priority = get_post_meta($ticket->ID, '_wphd_priority', true);
            echo '<tr>';
            echo '<td>#' . intval($ticket->ID) . '</td>';
            echo '<td><a href="' . esc_url(admin_url('admin.php?page=wphd-tickets&ticket_id=' . $ticket->ID)) . '">' . esc_html($ticket->post_title) . '</a></td>';
            echo '<td><span class="wphd-status wphd-status-' . esc_attr($status) . '">' . esc_html(ucfirst(str_replace('_', ' ', $status))) . '</span></td>';
            echo '<td><span class="wphd-priority wphd-priority-' . esc_attr($priority) . '">' . esc_html(ucfirst($priority)) . '</span></td>';
            echo '<td>' . esc_html(get_the_date('', $ticket)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    
    public static function render_tickets() {
        $ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;
        
        if ($ticket_id > 0) {
            self::render_ticket_details($ticket_id);
        } else {
            echo '<div class="wrap wphd-wrap">';
            echo '<h1 class="wp-heading-inline">' . esc_html__('All Tickets', 'wp-helpdesk') . '</h1>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=wphd-new-ticket')) . '" class="page-title-action">' . esc_html__('Add New', 'wp-helpdesk') . '</a>';
            echo '<hr class="wp-header-end">';
            echo WPHD_Ticket_List::render();
            echo '</div>';
        }
    }
    
    private static function render_ticket_details($ticket_id) {
        $ticket = get_post($ticket_id);
        if (!$ticket || $ticket->post_type !== 'wphd_ticket') {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Ticket not found.', 'wp-helpdesk') . '</p></div></div>';
            return;
        }
        
        echo '<div class="wrap wphd-wrap">';
        echo '<h1>' . esc_html__('Ticket', 'wp-helpdesk') . ' #' . intval($ticket_id) . ': ' . esc_html($ticket->post_title) . '</h1>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wphd-tickets')) . '" class="button">&larr; ' . esc_html__('Back to Tickets', 'wp-helpdesk') . '</a>';
        echo '<hr class="wp-header-end">';
        echo WPHD_Ticket_Details::render($ticket_id);
        echo '</div>';
    }
    
    public static function render_new_ticket() {
        echo '<div class="wrap wphd-wrap">';
        echo '<h1>' . esc_html__('Create New Ticket', 'wp-helpdesk') . '</h1>';
        echo WPHD_Ticket_Create::render();
        echo '</div>';
    }
    
    public static function render_handover() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        echo '<div class="wrap wphd-wrap">';
        
        if ($action === 'new') {
            echo '<h1>' . esc_html__('Create Handover Report', 'wp-helpdesk') . '</h1>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=wphd-handover')) . '" class="button">&larr; ' . esc_html__('Back to Reports', 'wp-helpdesk') . '</a>';
            echo '<hr class="wp-header-end">';
            echo WPHD_Handover_Create::render();
        } else {
            echo '<h1 class="wp-heading-inline">' . esc_html__('Handover Reports', 'wp-helpdesk') . '</h1>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=wphd-handover&action=new')) . '" class="page-title-action">' . esc_html__('Create New Report', 'wp-helpdesk') . '</a>';
            echo '<hr class="wp-header-end">';
            echo WPHD_Handover_List::render();
        }
        
        echo '</div>';
    }
    
    public static function render_analytics() {
        echo '<div class="wrap wphd-wrap">';
        echo '<h1>' . esc_html__('Analytics Dashboard', 'wp-helpdesk') . '</h1>';
        echo WPHD_Analytics_Dashboard::render();
        echo '</div>';
    }
    
    public static function render_settings() {
        echo WPHD_Settings_Page::render();
    }
}
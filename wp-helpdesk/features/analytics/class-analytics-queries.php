<?php
/**
 * Analytics Queries
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Analytics_Queries {
    
    public static function get_dashboard_data($period = 'week') {
        $date_range = self::get_date_range($period);
        
        return array(
            'overview' => self::get_overview_stats($date_range),
            'tickets_by_status' => self::get_tickets_by_status(),
            'tickets_by_priority' => self::get_tickets_by_priority(),
            'tickets_trend' => self::get_tickets_trend($date_range),
            'sla_performance' => self::get_sla_performance($date_range),
            'agent_performance' => self::get_agent_performance($date_range)
        );
    }
    
    private static function get_date_range($period) {
        $end = current_time('mysql');
        switch ($period) {
            case 'today':
                $start = date('Y-m-d 00:00:00', current_time('timestamp'));
                break;
            case 'week':
                $start = date('Y-m-d H:i:s', strtotime('-7 days', current_time('timestamp')));
                break;
            case 'month':
                $start = date('Y-m-d H:i:s', strtotime('-30 days', current_time('timestamp')));
                break;
            default:
                $start = date('Y-m-d H:i:s', strtotime('-7 days', current_time('timestamp')));
        }
        return array('start' => $start, 'end' => $end);
    }
    
    public static function get_overview_stats($date_range) {
        global $wpdb;
        
        $total_tickets = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wphd_ticket' AND post_status = 'publish' AND post_date BETWEEN %s AND %s",
            $date_range['start'], $date_range['end']
        ));
        
        return array(
            'total_tickets' => intval($total_tickets),
            'open_tickets' => 0,
            'closed_tickets' => 0
        );
    }
    
    public static function get_tickets_by_status() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT pm.meta_value as status, COUNT(*) as count 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'wphd_ticket' AND p.post_status = 'publish' 
            AND pm.meta_key = '_wphd_status' 
            GROUP BY pm.meta_value"
        );
        
        return $results;
    }
    
    public static function get_tickets_by_priority() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT pm.meta_value as priority, COUNT(*) as count 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'wphd_ticket' AND p.post_status = 'publish' 
            AND pm.meta_key = '_wphd_priority' 
            GROUP BY pm.meta_value"
        );
        
        return $results;
    }
    
    public static function get_tickets_trend($date_range) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(post_date) as date, COUNT(*) as count 
            FROM {$wpdb->posts} 
            WHERE post_type = 'wphd_ticket' AND post_status = 'publish' 
            AND post_date BETWEEN %s AND %s 
            GROUP BY DATE(post_date) 
            ORDER BY date ASC",
            $date_range['start'], $date_range['end']
        ));
    }
    
    public static function get_sla_performance($date_range) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_sla_log';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        return array(
            'total' => intval($total),
            'first_response_rate' => 0,
            'resolution_rate' => 0
        );
    }
    
    public static function get_agent_performance($date_range) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value as agent_id, COUNT(*) as tickets_handled
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'wphd_ticket' AND p.post_status = 'publish' 
            AND pm.meta_key = '_wphd_assignee' AND pm.meta_value != '0'
            AND p.post_date BETWEEN %s AND %s
            GROUP BY pm.meta_value
            LIMIT 10",
            $date_range['start'], $date_range['end']
        ));
        
        $data = array();
        foreach ($results as $row) {
            $user = get_user_by('id', $row->agent_id);
            if ($user) {
                $data[] = array(
                    'id' => intval($row->agent_id),
                    'name' => $user->display_name,
                    'tickets_handled' => intval($row->tickets_handled)
                );
            }
        }
        
        return $data;
    }
}
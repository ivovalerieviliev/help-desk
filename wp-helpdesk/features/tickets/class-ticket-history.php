<?php
/**
 * Ticket History Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Ticket_History {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function log($ticket_id, $action, $old_value = '', $new_value = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_history';
        
        $wpdb->insert($table, array(
            'ticket_id' => $ticket_id,
            'user_id' => get_current_user_id(),
            'action' => $action,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'created_at' => current_time('mysql')
        ));
    }
    
    public static function get_history($ticket_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_history';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE ticket_id = %d ORDER BY created_at DESC",
            $ticket_id
        ));
    }
}
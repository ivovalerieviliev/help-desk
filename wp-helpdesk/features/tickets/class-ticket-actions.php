<?php
/**
 * Ticket Actions Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Ticket_Actions {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_action_items($ticket_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_action_items';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE ticket_id = %d ORDER BY created_at ASC",
            $ticket_id
        ));
    }
    
    public static function add_action_item($ticket_id, $title) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_action_items';
        return $wpdb->insert($table, array(
            'ticket_id' => $ticket_id,
            'title' => $title,
            'completed' => 0,
            'created_at' => current_time('mysql')
        ));
    }
    
    public static function toggle_action_item($item_id, $completed) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_action_items';
        return $wpdb->update($table, array('completed' => $completed), array('id' => $item_id));
    }
}
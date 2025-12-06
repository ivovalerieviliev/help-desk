<?php
/**
 * Ticket SLA Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Ticket_SLA {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_sla_status($ticket_id) {
        $ticket = get_post($ticket_id);
        $created = strtotime($ticket->post_date);
        $now = current_time('timestamp');
        $elapsed = $now - $created;
        
        $sla_settings = get_option('wphd_sla_settings', array(
            'first_response' => 14400,
            'resolution' => 86400
        ));
        
        $first_response = $sla_settings['first_response'];
        $resolution = $sla_settings['resolution'];
        
        if ($elapsed > $resolution) {
            return 'breached';
        } elseif ($elapsed > ($resolution * 0.75)) {
            return 'warning';
        }
        return 'ok';
    }
    
    public static function get_time_remaining($ticket_id) {
        $ticket = get_post($ticket_id);
        $created = strtotime($ticket->post_date);
        $now = current_time('timestamp');
        
        $sla_settings = get_option('wphd_sla_settings', array('resolution' => 86400));
        $deadline = $created + $sla_settings['resolution'];
        
        return max(0, $deadline - $now);
    }
}
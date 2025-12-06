<?php
/**
 * Handover Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Handover {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_handovers($args = array()) {
        return WPHD_Database::get_handovers($args);
    }
    
    public static function get_handover($id) {
        $handover = WPHD_Database::get_handover($id);
        if (!$handover) return null;
        
        $handover->tickets = self::get_handover_tickets_details($id);
        $handover->created_by_user = get_user_by('id', $handover->created_by);
        
        return $handover;
    }
    
    public static function get_handover_tickets_details($handover_id) {
        $handover_tickets = WPHD_Database::get_handover_tickets($handover_id);
        $tickets = array();
        
        foreach ($handover_tickets as $ht) {
            $post = get_post($ht->ticket_id);
            if ($post) {
                $tickets[] = array(
                    'id' => $post->ID,
                    'number' => WPHD_Ticket_CPT::get_ticket_number($post->ID),
                    'title' => $post->post_title,
                    'status' => get_post_meta($post->ID, '_wphd_status', true),
                    'priority' => get_post_meta($post->ID, '_wphd_priority', true),
                    'notes' => $ht->notes,
                    'sort_order' => $ht->sort_order
                );
            }
        }
        
        return $tickets;
    }
    
    public static function create_handover($data) {
        $handover_id = WPHD_Database::create_handover($data);
        
        if ($handover_id && !empty($data['tickets'])) {
            $order = 0;
            foreach ($data['tickets'] as $ticket) {
                $ticket_id = is_array($ticket) ? intval($ticket['id']) : intval($ticket);
                $notes = is_array($ticket) ? sanitize_textarea_field($ticket['notes'] ?? '') : '';
                WPHD_Database::add_handover_ticket($handover_id, $ticket_id, $notes, $order++);
            }
        }
        
        do_action('wphd_handover_created', $handover_id);
        return $handover_id;
    }
    
    public static function update_handover($id, $data) {
        $result = WPHD_Database::update_handover($id, $data);
        do_action('wphd_handover_updated', $id);
        return $result;
    }
    
    public static function delete_handover($id) {
        return WPHD_Database::delete_handover($id);
    }
    
    public static function publish_handover($id) {
        return WPHD_Database::update_handover($id, array(
            'status' => 'published',
            'published_at' => current_time('mysql')
        ));
    }
    
    public static function mark_reviewed($id) {
        return WPHD_Database::update_handover($id, array(
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => current_time('mysql')
        ));
    }
}
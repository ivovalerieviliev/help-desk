<?php
/**
 * Handover Create Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Handover_Create {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function create_handover($data) {
        $handover_id = wp_insert_post(array(
            'post_type' => 'wphd_handover',
            'post_title' => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['notes']),
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ));
        
        if ($handover_id && !empty($data['tickets'])) {
            update_post_meta($handover_id, '_wphd_tickets', $data['tickets']);
            update_post_meta($handover_id, '_wphd_shift', $data['shift']);
            update_post_meta($handover_id, '_wphd_reviewed', 0);
        }
        
        return $handover_id;
    }
    
    public static function render_form() {
        ob_start();
        echo '<div class="wrap wphd-wrap">';
        echo '<h1>Create Handover</h1>';
        echo '<form id="wphd-handover-form" method="post">';
        echo '<div class="wphd-form-row">';
        echo '<label>Title</label>';
        echo '<input type="text" name="title" required class="regular-text">';
        echo '</div>';
        echo '<div class="wphd-form-row">';
        echo '<label>Shift</label>';
        echo '<select name="shift">';
        echo '<option value="morning">Morning</option>';
        echo '<option value="afternoon">Afternoon</option>';
        echo '<option value="night">Night</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="wphd-form-row">';
        echo '<label>Notes</label>';
        echo '<textarea name="notes" rows="5" class="large-text"></textarea>';
        echo '</div>';
        echo '<div class="wphd-form-row">';
        echo '<label>Search Tickets</label>';
        echo '<input type="text" id="wphd-ticket-search" class="regular-text">';
        echo '<div id="wphd-ticket-search-results"></div>';
        echo '</div>';
        echo '<div id="wphd-selected-tickets"></div>';
        echo '<p><button type="submit" class="button button-primary">Create Handover</button></p>';
        echo '</form>';
        echo '</div>';
        return ob_get_clean();
    }
}
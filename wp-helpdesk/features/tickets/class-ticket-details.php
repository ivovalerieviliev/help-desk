<?php
/**
 * Ticket Details Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Ticket_Details {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_full_ticket($ticket_id) {
        $post = get_post($ticket_id);
        if (!$post || $post->post_type !== 'wphd_ticket') {
            return null;
        }
        
        $status_slug = get_post_meta($ticket_id, '_wphd_status', true);
        $priority_slug = get_post_meta($ticket_id, '_wphd_priority', true);
        $category_slug = get_post_meta($ticket_id, '_wphd_category', true);
        $assignee_id = get_post_meta($ticket_id, '_wphd_assignee', true);
        
        $status_info = WPHD_Ticket_Meta::get_status_info($status_slug);
        $priority_info = WPHD_Ticket_Meta::get_priority_info($priority_slug);
        
        $author = get_user_by('id', $post->post_author);
        $assignee = $assignee_id ? get_user_by('id', $assignee_id) : null;
        
        $comments = WPHD_Database::get_comments($ticket_id);
        $history = WPHD_Database::get_history($ticket_id);
        $action_items = WPHD_Database::get_action_items($ticket_id);
        $sla = WPHD_Database::get_sla($ticket_id);
        
        return array(
            'id' => $post->ID,
            'number' => WPHD_Ticket_CPT::get_ticket_number($post->ID),
            'title' => $post->post_title,
            'content' => $post->post_content,
            'status' => array(
                'slug' => $status_slug,
                'name' => $status_info ? $status_info['name'] : $status_slug,
                'color' => $status_info ? $status_info['color'] : '#999'
            ),
            'priority' => array(
                'slug' => $priority_slug,
                'name' => $priority_info ? $priority_info['name'] : $priority_slug,
                'color' => $priority_info ? $priority_info['color'] : '#999'
            ),
            'category' => $category_slug,
            'author' => array(
                'id' => $post->post_author,
                'name' => $author ? $author->display_name : 'Unknown',
                'avatar' => get_avatar_url($post->post_author)
            ),
            'assignee' => $assignee ? array(
                'id' => $assignee->ID,
                'name' => $assignee->display_name,
                'avatar' => get_avatar_url($assignee->ID)
            ) : null,
            'comments' => $comments,
            'history' => $history,
            'action_items' => $action_items,
            'sla' => $sla,
            'created' => $post->post_date,
            'modified' => $post->post_modified
        );
    }

    public static function render($ticket_id) {
        ob_start();
        echo '<p>' . esc_html__('Use the ticket details page in the main tickets page.', 'wp-helpdesk') . '</p>';
        return ob_get_clean();
    }
}
<?php
/**
 * Ticket Comments Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Ticket_Comments {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_comments($ticket_id) {
        return WPHD_Database::get_comments($ticket_id);
    }
    
    public static function add_comment($ticket_id, $content, $is_internal = false) {
        $data = array(
            'ticket_id' => $ticket_id,
            'user_id' => get_current_user_id(),
            'content' => $content,
            'is_internal' => $is_internal ? 1 : 0
        );
        
        $comment_id = WPHD_Database::add_comment($data);
        
        if ($comment_id) {
            do_action('wphd_comment_added', $comment_id, $ticket_id);
        }
        
        return $comment_id;
    }
    
    public static function render_comments($ticket_id) {
        $comments = self::get_comments($ticket_id);
        ob_start();
        echo '<div class="wphd-ticket-comments">';
        echo '<h3>Comments</h3>';
        echo '<div id="wphd-comments-list">';
        foreach ($comments as $comment) {
            $user = get_user_by('id', $comment->user_id);
            $class = $comment->is_internal ? 'wphd-comment wphd-internal' : 'wphd-comment';
            echo '<div class="' . $class . '">';
            echo '<div class="wphd-comment-header">';
            echo get_avatar($comment->user_id, 32);
            echo '<strong>' . esc_html($user ? $user->display_name : 'Unknown') . '</strong>';
            echo '</div>';
            echo '<div class="wphd-comment-body">' . wp_kses_post($comment->content) . '</div>';
            echo '</div>';
        }
        echo '</div></div>';
        return ob_get_clean();
    }
}
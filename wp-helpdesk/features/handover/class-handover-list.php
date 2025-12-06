<?php
/**
 * Handover List Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Handover_List {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_handovers($args = array()) {
        $defaults = array(
            'post_type' => 'wphd_handover',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        $args = wp_parse_args($args, $defaults);
        return get_posts($args);
    }
    
    public static function render_list() {
        $handovers = self::get_handovers();
        ob_start();
        echo '<div class="wrap wphd-wrap">';
        echo '<h1>Handovers <a href="?page=wphd-handover-new" class="page-title-action">Add New</a></h1>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Title</th><th>Shift</th><th>Author</th><th>Date</th><th>Status</th></tr></thead>';
        echo '<tbody>';
        foreach ($handovers as $handover) {
            $shift = get_post_meta($handover->ID, '_wphd_shift', true);
            $reviewed = get_post_meta($handover->ID, '_wphd_reviewed', true);
            $author = get_user_by('id', $handover->post_author);
            echo '<tr>';
            echo '<td><a href="?page=wphd-handover&id=' . $handover->ID . '">' . esc_html($handover->post_title) . '</a></td>';
            echo '<td>' . esc_html(ucfirst($shift)) . '</td>';
            echo '<td>' . esc_html($author ? $author->display_name : 'Unknown') . '</td>';
            echo '<td>' . get_the_date('', $handover) . '</td>';
            echo '<td>' . ($reviewed ? '<span class="wphd-badge wphd-reviewed">Reviewed</span>' : '<span class="wphd-badge wphd-pending">Pending</span>') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        return ob_get_clean();
    }
}
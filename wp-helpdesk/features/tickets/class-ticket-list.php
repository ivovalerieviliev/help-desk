<?php
/**
 * Ticket List Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Ticket_List {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Initialization
    }
    
    public static function get_tickets($args = array()) {
        $defaults = array(
            'status' => '',
            'category' => '',
            'priority' => '',
            'assignee' => 0,
            'search' => '',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query_args = array(
            'post_type' => 'wphd_ticket',
            'posts_per_page' => $args['per_page'],
            'paged' => $args['page'],
            'orderby' => $args['orderby'],
            'order' => $args['order']
        );
        
        $meta_query = array();
        
        if (!empty($args['status'])) {
            $meta_query[] = array('key' => '_wphd_status', 'value' => $args['status']);
        }
        if (!empty($args['category'])) {
            $meta_query[] = array('key' => '_wphd_category', 'value' => $args['category']);
        }
        if (!empty($args['priority'])) {
            $meta_query[] = array('key' => '_wphd_priority', 'value' => $args['priority']);
        }
        if (!empty($args['assignee'])) {
            $meta_query[] = array('key' => '_wphd_assignee', 'value' => $args['assignee']);
        }
        
        if (!empty($meta_query)) {
            $query_args['meta_query'] = $meta_query;
        }
        
        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
        }
        
        $query = new WP_Query($query_args);
        
        $tickets = array();
        foreach ($query->posts as $post) {
            $tickets[] = self::format_ticket($post);
        }
        
        return array(
            'tickets' => $tickets,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $args['page']
        );
    }
    
    public static function format_ticket($post) {
        $status_slug = get_post_meta($post->ID, '_wphd_status', true);
        $priority_slug = get_post_meta($post->ID, '_wphd_priority', true);
        $assignee_id = get_post_meta($post->ID, '_wphd_assignee', true);
        
        $status_info = WPHD_Ticket_Meta::get_status_info($status_slug);
        $priority_info = WPHD_Ticket_Meta::get_priority_info($priority_slug);
        
        $assignee = null;
        if ($assignee_id) {
            $user = get_user_by('id', $assignee_id);
            if ($user) {
                $assignee = array(
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'avatar' => get_avatar_url($user->ID, array('size' => 32))
                );
            }
        }
        
        return array(
            'id' => $post->ID,
            'number' => WPHD_Ticket_CPT::get_ticket_number($post->ID),
            'title' => $post->post_title,
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
            'assignee' => $assignee,
            'created' => $post->post_date,
            'modified' => $post->post_modified
        );
    }
}
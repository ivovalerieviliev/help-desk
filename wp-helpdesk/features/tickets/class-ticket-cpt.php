<?php
/**
 * Ticket Custom Post Type
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Ticket_CPT {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
    }
    
    public function register_post_type() {
        $labels = array(
            'name' => __('Tickets', 'wp-helpdesk'),
            'singular_name' => __('Ticket', 'wp-helpdesk'),
            'add_new' => __('Add New', 'wp-helpdesk'),
            'add_new_item' => __('Add New Ticket', 'wp-helpdesk'),
            'edit_item' => __('Edit Ticket', 'wp-helpdesk'),
            'new_item' => __('New Ticket', 'wp-helpdesk'),
            'view_item' => __('View Ticket', 'wp-helpdesk'),
            'search_items' => __('Search Tickets', 'wp-helpdesk'),
            'not_found' => __('No tickets found', 'wp-helpdesk'),
            'not_found_in_trash' => __('No tickets found in Trash', 'wp-helpdesk'),
            'all_items' => __('All Tickets', 'wp-helpdesk'),
            'menu_name' => __('Tickets', 'wp-helpdesk')
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'query_var' => true,
            'rewrite' => array('slug' => 'ticket'),
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'delete_posts' => 'manage_options',
                'publish_posts' => 'manage_options',
                'read_private_posts' => 'manage_options'
            ),
            'map_meta_cap' => true,
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array('title', 'editor', 'author'),
            'show_in_rest' => true,
            'rest_base' => 'wphd-tickets'
        );
        
        register_post_type('wphd_ticket', $args);
    }
    
    public function register_taxonomies() {
        register_taxonomy('wphd_ticket_tag', 'wphd_ticket', array(
            'labels' => array(
                'name' => __('Ticket Tags', 'wp-helpdesk'),
                'singular_name' => __('Tag', 'wp-helpdesk'),
                'search_items' => __('Search Tags', 'wp-helpdesk'),
                'all_items' => __('All Tags', 'wp-helpdesk'),
                'edit_item' => __('Edit Tag', 'wp-helpdesk'),
                'update_item' => __('Update Tag', 'wp-helpdesk'),
                'add_new_item' => __('Add New Tag', 'wp-helpdesk'),
                'new_item_name' => __('New Tag Name', 'wp-helpdesk'),
                'menu_name' => __('Tags', 'wp-helpdesk')
            ),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'ticket-tag'),
            'show_in_rest' => true
        ));

        register_taxonomy('wphd_category', 'wphd_ticket', array(
            'labels' => array(
                'name' => __('Ticket Categories', 'wp-helpdesk'),
                'singular_name' => __('Category', 'wp-helpdesk'),
                'search_items' => __('Search Categories', 'wp-helpdesk'),
                'all_items' => __('All Categories', 'wp-helpdesk'),
                'edit_item' => __('Edit Category', 'wp-helpdesk'),
                'update_item' => __('Update Category', 'wp-helpdesk'),
                'add_new_item' => __('Add New Category', 'wp-helpdesk'),
                'new_item_name' => __('New Category Name', 'wp-helpdesk'),
                'menu_name' => __('Categories', 'wp-helpdesk')
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'ticket-category'),
            'show_in_rest' => true
        ));
    }
    
    public static function get_ticket($ticket_id) {
        $post = get_post($ticket_id);
        if (!$post || $post->post_type !== 'wphd_ticket') {
            return null;
        }
        
        return array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'status' => get_post_meta($post->ID, '_wphd_status', true),
            'category' => get_post_meta($post->ID, '_wphd_category', true),
            'priority' => get_post_meta($post->ID, '_wphd_priority', true),
            'assignee' => get_post_meta($post->ID, '_wphd_assignee', true),
            'due_date' => get_post_meta($post->ID, '_wphd_due_date', true),
            'tags' => get_post_meta($post->ID, '_wphd_tags', true) ?: array(),
            'author' => $post->post_author,
            'created' => $post->post_date,
            'modified' => $post->post_modified
        );
    }
    
    public static function get_ticket_number($ticket_id) {
        $settings = get_option('wphd_settings', array());
        $prefix = isset($settings['ticket_prefix']) ? $settings['ticket_prefix'] : 'TKT';
        return $prefix . '-' . str_pad($ticket_id, 5, '0', STR_PAD_LEFT);
    }
}

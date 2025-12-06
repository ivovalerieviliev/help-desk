<?php
/**
 * Handover Custom Post Type
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Handover_CPT {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
    }
    
    public function register_post_type() {
        $labels = array(
            'name' => 'Handovers',
            'singular_name' => 'Handover',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Handover',
            'edit_item' => 'Edit Handover',
            'view_item' => 'View Handover',
            'all_items' => 'All Handovers',
            'search_items' => 'Search Handovers'
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'author'),
            'has_archive' => false
        );
        
        register_post_type('wphd_handover', $args);
    }
}
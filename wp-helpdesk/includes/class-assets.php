<?php
/**
 * Assets Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Assets {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'wphd') === false && strpos($hook, 'helpdesk') === false) {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('wphd-admin', WPHD_PLUGIN_URL . 'assets/css/admin-style.css', array(), WPHD_VERSION);
        wp_enqueue_style('wphd-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
        
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
        
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true);
        wp_enqueue_script('wphd-admin', WPHD_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery', 'wp-color-picker', 'jquery-ui-sortable'), WPHD_VERSION, true);
        
        wp_enqueue_editor();
        
        wp_localize_script('wphd-admin', 'wphdAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('wphd/v1/'),
            'nonce' => wp_create_nonce('wphd_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this?', 'wp-helpdesk'),
                'saving' => __('Saving...', 'wp-helpdesk'),
                'saved' => __('Saved!', 'wp-helpdesk'),
                'error' => __('An error occurred', 'wp-helpdesk'),
                'loading' => __('Loading...', 'wp-helpdesk')
            )
        ));
    }
    
    public function enqueue_public_assets() {
        if (!is_singular('wphd_ticket')) {
            return;
        }
        
        wp_enqueue_style('wphd-public', WPHD_PLUGIN_URL . 'public/css/public-styles.css', array(), WPHD_VERSION);
        wp_enqueue_script('wphd-public', WPHD_PLUGIN_URL . 'public/js/public-scripts.js', array('jquery'), WPHD_VERSION, true);
        
        wp_localize_script('wphd-public', 'wphdPublic', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wphd_public_nonce')
        ));
    }
}
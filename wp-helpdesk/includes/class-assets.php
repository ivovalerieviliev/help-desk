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
        // Only enqueue public assets, admin assets are handled by WPHD_Admin_Menu
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
    }
    
    /**
     * Enqueue admin assets - NO LONGER USED, kept for backwards compatibility
     * Admin assets are now enqueued by WPHD_Admin_Menu for better control
     * 
     * @deprecated Use WPHD_Admin_Menu::enqueue_admin_assets() instead
     */
    public function enqueue_admin_assets($hook) {
        // This method is deprecated - admin assets are now enqueued by WPHD_Admin_Menu
        // Keeping this empty method for backwards compatibility
        return;
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
<?php
/**
 * Plugin Name: WP Help Desk
 * Plugin URI: https://github.com/ivovalerieviliev/help-desk
 * Description: A comprehensive WordPress ticketing and help desk system with SLA tracking, handover reports, and analytics.
 * Version: 1.0.0
 * Author: ivovalerieviliev
 * License: GPL v2 or later
 * Text Domain: wp-helpdesk
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPHD_VERSION', '1.0.0');
define('WPHD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPHD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPHD_PLUGIN_BASENAME', plugin_basename(__FILE__));

final class WP_HelpDesk {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once WPHD_PLUGIN_DIR . 'includes/class-activator.php';
        require_once WPHD_PLUGIN_DIR . 'includes/class-deactivator.php';
        require_once WPHD_PLUGIN_DIR . 'includes/class-database.php';
        require_once WPHD_PLUGIN_DIR . 'includes/class-assets.php';
        require_once WPHD_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once WPHD_PLUGIN_DIR . 'includes/class-rest-api.php';
        
        require_once WPHD_PLUGIN_DIR . 'features/tickets/class-ticket-cpt.php';
        require_once WPHD_PLUGIN_DIR . 'features/tickets/class-ticket-meta.php';
        require_once WPHD_PLUGIN_DIR . 'features/tickets/class-ticket-list.php';
        require_once WPHD_PLUGIN_DIR . 'features/tickets/class-ticket-create.php';
        require_once WPHD_PLUGIN_DIR . 'features/tickets/class-ticket-details.php';
        require_once WPHD_PLUGIN_DIR . 'features/tickets/class-ticket-comments.php';
        require_once WPHD_PLUGIN_DIR . 'features/tickets/class-ticket-history.php';
        require_once WPHD_PLUGIN_DIR . 'features/tickets/class-ticket-actions.php';
        require_once WPHD_PLUGIN_DIR . 'features/tickets/class-ticket-sla.php';
        
        require_once WPHD_PLUGIN_DIR . 'features/handover/class-handover-cpt.php';
        require_once WPHD_PLUGIN_DIR . 'features/handover/class-handover-create.php';
        require_once WPHD_PLUGIN_DIR . 'features/handover/class-handover-list.php';
        
        require_once WPHD_PLUGIN_DIR . 'features/analytics/class-analytics-dashboard.php';
        require_once WPHD_PLUGIN_DIR . 'features/analytics/class-analytics-queries.php';
        require_once WPHD_PLUGIN_DIR . 'features/analytics/class-analytics-charts.php';
        require_once WPHD_PLUGIN_DIR . 'features/analytics/class-analytics-reports.php';
        
        require_once WPHD_PLUGIN_DIR . 'features/settings/class-settings-page.php';
        require_once WPHD_PLUGIN_DIR . 'features/settings/class-settings-statuses.php';
        require_once WPHD_PLUGIN_DIR . 'features/settings/class-settings-categories.php';
        require_once WPHD_PLUGIN_DIR . 'features/settings/class-settings-priorities.php';
        require_once WPHD_PLUGIN_DIR . 'features/settings/class-settings-sla.php';
        
        require_once WPHD_PLUGIN_DIR . 'features/organizations/class-organizations.php';
        require_once WPHD_PLUGIN_DIR . 'features/organizations/class-organization-permissions.php';
        
        require_once WPHD_PLUGIN_DIR . 'admin/class-admin-menu.php';
        require_once WPHD_PLUGIN_DIR . 'admin/class-admin-pages.php';
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array('WPHD_Deactivator', 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init_plugin'));
        add_action('init', array($this, 'load_textdomain'));
        
        // Multisite: Create tables when a new site is added
        add_action('wp_initialize_site', array($this, 'on_new_site_created'), 10, 2);
    }
    
    /**
     * Plugin activation handler with multisite support.
     *
     * @param bool $network_wide Whether the plugin is being activated network-wide.
     */
    public function activate_plugin($network_wide) {
        if (is_multisite() && $network_wide) {
            // Network activation - create tables on all sites
            $sites = get_sites(array('number' => 0));
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                WPHD_Activator::activate();
                restore_current_blog();
            }
        } else {
            // Single site activation
            WPHD_Activator::activate();
        }
    }
    
    /**
     * Create tables when a new site is created in multisite.
     *
     * @param WP_Site $new_site New site object.
     * @param array   $args     Arguments for the initialization.
     */
    public function on_new_site_created($new_site, $args) {
        // Only run if plugin is network activated
        if (!is_plugin_active_for_network(WPHD_PLUGIN_BASENAME)) {
            return;
        }
        
        switch_to_blog($new_site->blog_id);
        WPHD_Activator::activate();
        restore_current_blog();
    }
    
    public function init_plugin() {
        WPHD_Database::instance();
        WPHD_Assets::instance();
        WPHD_Ajax_Handler::instance();
        WPHD_REST_API::instance();
        
        WPHD_Ticket_CPT::instance();
        WPHD_Ticket_Meta::instance();
        WPHD_Ticket_List::instance();
        WPHD_Ticket_Create::instance();
        WPHD_Ticket_Details::instance();
        WPHD_Ticket_Comments::instance();
        WPHD_Ticket_History::instance();
        WPHD_Ticket_Actions::instance();
        WPHD_Ticket_SLA::instance();
        
        WPHD_Handover_CPT::instance();
        WPHD_Handover_Create::instance();
        WPHD_Handover_List::instance();
        
        WPHD_Analytics_Dashboard::instance();
        WPHD_Analytics_Queries::instance();
        WPHD_Analytics_Charts::instance();
        
        WPHD_Settings_Page::instance();
        WPHD_Settings_Statuses::instance();
        WPHD_Settings_Categories::instance();
        WPHD_Settings_Priorities::instance();
        WPHD_Settings_SLA::instance();
        
        WPHD_Organizations::instance();
        WPHD_Organization_Permissions::instance();
        
        if (is_admin()) {
            WPHD_Admin_Menu::instance();
            WPHD_Admin_Pages::instance();
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('wp-helpdesk', false, dirname(WPHD_PLUGIN_BASENAME) . '/languages');
    }
}

function wphd() {
    return WP_HelpDesk::instance();
}

wphd();

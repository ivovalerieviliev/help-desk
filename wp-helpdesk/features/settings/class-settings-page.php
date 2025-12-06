<?php
/**
 * Settings Page Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Settings_Page {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }
    
    private function init() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function register_settings() {
        register_setting('wphd_settings', 'wphd_general_settings');
        register_setting('wphd_settings', 'wphd_email_settings');
        register_setting('wphd_settings', 'wphd_sla_settings');
    }
    
    public static function render() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ob_start();
        echo '<div class="wrap wphd-wrap">';
        echo '<h1>Help Desk Settings</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="?page=wphd-settings&tab=general" class="nav-tab ' . ($active_tab == 'general' ? 'nav-tab-active' : '') . '">General</a>';
        echo '<a href="?page=wphd-settings&tab=statuses" class="nav-tab ' . ($active_tab == 'statuses' ? 'nav-tab-active' : '') . '">Statuses</a>';
        echo '<a href="?page=wphd-settings&tab=categories" class="nav-tab ' . ($active_tab == 'categories' ? 'nav-tab-active' : '') . '">Categories</a>';
        echo '<a href="?page=wphd-settings&tab=sla" class="nav-tab ' . ($active_tab == 'sla' ? 'nav-tab-active' : '') . '">SLA</a>';
        echo '</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields('wphd_settings');
        switch ($active_tab) {
            case 'statuses':
                echo WPHD_Settings_Statuses::render();
                break;
            case 'categories':
                echo WPHD_Settings_Categories::render();
                break;
            case 'sla':
                echo WPHD_Settings_SLA::render();
                break;
            default:
                self::render_general_tab();
        }
        submit_button();
        echo '</form></div>';
        return ob_get_clean();
    }
    
    private static function render_general_tab() {
        $settings = get_option('wphd_general_settings', array());
        echo '<table class="form-table">';
        echo '<tr><th>Default Assignee</th><td>';
        wp_dropdown_users(array('name' => 'wphd_general_settings[default_assignee]', 'selected' => isset($settings['default_assignee']) ? $settings['default_assignee'] : 0));
        echo '</td></tr>';
        echo '<tr><th>Tickets Per Page</th><td><input type="number" name="wphd_general_settings[per_page]" value="' . (isset($settings['per_page']) ? intval($settings['per_page']) : 20) . '"></td></tr>';
        echo '</table>';
    }
}
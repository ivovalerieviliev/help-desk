<?php
/**
 * Plugin Deactivator
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Deactivator {
    
    public static function deactivate() {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('wphd_check_sla_breaches');
        wp_clear_scheduled_hook('wphd_send_sla_reminders');
    }
}
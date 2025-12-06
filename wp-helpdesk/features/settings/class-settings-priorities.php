<?php
/**
 * Settings Priorities Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Settings_Priorities {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_default_priorities() {
        return array(
            array('slug' => 'low', 'name' => 'Low', 'color' => '#95a5a6', 'order' => 4),
            array('slug' => 'medium', 'name' => 'Medium', 'color' => '#f39c12', 'order' => 3),
            array('slug' => 'high', 'name' => 'High', 'color' => '#e67e22', 'order' => 2),
            array('slug' => 'critical', 'name' => 'Critical', 'color' => '#e74c3c', 'order' => 1)
        );
    }
    
    public static function get_priorities() {
        $priorities = get_option('wphd_priorities', self::get_default_priorities());
        return $priorities;
    }
}

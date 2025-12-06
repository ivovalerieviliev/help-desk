<?php
/**
 * Analytics Charts Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Analytics_Charts {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }
    
    private function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_chart_scripts'));
    }
    
    public function enqueue_chart_scripts($hook) {
        if (strpos($hook, 'wphd-analytics') === false) {
            return;
        }
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        wp_enqueue_script('wphd-charts', WPHD_PLUGIN_URL . 'assets/js/charts.js', array('chart-js'), WPHD_VERSION, true);
        wp_localize_script('wphd-charts', 'wphdChartData', self::get_chart_data());
    }
    
    public static function get_chart_data() {
        return array(
            'status' => WPHD_Analytics_Queries::get_tickets_by_status(),
            'priority' => WPHD_Analytics_Queries::get_tickets_by_priority(),
            'trends' => WPHD_Analytics_Queries::get_ticket_trends()
        );
    }
}
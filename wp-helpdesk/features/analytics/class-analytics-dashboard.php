<?php
/**
 * Analytics Dashboard Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Analytics_Dashboard {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function render() {
        $stats = WPHD_Analytics_Queries::get_overview_stats();
        ob_start();
        echo '<div class="wrap wphd-wrap">';
        echo '<h1>Analytics Dashboard</h1>';
        echo '<div class="wphd-analytics-cards">';
        echo '<div class="wphd-card"><h3>Total Tickets</h3><p class="wphd-stat">' . intval($stats['total']) . '</p></div>';
        echo '<div class="wphd-card"><h3>Open Tickets</h3><p class="wphd-stat">' . intval($stats['open']) . '</p></div>';
        echo '<div class="wphd-card"><h3>Resolved Today</h3><p class="wphd-stat">' . intval($stats['resolved_today']) . '</p></div>';
        echo '<div class="wphd-card"><h3>SLA Breached</h3><p class="wphd-stat wphd-warning">' . intval($stats['sla_breached']) . '</p></div>';
        echo '</div>';
        echo '<div class="wphd-charts-row">';
        echo '<div class="wphd-chart-container"><h3>Tickets by Status</h3><canvas id="wphd-status-chart"></canvas></div>';
        echo '<div class="wphd-chart-container"><h3>Tickets by Priority</h3><canvas id="wphd-priority-chart"></canvas></div>';
        echo '</div>';
        echo '<div class="wphd-chart-container wphd-full-width"><h3>Ticket Trends</h3><canvas id="wphd-trends-chart"></canvas></div>';
        echo '</div>';
        return ob_get_clean();
    }
}
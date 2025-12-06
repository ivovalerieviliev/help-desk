<?php
/**
 * Settings SLA Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Settings_SLA {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_default_sla() {
        return array(
            'first_response' => 14400,
            'resolution' => 86400,
            'priorities' => array(
                'critical' => array('first_response' => 3600, 'resolution' => 14400),
                'high' => array('first_response' => 7200, 'resolution' => 28800),
                'medium' => array('first_response' => 14400, 'resolution' => 86400),
                'low' => array('first_response' => 28800, 'resolution' => 172800)
            )
        );
    }
    
    public static function get_sla_settings() {
        return get_option('wphd_sla_settings', self::get_default_sla());
    }
    
    public static function render() {
        $settings = self::get_sla_settings();
        ob_start();
        echo '<h3>SLA Settings</h3>';
        echo '<p>Configure Service Level Agreement times for ticket responses and resolutions.</p>';
        echo '<table class="form-table">';
        echo '<tr><th>Default First Response Time</th><td>';
        echo '<input type="number" name="wphd_sla_settings[first_response]" value="' . intval($settings['first_response']) . '"> seconds';
        echo '<p class="description">' . self::format_time($settings['first_response']) . '</p>';
        echo '</td></tr>';
        echo '<tr><th>Default Resolution Time</th><td>';
        echo '<input type="number" name="wphd_sla_settings[resolution]" value="' . intval($settings['resolution']) . '"> seconds';
        echo '<p class="description">' . self::format_time($settings['resolution']) . '</p>';
        echo '</td></tr>';
        echo '</table>';
        echo '<h4>Priority-Based SLA</h4>';
        echo '<table class="widefat">';
        echo '<thead><tr><th>Priority</th><th>First Response (seconds)</th><th>Resolution (seconds)</th></tr></thead>';
        echo '<tbody>';
        foreach ($settings['priorities'] as $priority => $times) {
            echo '<tr>';
            echo '<td>' . ucfirst($priority) . '</td>';
            echo '<td><input type="number" name="wphd_sla_settings[priorities][' . $priority . '][first_response]" value="' . intval($times['first_response']) . '"></td>';
            echo '<td><input type="number" name="wphd_sla_settings[priorities][' . $priority . '][resolution]" value="' . intval($times['resolution']) . '"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        return ob_get_clean();
    }
    
    private static function format_time($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%d hours %d minutes', $hours, $minutes);
    }
}
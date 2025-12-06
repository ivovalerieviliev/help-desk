<?php
/**
 * Settings Statuses Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Settings_Statuses {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_default_statuses() {
        return array(
            'open' => array('label' => 'Open', 'color' => '#0073aa'),
            'in_progress' => array('label' => 'In Progress', 'color' => '#ffb900'),
            'waiting' => array('label' => 'Waiting on Customer', 'color' => '#826eb4'),
            'resolved' => array('label' => 'Resolved', 'color' => '#46b450'),
            'closed' => array('label' => 'Closed', 'color' => '#666666')
        );
    }
    
    public static function get_statuses() {
        $statuses = get_option('wphd_statuses', self::get_default_statuses());
        return $statuses;
    }
    
    public static function render() {
        $statuses = self::get_statuses();
        ob_start();
        echo '<h3>Ticket Statuses</h3>';
        echo '<table class="form-table wphd-statuses-table">';
        echo '<thead><tr><th>Key</th><th>Label</th><th>Color</th><th>Actions</th></tr></thead>';
        echo '<tbody id="wphd-statuses-list">';
        foreach ($statuses as $key => $status) {
            echo '<tr data-key="' . esc_attr($key) . '">';
            echo '<td><code>' . esc_html($key) . '</code></td>';
            echo '<td><input type="text" name="wphd_statuses[' . esc_attr($key) . '][label]" value="' . esc_attr($status['label']) . '"></td>';
            echo '<td><input type="color" name="wphd_statuses[' . esc_attr($key) . '][color]" value="' . esc_attr($status['color']) . '"></td>';
            echo '<td><button type="button" class="button wphd-remove-status">Remove</button></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p><button type="button" class="button" id="wphd-add-status">Add Status</button></p>';
        return ob_get_clean();
    }
}
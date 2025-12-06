<?php
/**
 * Settings Categories Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Settings_Categories {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function get_default_categories() {
        return array(
            'general' => array('label' => 'General Inquiry', 'icon' => 'dashicons-info'),
            'technical' => array('label' => 'Technical Support', 'icon' => 'dashicons-admin-tools'),
            'billing' => array('label' => 'Billing', 'icon' => 'dashicons-money-alt'),
            'feature' => array('label' => 'Feature Request', 'icon' => 'dashicons-lightbulb'),
            'bug' => array('label' => 'Bug Report', 'icon' => 'dashicons-warning')
        );
    }
    
    public static function get_categories() {
        $categories = get_option('wphd_categories', self::get_default_categories());
        return $categories;
    }
    
    public static function render() {
        $categories = self::get_categories();
        ob_start();
        echo '<h3>Ticket Categories</h3>';
        echo '<table class="form-table wphd-categories-table">';
        echo '<thead><tr><th>Key</th><th>Label</th><th>Icon</th><th>Actions</th></tr></thead>';
        echo '<tbody id="wphd-categories-list">';
        foreach ($categories as $key => $category) {
            echo '<tr data-key="' . esc_attr($key) . '">';
            echo '<td><code>' . esc_html($key) . '</code></td>';
            echo '<td><input type="text" name="wphd_categories[' . esc_attr($key) . '][label]" value="' . esc_attr($category['label']) . '"></td>';
            echo '<td><input type="text" name="wphd_categories[' . esc_attr($key) . '][icon]" value="' . esc_attr($category['icon']) . '" class="regular-text"></td>';
            echo '<td><button type="button" class="button wphd-remove-category">Remove</button></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p><button type="button" class="button" id="wphd-add-category">Add Category</button></p>';
        return ob_get_clean();
    }
}
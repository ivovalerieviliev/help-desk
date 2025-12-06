<?php
/**
 * Settings Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Settings {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function register_settings() {
        register_setting('wphd_settings', 'wphd_settings');
        register_setting('wphd_settings', 'wphd_statuses');
        register_setting('wphd_settings', 'wphd_categories');
        register_setting('wphd_settings', 'wphd_priorities');
        register_setting('wphd_settings', 'wphd_sla_settings');
    }
    
    public static function render($tab) {
        switch ($tab) {
            case 'statuses':
                return self::render_statuses();
            case 'categories':
                return self::render_categories();
            case 'sla':
                return self::render_sla();
            default:
                return self::render_general();
        }
    }
    
    public static function render_general() {
        $settings = get_option('wphd_settings', array());
        ob_start();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('wphd_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="ticket_prefix"><?php _e('Ticket Prefix', 'wp-helpdesk'); ?></label></th>
                    <td>
                        <input type="text" id="ticket_prefix" name="wphd_settings[ticket_prefix]" value="<?php echo esc_attr($settings['ticket_prefix'] ?? 'TKT'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tickets_per_page"><?php _e('Tickets Per Page', 'wp-helpdesk'); ?></label></th>
                    <td>
                        <input type="number" id="tickets_per_page" name="wphd_settings[tickets_per_page]" value="<?php echo esc_attr($settings['tickets_per_page'] ?? 20); ?>">
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
        return ob_get_clean();
    }
    
    public static function render_statuses() {
        $statuses = get_option('wphd_statuses', array());
        ob_start();
        ?>
        <div class="wphd-settings-statuses">
            <table class="widefat" id="wphd-statuses-table">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'wp-helpdesk'); ?></th>
                        <th><?php _e('Color', 'wp-helpdesk'); ?></th>
                        <th><?php _e('Actions', 'wp-helpdesk'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statuses as $status) : ?>
                        <tr>
                            <td><?php echo esc_html($status['name']); ?></td>
                            <td><span style="background:<?php echo esc_attr($status['color']); ?>;">&nbsp;</span></td>
                            <td><button class="button"><?php _e('Edit', 'wp-helpdesk'); ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public static function render_categories() {
        $categories = get_option('wphd_categories', array());
        ob_start();
        ?>
        <div class="wphd-settings-categories">
            <table class="widefat" id="wphd-categories-table">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'wp-helpdesk'); ?></th>
                        <th><?php _e('Slug', 'wp-helpdesk'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category) : ?>
                        <tr>
                            <td><?php echo esc_html($category['name']); ?></td>
                            <td><?php echo esc_html($category['slug']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public static function render_sla() {
        $sla = get_option('wphd_sla_settings', array());
        ob_start();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('wphd_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('First Response Time', 'wp-helpdesk'); ?></th>
                    <td>
                        <input type="number" name="wphd_sla_settings[first_response]" value="<?php echo esc_attr(($sla['first_response'] ?? 14400) / 3600); ?>"> <?php _e('hours', 'wp-helpdesk'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Resolution Time', 'wp-helpdesk'); ?></th>
                    <td>
                        <input type="number" name="wphd_sla_settings[resolution]" value="<?php echo esc_attr(($sla['resolution'] ?? 86400) / 3600); ?>"> <?php _e('hours', 'wp-helpdesk'); ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
        return ob_get_clean();
    }
}
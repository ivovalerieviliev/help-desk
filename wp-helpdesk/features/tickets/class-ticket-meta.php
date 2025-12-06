<?php
/**
 * Ticket Meta Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Ticket_Meta {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_wphd_ticket', array($this, 'save_meta'));
    }
    
    public function add_meta_boxes() {
        add_meta_box('wphd_ticket_details', __('Ticket Details', 'wp-helpdesk'), array($this, 'render_details_box'), 'wphd_ticket', 'side', 'high');
        add_meta_box('wphd_ticket_sla', __('SLA Information', 'wp-helpdesk'), array($this, 'render_sla_box'), 'wphd_ticket', 'side', 'default');
    }
    
    public function render_details_box($post) {
        wp_nonce_field('wphd_ticket_meta', 'wphd_ticket_meta_nonce');
        
        $status = get_post_meta($post->ID, '_wphd_status', true);
        $category = get_post_meta($post->ID, '_wphd_category', true);
        $priority = get_post_meta($post->ID, '_wphd_priority', true);
        $assignee = get_post_meta($post->ID, '_wphd_assignee', true);
        $due_date = get_post_meta($post->ID, '_wphd_due_date', true);
        
        $statuses = get_option('wphd_statuses', array());
        $categories = get_option('wphd_categories', array());
        $priorities = get_option('wphd_priorities', array());
        
        $users = get_users(array('role__in' => array('administrator', 'editor')));
        ?>
        <p>
            <label for="wphd_status"><strong><?php _e('Status', 'wp-helpdesk'); ?></strong></label>
            <select name="wphd_status" id="wphd_status" class="widefat">
                <?php foreach ($statuses as $s) : ?>
                    <option value="<?php echo esc_attr($s['slug']); ?>" <?php selected($status, $s['slug']); ?>><?php echo esc_html($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="wphd_category"><strong><?php _e('Category', 'wp-helpdesk'); ?></strong></label>
            <select name="wphd_category" id="wphd_category" class="widefat">
                <option value=""><?php _e('Select Category', 'wp-helpdesk'); ?></option>
                <?php foreach ($categories as $c) : ?>
                    <option value="<?php echo esc_attr($c['slug']); ?>" <?php selected($category, $c['slug']); ?>><?php echo esc_html($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="wphd_priority"><strong><?php _e('Priority', 'wp-helpdesk'); ?></strong></label>
            <select name="wphd_priority" id="wphd_priority" class="widefat">
                <?php foreach ($priorities as $p) : ?>
                    <option value="<?php echo esc_attr($p['slug']); ?>" <?php selected($priority, $p['slug']); ?>><?php echo esc_html($p['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="wphd_assignee"><strong><?php _e('Assignee', 'wp-helpdesk'); ?></strong></label>
            <select name="wphd_assignee" id="wphd_assignee" class="widefat">
                <option value="0"><?php _e('Unassigned', 'wp-helpdesk'); ?></option>
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($assignee, $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="wphd_due_date"><strong><?php _e('Due Date', 'wp-helpdesk'); ?></strong></label>
            <input type="datetime-local" name="wphd_due_date" id="wphd_due_date" class="widefat" value="<?php echo esc_attr($due_date); ?>">
        </p>
        <?php
    }
    
    public function render_sla_box($post) {
        $sla = WPHD_Database::get_sla($post->ID);
        if (!$sla) {
            echo '<p>' . __('No SLA data available', 'wp-helpdesk') . '</p>';
            return;
        }
        
        $now = current_time('timestamp');
        ?>
        <div class="wphd-sla-info">
            <p>
                <strong><?php _e('First Response', 'wp-helpdesk'); ?></strong><br>
                <?php if ($sla->first_response_at) : ?>
                    <span class="wphd-sla-met"><?php _e('Met:', 'wp-helpdesk'); ?> <?php echo esc_html($sla->first_response_at); ?></span>
                <?php else : ?>
                    <?php 
                    $remaining = strtotime($sla->first_response_due) - $now;
                    $class = $remaining < 0 ? 'wphd-sla-breached' : ($remaining < 3600 ? 'wphd-sla-warning' : 'wphd-sla-ok');
                    ?>
                    <span class="<?php echo $class; ?>">
                        <?php _e('Due:', 'wp-helpdesk'); ?> <?php echo esc_html($sla->first_response_due); ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
    
    public function save_meta($post_id) {
        if (!isset($_POST['wphd_ticket_meta_nonce']) || !wp_verify_nonce($_POST['wphd_ticket_meta_nonce'], 'wphd_ticket_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $fields = array('status', 'category', 'priority', 'assignee', 'due_date');
        
        foreach ($fields as $field) {
            if (isset($_POST['wphd_' . $field])) {
                $old_value = get_post_meta($post_id, '_wphd_' . $field, true);
                $new_value = sanitize_text_field($_POST['wphd_' . $field]);
                
                if ($old_value !== $new_value) {
                    update_post_meta($post_id, '_wphd_' . $field, $new_value);
                    WPHD_Database::add_history($post_id, $field, $old_value, $new_value);
                }
            }
        }
    }
    
    public static function get_status_info($slug) {
        $statuses = get_option('wphd_statuses', array());
        foreach ($statuses as $status) {
            if ($status['slug'] === $slug) {
                return $status;
            }
        }
        return null;
    }
    
    public static function get_priority_info($slug) {
        $priorities = get_option('wphd_priorities', array());
        foreach ($priorities as $priority) {
            if ($priority['slug'] === $slug) {
                return $priority;
            }
        }
        return null;
    }
}
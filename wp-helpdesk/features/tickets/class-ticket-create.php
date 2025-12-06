<?php
/**
 * Ticket Create Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Ticket_Create {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Initialization
    }
    
    public static function create_ticket($data) {
        if (empty($data['title'])) {
            return new WP_Error('missing_title', __('Title is required', 'wp-helpdesk'));
        }
        
        $statuses = get_option('wphd_statuses', array());
        $default_status = 'new';
        foreach ($statuses as $status) {
            if (!empty($status['is_default'])) {
                $default_status = $status['slug'];
                break;
            }
        }
        
        $ticket_id = wp_insert_post(array(
            'post_type' => 'wphd_ticket',
            'post_title' => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ));
        
        if (is_wp_error($ticket_id)) {
            return $ticket_id;
        }
        
        update_post_meta($ticket_id, '_wphd_status', $default_status);
        update_post_meta($ticket_id, '_wphd_category', sanitize_text_field($data['category'] ?? ''));
        update_post_meta($ticket_id, '_wphd_priority', sanitize_text_field($data['priority'] ?? 'medium'));
        update_post_meta($ticket_id, '_wphd_assignee', intval($data['assignee'] ?? 0));
        
        if (!empty($data['due_date'])) {
            update_post_meta($ticket_id, '_wphd_due_date', sanitize_text_field($data['due_date']));
        }
        
        $sla_settings = get_option('wphd_sla_settings', array());
        $first_response = isset($sla_settings['first_response']) ? $sla_settings['first_response'] : 4 * HOUR_IN_SECONDS;
        $resolution = isset($sla_settings['resolution']) ? $sla_settings['resolution'] : 24 * HOUR_IN_SECONDS;
        
        $now = current_time('mysql');
        WPHD_Database::create_sla(
            $ticket_id,
            date('Y-m-d H:i:s', strtotime($now) + $first_response),
            date('Y-m-d H:i:s', strtotime($now) + $resolution)
        );
        
        WPHD_Database::add_history($ticket_id, 'created', '', $data['title']);
        
        do_action('wphd_ticket_created', $ticket_id, $data);
        
        return $ticket_id;
    }
    
    public static function render_form() {
        $categories = get_option('wphd_categories', array());
        $priorities = get_option('wphd_priorities', array());
        $users = get_users(array('role__in' => array('administrator', 'editor')));
        
        ob_start();
        ?>
        <div class="wphd-create-ticket-form">
            <form id="wphd-new-ticket-form" method="post">
                <?php wp_nonce_field('wphd_nonce', 'wphd_nonce'); ?>
                
                <div class="wphd-form-row">
                    <label for="wphd-title"><?php _e('Title', 'wp-helpdesk'); ?> <span class="required">*</span></label>
                    <input type="text" id="wphd-title" name="title" required class="widefat">
                </div>
                
                <div class="wphd-form-row">
                    <label for="wphd-content"><?php _e('Description', 'wp-helpdesk'); ?></label>
                    <?php wp_editor('', 'wphd-content', array(
                        'textarea_name' => 'content',
                        'textarea_rows' => 10,
                        'media_buttons' => true
                    )); ?>
                </div>
                
                <div class="wphd-form-columns">
                    <div class="wphd-form-column">
                        <div class="wphd-form-row">
                            <label for="wphd-category"><?php _e('Category', 'wp-helpdesk'); ?></label>
                            <select id="wphd-category" name="category" class="widefat">
                                <option value=""><?php _e('Select Category', 'wp-helpdesk'); ?></option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat['slug']); ?>"><?php echo esc_html($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="wphd-form-row">
                            <label for="wphd-priority"><?php _e('Priority', 'wp-helpdesk'); ?></label>
                            <select id="wphd-priority" name="priority" class="widefat">
                                <?php foreach ($priorities as $p) : ?>
                                    <option value="<?php echo esc_attr($p['slug']); ?>"><?php echo esc_html($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="wphd-form-column">
                        <div class="wphd-form-row">
                            <label for="wphd-assignee"><?php _e('Assignee', 'wp-helpdesk'); ?></label>
                            <select id="wphd-assignee" name="assignee" class="widefat">
                                <option value="0"><?php _e('Unassigned', 'wp-helpdesk'); ?></option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="wphd-form-row">
                            <label for="wphd-due-date"><?php _e('Due Date', 'wp-helpdesk'); ?></label>
                            <input type="datetime-local" id="wphd-due-date" name="due_date" class="widefat">
                        </div>
                    </div>
                </div>
                
                <div class="wphd-form-row wphd-form-actions">
                    <button type="submit" class="button button-primary button-large"><?php _e('Create Ticket', 'wp-helpdesk'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=wphd-tickets'); ?>" class="button button-large"><?php _e('Cancel', 'wp-helpdesk'); ?></a>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
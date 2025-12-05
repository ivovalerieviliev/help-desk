<?php
/**
 * AJAX Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Ajax_Handler {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('wp_ajax_wphd_create_ticket', array($this, 'create_ticket'));
        add_action('wp_ajax_wphd_update_ticket', array($this, 'update_ticket'));
        add_action('wp_ajax_wphd_delete_ticket', array($this, 'delete_ticket'));
        add_action('wp_ajax_wphd_get_tickets', array($this, 'get_tickets'));
        add_action('wp_ajax_wphd_change_status', array($this, 'change_status'));
        add_action('wp_ajax_wphd_assign_ticket', array($this, 'assign_ticket'));
        add_action('wp_ajax_wphd_add_comment', array($this, 'add_comment'));
        add_action('wp_ajax_wphd_update_comment', array($this, 'update_comment'));
        add_action('wp_ajax_wphd_delete_comment', array($this, 'delete_comment'));
        add_action('wp_ajax_wphd_add_action_item', array($this, 'add_action_item'));
        add_action('wp_ajax_wphd_update_action_item', array($this, 'update_action_item'));
        add_action('wp_ajax_wphd_delete_action_item', array($this, 'delete_action_item'));
        add_action('wp_ajax_wphd_complete_action_item', array($this, 'complete_action_item'));
        add_action('wp_ajax_wphd_create_handover', array($this, 'create_handover'));
        add_action('wp_ajax_wphd_update_handover', array($this, 'update_handover'));
        add_action('wp_ajax_wphd_delete_handover', array($this, 'delete_handover'));
        add_action('wp_ajax_wphd_search_tickets', array($this, 'search_tickets'));
        add_action('wp_ajax_wphd_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_wphd_save_statuses', array($this, 'save_statuses'));
        add_action('wp_ajax_wphd_save_categories', array($this, 'save_categories'));
        add_action('wp_ajax_wphd_get_analytics', array($this, 'get_analytics'));
        add_action('wp_ajax_wphd_export_csv', array($this, 'export_csv'));
    }
    
    private function verify_nonce() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wphd_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-helpdesk')));
        }
    }
    
    private function check_capability($cap = 'manage_wphd_tickets') {
        if (!current_user_can($cap)) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-helpdesk')));
        }
    }
    
    public function create_ticket() {
        $this->verify_nonce();
        $this->check_capability('create_wphd_tickets');
        
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        $priority = sanitize_text_field($_POST['priority'] ?? 'medium');
        $assignee = intval($_POST['assignee'] ?? 0);
        
        if (empty($title)) {
            wp_send_json_error(array('message' => __('Title is required', 'wp-helpdesk')));
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
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish'
        ));
        
        if (is_wp_error($ticket_id)) {
            wp_send_json_error(array('message' => $ticket_id->get_error_message()));
        }
        
        update_post_meta($ticket_id, '_wphd_status', $default_status);
        update_post_meta($ticket_id, '_wphd_category', $category);
        update_post_meta($ticket_id, '_wphd_priority', $priority);
        update_post_meta($ticket_id, '_wphd_assignee', $assignee);
        
        $sla_settings = get_option('wphd_sla_settings', array());
        $first_response = isset($sla_settings['first_response']) ? $sla_settings['first_response'] : 4 * HOUR_IN_SECONDS;
        $resolution = isset($sla_settings['resolution']) ? $sla_settings['resolution'] : 24 * HOUR_IN_SECONDS;
        
        $now = current_time('mysql');
        WPHD_Database::create_sla($ticket_id, date('Y-m-d H:i:s', strtotime($now) + $first_response), date('Y-m-d H:i:s', strtotime($now) + $resolution));
        WPHD_Database::add_history($ticket_id, 'created', '', $title);
        
        do_action('wphd_ticket_created', $ticket_id);
        
        wp_send_json_success(array('message' => __('Ticket created successfully', 'wp-helpdesk'), 'ticket_id' => $ticket_id));
    }
    
    public function update_ticket() {
        $this->verify_nonce();
        $this->check_capability('edit_wphd_tickets');
        
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        if (!$ticket_id) {
            wp_send_json_error(array('message' => __('Invalid ticket', 'wp-helpdesk')));
        }
        
        $updates = array('ID' => $ticket_id);
        if (isset($_POST['title'])) $updates['post_title'] = sanitize_text_field($_POST['title']);
        if (isset($_POST['content'])) $updates['post_content'] = wp_kses_post($_POST['content']);
        
        wp_update_post($updates);
        
        $meta_fields = array('category', 'priority', 'assignee', 'due_date', 'tags');
        foreach ($meta_fields as $field) {
            if (isset($_POST[$field])) {
                $old_value = get_post_meta($ticket_id, '_wphd_' . $field, true);
                $new_value = is_array($_POST[$field]) ? array_map('sanitize_text_field', $_POST[$field]) : sanitize_text_field($_POST[$field]);
                if ($old_value !== $new_value) {
                    update_post_meta($ticket_id, '_wphd_' . $field, $new_value);
                    WPHD_Database::add_history($ticket_id, $field, maybe_serialize($old_value), maybe_serialize($new_value));
                }
            }
        }
        
        do_action('wphd_ticket_updated', $ticket_id);
        wp_send_json_success(array('message' => __('Ticket updated successfully', 'wp-helpdesk')));
    }
    
    public function delete_ticket() {
        $this->verify_nonce();
        $this->check_capability('delete_wphd_tickets');
        
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        if (!$ticket_id) {
            wp_send_json_error(array('message' => __('Invalid ticket', 'wp-helpdesk')));
        }
        
        wp_delete_post($ticket_id, true);
        wp_send_json_success(array('message' => __('Ticket deleted successfully', 'wp-helpdesk')));
    }
    
    public function get_tickets() {
        $this->verify_nonce();
        $this->check_capability('manage_wphd_tickets');
        
        $args = array(
            'post_type' => 'wphd_ticket',
            'posts_per_page' => intval($_POST['per_page'] ?? 20),
            'paged' => intval($_POST['page'] ?? 1),
            'orderby' => sanitize_text_field($_POST['orderby'] ?? 'date'),
            'order' => sanitize_text_field($_POST['order'] ?? 'DESC')
        );
        
        $query = new WP_Query($args);
        $tickets = array();
        
        foreach ($query->posts as $post) {
            $tickets[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => get_post_meta($post->ID, '_wphd_status', true),
                'category' => get_post_meta($post->ID, '_wphd_category', true),
                'priority' => get_post_meta($post->ID, '_wphd_priority', true),
                'assignee' => get_post_meta($post->ID, '_wphd_assignee', true),
                'created' => $post->post_date
            );
        }
        
        wp_send_json_success(array('tickets' => $tickets, 'total' => $query->found_posts, 'pages' => $query->max_num_pages));
    }
    
    public function change_status() {
        $this->verify_nonce();
        $this->check_capability('edit_wphd_tickets');
        
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');
        
        $old_status = get_post_meta($ticket_id, '_wphd_status', true);
        update_post_meta($ticket_id, '_wphd_status', $new_status);
        WPHD_Database::add_history($ticket_id, 'status', $old_status, $new_status);
        
        do_action('wphd_ticket_status_changed', $ticket_id, $old_status, $new_status);
        wp_send_json_success(array('message' => __('Status updated', 'wp-helpdesk')));
    }
    
    public function assign_ticket() {
        $this->verify_nonce();
        $this->check_capability('edit_wphd_tickets');
        
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $assignee = intval($_POST['assignee'] ?? 0);
        
        $old_assignee = get_post_meta($ticket_id, '_wphd_assignee', true);
        update_post_meta($ticket_id, '_wphd_assignee', $assignee);
        WPHD_Database::add_history($ticket_id, 'assignee', $old_assignee, $assignee);
        
        do_action('wphd_ticket_assigned', $ticket_id, $assignee, $old_assignee);
        wp_send_json_success(array('message' => __('Ticket assigned', 'wp-helpdesk')));
    }
    
    public function add_comment() {
        $this->verify_nonce();
        $this->check_capability('edit_wphd_tickets');
        
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $content = wp_kses_post($_POST['content'] ?? '');
        $is_internal = intval($_POST['is_internal'] ?? 0);
        
        $comment_id = WPHD_Database::add_comment(array('ticket_id' => $ticket_id, 'content' => $content, 'is_internal' => $is_internal));
        
        if ($comment_id) {
            $sla = WPHD_Database::get_sla($ticket_id);
            if ($sla && empty($sla->first_response_at)) {
                WPHD_Database::update_sla($ticket_id, array('first_response_at' => current_time('mysql')));
            }
            wp_send_json_success(array('message' => __('Comment added', 'wp-helpdesk'), 'comment_id' => $comment_id));
        }
        wp_send_json_error(array('message' => __('Failed to add comment', 'wp-helpdesk')));
    }
    
    public function update_comment() {
        $this->verify_nonce();
        $comment_id = intval($_POST['comment_id'] ?? 0);
        $content = wp_kses_post($_POST['content'] ?? '');
        WPHD_Database::update_comment($comment_id, array('content' => $content));
        wp_send_json_success(array('message' => __('Comment updated', 'wp-helpdesk')));
    }
    
    public function delete_comment() {
        $this->verify_nonce();
        $comment_id = intval($_POST['comment_id'] ?? 0);
        WPHD_Database::delete_comment($comment_id);
        wp_send_json_success(array('message' => __('Comment deleted', 'wp-helpdesk')));
    }
    
    public function add_action_item() {
        $this->verify_nonce();
        $data = array(
            'ticket_id' => intval($_POST['ticket_id'] ?? 0),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'assigned_to' => intval($_POST['assigned_to'] ?? 0),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? '')
        );
        $item_id = WPHD_Database::add_action_item($data);
        if ($item_id) {
            wp_send_json_success(array('message' => __('Action item added', 'wp-helpdesk'), 'item_id' => $item_id));
        }
        wp_send_json_error(array('message' => __('Failed to add action item', 'wp-helpdesk')));
    }
    
    public function update_action_item() {
        $this->verify_nonce();
        $item_id = intval($_POST['item_id'] ?? 0);
        $data = array();
        if (isset($_POST['title'])) $data['title'] = sanitize_text_field($_POST['title']);
        if (isset($_POST['description'])) $data['description'] = sanitize_textarea_field($_POST['description']);
        if (isset($_POST['assigned_to'])) $data['assigned_to'] = intval($_POST['assigned_to']);
        if (isset($_POST['due_date'])) $data['due_date'] = sanitize_text_field($_POST['due_date']);
        WPHD_Database::update_action_item($item_id, $data);
        wp_send_json_success(array('message' => __('Action item updated', 'wp-helpdesk')));
    }
    
    public function delete_action_item() {
        $this->verify_nonce();
        $item_id = intval($_POST['item_id'] ?? 0);
        WPHD_Database::delete_action_item($item_id);
        wp_send_json_success(array('message' => __('Action item deleted', 'wp-helpdesk')));
    }
    
    public function complete_action_item() {
        $this->verify_nonce();
        $item_id = intval($_POST['item_id'] ?? 0);
        $completed = intval($_POST['completed'] ?? 1);
        WPHD_Database::update_action_item($item_id, array('is_completed' => $completed));
        wp_send_json_success(array('message' => __('Action item updated', 'wp-helpdesk')));
    }
    
    public function create_handover() {
        $this->verify_nonce();
        $this->check_capability('create_wphd_handovers');
        
        $data = array(
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'shift_name' => sanitize_text_field($_POST['shift_name'] ?? ''),
            'shift_start' => sanitize_text_field($_POST['shift_start'] ?? ''),
            'shift_end' => sanitize_text_field($_POST['shift_end'] ?? ''),
            'notes' => wp_kses_post($_POST['notes'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'draft')
        );
        
        $handover_id = WPHD_Database::create_handover($data);
        
        if ($handover_id && !empty($_POST['tickets'])) {
            $order = 0;
            foreach ((array)$_POST['tickets'] as $ticket) {
                $ticket_id = intval($ticket['id'] ?? 0);
                $notes = sanitize_textarea_field($ticket['notes'] ?? '');
                if ($ticket_id) {
                    WPHD_Database::add_handover_ticket($handover_id, $ticket_id, $notes, $order++);
                }
            }
        }
        
        if ($handover_id) {
            wp_send_json_success(array('message' => __('Handover created', 'wp-helpdesk'), 'handover_id' => $handover_id));
        }
        wp_send_json_error(array('message' => __('Failed to create handover', 'wp-helpdesk')));
    }
    
    public function update_handover() {
        $this->verify_nonce();
        $handover_id = intval($_POST['handover_id'] ?? 0);
        $data = array();
        if (isset($_POST['title'])) $data['title'] = sanitize_text_field($_POST['title']);
        if (isset($_POST['notes'])) $data['notes'] = wp_kses_post($_POST['notes']);
        if (isset($_POST['status'])) $data['status'] = sanitize_text_field($_POST['status']);
        if (isset($_POST['reviewed']) && $_POST['reviewed']) {
            $data['reviewed_by'] = get_current_user_id();
            $data['reviewed_at'] = current_time('mysql');
        }
        WPHD_Database::update_handover($handover_id, $data);
        wp_send_json_success(array('message' => __('Handover updated', 'wp-helpdesk')));
    }
    
    public function delete_handover() {
        $this->verify_nonce();
        $handover_id = intval($_POST['handover_id'] ?? 0);
        WPHD_Database::delete_handover($handover_id);
        wp_send_json_success(array('message' => __('Handover deleted', 'wp-helpdesk')));
    }
    
    public function search_tickets() {
        $this->verify_nonce();
        $search = sanitize_text_field($_POST['search'] ?? '');
        $args = array('post_type' => 'wphd_ticket', 'posts_per_page' => 20, 's' => $search);
        $query = new WP_Query($args);
        $tickets = array();
        foreach ($query->posts as $post) {
            $tickets[] = array('id' => $post->ID, 'title' => $post->post_title, 'status' => get_post_meta($post->ID, '_wphd_status', true));
        }
        wp_send_json_success(array('tickets' => $tickets));
    }
    
    public function save_settings() {
        $this->verify_nonce();
        $this->check_capability('manage_wphd_settings');
        $settings = array(
            'ticket_prefix' => sanitize_text_field($_POST['ticket_prefix'] ?? 'TKT'),
            'items_per_page' => intval($_POST['items_per_page'] ?? 20),
            'enable_email_notifications' => !empty($_POST['enable_email_notifications']),
            'default_assignee' => intval($_POST['default_assignee'] ?? 0),
            'allow_attachments' => !empty($_POST['allow_attachments']),
            'max_attachment_size' => intval($_POST['max_attachment_size'] ?? 5) * MB_IN_BYTES
        );
        update_option('wphd_settings', $settings);
        wp_send_json_success(array('message' => __('Settings saved', 'wp-helpdesk')));
    }
    
    public function save_statuses() {
        $this->verify_nonce();
        $this->check_capability('manage_wphd_settings');
        $statuses = array();
        if (!empty($_POST['statuses']) && is_array($_POST['statuses'])) {
            foreach ($_POST['statuses'] as $status) {
                $statuses[] = array(
                    'slug' => sanitize_title($status['slug'] ?? ''),
                    'name' => sanitize_text_field($status['name'] ?? ''),
                    'color' => sanitize_hex_color($status['color'] ?? '#3498db'),
                    'order' => intval($status['order'] ?? 0),
                    'is_default' => !empty($status['is_default']),
                    'is_closed' => !empty($status['is_closed']),
                    'pauses_sla' => !empty($status['pauses_sla'])
                );
            }
        }
        update_option('wphd_statuses', $statuses);
        wp_send_json_success(array('message' => __('Statuses saved', 'wp-helpdesk')));
    }
    
    public function save_categories() {
        $this->verify_nonce();
        $this->check_capability('manage_wphd_settings');
        $categories = array();
        if (!empty($_POST['categories']) && is_array($_POST['categories'])) {
            foreach ($_POST['categories'] as $cat) {
                $categories[] = array(
                    'slug' => sanitize_title($cat['slug'] ?? ''),
                    'name' => sanitize_text_field($cat['name'] ?? ''),
                    'color' => sanitize_hex_color($cat['color'] ?? '#3498db'),
                    'icon' => sanitize_text_field($cat['icon'] ?? 'dashicons-admin-generic')
                );
            }
        }
        update_option('wphd_categories', $categories);
        wp_send_json_success(array('message' => __('Categories saved', 'wp-helpdesk')));
    }
    
    public function get_analytics() {
        $this->verify_nonce();
        $this->check_capability('view_wphd_analytics');
        $period = sanitize_text_field($_POST['period'] ?? 'week');
        $data = WPHD_Analytics_Queries::get_dashboard_data($period);
        wp_send_json_success($data);
    }
    
    public function export_csv() {
        $this->verify_nonce();
        $this->check_capability('view_wphd_analytics');
        $type = sanitize_text_field($_POST['type'] ?? 'tickets');
        $data = WPHD_Analytics_Queries::get_export_data($type);
        wp_send_json_success(array('data' => $data));
    }
}

<?php
/**
 * Database Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Database {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'check_db_update'));
        add_action('admin_init', array($this, 'maybe_create_tables'), 1); // Priority 1 = runs first
    }
    
    /**
     * Check and auto-create missing tables (runs once per hour per site).
     *
     * @since 1.0.0
     */
    public function maybe_create_tables() {
        // Get page parameter once at the top
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $is_plugin_page = $page && strpos($page, 'wp-helpdesk') !== false;
        $is_form_submission = isset($_POST['action']) && !empty($_POST['action']);
        
        // Always create tables if a form is being submitted on our pages
        if ($is_plugin_page && $is_form_submission) {
            // Skip transient, always verify tables on form submission
            WPHD_Activator::create_tables();
            return;
        }
        
        // Regular transient-based check for other cases
        $transient_key = 'wphd_tables_verified_' . get_current_blog_id();
        
        // Don't use transient if we're on an admin page for the plugin
        // This ensures tables are always checked when user is actively using the plugin
        if (!$is_plugin_page && get_transient($transient_key)) {
            return;
        }
        
        global $wpdb;
        $tables_to_check = array(
            $wpdb->prefix . 'wphd_comments',
            $wpdb->prefix . 'wphd_history',
            $wpdb->prefix . 'wphd_sla_log',
            $wpdb->prefix . 'wphd_action_items',
            $wpdb->prefix . 'wphd_handovers',
            $wpdb->prefix . 'wphd_handover_tickets',
            $wpdb->prefix . 'wphd_ticket_meta',
            $wpdb->prefix . 'wphd_organizations',
            $wpdb->prefix . 'wphd_organization_members',
            $wpdb->prefix . 'wphd_organization_logs',
        );
        
        // Get all existing tables in a single query
        $existing_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}wphd_%'");
        
        // Check if any required tables are missing
        $missing_tables = false;
        foreach ($tables_to_check as $table) {
            if (!in_array($table, $existing_tables, true)) {
                $missing_tables = true;
                break;
            }
        }
        
        if ($missing_tables) {
            WPHD_Activator::create_tables();
        }
        
        set_transient($transient_key, true, HOUR_IN_SECONDS);
    }
    
    /**
     * Force table creation (called directly when needed).
     * 
     * This method bypasses the transient caching and forces table creation.
     * Use this when you need to ensure tables are created immediately, such as
     * during plugin activation or database repair operations.
     * 
     * Note: This method uses dbDelta which is idempotent and safe to run multiple times.
     * No error handling is performed as dbDelta will handle SQL errors gracefully and
     * will only create or update tables that need changes.
     *
     * @since 1.0.0
     * @return void
     */
    public function force_create_tables() {
        WPHD_Activator::create_tables();
        
        // Clear the transient to force a recheck next time
        delete_transient('wphd_tables_verified_' . get_current_blog_id());
    }
    
    /**
     * Check if a specific table exists.
     *
     * @since 1.0.0
     * @param string $table_name Full table name with prefix.
     * @return bool True if table exists, false otherwise.
     */
    public static function check_table_exists($table_name) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
    }
    
    public function check_db_update() {
        $current_version = get_option('wphd_db_version', '0');
        if (version_compare($current_version, WPHD_VERSION, '<')) {
            WPHD_Activator::activate();
        }
    }
    
    public static function get_comments($ticket_id, $include_internal = true) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_comments';
        
        // Check if current user can view internal comments
        if (!$include_internal || !WPHD_Organization_Permissions::can_view_internal_comments()) {
            $include_internal = false;
        }
        
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE ticket_id = %d", $ticket_id);
        if (!$include_internal) {
            $sql .= " AND is_internal = 0";
        }
        $sql .= " ORDER BY created_at ASC";
        
        return $wpdb->get_results($sql);
    }
    
    public static function add_comment($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_comments';
        
        $result = $wpdb->insert($table, array(
            'ticket_id' => $data['ticket_id'],
            'user_id' => get_current_user_id(),
            'content' => $data['content'],
            'is_internal' => isset($data['is_internal']) ? $data['is_internal'] : 0,
            'attachments' => isset($data['attachments']) ? maybe_serialize($data['attachments']) : ''
        ));
        
        if ($result) {
            do_action('wphd_comment_added', $wpdb->insert_id, $data);
            return $wpdb->insert_id;
        }
        return false;
    }
    
    public static function update_comment($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_comments';
        
        return $wpdb->update($table, array(
            'content' => $data['content'],
            'is_internal' => isset($data['is_internal']) ? $data['is_internal'] : 0
        ), array('id' => $id));
    }
    
    public static function delete_comment($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_comments';
        return $wpdb->delete($table, array('id' => $id));
    }
    
    public static function get_history($ticket_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_history';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE ticket_id = %d ORDER BY created_at DESC",
            $ticket_id
        ));
    }
    
    public static function add_history($ticket_id, $field, $old_value, $new_value) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_history';
        
        return $wpdb->insert($table, array(
            'ticket_id' => $ticket_id,
            'user_id' => get_current_user_id(),
            'field_name' => $field,
            'old_value' => $old_value,
            'new_value' => $new_value
        ));
    }
    
    public static function get_action_items($ticket_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_action_items';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE ticket_id = %d ORDER BY created_at ASC",
            $ticket_id
        ));
    }
    
    public static function add_action_item($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_action_items';
        
        $result = $wpdb->insert($table, array(
            'ticket_id' => $data['ticket_id'],
            'title' => $data['title'],
            'description' => isset($data['description']) ? $data['description'] : '',
            'assigned_to' => isset($data['assigned_to']) ? $data['assigned_to'] : null,
            'due_date' => isset($data['due_date']) ? $data['due_date'] : null,
            'created_by' => get_current_user_id()
        ));
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function update_action_item($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_action_items';
        
        $update_data = array();
        if (isset($data['title'])) $update_data['title'] = $data['title'];
        if (isset($data['description'])) $update_data['description'] = $data['description'];
        if (isset($data['assigned_to'])) $update_data['assigned_to'] = $data['assigned_to'];
        if (isset($data['due_date'])) $update_data['due_date'] = $data['due_date'];
        if (isset($data['is_completed'])) {
            $update_data['is_completed'] = $data['is_completed'];
            if ($data['is_completed']) {
                $update_data['completed_at'] = current_time('mysql');
                $update_data['completed_by'] = get_current_user_id();
            }
        }
        
        return $wpdb->update($table, $update_data, array('id' => $id));
    }
    
    public static function delete_action_item($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_action_items';
        return $wpdb->delete($table, array('id' => $id));
    }
    
    public static function get_sla($ticket_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_sla_log';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE ticket_id = %d",
            $ticket_id
        ));
    }
    
    public static function create_sla($ticket_id, $first_response_due, $resolution_due) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_sla_log';
        
        return $wpdb->insert($table, array(
            'ticket_id' => $ticket_id,
            'first_response_due' => $first_response_due,
            'resolution_due' => $resolution_due
        ));
    }
    
    public static function update_sla($ticket_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_sla_log';
        
        return $wpdb->update($table, $data, array('ticket_id' => $ticket_id));
    }
    
    public static function get_handovers($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handovers';
        
        $defaults = array(
            'status' => '',
            'created_by' => 0,
            'date_from' => '',
            'date_to' => '',
            'limit' => 20,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM $table WHERE 1=1";
        
        if ($args['status']) {
            $sql .= $wpdb->prepare(" AND status = %s", $args['status']);
        }
        if ($args['created_by']) {
            $sql .= $wpdb->prepare(" AND created_by = %d", $args['created_by']);
        }
        if ($args['date_from']) {
            $sql .= $wpdb->prepare(" AND created_at >= %s", $args['date_from']);
        }
        if ($args['date_to']) {
            $sql .= $wpdb->prepare(" AND created_at <= %s", $args['date_to']);
        }
        
        $sql .= " ORDER BY created_at DESC";
        $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        
        return $wpdb->get_results($sql);
    }
    
    public static function get_handover($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handovers';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
    }
    
    public static function create_handover($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handovers';
        
        $result = $wpdb->insert($table, array(
            'title' => $data['title'],
            'shift_name' => isset($data['shift_name']) ? $data['shift_name'] : '',
            'shift_start' => isset($data['shift_start']) ? $data['shift_start'] : null,
            'shift_end' => isset($data['shift_end']) ? $data['shift_end'] : null,
            'notes' => isset($data['notes']) ? $data['notes'] : '',
            'status' => isset($data['status']) ? $data['status'] : 'draft',
            'created_by' => get_current_user_id()
        ));
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function update_handover($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handovers';
        
        return $wpdb->update($table, $data, array('id' => $id));
    }
    
    public static function delete_handover($id) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wphd_handover_tickets', array('handover_id' => $id));
        return $wpdb->delete($wpdb->prefix . 'wphd_handovers', array('id' => $id));
    }
    
    public static function get_handover_tickets($handover_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_tickets';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE handover_id = %d ORDER BY sort_order ASC",
            $handover_id
        ));
    }
    
    public static function add_handover_ticket($handover_id, $ticket_id, $notes = '', $order = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_tickets';
        
        return $wpdb->insert($table, array(
            'handover_id' => $handover_id,
            'ticket_id' => $ticket_id,
            'notes' => $notes,
            'sort_order' => $order
        ));
    }
    
    public static function remove_handover_ticket($handover_id, $ticket_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_tickets';
        
        return $wpdb->delete($table, array(
            'handover_id' => $handover_id,
            'ticket_id' => $ticket_id
        ));
    }
}
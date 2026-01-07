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
            $wpdb->prefix . 'wphd_shifts',
            $wpdb->prefix . 'wphd_handover_reports',
            $wpdb->prefix . 'wphd_handover_report_tickets',
            $wpdb->prefix . 'wphd_handover_sections',
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
        // User needs permission in at least one system (Access Control OR Organization)
        // Exclude internal comments if user lacks permission in BOTH systems
        if (!$include_internal || 
            (!WPHD_Access_Control::can_access('ticket_internal_comments') && 
             !WPHD_Organization_Permissions::can_view_internal_comments())) {
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
    
    public static function get_action_item($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_action_items';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
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
    
    // Shifts Methods
    
    public static function get_shifts($org_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_shifts';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE organization_id = %d ORDER BY start_time ASC",
            $org_id
        ));
    }
    
    public static function get_shift($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_shifts';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
    }
    
    public static function create_shift($org_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_shifts';
        
        // Validate timezone
        $timezone = isset($data['timezone']) ? sanitize_text_field($data['timezone']) : 'UTC';
        $valid_timezones = timezone_identifiers_list();
        if (!in_array($timezone, $valid_timezones, true)) {
            $timezone = 'UTC'; // Fallback to UTC if invalid
        }
        
        $result = $wpdb->insert($table, array(
            'organization_id' => $org_id,
            'name' => sanitize_text_field($data['name']),
            'start_time' => sanitize_text_field($data['start_time']),
            'end_time' => sanitize_text_field($data['end_time']),
            'timezone' => $timezone,
            'created_by' => get_current_user_id()
        ));
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function update_shift($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_shifts';
        
        $update_data = array();
        if (isset($data['name'])) $update_data['name'] = sanitize_text_field($data['name']);
        if (isset($data['start_time'])) $update_data['start_time'] = sanitize_text_field($data['start_time']);
        if (isset($data['end_time'])) $update_data['end_time'] = sanitize_text_field($data['end_time']);
        if (isset($data['timezone'])) {
            $timezone = sanitize_text_field($data['timezone']);
            $valid_timezones = timezone_identifiers_list();
            if (in_array($timezone, $valid_timezones, true)) {
                $update_data['timezone'] = $timezone;
            } else {
                // Return false to indicate validation failure
                return false;
            }
        }
        
        return $wpdb->update($table, $update_data, array('id' => $id));
    }
    
    public static function delete_shift($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_shifts';
        return $wpdb->delete($table, array('id' => $id));
    }
    
    // Handover Report Methods
    
    /**
     * Save a handover report to the database.
     *
     * @param array $data Report data.
     * @return int|false Report ID on success, false on failure.
     */
    public static function save_handover_report($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_reports';
        
        $insert_data = array(
            'user_id' => isset($data['user_id']) ? intval($data['user_id']) : get_current_user_id(),
            'shift_type' => sanitize_text_field($data['shift_type']),
            'shift_date' => isset($data['shift_date']) ? $data['shift_date'] : current_time('mysql'),
            'additional_instructions' => isset($data['additional_instructions']) ? wp_kses_post($data['additional_instructions']) : '',
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active'
        );
        
        // Add organization_id if provided
        if (isset($data['organization_id'])) {
            $insert_data['organization_id'] = intval($data['organization_id']);
        }
        
        $result = $wpdb->insert($table, $insert_data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get handover reports with optional filters.
     *
     * @param array $args Query arguments.
     * @return array Array of report objects.
     */
    public static function get_handover_reports($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_reports';
        
        $defaults = array(
            'user_id' => 0,
            'shift_type' => '',
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM $table WHERE 1=1";
        
        if ($args['user_id']) {
            $sql .= $wpdb->prepare(" AND user_id = %d", $args['user_id']);
        }
        if ($args['shift_type']) {
            $sql .= $wpdb->prepare(" AND shift_type = %s", $args['shift_type']);
        }
        if ($args['status']) {
            $sql .= $wpdb->prepare(" AND status = %s", $args['status']);
        }
        if ($args['date_from']) {
            $sql .= $wpdb->prepare(" AND shift_date >= %s", $args['date_from']);
        }
        if ($args['date_to']) {
            $sql .= $wpdb->prepare(" AND shift_date <= %s", $args['date_to']);
        }
        
        // Validate orderby and order, then build SQL safely
        $allowed_orderby = array('id', 'shift_date', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order = 'ASC' === strtoupper($args['order']) ? 'ASC' : 'DESC';
        
        // Build ORDER BY clause safely - orderby is validated against allowlist
        // We cannot use wpdb->prepare for column names, so we validate and use direct concatenation
        $sql .= " ORDER BY " . $orderby . " " . $order;
        $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get a single handover report by ID.
     *
     * @param int $report_id Report ID.
     * @return object|null Report object or null if not found.
     */
    public static function get_handover_report($report_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_reports';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $report_id
        ));
    }
    
    /**
     * Get tickets associated with a handover report.
     *
     * @param int $report_id Report ID.
     * @param string $section_type Optional section type filter.
     * @return array Array of ticket objects.
     */
    public static function get_handover_report_tickets($report_id, $section_type = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_report_tickets';
        
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE report_id = %d", $report_id);
        
        if ($section_type) {
            $sql .= $wpdb->prepare(" AND section_type = %s", $section_type);
        }
        
        $sql .= " ORDER BY display_order ASC, id ASC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Add a ticket to a handover report.
     *
     * @param int $report_id Report ID.
     * @param int $ticket_id Ticket ID.
     * @param string $section_type Section type (tasks_todo, follow_up, important_info).
     * @param string $special_instructions Special instructions for the ticket.
     * @param int $display_order Display order.
     * @return int|false Insert ID on success, false on failure.
     */
    public static function add_handover_report_ticket($report_id, $ticket_id, $section_type, $special_instructions = '', $display_order = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_report_tickets';
        
        $result = $wpdb->insert($table, array(
            'report_id' => intval($report_id),
            'ticket_id' => intval($ticket_id),
            'section_type' => sanitize_text_field($section_type),
            'special_instructions' => sanitize_textarea_field($special_instructions),
            'display_order' => intval($display_order)
        ));
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Delete a handover report and its associated tickets.
     *
     * @param int $report_id Report ID.
     * @return bool True on success, false on failure.
     */
    public static function delete_handover_report($report_id) {
        global $wpdb;
        
        // Delete associated tickets first
        $wpdb->delete(
            $wpdb->prefix . 'wphd_handover_report_tickets',
            array('report_id' => $report_id),
            array('%d')
        );
        
        // Delete the report
        $result = $wpdb->delete(
            $wpdb->prefix . 'wphd_handover_reports',
            array('id' => $report_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get organization draft report for a shift.
     *
     * @since 1.0.0
     * @param int $org_id Organization ID.
     * @param string $shift_type Shift type (morning, afternoon, night).
     * @param string $date Date in Y-m-d format.
     * @return object|null Report object or null if not found.
     */
    public static function get_organization_draft_report($org_id, $shift_type, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_reports';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE organization_id = %d 
            AND shift_type = %s 
            AND DATE(shift_date) = %s 
            AND status = 'draft'
            ORDER BY created_at DESC
            LIMIT 1",
            $org_id,
            $shift_type,
            $date
        ));
    }
    
    /**
     * Check if a completed report exists for organization, shift, and date.
     *
     * @param int $org_id Organization ID.
     * @param string $shift_type Shift type.
     * @param string $date Date (Y-m-d format).
     * @return object|null Report object or null if not found.
     */
    public static function check_completed_report_exists($org_id, $shift_type, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_reports';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE organization_id = %d 
            AND shift_type = %s 
            AND DATE(shift_date) = %s 
            AND status = 'completed'
            ORDER BY created_at DESC
            LIMIT 1",
            $org_id,
            $shift_type,
            $date
        ));
    }
    
    /**
     * Merge new tickets into an existing report (avoiding duplicates).
     *
     * @param int $report_id Existing report ID.
     * @param array $new_tickets Array of new ticket data.
     * @return int Number of tickets added.
     */
    public static function merge_report_tickets($report_id, $new_tickets) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_report_tickets';
        
        // Get existing tickets in this report
        $existing_tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT ticket_id, section_type FROM $table WHERE report_id = %d",
            $report_id
        ), ARRAY_A);
        
        // Create a lookup array for quick duplicate checking
        $existing_lookup = array();
        foreach ($existing_tickets as $ticket) {
            $key = $ticket['ticket_id'] . '_' . $ticket['section_type'];
            $existing_lookup[$key] = true;
        }
        
        $added_count = 0;
        
        // Add only new tickets that don't already exist
        foreach ($new_tickets as $ticket_data) {
            $key = $ticket_data['ticket_id'] . '_' . $ticket_data['section_type'];
            
            if (!isset($existing_lookup[$key])) {
                $result = self::add_handover_report_ticket(
                    $report_id,
                    $ticket_data['ticket_id'],
                    $ticket_data['section_type'],
                    isset($ticket_data['special_instructions']) ? $ticket_data['special_instructions'] : '',
                    isset($ticket_data['display_order']) ? $ticket_data['display_order'] : 0
                );
                
                if ($result) {
                    $added_count++;
                }
            }
        }
        
        return $added_count;
    }
    
    /**
     * Append additional instructions to a report.
     *
     * @param int $report_id Report ID.
     * @param int $user_id User ID who is adding instructions.
     * @param string $content Instructions content.
     * @return int|false Insert ID on success, false on failure.
     */
    public static function append_additional_instructions($report_id, $user_id, $content) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_additional_instructions';
        
        $result = $wpdb->insert($table, array(
            'report_id' => intval($report_id),
            'user_id' => intval($user_id),
            'content' => wp_kses_post($content)
        ));
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get additional instructions for a report.
     *
     * @param int $report_id Report ID.
     * @return array Array of instruction objects.
     */
    public static function get_additional_instructions($report_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_additional_instructions';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE report_id = %d ORDER BY created_at ASC",
            $report_id
        ));
    }
    
    /**
     * Get formatted additional instructions for display.
     * Formats instructions with attribution blocks.
     *
     * @since 1.0.0
     * @param int $report_id Report ID.
     * @return string Formatted HTML string with attribution blocks.
     */
    public static function get_formatted_additional_instructions($report_id) {
        $instructions = self::get_additional_instructions($report_id);
        
        if (empty($instructions)) {
            return '';
        }
        
        $output = '';
        
        foreach ($instructions as $instruction) {
            $user = get_userdata($instruction->user_id);
            $user_name = $user ? $user->display_name : __('Unknown User', 'wp-helpdesk');
            $date_time = mysql2date(get_option('date_format') . ' ' . __('at', 'wp-helpdesk') . ' ' . get_option('time_format'), $instruction->created_at);
            
            $output .= '<div class="wphd-instruction-block">';
            $output .= '<div class="wphd-instruction-separator">─────────────────────────────────────</div>';
            $output .= '<div class="wphd-instruction-meta">';
            $output .= '<strong>' . esc_html__('Additional Instructions', 'wp-helpdesk') . '</strong><br>';
            $output .= sprintf(
                /* translators: 1: User name, 2: Date and time */
                esc_html__('Added by: %1$s', 'wp-helpdesk') . '<br>' . esc_html__('Date: %2$s', 'wp-helpdesk'),
                '<strong>' . esc_html($user_name) . '</strong>',
                '<strong>' . esc_html($date_time) . '</strong>'
            );
            $output .= '</div>';
            $output .= '<div class="wphd-instruction-separator">─────────────────────────────────────</div>';
            $output .= '<div class="wphd-instruction-content">' . wp_kses_post($instruction->content) . '</div>';
            $output .= '</div>';
        }
        
        return $output;
    }
    
    /**
     * Search handover reports.
     *
     * @param string $search_term Search term.
     * @param array $filters Additional filters.
     * @return array Array of report IDs matching search.
     */
    public static function search_handover_reports($search_term, $filters = array()) {
        global $wpdb;
        $reports_table = $wpdb->prefix . 'wphd_handover_reports';
        $tickets_table = $wpdb->prefix . 'wphd_handover_report_tickets';
        $posts_table = $wpdb->posts;
        $comments_table = $wpdb->prefix . 'wphd_comments';
        
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        
        // Build query to search across multiple fields
        $sql = "SELECT DISTINCT r.* FROM $reports_table r
                LEFT JOIN $tickets_table hrt ON r.id = hrt.report_id
                LEFT JOIN $posts_table p ON hrt.ticket_id = p.ID
                LEFT JOIN $comments_table c ON hrt.ticket_id = c.ticket_id
                WHERE (
                    p.ID LIKE %s
                    OR p.post_title LIKE %s
                    OR p.post_content LIKE %s
                    OR c.content LIKE %s
                    OR r.additional_instructions LIKE %s
                    OR hrt.special_instructions LIKE %s
                )";
        
        $params = array($search_term, $search_term, $search_term, $search_term, $search_term, $search_term);
        
        // Add date filters if provided
        if (!empty($filters['date_from'])) {
            $sql .= " AND r.shift_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND r.shift_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT 50";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Update handover report.
     *
     * @param int $report_id Report ID.
     * @param array $data Update data.
     * @return bool True on success, false on failure.
     */
    public static function update_handover_report($report_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_reports';
        
        $update_data = array();
        
        if (isset($data['additional_instructions'])) {
            $update_data['additional_instructions'] = wp_kses_post($data['additional_instructions']);
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        
        if (isset($data['shift_type'])) {
            $update_data['shift_type'] = sanitize_text_field($data['shift_type']);
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $report_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Remove a ticket from a handover report.
     *
     * @param int $report_id Report ID.
     * @param int $ticket_id Ticket ID.
     * @param string $section_type Section type (optional).
     * @return bool True on success, false on failure.
     */
    public static function remove_handover_report_ticket($report_id, $ticket_id, $section_type = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_report_tickets';
        
        $where = array(
            'report_id' => $report_id,
            'ticket_id' => $ticket_id
        );
        
        if ($section_type) {
            $where['section_type'] = $section_type;
        }
        
        return $wpdb->delete($table, $where) !== false;
    }
    
    /**
     * Get user's organization ID.
     *
     * @param int $user_id User ID (default: current user).
     * @return int|null Organization ID or null if not found.
     */
    public static function get_user_organization_id($user_id = 0) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table = $wpdb->prefix . 'wphd_organization_members';
        
        $org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT organization_id FROM $table WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        
        return $org_id ? intval($org_id) : null;
    }
    
    /**
     * Get all handover sections.
     *
     * @since 1.0.0
     * @param bool $active_only Whether to get only active sections.
     * @return array Array of section objects.
     */
    public static function get_handover_sections($active_only = true) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_sections';
        
        $sql = "SELECT * FROM $table";
        
        if ($active_only) {
            $sql .= " WHERE is_active = 1";
        }
        
        $sql .= " ORDER BY display_order ASC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get a single handover section by ID.
     *
     * @since 1.0.0
     * @param int $section_id Section ID.
     * @return object|null Section object or null if not found.
     */
    public static function get_handover_section($section_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_sections';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $section_id
        ));
    }
    
    /**
     * Create a new handover section.
     *
     * @since 1.0.0
     * @param array $data Section data.
     * @return int|false Section ID on success, false on failure.
     */
    public static function create_handover_section($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_sections';
        
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_title($data['slug']),
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'display_order' => isset($data['display_order']) ? intval($data['display_order']) : 0,
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1
        );
        
        $result = $wpdb->insert($table, $insert_data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update a handover section.
     *
     * @since 1.0.0
     * @param int $section_id Section ID.
     * @param array $data Update data.
     * @return bool True on success, false on failure.
     */
    public static function update_handover_section($section_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_sections';
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['slug'])) {
            $update_data['slug'] = sanitize_title($data['slug']);
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['display_order'])) {
            $update_data['display_order'] = intval($data['display_order']);
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = intval($data['is_active']);
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $section_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a handover section.
     *
     * @since 1.0.0
     * @param int $section_id Section ID.
     * @return bool True on success, false on failure.
     */
    public static function delete_handover_section($section_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_handover_sections';
        
        $result = $wpdb->delete(
            $table,
            array('id' => $section_id),
            array('%d')
        );
        
        return $result !== false;
    }
}
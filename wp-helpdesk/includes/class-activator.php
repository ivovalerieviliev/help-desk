<?php
/**
 * Plugin Activator
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_Activator {
    
    public static function activate() {
        self::create_tables();
        self::create_default_options();
        self::create_capabilities();
        flush_rewrite_rules();
    }
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Ticket Comments Table
        $table_comments = $wpdb->prefix . 'wphd_comments';
        $sql_comments = "CREATE TABLE $table_comments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            content longtext NOT NULL,
            is_internal tinyint(1) DEFAULT 0,
            attachments longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_comments);
        
        // Ticket History Table
        $table_history = $wpdb->prefix . 'wphd_history';
        $sql_history = "CREATE TABLE $table_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            field_name varchar(100) NOT NULL,
            old_value longtext,
            new_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id)
        ) $charset_collate;";
        dbDelta($sql_history);
        
        // Action Items Table
        $table_actions = $wpdb->prefix . 'wphd_action_items';
        $sql_actions = "CREATE TABLE $table_actions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            assigned_to bigint(20),
            due_date datetime,
            is_completed tinyint(1) DEFAULT 0,
            completed_at datetime,
            completed_by bigint(20),
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id)
        ) $charset_collate;";
        dbDelta($sql_actions);
        
        // SLA Log Table
        $table_sla = $wpdb->prefix . 'wphd_sla_log';
        $sql_sla = "CREATE TABLE $table_sla (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) NOT NULL,
            first_response_due datetime,
            first_response_at datetime,
            first_response_breached tinyint(1) DEFAULT 0,
            resolution_due datetime,
            resolved_at datetime,
            resolution_breached tinyint(1) DEFAULT 0,
            paused_at datetime,
            total_paused_time int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ticket_id (ticket_id)
        ) $charset_collate;";
        dbDelta($sql_sla);
        
        // Handovers Table
        $table_handovers = $wpdb->prefix . 'wphd_handovers';
        $sql_handovers = "CREATE TABLE $table_handovers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            shift_name varchar(100),
            shift_start datetime,
            shift_end datetime,
            notes longtext,
            status varchar(50) DEFAULT 'draft',
            created_by bigint(20) NOT NULL,
            reviewed_by bigint(20),
            reviewed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by)
        ) $charset_collate;";
        dbDelta($sql_handovers);
        
        // Handover Tickets Table
        $table_handover_tickets = $wpdb->prefix . 'wphd_handover_tickets';
        $sql_handover_tickets = "CREATE TABLE $table_handover_tickets (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            handover_id bigint(20) NOT NULL,
            ticket_id bigint(20) NOT NULL,
            notes text,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY handover_id (handover_id),
            KEY ticket_id (ticket_id)
        ) $charset_collate;";
        dbDelta($sql_handover_tickets);
        
        // Ticket Meta Extended Table
        $table_meta = $wpdb->prefix . 'wphd_ticket_meta';
        $sql_meta = "CREATE TABLE $table_meta (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";
        dbDelta($sql_meta);
        
        // Organizations Table
        $table_organizations = $wpdb->prefix . 'wphd_organizations';
        $sql_organizations = "CREATE TABLE $table_organizations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            logo_id bigint(20),
            allowed_domains text,
            status varchar(20) DEFAULT 'active',
            settings longtext,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_organizations);
        
        // Organization Members Table
        $table_org_members = $wpdb->prefix . 'wphd_organization_members';
        $sql_org_members = "CREATE TABLE $table_org_members (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            role varchar(50) DEFAULT 'member',
            added_by bigint(20),
            added_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY org_user (organization_id, user_id),
            KEY organization_id (organization_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_org_members);
        
        // Organization Change Log Table
        $table_org_logs = $wpdb->prefix . 'wphd_organization_logs';
        $sql_org_logs = "CREATE TABLE $table_org_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            field_name varchar(100),
            old_value longtext,
            new_value longtext,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_org_logs);
        
        // Shifts Table
        $table_shifts = $wpdb->prefix . 'wphd_shifts';
        $sql_shifts = "CREATE TABLE $table_shifts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            timezone varchar(100) DEFAULT 'UTC',
            created_by bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id)
        ) $charset_collate;";
        dbDelta($sql_shifts);
        
        // Handover Reports Table
        $table_handover_reports = $wpdb->prefix . 'wphd_handover_reports';
        $sql_handover_reports = "CREATE TABLE $table_handover_reports (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            organization_id bigint(20) unsigned NOT NULL,
            shift_type varchar(50) NOT NULL,
            shift_date datetime NOT NULL,
            additional_instructions longtext,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY organization_id (organization_id),
            KEY shift_date (shift_date),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_handover_reports);
        
        // Handover Report Tickets Table
        $table_handover_report_tickets = $wpdb->prefix . 'wphd_handover_report_tickets';
        $sql_handover_report_tickets = "CREATE TABLE $table_handover_report_tickets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            report_id bigint(20) unsigned NOT NULL,
            ticket_id bigint(20) unsigned NOT NULL,
            section_type varchar(50) NOT NULL,
            special_instructions text,
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY ticket_id (ticket_id),
            KEY section_type (section_type)
        ) $charset_collate;";
        dbDelta($sql_handover_report_tickets);
        
        // Handover Additional Instructions Table
        $table_handover_additional_instructions = $wpdb->prefix . 'wphd_handover_additional_instructions';
        $sql_handover_additional_instructions = "CREATE TABLE $table_handover_additional_instructions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            report_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            content longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_handover_additional_instructions);
        
        update_option('wphd_db_version', WPHD_VERSION);
    }
    
    private static function create_default_options() {
        $default_statuses = array(
            array('slug' => 'new', 'name' => 'New', 'color' => '#3498db', 'order' => 1, 'is_default' => true, 'is_closed' => false),
            array('slug' => 'open', 'name' => 'Open', 'color' => '#9b59b6', 'order' => 2, 'is_default' => false, 'is_closed' => false),
            array('slug' => 'in-progress', 'name' => 'In Progress', 'color' => '#f39c12', 'order' => 3, 'is_default' => false, 'is_closed' => false),
            array('slug' => 'waiting', 'name' => 'Waiting for Customer', 'color' => '#e67e22', 'order' => 4, 'is_default' => false, 'is_closed' => false, 'pauses_sla' => true),
            array('slug' => 'resolved', 'name' => 'Resolved', 'color' => '#27ae60', 'order' => 5, 'is_default' => false, 'is_closed' => true),
            array('slug' => 'closed', 'name' => 'Closed', 'color' => '#95a5a6', 'order' => 6, 'is_default' => false, 'is_closed' => true)
        );
        
        $default_categories = array(
            array('slug' => 'general', 'name' => 'General', 'color' => '#3498db', 'icon' => 'dashicons-admin-generic'),
            array('slug' => 'technical', 'name' => 'Technical', 'color' => '#e74c3c', 'icon' => 'dashicons-admin-tools'),
            array('slug' => 'billing', 'name' => 'Billing', 'color' => '#2ecc71', 'icon' => 'dashicons-money-alt'),
            array('slug' => 'feature-request', 'name' => 'Feature Request', 'color' => '#9b59b6', 'icon' => 'dashicons-lightbulb')
        );
        
        $default_priorities = array(
            array('slug' => 'low', 'name' => 'Low', 'color' => '#95a5a6', 'order' => 1),
            array('slug' => 'medium', 'name' => 'Medium', 'color' => '#f39c12', 'order' => 2),
            array('slug' => 'high', 'name' => 'High', 'color' => '#e67e22', 'order' => 3),
            array('slug' => 'urgent', 'name' => 'Urgent', 'color' => '#e74c3c', 'order' => 4)
        );
        
        $default_sla = array(
            'first_response' => 4 * HOUR_IN_SECONDS,
            'resolution' => 24 * HOUR_IN_SECONDS,
            'business_hours_only' => false,
            'business_hours_start' => '09:00',
            'business_hours_end' => '17:00',
            'business_days' => array(1, 2, 3, 4, 5)
        );
        
        $default_settings = array(
            'ticket_prefix' => 'TKT',
            'items_per_page' => 20,
            'enable_email_notifications' => true,
            'default_assignee' => 0,
            'allow_attachments' => true,
            'max_attachment_size' => 5 * MB_IN_BYTES
        );
        
        if (!get_option('wphd_statuses')) {
            update_option('wphd_statuses', $default_statuses);
        }
        if (!get_option('wphd_categories')) {
            update_option('wphd_categories', $default_categories);
        }
        if (!get_option('wphd_priorities')) {
            update_option('wphd_priorities', $default_priorities);
        }
        if (!get_option('wphd_sla_settings')) {
            update_option('wphd_sla_settings', $default_sla);
        }
        if (!get_option('wphd_settings')) {
            update_option('wphd_settings', $default_settings);
        }
    }
    
    private static function create_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_wphd_tickets');
            $admin->add_cap('create_wphd_tickets');
            $admin->add_cap('edit_wphd_tickets');
            $admin->add_cap('delete_wphd_tickets');
            $admin->add_cap('view_wphd_analytics');
            $admin->add_cap('manage_wphd_settings');
            $admin->add_cap('create_wphd_handovers');
            $admin->add_cap('view_wphd_handovers');
            $admin->add_cap('create_wphd_handover_reports');
            $admin->add_cap('view_wphd_handover_reports');
        }
        
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('manage_wphd_tickets');
            $editor->add_cap('create_wphd_tickets');
            $editor->add_cap('edit_wphd_tickets');
            $editor->add_cap('view_wphd_analytics');
            $editor->add_cap('create_wphd_handovers');
            $editor->add_cap('view_wphd_handovers');
            $editor->add_cap('create_wphd_handover_reports');
            $editor->add_cap('view_wphd_handover_reports');
        }
    }
}
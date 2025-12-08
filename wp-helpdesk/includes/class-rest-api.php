<?php
/**
 * REST API Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPHD_REST_API {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        $namespace = 'wphd/v1';
        
        register_rest_route($namespace, '/tickets', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_tickets'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_ticket'),
                'permission_callback' => array($this, 'check_permission')
            )
        ));
        
        register_rest_route($namespace, '/tickets/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_ticket'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_ticket'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_ticket'),
                'permission_callback' => array($this, 'check_delete_permission')
            )
        ));
        
        register_rest_route($namespace, '/tickets/(?P<id>\d+)/comments', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_comments'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_comment'),
                'permission_callback' => array($this, 'check_permission')
            )
        ));
        
        register_rest_route($namespace, '/tickets/(?P<id>\d+)/history', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_history'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        register_rest_route($namespace, '/tickets/(?P<id>\d+)/actions', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_action_items'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_action_item'),
                'permission_callback' => array($this, 'check_permission')
            )
        ));
        
        register_rest_route($namespace, '/tickets/(?P<id>\d+)/sla', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sla'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        register_rest_route($namespace, '/handovers', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_handovers'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_handover'),
                'permission_callback' => array($this, 'check_permission')
            )
        ));
        
        register_rest_route($namespace, '/analytics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_analytics'),
            'permission_callback' => array($this, 'check_analytics_permission')
        ));
        
        register_rest_route($namespace, '/settings', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_settings'),
                'permission_callback' => array($this, 'check_settings_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_settings'),
                'permission_callback' => array($this, 'check_settings_permission')
            )
        ));
        
        // Shifts endpoints
        register_rest_route($namespace, '/organizations/(?P<org_id>\d+)/shifts', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_shifts'),
                'permission_callback' => array($this, 'check_shifts_view_permission'),
                'args' => array(
                    'org_id' => array(
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    )
                )
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_shift'),
                'permission_callback' => array($this, 'check_shifts_manage_permission'),
                'args' => array(
                    'org_id' => array(
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    )
                )
            )
        ));
        
        register_rest_route($namespace, '/shifts/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_shift'),
                'permission_callback' => array($this, 'check_shifts_view_permission')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_shift'),
                'permission_callback' => array($this, 'check_shifts_manage_permission')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_shift'),
                'permission_callback' => array($this, 'check_shifts_manage_permission')
            )
        ));
    }
    
    public function check_permission() {
        return current_user_can('manage_wphd_tickets');
    }
    
    public function check_delete_permission() {
        return current_user_can('delete_wphd_tickets');
    }
    
    public function check_analytics_permission() {
        return current_user_can('view_wphd_analytics');
    }
    
    public function check_settings_permission() {
        return current_user_can('manage_wphd_settings');
    }
    
    public function check_shifts_view_permission() {
        return WPHD_Access_Control::can_access('shifts_view');
    }
    
    public function check_shifts_manage_permission() {
        return WPHD_Access_Control::can_access('shifts_manage');
    }
    
    public function get_tickets($request) {
        $args = array(
            'post_type' => 'wphd_ticket',
            'posts_per_page' => $request->get_param('per_page') ?: 20,
            'paged' => $request->get_param('page') ?: 1
        );
        
        if ($request->get_param('status')) {
            $args['meta_query'][] = array('key' => '_wphd_status', 'value' => $request->get_param('status'));
        }
        
        $query = new WP_Query($args);
        $tickets = array();
        
        foreach ($query->posts as $post) {
            $tickets[] = $this->format_ticket($post);
        }
        
        return new WP_REST_Response(array(
            'tickets' => $tickets,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages
        ), 200);
    }
    
    public function get_ticket($request) {
        $post = get_post($request['id']);
        if (!$post || $post->post_type !== 'wphd_ticket') {
            return new WP_Error('not_found', 'Ticket not found', array('status' => 404));
        }
        return new WP_REST_Response($this->format_ticket($post, true), 200);
    }
    
    public function create_ticket($request) {
        $ticket_id = wp_insert_post(array(
            'post_type' => 'wphd_ticket',
            'post_title' => sanitize_text_field($request->get_param('title')),
            'post_content' => wp_kses_post($request->get_param('content')),
            'post_status' => 'publish'
        ));
        
        if (is_wp_error($ticket_id)) {
            return $ticket_id;
        }
        
        update_post_meta($ticket_id, '_wphd_status', $request->get_param('status') ?: 'new');
        update_post_meta($ticket_id, '_wphd_category', $request->get_param('category') ?: '');
        update_post_meta($ticket_id, '_wphd_priority', $request->get_param('priority') ?: 'medium');
        update_post_meta($ticket_id, '_wphd_assignee', $request->get_param('assignee') ?: 0);
        
        $sla_settings = get_option('wphd_sla_settings', array());
        $now = current_time('mysql');
        WPHD_Database::create_sla(
            $ticket_id,
            date('Y-m-d H:i:s', strtotime($now) + ($sla_settings['first_response'] ?? 14400)),
            date('Y-m-d H:i:s', strtotime($now) + ($sla_settings['resolution'] ?? 86400))
        );
        
        return new WP_REST_Response($this->format_ticket(get_post($ticket_id)), 201);
    }
    
    public function update_ticket($request) {
        $ticket_id = $request['id'];
        $post = get_post($ticket_id);
        
        if (!$post || $post->post_type !== 'wphd_ticket') {
            return new WP_Error('not_found', 'Ticket not found', array('status' => 404));
        }
        
        $updates = array('ID' => $ticket_id);
        if ($request->get_param('title')) $updates['post_title'] = sanitize_text_field($request->get_param('title'));
        if ($request->get_param('content')) $updates['post_content'] = wp_kses_post($request->get_param('content'));
        
        wp_update_post($updates);
        
        $fields = array('status', 'category', 'priority', 'assignee');
        foreach ($fields as $field) {
            if ($request->get_param($field) !== null) {
                $old = get_post_meta($ticket_id, '_wphd_' . $field, true);
                $new = sanitize_text_field($request->get_param($field));
                if ($old !== $new) {
                    update_post_meta($ticket_id, '_wphd_' . $field, $new);
                    WPHD_Database::add_history($ticket_id, $field, $old, $new);
                    
                    // Handle SLA resolved_at when status changes
                    if ($field === 'status') {
                        $this->update_sla_on_status_change($ticket_id, $old, $new);
                    }
                }
            }
        }
        
        return new WP_REST_Response($this->format_ticket(get_post($ticket_id)), 200);
    }
    
    /**
     * Update SLA resolved_at when status changes to/from closed.
     *
     * @param int    $ticket_id Ticket ID.
     * @param string $old_status Old status slug.
     * @param string $new_status New status slug.
     */
    private function update_sla_on_status_change($ticket_id, $old_status, $new_status) {
        $statuses = get_option('wphd_statuses', array());
        
        // Check if new status is a closed/resolved status
        $is_new_closed = false;
        foreach ($statuses as $status) {
            if ($status['slug'] === $new_status && !empty($status['is_closed'])) {
                $is_new_closed = true;
                break;
            }
        }
        
        // Check if old status was a closed/resolved status
        $is_old_closed = false;
        foreach ($statuses as $status) {
            if ($status['slug'] === $old_status && !empty($status['is_closed'])) {
                $is_old_closed = true;
                break;
            }
        }
        
        // If status changed to closed/resolved, update SLA resolved_at
        if ($is_new_closed && !$is_old_closed) {
            $sla = WPHD_Database::get_sla($ticket_id);
            if ($sla && empty($sla->resolved_at)) {
                WPHD_Database::update_sla($ticket_id, array( 
                    'resolved_at' => current_time('mysql') 
                ));
            }
        }
        
        // If status changed FROM closed to open, clear resolved_at (ticket reopened)
        if ($is_old_closed && !$is_new_closed) {
            WPHD_Database::update_sla($ticket_id, array( 
                'resolved_at' => null 
            ));
        }
    }
    
    public function delete_ticket($request) {
        $post = get_post($request['id']);
        if (!$post || $post->post_type !== 'wphd_ticket') {
            return new WP_Error('not_found', 'Ticket not found', array('status' => 404));
        }
        wp_delete_post($request['id'], true);
        return new WP_REST_Response(null, 204);
    }
    
    public function get_comments($request) {
        $comments = WPHD_Database::get_comments($request['id']);
        $formatted = array();
        foreach ($comments as $comment) {
            $user = get_user_by('id', $comment->user_id);
            $formatted[] = array(
                'id' => $comment->id,
                'content' => $comment->content,
                'is_internal' => (bool)$comment->is_internal,
                'author' => array(
                    'id' => $comment->user_id,
                    'name' => $user ? $user->display_name : 'Unknown',
                    'avatar' => get_avatar_url($comment->user_id)
                ),
                'created_at' => $comment->created_at
            );
        }
        return new WP_REST_Response($formatted, 200);
    }
    
    public function add_comment($request) {
        $comment_id = WPHD_Database::add_comment(array(
            'ticket_id' => $request['id'],
            'content' => wp_kses_post($request->get_param('content')),
            'is_internal' => (bool)$request->get_param('is_internal')
        ));
        
        if (!$comment_id) {
            return new WP_Error('failed', 'Failed to add comment', array('status' => 500));
        }
        
        return new WP_REST_Response(array('id' => $comment_id), 201);
    }
    
    public function get_history($request) {
        $history = WPHD_Database::get_history($request['id']);
        $formatted = array();
        foreach ($history as $entry) {
            $user = get_user_by('id', $entry->user_id);
            $formatted[] = array(
                'id' => $entry->id,
                'field' => $entry->field_name,
                'old_value' => $entry->old_value,
                'new_value' => $entry->new_value,
                'user' => $user ? $user->display_name : 'Unknown',
                'created_at' => $entry->created_at
            );
        }
        return new WP_REST_Response($formatted, 200);
    }
    
    public function get_action_items($request) {
        $items = WPHD_Database::get_action_items($request['id']);
        return new WP_REST_Response($items, 200);
    }
    
    public function add_action_item($request) {
        $item_id = WPHD_Database::add_action_item(array(
            'ticket_id' => $request['id'],
            'title' => sanitize_text_field($request->get_param('title')),
            'description' => sanitize_textarea_field($request->get_param('description')),
            'assigned_to' => intval($request->get_param('assigned_to')),
            'due_date' => sanitize_text_field($request->get_param('due_date'))
        ));
        return new WP_REST_Response(array('id' => $item_id), 201);
    }
    
    public function get_sla($request) {
        $sla = WPHD_Database::get_sla($request['id']);
        if (!$sla) {
            return new WP_REST_Response(null, 200);
        }
        
        $now = current_time('timestamp');
        $first_response_remaining = $sla->first_response_at ? 0 : max(0, strtotime($sla->first_response_due) - $now);
        $resolution_remaining = $sla->resolved_at ? 0 : max(0, strtotime($sla->resolution_due) - $now);
        
        return new WP_REST_Response(array(
            'first_response' => array(
                'due' => $sla->first_response_due,
                'met_at' => $sla->first_response_at,
                'breached' => (bool)$sla->first_response_breached,
                'remaining_seconds' => $first_response_remaining
            ),
            'resolution' => array(
                'due' => $sla->resolution_due,
                'met_at' => $sla->resolved_at,
                'breached' => (bool)$sla->resolution_breached,
                'remaining_seconds' => $resolution_remaining
            )
        ), 200);
    }
    
    public function get_handovers($request) {
        $handovers = WPHD_Database::get_handovers(array(
            'status' => $request->get_param('status'),
            'limit' => $request->get_param('per_page') ?: 20,
            'offset' => (($request->get_param('page') ?: 1) - 1) * ($request->get_param('per_page') ?: 20)
        ));
        return new WP_REST_Response($handovers, 200);
    }
    
    public function create_handover($request) {
        $handover_id = WPHD_Database::create_handover(array(
            'title' => sanitize_text_field($request->get_param('title')),
            'shift_name' => sanitize_text_field($request->get_param('shift_name')),
            'shift_start' => sanitize_text_field($request->get_param('shift_start')),
            'shift_end' => sanitize_text_field($request->get_param('shift_end')),
            'notes' => wp_kses_post($request->get_param('notes')),
            'status' => $request->get_param('status') ?: 'draft'
        ));
        
        if ($handover_id && $request->get_param('tickets')) {
            $order = 0;
            foreach ($request->get_param('tickets') as $ticket) {
                WPHD_Database::add_handover_ticket($handover_id, $ticket['id'], $ticket['notes'] ?? '', $order++);
            }
        }
        
        return new WP_REST_Response(array('id' => $handover_id), 201);
    }
    
    public function get_analytics($request) {
        $period = $request->get_param('period') ?: 'week';
        return new WP_REST_Response(WPHD_Analytics_Queries::get_dashboard_data($period), 200);
    }
    
    public function get_settings($request) {
        return new WP_REST_Response(array(
            'general' => get_option('wphd_settings', array()),
            'statuses' => get_option('wphd_statuses', array()),
            'categories' => get_option('wphd_categories', array()),
            'priorities' => get_option('wphd_priorities', array()),
            'sla' => get_option('wphd_sla_settings', array())
        ), 200);
    }
    
    public function save_settings($request) {
        if ($request->get_param('general')) {
            update_option('wphd_settings', $request->get_param('general'));
        }
        if ($request->get_param('statuses')) {
            update_option('wphd_statuses', $request->get_param('statuses'));
        }
        if ($request->get_param('categories')) {
            update_option('wphd_categories', $request->get_param('categories'));
        }
        if ($request->get_param('sla')) {
            update_option('wphd_sla_settings', $request->get_param('sla'));
        }
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    private function format_ticket($post, $detailed = false) {
        $data = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'status' => get_post_meta($post->ID, '_wphd_status', true),
            'category' => get_post_meta($post->ID, '_wphd_category', true),
            'priority' => get_post_meta($post->ID, '_wphd_priority', true),
            'assignee' => get_post_meta($post->ID, '_wphd_assignee', true),
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified
        );
        
        if ($detailed) {
            $data['content'] = $post->post_content;
            $data['author'] = array(
                'id' => $post->post_author,
                'name' => get_the_author_meta('display_name', $post->post_author),
                'avatar' => get_avatar_url($post->post_author)
            );
            $assignee_id = get_post_meta($post->ID, '_wphd_assignee', true);
            if ($assignee_id) {
                $data['assignee_info'] = array(
                    'id' => $assignee_id,
                    'name' => get_the_author_meta('display_name', $assignee_id),
                    'avatar' => get_avatar_url($assignee_id)
                );
            }
            $data['tags'] = get_post_meta($post->ID, '_wphd_tags', true) ?: array();
            $data['due_date'] = get_post_meta($post->ID, '_wphd_due_date', true);
        }
        
        return $data;
    }
    
    // Shifts REST API methods
    
    public function get_shifts($request) {
        $org_id = $request->get_param('org_id');
        $shifts = WPHD_Database::get_shifts($org_id);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $shifts
        ), 200);
    }
    
    public function get_shift($request) {
        $shift_id = $request->get_param('id');
        $shift = WPHD_Database::get_shift($shift_id);
        
        if (!$shift) {
            return new WP_Error('shift_not_found', __('Shift not found', 'wp-helpdesk'), array('status' => 404));
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $shift
        ), 200);
    }
    
    public function create_shift($request) {
        $org_id = $request->get_param('org_id');
        $name = sanitize_text_field($request->get_param('name'));
        $start_time = sanitize_text_field($request->get_param('start_time'));
        $end_time = sanitize_text_field($request->get_param('end_time'));
        $timezone = sanitize_text_field($request->get_param('timezone')) ?: 'UTC';
        
        if (empty($name) || empty($start_time) || empty($end_time)) {
            return new WP_Error('missing_fields', __('Name, start time, and end time are required', 'wp-helpdesk'), array('status' => 400));
        }
        
        // Server-side validation: start_time should be before end_time
        if (strtotime($start_time) >= strtotime($end_time)) {
            return new WP_Error('invalid_times', __('Start time must be before end time', 'wp-helpdesk'), array('status' => 400));
        }
        
        $data = array(
            'name' => $name,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'timezone' => $timezone
        );
        
        $shift_id = WPHD_Database::create_shift($org_id, $data);
        
        if (!$shift_id) {
            return new WP_Error('create_failed', __('Failed to create shift', 'wp-helpdesk'), array('status' => 500));
        }
        
        // Add to organization history
        WPHD_Organizations::log_change($org_id, 'shift_created', '', '', $name);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'shift_id' => $shift_id,
                'message' => __('Shift created successfully', 'wp-helpdesk')
            )
        ), 201);
    }
    
    public function update_shift($request) {
        $shift_id = $request->get_param('id');
        $shift = WPHD_Database::get_shift($shift_id);
        
        if (!$shift) {
            return new WP_Error('shift_not_found', __('Shift not found', 'wp-helpdesk'), array('status' => 404));
        }
        
        $data = array();
        $old_name = $shift->name;
        
        if ($request->has_param('name')) {
            $data['name'] = sanitize_text_field($request->get_param('name'));
        }
        if ($request->has_param('start_time')) {
            $data['start_time'] = sanitize_text_field($request->get_param('start_time'));
        }
        if ($request->has_param('end_time')) {
            $data['end_time'] = sanitize_text_field($request->get_param('end_time'));
        }
        if ($request->has_param('timezone')) {
            $data['timezone'] = sanitize_text_field($request->get_param('timezone'));
        }
        
        // Server-side validation if both times are provided
        $start = isset($data['start_time']) ? $data['start_time'] : $shift->start_time;
        $end = isset($data['end_time']) ? $data['end_time'] : $shift->end_time;
        
        if (strtotime($start) >= strtotime($end)) {
            return new WP_Error('invalid_times', __('Start time must be before end time', 'wp-helpdesk'), array('status' => 400));
        }
        
        $result = WPHD_Database::update_shift($shift_id, $data);
        
        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update shift', 'wp-helpdesk'), array('status' => 500));
        }
        
        // Add to organization history
        $new_name = isset($data['name']) ? $data['name'] : $old_name;
        WPHD_Organizations::log_change($shift->organization_id, 'shift_edited', '', $old_name, $new_name);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'message' => __('Shift updated successfully', 'wp-helpdesk')
            )
        ), 200);
    }
    
    public function delete_shift($request) {
        $shift_id = $request->get_param('id');
        $shift = WPHD_Database::get_shift($shift_id);
        
        if (!$shift) {
            return new WP_Error('shift_not_found', __('Shift not found', 'wp-helpdesk'), array('status' => 404));
        }
        
        $shift_name = $shift->name;
        $org_id = $shift->organization_id;
        $result = WPHD_Database::delete_shift($shift_id);
        
        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete shift', 'wp-helpdesk'), array('status' => 500));
        }
        
        // Add to organization history
        WPHD_Organizations::log_change($org_id, 'shift_deleted', '', $shift_name, '');
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'message' => __('Shift deleted successfully', 'wp-helpdesk')
            )
        ), 200);
    }
}
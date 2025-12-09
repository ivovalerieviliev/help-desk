<?php
/**
 * Handover Report Class
 *
 * Handles the creation and management of shift handover reports.
 *
 * @package     WP_HelpDesk
 * @subpackage  Admin
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPHD_Handover_Report
 *
 * Manages handover report functionality including creation, display, and AJAX handlers.
 *
 * @since 1.0.0
 */
class WPHD_Handover_Report {

    /**
     * Instance of this class.
     *
     * @since  1.0.0
     * @access private
     * @var    WPHD_Handover_Report
     */
    private static $instance = null;

    /**
     * Get the singleton instance of this class.
     *
     * @since  1.0.0
     * @return WPHD_Handover_Report
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    private function __construct() {
        add_action( 'admin_post_wphd_create_handover_report', array( $this, 'handle_create_report' ) );
        add_action( 'wp_ajax_wphd_search_tickets_for_handover', array( $this, 'search_tickets' ) );
    }

    /**
     * Render the create handover report page.
     *
     * @since 1.0.0
     */
    public function render_create_page() {
        if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
            wp_die( esc_html__( 'You do not have permission to create handover reports.', 'wp-helpdesk' ) );
        }

        $current_user = wp_get_current_user();
        $shift_types = $this->get_shift_types();
        
        ?>
        <div class="wrap wp-helpdesk-wrap wphd-handover-report-wrap">
            <h1><?php esc_html_e( 'Create Handover Report', 'wp-helpdesk' ); ?></h1>
            
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wphd-handover-report-form">
                <?php wp_nonce_field( 'wphd_create_handover_report', 'wphd_handover_report_nonce' ); ?>
                <input type="hidden" name="action" value="wphd_create_handover_report">
                
                <!-- Shift Details Section -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php esc_html_e( 'Shift Details', 'wp-helpdesk' ); ?></h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e( 'User', 'wp-helpdesk' ); ?></label>
                                </th>
                                <td>
                                    <strong><?php echo esc_html( $current_user->display_name ); ?></strong>
                                    <p class="description"><?php esc_html_e( 'Current logged-in user creating this report', 'wp-helpdesk' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="shift_type"><?php esc_html_e( 'Current Shift', 'wp-helpdesk' ); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <select name="shift_type" id="shift_type" required class="regular-text">
                                        <option value=""><?php esc_html_e( 'Select Shift', 'wp-helpdesk' ); ?></option>
                                        <?php foreach ( $shift_types as $key => $label ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e( 'Shift Date and Time', 'wp-helpdesk' ); ?></label>
                                </th>
                                <td>
                                    <strong><?php echo esc_html( current_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></strong>
                                    <p class="description"><?php esc_html_e( 'Auto-populated with current date and time', 'wp-helpdesk' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Tasks to be Done Section -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php esc_html_e( 'Tasks to be Done', 'wp-helpdesk' ); ?></h2>
                    </div>
                    <div class="inside">
                        <p class="description"><?php esc_html_e( 'Tickets that need to be completed', 'wp-helpdesk' ); ?></p>
                        <button type="button" class="button wphd-add-ticket-btn" data-section="tasks_todo">
                            <?php esc_html_e( 'Add Ticket', 'wp-helpdesk' ); ?>
                        </button>
                        <div class="wphd-ticket-list" id="tasks_todo_list">
                            <!-- Tickets will be added here via JavaScript -->
                        </div>
                        <input type="hidden" name="tasks_todo_tickets" id="tasks_todo_tickets" value="">
                    </div>
                </div>

                <!-- Follow-up Tickets Section -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php esc_html_e( 'Follow-up Tickets', 'wp-helpdesk' ); ?></h2>
                    </div>
                    <div class="inside">
                        <p class="description"><?php esc_html_e( 'Tickets requiring follow-up by the next shift', 'wp-helpdesk' ); ?></p>
                        <button type="button" class="button wphd-add-ticket-btn" data-section="follow_up">
                            <?php esc_html_e( 'Add Ticket', 'wp-helpdesk' ); ?>
                        </button>
                        <div class="wphd-ticket-list" id="follow_up_list">
                            <!-- Tickets will be added here via JavaScript -->
                        </div>
                        <input type="hidden" name="follow_up_tickets" id="follow_up_tickets" value="">
                    </div>
                </div>

                <!-- Important Information Section -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php esc_html_e( 'Important Information', 'wp-helpdesk' ); ?></h2>
                    </div>
                    <div class="inside">
                        <p class="description"><?php esc_html_e( 'Tickets containing important internal knowledge messages', 'wp-helpdesk' ); ?></p>
                        <button type="button" class="button wphd-add-ticket-btn" data-section="important_info">
                            <?php esc_html_e( 'Add Ticket', 'wp-helpdesk' ); ?>
                        </button>
                        <div class="wphd-ticket-list" id="important_info_list">
                            <!-- Tickets will be added here via JavaScript -->
                        </div>
                        <input type="hidden" name="important_info_tickets" id="important_info_tickets" value="">
                    </div>
                </div>

                <!-- Additional Instructions Section -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php esc_html_e( 'Additional Instructions', 'wp-helpdesk' ); ?></h2>
                    </div>
                    <div class="inside">
                        <p class="description"><?php esc_html_e( 'Free-form additional instructions for the next shift', 'wp-helpdesk' ); ?></p>
                        <?php
                        wp_editor(
                            '',
                            'additional_instructions',
                            array(
                                'textarea_name' => 'additional_instructions',
                                'textarea_rows' => 10,
                                'media_buttons' => false,
                                'teeny'         => false,
                                'tinymce'       => true,
                            )
                        );
                        ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <p class="submit">
                    <button type="submit" name="submit" class="button button-primary button-large">
                        <?php esc_html_e( 'Create Report', 'wp-helpdesk' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-helpdesk' ) ); ?>" class="button button-large wphd-cancel-handover-btn">
                        <?php esc_html_e( 'Cancel', 'wp-helpdesk' ); ?>
                    </a>
                </p>
            </form>
        </div>

        <!-- Ticket Search Modal -->
        <div id="wphd-ticket-search-modal" class="wphd-modal" style="display: none;">
            <div class="wphd-modal-content">
                <span class="wphd-modal-close">&times;</span>
                <h2><?php esc_html_e( 'Search and Add Ticket', 'wp-helpdesk' ); ?></h2>
                <div class="wphd-search-container">
                    <input type="text" id="wphd-ticket-search-input" class="regular-text" placeholder="<?php esc_attr_e( 'Search by Ticket ID or Title...', 'wp-helpdesk' ); ?>">
                    <div id="wphd-ticket-search-results"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle form submission for creating a handover report.
     *
     * @since 1.0.0
     */
    public function handle_create_report() {
        // Verify nonce - sanitize before checking
        $nonce = isset( $_POST['wphd_handover_report_nonce'] ) ? sanitize_text_field( $_POST['wphd_handover_report_nonce'] ) : '';
        
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wphd_create_handover_report' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'wp-helpdesk' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
            wp_die( esc_html__( 'You do not have permission to create handover reports.', 'wp-helpdesk' ) );
        }

        // Validate required fields
        if ( empty( $_POST['shift_type'] ) ) {
            wp_die( esc_html__( 'Shift type is required.', 'wp-helpdesk' ) );
        }

        // Prepare report data
        $report_data = array(
            'user_id'                  => get_current_user_id(),
            'shift_type'               => sanitize_text_field( $_POST['shift_type'] ),
            'shift_date'               => current_time( 'mysql' ),
            'additional_instructions'  => isset( $_POST['additional_instructions'] ) ? wp_kses_post( $_POST['additional_instructions'] ) : '',
            'status'                   => 'active',
        );

        // Save the report
        $report_id = WPHD_Database::save_handover_report( $report_data );

        if ( ! $report_id ) {
            wp_die( esc_html__( 'Failed to create handover report.', 'wp-helpdesk' ) );
        }

        // Process and save tickets for each section
        $sections = array( 'tasks_todo', 'follow_up', 'important_info' );
        
        foreach ( $sections as $section ) {
            $tickets_field = $section . '_tickets';
            if ( ! empty( $_POST[ $tickets_field ] ) ) {
                // Sanitize JSON input before decoding
                $tickets_json = sanitize_textarea_field( stripslashes( $_POST[ $tickets_field ] ) );
                $tickets_data = json_decode( $tickets_json, true );
                
                // Check for JSON decode errors
                if ( json_last_error() !== JSON_ERROR_NONE ) {
                    error_log( 'WP HelpDesk: Invalid JSON in handover report for section ' . $section . ': ' . json_last_error_msg() );
                    continue; // Skip this section and continue with others
                }
                
                if ( is_array( $tickets_data ) ) {
                    foreach ( $tickets_data as $index => $ticket_data ) {
                        if ( isset( $ticket_data['ticket_id'] ) && is_numeric( $ticket_data['ticket_id'] ) ) {
                            WPHD_Database::add_handover_report_ticket(
                                $report_id,
                                intval( $ticket_data['ticket_id'] ),
                                $section,
                                isset( $ticket_data['special_instructions'] ) ? sanitize_textarea_field( $ticket_data['special_instructions'] ) : '',
                                $index
                            );
                        }
                    }
                }
            }
        }

        // Redirect to handover reports history page with success message
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'wp-helpdesk-handover-reports',
                    'created' => '1',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * AJAX handler for searching tickets.
     *
     * @since 1.0.0
     */
    public function search_tickets() {
        check_ajax_referer( 'wphd_search_tickets_handover', 'nonce' );

        if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
        }

        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';

        if ( strlen( $search ) < 2 ) {
            wp_send_json_success( array( 'tickets' => array() ) );
        }

        $tickets = $this->get_ticket_data( $search );

        wp_send_json_success( array( 'tickets' => $tickets ) );
    }

    /**
     * Get ticket data for search results.
     *
     * @since 1.0.0
     * @param string $search Search query.
     * @return array Array of ticket data.
     */
    public function get_ticket_data( $search = '' ) {
        $args = array(
            'post_type'      => 'wphd_ticket',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        // Check if search is a ticket ID
        if ( is_numeric( $search ) || preg_match( '/^#?(\d+)$/', $search, $matches ) ) {
            $ticket_id = is_numeric( $search ) ? intval( $search ) : intval( $matches[1] );
            $args['p'] = $ticket_id;
        } else {
            // Search by title
            $args['s'] = $search;
        }

        $query = new WP_Query( $args );
        $tickets = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $ticket_id = get_the_ID();

                $status   = get_post_meta( $ticket_id, '_wphd_status', true );
                $priority = get_post_meta( $ticket_id, '_wphd_priority', true );
                $category = get_post_meta( $ticket_id, '_wphd_category', true );
                
                $reporter_id = get_post_field( 'post_author', $ticket_id );
                $reporter = get_userdata( $reporter_id );
                
                // Get status label
                $statuses = get_option( 'wphd_statuses', array() );
                $status_label = $status;
                foreach ( $statuses as $s ) {
                    if ( $s['slug'] === $status ) {
                        $status_label = $s['name'];
                        break;
                    }
                }

                // Get priority label
                $priorities = get_option( 'wphd_priorities', array() );
                $priority_label = $priority;
                foreach ( $priorities as $p ) {
                    if ( $p['slug'] === $priority ) {
                        $priority_label = $p['name'];
                        break;
                    }
                }

                // Get category label
                $categories = get_option( 'wphd_categories', array() );
                $category_label = $category;
                foreach ( $categories as $c ) {
                    if ( $c['slug'] === $category ) {
                        $category_label = $c['name'];
                        break;
                    }
                }

                // Get due date if exists
                $due_date = get_post_meta( $ticket_id, '_wphd_due_date', true );

                $tickets[] = array(
                    'id'         => $ticket_id,
                    'title'      => get_the_title(),
                    'status'     => $status,
                    'status_label' => $status_label,
                    'priority'   => $priority,
                    'priority_label' => $priority_label,
                    'category'   => $category,
                    'category_label' => $category_label,
                    'reporter'   => $reporter ? $reporter->display_name : __( 'Unknown', 'wp-helpdesk' ),
                    'created_at' => get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
                    'due_date'   => $due_date ? mysql2date( get_option( 'date_format' ), $due_date ) : '',
                );
            }
        }

        wp_reset_postdata();

        return $tickets;
    }

    /**
     * Get available shift types.
     *
     * @since 1.0.0
     * @return array Shift types.
     */
    private function get_shift_types() {
        // These can be made configurable in settings later
        return array(
            'morning'   => __( 'Morning (06:00 - 14:00)', 'wp-helpdesk' ),
            'afternoon' => __( 'Afternoon (14:00 - 22:00)', 'wp-helpdesk' ),
            'night'     => __( 'Night (22:00 - 06:00)', 'wp-helpdesk' ),
        );
    }
}

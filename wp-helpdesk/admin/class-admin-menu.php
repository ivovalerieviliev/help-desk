<?php
/**
 * Admin Menu Class
 *
 * Handles the admin menu registration and pages for the WordPress Help Desk plugin.
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
 * Class WPHD_Admin_Menu
 *
 * Registers and manages the admin menu items for the Help Desk plugin.
 *
 * @since 1.0.0
 */
class WPHD_Admin_Menu {

    /**
     * Instance of this class.
     *
     * @since  1.0.0
     * @access private
     * @var    WPHD_Admin_Menu
     */
    private static $instance = null;

    /**
     * Plugin menu slug.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $menu_slug = 'wp-helpdesk';

    /**
     * Required capability to access the plugin.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $capability = 'read';

    /**
     * Required capability for admin-only pages.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $admin_capability = 'manage_options';

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Get the singleton instance of this class.
     *
     * @since  1.0.0
     * @return WPHD_Admin_Menu
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize hooks.
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
        add_action( 'admin_init', array( $this, 'handle_repair_database' ) );
    }

    /**
     * Register the admin menu and submenus.
     *
     * @since 1.0.0
     */
    public function register_admin_menu() {
        // Main menu page.
        add_menu_page(
            __( 'Help Desk', 'wp-helpdesk' ),
            __( 'Help Desk', 'wp-helpdesk' ),
            $this->capability,
            $this->menu_slug,
            array( $this, 'render_dashboard_page' ),
            'dashicons-tickets-alt',
            26
        );

        // Dashboard submenu (same as main menu).
        if ( WPHD_Access_Control::can_access( 'dashboard' ) ) {
            add_submenu_page(
                $this->menu_slug,
                __( 'Dashboard', 'wp-helpdesk' ),
                __( 'Dashboard', 'wp-helpdesk' ),
                $this->capability,
                $this->menu_slug,
                array( $this, 'render_dashboard_page' )
            );
        }

        // Tickets submenu.
        if ( WPHD_Access_Control::can_access( 'tickets_list' ) ) {
            add_submenu_page(
                $this->menu_slug,
                __( 'All Tickets', 'wp-helpdesk' ),
                __( 'All Tickets', 'wp-helpdesk' ),
                $this->capability,
                $this->menu_slug . '-tickets',
                array( $this, 'render_tickets_page' )
            );
        }

        // Add New Ticket submenu.
        if ( WPHD_Access_Control::can_access( 'ticket_create' ) ) {
            add_submenu_page(
                $this->menu_slug,
                __( 'Add New Ticket', 'wp-helpdesk' ),
                __( 'Add New', 'wp-helpdesk' ),
                $this->capability,
                $this->menu_slug . '-add-ticket',
                array( $this, 'render_add_ticket_page' )
            );
        }

        // Categories submenu (Admin only).
        add_submenu_page(
            $this->menu_slug,
            __( 'Categories', 'wp-helpdesk' ),
            __( 'Categories', 'wp-helpdesk' ),
            $this->admin_capability,
            $this->menu_slug . '-categories',
            array( $this, 'render_categories_page' )
        );

        // Statuses submenu (Admin only).
        add_submenu_page(
            $this->menu_slug,
            __( 'Statuses', 'wp-helpdesk' ),
            __( 'Statuses', 'wp-helpdesk' ),
            $this->admin_capability,
            $this->menu_slug . '-statuses',
            array( $this, 'render_statuses_page' )
        );

        // Priorities submenu (Admin only).
        add_submenu_page(
            $this->menu_slug,
            __( 'Priorities', 'wp-helpdesk' ),
            __( 'Priorities', 'wp-helpdesk' ),
            $this->admin_capability,
            $this->menu_slug . '-priorities',
            array( $this, 'render_priorities_page' )
        );

        // Organizations submenu (Admin only).
        add_submenu_page(
            $this->menu_slug,
            __( 'Organizations', 'wp-helpdesk' ),
            __( 'Organizations', 'wp-helpdesk' ),
            $this->admin_capability,
            $this->menu_slug . '-organizations',
            array( $this, 'render_organizations_page' )
        );

        // Settings submenu (Admin only).
        add_submenu_page(
            $this->menu_slug,
            __( 'Settings', 'wp-helpdesk' ),
            __( 'Settings', 'wp-helpdesk' ),
            $this->admin_capability,
            $this->menu_slug . '-settings',
            array( $this, 'render_settings_page' )
        );

        // Reports submenu (Admin only).
        if ( WPHD_Access_Control::can_access( 'reports' ) ) {
            add_submenu_page(
                $this->menu_slug,
                __( 'Reports', 'wp-helpdesk' ),
                __( 'Reports', 'wp-helpdesk' ),
                $this->capability,
                $this->menu_slug . '-reports',
                array( $this, 'render_reports_page' )
            );
        }
    }

    /**
     * Enqueue admin assets (CSS and JS).
     *
     * @since 1.0.0
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on plugin pages.
        if ( strpos( $hook, $this->menu_slug ) === false ) {
            return;
        }

        // Enqueue admin styles.
        wp_enqueue_style(
            'wp-helpdesk-admin',
            WPHD_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            WPHD_VERSION
        );

        // Enqueue admin scripts.
        wp_enqueue_script(
            'wp-helpdesk-admin',
            WPHD_PLUGIN_URL . 'assets/js/admin-script.js',
            array( 'jquery' ),
            WPHD_VERSION,
            true
        );

        // Enqueue Chart.js and reports.js on reports page
        if ( strpos( $hook, 'reports' ) !== false ) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );
            
            // Add integrity and crossorigin attributes for CDN security
            add_filter( 'script_loader_tag', function( $tag, $handle ) {
                if ( 'chartjs' === $handle ) {
                    $tag = str_replace(
                        ' src',
                        ' integrity="sha256-6L34L8Kqw8J2g9OO8sIjjWoVOKk72c8J5RgJFPdFpLY=" crossorigin="anonymous" src',
                        $tag
                    );
                }
                return $tag;
            }, 10, 2 );
            
            wp_enqueue_script(
                'wp-helpdesk-reports',
                WPHD_PLUGIN_URL . 'assets/js/reports.js',
                array( 'jquery', 'chartjs' ),
                WPHD_VERSION,
                true
            );
        }

        // Localize script with data.
        wp_localize_script(
            'wp-helpdesk-admin',
            'wpHelpDesk',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'wphd_nonce' ),
                'i18n'    => array(
                    'confirm_delete' => __( 'Are you sure you want to delete this item?', 'wp-helpdesk' ),
                    'saving'         => __( 'Saving...', 'wp-helpdesk' ),
                    'saved'          => __( 'Saved!', 'wp-helpdesk' ),
                    'error'          => __( 'An error occurred. Please try again.', 'wp-helpdesk' ),
                    'reports'        => __( 'Reports', 'wp-helpdesk' ),
                    'report_for'     => __( 'Report for', 'wp-helpdesk' ),
                    'no_tickets'     => __( 'No tickets found.', 'wp-helpdesk' ),
                    'exporting'      => __( 'Exporting...', 'wp-helpdesk' ),
                    'export_csv'     => __( 'Export to CSV', 'wp-helpdesk' ),
                    'team_avg'       => __( 'team average', 'wp-helpdesk' ),
                ),
            )
        );
    }

    /**
     * Render the dashboard page.
     *
     * @since 1.0.0
     */
    public function render_dashboard_page() {
        if ( ! WPHD_Access_Control::can_access( 'dashboard' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
        }
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php esc_html_e( 'Help Desk Dashboard', 'wp-helpdesk' ); ?></h1>
            <div class="wp-helpdesk-dashboard">
                <div class="wp-helpdesk-welcome">
                    <h2><?php esc_html_e( 'Welcome to WP Help Desk', 'wp-helpdesk' ); ?></h2>
                    <p><?php esc_html_e( 'Manage your support tickets efficiently with WordPress Help Desk.', 'wp-helpdesk' ); ?></p>
                </div>
                <?php $this->render_dashboard_widgets(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render dashboard widgets.
     *
     * @since 1.0.0
     */
    private function render_dashboard_widgets() {
        // Get real statistics
        $total_tickets    = wp_count_posts( 'wphd_ticket' );
        $total_count      = isset( $total_tickets->publish ) ? $total_tickets->publish : 0;
        $open_count       = $this->count_tickets_by_status( 'open' );
        $in_progress_count = $this->count_tickets_by_status( 'in-progress' );
        $resolved_count   = $this->count_tickets_by_status( 'resolved' );
        $closed_count     = $this->count_tickets_by_status( 'closed' );
        
        // Get recent tickets
        $recent_tickets = get_posts(
            array(
                'post_type'      => 'wphd_ticket',
                'post_status'    => 'publish',
                'posts_per_page' => 10,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );
        ?>
        <div class="wp-helpdesk-widgets" style="display: flex; gap: 20px; margin-top: 20px;">
            <div class="wp-helpdesk-widget" style="flex: 1; background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e( 'Ticket Statistics', 'wp-helpdesk' ); ?></h3>
                <ul style="list-style: none; padding: 0;">
                    <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                        <?php esc_html_e( 'Total Tickets:', 'wp-helpdesk' ); ?> <strong><?php echo esc_html( $total_count ); ?></strong>
                    </li>
                    <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                        <?php esc_html_e( 'Open Tickets:', 'wp-helpdesk' ); ?> <strong><?php echo esc_html( $open_count ); ?></strong>
                    </li>
                    <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                        <?php esc_html_e( 'In Progress:', 'wp-helpdesk' ); ?> <strong><?php echo esc_html( $in_progress_count ); ?></strong>
                    </li>
                    <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                        <?php esc_html_e( 'Resolved:', 'wp-helpdesk' ); ?> <strong><?php echo esc_html( $resolved_count ); ?></strong>
                    </li>
                    <li style="padding: 8px 0;">
                        <?php esc_html_e( 'Closed Tickets:', 'wp-helpdesk' ); ?> <strong><?php echo esc_html( $closed_count ); ?></strong>
                    </li>
                </ul>
            </div>
            <div class="wp-helpdesk-widget" style="flex: 1; background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e( 'Quick Actions', 'wp-helpdesk' ); ?></h3>
                <p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-add-ticket' ) ); ?>" class="button button-primary button-large" style="margin-right: 10px;">
                        <?php esc_html_e( 'Create New Ticket', 'wp-helpdesk' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-tickets' ) ); ?>" class="button button-large">
                        <?php esc_html_e( 'View All Tickets', 'wp-helpdesk' ); ?>
                    </a>
                </p>
            </div>
        </div>
        
        <?php if ( ! empty( $recent_tickets ) ) : ?>
        <div style="margin-top: 20px; background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3><?php esc_html_e( 'Recent Tickets', 'wp-helpdesk' ); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Subject', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'wp-helpdesk' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_tickets as $ticket ) : ?>
                        <?php
                        $status   = get_post_meta( $ticket->ID, '_wphd_status', true );
                        $priority = get_post_meta( $ticket->ID, '_wphd_priority', true );
                        ?>
                        <tr>
                            <td><strong>#<?php echo esc_html( $ticket->ID ); ?></strong></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-tickets&ticket_id=' . $ticket->ID ) ); ?>">
                                    <?php echo esc_html( $ticket->post_title ); ?>
                                </a>
                            </td>
                            <td><?php echo wp_kses_post( $this->get_status_label( $status ) ); ?></td>
                            <td><?php echo wp_kses_post( $this->get_priority_label( $priority ) ); ?></td>
                            <td><?php echo esc_html( get_the_date( '', $ticket ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Count tickets by status.
     *
     * @since 1.0.0
     */
    private function count_tickets_by_status( $status ) {
        $args = array(
            'post_type'      => 'wphd_ticket',
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'   => '_wphd_status',
                    'value' => $status,
                ),
            ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        $query = new WP_Query( $args );
        return $query->found_posts;
    }

    /**
     * Render the tickets page.
     *
     * @since 1.0.0
     */
    public function render_tickets_page() {
        // Check if viewing a single ticket
        if ( isset( $_GET['ticket_id'] ) && ! empty( $_GET['ticket_id'] ) ) {
            // Verify user can view ticket details
            if ( ! WPHD_Access_Control::can_access( 'ticket_view' ) ) {
                wp_die( esc_html__( 'You do not have permission to view ticket details.', 'wp-helpdesk' ) );
            }
            $this->render_ticket_details_page( intval( $_GET['ticket_id'] ) );
            return;
        }

        // Check list access
        if ( ! WPHD_Access_Control::can_access( 'tickets_list' ) ) {
            wp_die( esc_html__( 'You do not have permission to view tickets.', 'wp-helpdesk' ) );
        }

        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'All Tickets', 'wp-helpdesk' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-add-ticket' ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Add New', 'wp-helpdesk' ); ?>
            </a>
            <hr class="wp-header-end">
            <?php settings_errors( 'wp_helpdesk' ); ?>
            <div class="wp-helpdesk-tickets-list">
                <?php $this->render_tickets_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render tickets table.
     *
     * @since 1.0.0
     */
    private function render_tickets_table() {
        $statuses   = get_option( 'wphd_statuses', array() );
        $priorities = get_option( 'wphd_priorities', array() );
        $categories = get_option( 'wphd_categories', array() );

        // Get valid slugs
        $valid_statuses   = wp_list_pluck( $statuses, 'slug' );
        $valid_priorities = wp_list_pluck( $priorities, 'slug' );
        $valid_categories = wp_list_pluck( $categories, 'slug' );

        // Validate filters
        $status_filter   = isset( $_GET['status'] ) && in_array( $_GET['status'], $valid_statuses, true ) ? sanitize_text_field( $_GET['status'] ) : '';
        $priority_filter = isset( $_GET['priority'] ) && in_array( $_GET['priority'], $valid_priorities, true ) ? sanitize_text_field( $_GET['priority'] ) : '';
        $category_filter = isset( $_GET['category'] ) && in_array( $_GET['category'], $valid_categories, true ) ? sanitize_text_field( $_GET['category'] ) : '';

        $args = array(
            'post_type'      => 'wphd_ticket',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $meta_query = array();

        if ( ! empty( $status_filter ) ) {
            $meta_query[] = array(
                'key'   => '_wphd_status',
                'value' => $status_filter,
            );
        }

        if ( ! empty( $priority_filter ) ) {
            $meta_query[] = array(
                'key'   => '_wphd_priority',
                'value' => $priority_filter,
            );
        }

        if ( ! empty( $category_filter ) ) {
            $meta_query[] = array(
                'key'   => '_wphd_category',
                'value' => $category_filter,
            );
        }

        if ( ! empty( $meta_query ) ) {
            $args['meta_query'] = $meta_query;
        }

        $tickets = new WP_Query( $args );

        // Render filters
        $this->render_tickets_filters( $status_filter, $priority_filter, $category_filter );

        if ( $tickets->have_posts() ) {
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Subject', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Category', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Assignee', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'wp-helpdesk' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ( $tickets->have_posts() ) :
                        $tickets->the_post();
                        $ticket_id = get_the_ID();
                        $status    = get_post_meta( $ticket_id, '_wphd_status', true );
                        $priority  = get_post_meta( $ticket_id, '_wphd_priority', true );
                        $category  = get_post_meta( $ticket_id, '_wphd_category', true );
                        $assignee  = get_post_meta( $ticket_id, '_wphd_assignee', true );

                        $status_info   = $this->get_status_label( $status );
                        $priority_info = $this->get_priority_label( $priority );
                        $category_info = $this->get_category_label( $category );
                        
                        $assignee_name = __( 'Unassigned', 'wp-helpdesk' );
                        if ( $assignee ) {
                            $user_data = get_userdata( $assignee );
                            if ( $user_data ) {
                                $assignee_name = $user_data->display_name;
                            }
                        }
                        ?>
                        <tr>
                            <td><strong>#<?php echo esc_html( $ticket_id ); ?></strong></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-tickets&ticket_id=' . $ticket_id ) ); ?>">
                                    <?php echo esc_html( get_the_title() ); ?>
                                </a>
                            </td>
                            <td><?php echo wp_kses_post( $status_info ); ?></td>
                            <td><?php echo wp_kses_post( $priority_info ); ?></td>
                            <td><?php echo esc_html( $category_info ); ?></td>
                            <td><?php echo esc_html( $assignee_name ); ?></td>
                            <td><?php echo esc_html( get_the_date() ); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<p>' . esc_html__( 'No tickets found.', 'wp-helpdesk' ) . '</p>';
        }

        wp_reset_postdata();
    }

    /**
     * Render tickets filters.
     *
     * @since 1.0.0
     */
    private function render_tickets_filters( $status_filter, $priority_filter, $category_filter ) {
        $statuses   = get_option( 'wphd_statuses', array() );
        $priorities = get_option( 'wphd_priorities', array() );
        $categories = get_option( 'wphd_categories', array() );
        ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="status">
                    <option value=""><?php esc_html_e( 'All Statuses', 'wp-helpdesk' ); ?></option>
                    <?php foreach ( $statuses as $status ) : ?>
                        <option value="<?php echo esc_attr( $status['slug'] ); ?>" <?php selected( $status_filter, $status['slug'] ); ?>>
                            <?php echo esc_html( $status['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="priority">
                    <option value=""><?php esc_html_e( 'All Priorities', 'wp-helpdesk' ); ?></option>
                    <?php foreach ( $priorities as $priority ) : ?>
                        <option value="<?php echo esc_attr( $priority['slug'] ); ?>" <?php selected( $priority_filter, $priority['slug'] ); ?>>
                            <?php echo esc_html( $priority['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="category">
                    <option value=""><?php esc_html_e( 'All Categories', 'wp-helpdesk' ); ?></option>
                    <?php foreach ( $categories as $category ) : ?>
                        <option value="<?php echo esc_attr( $category['slug'] ); ?>" <?php selected( $category_filter, $category['slug'] ); ?>>
                            <?php echo esc_html( $category['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-helpdesk' ); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * Get status label HTML.
     *
     * @since 1.0.0
     */
    private function get_status_label( $slug ) {
        $statuses = get_option( 'wphd_statuses', array() );
        foreach ( $statuses as $status ) {
            if ( $status['slug'] === $slug ) {
                return sprintf(
                    '<span class="wphd-status-badge" style="background-color: %s; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px;">%s</span>',
                    esc_attr( $status['color'] ),
                    esc_html( $status['name'] )
                );
            }
        }
        return esc_html( ucfirst( str_replace( '-', ' ', $slug ) ) );
    }

    /**
     * Get priority label HTML.
     *
     * @since 1.0.0
     */
    private function get_priority_label( $slug ) {
        $priorities = get_option( 'wphd_priorities', array() );
        foreach ( $priorities as $priority ) {
            if ( $priority['slug'] === $slug ) {
                return sprintf(
                    '<span class="wphd-priority-badge" style="background-color: %s; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px;">%s</span>',
                    esc_attr( $priority['color'] ),
                    esc_html( $priority['name'] )
                );
            }
        }
        return esc_html( ucfirst( $slug ) );
    }

    /**
     * Get category label.
     *
     * @since 1.0.0
     */
    private function get_category_label( $slug ) {
        if ( empty( $slug ) ) {
            return __( 'None', 'wp-helpdesk' );
        }

        $categories = get_option( 'wphd_categories', array() );
        foreach ( $categories as $category ) {
            if ( $category['slug'] === $slug ) {
                return $category['name'];
            }
        }
        return ucfirst( str_replace( '-', ' ', $slug ) );
    }

    /**
     * Render ticket details page.
     *
     * @since 1.0.0
     */
    private function render_ticket_details_page( $ticket_id ) {
        $ticket = get_post( $ticket_id );

        if ( ! $ticket || 'wphd_ticket' !== $ticket->post_type ) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Ticket Not Found', 'wp-helpdesk' ); ?></h1>
                <p><?php esc_html_e( 'The requested ticket could not be found.', 'wp-helpdesk' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-tickets' ) ); ?>" class="button">
                    <?php esc_html_e( 'Back to Tickets', 'wp-helpdesk' ); ?>
                </a>
            </div>
            <?php
            return;
        }

        $status    = get_post_meta( $ticket_id, '_wphd_status', true );
        $priority  = get_post_meta( $ticket_id, '_wphd_priority', true );
        $category  = get_post_meta( $ticket_id, '_wphd_category', true );
        $assignee  = get_post_meta( $ticket_id, '_wphd_assignee', true );

        $statuses   = get_option( 'wphd_statuses', array() );
        $priorities = get_option( 'wphd_priorities', array() );
        $categories = get_option( 'wphd_categories', array() );
        $users      = get_users( array( 'role__in' => array( 'administrator', 'editor' ) ) );

        // Get comments and history
        $comments = WPHD_Database::get_comments( $ticket_id );
        $history  = WPHD_Database::get_history( $ticket_id );
        $sla      = WPHD_Database::get_sla( $ticket_id );

        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1>
                <?php echo esc_html( sprintf( __( 'Ticket #%d: %s', 'wp-helpdesk' ), $ticket_id, $ticket->post_title ) ); ?>
            </h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-tickets' ) ); ?>" class="button">
                &larr; <?php esc_html_e( 'Back to Tickets', 'wp-helpdesk' ); ?>
            </a>
            <hr class="wp-header-end">

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <div style="flex: 2;">
                    <div class="postbox">
                        <div class="inside">
                            <h2><?php esc_html_e( 'Description', 'wp-helpdesk' ); ?></h2>
                            <div><?php echo wp_kses_post( $ticket->post_content ); ?></div>
                        </div>
                    </div>

                    <div class="postbox">
                        <div class="inside">
                            <h2><?php esc_html_e( 'Comments', 'wp-helpdesk' ); ?></h2>
                            <?php if ( ! empty( $comments ) ) : ?>
                                <?php foreach ( $comments as $comment ) : ?>
                                    <?php
                                    $user = get_userdata( $comment->user_id );
                                    ?>
                                    <div class="wphd-comment" style="padding: 15px; margin-bottom: 15px; background: #f9f9f9; border-left: 3px solid #2271b1;">
                                        <div style="margin-bottom: 10px;">
                                            <strong><?php echo esc_html( $user ? $user->display_name : __( 'Unknown', 'wp-helpdesk' ) ); ?></strong>
                                            <span style="color: #666; font-size: 12px;">
                                                - <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $comment->created_at ) ); ?>
                                            </span>
                                            <?php if ( $comment->is_internal ) : ?>
                                                <span style="background: #d63638; color: #fff; padding: 2px 6px; border-radius: 2px; font-size: 11px; margin-left: 5px;">
                                                    <?php esc_html_e( 'Internal', 'wp-helpdesk' ); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div><?php echo wp_kses_post( $comment->content ); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p><?php esc_html_e( 'No comments yet.', 'wp-helpdesk' ); ?></p>
                            <?php endif; ?>

                            <form method="post" style="margin-top: 20px;">
                                <?php wp_nonce_field( 'wp_helpdesk_add_comment', 'wp_helpdesk_comment_nonce' ); ?>
                                <input type="hidden" name="action" value="add_comment">
                                <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket_id ); ?>">
                                <p>
                                    <?php
                                    wp_editor(
                                        '',
                                        'comment_content',
                                        array(
                                            'textarea_name' => 'comment_content',
                                            'textarea_rows' => 5,
                                            'media_buttons' => false,
                                        )
                                    );
                                    ?>
                                </p>
                                <p>
                                    <label>
                                        <input type="checkbox" name="is_internal" value="1">
                                        <?php esc_html_e( 'Internal Note (not visible to customer)', 'wp-helpdesk' ); ?>
                                    </label>
                                </p>
                                <?php submit_button( __( 'Add Comment', 'wp-helpdesk' ), 'secondary' ); ?>
                            </form>
                        </div>
                    </div>

                    <?php if ( ! empty( $history ) ) : ?>
                    <div class="postbox">
                        <div class="inside">
                            <h2><?php esc_html_e( 'History', 'wp-helpdesk' ); ?></h2>
                            <ul style="list-style: none; padding: 0;">
                                <?php foreach ( $history as $entry ) : ?>
                                    <?php
                                    $user = get_userdata( $entry->user_id );
                                    ?>
                                    <li style="padding: 10px; border-bottom: 1px solid #ddd;">
                                        <strong><?php echo esc_html( $user ? $user->display_name : __( 'Unknown', 'wp-helpdesk' ) ); ?></strong>
                                        <?php echo esc_html( sprintf( __( 'changed %s', 'wp-helpdesk' ), $entry->field_name ) ); ?>
                                        <?php if ( ! empty( $entry->old_value ) ) : ?>
                                            <?php echo esc_html( sprintf( __( 'from "%s"', 'wp-helpdesk' ), $entry->old_value ) ); ?>
                                        <?php endif; ?>
                                        <?php echo esc_html( sprintf( __( 'to "%s"', 'wp-helpdesk' ), $entry->new_value ) ); ?>
                                        <br>
                                        <small style="color: #666;">
                                            <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry->created_at ) ); ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="flex: 1;">
                    <div class="postbox">
                        <div class="inside">
                            <h2><?php esc_html_e( 'Ticket Details', 'wp-helpdesk' ); ?></h2>
                            <form method="post">
                                <?php wp_nonce_field( 'wp_helpdesk_update_ticket', 'wp_helpdesk_update_nonce' ); ?>
                                <input type="hidden" name="action" value="update_ticket">
                                <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket_id ); ?>">

                                <p>
                                    <label><strong><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></strong></label><br>
                                    <select name="ticket_status" class="widefat">
                                        <?php foreach ( $statuses as $s ) : ?>
                                            <option value="<?php echo esc_attr( $s['slug'] ); ?>" <?php selected( $status, $s['slug'] ); ?>>
                                                <?php echo esc_html( $s['name'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>

                                <p>
                                    <label><strong><?php esc_html_e( 'Priority', 'wp-helpdesk' ); ?></strong></label><br>
                                    <select name="ticket_priority" class="widefat">
                                        <?php foreach ( $priorities as $p ) : ?>
                                            <option value="<?php echo esc_attr( $p['slug'] ); ?>" <?php selected( $priority, $p['slug'] ); ?>>
                                                <?php echo esc_html( $p['name'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>

                                <p>
                                    <label><strong><?php esc_html_e( 'Category', 'wp-helpdesk' ); ?></strong></label><br>
                                    <select name="ticket_category" class="widefat">
                                        <option value=""><?php esc_html_e( 'None', 'wp-helpdesk' ); ?></option>
                                        <?php foreach ( $categories as $c ) : ?>
                                            <option value="<?php echo esc_attr( $c['slug'] ); ?>" <?php selected( $category, $c['slug'] ); ?>>
                                                <?php echo esc_html( $c['name'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>

                                <p>
                                    <label><strong><?php esc_html_e( 'Assignee', 'wp-helpdesk' ); ?></strong></label><br>
                                    <select name="ticket_assignee" class="widefat">
                                        <option value="0"><?php esc_html_e( 'Unassigned', 'wp-helpdesk' ); ?></option>
                                        <?php foreach ( $users as $user ) : ?>
                                            <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $assignee, $user->ID ); ?>>
                                                <?php echo esc_html( $user->display_name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>

                                <p>
                                    <label><strong><?php esc_html_e( 'Created', 'wp-helpdesk' ); ?></strong></label><br>
                                    <?php echo esc_html( get_the_date( '', $ticket ) ); ?>
                                </p>

                                <p>
                                    <label><strong><?php esc_html_e( 'Last Modified', 'wp-helpdesk' ); ?></strong></label><br>
                                    <?php echo esc_html( get_the_modified_date( '', $ticket ) ); ?>
                                </p>

                                <?php submit_button( __( 'Update Ticket', 'wp-helpdesk' ), 'primary', 'submit', true ); ?>
                            </form>
                        </div>
                    </div>

                    <?php if ( $sla ) : ?>
                    <div class="postbox">
                        <div class="inside">
                            <h2><?php esc_html_e( 'SLA Information', 'wp-helpdesk' ); ?></h2>
                            <p>
                                <strong><?php esc_html_e( 'First Response Due', 'wp-helpdesk' ); ?>:</strong><br>
                                <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sla->first_response_due ) ); ?>
                                <?php if ( $sla->first_response_at ) : ?>
                                    <br><span style="color: green;">✓ <?php esc_html_e( 'Met', 'wp-helpdesk' ); ?></span>
                                <?php elseif ( strtotime( $sla->first_response_due ) < time() ) : ?>
                                    <br><span style="color: red;">✗ <?php esc_html_e( 'Breached', 'wp-helpdesk' ); ?></span>
                                <?php endif; ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e( 'Resolution Due', 'wp-helpdesk' ); ?>:</strong><br>
                                <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sla->resolution_due ) ); ?>
                                <?php if ( $sla->resolved_at ) : ?>
                                    <br><span style="color: green;">✓ <?php esc_html_e( 'Met', 'wp-helpdesk' ); ?></span>
                                <?php elseif ( strtotime( $sla->resolution_due ) < time() ) : ?>
                                    <br><span style="color: red;">✗ <?php esc_html_e( 'Breached', 'wp-helpdesk' ); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the add ticket page.
     *
     * @since 1.0.0
     */
    public function render_add_ticket_page() {
        if ( ! WPHD_Access_Control::can_access( 'ticket_create' ) ) {
            wp_die( esc_html__( 'You do not have permission to create tickets.', 'wp-helpdesk' ) );
        }
        
        $priorities = get_option( 'wphd_priorities', array() );
        $categories = get_option( 'wphd_categories', array() );
        $users = get_users( array( 'role__in' => array( 'administrator', 'editor' ) ) );
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php esc_html_e( 'Add New Ticket', 'wp-helpdesk' ); ?></h1>
            <form method="post" action="" class="wp-helpdesk-form">
                <?php wp_nonce_field( 'wp_helpdesk_add_ticket', 'wp_helpdesk_nonce' ); ?>
                <input type="hidden" name="action" value="create_ticket">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ticket_subject"><?php esc_html_e( 'Subject', 'wp-helpdesk' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="ticket_subject" id="ticket_subject" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ticket_description"><?php esc_html_e( 'Description', 'wp-helpdesk' ); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_editor(
                                '',
                                'ticket_description',
                                array(
                                    'textarea_name' => 'ticket_description',
                                    'textarea_rows' => 10,
                                    'media_buttons' => true,
                                )
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ticket_category"><?php esc_html_e( 'Category', 'wp-helpdesk' ); ?></label>
                        </th>
                        <td>
                            <select name="ticket_category" id="ticket_category">
                                <option value=""><?php esc_html_e( 'Select Category', 'wp-helpdesk' ); ?></option>
                                <?php foreach ( $categories as $category ) : ?>
                                    <option value="<?php echo esc_attr( $category['slug'] ); ?>">
                                        <?php echo esc_html( $category['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ticket_priority"><?php esc_html_e( 'Priority', 'wp-helpdesk' ); ?></label>
                        </th>
                        <td>
                            <select name="ticket_priority" id="ticket_priority">
                                <?php foreach ( $priorities as $priority ) : ?>
                                    <option value="<?php echo esc_attr( $priority['slug'] ); ?>" <?php selected( $priority['slug'], 'medium' ); ?>>
                                        <?php echo esc_html( $priority['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ticket_assignee"><?php esc_html_e( 'Assignee', 'wp-helpdesk' ); ?></label>
                        </th>
                        <td>
                            <select name="ticket_assignee" id="ticket_assignee">
                                <option value="0"><?php esc_html_e( 'Unassigned', 'wp-helpdesk' ); ?></option>
                                <?php foreach ( $users as $user ) : ?>
                                    <option value="<?php echo esc_attr( $user->ID ); ?>">
                                        <?php echo esc_html( $user->display_name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Create Ticket', 'wp-helpdesk' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the categories page.
     *
     * @since 1.0.0
     */
    public function render_categories_page() {
        // Handle form submissions
        if ( isset( $_POST['action'] ) ) {
            if ( 'save_category' === $_POST['action'] ) {
                $this->handle_save_categories();
            } elseif ( 'edit_category' === $_POST['action'] ) {
                $this->handle_edit_category();
            } elseif ( 'delete_category' === $_POST['action'] ) {
                $this->handle_delete_category();
            }
        }

        $categories = get_option( 'wphd_categories', array() );
        $editing_slug = isset( $_GET['edit'] ) ? sanitize_text_field( $_GET['edit'] ) : '';
        $editing_category = null;
        
        if ( $editing_slug ) {
            foreach ( $categories as $cat ) {
                if ( $cat['slug'] === $editing_slug ) {
                    $editing_category = $cat;
                    break;
                }
            }
        }
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php esc_html_e( 'Ticket Categories', 'wp-helpdesk' ); ?></h1>
            <?php settings_errors( 'wp_helpdesk_categories' ); ?>
            
            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <div style="flex: 2;">
                    <h2><?php esc_html_e( 'Existing Categories', 'wp-helpdesk' ); ?></h2>
                    <?php if ( ! empty( $categories ) ) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Color', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Icon', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $categories as $category ) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html( $category['slug'] ); ?></code></td>
                                        <td><?php echo esc_html( $category['name'] ); ?></td>
                                        <td>
                                            <span style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo esc_attr( $category['color'] ); ?>; border: 1px solid #ddd; border-radius: 3px;"></span>
                                            <code><?php echo esc_html( $category['color'] ); ?></code>
                                        </td>
                                        <td>
                                            <span class="dashicons <?php echo esc_attr( $category['icon'] ); ?>"></span>
                                            <code><?php echo esc_html( $category['icon'] ); ?></code>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-categories&edit=' . $category['slug'] ) ); ?>" class="button button-small">
                                                <?php esc_html_e( 'Edit', 'wp-helpdesk' ); ?>
                                            </a>
                                            <form method="post" style="display: inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this category?', 'wp-helpdesk' ) ); ?>');">
                                                <?php wp_nonce_field( 'wp_helpdesk_delete_category', 'wp_helpdesk_delete_nonce' ); ?>
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="category_slug" value="<?php echo esc_attr( $category['slug'] ); ?>">
                                                <button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'wp-helpdesk' ); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No categories found.', 'wp-helpdesk' ); ?></p>
                    <?php endif; ?>
                </div>
                
                <div style="flex: 1;">
                    <div class="postbox">
                        <div class="inside">
                            <h2><?php echo $editing_category ? esc_html__( 'Edit Category', 'wp-helpdesk' ) : esc_html__( 'Add New Category', 'wp-helpdesk' ); ?></h2>
                            <form method="post">
                                <?php wp_nonce_field( $editing_category ? 'wp_helpdesk_edit_category' : 'wp_helpdesk_save_categories', $editing_category ? 'wp_helpdesk_edit_nonce' : 'wp_helpdesk_categories_nonce' ); ?>
                                <input type="hidden" name="action" value="<?php echo $editing_category ? 'edit_category' : 'save_category'; ?>">
                                <?php if ( $editing_category ) : ?>
                                    <input type="hidden" name="old_category_slug" value="<?php echo esc_attr( $editing_category['slug'] ); ?>">
                                <?php endif; ?>
                                
                                <p>
                                    <label for="category_slug"><strong><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="text" name="category_slug" id="category_slug" class="widefat" required pattern="[a-z0-9\-]+" title="<?php esc_attr_e( 'Only lowercase letters, numbers, and hyphens', 'wp-helpdesk' ); ?>" value="<?php echo $editing_category ? esc_attr( $editing_category['slug'] ) : ''; ?>" <?php echo $editing_category ? 'readonly' : ''; ?>>
                                    <small><?php esc_html_e( 'Lowercase letters, numbers, and hyphens only', 'wp-helpdesk' ); ?></small>
                                </p>
                                
                                <p>
                                    <label for="category_name"><strong><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="text" name="category_name" id="category_name" class="widefat" required value="<?php echo $editing_category ? esc_attr( $editing_category['name'] ) : ''; ?>">
                                </p>
                                
                                <p>
                                    <label for="category_color"><strong><?php esc_html_e( 'Color', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="color" name="category_color" id="category_color" value="<?php echo $editing_category ? esc_attr( $editing_category['color'] ) : '#3498db'; ?>">
                                </p>
                                
                                <p>
                                    <label for="category_icon"><strong><?php esc_html_e( 'Icon (Dashicon)', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="text" name="category_icon" id="category_icon" class="widefat" value="<?php echo $editing_category ? esc_attr( $editing_category['icon'] ) : 'dashicons-admin-generic'; ?>">
                                    <small><?php esc_html_e( 'e.g., dashicons-admin-generic', 'wp-helpdesk' ); ?></small>
                                </p>
                                
                                <?php if ( $editing_category ) : ?>
                                    <?php submit_button( __( 'Update Category', 'wp-helpdesk' ), 'primary', 'submit', true ); ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-categories' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'wp-helpdesk' ); ?></a>
                                <?php else : ?>
                                    <?php submit_button( __( 'Add Category', 'wp-helpdesk' ), 'primary', 'submit', true ); ?>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle save categories.
     *
     * @since 1.0.0
     */
    private function handle_save_categories() {
        if ( ! isset( $_POST['wp_helpdesk_categories_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_categories_nonce'], 'wp_helpdesk_save_categories' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $slug  = isset( $_POST['category_slug'] ) ? sanitize_title( $_POST['category_slug'] ) : '';
        $name  = isset( $_POST['category_name'] ) ? sanitize_text_field( $_POST['category_name'] ) : '';
        $color = isset( $_POST['category_color'] ) ? sanitize_hex_color( $_POST['category_color'] ) : '#3498db';
        $icon  = isset( $_POST['category_icon'] ) ? sanitize_text_field( $_POST['category_icon'] ) : 'dashicons-admin-generic';

        if ( empty( $slug ) || empty( $name ) ) {
            add_settings_error(
                'wp_helpdesk_categories',
                'missing_fields',
                __( 'Slug and name are required.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        $categories = get_option( 'wphd_categories', array() );

        // Check if slug already exists
        foreach ( $categories as $category ) {
            if ( $category['slug'] === $slug ) {
                add_settings_error(
                    'wp_helpdesk_categories',
                    'duplicate_slug',
                    __( 'A category with this slug already exists.', 'wp-helpdesk' ),
                    'error'
                );
                return;
            }
        }

        $categories[] = array(
            'slug'  => $slug,
            'name'  => $name,
            'color' => $color,
            'icon'  => $icon,
        );

        update_option( 'wphd_categories', $categories );

        add_settings_error(
            'wp_helpdesk_categories',
            'category_added',
            __( 'Category added successfully.', 'wp-helpdesk' ),
            'success'
        );
    }

    /**
     * Handle edit category.
     *
     * @since 1.0.0
     */
    private function handle_edit_category() {
        if ( ! isset( $_POST['wp_helpdesk_edit_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_edit_nonce'], 'wp_helpdesk_edit_category' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $old_slug = isset( $_POST['old_category_slug'] ) ? sanitize_title( $_POST['old_category_slug'] ) : '';
        $name     = isset( $_POST['category_name'] ) ? sanitize_text_field( $_POST['category_name'] ) : '';
        $color    = isset( $_POST['category_color'] ) ? sanitize_hex_color( $_POST['category_color'] ) : '#3498db';
        $icon     = isset( $_POST['category_icon'] ) ? sanitize_text_field( $_POST['category_icon'] ) : 'dashicons-admin-generic';

        if ( empty( $old_slug ) || empty( $name ) ) {
            add_settings_error(
                'wp_helpdesk_categories',
                'missing_fields',
                __( 'Name is required.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        $categories = get_option( 'wphd_categories', array() );
        $found = false;

        foreach ( $categories as $key => $category ) {
            if ( $category['slug'] === $old_slug ) {
                $categories[ $key ] = array(
                    'slug'  => $old_slug,
                    'name'  => $name,
                    'color' => $color,
                    'icon'  => $icon,
                );
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            add_settings_error(
                'wp_helpdesk_categories',
                'category_not_found',
                __( 'Category not found.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        update_option( 'wphd_categories', $categories );

        add_settings_error(
            'wp_helpdesk_categories',
            'category_updated',
            __( 'Category updated successfully.', 'wp-helpdesk' ),
            'success'
        );

        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-categories' ) );
        exit;
    }

    /**
     * Handle delete category.
     *
     * @since 1.0.0
     */
    private function handle_delete_category() {
        if ( ! isset( $_POST['wp_helpdesk_delete_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_delete_nonce'], 'wp_helpdesk_delete_category' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $slug = isset( $_POST['category_slug'] ) ? sanitize_title( $_POST['category_slug'] ) : '';

        if ( empty( $slug ) ) {
            return;
        }

        $categories = get_option( 'wphd_categories', array() );
        $new_categories = array();

        foreach ( $categories as $category ) {
            if ( $category['slug'] !== $slug ) {
                $new_categories[] = $category;
            }
        }

        update_option( 'wphd_categories', $new_categories );

        add_settings_error(
            'wp_helpdesk_categories',
            'category_deleted',
            __( 'Category deleted successfully.', 'wp-helpdesk' ),
            'success'
        );

        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-categories' ) );
        exit;
    }

    /**
     * Render the settings page.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        // Handle form submissions
        if ( isset( $_POST['action'] ) && 'save_settings' === $_POST['action'] ) {
            $this->handle_save_settings();
        }

        $allowed_tabs = array( 'general', 'email', 'sla', 'access_control', 'tools' );
        $active_tab   = isset( $_GET['tab'] ) && in_array( $_GET['tab'], $allowed_tabs, true ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        $settings     = get_option( 'wphd_settings', array() );
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php esc_html_e( 'Help Desk Settings', 'wp-helpdesk' ); ?></h1>
            <?php settings_errors( 'wp_helpdesk_settings' ); ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr( $this->menu_slug ); ?>-settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'General', 'wp-helpdesk' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( $this->menu_slug ); ?>-settings&tab=email" class="nav-tab <?php echo 'email' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Email', 'wp-helpdesk' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( $this->menu_slug ); ?>-settings&tab=sla" class="nav-tab <?php echo 'sla' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'SLA', 'wp-helpdesk' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( $this->menu_slug ); ?>-settings&tab=access_control" class="nav-tab <?php echo 'access_control' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Access Control', 'wp-helpdesk' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( $this->menu_slug ); ?>-settings&tab=tools" class="nav-tab <?php echo 'tools' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Tools', 'wp-helpdesk' ); ?>
                </a>
            </h2>

            <?php if ( 'tools' === $active_tab ) : ?>
                <?php $this->render_tools_tab(); ?>
            <?php elseif ( 'access_control' === $active_tab ) : ?>
            <form method="post">
                <?php wp_nonce_field( 'wp_helpdesk_save_settings', 'wp_helpdesk_settings_nonce' ); ?>
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="tab" value="access_control">
                <?php echo WPHD_Settings_Access_Control::render(); ?>
                <?php submit_button(); ?>
            </form>
            <?php else : ?>
            <form method="post">
                <?php wp_nonce_field( 'wp_helpdesk_save_settings', 'wp_helpdesk_settings_nonce' ); ?>
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab ); ?>">

                <?php
                if ( 'general' === $active_tab ) {
                    $this->render_general_settings_tab( $settings );
                } elseif ( 'email' === $active_tab ) {
                    $this->render_email_settings_tab( $settings );
                } elseif ( 'sla' === $active_tab ) {
                    $this->render_sla_settings_tab();
                }
                ?>

                <?php submit_button(); ?>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render general settings tab.
     *
     * @since 1.0.0
     */
    private function render_general_settings_tab( $settings ) {
        $statuses = get_option( 'wphd_statuses', array() );
        $priorities = get_option( 'wphd_priorities', array() );
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="default_status"><?php esc_html_e( 'Default Ticket Status', 'wp-helpdesk' ); ?></label>
                </th>
                <td>
                    <select name="default_status" id="default_status">
                        <?php foreach ( $statuses as $status ) : ?>
                            <option value="<?php echo esc_attr( $status['slug'] ); ?>" <?php selected( isset( $settings['default_status'] ) ? $settings['default_status'] : '', $status['slug'] ); ?>>
                                <?php echo esc_html( $status['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="default_priority"><?php esc_html_e( 'Default Priority', 'wp-helpdesk' ); ?></label>
                </th>
                <td>
                    <select name="default_priority" id="default_priority">
                        <?php foreach ( $priorities as $priority ) : ?>
                            <option value="<?php echo esc_attr( $priority['slug'] ); ?>" <?php selected( isset( $settings['default_priority'] ) ? $settings['default_priority'] : 'medium', $priority['slug'] ); ?>>
                                <?php echo esc_html( $priority['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tickets_per_page"><?php esc_html_e( 'Tickets Per Page', 'wp-helpdesk' ); ?></label>
                </th>
                <td>
                    <input type="number" name="tickets_per_page" id="tickets_per_page" value="<?php echo esc_attr( isset( $settings['items_per_page'] ) ? $settings['items_per_page'] : 20 ); ?>" min="1" max="100">
                    <p class="description"><?php esc_html_e( 'Number of tickets to display per page in the list view.', 'wp-helpdesk' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ticket_prefix"><?php esc_html_e( 'Ticket Prefix', 'wp-helpdesk' ); ?></label>
                </th>
                <td>
                    <input type="text" name="ticket_prefix" id="ticket_prefix" value="<?php echo esc_attr( isset( $settings['ticket_prefix'] ) ? $settings['ticket_prefix'] : 'TKT' ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Prefix for ticket numbers (e.g., TKT-00001)', 'wp-helpdesk' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php esc_html_e( 'Email Notifications', 'wp-helpdesk' ); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_email_notifications" value="1" <?php checked( isset( $settings['enable_email_notifications'] ) ? $settings['enable_email_notifications'] : true, true ); ?>>
                        <?php esc_html_e( 'Enable email notifications', 'wp-helpdesk' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render email settings tab.
     *
     * @since 1.0.0
     */
    private function render_email_settings_tab( $settings ) {
        $email_settings = get_option( 'wphd_email_settings', array() );
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="admin_email"><?php esc_html_e( 'Admin Notification Email', 'wp-helpdesk' ); ?></label>
                </th>
                <td>
                    <input type="email" name="admin_email" id="admin_email" value="<?php echo esc_attr( isset( $email_settings['admin_email'] ) ? $email_settings['admin_email'] : get_option( 'admin_email' ) ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Email address to receive notifications about new tickets.', 'wp-helpdesk' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="new_ticket_subject"><?php esc_html_e( 'New Ticket Email Subject', 'wp-helpdesk' ); ?></label>
                </th>
                <td>
                    <input type="text" name="new_ticket_subject" id="new_ticket_subject" value="<?php echo esc_attr( isset( $email_settings['new_ticket_subject'] ) ? $email_settings['new_ticket_subject'] : '[New Ticket] #{ticket_id}: {ticket_title}' ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Available variables: {ticket_id}, {ticket_title}, {status}, {priority}', 'wp-helpdesk' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="reply_subject"><?php esc_html_e( 'Reply Email Subject', 'wp-helpdesk' ); ?></label>
                </th>
                <td>
                    <input type="text" name="reply_subject" id="reply_subject" value="<?php echo esc_attr( isset( $email_settings['reply_subject'] ) ? $email_settings['reply_subject'] : '[Reply] #{ticket_id}: {ticket_title}' ); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="status_change_subject"><?php esc_html_e( 'Status Change Email Subject', 'wp-helpdesk' ); ?></label>
                </th>
                <td>
                    <input type="text" name="status_change_subject" id="status_change_subject" value="<?php echo esc_attr( isset( $email_settings['status_change_subject'] ) ? $email_settings['status_change_subject'] : '[Status Updated] #{ticket_id}: {ticket_title}' ); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render SLA settings tab.
     *
     * @since 1.0.0
     */
    private function render_sla_settings_tab() {
        $sla_settings = get_option( 'wphd_sla_settings', array() );
        $priorities = get_option( 'wphd_priorities', array() );
        ?>
        <h3><?php esc_html_e( 'SLA Response Times', 'wp-helpdesk' ); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="first_response"><?php esc_html_e( 'First Response Time (hours)', 'wp-helpdesk' ); ?></label>
                </th>
                <td>
                    <input type="number" name="first_response" id="first_response" value="<?php echo esc_attr( isset( $sla_settings['first_response'] ) ? $sla_settings['first_response'] / HOUR_IN_SECONDS : 4 ); ?>" min="1" step="0.5">
                    <p class="description"><?php esc_html_e( 'Default time before first response is due.', 'wp-helpdesk' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="resolution_time"><?php esc_html_e( 'Resolution Time (hours)', 'wp-helpdesk' ); ?></label>
                </th>
                <td>
                    <input type="number" name="resolution_time" id="resolution_time" value="<?php echo esc_attr( isset( $sla_settings['resolution'] ) ? $sla_settings['resolution'] / HOUR_IN_SECONDS : 24 ); ?>" min="1" step="0.5">
                    <p class="description"><?php esc_html_e( 'Default time before resolution is due.', 'wp-helpdesk' ); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e( 'SLA by Priority', 'wp-helpdesk' ); ?></h3>
        <table class="form-table">
            <?php foreach ( $priorities as $priority ) : ?>
                <?php
                $priority_sla = isset( $sla_settings['priority'][ $priority['slug'] ] ) ? $sla_settings['priority'][ $priority['slug'] ] : array();
                ?>
                <tr>
                    <th scope="row">
                        <?php echo esc_html( $priority['name'] ); ?>
                    </th>
                    <td>
                        <label>
                            <?php esc_html_e( 'First Response:', 'wp-helpdesk' ); ?>
                            <input type="number" name="priority_first_response[<?php echo esc_attr( $priority['slug'] ); ?>]" value="<?php echo esc_attr( isset( $priority_sla['first_response'] ) ? $priority_sla['first_response'] : 4 ); ?>" min="1" step="0.5" style="width: 80px;">
                            <?php esc_html_e( 'hours', 'wp-helpdesk' ); ?>
                        </label>
                        &nbsp;&nbsp;
                        <label>
                            <?php esc_html_e( 'Resolution:', 'wp-helpdesk' ); ?>
                            <input type="number" name="priority_resolution[<?php echo esc_attr( $priority['slug'] ); ?>]" value="<?php echo esc_attr( isset( $priority_sla['resolution'] ) ? $priority_sla['resolution'] : 24 ); ?>" min="1" step="0.5" style="width: 80px;">
                            <?php esc_html_e( 'hours', 'wp-helpdesk' ); ?>
                        </label>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    /**
     * Handle save settings.
     *
     * @since 1.0.0
     */
    private function handle_save_settings() {
        if ( ! isset( $_POST['wp_helpdesk_settings_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_settings_nonce'], 'wp_helpdesk_save_settings' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $tab = isset( $_POST['tab'] ) ? sanitize_text_field( $_POST['tab'] ) : 'general';

        if ( 'access_control' === $tab ) {
            // Handle access control save
            WPHD_Settings_Access_Control::save();
            add_settings_error(
                'wp_helpdesk_settings',
                'settings_saved',
                __( 'Access control permissions saved successfully.', 'wp-helpdesk' ),
                'success'
            );
            return;
        } elseif ( 'general' === $tab ) {
            $settings = array(
                'default_status'             => isset( $_POST['default_status'] ) ? sanitize_text_field( $_POST['default_status'] ) : 'open',
                'default_priority'           => isset( $_POST['default_priority'] ) ? sanitize_text_field( $_POST['default_priority'] ) : 'medium',
                'items_per_page'             => isset( $_POST['tickets_per_page'] ) ? intval( $_POST['tickets_per_page'] ) : 20,
                'ticket_prefix'              => isset( $_POST['ticket_prefix'] ) ? sanitize_text_field( $_POST['ticket_prefix'] ) : 'TKT',
                'enable_email_notifications' => isset( $_POST['enable_email_notifications'] ) ? true : false,
            );
            update_option( 'wphd_settings', $settings );
        } elseif ( 'email' === $tab ) {
            $email_settings = array(
                'admin_email'           => isset( $_POST['admin_email'] ) ? sanitize_email( $_POST['admin_email'] ) : get_option( 'admin_email' ),
                'new_ticket_subject'    => isset( $_POST['new_ticket_subject'] ) ? sanitize_text_field( $_POST['new_ticket_subject'] ) : '',
                'reply_subject'         => isset( $_POST['reply_subject'] ) ? sanitize_text_field( $_POST['reply_subject'] ) : '',
                'status_change_subject' => isset( $_POST['status_change_subject'] ) ? sanitize_text_field( $_POST['status_change_subject'] ) : '',
            );
            update_option( 'wphd_email_settings', $email_settings );
        } elseif ( 'sla' === $tab ) {
            $sla_settings = array(
                'first_response' => isset( $_POST['first_response'] ) ? floatval( $_POST['first_response'] ) * HOUR_IN_SECONDS : 4 * HOUR_IN_SECONDS,
                'resolution'     => isset( $_POST['resolution_time'] ) ? floatval( $_POST['resolution_time'] ) * HOUR_IN_SECONDS : 24 * HOUR_IN_SECONDS,
                'priority'       => array(),
            );

            if ( isset( $_POST['priority_first_response'] ) && is_array( $_POST['priority_first_response'] ) ) {
                foreach ( $_POST['priority_first_response'] as $priority => $hours ) {
                    $sla_settings['priority'][ sanitize_text_field( $priority ) ]['first_response'] = floatval( $hours );
                }
            }

            if ( isset( $_POST['priority_resolution'] ) && is_array( $_POST['priority_resolution'] ) ) {
                foreach ( $_POST['priority_resolution'] as $priority => $hours ) {
                    $sla_settings['priority'][ sanitize_text_field( $priority ) ]['resolution'] = floatval( $hours );
                }
            }

            update_option( 'wphd_sla_settings', $sla_settings );
        }

        add_settings_error(
            'wp_helpdesk_settings',
            'settings_saved',
            __( 'Settings saved successfully.', 'wp-helpdesk' ),
            'success'
        );
    }

    /**
     * Render the reports page.
     *
     * @since 1.0.0
     */
    public function render_reports_page() {
        if ( ! WPHD_Access_Control::can_access( 'reports' ) ) {
            wp_die( esc_html__( 'You do not have permission to access reports.', 'wp-helpdesk' ) );
        }
        
        // Get filter options
        $statuses   = get_option( 'wphd_statuses', array() );
        $priorities = get_option( 'wphd_priorities', array() );
        $categories = get_option( 'wphd_categories', array() );
        
        // Get users with tickets assigned
        $agents = get_users( array(
            'role__in' => array( 'administrator', 'editor' ),
            'orderby'  => 'display_name',
        ) );
        
        ?>
        <div class="wrap wp-helpdesk-wrap" id="wphd-reports-page">
            <div class="wphd-reports-header">
                <h1 id="wphd-report-title"><?php esc_html_e( 'Help Desk Reports', 'wp-helpdesk' ); ?></h1>
                <div>
                    <button type="button" id="wphd-print-report" class="button">
                        <span class="dashicons dashicons-printer"></span>
                        <?php esc_html_e( 'Print Report', 'wp-helpdesk' ); ?>
                    </button>
                    <button type="button" id="wphd-export-csv" class="button button-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'Export to CSV', 'wp-helpdesk' ); ?>
                    </button>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="wphd-reports-filters">
                <div class="wphd-filter-grid">
                    <div class="wphd-filter-field">
                        <label for="wphd-date-range"><?php esc_html_e( 'Date Range', 'wp-helpdesk' ); ?></label>
                        <select id="wphd-date-range">
                            <option value="today"><?php esc_html_e( 'Today', 'wp-helpdesk' ); ?></option>
                            <option value="week"><?php esc_html_e( 'This Week', 'wp-helpdesk' ); ?></option>
                            <option value="month" selected><?php esc_html_e( 'This Month', 'wp-helpdesk' ); ?></option>
                            <option value="quarter"><?php esc_html_e( 'This Quarter', 'wp-helpdesk' ); ?></option>
                            <option value="year"><?php esc_html_e( 'This Year', 'wp-helpdesk' ); ?></option>
                            <option value="custom"><?php esc_html_e( 'Custom Date Range', 'wp-helpdesk' ); ?></option>
                        </select>
                    </div>

                    <div class="wphd-filter-field">
                        <label for="wphd-filter-status"><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></label>
                        <select id="wphd-filter-status">
                            <option value=""><?php esc_html_e( 'All Statuses', 'wp-helpdesk' ); ?></option>
                            <?php foreach ( $statuses as $status ) : ?>
                                <option value="<?php echo esc_attr( $status['slug'] ); ?>">
                                    <?php echo esc_html( $status['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="wphd-filter-field">
                        <label for="wphd-filter-priority"><?php esc_html_e( 'Priority', 'wp-helpdesk' ); ?></label>
                        <select id="wphd-filter-priority">
                            <option value=""><?php esc_html_e( 'All Priorities', 'wp-helpdesk' ); ?></option>
                            <?php foreach ( $priorities as $priority ) : ?>
                                <option value="<?php echo esc_attr( $priority['slug'] ); ?>">
                                    <?php echo esc_html( $priority['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="wphd-filter-field">
                        <label for="wphd-filter-category"><?php esc_html_e( 'Category', 'wp-helpdesk' ); ?></label>
                        <select id="wphd-filter-category">
                            <option value=""><?php esc_html_e( 'All Categories', 'wp-helpdesk' ); ?></option>
                            <?php foreach ( $categories as $category ) : ?>
                                <option value="<?php echo esc_attr( $category['slug'] ); ?>">
                                    <?php echo esc_html( $category['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="wphd-filter-field">
                        <label for="wphd-filter-assignee"><?php esc_html_e( 'Assignee', 'wp-helpdesk' ); ?></label>
                        <select id="wphd-filter-assignee">
                            <option value=""><?php esc_html_e( 'All Agents', 'wp-helpdesk' ); ?></option>
                            <?php foreach ( $agents as $agent ) : ?>
                                <option value="<?php echo esc_attr( $agent->ID ); ?>">
                                    <?php echo esc_html( $agent->display_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="wphd-filter-field">
                        <label for="wphd-view-user"><?php esc_html_e( 'View as User', 'wp-helpdesk' ); ?></label>
                        <select id="wphd-view-user">
                            <option value="0"><?php esc_html_e( 'All Users (Team View)', 'wp-helpdesk' ); ?></option>
                            <?php foreach ( $agents as $agent ) : ?>
                                <option value="<?php echo esc_attr( $agent->ID ); ?>">
                                    <?php echo esc_html( $agent->display_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="wphd-custom-dates" style="display: none;">
                        <div class="wphd-filter-field">
                            <label for="wphd-date-start"><?php esc_html_e( 'Start Date', 'wp-helpdesk' ); ?></label>
                            <input type="date" id="wphd-date-start" value="<?php echo esc_attr( date( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>">
                        </div>
                        <div class="wphd-filter-field">
                            <label for="wphd-date-end"><?php esc_html_e( 'End Date', 'wp-helpdesk' ); ?></label>
                            <input type="date" id="wphd-date-end" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                        </div>
                    </div>
                </div>

                <div class="wphd-filter-actions">
                    <button type="button" id="wphd-generate-report" class="button button-primary">
                        <?php esc_html_e( 'Generate Report', 'wp-helpdesk' ); ?>
                    </button>
                </div>
            </div>

            <!-- Loading Indicator -->
            <div id="wphd-reports-loading" style="display: none;">
                <div class="wphd-spinner"></div>
                <p><?php esc_html_e( 'Loading report data...', 'wp-helpdesk' ); ?></p>
            </div>

            <!-- Reports Content -->
            <div id="wphd-reports-content">
                <!-- User Comparison (shown when viewing specific user) -->
                <div id="wphd-user-comparison" style="display: none;">
                    <h3><?php esc_html_e( 'Performance Comparison', 'wp-helpdesk' ); ?></h3>
                    <div class="wphd-comparison-cards">
                        <div class="wphd-comparison-card">
                            <div class="label"><?php esc_html_e( 'Tickets Assigned', 'wp-helpdesk' ); ?></div>
                            <div class="value" id="wphd-user-tickets">-</div>
                            <div class="diff" id="wphd-user-tickets-diff">-</div>
                        </div>
                        <div class="wphd-comparison-card">
                            <div class="label"><?php esc_html_e( 'Avg Resolution Time', 'wp-helpdesk' ); ?></div>
                            <div class="value" id="wphd-user-resolution">-</div>
                            <div class="diff" id="wphd-user-resolution-diff">-</div>
                        </div>
                        <div class="wphd-comparison-card">
                            <div class="label"><?php esc_html_e( 'SLA Compliance', 'wp-helpdesk' ); ?></div>
                            <div class="value" id="wphd-user-sla">-</div>
                            <div class="diff" id="wphd-user-sla-diff">-</div>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics Cards -->
                <div class="wphd-summary-cards">
                    <div class="wphd-summary-card">
                        <span class="card-value" id="wphd-stat-total">0</span>
                        <span class="card-label"><?php esc_html_e( 'Total Tickets', 'wp-helpdesk' ); ?></span>
                    </div>
                    <div class="wphd-summary-card">
                        <span class="card-value" id="wphd-stat-open">0</span>
                        <span class="card-label"><?php esc_html_e( 'Open Tickets', 'wp-helpdesk' ); ?></span>
                    </div>
                    <div class="wphd-summary-card">
                        <span class="card-value" id="wphd-stat-closed">0</span>
                        <span class="card-label"><?php esc_html_e( 'Closed/Resolved', 'wp-helpdesk' ); ?></span>
                    </div>
                    <div class="wphd-summary-card">
                        <span class="card-value" id="wphd-stat-avg-resolution">0h</span>
                        <span class="card-label"><?php esc_html_e( 'Avg Resolution Time', 'wp-helpdesk' ); ?></span>
                    </div>
                    <div class="wphd-summary-card">
                        <span class="card-value" id="wphd-stat-sla-compliance">0%</span>
                        <span class="card-label"><?php esc_html_e( 'SLA Compliance Rate', 'wp-helpdesk' ); ?></span>
                    </div>
                    <div class="wphd-summary-card">
                        <span class="card-value" id="wphd-stat-avg-response">0h</span>
                        <span class="card-label"><?php esc_html_e( 'Avg First Response', 'wp-helpdesk' ); ?></span>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="wphd-charts-grid">
                    <div class="wphd-chart-container">
                        <h3><?php esc_html_e( 'Tickets Over Time', 'wp-helpdesk' ); ?></h3>
                        <div class="wphd-chart-canvas">
                            <canvas id="wphd-tickets-over-time-chart"></canvas>
                        </div>
                    </div>

                    <div class="wphd-chart-container">
                        <h3><?php esc_html_e( 'Tickets by Status', 'wp-helpdesk' ); ?></h3>
                        <div class="wphd-chart-canvas">
                            <canvas id="wphd-tickets-by-status-chart"></canvas>
                        </div>
                    </div>

                    <div class="wphd-chart-container">
                        <h3><?php esc_html_e( 'Tickets by Priority', 'wp-helpdesk' ); ?></h3>
                        <div class="wphd-chart-canvas">
                            <canvas id="wphd-tickets-by-priority-chart"></canvas>
                        </div>
                    </div>

                    <div class="wphd-chart-container">
                        <h3><?php esc_html_e( 'Tickets by Category', 'wp-helpdesk' ); ?></h3>
                        <div class="wphd-chart-canvas">
                            <canvas id="wphd-tickets-by-category-chart"></canvas>
                        </div>
                    </div>

                    <div class="wphd-chart-container full-width">
                        <h3><?php esc_html_e( 'Agent Performance Comparison', 'wp-helpdesk' ); ?></h3>
                        <div class="wphd-chart-canvas">
                            <canvas id="wphd-agent-performance-chart"></canvas>
                        </div>
                    </div>

                    <div class="wphd-chart-container full-width">
                        <h3><?php esc_html_e( 'Resolution Time Trend', 'wp-helpdesk' ); ?></h3>
                        <div class="wphd-chart-canvas">
                            <canvas id="wphd-resolution-time-trend-chart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Ticket Details Table -->
                <div class="wphd-data-table-container">
                    <div class="wphd-data-table-header">
                        <h3><?php esc_html_e( 'Ticket Details', 'wp-helpdesk' ); ?></h3>
                    </div>
                    <table id="wphd-tickets-table" class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'ID', 'wp-helpdesk' ); ?></th>
                                <th><?php esc_html_e( 'Subject', 'wp-helpdesk' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></th>
                                <th><?php esc_html_e( 'Priority', 'wp-helpdesk' ); ?></th>
                                <th><?php esc_html_e( 'Category', 'wp-helpdesk' ); ?></th>
                                <th><?php esc_html_e( 'Assignee', 'wp-helpdesk' ); ?></th>
                                <th><?php esc_html_e( 'Created', 'wp-helpdesk' ); ?></th>
                                <th><?php esc_html_e( 'Resolved', 'wp-helpdesk' ); ?></th>
                                <th><?php esc_html_e( 'Resolution Time', 'wp-helpdesk' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="9"><?php esc_html_e( 'Loading...', 'wp-helpdesk' ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the statuses page.
     *
     * @since 1.0.0
     */
    public function render_statuses_page() {
        // Handle form submissions
        if ( isset( $_POST['action'] ) ) {
            if ( 'save_status' === $_POST['action'] ) {
                $this->handle_save_status();
            } elseif ( 'edit_status' === $_POST['action'] ) {
                $this->handle_edit_status();
            } elseif ( 'delete_status' === $_POST['action'] ) {
                $this->handle_delete_status();
            }
        }

        $statuses = get_option( 'wphd_statuses', array() );
        $editing_slug = isset( $_GET['edit'] ) ? sanitize_text_field( $_GET['edit'] ) : '';
        $editing_status = null;
        
        if ( $editing_slug ) {
            foreach ( $statuses as $status ) {
                if ( $status['slug'] === $editing_slug ) {
                    $editing_status = $status;
                    break;
                }
            }
        }
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php esc_html_e( 'Ticket Statuses', 'wp-helpdesk' ); ?></h1>
            <?php settings_errors( 'wp_helpdesk_statuses' ); ?>
            
            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <div style="flex: 2;">
                    <h2><?php esc_html_e( 'Existing Statuses', 'wp-helpdesk' ); ?></h2>
                    <?php if ( ! empty( $statuses ) ) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Color', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Order', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $statuses as $status ) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html( $status['slug'] ); ?></code></td>
                                        <td><?php echo esc_html( $status['name'] ); ?></td>
                                        <td>
                                            <span class="wphd-status-badge" style="background-color: <?php echo esc_attr( $status['color'] ); ?>; color: #fff; padding: 5px 10px; border-radius: 3px;">
                                                <?php echo esc_html( $status['name'] ); ?>
                                            </span>
                                            <code><?php echo esc_html( $status['color'] ); ?></code>
                                        </td>
                                        <td><?php echo esc_html( $status['order'] ); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-statuses&edit=' . $status['slug'] ) ); ?>" class="button button-small">
                                                <?php esc_html_e( 'Edit', 'wp-helpdesk' ); ?>
                                            </a>
                                            <form method="post" style="display: inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this status?', 'wp-helpdesk' ) ); ?>');">
                                                <?php wp_nonce_field( 'wp_helpdesk_delete_status', 'wp_helpdesk_delete_nonce' ); ?>
                                                <input type="hidden" name="action" value="delete_status">
                                                <input type="hidden" name="status_slug" value="<?php echo esc_attr( $status['slug'] ); ?>">
                                                <button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'wp-helpdesk' ); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No statuses found.', 'wp-helpdesk' ); ?></p>
                    <?php endif; ?>
                </div>
                
                <div style="flex: 1;">
                    <div class="postbox">
                        <div class="inside">
                            <h2><?php echo $editing_status ? esc_html__( 'Edit Status', 'wp-helpdesk' ) : esc_html__( 'Add New Status', 'wp-helpdesk' ); ?></h2>
                            <form method="post">
                                <?php wp_nonce_field( $editing_status ? 'wp_helpdesk_edit_status' : 'wp_helpdesk_save_status', $editing_status ? 'wp_helpdesk_edit_nonce' : 'wp_helpdesk_status_nonce' ); ?>
                                <input type="hidden" name="action" value="<?php echo $editing_status ? 'edit_status' : 'save_status'; ?>">
                                <?php if ( $editing_status ) : ?>
                                    <input type="hidden" name="old_status_slug" value="<?php echo esc_attr( $editing_status['slug'] ); ?>">
                                <?php endif; ?>
                                
                                <p>
                                    <label for="status_slug"><strong><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="text" name="status_slug" id="status_slug" class="widefat" required pattern="[a-z0-9\-]+" title="<?php esc_attr_e( 'Only lowercase letters, numbers, and hyphens', 'wp-helpdesk' ); ?>" value="<?php echo $editing_status ? esc_attr( $editing_status['slug'] ) : ''; ?>" <?php echo $editing_status ? 'readonly' : ''; ?>>
                                    <small><?php esc_html_e( 'Lowercase letters, numbers, and hyphens only', 'wp-helpdesk' ); ?></small>
                                </p>
                                
                                <p>
                                    <label for="status_name"><strong><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="text" name="status_name" id="status_name" class="widefat" required value="<?php echo $editing_status ? esc_attr( $editing_status['name'] ) : ''; ?>">
                                </p>
                                
                                <p>
                                    <label for="status_color"><strong><?php esc_html_e( 'Color', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="color" name="status_color" id="status_color" value="<?php echo $editing_status ? esc_attr( $editing_status['color'] ) : '#3498db'; ?>">
                                </p>
                                
                                <p>
                                    <label for="status_order"><strong><?php esc_html_e( 'Order', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="number" name="status_order" id="status_order" class="widefat" min="1" value="<?php echo $editing_status ? esc_attr( $editing_status['order'] ) : '1'; ?>">
                                    <small><?php esc_html_e( 'Lower numbers appear first', 'wp-helpdesk' ); ?></small>
                                </p>
                                
                                <p>
                                    <label>
                                        <input type="checkbox" name="status_is_default" value="1" <?php checked( ! empty( $editing_status['is_default'] ) ); ?>>
                                        <?php esc_html_e( 'Default status for new tickets', 'wp-helpdesk' ); ?>
                                    </label>
                                </p>
                                
                                <p>
                                    <label>
                                        <input type="checkbox" name="status_is_closed" value="1" <?php checked( ! empty( $editing_status['is_closed'] ) ); ?>>
                                        <?php esc_html_e( 'This is a closed/resolved status', 'wp-helpdesk' ); ?>
                                    </label>
                                </p>
                                
                                <?php if ( $editing_status ) : ?>
                                    <?php submit_button( __( 'Update Status', 'wp-helpdesk' ), 'primary', 'submit', true ); ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-statuses' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'wp-helpdesk' ); ?></a>
                                <?php else : ?>
                                    <?php submit_button( __( 'Add Status', 'wp-helpdesk' ), 'primary', 'submit', true ); ?>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the priorities page.
     *
     * @since 1.0.0
     */
    public function render_priorities_page() {
        // Handle form submissions
        if ( isset( $_POST['action'] ) ) {
            if ( 'save_priority' === $_POST['action'] ) {
                $this->handle_save_priority();
            } elseif ( 'edit_priority' === $_POST['action'] ) {
                $this->handle_edit_priority();
            } elseif ( 'delete_priority' === $_POST['action'] ) {
                $this->handle_delete_priority();
            }
        }

        $priorities = get_option( 'wphd_priorities', array() );
        $editing_slug = isset( $_GET['edit'] ) ? sanitize_text_field( $_GET['edit'] ) : '';
        $editing_priority = null;
        
        if ( $editing_slug ) {
            foreach ( $priorities as $priority ) {
                if ( $priority['slug'] === $editing_slug ) {
                    $editing_priority = $priority;
                    break;
                }
            }
        }
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php esc_html_e( 'Ticket Priorities', 'wp-helpdesk' ); ?></h1>
            <?php settings_errors( 'wp_helpdesk_priorities' ); ?>
            
            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <div style="flex: 2;">
                    <h2><?php esc_html_e( 'Existing Priorities', 'wp-helpdesk' ); ?></h2>
                    <?php if ( ! empty( $priorities ) ) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Color', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Order', 'wp-helpdesk' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $priorities as $priority ) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html( $priority['slug'] ); ?></code></td>
                                        <td><?php echo esc_html( $priority['name'] ); ?></td>
                                        <td>
                                            <span class="wphd-priority-badge" style="background-color: <?php echo esc_attr( $priority['color'] ); ?>; color: #fff; padding: 5px 10px; border-radius: 3px;">
                                                <?php echo esc_html( $priority['name'] ); ?>
                                            </span>
                                            <code><?php echo esc_html( $priority['color'] ); ?></code>
                                        </td>
                                        <td><?php echo esc_html( $priority['order'] ); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-priorities&edit=' . $priority['slug'] ) ); ?>" class="button button-small">
                                                <?php esc_html_e( 'Edit', 'wp-helpdesk' ); ?>
                                            </a>
                                            <form method="post" style="display: inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this priority?', 'wp-helpdesk' ) ); ?>');">
                                                <?php wp_nonce_field( 'wp_helpdesk_delete_priority', 'wp_helpdesk_delete_nonce' ); ?>
                                                <input type="hidden" name="action" value="delete_priority">
                                                <input type="hidden" name="priority_slug" value="<?php echo esc_attr( $priority['slug'] ); ?>">
                                                <button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'wp-helpdesk' ); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No priorities found.', 'wp-helpdesk' ); ?></p>
                    <?php endif; ?>
                </div>
                
                <div style="flex: 1;">
                    <div class="postbox">
                        <div class="inside">
                            <h2><?php echo $editing_priority ? esc_html__( 'Edit Priority', 'wp-helpdesk' ) : esc_html__( 'Add New Priority', 'wp-helpdesk' ); ?></h2>
                            <form method="post">
                                <?php wp_nonce_field( $editing_priority ? 'wp_helpdesk_edit_priority' : 'wp_helpdesk_save_priority', $editing_priority ? 'wp_helpdesk_edit_nonce' : 'wp_helpdesk_priority_nonce' ); ?>
                                <input type="hidden" name="action" value="<?php echo $editing_priority ? 'edit_priority' : 'save_priority'; ?>">
                                <?php if ( $editing_priority ) : ?>
                                    <input type="hidden" name="old_priority_slug" value="<?php echo esc_attr( $editing_priority['slug'] ); ?>">
                                <?php endif; ?>
                                
                                <p>
                                    <label for="priority_slug"><strong><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="text" name="priority_slug" id="priority_slug" class="widefat" required pattern="[a-z0-9\-]+" title="<?php esc_attr_e( 'Only lowercase letters, numbers, and hyphens', 'wp-helpdesk' ); ?>" value="<?php echo $editing_priority ? esc_attr( $editing_priority['slug'] ) : ''; ?>" <?php echo $editing_priority ? 'readonly' : ''; ?>>
                                    <small><?php esc_html_e( 'Lowercase letters, numbers, and hyphens only', 'wp-helpdesk' ); ?></small>
                                </p>
                                
                                <p>
                                    <label for="priority_name"><strong><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="text" name="priority_name" id="priority_name" class="widefat" required value="<?php echo $editing_priority ? esc_attr( $editing_priority['name'] ) : ''; ?>">
                                </p>
                                
                                <p>
                                    <label for="priority_color"><strong><?php esc_html_e( 'Color', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="color" name="priority_color" id="priority_color" value="<?php echo $editing_priority ? esc_attr( $editing_priority['color'] ) : '#f39c12'; ?>">
                                </p>
                                
                                <p>
                                    <label for="priority_order"><strong><?php esc_html_e( 'Order/Level', 'wp-helpdesk' ); ?></strong></label><br>
                                    <input type="number" name="priority_order" id="priority_order" class="widefat" min="1" value="<?php echo $editing_priority ? esc_attr( $editing_priority['order'] ) : '1'; ?>">
                                    <small><?php esc_html_e( 'Lower numbers = higher priority', 'wp-helpdesk' ); ?></small>
                                </p>
                                
                                <?php if ( $editing_priority ) : ?>
                                    <?php submit_button( __( 'Update Priority', 'wp-helpdesk' ), 'primary', 'submit', true ); ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-priorities' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'wp-helpdesk' ); ?></a>
                                <?php else : ?>
                                    <?php submit_button( __( 'Add Priority', 'wp-helpdesk' ), 'primary', 'submit', true ); ?>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get the menu slug.
     *
     * @since  1.0.0
     * @return string
     */
    public function get_menu_slug() {
        return $this->menu_slug;
    }

    /**
     * Handle form submissions.
     *
     * @since 1.0.0
     */
    public function handle_form_submissions() {
        if ( ! isset( $_POST['action'] ) ) {
            return;
        }

        // CRITICAL: Ensure database tables exist before ANY form processing
        // This is a safety check in addition to maybe_create_tables() which runs earlier
        // We check table existence first for performance
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organizations';
        if ( ! WPHD_Database::check_table_exists( $table ) ) {
            if ( class_exists( 'WPHD_Activator' ) ) {
                WPHD_Activator::create_tables();
            }
        }

        if ( 'create_ticket' === $_POST['action'] ) {
            $this->handle_create_ticket();
        } elseif ( 'update_ticket' === $_POST['action'] ) {
            $this->handle_update_ticket();
        } elseif ( 'add_comment' === $_POST['action'] ) {
            $this->handle_add_comment();
        } elseif ( 'save_organization' === $_POST['action'] ) {
            $this->handle_save_organization();
        } elseif ( 'add_organization_member' === $_POST['action'] ) {
            $this->handle_add_organization_member();
        } elseif ( 'remove_organization_member' === $_POST['action'] ) {
            $this->handle_remove_organization_member();
        } elseif ( 'save_organization_permissions' === $_POST['action'] ) {
            $this->handle_save_organization_permissions();
        } elseif ( 'save_organization_access_control' === $_POST['action'] ) {
            $this->handle_save_organization_access_control();
        }
    }

    /**
     * Handle ticket creation.
     *
     * @since 1.0.0
     */
    private function handle_create_ticket() {
        if ( ! isset( $_POST['wp_helpdesk_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_nonce'], 'wp_helpdesk_add_ticket' ) ) {
            return;
        }

        if ( ! current_user_can( 'read' ) ) {
            return;
        }

        $subject     = isset( $_POST['ticket_subject'] ) ? sanitize_text_field( $_POST['ticket_subject'] ) : '';
        $description = isset( $_POST['ticket_description'] ) ? wp_kses_post( $_POST['ticket_description'] ) : '';
        $priority    = isset( $_POST['ticket_priority'] ) ? sanitize_text_field( $_POST['ticket_priority'] ) : 'medium';
        $category    = isset( $_POST['ticket_category'] ) ? sanitize_text_field( $_POST['ticket_category'] ) : '';
        $assignee    = isset( $_POST['ticket_assignee'] ) ? intval( $_POST['ticket_assignee'] ) : 0;

        if ( empty( $subject ) ) {
            add_settings_error(
                'wp_helpdesk',
                'missing_subject',
                __( 'Subject is required.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        // Get default status
        $statuses       = get_option( 'wphd_statuses', array() );
        $default_status = 'open';
        foreach ( $statuses as $status ) {
            if ( ! empty( $status['is_default'] ) ) {
                $default_status = $status['slug'];
                break;
            }
        }

        // Create ticket post
        $ticket_id = wp_insert_post(
            array(
                'post_type'    => 'wphd_ticket',
                'post_title'   => $subject,
                'post_content' => $description,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            )
        );

        if ( is_wp_error( $ticket_id ) ) {
            add_settings_error(
                'wp_helpdesk',
                'create_failed',
                $ticket_id->get_error_message(),
                'error'
            );
            return;
        }

        // Save ticket metadata
        update_post_meta( $ticket_id, '_wphd_status', $default_status );
        update_post_meta( $ticket_id, '_wphd_priority', $priority );
        update_post_meta( $ticket_id, '_wphd_category', $category );
        update_post_meta( $ticket_id, '_wphd_assignee', $assignee );
        update_post_meta( $ticket_id, '_wphd_created_by', get_current_user_id() );

        // Create SLA entries
        $sla_settings   = get_option( 'wphd_sla_settings', array() );
        $first_response = isset( $sla_settings['first_response'] ) ? $sla_settings['first_response'] : 4 * HOUR_IN_SECONDS;
        $resolution     = isset( $sla_settings['resolution'] ) ? $sla_settings['resolution'] : 24 * HOUR_IN_SECONDS;

        $now = current_time( 'mysql' );
        WPHD_Database::create_sla(
            $ticket_id,
            date( 'Y-m-d H:i:s', strtotime( $now ) + $first_response ),
            date( 'Y-m-d H:i:s', strtotime( $now ) + $resolution )
        );

        // Add history entry
        WPHD_Database::add_history( $ticket_id, 'created', '', $subject );

        do_action( 'wphd_ticket_created', $ticket_id );

        add_settings_error(
            'wp_helpdesk',
            'ticket_created',
            sprintf( __( 'Ticket #%d created successfully.', 'wp-helpdesk' ), $ticket_id ),
            'success'
        );

        // Redirect to ticket list
        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-tickets' ) );
        exit;
    }

    /**
     * Handle ticket update.
     *
     * @since 1.0.0
     */
    private function handle_update_ticket() {
        if ( ! isset( $_POST['wp_helpdesk_update_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_update_nonce'], 'wp_helpdesk_update_ticket' ) ) {
            return;
        }

        if ( ! current_user_can( 'read' ) ) {
            return;
        }

        $ticket_id = isset( $_POST['ticket_id'] ) ? intval( $_POST['ticket_id'] ) : 0;

        if ( ! $ticket_id ) {
            return;
        }

        // Field name mapping for history
        $field_mapping = array(
            'ticket_status'   => 'status',
            'ticket_priority' => 'priority',
            'ticket_category' => 'category',
            'ticket_assignee' => 'assignee',
        );

        // Update meta fields
        $fields = array(
            'ticket_status'   => '_wphd_status',
            'ticket_priority' => '_wphd_priority',
            'ticket_category' => '_wphd_category',
            'ticket_assignee' => '_wphd_assignee',
        );

        foreach ( $fields as $field_name => $meta_key ) {
            if ( isset( $_POST[ $field_name ] ) ) {
                $old_value = get_post_meta( $ticket_id, $meta_key, true );
                $new_value = sanitize_text_field( $_POST[ $field_name ] );

                if ( $old_value !== $new_value ) {
                    update_post_meta( $ticket_id, $meta_key, $new_value );
                    $history_field = isset( $field_mapping[ $field_name ] ) ? $field_mapping[ $field_name ] : $field_name;
                    WPHD_Database::add_history( $ticket_id, $history_field, $old_value, $new_value );
                }
            }
        }

        do_action( 'wphd_ticket_updated', $ticket_id );

        add_settings_error(
            'wp_helpdesk',
            'ticket_updated',
            __( 'Ticket updated successfully.', 'wp-helpdesk' ),
            'success'
        );

        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-tickets&ticket_id=' . $ticket_id ) );
        exit;
    }

    /**
     * Handle add comment.
     *
     * @since 1.0.0
     */
    private function handle_add_comment() {
        if ( ! isset( $_POST['wp_helpdesk_comment_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_comment_nonce'], 'wp_helpdesk_add_comment' ) ) {
            return;
        }

        if ( ! current_user_can( 'read' ) ) {
            return;
        }

        $ticket_id   = isset( $_POST['ticket_id'] ) ? intval( $_POST['ticket_id'] ) : 0;
        $content     = isset( $_POST['comment_content'] ) ? wp_kses_post( $_POST['comment_content'] ) : '';
        $is_internal = isset( $_POST['is_internal'] ) ? 1 : 0;

        if ( ! $ticket_id || empty( $content ) ) {
            return;
        }

        $comment_id = WPHD_Database::add_comment(
            array(
                'ticket_id'   => $ticket_id,
                'content'     => $content,
                'is_internal' => $is_internal,
            )
        );

        if ( $comment_id ) {
            // Update first response SLA if this is the first response
            $sla = WPHD_Database::get_sla( $ticket_id );
            if ( $sla && empty( $sla->first_response_at ) ) {
                WPHD_Database::update_sla( $ticket_id, array( 'first_response_at' => current_time( 'mysql' ) ) );
            }

            add_settings_error(
                'wp_helpdesk',
                'comment_added',
                __( 'Comment added successfully.', 'wp-helpdesk' ),
                'success'
            );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-tickets&ticket_id=' . $ticket_id ) );
        exit;
    }

    /**
     * Handle save status.
     *
     * @since 1.0.0
     */
    private function handle_save_status() {
        if ( ! isset( $_POST['wp_helpdesk_status_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_status_nonce'], 'wp_helpdesk_save_status' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $slug       = isset( $_POST['status_slug'] ) ? sanitize_title( $_POST['status_slug'] ) : '';
        $name       = isset( $_POST['status_name'] ) ? sanitize_text_field( $_POST['status_name'] ) : '';
        $color      = isset( $_POST['status_color'] ) ? sanitize_hex_color( $_POST['status_color'] ) : '#3498db';
        $order      = isset( $_POST['status_order'] ) ? intval( $_POST['status_order'] ) : 1;
        $is_default = isset( $_POST['status_is_default'] ) ? true : false;
        $is_closed  = isset( $_POST['status_is_closed'] ) ? true : false;

        if ( empty( $slug ) || empty( $name ) ) {
            add_settings_error(
                'wp_helpdesk_statuses',
                'missing_fields',
                __( 'Slug and name are required.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        $statuses = get_option( 'wphd_statuses', array() );

        // Check if slug already exists
        foreach ( $statuses as $status ) {
            if ( $status['slug'] === $slug ) {
                add_settings_error(
                    'wp_helpdesk_statuses',
                    'duplicate_slug',
                    __( 'A status with this slug already exists.', 'wp-helpdesk' ),
                    'error'
                );
                return;
            }
        }

        // If setting as default, unset other defaults
        if ( $is_default ) {
            foreach ( $statuses as $key => $status ) {
                $statuses[ $key ]['is_default'] = false;
            }
        }

        $statuses[] = array(
            'slug'       => $slug,
            'name'       => $name,
            'color'      => $color,
            'order'      => $order,
            'is_default' => $is_default,
            'is_closed'  => $is_closed,
        );

        update_option( 'wphd_statuses', $statuses );

        add_settings_error(
            'wp_helpdesk_statuses',
            'status_added',
            __( 'Status added successfully.', 'wp-helpdesk' ),
            'success'
        );
    }

    /**
     * Handle edit status.
     *
     * @since 1.0.0
     */
    private function handle_edit_status() {
        if ( ! isset( $_POST['wp_helpdesk_edit_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_edit_nonce'], 'wp_helpdesk_edit_status' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $old_slug   = isset( $_POST['old_status_slug'] ) ? sanitize_title( $_POST['old_status_slug'] ) : '';
        $name       = isset( $_POST['status_name'] ) ? sanitize_text_field( $_POST['status_name'] ) : '';
        $color      = isset( $_POST['status_color'] ) ? sanitize_hex_color( $_POST['status_color'] ) : '#3498db';
        $order      = isset( $_POST['status_order'] ) ? intval( $_POST['status_order'] ) : 1;
        $is_default = isset( $_POST['status_is_default'] ) ? true : false;
        $is_closed  = isset( $_POST['status_is_closed'] ) ? true : false;

        if ( empty( $old_slug ) || empty( $name ) ) {
            add_settings_error(
                'wp_helpdesk_statuses',
                'missing_fields',
                __( 'Name is required.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        $statuses = get_option( 'wphd_statuses', array() );
        $found = false;

        // If setting as default, unset other defaults
        if ( $is_default ) {
            foreach ( $statuses as $key => $status ) {
                $statuses[ $key ]['is_default'] = false;
            }
        }

        foreach ( $statuses as $key => $status ) {
            if ( $status['slug'] === $old_slug ) {
                $statuses[ $key ]['name']       = $name;
                $statuses[ $key ]['color']      = $color;
                $statuses[ $key ]['order']      = $order;
                $statuses[ $key ]['is_default'] = $is_default;
                $statuses[ $key ]['is_closed']  = $is_closed;
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            add_settings_error(
                'wp_helpdesk_statuses',
                'status_not_found',
                __( 'Status not found.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        update_option( 'wphd_statuses', $statuses );

        add_settings_error(
            'wp_helpdesk_statuses',
            'status_updated',
            __( 'Status updated successfully.', 'wp-helpdesk' ),
            'success'
        );

        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-statuses' ) );
        exit;
    }

    /**
     * Handle delete status.
     *
     * @since 1.0.0
     */
    private function handle_delete_status() {
        if ( ! isset( $_POST['wp_helpdesk_delete_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_delete_nonce'], 'wp_helpdesk_delete_status' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $slug = isset( $_POST['status_slug'] ) ? sanitize_title( $_POST['status_slug'] ) : '';

        if ( empty( $slug ) ) {
            return;
        }

        $statuses = get_option( 'wphd_statuses', array() );
        $new_statuses = array();

        foreach ( $statuses as $status ) {
            if ( $status['slug'] !== $slug ) {
                $new_statuses[] = $status;
            }
        }

        update_option( 'wphd_statuses', $new_statuses );

        add_settings_error(
            'wp_helpdesk_statuses',
            'status_deleted',
            __( 'Status deleted successfully.', 'wp-helpdesk' ),
            'success'
        );

        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-statuses' ) );
        exit;
    }

    /**
     * Handle save priority.
     *
     * @since 1.0.0
     */
    private function handle_save_priority() {
        if ( ! isset( $_POST['wp_helpdesk_priority_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_priority_nonce'], 'wp_helpdesk_save_priority' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $slug  = isset( $_POST['priority_slug'] ) ? sanitize_title( $_POST['priority_slug'] ) : '';
        $name  = isset( $_POST['priority_name'] ) ? sanitize_text_field( $_POST['priority_name'] ) : '';
        $color = isset( $_POST['priority_color'] ) ? sanitize_hex_color( $_POST['priority_color'] ) : '#f39c12';
        $order = isset( $_POST['priority_order'] ) ? intval( $_POST['priority_order'] ) : 1;

        if ( empty( $slug ) || empty( $name ) ) {
            add_settings_error(
                'wp_helpdesk_priorities',
                'missing_fields',
                __( 'Slug and name are required.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        $priorities = get_option( 'wphd_priorities', array() );

        // Check if slug already exists
        foreach ( $priorities as $priority ) {
            if ( $priority['slug'] === $slug ) {
                add_settings_error(
                    'wp_helpdesk_priorities',
                    'duplicate_slug',
                    __( 'A priority with this slug already exists.', 'wp-helpdesk' ),
                    'error'
                );
                return;
            }
        }

        $priorities[] = array(
            'slug'  => $slug,
            'name'  => $name,
            'color' => $color,
            'order' => $order,
        );

        update_option( 'wphd_priorities', $priorities );

        add_settings_error(
            'wp_helpdesk_priorities',
            'priority_added',
            __( 'Priority added successfully.', 'wp-helpdesk' ),
            'success'
        );
    }

    /**
     * Handle edit priority.
     *
     * @since 1.0.0
     */
    private function handle_edit_priority() {
        if ( ! isset( $_POST['wp_helpdesk_edit_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_edit_nonce'], 'wp_helpdesk_edit_priority' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $old_slug = isset( $_POST['old_priority_slug'] ) ? sanitize_title( $_POST['old_priority_slug'] ) : '';
        $name     = isset( $_POST['priority_name'] ) ? sanitize_text_field( $_POST['priority_name'] ) : '';
        $color    = isset( $_POST['priority_color'] ) ? sanitize_hex_color( $_POST['priority_color'] ) : '#f39c12';
        $order    = isset( $_POST['priority_order'] ) ? intval( $_POST['priority_order'] ) : 1;

        if ( empty( $old_slug ) || empty( $name ) ) {
            add_settings_error(
                'wp_helpdesk_priorities',
                'missing_fields',
                __( 'Name is required.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        $priorities = get_option( 'wphd_priorities', array() );
        $found = false;

        foreach ( $priorities as $key => $priority ) {
            if ( $priority['slug'] === $old_slug ) {
                $priorities[ $key ]['name'] = $name;
                $priorities[ $key ]['color'] = $color;
                $priorities[ $key ]['order'] = $order;
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            add_settings_error(
                'wp_helpdesk_priorities',
                'priority_not_found',
                __( 'Priority not found.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        update_option( 'wphd_priorities', $priorities );

        add_settings_error(
            'wp_helpdesk_priorities',
            'priority_updated',
            __( 'Priority updated successfully.', 'wp-helpdesk' ),
            'success'
        );

        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-priorities' ) );
        exit;
    }

    /**
     * Handle delete priority.
     *
     * @since 1.0.0
     */
    private function handle_delete_priority() {
        if ( ! isset( $_POST['wp_helpdesk_delete_nonce'] ) || ! wp_verify_nonce( $_POST['wp_helpdesk_delete_nonce'], 'wp_helpdesk_delete_priority' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $slug = isset( $_POST['priority_slug'] ) ? sanitize_title( $_POST['priority_slug'] ) : '';

        if ( empty( $slug ) ) {
            return;
        }

        $priorities = get_option( 'wphd_priorities', array() );
        $new_priorities = array();

        foreach ( $priorities as $priority ) {
            if ( $priority['slug'] !== $slug ) {
                $new_priorities[] = $priority;
            }
        }

        update_option( 'wphd_priorities', $new_priorities );

        add_settings_error(
            'wp_helpdesk_priorities',
            'priority_deleted',
            __( 'Priority deleted successfully.', 'wp-helpdesk' ),
            'success'
        );

        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-priorities' ) );
        exit;
    }

    /**
     * Render the organizations page.
     *
     * @since 1.0.0
     */
    public function render_organizations_page() {
        // Check if viewing/editing a single organization
        if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['org_id'] ) ) {
            $this->render_organization_edit_page( intval( $_GET['org_id'] ) );
            return;
        }

        if ( isset( $_GET['action'] ) && 'new' === $_GET['action'] ) {
            $this->render_organization_edit_page( 0 );
            return;
        }

        // List all organizations
        $organizations = WPHD_Organizations::get_all();
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Organizations', 'wp-helpdesk' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations&action=new' ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Add New Organization', 'wp-helpdesk' ); ?>
            </a>
            <hr class="wp-header-end">
            <?php settings_errors( 'wp_helpdesk_organizations' ); ?>

            <?php if ( ! empty( $organizations ) ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Members', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Domains', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Created', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $organizations as $org ) : ?>
                            <?php
                            $member_count = WPHD_Organizations::get_member_count( $org->id );
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations&action=edit&org_id=' . $org->id ) ); ?>">
                                            <?php echo esc_html( $org->name ); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html( $member_count ); ?></td>
                                <td><?php echo esc_html( $org->allowed_domains ? $org->allowed_domains : __( 'None', 'wp-helpdesk' ) ); ?></td>
                                <td>
                                    <?php if ( 'active' === $org->status ) : ?>
                                        <span style="color: green;">● <?php esc_html_e( 'Active', 'wp-helpdesk' ); ?></span>
                                    <?php else : ?>
                                        <span style="color: #999;">● <?php esc_html_e( 'Inactive', 'wp-helpdesk' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $org->created_at ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations&action=edit&org_id=' . $org->id ) ); ?>" class="button button-small">
                                        <?php esc_html_e( 'Edit', 'wp-helpdesk' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No organizations found.', 'wp-helpdesk' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render organization edit/create page.
     *
     * @since 1.0.0
     * @param int $org_id Organization ID (0 for new).
     */
    private function render_organization_edit_page( $org_id ) {
        $is_new = 0 === $org_id;
        $org = $is_new ? null : WPHD_Organizations::get( $org_id );

        if ( ! $is_new && ! $org ) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Organization Not Found', 'wp-helpdesk' ); ?></h1>
                <p><?php esc_html_e( 'The requested organization could not be found.', 'wp-helpdesk' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations' ) ); ?>" class="button">
                    <?php esc_html_e( 'Back to Organizations', 'wp-helpdesk' ); ?>
                </a>
            </div>
            <?php
            return;
        }

        $settings = $org ? maybe_unserialize( $org->settings ) : array();
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overview';

        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php echo $is_new ? esc_html__( 'Add New Organization', 'wp-helpdesk' ) : esc_html( sprintf( __( 'Edit Organization: %s', 'wp-helpdesk' ), $org->name ) ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations' ) ); ?>" class="button">
                &larr; <?php esc_html_e( 'Back to Organizations', 'wp-helpdesk' ); ?>
            </a>
            <hr class="wp-header-end">
            <?php settings_errors( 'wp_helpdesk_organizations' ); ?>

            <?php if ( ! $is_new ) : ?>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations&action=edit&org_id=' . $org_id . '&tab=overview' ) ); ?>" class="nav-tab <?php echo 'overview' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Overview', 'wp-helpdesk' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations&action=edit&org_id=' . $org_id . '&tab=members' ) ); ?>" class="nav-tab <?php echo 'members' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Members', 'wp-helpdesk' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations&action=edit&org_id=' . $org_id . '&tab=permissions' ) ); ?>" class="nav-tab <?php echo 'permissions' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Permissions', 'wp-helpdesk' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations&action=edit&org_id=' . $org_id . '&tab=access_control' ) ); ?>" class="nav-tab <?php echo 'access_control' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Access Control', 'wp-helpdesk' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations&action=edit&org_id=' . $org_id . '&tab=logs' ) ); ?>" class="nav-tab <?php echo 'logs' === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Change Log', 'wp-helpdesk' ); ?>
                </a>
            </h2>
            <?php endif; ?>

            <?php if ( 'overview' === $tab || $is_new ) : ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'wphd_save_organization', 'wphd_organization_nonce' ); ?>
                    <input type="hidden" name="action" value="save_organization">
                    <?php if ( ! $is_new ) : ?>
                        <input type="hidden" name="org_id" value="<?php echo esc_attr( $org_id ); ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="org_name"><?php esc_html_e( 'Organization Name', 'wp-helpdesk' ); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" name="org_name" id="org_name" class="regular-text" required value="<?php echo $org ? esc_attr( $org->name ) : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="org_slug"><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="org_slug" id="org_slug" class="regular-text" value="<?php echo $org ? esc_attr( $org->slug ) : ''; ?>" <?php echo $org ? 'readonly' : ''; ?>>
                                <p class="description"><?php esc_html_e( 'Auto-generated from name if left empty. Cannot be changed after creation.', 'wp-helpdesk' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="org_description"><?php esc_html_e( 'Description', 'wp-helpdesk' ); ?></label>
                            </th>
                            <td>
                                <textarea name="org_description" id="org_description" rows="5" class="large-text"><?php echo $org ? esc_textarea( $org->description ) : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="org_domains"><?php esc_html_e( 'Allowed Email Domains', 'wp-helpdesk' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="org_domains" id="org_domains" class="regular-text" value="<?php echo $org ? esc_attr( $org->allowed_domains ) : ''; ?>">
                                <p class="description"><?php esc_html_e( 'Comma-separated list (e.g., "acme.com, acme.org"). New users with these domains will be auto-added.', 'wp-helpdesk' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="org_status"><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></label>
                            </th>
                            <td>
                                <select name="org_status" id="org_status">
                                    <option value="active" <?php selected( $org ? $org->status : 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'wp-helpdesk' ); ?></option>
                                    <option value="inactive" <?php selected( $org ? $org->status : 'active', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'wp-helpdesk' ); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button( $is_new ? __( 'Create Organization', 'wp-helpdesk' ) : __( 'Update Organization', 'wp-helpdesk' ) ); ?>
                </form>
            <?php elseif ( 'members' === $tab ) : ?>
                <?php $this->render_organization_members_tab( $org_id ); ?>
            <?php elseif ( 'permissions' === $tab ) : ?>
                <?php $this->render_organization_permissions_tab( $org_id, $settings ); ?>
            <?php elseif ( 'access_control' === $tab ) : ?>
                <?php $this->render_organization_access_control_tab( $org_id, $settings ); ?>
            <?php elseif ( 'logs' === $tab ) : ?>
                <?php $this->render_organization_logs_tab( $org_id ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render organization members tab.
     *
     * @since 1.0.0
     * @param int $org_id Organization ID.
     */
    private function render_organization_members_tab( $org_id ) {
        $members = WPHD_Organizations::get_members( $org_id );
        $all_users = get_users( array( 'orderby' => 'display_name' ) );
        ?>
        <div style="margin-top: 20px;">
            <h3><?php esc_html_e( 'Add Member', 'wp-helpdesk' ); ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'wphd_add_member', 'wphd_member_nonce' ); ?>
                <input type="hidden" name="action" value="add_organization_member">
                <input type="hidden" name="org_id" value="<?php echo esc_attr( $org_id ); ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="user_id"><?php esc_html_e( 'Select User', 'wp-helpdesk' ); ?></label>
                        </th>
                        <td>
                            <select name="user_id" id="user_id" required>
                                <option value=""><?php esc_html_e( 'Select a user...', 'wp-helpdesk' ); ?></option>
                                <?php foreach ( $all_users as $user ) : ?>
                                    <option value="<?php echo esc_attr( $user->ID ); ?>">
                                        <?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php submit_button( __( 'Add Member', 'wp-helpdesk' ), 'secondary', 'submit', false ); ?>
                        </td>
                    </tr>
                </table>
            </form>

            <h3><?php esc_html_e( 'Current Members', 'wp-helpdesk' ); ?></h3>
            <?php if ( ! empty( $members ) ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'User', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Role', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Added', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $members as $member ) : ?>
                            <?php
                            $user = get_userdata( $member->user_id );
                            if ( ! $user ) {
                                continue;
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html( $user->display_name ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td><?php echo esc_html( ucfirst( $member->role ) ); ?></td>
                                <td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $member->added_at ) ); ?></td>
                                <td>
                                    <form method="post" action="" style="display: inline-block;">
                                        <?php wp_nonce_field( 'wphd_remove_member', 'wphd_member_nonce' ); ?>
                                        <input type="hidden" name="action" value="remove_organization_member">
                                        <input type="hidden" name="org_id" value="<?php echo esc_attr( $org_id ); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr( $member->user_id ); ?>">
                                        <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove this member?', 'wp-helpdesk' ) ); ?>');">
                                            <?php esc_html_e( 'Remove', 'wp-helpdesk' ); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No members yet.', 'wp-helpdesk' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render organization permissions tab.
     *
     * @since 1.0.0
     * @param int   $org_id   Organization ID.
     * @param array $settings Organization settings.
     */
    private function render_organization_permissions_tab( $org_id, $settings ) {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'wphd_save_permissions', 'wphd_permissions_nonce' ); ?>
            <input type="hidden" name="action" value="save_organization_permissions">
            <input type="hidden" name="org_id" value="<?php echo esc_attr( $org_id ); ?>">

            <div style="margin-top: 20px;">
                <h3><?php esc_html_e( 'Ticket Visibility Rules', 'wp-helpdesk' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Visibility Settings', 'wp-helpdesk' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="visibility_mode" value="own" <?php checked( empty( $settings['view_organization_tickets'] ) && empty( $settings['view_all_tickets'] ) ); ?>>
                                    <?php esc_html_e( 'View own tickets only', 'wp-helpdesk' ); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="visibility_mode" value="organization" <?php checked( ! empty( $settings['view_organization_tickets'] ) ); ?>>
                                    <?php esc_html_e( 'View all tickets from organization members', 'wp-helpdesk' ); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="visibility_mode" value="all" <?php checked( ! empty( $settings['view_all_tickets'] ) ); ?>>
                                    <?php esc_html_e( 'View all tickets (for internal/admin teams)', 'wp-helpdesk' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e( 'Ticket Permissions', 'wp-helpdesk' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Create & Edit', 'wp-helpdesk' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="can_create_tickets" value="1" <?php checked( ! empty( $settings['can_create_tickets'] ) ); ?>>
                                    <?php esc_html_e( 'Can create new tickets', 'wp-helpdesk' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="can_edit_own_tickets" value="1" <?php checked( ! empty( $settings['can_edit_own_tickets'] ) ); ?>>
                                    <?php esc_html_e( 'Can edit their own tickets', 'wp-helpdesk' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="can_edit_org_tickets" value="1" <?php checked( ! empty( $settings['can_edit_org_tickets'] ) ); ?>>
                                    <?php esc_html_e( 'Can edit any ticket in their organization', 'wp-helpdesk' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Delete', 'wp-helpdesk' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="can_delete_own_tickets" value="1" <?php checked( ! empty( $settings['can_delete_own_tickets'] ) ); ?>>
                                    <?php esc_html_e( 'Can delete their own tickets', 'wp-helpdesk' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="can_delete_org_tickets" value="1" <?php checked( ! empty( $settings['can_delete_org_tickets'] ) ); ?>>
                                    <?php esc_html_e( 'Can delete any ticket in their organization', 'wp-helpdesk' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Comments', 'wp-helpdesk' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="can_comment_on_tickets" value="1" <?php checked( ! empty( $settings['can_comment_on_tickets'] ) ); ?>>
                                    <?php esc_html_e( 'Can add comments to tickets', 'wp-helpdesk' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="can_view_internal_comments" value="1" <?php checked( ! empty( $settings['can_view_internal_comments'] ) ); ?>>
                                    <?php esc_html_e( 'Can view internal/private comments', 'wp-helpdesk' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Permissions', 'wp-helpdesk' ) ); ?>
            </div>
        </form>
        <?php
    }

    /**
     * Render organization access control tab.
     *
     * @since 1.0.0
     * @param int   $org_id   Organization ID.
     * @param array $settings Organization settings.
     */
    private function render_organization_access_control_tab( $org_id, $settings ) {
        // Get all features
        $features = WPHD_Access_Control::get_controllable_features();
        
        // Get current access control settings
        $access_control_mode = isset( $settings['access_control_mode'] ) ? $settings['access_control_mode'] : 'role_defaults';
        $access_control      = isset( $settings['access_control'] ) ? $settings['access_control'] : array();
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'wphd_save_org_access_control', 'wphd_org_access_control_nonce' ); ?>
            <input type="hidden" name="action" value="save_organization_access_control">
            <input type="hidden" name="org_id" value="<?php echo esc_attr( $org_id ); ?>">

            <div style="margin-top: 20px;">
                <h3><?php esc_html_e( 'Access Control Mode', 'wp-helpdesk' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Permission Mode', 'wp-helpdesk' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="access_control_mode" value="role_defaults" <?php checked( $access_control_mode, 'role_defaults' ); ?>>
                                    <?php esc_html_e( 'Use Role Defaults', 'wp-helpdesk' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Members inherit permissions based on their WordPress role.', 'wp-helpdesk' ); ?></p>
                                <br>
                                <label>
                                    <input type="radio" name="access_control_mode" value="custom" <?php checked( $access_control_mode, 'custom' ); ?>>
                                    <?php esc_html_e( 'Custom Permissions for this Organization', 'wp-helpdesk' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Override role permissions with organization-specific settings.', 'wp-helpdesk' ); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <div id="wphd-custom-permissions-section" style="<?php echo 'custom' !== $access_control_mode ? 'display: none;' : ''; ?>">
                    <h3><?php esc_html_e( 'Custom Feature Access', 'wp-helpdesk' ); ?></h3>
                    <p><?php esc_html_e( 'Configure which features organization members can access. These settings override role-based permissions.', 'wp-helpdesk' ); ?></p>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Feature', 'wp-helpdesk' ); ?></th>
                                <th style="text-align: center; width: 120px;"><?php esc_html_e( 'Allow Access', 'wp-helpdesk' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $features as $feature_key => $feature_data ) : ?>
                                <?php
                                $is_checked = false;
                                if ( isset( $access_control[ $feature_key ] ) ) {
                                    $is_checked = (bool) $access_control[ $feature_key ];
                                } else {
                                    $is_checked = isset( $feature_data['default'] ) ? $feature_data['default'] : false;
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $feature_data['label'] ); ?></strong>
                                        <?php if ( ! empty( $feature_data['description'] ) ) : ?>
                                            <br>
                                            <span class="description"><?php echo esc_html( $feature_data['description'] ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <input 
                                            type="checkbox" 
                                            name="access_control[<?php echo esc_attr( $feature_key ); ?>]" 
                                            value="1" 
                                            <?php checked( $is_checked, true ); ?>
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php submit_button( __( 'Save Access Control Settings', 'wp-helpdesk' ) ); ?>
            </div>
        </form>
        <?php
        // Enqueue inline script for toggling custom permissions
        $script = "
            jQuery(document).ready(function($) {
                $('input[name=\"access_control_mode\"]').on('change', function() {
                    if ($(this).val() === 'custom') {
                        $('#wphd-custom-permissions-section').slideDown();
                    } else {
                        $('#wphd-custom-permissions-section').slideUp();
                    }
                });
            });
        ";
        wp_add_inline_script( 'wp-helpdesk-admin', $script );
    }

    /**
     * Render organization logs tab.
     *
     * @since 1.0.0
     * @param int $org_id Organization ID.
     */
    private function render_organization_logs_tab( $org_id ) {
        $logs = WPHD_Organizations::get_logs( $org_id, array( 'limit' => 100 ) );
        ?>
        <div style="margin-top: 20px;">
            <h3><?php esc_html_e( 'Change Log', 'wp-helpdesk' ); ?></h3>
            <?php if ( ! empty( $logs ) ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date & Time', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'User', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Details', 'wp-helpdesk' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $log ) : ?>
                            <?php
                            $user = get_userdata( $log->user_id );
                            $details = '';
                            
                            if ( 'updated' === $log->action || 'permission_changed' === $log->action ) {
                                $details = sprintf(
                                    __( 'Changed "%s" from "%s" to "%s"', 'wp-helpdesk' ),
                                    esc_html( $log->field_name ),
                                    esc_html( $log->old_value ),
                                    esc_html( $log->new_value )
                                );
                            } elseif ( 'user_added' === $log->action ) {
                                $details = sprintf( __( 'Added user "%s"', 'wp-helpdesk' ), esc_html( $log->new_value ) );
                            } elseif ( 'user_removed' === $log->action ) {
                                $details = sprintf( __( 'Removed user "%s"', 'wp-helpdesk' ), esc_html( $log->old_value ) );
                            } elseif ( 'created' === $log->action ) {
                                $details = sprintf( __( 'Created organization "%s"', 'wp-helpdesk' ), esc_html( $log->new_value ) );
                            } elseif ( 'deleted' === $log->action ) {
                                $details = sprintf( __( 'Deleted organization "%s"', 'wp-helpdesk' ), esc_html( $log->new_value ) );
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log->created_at ) ); ?></td>
                                <td><?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Unknown', 'wp-helpdesk' ); ?></td>
                                <td><?php echo esc_html( ucwords( str_replace( '_', ' ', $log->action ) ) ); ?></td>
                                <td><?php echo wp_kses_post( $details ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No change log entries yet.', 'wp-helpdesk' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle save organization.
     *
     * @since 1.0.0
     */
    private function handle_save_organization() {
        if ( ! isset( $_POST['wphd_organization_nonce'] ) || ! wp_verify_nonce( $_POST['wphd_organization_nonce'], 'wphd_save_organization' ) ) {
            return;
        }

        $org_id = isset( $_POST['org_id'] ) ? intval( $_POST['org_id'] ) : 0;

        // Check permissions based on action
        if ( $org_id > 0 ) {
            if ( ! WPHD_Organization_Permissions::can_edit_organization( $org_id ) ) {
                wp_die( esc_html__( 'You do not have permission to edit this organization.', 'wp-helpdesk' ) );
            }
        } else {
            if ( ! WPHD_Organization_Permissions::can_create_organizations() ) {
                wp_die( esc_html__( 'You do not have permission to create organizations.', 'wp-helpdesk' ) );
            }
        }

        $name = isset( $_POST['org_name'] ) ? sanitize_text_field( $_POST['org_name'] ) : '';

        if ( empty( $name ) ) {
            add_settings_error(
                'wp_helpdesk_organizations',
                'missing_name',
                __( 'Organization name is required.', 'wp-helpdesk' ),
                'error'
            );
            return;
        }

        $data = array(
            'name'            => $name,
            'slug'            => isset( $_POST['org_slug'] ) ? sanitize_title( $_POST['org_slug'] ) : '',
            'description'     => isset( $_POST['org_description'] ) ? wp_kses_post( $_POST['org_description'] ) : '',
            'allowed_domains' => isset( $_POST['org_domains'] ) ? sanitize_text_field( $_POST['org_domains'] ) : '',
            'status'          => isset( $_POST['org_status'] ) ? sanitize_text_field( $_POST['org_status'] ) : 'active',
        );

        if ( $org_id > 0 ) {
            $result = WPHD_Organizations::update( $org_id, $data );
            if ( $result ) {
                add_settings_error(
                    'wp_helpdesk_organizations',
                    'org_updated',
                    __( 'Organization updated successfully.', 'wp-helpdesk' ),
                    'success'
                );
            } else {
                add_settings_error(
                    'wp_helpdesk_organizations',
                    'update_failed',
                    __( 'Failed to update organization.', 'wp-helpdesk' ),
                    'error'
                );
            }
        } else {
            $new_org_id = WPHD_Organizations::create( $data );
            if ( $new_org_id ) {
                add_settings_error(
                    'wp_helpdesk_organizations',
                    'org_created',
                    __( 'Organization created successfully.', 'wp-helpdesk' ),
                    'success'
                );
                wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations&action=edit&org_id=' . $new_org_id ) );
                exit;
            } else {
                add_settings_error(
                    'wp_helpdesk_organizations',
                    'create_failed',
                    __( 'Failed to create organization. The slug may already exist.', 'wp-helpdesk' ),
                    'error'
                );
            }
        }
    }

    /**
     * Handle add organization member.
     *
     * @since 1.0.0
     */
    private function handle_add_organization_member() {
        if ( ! isset( $_POST['wphd_member_nonce'] ) || ! wp_verify_nonce( $_POST['wphd_member_nonce'], 'wphd_add_member' ) ) {
            return;
        }

        $org_id  = isset( $_POST['org_id'] ) ? intval( $_POST['org_id'] ) : 0;
        $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

        if ( ! $org_id || ! $user_id ) {
            return;
        }

        // Check permissions
        if ( ! WPHD_Organization_Permissions::can_manage_members( $org_id ) ) {
            wp_die( esc_html__( 'You do not have permission to manage members for this organization.', 'wp-helpdesk' ) );
        }

        $result = WPHD_Organizations::add_member( $org_id, $user_id );

        if ( $result ) {
            add_settings_error(
                'wp_helpdesk_organizations',
                'member_added',
                __( 'Member added successfully.', 'wp-helpdesk' ),
                'success'
            );
        } else {
            add_settings_error(
                'wp_helpdesk_organizations',
                'add_member_failed',
                __( 'Failed to add member. They may already be a member.', 'wp-helpdesk' ),
                'error'
            );
        }
    }

    /**
     * Handle remove organization member.
     *
     * @since 1.0.0
     */
    private function handle_remove_organization_member() {
        if ( ! isset( $_POST['wphd_member_nonce'] ) || ! wp_verify_nonce( $_POST['wphd_member_nonce'], 'wphd_remove_member' ) ) {
            return;
        }

        $org_id  = isset( $_POST['org_id'] ) ? intval( $_POST['org_id'] ) : 0;
        $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

        if ( ! $org_id || ! $user_id ) {
            return;
        }

        // Check permissions
        if ( ! WPHD_Organization_Permissions::can_manage_members( $org_id ) ) {
            wp_die( esc_html__( 'You do not have permission to manage members for this organization.', 'wp-helpdesk' ) );
        }

        $result = WPHD_Organizations::remove_member( $org_id, $user_id );

        if ( $result ) {
            add_settings_error(
                'wp_helpdesk_organizations',
                'member_removed',
                __( 'Member removed successfully.', 'wp-helpdesk' ),
                'success'
            );
        } else {
            add_settings_error(
                'wp_helpdesk_organizations',
                'remove_member_failed',
                __( 'Failed to remove member.', 'wp-helpdesk' ),
                'error'
            );
        }
    }

    /**
     * Handle save organization permissions.
     *
     * @since 1.0.0
     */
    private function handle_save_organization_permissions() {
        if ( ! isset( $_POST['wphd_permissions_nonce'] ) || ! wp_verify_nonce( $_POST['wphd_permissions_nonce'], 'wphd_save_permissions' ) ) {
            return;
        }

        $org_id = isset( $_POST['org_id'] ) ? intval( $_POST['org_id'] ) : 0;

        if ( ! $org_id ) {
            return;
        }

        // Check if user can change permissions (only administrators)
        if ( ! WPHD_Organization_Permissions::can_change_permissions( $org_id ) ) {
            wp_die( esc_html__( 'You do not have permission to change permissions for this organization.', 'wp-helpdesk' ) );
        }

        $visibility_mode = isset( $_POST['visibility_mode'] ) ? sanitize_text_field( $_POST['visibility_mode'] ) : 'own';

        $settings = array(
            'view_own_tickets_only'       => 'own' === $visibility_mode,
            'view_organization_tickets'   => 'organization' === $visibility_mode,
            'view_all_tickets'            => 'all' === $visibility_mode,
            'can_create_tickets'          => isset( $_POST['can_create_tickets'] ),
            'can_edit_own_tickets'        => isset( $_POST['can_edit_own_tickets'] ),
            'can_edit_org_tickets'        => isset( $_POST['can_edit_org_tickets'] ),
            'can_delete_own_tickets'      => isset( $_POST['can_delete_own_tickets'] ),
            'can_delete_org_tickets'      => isset( $_POST['can_delete_org_tickets'] ),
            'can_comment_on_tickets'      => isset( $_POST['can_comment_on_tickets'] ),
            'can_view_internal_comments'  => isset( $_POST['can_view_internal_comments'] ),
        );

        $result = WPHD_Organizations::update( $org_id, array( 'settings' => $settings ) );

        if ( $result !== false ) {
            add_settings_error(
                'wp_helpdesk_organizations',
                'permissions_saved',
                __( 'Permissions saved successfully.', 'wp-helpdesk' ),
                'success'
            );
        } else {
            add_settings_error(
                'wp_helpdesk_organizations',
                'permissions_save_failed',
                __( 'Failed to save permissions.', 'wp-helpdesk' ),
                'error'
            );
        }
    }

    /**
     * Handle save organization access control settings.
     *
     * @since 1.0.0
     */
    private function handle_save_organization_access_control() {
        if ( ! isset( $_POST['wphd_org_access_control_nonce'] ) || ! wp_verify_nonce( $_POST['wphd_org_access_control_nonce'], 'wphd_save_org_access_control' ) ) {
            return;
        }

        $org_id = isset( $_POST['org_id'] ) ? intval( $_POST['org_id'] ) : 0;

        if ( ! $org_id ) {
            return;
        }

        // Check if user can change permissions (only administrators)
        if ( ! WPHD_Organization_Permissions::can_change_permissions( $org_id ) ) {
            wp_die( esc_html__( 'You do not have permission to change access control for this organization.', 'wp-helpdesk' ) );
        }

        // Get current settings
        $org      = WPHD_Organizations::get( $org_id );
        $settings = $org && ! empty( $org->settings ) ? maybe_unserialize( $org->settings ) : array();

        // Ensure settings is an array
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        // Get access control mode
        $access_control_mode = isset( $_POST['access_control_mode'] ) ? sanitize_text_field( $_POST['access_control_mode'] ) : 'role_defaults';
        $settings['access_control_mode'] = $access_control_mode;

        // Process access control permissions if custom mode
        if ( 'custom' === $access_control_mode && isset( $_POST['access_control'] ) ) {
            $features         = WPHD_Access_Control::get_controllable_features();
            $access_control   = array();
            $submitted_access = wp_unslash( $_POST['access_control'] );

            foreach ( $features as $feature_key => $feature_data ) {
                // Checkbox is only present if checked
                $access_control[ $feature_key ] = isset( $submitted_access[ $feature_key ] );
            }

            $settings['access_control'] = $access_control;
        } elseif ( 'role_defaults' === $access_control_mode ) {
            // Clear custom access control settings when switching to role defaults
            unset( $settings['access_control'] );
        }

        // Update the organization
        $result = WPHD_Organizations::update( $org_id, array( 'settings' => $settings ) );

        if ( $result !== false ) {
            add_settings_error(
                'wp_helpdesk_organizations',
                'access_control_saved',
                __( 'Access control settings saved successfully.', 'wp-helpdesk' ),
                'success'
            );
        } else {
            add_settings_error(
                'wp_helpdesk_organizations',
                'access_control_save_failed',
                __( 'Failed to save access control settings.', 'wp-helpdesk' ),
                'error'
            );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-organizations&action=edit&org_id=' . $org_id . '&tab=access_control' ) );
        exit;
    }
    
    /**
     * Render the tools tab with database status.
     *
     * @since 1.0.0
     */
    private function render_tools_tab() {
        global $wpdb;
        
        $tables = array(
            'wphd_comments' => 'Ticket Comments',
            'wphd_history' => 'Ticket History',
            'wphd_sla_log' => 'SLA Tracking',
            'wphd_action_items' => 'Action Items',
            'wphd_handovers' => 'Handovers',
            'wphd_handover_tickets' => 'Handover Tickets',
            'wphd_ticket_meta' => 'Ticket Meta',
            'wphd_organizations' => 'Organizations',
            'wphd_organization_members' => 'Organization Members',
            'wphd_organization_logs' => 'Organization Logs',
        );
        
        // Get all existing tables in a single query for efficiency
        $existing_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}wphd_%'");
        
        ?>
        <div style="margin-top: 20px;">
            <h2><?php esc_html_e( 'Database Status', 'wp-helpdesk' ); ?></h2>
            <p><?php esc_html_e( 'This page shows the status of all required database tables for the Help Desk plugin.', 'wp-helpdesk' ); ?></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Table', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Table Name', 'wp-helpdesk' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $all_exist = true;
                    foreach ( $tables as $table_suffix => $label ) :
                        $table_name = $wpdb->prefix . $table_suffix;
                        $exists = in_array( $table_name, $existing_tables, true );
                        if ( ! $exists ) {
                            $all_exist = false;
                        }
                        $status = $exists ? '<span style="color:green; font-weight: bold;">✓ ' . esc_html__( 'Exists', 'wp-helpdesk' ) . '</span>' : '<span style="color:red; font-weight: bold;">✗ ' . esc_html__( 'Missing', 'wp-helpdesk' ) . '</span>';
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $label ); ?></strong></td>
                            <td><code><?php echo esc_html( $table_name ); ?></code></td>
                            <td><?php echo wp_kses_post( $status ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ( is_multisite() ) : ?>
                <p style="margin-top: 15px;">
                    <strong><?php esc_html_e( 'Multisite Information:', 'wp-helpdesk' ); ?></strong><br>
                    <?php
                    echo sprintf(
                        esc_html__( 'Current Site: %s (ID: %d)', 'wp-helpdesk' ),
                        esc_html( get_bloginfo( 'name' ) ),
                        get_current_blog_id()
                    );
                    ?>
                </p>
            <?php endif; ?>
            
            <form method="post" style="margin-top: 20px;">
                <?php wp_nonce_field( 'wphd_repair_database', 'wphd_repair_nonce' ); ?>
                <input type="hidden" name="action" value="repair_database">
                
                <?php if ( $all_exist ) : ?>
                    <p style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
                        <?php esc_html_e( '✓ All database tables are present and healthy.', 'wp-helpdesk' ); ?>
                    </p>
                    <p>
                        <button type="submit" class="button button-secondary">
                            <?php esc_html_e( 'Recreate All Tables', 'wp-helpdesk' ); ?>
                        </button>
                        <span class="description"><?php esc_html_e( 'This will attempt to recreate all tables. Existing data will not be lost.', 'wp-helpdesk' ); ?></span>
                    </p>
                <?php else : ?>
                    <p style="padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
                        <?php esc_html_e( '⚠ Some database tables are missing. Click the button below to create them.', 'wp-helpdesk' ); ?>
                    </p>
                    <p>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Repair Database', 'wp-helpdesk' ); ?>
                        </button>
                        <span class="description"><?php esc_html_e( 'This will create all missing tables.', 'wp-helpdesk' ); ?></span>
                    </p>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle database repair action.
     *
     * @since 1.0.0
     */
    public function handle_repair_database() {
        if ( ! isset( $_POST['action'] ) || 'repair_database' !== $_POST['action'] ) {
            return;
        }
        
        if ( ! isset( $_POST['wphd_repair_nonce'] ) || ! wp_verify_nonce( $_POST['wphd_repair_nonce'], 'wphd_repair_database' ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-helpdesk' ) );
        }
        
        // Run table creation
        WPHD_Activator::create_tables();
        
        // Clear the transient to force a recheck
        delete_transient( 'wphd_tables_verified_' . get_current_blog_id() );
        
        add_settings_error(
            'wp_helpdesk_settings',
            'database_repaired',
            __( 'Database tables have been created/repaired successfully.', 'wp-helpdesk' ),
            'success'
        );
        
        // Redirect to prevent resubmission
        wp_safe_redirect( admin_url( 'admin.php?page=' . $this->menu_slug . '-settings&tab=tools&repaired=1' ) );
        exit;
    }
}

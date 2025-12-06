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
    private $capability = 'manage_options';

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
        add_submenu_page(
            $this->menu_slug,
            __( 'Dashboard', 'wp-helpdesk' ),
            __( 'Dashboard', 'wp-helpdesk' ),
            $this->capability,
            $this->menu_slug,
            array( $this, 'render_dashboard_page' )
        );

        // Tickets submenu.
        add_submenu_page(
            $this->menu_slug,
            __( 'All Tickets', 'wp-helpdesk' ),
            __( 'All Tickets', 'wp-helpdesk' ),
            $this->capability,
            $this->menu_slug . '-tickets',
            array( $this, 'render_tickets_page' )
        );

        // Add New Ticket submenu.
        add_submenu_page(
            $this->menu_slug,
            __( 'Add New Ticket', 'wp-helpdesk' ),
            __( 'Add New', 'wp-helpdesk' ),
            $this->capability,
            $this->menu_slug . '-add-ticket',
            array( $this, 'render_add_ticket_page' )
        );

        // Categories submenu.
        add_submenu_page(
            $this->menu_slug,
            __( 'Categories', 'wp-helpdesk' ),
            __( 'Categories', 'wp-helpdesk' ),
            $this->capability,
            $this->menu_slug . '-categories',
            array( $this, 'render_categories_page' )
        );

        // Settings submenu.
        add_submenu_page(
            $this->menu_slug,
            __( 'Settings', 'wp-helpdesk' ),
            __( 'Settings', 'wp-helpdesk' ),
            $this->capability,
            $this->menu_slug . '-settings',
            array( $this, 'render_settings_page' )
        );

        // Reports submenu.
        add_submenu_page(
            $this->menu_slug,
            __( 'Reports', 'wp-helpdesk' ),
            __( 'Reports', 'wp-helpdesk' ),
            $this->capability,
            $this->menu_slug . '-reports',
            array( $this, 'render_reports_page' )
        );
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
            WPHD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPHD_VERSION
        );

        // Enqueue admin scripts.
        wp_enqueue_script(
            'wp-helpdesk-admin',
            WPHD_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WPHD_VERSION,
            true
        );

        // Localize script with data.
        wp_localize_script(
            'wp-helpdesk-admin',
            'wpHelpDesk',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'wp_helpdesk_nonce' ),
                'i18n'    => array(
                    'confirm_delete' => __( 'Are you sure you want to delete this item?', 'wp-helpdesk' ),
                    'saving'         => __( 'Saving...', 'wp-helpdesk' ),
                    'saved'          => __( 'Saved!', 'wp-helpdesk' ),
                    'error'          => __( 'An error occurred. Please try again.', 'wp-helpdesk' ),
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
        ?>
        <div class="wp-helpdesk-widgets">
            <div class="wp-helpdesk-widget">
                <h3><?php esc_html_e( 'Ticket Statistics', 'wp-helpdesk' ); ?></h3>
                <ul>
                    <li><?php esc_html_e( 'Open Tickets:', 'wp-helpdesk' ); ?> <strong>0</strong></li>
                    <li><?php esc_html_e( 'Pending Tickets:', 'wp-helpdesk' ); ?> <strong>0</strong></li>
                    <li><?php esc_html_e( 'Closed Tickets:', 'wp-helpdesk' ); ?> <strong>0</strong></li>
                </ul>
            </div>
            <div class="wp-helpdesk-widget">
                <h3><?php esc_html_e( 'Quick Actions', 'wp-helpdesk' ); ?></h3>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-add-ticket' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Create New Ticket', 'wp-helpdesk' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-tickets' ) ); ?>" class="button">
                    <?php esc_html_e( 'View All Tickets', 'wp-helpdesk' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render the tickets page.
     *
     * @since 1.0.0
     */
    public function render_tickets_page() {
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'All Tickets', 'wp-helpdesk' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-add-ticket' ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Add New', 'wp-helpdesk' ); ?>
            </a>
            <hr class="wp-header-end">
            <div class="wp-helpdesk-tickets-list">
                <p><?php esc_html_e( 'No tickets found.', 'wp-helpdesk' ); ?></p>
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
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php esc_html_e( 'Add New Ticket', 'wp-helpdesk' ); ?></h1>
            <form method="post" action="" class="wp-helpdesk-form">
                <?php wp_nonce_field( 'wp_helpdesk_add_ticket', 'wp_helpdesk_nonce' ); ?>
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
                            <label for="ticket_priority"><?php esc_html_e( 'Priority', 'wp-helpdesk' ); ?></label>
                        </th>
                        <td>
                            <select name="ticket_priority" id="ticket_priority">
                                <option value="low"><?php esc_html_e( 'Low', 'wp-helpdesk' ); ?></option>
                                <option value="medium" selected><?php esc_html_e( 'Medium', 'wp-helpdesk' ); ?></option>
                                <option value="high"><?php esc_html_e( 'High', 'wp-helpdesk' ); ?></option>
                                <option value="urgent"><?php esc_html_e( 'Urgent', 'wp-helpdesk' ); ?></option>
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
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php esc_html_e( 'Ticket Categories', 'wp-helpdesk' ); ?></h1>
            <div class="wp-helpdesk-categories">
                <p><?php esc_html_e( 'Manage ticket categories here.', 'wp-helpdesk' ); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render the settings page.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php esc_html_e( 'Help Desk Settings', 'wp-helpdesk' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wp_helpdesk_settings' );
                do_settings_sections( 'wp_helpdesk_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the reports page.
     *
     * @since 1.0.0
     */
    public function render_reports_page() {
        ?>
        <div class="wrap wp-helpdesk-wrap">
            <h1><?php esc_html_e( 'Help Desk Reports', 'wp-helpdesk' ); ?></h1>
            <div class="wp-helpdesk-reports">
                <p><?php esc_html_e( 'View ticket statistics and reports here.', 'wp-helpdesk' ); ?></p>
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
}
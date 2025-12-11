<?php
/**
 * Handover Settings Class
 *
 * Handles handover sections configuration in settings.
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
 * Class WPHD_Settings_Handover
 *
 * Manages handover section settings.
 *
 * @since 1.0.0
 */
class WPHD_Settings_Handover {

    /**
     * Instance of this class.
     *
     * @since  1.0.0
     * @access private
     * @var    WPHD_Settings_Handover
     */
    private static $instance = null;

    /**
     * Get the singleton instance of this class.
     *
     * @since  1.0.0
     * @return WPHD_Settings_Handover
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
        // Register AJAX handlers
        add_action( 'wp_ajax_wphd_get_handover_sections', array( $this, 'ajax_get_sections' ) );
        add_action( 'wp_ajax_wphd_save_handover_section', array( $this, 'ajax_save_section' ) );
        add_action( 'wp_ajax_wphd_delete_handover_section', array( $this, 'ajax_delete_section' ) );
    }

    /**
     * Render the handover settings page.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        if ( ! WPHD_Access_Control::can_access( 'settings_manage' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage settings.', 'wp-helpdesk' ) );
        }

        $sections = WPHD_Database::get_handover_sections( false );

        ?>
        <div class="wrap wp-helpdesk-wrap wphd-settings-handover-wrap">
            <h1><?php esc_html_e( 'Handover Report Settings', 'wp-helpdesk' ); ?></h1>
            
            <div class="wphd-settings-section">
                <h2><?php esc_html_e( 'Sections Management', 'wp-helpdesk' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Manage the sections available in handover reports. Each section can be enabled or disabled as needed.', 'wp-helpdesk' ); ?>
                </p>
                
                <button type="button" class="button button-primary wphd-add-section-btn">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e( 'Add New Section', 'wp-helpdesk' ); ?>
                </button>
                
                <table class="wp-list-table widefat fixed striped wphd-sections-table" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Order', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wphd-sections-list">
                        <?php if ( empty( $sections ) ) : ?>
                            <tr>
                                <td colspan="5"><?php esc_html_e( 'No sections found.', 'wp-helpdesk' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $sections as $section ) : ?>
                                <tr data-section-id="<?php echo esc_attr( $section->id ); ?>">
                                    <td><strong><?php echo esc_html( $section->name ); ?></strong></td>
                                    <td><code><?php echo esc_html( $section->slug ); ?></code></td>
                                    <td><?php echo esc_html( $section->display_order ); ?></td>
                                    <td>
                                        <?php if ( $section->is_active ) : ?>
                                            <span class="wphd-status-badge wphd-status-active"><?php esc_html_e( 'Active', 'wp-helpdesk' ); ?></span>
                                        <?php else : ?>
                                            <span class="wphd-status-badge wphd-status-inactive"><?php esc_html_e( 'Inactive', 'wp-helpdesk' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="wphd-action-buttons">
                                        <button type="button" class="button button-small wphd-edit-section-btn" data-section-id="<?php echo esc_attr( $section->id ); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                            <?php esc_html_e( 'Edit', 'wp-helpdesk' ); ?>
                                        </button>
                                        <button type="button" class="button button-small button-link-delete wphd-delete-section-btn" data-section-id="<?php echo esc_attr( $section->id ); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                            <?php esc_html_e( 'Delete', 'wp-helpdesk' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section Edit/Add Modal -->
        <div id="wphd-section-modal" class="wphd-modal" style="display: none;">
            <div class="wphd-modal-content">
                <div class="wphd-modal-header">
                    <h2 id="wphd-section-modal-title"><?php esc_html_e( 'Add New Section', 'wp-helpdesk' ); ?></h2>
                    <span class="wphd-modal-close">&times;</span>
                </div>
                <form id="wphd-section-form">
                    <input type="hidden" id="wphd-section-id" name="section_id" value="">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="wphd-section-name"><?php esc_html_e( 'Section Name', 'wp-helpdesk' ); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="wphd-section-name" name="section_name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="wphd-section-slug"><?php esc_html_e( 'Slug', 'wp-helpdesk' ); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="wphd-section-slug" name="section_slug" class="regular-text" required>
                                <p class="description"><?php esc_html_e( 'Unique identifier (auto-generated from name)', 'wp-helpdesk' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="wphd-section-description"><?php esc_html_e( 'Description', 'wp-helpdesk' ); ?></label>
                            </th>
                            <td>
                                <textarea id="wphd-section-description" name="section_description" rows="3" class="large-text"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="wphd-section-order"><?php esc_html_e( 'Display Order', 'wp-helpdesk' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="wphd-section-order" name="section_order" min="0" value="0" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'Status', 'wp-helpdesk' ); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="wphd-section-active" name="section_active" value="1" checked>
                                    <?php esc_html_e( 'Enable this section', 'wp-helpdesk' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="wphd-modal-footer">
                        <button type="button" class="button wphd-cancel-section-btn"><?php esc_html_e( 'Cancel', 'wp-helpdesk' ); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Section', 'wp-helpdesk' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to get sections.
     *
     * @since 1.0.0
     */
    public function ajax_get_sections() {
        check_ajax_referer( 'wphd_nonce', 'nonce' );

        if ( ! WPHD_Access_Control::can_access( 'settings_manage' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
        }

        $sections = WPHD_Database::get_handover_sections( false );

        wp_send_json_success( array( 'sections' => $sections ) );
    }

    /**
     * AJAX handler to save a section.
     *
     * @since 1.0.0
     */
    public function ajax_save_section() {
        check_ajax_referer( 'wphd_nonce', 'nonce' );

        if ( ! WPHD_Access_Control::can_access( 'settings_manage' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
        }

        $section_id = isset( $_POST['section_id'] ) ? intval( $_POST['section_id'] ) : 0;
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
        $description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
        $display_order = isset( $_POST['display_order'] ) ? intval( $_POST['display_order'] ) : 0;
        $is_active = isset( $_POST['is_active'] ) ? intval( $_POST['is_active'] ) : 0;

        if ( empty( $name ) || empty( $slug ) ) {
            wp_send_json_error( array( 'message' => __( 'Name and slug are required.', 'wp-helpdesk' ) ) );
        }

        $data = array(
            'name'          => $name,
            'slug'          => $slug,
            'description'   => $description,
            'display_order' => $display_order,
            'is_active'     => $is_active,
        );

        if ( $section_id > 0 ) {
            // Update existing section
            $result = WPHD_Database::update_handover_section( $section_id, $data );
            $message = __( 'Section updated successfully!', 'wp-helpdesk' );
        } else {
            // Create new section
            $result = WPHD_Database::create_handover_section( $data );
            $message = __( 'Section created successfully!', 'wp-helpdesk' );
        }

        if ( $result ) {
            wp_send_json_success( array( 'message' => $message ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to save section. Please try again.', 'wp-helpdesk' ) ) );
        }
    }

    /**
     * AJAX handler to delete a section.
     *
     * @since 1.0.0
     */
    public function ajax_delete_section() {
        check_ajax_referer( 'wphd_nonce', 'nonce' );

        if ( ! WPHD_Access_Control::can_access( 'settings_manage' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
        }

        $section_id = isset( $_POST['section_id'] ) ? intval( $_POST['section_id'] ) : 0;

        if ( ! $section_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid section ID.', 'wp-helpdesk' ) ) );
        }

        $result = WPHD_Database::delete_handover_section( $section_id );

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Section deleted successfully!', 'wp-helpdesk' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete section. Please try again.', 'wp-helpdesk' ) ) );
        }
    }
}

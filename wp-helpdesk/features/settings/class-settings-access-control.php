<?php
/**
 * Settings Access Control Handler
 *
 * Handles the Access Control settings page for role-based permissions.
 *
 * @package     WP_HelpDesk
 * @subpackage  Features/Settings
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHD_Settings_Access_Control
 *
 * Manages the Access Control settings interface.
 *
 * @since 1.0.0
 */
class WPHD_Settings_Access_Control {

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    WPHD_Settings_Access_Control
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @since  1.0.0
	 * @return WPHD_Settings_Access_Control
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
		// Hooks are handled by parent settings page
	}

	/**
	 * Render the access control settings tab.
	 *
	 * @since 1.0.0
	 */
	public static function render() {
		// Get all features and roles
		$features = WPHD_Access_Control::get_controllable_features();
		$roles    = WPHD_Access_Control::get_manageable_roles();
		
		// Get current permissions
		$permissions = get_option( 'wphd_role_permissions', array() );
		
		ob_start();
		?>
		<div style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Role-Based Access Control', 'wp-helpdesk' ); ?></h2>
			<p><?php esc_html_e( 'Configure which pages and features each WordPress role can access. Administrators always have full access.', 'wp-helpdesk' ); ?></p>
			
			<div style="overflow-x: auto;">
				<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
					<thead>
						<tr>
							<th style="width: 250px;"><?php esc_html_e( 'Feature', 'wp-helpdesk' ); ?></th>
							<?php foreach ( $roles as $role_slug => $role_name ) : ?>
								<th style="text-align: center; width: 120px;">
									<?php echo esc_html( translate_user_role( $role_name ) ); ?>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $features as $feature_key => $feature_data ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $feature_data['label'] ); ?></strong>
									<?php if ( ! empty( $feature_data['description'] ) ) : ?>
										<br>
										<span class="description"><?php echo esc_html( $feature_data['description'] ); ?></span>
									<?php endif; ?>
								</td>
								<?php foreach ( $roles as $role_slug => $role_name ) : ?>
									<?php
									$is_checked = false;
									if ( isset( $permissions[ $role_slug ][ $feature_key ] ) ) {
										$is_checked = (bool) $permissions[ $role_slug ][ $feature_key ];
									} else {
										$is_checked = isset( $feature_data['default'] ) ? $feature_data['default'] : false;
									}
									?>
									<td style="text-align: center;">
										<input 
											type="checkbox" 
											name="wphd_role_permissions[<?php echo esc_attr( $role_slug ); ?>][<?php echo esc_attr( $feature_key ); ?>]" 
											value="1" 
											<?php checked( $is_checked, true ); ?>
										>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			
			<p class="description" style="margin-top: 15px;">
				<?php esc_html_e( 'Note: These are default permissions for each role. Organization-specific permissions (if configured) will override these defaults.', 'wp-helpdesk' ); ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save access control permissions.
	 *
	 * @since 1.0.0
	 */
	public static function save() {
		if ( ! isset( $_POST['wphd_role_permissions'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$submitted_permissions = wp_unslash( $_POST['wphd_role_permissions'] );
		$features              = WPHD_Access_Control::get_controllable_features();
		$roles                 = WPHD_Access_Control::get_manageable_roles();
		
		$sanitized_permissions = array();
		
		// Process each role
		foreach ( $roles as $role_slug => $role_name ) {
			$sanitized_permissions[ $role_slug ] = array();
			
			// Process each feature
			foreach ( $features as $feature_key => $feature_data ) {
				// Checkbox is only present if checked, default to false if not present
				if ( isset( $submitted_permissions[ $role_slug ][ $feature_key ] ) ) {
					$sanitized_permissions[ $role_slug ][ $feature_key ] = true;
				} else {
					$sanitized_permissions[ $role_slug ][ $feature_key ] = false;
				}
			}
		}
		
		// Save the permissions
		WPHD_Access_Control::save_role_permissions( $sanitized_permissions );
		
		add_settings_error(
			'wphd_settings',
			'permissions_saved',
			__( 'Access control permissions saved successfully.', 'wp-helpdesk' ),
			'success'
		);
	}
}

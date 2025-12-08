<?php
/**
 * Access Control Class
 *
 * Handles permission checks for pages and features in the Help Desk plugin.
 *
 * @package     WP_HelpDesk
 * @subpackage  Includes
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHD_Access_Control
 *
 * Manages access control permissions for users based on roles and organizations.
 *
 * @since 1.0.0
 */
class WPHD_Access_Control {

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    WPHD_Access_Control
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @since  1.0.0
	 * @return WPHD_Access_Control
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
		// Hook to initialize default permissions
		add_action( 'init', array( $this, 'maybe_initialize_defaults' ) );
	}

	/**
	 * Initialize default role permissions if not set.
	 *
	 * @since 1.0.0
	 */
	public function maybe_initialize_defaults() {
		$permissions = get_option( 'wphd_role_permissions', array() );
		if ( empty( $permissions ) ) {
			$this->initialize_default_permissions();
		}
	}

	/**
	 * Initialize default permissions for all roles.
	 *
	 * @since 1.0.0
	 */
	private function initialize_default_permissions() {
		$features = self::get_controllable_features();
		$roles    = wp_roles()->get_names();
		
		$default_permissions = array();
		
		foreach ( $roles as $role_slug => $role_name ) {
			// Skip administrator as they always have full access
			if ( 'administrator' === $role_slug ) {
				continue;
			}
			
			$default_permissions[ $role_slug ] = array();
			
			foreach ( $features as $feature_key => $feature_data ) {
				// Use the default value from feature definition
				$default_permissions[ $role_slug ][ $feature_key ] = isset( $feature_data['default'] ) ? $feature_data['default'] : false;
			}
		}
		
		update_option( 'wphd_role_permissions', $default_permissions );
	}

	/**
	 * Check if current user can access a specific page/feature.
	 *
	 * @since  1.0.0
	 * @param  string   $feature_key The feature key (e.g., 'dashboard', 'ticket_create').
	 * @param  int|null $user_id     Optional user ID, defaults to current user.
	 * @return bool
	 */
	public static function can_access( $feature_key, $user_id = null ) {
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		// Validate user_id
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		// Admins always have full access
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Check organization-level permissions first (if user belongs to an org)
		if ( class_exists( 'WPHD_Organizations' ) ) {
			$user_org = WPHD_Organizations::get_user_organization( $user_id );
			if ( $user_org ) {
				$org_permissions = self::get_organization_permissions( $user_org->id );
				
				// Check if organization has custom permissions mode
				if ( isset( $org_permissions['access_control_mode'] ) && 'custom' === $org_permissions['access_control_mode'] ) {
					if ( isset( $org_permissions['access_control'][ $feature_key ] ) ) {
						return (bool) $org_permissions['access_control'][ $feature_key ];
					}
				}
			}
		}

		// Fall back to role-based permissions
		$user = get_userdata( $user_id );
		if ( $user && ! empty( $user->roles ) ) {
			$role             = $user->roles[0]; // Primary role
			$role_permissions = self::get_role_permissions( $role );
			if ( isset( $role_permissions[ $feature_key ] ) ) {
				return (bool) $role_permissions[ $feature_key ];
			}
		}

		// Fall back to feature default
		$features = self::get_controllable_features();
		return isset( $features[ $feature_key ]['default'] ) ? $features[ $feature_key ]['default'] : false;
	}

	/**
	 * Get all controllable features.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_controllable_features() {
		$features = array(
			'dashboard'                  => array(
				'label'       => __( 'Dashboard', 'wp-helpdesk' ),
				'description' => __( 'View the Help Desk dashboard with statistics', 'wp-helpdesk' ),
				'default'     => true,
			),
			'tickets_list'               => array(
				'label'       => __( 'View Tickets List', 'wp-helpdesk' ),
				'description' => __( 'Access the All Tickets page', 'wp-helpdesk' ),
				'default'     => true,
			),
			'ticket_view'                => array(
				'label'       => __( 'View Ticket Details', 'wp-helpdesk' ),
				'description' => __( 'View individual ticket details', 'wp-helpdesk' ),
				'default'     => true,
			),
			'ticket_create'              => array(
				'label'       => __( 'Create Tickets', 'wp-helpdesk' ),
				'description' => __( 'Access the Add New Ticket page and create tickets', 'wp-helpdesk' ),
				'default'     => true,
			),
			'ticket_edit'                => array(
				'label'       => __( 'Edit Tickets', 'wp-helpdesk' ),
				'description' => __( 'Edit existing tickets (subject to ownership rules)', 'wp-helpdesk' ),
				'default'     => false,
			),
			'ticket_delete'              => array(
				'label'       => __( 'Delete Tickets', 'wp-helpdesk' ),
				'description' => __( 'Delete tickets (subject to ownership rules)', 'wp-helpdesk' ),
				'default'     => false,
			),
			'ticket_comment'             => array(
				'label'       => __( 'Add Comments', 'wp-helpdesk' ),
				'description' => __( 'Add comments to tickets', 'wp-helpdesk' ),
				'default'     => true,
			),
			'ticket_internal_comments'   => array(
				'label'       => __( 'View Internal Comments', 'wp-helpdesk' ),
				'description' => __( 'View internal/private comments on tickets', 'wp-helpdesk' ),
				'default'     => false,
			),
			'reports'                    => array(
				'label'       => __( 'View Reports', 'wp-helpdesk' ),
				'description' => __( 'Access the Reports page', 'wp-helpdesk' ),
				'default'     => false,
			),
			'categories_view'            => array(
				'label'       => __( 'View Categories', 'wp-helpdesk' ),
				'description' => __( 'View ticket categories', 'wp-helpdesk' ),
				'default'     => true,
			),
			'statuses_view'              => array(
				'label'       => __( 'View Statuses', 'wp-helpdesk' ),
				'description' => __( 'View ticket statuses', 'wp-helpdesk' ),
				'default'     => true,
			),
			'priorities_view'            => array(
				'label'       => __( 'View Priorities', 'wp-helpdesk' ),
				'description' => __( 'View ticket priorities', 'wp-helpdesk' ),
				'default'     => true,
			),
			'action_items_view'          => array(
				'label'       => __( 'View Action Items', 'wp-helpdesk' ),
				'description' => __( 'View action items on tickets', 'wp-helpdesk' ),
				'default'     => true,
			),
			'action_items_manage'        => array(
				'label'       => __( 'Manage Action Items', 'wp-helpdesk' ),
				'description' => __( 'Create, edit, delete, and complete action items', 'wp-helpdesk' ),
				'default'     => false,
			),
			'shifts_view'                => array(
				'label'       => __( 'View Shifts', 'wp-helpdesk' ),
				'description' => __( 'View organization shifts', 'wp-helpdesk' ),
				'default'     => true,
			),
			'shifts_manage'              => array(
				'label'       => __( 'Manage Shifts', 'wp-helpdesk' ),
				'description' => __( 'Create, edit, and delete organization shifts', 'wp-helpdesk' ),
				'default'     => false,
			),
		);

		/**
		 * Filter the controllable features.
		 *
		 * Allows plugins/themes to add more features to the access control system.
		 *
		 * @since 1.0.0
		 *
		 * @param array $features Array of controllable features.
		 */
		return apply_filters( 'wphd_controllable_features', $features );
	}

	/**
	 * Get role permissions from options.
	 *
	 * @since  1.0.0
	 * @param  string $role Role slug.
	 * @return array
	 */
	public static function get_role_permissions( $role ) {
		$all_permissions = get_option( 'wphd_role_permissions', array() );
		return isset( $all_permissions[ $role ] ) ? $all_permissions[ $role ] : array();
	}

	/**
	 * Get organization permissions.
	 *
	 * @since  1.0.0
	 * @param  int $org_id Organization ID.
	 * @return array
	 */
	public static function get_organization_permissions( $org_id ) {
		if ( ! class_exists( 'WPHD_Organizations' ) ) {
			return array();
		}

		$org = WPHD_Organizations::get( $org_id );
		if ( $org && ! empty( $org->settings ) ) {
			$settings = maybe_unserialize( $org->settings );
			// Validate that the result is an array
			if ( is_array( $settings ) ) {
				return $settings;
			}
		}
		return array();
	}

	/**
	 * Save role permissions.
	 *
	 * @since  1.0.0
	 * @param  array $permissions Permissions array keyed by role.
	 * @return bool
	 */
	public static function save_role_permissions( $permissions ) {
		return update_option( 'wphd_role_permissions', $permissions );
	}

	/**
	 * Get all roles except administrator.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_manageable_roles() {
		$all_roles = wp_roles()->get_names();
		unset( $all_roles['administrator'] );
		return $all_roles;
	}
}

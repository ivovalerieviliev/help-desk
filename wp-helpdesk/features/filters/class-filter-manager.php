<?php
/**
 * Filter Manager Class
 *
 * Handles CRUD operations for saved filters
 *
 * @package     WP_HelpDesk
 * @subpackage  Features/Filters
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHD_Filter_Manager
 *
 * Manages saved filters and their operations.
 *
 * @since 1.0.0
 */
class WPHD_Filter_Manager {

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    WPHD_Filter_Manager
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @since  1.0.0
	 * @return WPHD_Filter_Manager
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
		add_action( 'admin_init', array( $this, 'maybe_create_presets' ) );
	}

	/**
	 * Create a new filter.
	 *
	 * @since  1.0.0
	 * @param  array $data Filter data.
	 * @return int|WP_Error Filter ID on success, WP_Error on failure.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		// Validate required fields
		if ( empty( $data['name'] ) || empty( $data['filter_config'] ) ) {
			return new WP_Error( 'missing_data', __( 'Filter name and configuration are required.', 'wp-helpdesk' ) );
		}

		// Validate filter config
		$filter_config = is_string( $data['filter_config'] ) ? json_decode( $data['filter_config'], true ) : $data['filter_config'];
		$validation = WPHD_Advanced_Filter_Builder::validate_config( $filter_config );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check permissions
		$filter_type = ! empty( $data['filter_type'] ) ? $data['filter_type'] : 'user';
		if ( ! self::can_create_filter( $filter_type ) ) {
			return new WP_Error( 'permission_denied', __( 'You do not have permission to create this type of filter.', 'wp-helpdesk' ) );
		}

		$defaults = array(
			'description'     => '',
			'filter_type'     => 'user',
			'user_id'         => get_current_user_id(),
			'organization_id' => null,
			'sort_field'      => 'date',
			'sort_order'      => 'DESC',
			'is_default'      => 0,
			'display_order'   => 0,
			'created_by'      => get_current_user_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		// If organization filter, get user's org
		if ( 'organization' === $data['filter_type'] ) {
			$user_org = WPHD_Organizations::get_user_organization( get_current_user_id() );
			if ( $user_org ) {
				$data['organization_id'] = $user_org->id;
			}
		}

		// Encode config if it's an array
		if ( is_array( $data['filter_config'] ) ) {
			$data['filter_config'] = wp_json_encode( $data['filter_config'] );
		}

		// If this is being set as default, unset other defaults
		if ( $data['is_default'] ) {
			if ( 'user' === $data['filter_type'] ) {
				self::unset_default_user_filters( $data['user_id'] );
			} elseif ( 'organization' === $data['filter_type'] && $data['organization_id'] ) {
				self::unset_default_org_filters( $data['organization_id'] );
			}
		}

		$result = $wpdb->insert(
			$table,
			array(
				'name'            => sanitize_text_field( $data['name'] ),
				'description'     => sanitize_textarea_field( $data['description'] ),
				'filter_type'     => $data['filter_type'],
				'user_id'         => $data['user_id'],
				'organization_id' => $data['organization_id'],
				'filter_config'   => $data['filter_config'],
				'sort_field'      => sanitize_text_field( $data['sort_field'] ),
				'sort_order'      => $data['sort_order'],
				'is_default'      => $data['is_default'],
				'display_order'   => $data['display_order'],
				'created_by'      => $data['created_by'],
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create filter.', 'wp-helpdesk' ) );
		}

		do_action( 'wphd_filter_created', $wpdb->insert_id, $data );

		return $wpdb->insert_id;
	}

	/**
	 * Update an existing filter.
	 *
	 * @since  1.0.0
	 * @param  int   $filter_id Filter ID.
	 * @param  array $data      Update data.
	 * @return bool|WP_Error    True on success, WP_Error on failure.
	 */
	public static function update( $filter_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		// Check if filter exists
		$filter = self::get( $filter_id );
		if ( ! $filter ) {
			return new WP_Error( 'not_found', __( 'Filter not found.', 'wp-helpdesk' ) );
		}

		// Check permissions
		if ( ! self::can_edit_filter( $filter ) ) {
			return new WP_Error( 'permission_denied', __( 'You do not have permission to edit this filter.', 'wp-helpdesk' ) );
		}

		$update_data = array();
		$update_format = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
			$update_format[] = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
			$update_format[] = '%s';
		}

		if ( isset( $data['filter_config'] ) ) {
			$filter_config = is_string( $data['filter_config'] ) ? json_decode( $data['filter_config'], true ) : $data['filter_config'];
			$validation = WPHD_Advanced_Filter_Builder::validate_config( $filter_config );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			if ( is_array( $data['filter_config'] ) ) {
				$update_data['filter_config'] = wp_json_encode( $data['filter_config'] );
			} else {
				$update_data['filter_config'] = $data['filter_config'];
			}
			$update_format[] = '%s';
		}

		if ( isset( $data['sort_field'] ) ) {
			$update_data['sort_field'] = sanitize_text_field( $data['sort_field'] );
			$update_format[] = '%s';
		}

		if ( isset( $data['sort_order'] ) ) {
			$update_data['sort_order'] = $data['sort_order'];
			$update_format[] = '%s';
		}

		if ( isset( $data['is_default'] ) ) {
			if ( $data['is_default'] ) {
				if ( 'user' === $filter->filter_type ) {
					self::unset_default_user_filters( $filter->user_id );
				} elseif ( 'organization' === $filter->filter_type && $filter->organization_id ) {
					self::unset_default_org_filters( $filter->organization_id );
				}
			}
			$update_data['is_default'] = $data['is_default'];
			$update_format[] = '%d';
		}

		if ( isset( $data['display_order'] ) ) {
			$update_data['display_order'] = $data['display_order'];
			$update_format[] = '%d';
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $filter_id ),
			$update_format,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to update filter.', 'wp-helpdesk' ) );
		}

		do_action( 'wphd_filter_updated', $filter_id, $data );

		return true;
	}

	/**
	 * Delete a filter.
	 *
	 * @since  1.0.0
	 * @param  int $filter_id Filter ID.
	 * @return bool|WP_Error  True on success, WP_Error on failure.
	 */
	public static function delete( $filter_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		$filter = self::get( $filter_id );
		if ( ! $filter ) {
			return new WP_Error( 'not_found', __( 'Filter not found.', 'wp-helpdesk' ) );
		}

		if ( ! self::can_delete_filter( $filter ) ) {
			return new WP_Error( 'permission_denied', __( 'You do not have permission to delete this filter.', 'wp-helpdesk' ) );
		}

		$result = $wpdb->delete( $table, array( 'id' => $filter_id ), array( '%d' ) );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to delete filter.', 'wp-helpdesk' ) );
		}

		do_action( 'wphd_filter_deleted', $filter_id );

		return true;
	}

	/**
	 * Get a filter by ID.
	 *
	 * @since  1.0.0
	 * @param  int $filter_id Filter ID.
	 * @return object|null    Filter object or null.
	 */
	public static function get( $filter_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE id = %d",
			$filter_id
		) );
	}

	/**
	 * Get user's filters.
	 *
	 * @since  1.0.0
	 * @param  int|null $user_id User ID (null for current user).
	 * @return array Filters.
	 */
	public static function get_user_filters( $user_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE filter_type = 'user' AND user_id = %d ORDER BY display_order ASC, name ASC",
			$user_id
		) );
	}

	/**
	 * Get organization filters.
	 *
	 * @since  1.0.0
	 * @param  int $org_id Organization ID.
	 * @return array Filters.
	 */
	public static function get_organization_filters( $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE filter_type = 'organization' AND organization_id = %d ORDER BY display_order ASC, name ASC",
			$org_id
		) );
	}

	/**
	 * Get default filter for user.
	 *
	 * @since  1.0.0
	 * @param  int|null $user_id User ID (null for current user).
	 * @return object|null Default filter or null.
	 */
	public static function get_default_filter( $user_id = null ) {
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		// Check for user default
		$user_filters = self::get_user_filters( $user_id );
		foreach ( $user_filters as $filter ) {
			if ( $filter->is_default ) {
				return $filter;
			}
		}

		// Check for organization default
		if ( class_exists( 'WPHD_Organizations' ) ) {
			$user_org = WPHD_Organizations::get_user_organization( $user_id );
			if ( $user_org ) {
				$org_filters = self::get_organization_filters( $user_org->id );
				foreach ( $org_filters as $filter ) {
					if ( $filter->is_default ) {
						return $filter;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Create preset filters if they don't exist.
	 *
	 * @since 1.0.0
	 */
	public function maybe_create_presets() {
		if ( get_option( 'wphd_filter_presets_created', false ) ) {
			return;
		}

		// Only create once
		update_option( 'wphd_filter_presets_created', true );
	}

	/**
	 * Unset default filters for user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return void
	 */
	private static function unset_default_user_filters( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		$wpdb->update(
			$table,
			array( 'is_default' => 0 ),
			array(
				'filter_type' => 'user',
				'user_id'     => $user_id,
			),
			array( '%d' ),
			array( '%s', '%d' )
		);
	}

	/**
	 * Unset default filters for organization.
	 *
	 * @since  1.0.0
	 * @param  int $org_id Organization ID.
	 * @return void
	 */
	private static function unset_default_org_filters( $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		$wpdb->update(
			$table,
			array( 'is_default' => 0 ),
			array(
				'filter_type'     => 'organization',
				'organization_id' => $org_id,
			),
			array( '%d' ),
			array( '%s', '%d' )
		);
	}

	/**
	 * Check if user can create a filter type.
	 *
	 * @since  1.0.0
	 * @param  string $filter_type Filter type.
	 * @return bool
	 */
	private static function can_create_filter( $filter_type ) {
		if ( 'user' === $filter_type ) {
			return WPHD_Access_Control::can_access( 'queue_filters_user_create' );
		}

		if ( 'organization' === $filter_type ) {
			return WPHD_Access_Control::can_access( 'queue_filters_org_create' );
		}

		return false;
	}

	/**
	 * Check if user can edit a filter.
	 *
	 * @since  1.0.0
	 * @param  object $filter Filter object.
	 * @return bool
	 */
	private static function can_edit_filter( $filter ) {
		// Admins can edit any filter
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( 'user' === $filter->filter_type ) {
			return $filter->user_id == get_current_user_id() && WPHD_Access_Control::can_access( 'queue_filters_user_edit' );
		}

		if ( 'organization' === $filter->filter_type ) {
			return WPHD_Access_Control::can_access( 'queue_filters_org_edit' );
		}

		return false;
	}

	/**
	 * Check if user can delete a filter.
	 *
	 * @since  1.0.0
	 * @param  object $filter Filter object.
	 * @return bool
	 */
	private static function can_delete_filter( $filter ) {
		// Admins can delete any filter
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( 'user' === $filter->filter_type ) {
			return $filter->user_id == get_current_user_id() && WPHD_Access_Control::can_access( 'queue_filters_user_delete' );
		}

		if ( 'organization' === $filter->filter_type ) {
			return WPHD_Access_Control::can_access( 'queue_filters_org_delete' );
		}

		return false;
	}
}

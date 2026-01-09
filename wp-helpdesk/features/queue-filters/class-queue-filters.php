<?php
/**
 * Queue Filters Class
 *
 * Handles CRUD operations for queue filters
 *
 * @package     WP_HelpDesk
 * @subpackage  Features/Queue_Filters
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHD_Queue_Filters
 *
 * Manages queue filters and their operations.
 *
 * @since 1.0.0
 */
class WPHD_Queue_Filters {

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    WPHD_Queue_Filters
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @since  1.0.0
	 * @return WPHD_Queue_Filters
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
		add_action( 'admin_init', array( $this, 'maybe_create_default_filters' ) );
	}

	/**
	 * Create a new queue filter.
	 *
	 * @since  1.0.0
	 * @param  array $data Filter data.
	 * @return int|false Filter ID on success, false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		// Validate required fields
		if ( empty( $data['name'] ) || empty( $data['filter_config'] ) ) {
			return false;
		}

		// Validate filter config
		$filter_config = is_string( $data['filter_config'] ) ? $data['filter_config'] : wp_json_encode( $data['filter_config'] );
		if ( ! self::validate_filter_config( $filter_config ) ) {
			return false;
		}

		$defaults = array(
			'description'       => '',
			'filter_type'       => 'user',
			'user_id'           => get_current_user_id(),
			'organization_id'   => null,
			'sort_field'        => 'date',
			'sort_order'        => 'DESC',
			'is_default'        => 0,
			'display_order'     => 0,
			'created_by'        => get_current_user_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		// If this is being set as default, unset other defaults
		if ( $data['is_default'] ) {
			if ( 'user' === $data['filter_type'] ) {
				self::unset_default_user_filters( $data['user_id'] );
			} elseif ( 'organization' === $data['filter_type'] && $data['organization_id'] ) {
				self::unset_default_org_filters( $data['organization_id'] );
			}
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'name'              => sanitize_text_field( $data['name'] ),
				'description'       => sanitize_textarea_field( $data['description'] ),
				'filter_type'       => sanitize_text_field( $data['filter_type'] ),
				'user_id'           => absint( $data['user_id'] ),
				'organization_id'   => ! empty( $data['organization_id'] ) ? absint( $data['organization_id'] ) : null,
				'filter_config'     => $filter_config,
				'sort_field'        => sanitize_text_field( $data['sort_field'] ),
				'sort_order'        => sanitize_text_field( $data['sort_order'] ),
				'is_default'        => absint( $data['is_default'] ),
				'display_order'     => absint( $data['display_order'] ),
				'created_by'        => absint( $data['created_by'] ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update an existing queue filter.
	 *
	 * @since  1.0.0
	 * @param  int   $filter_id Filter ID.
	 * @param  array $data      Filter data.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $filter_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		$filter_id = absint( $filter_id );
		if ( ! $filter_id ) {
			return false;
		}

		// Check if filter exists
		$existing = self::get( $filter_id );
		if ( ! $existing ) {
			return false;
		}

		// Build update data
		$update_data  = array();
		$update_types = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name']    = sanitize_text_field( $data['name'] );
			$update_types[]         = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
			$update_types[]             = '%s';
		}

		if ( isset( $data['filter_config'] ) ) {
			$filter_config = is_string( $data['filter_config'] ) ? $data['filter_config'] : wp_json_encode( $data['filter_config'] );
			if ( ! self::validate_filter_config( $filter_config ) ) {
				return false;
			}
			$update_data['filter_config'] = $filter_config;
			$update_types[]               = '%s';
		}

		if ( isset( $data['sort_field'] ) ) {
			$update_data['sort_field'] = sanitize_text_field( $data['sort_field'] );
			$update_types[]            = '%s';
		}

		if ( isset( $data['sort_order'] ) ) {
			$update_data['sort_order'] = sanitize_text_field( $data['sort_order'] );
			$update_types[]            = '%s';
		}

		if ( isset( $data['is_default'] ) ) {
			if ( $data['is_default'] ) {
				if ( 'user' === $existing->filter_type ) {
					self::unset_default_user_filters( $existing->user_id );
				} elseif ( 'organization' === $existing->filter_type && $existing->organization_id ) {
					self::unset_default_org_filters( $existing->organization_id );
				}
			}
			$update_data['is_default'] = absint( $data['is_default'] );
			$update_types[]            = '%d';
		}

		if ( isset( $data['display_order'] ) ) {
			$update_data['display_order'] = absint( $data['display_order'] );
			$update_types[]               = '%d';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$updated = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $filter_id ),
			$update_types,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Delete a queue filter.
	 *
	 * @since  1.0.0
	 * @param  int $filter_id Filter ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $filter_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		$filter_id = absint( $filter_id );
		if ( ! $filter_id ) {
			return false;
		}

		$deleted = $wpdb->delete(
			$table,
			array( 'id' => $filter_id ),
			array( '%d' )
		);

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Get a single queue filter by ID.
	 *
	 * @since  1.0.0
	 * @param  int $filter_id Filter ID.
	 * @return object|null Filter object or null if not found.
	 */
	public static function get( $filter_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		$filter_id = absint( $filter_id );
		if ( ! $filter_id ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $filter_id )
		);
	}

	/**
	 * Get user's personal filters.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID (default: current user).
	 * @return array Array of filter objects.
	 */
	public static function get_user_filters( $user_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE filter_type = 'user' AND user_id = %d ORDER BY display_order ASC, name ASC",
				$user_id
			)
		);
	}

	/**
	 * Get organization filters.
	 *
	 * @since  1.0.0
	 * @param  int $org_id Organization ID.
	 * @return array Array of filter objects.
	 */
	public static function get_organization_filters( $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_queue_filters';

		$org_id = absint( $org_id );
		if ( ! $org_id ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE filter_type = 'organization' AND organization_id = %d ORDER BY display_order ASC, name ASC",
				$org_id
			)
		);
	}

	/**
	 * Get all filters accessible to a user (both personal and org).
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID (default: current user).
	 * @return array Array with 'user' and 'organization' keys.
	 */
	public static function get_all_filters( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$filters = array(
			'user'         => self::get_user_filters( $user_id ),
			'organization' => array(),
		);

		// Get org filters if user has permission
		if ( WPHD_Access_Control::can_access( 'queue_filters_org_view', $user_id ) && class_exists( 'WPHD_Organizations' ) ) {
			$user_org = WPHD_Organizations::get_user_organization( $user_id );
			if ( $user_org ) {
				$filters['organization'] = self::get_organization_filters( $user_org->id );
			}
		}

		return $filters;
	}

	/**
	 * Check if user can create personal filters.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID (default: current user).
	 * @return bool
	 */
	public static function can_create_user_filter( $user_id = 0 ) {
		return WPHD_Access_Control::can_access( 'queue_filters_user_create', $user_id );
	}

	/**
	 * Check if user can create organization filters.
	 *
	 * @since  1.0.0
	 * @param  int $org_id  Organization ID.
	 * @param  int $user_id User ID (default: current user).
	 * @return bool
	 */
	public static function can_create_org_filter( $org_id, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Check permission
		if ( ! WPHD_Access_Control::can_access( 'queue_filters_org_create', $user_id ) ) {
			return false;
		}

		// Check if user belongs to the organization
		if ( class_exists( 'WPHD_Organizations' ) ) {
			$user_org = WPHD_Organizations::get_user_organization( $user_id );
			if ( ! $user_org || $user_org->id !== absint( $org_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if user can edit a filter.
	 *
	 * @since  1.0.0
	 * @param  int $filter_id Filter ID.
	 * @param  int $user_id   User ID (default: current user).
	 * @return bool
	 */
	public static function can_edit_filter( $filter_id, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$filter = self::get( $filter_id );
		if ( ! $filter ) {
			return false;
		}

		// User filters
		if ( 'user' === $filter->filter_type ) {
			if ( absint( $filter->user_id ) === absint( $user_id ) ) {
				return WPHD_Access_Control::can_access( 'queue_filters_user_edit', $user_id );
			}
			return false;
		}

		// Organization filters
		if ( 'organization' === $filter->filter_type ) {
			if ( ! WPHD_Access_Control::can_access( 'queue_filters_org_edit', $user_id ) ) {
				return false;
			}

			// Check if user belongs to the organization
			if ( class_exists( 'WPHD_Organizations' ) ) {
				$user_org = WPHD_Organizations::get_user_organization( $user_id );
				if ( ! $user_org || absint( $user_org->id ) !== absint( $filter->organization_id ) ) {
					return false;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Check if user can delete a filter.
	 *
	 * @since  1.0.0
	 * @param  int $filter_id Filter ID.
	 * @param  int $user_id   User ID (default: current user).
	 * @return bool
	 */
	public static function can_delete_filter( $filter_id, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$filter = self::get( $filter_id );
		if ( ! $filter ) {
			return false;
		}

		// User filters
		if ( 'user' === $filter->filter_type ) {
			if ( absint( $filter->user_id ) === absint( $user_id ) ) {
				return WPHD_Access_Control::can_access( 'queue_filters_user_delete', $user_id );
			}
			return false;
		}

		// Organization filters
		if ( 'organization' === $filter->filter_type ) {
			if ( ! WPHD_Access_Control::can_access( 'queue_filters_org_delete', $user_id ) ) {
				return false;
			}

			// Check if user belongs to the organization
			if ( class_exists( 'WPHD_Organizations' ) ) {
				$user_org = WPHD_Organizations::get_user_organization( $user_id );
				if ( ! $user_org || absint( $user_org->id ) !== absint( $filter->organization_id ) ) {
					return false;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Create default filters for a user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return void
	 */
	public static function create_default_filters( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}

		// Get statuses and priorities.
		$statuses   = get_option( 'wphd_statuses', array() );
		$priorities = get_option( 'wphd_priorities', array() );

		// Find "open" status.
		$open_statuses = array();
		foreach ( $statuses as $status ) {
			// Check for exact slug match or if the name is exactly 'Open' (case-insensitive).
			if ( 'open' === $status['slug'] || 
			     ( isset( $status['name'] ) && 'open' === strtolower( trim( $status['name'] ) ) ) ) {
				$open_statuses[] = $status['slug'];
			}
		}

		// Find "in-progress" status.
		$in_progress_statuses = array();
		foreach ( $statuses as $status ) {
			// Check for exact slug match or specific name variations.
			if ( in_array( $status['slug'], array( 'in-progress', 'in_progress', 'inprogress' ), true ) ||
			     ( isset( $status['name'] ) && preg_match( '/^in[\s\-_]?progress$/i', trim( $status['name'] ) ) ) ) {
				$in_progress_statuses[] = $status['slug'];
			}
		}

		// Get closed statuses.
		$closed_statuses = array();
		foreach ( $statuses as $status ) {
			if ( isset( $status['is_closed'] ) && $status['is_closed'] ) {
				$closed_statuses[] = $status['slug'];
			}
		}

		// Get high priority slugs.
		$high_priorities = array();
		foreach ( $priorities as $priority ) {
			if ( isset( $priority['level'] ) && in_array( strtolower( $priority['level'] ), array( 'high', 'critical', 'urgent' ), true ) ) {
				$high_priorities[] = $priority['slug'];
			} elseif ( isset( $priority['slug'] ) && in_array( strtolower( $priority['slug'] ), array( 'high', 'critical', 'urgent' ), true ) ) {
				$high_priorities[] = $priority['slug'];
			}
		}

		// Default filters to create.
		$default_filters = array();
		$display_order   = 0;

		// Add Open Tickets filter if we found open statuses.
		if ( ! empty( $open_statuses ) ) {
			$default_filters[] = array(
				'name'          => __( 'Open Tickets', 'wp-helpdesk' ),
				'description'   => __( 'All tickets with open status', 'wp-helpdesk' ),
				'filter_config' => wp_json_encode( array( 'status' => $open_statuses ) ),
				'display_order' => ++$display_order,
			);
		}

		// Add In Progress filter if we found in-progress statuses.
		if ( ! empty( $in_progress_statuses ) ) {
			$default_filters[] = array(
				'name'          => __( 'In Progress', 'wp-helpdesk' ),
				'description'   => __( 'All tickets currently in progress', 'wp-helpdesk' ),
				'filter_config' => wp_json_encode( array( 'status' => $in_progress_statuses ) ),
				'display_order' => ++$display_order,
			);
		}

		// Always add My Tickets filter.
		$default_filters[] = array(
			'name'          => __( 'My Tickets', 'wp-helpdesk' ),
			'description'   => __( 'Tickets assigned to me', 'wp-helpdesk' ),
			'filter_config' => wp_json_encode( array( 'assignee_type' => 'me' ) ),
			'display_order' => ++$display_order,
			'is_default'    => 1,
		);

		// Always add Unassigned filter.
		$default_filters[] = array(
			'name'          => __( 'Unassigned', 'wp-helpdesk' ),
			'description'   => __( 'Tickets not yet assigned to anyone', 'wp-helpdesk' ),
			'filter_config' => wp_json_encode( array( 'assignee_type' => 'unassigned' ) ),
			'display_order' => ++$display_order,
		);

		// Always add Created Today filter.
		$default_filters[] = array(
			'name'          => __( 'Created Today', 'wp-helpdesk' ),
			'description'   => __( 'Tickets created today', 'wp-helpdesk' ),
			'filter_config' => wp_json_encode( array( 'date_created' => array( 'operator' => 'today' ) ) ),
			'display_order' => ++$display_order,
		);

		// Add closed tickets filter if we have closed statuses.
		if ( ! empty( $closed_statuses ) ) {
			$default_filters[] = array(
				'name'          => __( 'Closed', 'wp-helpdesk' ),
				'description'   => __( 'All closed tickets', 'wp-helpdesk' ),
				'filter_config' => wp_json_encode( array( 'status' => $closed_statuses ) ),
				'display_order' => ++$display_order,
			);
		}

		// Add high priority filter if we have high priorities.
		if ( ! empty( $high_priorities ) ) {
			$default_filters[] = array(
				'name'          => __( 'High Priority', 'wp-helpdesk' ),
				'description'   => __( 'High and critical priority tickets', 'wp-helpdesk' ),
				'filter_config' => wp_json_encode( array( 'priority' => $high_priorities ) ),
				'display_order' => ++$display_order,
			);
		}

		// Add SLA breached filter.
		$default_filters[] = array(
			'name'          => __( 'SLA Breached', 'wp-helpdesk' ),
			'description'   => __( 'Tickets with breached SLA', 'wp-helpdesk' ),
			'filter_config' => wp_json_encode( array(
				'sla_first_response' => 'breached',
				'sla_resolution'     => 'breached',
			) ),
			'display_order' => ++$display_order,
		);

		// Create each default filter.
		foreach ( $default_filters as $filter ) {
			$filter['filter_type'] = 'user';
			$filter['user_id']     = $user_id;
			$filter['created_by']  = $user_id;

			// Don't set is_default for all except "My Tickets"
			if ( ! isset( $filter['is_default'] ) ) {
				$filter['is_default'] = 0;
			}

			self::create( $filter );
		}
	}

	/**
	 * Ensure user has default filters (called on admin_init).
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function maybe_create_default_filters() {
		// Only run on help desk pages
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( empty( $request_uri ) || false === strpos( $request_uri, 'wp-helpdesk' ) ) {
			return;
		}

		// Only for logged in users
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		// Check if user already has filters
		$existing = self::get_user_filters( $user_id );
		if ( ! empty( $existing ) ) {
			return;
		}

		// Create default filters
		self::create_default_filters( $user_id );
	}

	/**
	 * Validate filter configuration JSON.
	 *
	 * @since  1.0.0
	 * @param  string $config JSON configuration string.
	 * @return bool True if valid, false otherwise.
	 */
	private static function validate_filter_config( $config ) {
		// Check if it's valid JSON
		$decoded = json_decode( $config, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return false;
		}

		// Additional validation can be added here
		return true;
	}

	/**
	 * Unset default flag for user filters.
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
				'user_id'     => absint( $user_id ),
			),
			array( '%d' ),
			array( '%s', '%d' )
		);
	}

	/**
	 * Unset default flag for organization filters.
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
				'organization_id' => absint( $org_id ),
			),
			array( '%d' ),
			array( '%s', '%d' )
		);
	}
}

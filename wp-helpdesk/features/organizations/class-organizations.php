<?php
/**
 * Organizations Class
 *
 * Handles CRUD operations for organizations
 *
 * @package     WP_HelpDesk
 * @subpackage  Features/Organizations
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPHD_Organizations
 *
 * Manages organizations and their settings.
 *
 * @since 1.0.0
 */
class WPHD_Organizations {

    /**
     * Instance of this class.
     *
     * @since  1.0.0
     * @access private
     * @var    WPHD_Organizations
     */
    private static $instance = null;

    /**
     * Get the singleton instance of this class.
     *
     * @since  1.0.0
     * @return WPHD_Organizations
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
        add_action( 'user_register', array( $this, 'auto_assign_user_to_organization' ) );
    }

    /**
     * Ensure the organizations table exists before database operations.
     * 
     * This is a safety check to prevent "table doesn't exist" errors.
     * Checks table existence first for performance before calling create_tables().
     *
     * @since 1.0.0
     * @return void
     */
    private static function ensure_tables_exist() {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organizations';
        
        if ( ! WPHD_Database::check_table_exists( $table ) ) {
            if ( class_exists( 'WPHD_Activator' ) ) {
                WPHD_Activator::create_tables();
            }
        }
    }

    /**
     * Create a new organization.
     *
     * @since  1.0.0
     * @param  array $data Organization data.
     * @return int|false Organization ID on success, false on failure.
     */
    public static function create( $data ) {
        global $wpdb;
        
        // Ensure tables exist before attempting to use them
        self::ensure_tables_exist();
        
        $table = $wpdb->prefix . 'wphd_organizations';

        $slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $data['name'] );

        // Check if slug already exists
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ) );
        if ( $existing ) {
            return false;
        }

        $defaults = array(
            'view_own_tickets_only'       => true,
            'view_organization_tickets'   => false,
            'view_all_tickets'            => false,
            'view_specific_orgs'          => array(),
            'can_create_tickets'          => true,
            'can_edit_own_tickets'        => true,
            'can_edit_org_tickets'        => false,
            'can_delete_own_tickets'      => false,
            'can_delete_org_tickets'      => false,
            'can_comment_on_tickets'      => true,
            'can_view_internal_comments'  => false,
        );

        $settings = isset( $data['settings'] ) ? array_merge( $defaults, $data['settings'] ) : $defaults;

        $insert_data = array(
            'name'            => sanitize_text_field( $data['name'] ),
            'slug'            => $slug,
            'description'     => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
            'logo_id'         => isset( $data['logo_id'] ) ? intval( $data['logo_id'] ) : null,
            'allowed_domains' => isset( $data['allowed_domains'] ) ? sanitize_text_field( $data['allowed_domains'] ) : '',
            'status'          => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
            'settings'        => maybe_serialize( $settings ),
            'created_by'      => get_current_user_id(),
        );

        $result = $wpdb->insert( $table, $insert_data );

        if ( $result ) {
            $org_id = $wpdb->insert_id;
            self::log_change( $org_id, 'created', '', '', $data['name'] );
            do_action( 'wphd_organization_created', $org_id, $data );
            return $org_id;
        }

        return false;
    }

    /**
     * Update an organization.
     *
     * @since  1.0.0
     * @param  int   $org_id Organization ID.
     * @param  array $data   Organization data.
     * @return bool True on success, false on failure.
     */
    public static function update( $org_id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organizations';

        $org = self::get( $org_id );
        if ( ! $org ) {
            return false;
        }

        $update_data = array();
        $old_settings = maybe_unserialize( $org->settings );

        // Track changes for logging
        if ( isset( $data['name'] ) && $data['name'] !== $org->name ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
            self::log_change( $org_id, 'updated', 'name', $org->name, $data['name'] );
        }

        if ( isset( $data['description'] ) && $data['description'] !== $org->description ) {
            $update_data['description'] = wp_kses_post( $data['description'] );
            self::log_change( $org_id, 'updated', 'description', $org->description, $data['description'] );
        }

        if ( isset( $data['logo_id'] ) && $data['logo_id'] != $org->logo_id ) {
            $update_data['logo_id'] = intval( $data['logo_id'] );
            self::log_change( $org_id, 'updated', 'logo_id', $org->logo_id, $data['logo_id'] );
        }

        if ( isset( $data['allowed_domains'] ) && $data['allowed_domains'] !== $org->allowed_domains ) {
            $update_data['allowed_domains'] = sanitize_text_field( $data['allowed_domains'] );
            self::log_change( $org_id, 'updated', 'allowed_domains', $org->allowed_domains, $data['allowed_domains'] );
        }

        if ( isset( $data['status'] ) && $data['status'] !== $org->status ) {
            $update_data['status'] = sanitize_text_field( $data['status'] );
            self::log_change( $org_id, 'updated', 'status', $org->status, $data['status'] );
        }

        if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
            $new_settings = array_merge( $old_settings, $data['settings'] );
            $update_data['settings'] = maybe_serialize( $new_settings );
            
            // Log permission changes
            foreach ( $data['settings'] as $key => $value ) {
                if ( isset( $old_settings[ $key ] ) && $old_settings[ $key ] !== $value ) {
                    self::log_change( $org_id, 'permission_changed', $key, $old_settings[ $key ], $value );
                }
            }
        }

        if ( ! empty( $update_data ) ) {
            $result = $wpdb->update( $table, $update_data, array( 'id' => $org_id ) );
            if ( $result !== false ) {
                do_action( 'wphd_organization_updated', $org_id, $data );
                return true;
            }
        }

        return false;
    }

    /**
     * Delete an organization.
     *
     * @since  1.0.0
     * @param  int $org_id Organization ID.
     * @return bool True on success, false on failure.
     */
    public static function delete( $org_id ) {
        global $wpdb;
        $table         = $wpdb->prefix . 'wphd_organizations';
        $members_table = $wpdb->prefix . 'wphd_organization_members';
        $logs_table    = $wpdb->prefix . 'wphd_organization_logs';

        $org = self::get( $org_id );
        if ( ! $org ) {
            return false;
        }

        // Delete all members
        $wpdb->delete( $members_table, array( 'organization_id' => $org_id ) );

        // Log deletion
        self::log_change( $org_id, 'deleted', '', '', $org->name );

        // Delete the organization
        $result = $wpdb->delete( $table, array( 'id' => $org_id ) );

        if ( $result ) {
            do_action( 'wphd_organization_deleted', $org_id );
            return true;
        }

        return false;
    }

    /**
     * Get an organization by ID.
     *
     * @since  1.0.0
     * @param  int $org_id Organization ID.
     * @return object|null Organization object or null if not found.
     */
    public static function get( $org_id ) {
        global $wpdb;
        
        // Ensure tables exist before querying
        self::ensure_tables_exist();
        
        $table = $wpdb->prefix . 'wphd_organizations';

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $org_id ) );
    }

    /**
     * Get an organization by slug.
     *
     * @since  1.0.0
     * @param  string $slug Organization slug.
     * @return object|null Organization object or null if not found.
     */
    public static function get_by_slug( $slug ) {
        global $wpdb;
        
        // Ensure tables exist before querying
        self::ensure_tables_exist();
        
        $table = $wpdb->prefix . 'wphd_organizations';

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ) );
    }

    /**
     * Get all organizations.
     *
     * @since  1.0.0
     * @param  array $args Query arguments.
     * @return array Array of organization objects.
     */
    public static function get_all( $args = array() ) {
        global $wpdb;
        
        // Ensure tables exist before querying
        self::ensure_tables_exist();
        
        $table = $wpdb->prefix . 'wphd_organizations';

        $defaults = array(
            'status'  => '',
            'orderby' => 'name',
            'order'   => 'ASC',
            'limit'   => -1,
            'offset'  => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $sql = "SELECT * FROM $table WHERE 1=1";

        if ( ! empty( $args['status'] ) ) {
            $sql .= $wpdb->prepare( " AND status = %s", $args['status'] );
        }

        $sql .= sprintf( " ORDER BY %s %s", sanitize_sql_orderby( $args['orderby'] ), sanitize_sql_orderby( $args['order'] ) );

        if ( $args['limit'] > 0 ) {
            $sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Get organization settings.
     *
     * @since  1.0.0
     * @param  int $org_id Organization ID.
     * @return array Organization settings.
     */
    public static function get_settings( $org_id ) {
        $org = self::get( $org_id );
        if ( ! $org ) {
            return array();
        }

        return maybe_unserialize( $org->settings );
    }

    /**
     * Add a user to an organization.
     *
     * @since  1.0.0
     * @param  int    $org_id  Organization ID.
     * @param  int    $user_id User ID.
     * @param  string $role    User role in organization (default: 'member').
     * @return bool True on success, false on failure.
     */
    public static function add_member( $org_id, $user_id, $role = 'member' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organization_members';

        $result = $wpdb->insert(
            $table,
            array(
                'organization_id' => $org_id,
                'user_id'         => $user_id,
                'role'            => $role,
                'added_by'        => get_current_user_id(),
            )
        );

        if ( $result ) {
            $user = get_userdata( $user_id );
            self::log_change( $org_id, 'user_added', 'member', '', $user ? $user->user_email : $user_id );
            do_action( 'wphd_organization_member_added', $org_id, $user_id, $role );
            return true;
        }

        return false;
    }

    /**
     * Remove a user from an organization.
     *
     * @since  1.0.0
     * @param  int $org_id  Organization ID.
     * @param  int $user_id User ID.
     * @return bool True on success, false on failure.
     */
    public static function remove_member( $org_id, $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organization_members';

        $user = get_userdata( $user_id );
        $result = $wpdb->delete(
            $table,
            array(
                'organization_id' => $org_id,
                'user_id'         => $user_id,
            )
        );

        if ( $result ) {
            self::log_change( $org_id, 'user_removed', 'member', $user ? $user->user_email : $user_id, '' );
            do_action( 'wphd_organization_member_removed', $org_id, $user_id );
            return true;
        }

        return false;
    }

    /**
     * Get all members of an organization.
     *
     * @since  1.0.0
     * @param  int $org_id Organization ID.
     * @return array Array of member objects.
     */
    public static function get_members( $org_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organization_members';

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE organization_id = %d ORDER BY joined_at DESC", $org_id ) );
    }

    /**
     * Get organization IDs for a user.
     *
     * @since  1.0.0
     * @param  int $user_id User ID.
     * @return array Array of organization IDs.
     */
    public static function get_user_organizations( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organization_members';

        return $wpdb->get_col( $wpdb->prepare( "SELECT organization_id FROM $table WHERE user_id = %d", $user_id ) );
    }

    /**
     * Get the primary organization for a user.
     *
     * @since  1.0.0
     * @param  int $user_id User ID.
     * @return object|null Organization object or null if not found.
     */
    public static function get_user_organization( $user_id ) {
        $org_ids = self::get_user_organizations( $user_id );
        if ( empty( $org_ids ) ) {
            return null;
        }

        return self::get( $org_ids[0] );
    }

    /**
     * Get all user IDs in an organization.
     *
     * @since  1.0.0
     * @param  int $org_id Organization ID.
     * @return array Array of user IDs.
     */
    public static function get_organization_user_ids( $org_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organization_members';

        return $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $table WHERE organization_id = %d", $org_id ) );
    }

    /**
     * Auto-assign user to organization based on email domain.
     *
     * @since  1.0.0
     * @param  int $user_id User ID.
     * @return void
     */
    public function auto_assign_user_to_organization( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $email  = $user->user_email;
        $domain = substr( strrchr( $email, '@' ), 1 );

        if ( empty( $domain ) ) {
            return;
        }

        $orgs = self::get_all( array( 'status' => 'active' ) );

        foreach ( $orgs as $org ) {
            if ( empty( $org->allowed_domains ) ) {
                continue;
            }

            $allowed_domains = array_map( 'trim', explode( ',', $org->allowed_domains ) );

            if ( in_array( $domain, $allowed_domains, true ) ) {
                self::add_member( $org->id, $user_id, 'member' );
                break; // Only add to first matching organization
            }
        }
    }

    /**
     * Log a change to an organization.
     *
     * @since  1.0.0
     * @param  int    $org_id     Organization ID.
     * @param  string $action     Action type.
     * @param  string $field_name Field name.
     * @param  mixed  $old_value  Old value.
     * @param  mixed  $new_value  New value.
     * @return bool True on success, false on failure.
     */
    public static function log_change( $org_id, $action, $field_name = '', $old_value = '', $new_value = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organization_logs';

        // Get IP address safely - note: this can still be spoofed through proxies
        // but we use sanitization for security
        $ip_address = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        $result = $wpdb->insert(
            $table,
            array(
                'organization_id' => $org_id,
                'user_id'         => get_current_user_id(),
                'action'          => $action,
                'field_name'      => $field_name,
                'old_value'       => self::prepare_log_value( $old_value ),
                'new_value'       => self::prepare_log_value( $new_value ),
                'ip_address'      => $ip_address,
            )
        );

        return $result !== false;
    }

    /**
     * Prepare a value for logging.
     *
     * @since  1.0.0
     * @param  mixed $value Value to prepare.
     * @return string Prepared value.
     */
    private static function prepare_log_value( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            return maybe_serialize( $value );
        }
        return (string) $value;
    }

    /**
     * Get change logs for an organization.
     *
     * @since  1.0.0
     * @param  int   $org_id Organization ID.
     * @param  array $args   Query arguments.
     * @return array Array of log entries.
     */
    public static function get_logs( $org_id, $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organization_logs';

        $defaults = array(
            'limit'  => 50,
            'offset' => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $sql = $wpdb->prepare( "SELECT * FROM $table WHERE organization_id = %d ORDER BY created_at DESC", $org_id );

        if ( $args['limit'] > 0 ) {
            $sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Get member count for an organization.
     *
     * @since  1.0.0
     * @param  int $org_id Organization ID.
     * @return int Member count.
     */
    public static function get_member_count( $org_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wphd_organization_members';

        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE organization_id = %d", $org_id ) );
    }
}

<?php
/**
 * Organization Permissions Class
 *
 * Handles permission checks for organizations
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
 * Class WPHD_Organization_Permissions
 *
 * Manages organization-based permissions.
 *
 * @since 1.0.0
 */
class WPHD_Organization_Permissions {

    /**
     * Instance of this class.
     *
     * @since  1.0.0
     * @access private
     * @var    WPHD_Organization_Permissions
     */
    private static $instance = null;

    /**
     * Get the singleton instance of this class.
     *
     * @since  1.0.0
     * @return WPHD_Organization_Permissions
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
        add_filter( 'user_has_cap', array( $this, 'filter_ticket_capabilities' ), 10, 3 );
    }

    /**
     * Check if user can manage organizations.
     *
     * @since  1.0.0
     * @param  int $user_id User ID (default: current user).
     * @return bool True if user can manage organizations.
     */
    public static function can_manage_organizations( $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Administrators can manage organizations
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can create organizations.
     *
     * @since  1.0.0
     * @param  int $user_id User ID (default: current user).
     * @return bool True if user can create organizations.
     */
    public static function can_create_organizations( $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Only administrators can create organizations
        return in_array( 'administrator', $user->roles, true );
    }

    /**
     * Check if user is an organization admin.
     *
     * @since  1.0.0
     * @param  int $org_id  Organization ID.
     * @param  int $user_id User ID (default: current user).
     * @return bool True if user is organization admin.
     */
    public static function is_organization_admin( $org_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Super admins always have access
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return true;
        }

        global $wpdb;
        $members_table = $wpdb->prefix . 'wphd_organization_members';
        
        $is_admin = $wpdb->get_var( $wpdb->prepare(
            "SELECT is_admin FROM $members_table WHERE organization_id = %d AND user_id = %d",
            $org_id,
            $user_id
        ) );
        
        return (bool) $is_admin;
    }

    /**
     * Check if user can edit organization.
     *
     * @since  1.0.0
     * @param  int $org_id  Organization ID.
     * @param  int $user_id User ID (default: current user).
     * @return bool True if user can edit organization.
     */
    public static function can_edit_organization( $org_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Super administrators can edit any organization
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return true;
        }

        // Organization admins can edit their organization
        if ( self::is_organization_admin( $org_id, $user_id ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can delete organization.
     *
     * @since  1.0.0
     * @param  int $org_id  Organization ID.
     * @param  int $user_id User ID (default: current user).
     * @return bool True if user can delete organization.
     */
    public static function can_delete_organization( $org_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Only administrators can delete organizations
        return in_array( 'administrator', $user->roles, true );
    }

    /**
     * Check if user can change organization permissions.
     *
     * @since  1.0.0
     * @param  int $org_id  Organization ID.
     * @param  int $user_id User ID (default: current user).
     * @return bool True if user can change permissions.
     */
    public static function can_change_permissions( $org_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Only administrators can change organization permissions
        return in_array( 'administrator', $user->roles, true );
    }

    /**
     * Check if user can add/remove members.
     *
     * @since  1.0.0
     * @param  int $org_id  Organization ID.
     * @param  int $user_id User ID (default: current user).
     * @return bool True if user can manage members.
     */
    public static function can_manage_members( $org_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Administrators can manage members
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return true;
        }

        // Organization admins can manage members
        if ( self::is_organization_admin( $org_id, $user_id ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can view organization.
     *
     * @since  1.0.0
     * @param  int $org_id  Organization ID.
     * @param  int $user_id User ID (default: current user).
     * @return bool True if user can view organization.
     */
    public static function can_view_organization( $org_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Administrators and editors can view all organizations
        if ( in_array( 'administrator', $user->roles, true ) || in_array( 'editor', $user->roles, true ) ) {
            return true;
        }

        // Check if user is a member of the organization
        $user_orgs = WPHD_Organizations::get_user_organizations( $user_id );
        return in_array( $org_id, $user_orgs, true );
    }

    /**
     * Get visible ticket IDs for a user based on organization settings.
     *
     * @since  1.0.0
     * @param  int $user_id User ID (default: current user).
     * @return array|string Array of ticket IDs or 'all' for all tickets.
     */
    public static function get_visible_ticket_ids( $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return array();
        }

        // Administrators can see all tickets
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return 'all';
        }

        $org = WPHD_Organizations::get_user_organization( $user_id );
        if ( ! $org ) {
            // No organization - only show own tickets
            return self::get_user_ticket_ids( $user_id );
        }

        $settings = maybe_unserialize( $org->settings );

        // Check view_all_tickets permission
        if ( ! empty( $settings['view_all_tickets'] ) ) {
            return 'all';
        }

        // Check view_organization_tickets permission
        if ( ! empty( $settings['view_organization_tickets'] ) ) {
            $org_user_ids = WPHD_Organizations::get_organization_user_ids( $org->id );
            return self::get_tickets_by_authors( $org_user_ids );
        }

        // Check view_specific_orgs permission
        if ( ! empty( $settings['view_specific_orgs'] ) && is_array( $settings['view_specific_orgs'] ) ) {
            $visible_org_ids = array_merge( array( $org->id ), $settings['view_specific_orgs'] );
            $visible_user_ids = array();
            foreach ( $visible_org_ids as $visible_org_id ) {
                $visible_user_ids = array_merge( $visible_user_ids, WPHD_Organizations::get_organization_user_ids( $visible_org_id ) );
            }
            return self::get_tickets_by_authors( array_unique( $visible_user_ids ) );
        }

        // Default: view_own_tickets_only
        return self::get_user_ticket_ids( $user_id );
    }

    /**
     * Get ticket IDs created by specific authors.
     *
     * @since  1.0.0
     * @param  array $author_ids Array of author IDs.
     * @return array Array of ticket IDs.
     */
    private static function get_tickets_by_authors( $author_ids ) {
        if ( empty( $author_ids ) ) {
            return array();
        }

        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $author_ids ), '%d' ) );
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wphd_ticket' AND post_status = 'publish' AND post_author IN ($placeholders)";
        
        return $wpdb->get_col( $wpdb->prepare( $sql, $author_ids ) );
    }

    /**
     * Get ticket IDs created by a specific user.
     *
     * @since  1.0.0
     * @param  int $user_id User ID.
     * @return array Array of ticket IDs.
     */
    private static function get_user_ticket_ids( $user_id ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wphd_ticket' AND post_status = 'publish' AND post_author = %d",
            $user_id
        ) );
    }

    /**
     * Check if user can create tickets.
     *
     * @since  1.0.0
     * @param  int $user_id User ID (default: current user).
     * @return bool True if user can create tickets.
     */
    public static function can_create_ticket( $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Administrators can always create tickets
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return true;
        }

        $org = WPHD_Organizations::get_user_organization( $user_id );
        if ( ! $org ) {
            return true; // No organization restrictions
        }

        $settings = maybe_unserialize( $org->settings );
        return ! empty( $settings['can_create_tickets'] );
    }

    /**
     * Check if user can edit a ticket.
     *
     * @since  1.0.0
     * @param  int $ticket_id Ticket ID.
     * @param  int $user_id   User ID (default: current user).
     * @return bool True if user can edit ticket.
     */
    public static function can_edit_ticket( $ticket_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Administrators can edit any ticket
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return true;
        }

        $ticket = get_post( $ticket_id );
        if ( ! $ticket ) {
            return false;
        }

        $org = WPHD_Organizations::get_user_organization( $user_id );
        if ( ! $org ) {
            return $ticket->post_author == $user_id; // Own ticket only
        }

        $settings = maybe_unserialize( $org->settings );

        // Check if user is ticket author
        if ( $ticket->post_author == $user_id ) {
            return ! empty( $settings['can_edit_own_tickets'] );
        }

        // Check if user can edit org tickets
        if ( ! empty( $settings['can_edit_org_tickets'] ) ) {
            $org_user_ids = WPHD_Organizations::get_organization_user_ids( $org->id );
            return in_array( $ticket->post_author, $org_user_ids, true );
        }

        return false;
    }

    /**
     * Check if user can delete a ticket.
     *
     * @since  1.0.0
     * @param  int $ticket_id Ticket ID.
     * @param  int $user_id   User ID (default: current user).
     * @return bool True if user can delete ticket.
     */
    public static function can_delete_ticket( $ticket_id, $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Administrators can delete any ticket
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return true;
        }

        $ticket = get_post( $ticket_id );
        if ( ! $ticket ) {
            return false;
        }

        $org = WPHD_Organizations::get_user_organization( $user_id );
        if ( ! $org ) {
            return false; // No delete permission without organization
        }

        $settings = maybe_unserialize( $org->settings );

        // Check if user is ticket author
        if ( $ticket->post_author == $user_id ) {
            return ! empty( $settings['can_delete_own_tickets'] );
        }

        // Check if user can delete org tickets
        if ( ! empty( $settings['can_delete_org_tickets'] ) ) {
            $org_user_ids = WPHD_Organizations::get_organization_user_ids( $org->id );
            return in_array( $ticket->post_author, $org_user_ids, true );
        }

        return false;
    }

    /**
     * Check if user can view internal comments.
     *
     * @since  1.0.0
     * @param  int $user_id User ID (default: current user).
     * @return bool True if user can view internal comments.
     */
    public static function can_view_internal_comments( $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Administrators can always view internal comments
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return true;
        }

        $org = WPHD_Organizations::get_user_organization( $user_id );
        if ( ! $org ) {
            return false;
        }

        $settings = maybe_unserialize( $org->settings );
        return ! empty( $settings['can_view_internal_comments'] );
    }

    /**
     * Filter ticket capabilities based on organization permissions.
     *
     * @since  1.0.0
     * @param  array $allcaps All capabilities.
     * @param  array $caps    Required capabilities.
     * @param  array $args    Capability arguments.
     * @return array Modified capabilities.
     */
    public function filter_ticket_capabilities( $allcaps, $caps, $args ) {
        if ( empty( $args[0] ) ) {
            return $allcaps;
        }

        $capability = $args[0];
        $user_id    = isset( $args[1] ) ? $args[1] : 0;

        // Check ticket-related capabilities
        if ( 'create_wphd_tickets' === $capability ) {
            if ( ! self::can_create_ticket( $user_id ) ) {
                $allcaps['create_wphd_tickets'] = false;
            }
        }

        if ( 'edit_wphd_tickets' === $capability && ! empty( $args[2] ) ) {
            $ticket_id = $args[2];
            if ( ! self::can_edit_ticket( $ticket_id, $user_id ) ) {
                $allcaps['edit_wphd_tickets'] = false;
            }
        }

        if ( 'delete_wphd_tickets' === $capability && ! empty( $args[2] ) ) {
            $ticket_id = $args[2];
            if ( ! self::can_delete_ticket( $ticket_id, $user_id ) ) {
                $allcaps['delete_wphd_tickets'] = false;
            }
        }

        return $allcaps;
    }
}

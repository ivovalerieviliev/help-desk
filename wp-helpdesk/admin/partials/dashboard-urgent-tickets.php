<?php
/**
 * Partial template for urgent tickets in Take Action section
 * Used for AJAX refresh
 * 
 * Variables available:
 * - $urgent_tickets: Array of WP_Post objects
 * 
 * @package WP_HelpDesk
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get admin menu instance to access helper methods
$admin_menu = WPHD_Admin_Menu::instance();
?>

<div class="wphd-section-header">
    <h2>
        <span class="dashicons dashicons-warning"></span>
        <?php esc_html_e( 'Take Action: Ongoing Tickets', 'wp-helpdesk' ); ?>
    </h2>
    <span class="wphd-last-updated"><?php esc_html_e( 'Last updated:', 'wp-helpdesk' ); ?> <span id="wphd-last-refresh-time"><?php echo esc_html( current_time( 'H:i:s' ) ); ?></span></span>
</div>

<?php if ( ! empty( $urgent_tickets ) ) : ?>
    <div class="wphd-urgent-table-container">
        <table class="wp-list-table widefat fixed striped wphd-urgent-table">
            <thead>
                <tr>
                    <th style="width: 35%;"><?php esc_html_e( 'Title', 'wp-helpdesk' ); ?></th>
                    <th style="width: 15%;"><?php esc_html_e( 'Reporter', 'wp-helpdesk' ); ?></th>
                    <th style="width: 15%;"><?php esc_html_e( 'Category', 'wp-helpdesk' ); ?></th>
                    <th style="width: 10%;"><?php esc_html_e( 'Priority', 'wp-helpdesk' ); ?></th>
                    <th style="width: 10%;"><?php esc_html_e( 'Looking', 'wp-helpdesk' ); ?></th>
                    <th style="width: 15%;"><?php esc_html_e( 'Time Remaining', 'wp-helpdesk' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $urgent_tickets as $ticket ) : ?>
                    <?php
                    $ticket_id = $ticket->ID;
                    $reporter  = get_userdata( $ticket->post_author );
                    $category  = get_post_meta( $ticket_id, '_wphd_category', true );
                    $priority  = get_post_meta( $ticket_id, '_wphd_priority', true );
                    
                    // Calculate SLA data
                    $sla = WPHD_Database::get_sla( $ticket_id );
                    $sla_data = array(
                        'time_display' => __( 'No SLA', 'wp-helpdesk' ),
                        'status_class' => 'no-sla',
                        'type'         => '',
                    );
                    
                    if ( $sla ) {
                        $now = current_time( 'timestamp' );
                        
                        // Determine which SLA to show
                        if ( ! $sla->first_response_at && $sla->first_response_due ) {
                            $deadline       = strtotime( $sla->first_response_due );
                            $type           = __( 'First Response', 'wp-helpdesk' );
                            $created        = strtotime( get_post_field( 'post_date', $ticket_id ) );
                            $total_duration = $deadline - $created;
                        } elseif ( $sla->resolution_due ) {
                            $deadline       = strtotime( $sla->resolution_due );
                            $type           = __( 'Resolution', 'wp-helpdesk' );
                            $created        = strtotime( get_post_field( 'post_date', $ticket_id ) );
                            $total_duration = $deadline - $created;
                        } else {
                            $deadline = null;
                        }
                        
                        if ( $deadline ) {
                            $remaining = $deadline - $now;
                            
                            if ( $remaining < 0 ) {
                                $status_class = 'breached';
                                $time_display = sprintf( __( 'Overdue by %s', 'wp-helpdesk' ), human_time_diff( $now, $deadline ) );
                            } elseif ( $remaining < ( $total_duration * 0.25 ) ) {
                                $status_class = 'warning';
                                $time_display = human_time_diff( $now, $deadline );
                            } else {
                                $status_class = 'ok';
                                $time_display = human_time_diff( $now, $deadline );
                            }
                            
                            $sla_data = array(
                                'time_display' => $time_display,
                                'status_class' => $status_class,
                                'type'         => $type,
                            );
                        }
                    }
                    
                    // Get labels using admin menu helper methods
                    $admin_menu = WPHD_Admin_Menu::instance();
                    $priority_label = $admin_menu->get_priority_label( $priority );
                    $category_label = $admin_menu->get_category_label( $category );
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-helpdesk-tickets&ticket_id=' . $ticket_id ) ); ?>">
                                <strong><?php echo esc_html( $ticket->post_title ); ?></strong>
                            </a>
                        </td>
                        <td>
                            <?php if ( $reporter ) : ?>
                                <?php echo get_avatar( $reporter->ID, 24, '', '', array( 'class' => 'wphd-avatar' ) ); ?>
                                <?php echo esc_html( $reporter->display_name ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Unknown', 'wp-helpdesk' ); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $category_label ); ?></td>
                        <td><?php echo wp_kses_post( $priority_label ); ?></td>
                        <td>
                            <span class="wphd-looking-placeholder" title="<?php esc_attr_e( 'Viewers (coming soon)', 'wp-helpdesk' ); ?>">👁️</span>
                        </td>
                        <td>
                            <span class="wphd-time-remaining <?php echo esc_attr( $sla_data['status_class'] ); ?>">
                                <strong><?php echo esc_html( $sla_data['time_display'] ); ?></strong>
                                <?php if ( ! empty( $sla_data['type'] ) ) : ?>
                                    <br>
                                    <small><?php echo esc_html( $sla_data['type'] ); ?></small>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else : ?>
    <div class="wphd-empty-state">
        <p><?php esc_html_e( '✓ No urgent tickets at the moment. Great work!', 'wp-helpdesk' ); ?></p>
    </div>
<?php endif; ?>

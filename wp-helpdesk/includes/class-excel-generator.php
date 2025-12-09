<?php
/**
 * Excel Generator Class
 *
 * Generates Excel exports for handover reports.
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
 * Class WPHD_Excel_Generator
 *
 * Handles Excel generation for handover reports.
 *
 * @since 1.0.0
 */
class WPHD_Excel_Generator {

	/**
	 * Generate Excel file for handover report.
	 *
	 * @since 1.0.0
	 * @param int $report_id Report ID.
	 * @return string|false File URL on success, false on failure.
	 */
	public function generate_handover_report_excel( $report_id ) {
		// Validate report_id
		$report_id = absint( $report_id );
		if ( $report_id < 1 ) {
			return false;
		}

		$report = WPHD_Database::get_handover_report( $report_id );

		if ( ! $report ) {
			return false;
		}

		// Get report details
		$user         = get_userdata( $report->user_id );
		$creator_name = $user ? $user->display_name : __( 'Unknown', 'wp-helpdesk' );

		// Get all tickets for this report
		$tasks_todo      = WPHD_Database::get_handover_report_tickets( $report_id, 'tasks_todo' );
		$follow_up       = WPHD_Database::get_handover_report_tickets( $report_id, 'follow_up' );
		$important_info  = WPHD_Database::get_handover_report_tickets( $report_id, 'important_info' );

		// Create CSV content (simple approach without external libraries)
		$csv_data = array();

		// Header
		$csv_data[] = array( __( 'HANDOVER REPORT', 'wp-helpdesk' ) );
		$csv_data[] = array(); // Empty row

		// Report metadata
		$csv_data[] = array( __( 'Created By:', 'wp-helpdesk' ), $creator_name );
		$csv_data[] = array(
			__( 'Shift Type:', 'wp-helpdesk' ),
			$this->get_shift_type_label( $report->shift_type )
		);
		$csv_data[] = array(
			__( 'Date:', 'wp-helpdesk' ),
			mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $report->created_at )
		);
		$csv_data[] = array(); // Empty row

		// Tasks To Be Done
		if ( ! empty( $tasks_todo ) ) {
			$csv_data[] = array( __( 'TASKS TO BE DONE', 'wp-helpdesk' ) );
			$csv_data[] = array(
				__( 'Ticket ID', 'wp-helpdesk' ),
				__( 'Title', 'wp-helpdesk' ),
				__( 'Status', 'wp-helpdesk' ),
				__( 'Priority', 'wp-helpdesk' ),
				__( 'Instructions', 'wp-helpdesk' )
			);

			foreach ( $tasks_todo as $ticket_data ) {
				$ticket = get_post( $ticket_data->ticket_id );
				if ( $ticket ) {
					$csv_data[] = array(
						'#' . $ticket->ID,
						$ticket->post_title,
						get_post_meta( $ticket->ID, '_wphd_status', true ),
						get_post_meta( $ticket->ID, '_wphd_priority', true ),
						wp_strip_all_tags( $ticket_data->special_instructions )
					);
				}
			}
			$csv_data[] = array(); // Empty row
		}

		// Follow-up Tickets
		if ( ! empty( $follow_up ) ) {
			$csv_data[] = array( __( 'FOLLOW-UP TICKETS', 'wp-helpdesk' ) );
			$csv_data[] = array(
				__( 'Ticket ID', 'wp-helpdesk' ),
				__( 'Title', 'wp-helpdesk' ),
				__( 'Status', 'wp-helpdesk' ),
				__( 'Priority', 'wp-helpdesk' ),
				__( 'Instructions', 'wp-helpdesk' )
			);

			foreach ( $follow_up as $ticket_data ) {
				$ticket = get_post( $ticket_data->ticket_id );
				if ( $ticket ) {
					$csv_data[] = array(
						'#' . $ticket->ID,
						$ticket->post_title,
						get_post_meta( $ticket->ID, '_wphd_status', true ),
						get_post_meta( $ticket->ID, '_wphd_priority', true ),
						wp_strip_all_tags( $ticket_data->special_instructions )
					);
				}
			}
			$csv_data[] = array(); // Empty row
		}

		// Important Information
		if ( ! empty( $important_info ) ) {
			$csv_data[] = array( __( 'IMPORTANT INFORMATION', 'wp-helpdesk' ) );
			$csv_data[] = array(
				__( 'Ticket ID', 'wp-helpdesk' ),
				__( 'Title', 'wp-helpdesk' ),
				__( 'Status', 'wp-helpdesk' ),
				__( 'Priority', 'wp-helpdesk' ),
				__( 'Instructions', 'wp-helpdesk' )
			);

			foreach ( $important_info as $ticket_data ) {
				$ticket = get_post( $ticket_data->ticket_id );
				if ( $ticket ) {
					$csv_data[] = array(
						'#' . $ticket->ID,
						$ticket->post_title,
						get_post_meta( $ticket->ID, '_wphd_status', true ),
						get_post_meta( $ticket->ID, '_wphd_priority', true ),
						wp_strip_all_tags( $ticket_data->special_instructions )
					);
				}
			}
			$csv_data[] = array(); // Empty row
		}

		// Additional Instructions
		if ( ! empty( $report->additional_instructions ) ) {
			$csv_data[] = array( __( 'ADDITIONAL INSTRUCTIONS', 'wp-helpdesk' ) );
			$csv_data[] = array( wp_strip_all_tags( $report->additional_instructions ) );
		}

		// Create file with WordPress filesystem
		$upload_dir = wp_upload_dir();
		$filename   = 'handover-report-' . absint( $report_id ) . '-' . gmdate( 'Y-m-d-His' ) . '.csv';
		$file_path  = trailingslashit( $upload_dir['path'] ) . sanitize_file_name( $filename );

		// Use WordPress filesystem
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		// Generate CSV content in memory
		ob_start();
		$temp_fp = fopen( 'php://output', 'w' );
		if ( ! $temp_fp ) {
			return false;
		}

		foreach ( $csv_data as $row ) {
			fputcsv( $temp_fp, $row );
		}

		fclose( $temp_fp );
		$csv_content = ob_get_clean();

		// Write to file using WordPress filesystem
		if ( ! $wp_filesystem->put_contents( $file_path, $csv_content, FS_CHMOD_FILE ) ) {
			return false;
		}

		// Return download URL
		return trailingslashit( $upload_dir['url'] ) . sanitize_file_name( $filename );
	}

	/**
	 * Get shift type label.
	 *
	 * @since 1.0.0
	 * @param string $shift_type Shift type slug.
	 * @return string Shift type label.
	 */
	private function get_shift_type_label( $shift_type ) {
		$types = array(
			'morning'   => __( 'Morning', 'wp-helpdesk' ),
			'afternoon' => __( 'Afternoon', 'wp-helpdesk' ),
			'night'     => __( 'Night', 'wp-helpdesk' ),
			'evening'   => __( 'Evening', 'wp-helpdesk' ),
		);

		return isset( $types[ $shift_type ] ) ? $types[ $shift_type ] : ucfirst( $shift_type );
	}
}

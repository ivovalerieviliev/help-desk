<?php
/**
 * Handover History Class
 *
 * Handles the display and management of handover report history.
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
 * Class WPHD_Handover_History
 *
 * Manages handover report history functionality including display, filtering, and exports.
 *
 * @since 1.0.0
 */
class WPHD_Handover_History {

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    WPHD_Handover_History
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @since  1.0.0
	 * @return WPHD_Handover_History
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
		add_action( 'wp_ajax_wphd_filter_handover_reports', array( $this, 'ajax_filter_reports' ) );
		add_action( 'wp_ajax_wphd_export_handover_excel', array( $this, 'export_to_excel' ) );
		add_action( 'wp_ajax_wphd_export_handover_pdf', array( $this, 'export_to_pdf' ) );
		add_action( 'wp_ajax_wphd_get_report_instructions', array( $this, 'ajax_get_instructions' ) );
		add_action( 'wp_ajax_wphd_search_handover_reports', array( $this, 'ajax_search_reports' ) );
		add_action( 'wp_ajax_wphd_get_report_details', array( $this, 'ajax_get_report_details' ) );
		add_action( 'wp_ajax_wphd_update_handover_report', array( $this, 'ajax_update_report' ) );
	}

	/**
	 * Render the history page.
	 *
	 * @since 1.0.0
	 */
	public function render_history_page() {
		if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
			wp_die( esc_html__( 'You do not have permission to view handover reports.', 'wp-helpdesk' ) );
		}

		// Show success message if redirected from create page
		if ( isset( $_GET['created'] ) && '1' === $_GET['created'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Handover report created successfully!', 'wp-helpdesk' ); ?></p>
			</div>
			<?php
		}
		
		// Show merge success message
		if ( isset( $_GET['merged'] ) && '1' === $_GET['merged'] ) {
			$added_count = isset( $_GET['added'] ) ? intval( $_GET['added'] ) : 0;
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					/* translators: %d: Number of tickets added */
					printf( esc_html__( 'Report updated successfully! %d new ticket(s) added.', 'wp-helpdesk' ), $added_count );
					?>
				</p>
			</div>
			<?php
		}

		?>
		<div class="wrap wp-helpdesk-wrap wphd-handover-history-wrap">
			<h1><?php esc_html_e( 'Handover Report History', 'wp-helpdesk' ); ?></h1>
			
			<?php $this->render_search_bar(); ?>
			<?php $this->render_filter_section(); ?>
			<?php $this->render_reports_table(); ?>
		</div>

		<!-- Special Instructions Modal -->
		<div id="wphd-instructions-modal" class="wphd-modal" style="display: none;">
			<div class="wphd-modal-content wphd-instructions-modal-content">
				<div class="wphd-modal-header">
					<h2><?php esc_html_e( 'Special Instructions', 'wp-helpdesk' ); ?></h2>
					<span class="wphd-modal-close">&times;</span>
				</div>
				<div class="wphd-modal-body" id="wphd-instructions-content">
					<!-- Content will be loaded here -->
				</div>
				<div class="wphd-modal-footer">
					<button type="button" class="button wphd-close-modal-btn"><?php esc_html_e( 'Close', 'wp-helpdesk' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the search bar section.
	 *
	 * @since 1.0.0
	 */
	private function render_search_bar() {
		?>
		<div class="wphd-search-bar-container">
			<div class="wphd-search-input-wrapper">
				<span class="dashicons dashicons-search"></span>
				<input 
					type="text" 
					id="wphd-handover-search-input" 
					placeholder="<?php esc_attr_e( 'Search handovers by ticket ID, title, description, comments, or instructions...', 'wp-helpdesk' ); ?>"
				>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the filter section.
	 *
	 * @since 1.0.0
	 */
	private function render_filter_section() {
		?>
		<div class="wphd-history-filters">
			<div class="wphd-filter-buttons">
				<button type="button" class="button wphd-filter-btn" data-filter="last_24h">
					<?php esc_html_e( 'Last 24 Hours', 'wp-helpdesk' ); ?>
				</button>
				<button type="button" class="button wphd-filter-btn" data-filter="last_week">
					<?php esc_html_e( 'Last Week', 'wp-helpdesk' ); ?>
				</button>
				<button type="button" class="button wphd-filter-btn" data-filter="custom">
					<?php esc_html_e( 'Custom', 'wp-helpdesk' ); ?>
				</button>
			</div>

			<div class="wphd-custom-date-range" style="display: none;">
				<div class="wphd-date-inputs">
					<div class="wphd-date-group">
						<label for="wphd-start-date"><?php esc_html_e( 'Start Date', 'wp-helpdesk' ); ?></label>
						<input type="date" id="wphd-start-date" class="regular-text">
						<input type="time" id="wphd-start-time" value="00:00">
					</div>
					<div class="wphd-date-group">
						<label for="wphd-end-date"><?php esc_html_e( 'End Date', 'wp-helpdesk' ); ?></label>
						<input type="date" id="wphd-end-date" class="regular-text">
						<input type="time" id="wphd-end-time" value="23:59">
					</div>
				</div>
			</div>

			<button type="button" class="button button-primary wphd-apply-filter">
				<?php esc_html_e( 'Filter', 'wp-helpdesk' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render the reports table.
	 *
	 * @since 1.0.0
	 */
	private function render_reports_table() {
		// Default: Show last 7 days
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$date_to   = current_time( 'mysql' );

		$reports = $this->get_filtered_reports( array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
		) );

		?>
		<div id="wphd-reports-table-container">
			<?php $this->display_reports_table( $reports ); ?>
		</div>
		<?php
	}

	/**
	 * Display the reports table HTML.
	 *
	 * @since 1.0.0
	 * @param array $reports Array of report objects.
	 */
	private function display_reports_table( $reports ) {
		if ( empty( $reports ) ) {
			?>
			<p><?php esc_html_e( 'No handover reports found for the selected period.', 'wp-helpdesk' ); ?></p>
			<?php
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Submitted On', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Shift Type', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Handover Creator', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Tickets Reported', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Special Instructions', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $reports as $report ) : ?>
					<?php
					$user         = get_userdata( $report->user_id );
					$ticket_count = $this->get_report_ticket_count( $report->id );
					$instructions = wp_trim_words( wp_strip_all_tags( $report->additional_instructions ), 15, '...' );
					?>
					<tr>
						<td>
							<?php
							echo esc_html(
								mysql2date(
									get_option( 'date_format' ) . ' ' . __( 'at', 'wp-helpdesk' ) . ' ' . get_option( 'time_format' ),
									$report->created_at
								)
							);
							?>
						</td>
						<td><?php echo esc_html( $this->get_shift_type_label( $report->shift_type ) ); ?></td>
						<td><?php echo esc_html( $user ? $user->display_name : __( 'Unknown', 'wp-helpdesk' ) ); ?></td>
						<td><?php echo esc_html( $ticket_count ); ?></td>
						<td>
							<?php if ( ! empty( $report->additional_instructions ) ) : ?>
								<a href="#" class="wphd-view-instructions" data-report-id="<?php echo esc_attr( $report->id ); ?>">
									<?php echo esc_html( $instructions ); ?>
								</a>
							<?php else : ?>
								<em><?php esc_html_e( 'None', 'wp-helpdesk' ); ?></em>
							<?php endif; ?>
						</td>
						<td class="wphd-action-buttons">
							<button type="button" class="button button-small wphd-view-btn" data-report-id="<?php echo esc_attr( $report->id ); ?>">
								<span class="dashicons dashicons-visibility"></span>
								<?php esc_html_e( 'View', 'wp-helpdesk' ); ?>
							</button>
							<button type="button" class="button button-small wphd-edit-btn" data-report-id="<?php echo esc_attr( $report->id ); ?>">
								<span class="dashicons dashicons-edit"></span>
								<?php esc_html_e( 'Edit', 'wp-helpdesk' ); ?>
							</button>
							<button type="button" class="button button-small wphd-export-excel" data-report-id="<?php echo esc_attr( $report->id ); ?>">
								<span class="dashicons dashicons-media-spreadsheet"></span>
								<?php esc_html_e( 'Excel', 'wp-helpdesk' ); ?>
							</button>
							<button type="button" class="button button-small wphd-export-pdf" data-report-id="<?php echo esc_attr( $report->id ); ?>">
								<span class="dashicons dashicons-pdf"></span>
								<?php esc_html_e( 'PDF', 'wp-helpdesk' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get filtered reports.
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments.
	 * @return array Array of report objects.
	 */
	private function get_filtered_reports( $args = array() ) {
		$defaults = array(
			'date_from' => '',
			'date_to'   => '',
			'limit'     => 20,
			'offset'    => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		return WPHD_Database::get_handover_reports( $args );
	}

	/**
	 * Get ticket count for a report.
	 *
	 * @since 1.0.0
	 * @param int $report_id Report ID.
	 * @return int Ticket count.
	 */
	private function get_report_ticket_count( $report_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wphd_handover_report_tickets';
		
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE report_id = %d",
			$report_id
		) );
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

	/**
	 * AJAX handler for filtering reports.
	 *
	 * @since 1.0.0
	 */
	public function ajax_filter_reports() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
		}

		$filter_type = isset( $_POST['filter_type'] ) ? sanitize_text_field( $_POST['filter_type'] ) : '';

		$args = array();

		if ( 'last_24h' === $filter_type ) {
			$args['date_from'] = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
			$args['date_to']   = current_time( 'mysql' );
		} elseif ( 'last_week' === $filter_type ) {
			$args['date_from'] = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
			$args['date_to']   = current_time( 'mysql' );
		} elseif ( 'custom' === $filter_type ) {
			$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
			$start_time = isset( $_POST['start_time'] ) ? sanitize_text_field( $_POST['start_time'] ) : '00:00';
			$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';
			$end_time   = isset( $_POST['end_time'] ) ? sanitize_text_field( $_POST['end_time'] ) : '23:59';

			if ( empty( $start_date ) || empty( $end_date ) ) {
				wp_send_json_error( array( 'message' => __( 'Please select both start and end dates.', 'wp-helpdesk' ) ) );
			}

			// Validate date format
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid date format.', 'wp-helpdesk' ) ) );
			}

			// Validate time format
			if ( ! preg_match( '/^\d{2}:\d{2}$/', $start_time ) || ! preg_match( '/^\d{2}:\d{2}$/', $end_time ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid time format.', 'wp-helpdesk' ) ) );
			}

			$args['date_from'] = $start_date . ' ' . $start_time . ':00';
			$args['date_to']   = $end_date . ' ' . $end_time . ':59';

			// Validate that end date is after start date
			if ( strtotime( $args['date_to'] ) < strtotime( $args['date_from'] ) ) {
				wp_send_json_error( array( 'message' => __( 'End date must be after start date.', 'wp-helpdesk' ) ) );
			}
		}

		$reports = $this->get_filtered_reports( $args );

		ob_start();
		$this->display_reports_table( $reports );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Export report to Excel.
	 *
	 * @since 1.0.0
	 */
	public function export_to_excel() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
		}

		$report_id = isset( $_POST['report_id'] ) ? intval( $_POST['report_id'] ) : 0;

		if ( ! $report_id || $report_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid report ID.', 'wp-helpdesk' ) ) );
		}

		$report = WPHD_Database::get_handover_report( $report_id );

		if ( ! $report ) {
			wp_send_json_error( array( 'message' => __( 'Report not found.', 'wp-helpdesk' ) ) );
		}

		// Use the Excel generator class
		$generator = new WPHD_Excel_Generator();
		$file_path = $generator->generate_handover_report_excel( $report_id );

		if ( $file_path ) {
			wp_send_json_success( array( 'file_url' => $file_path ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to generate Excel file.', 'wp-helpdesk' ) ) );
		}
	}

	/**
	 * Export report to PDF.
	 *
	 * @since 1.0.0
	 */
	public function export_to_pdf() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
		}

		$report_id = isset( $_POST['report_id'] ) ? intval( $_POST['report_id'] ) : 0;

		if ( ! $report_id || $report_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid report ID.', 'wp-helpdesk' ) ) );
		}

		$report = WPHD_Database::get_handover_report( $report_id );

		if ( ! $report ) {
			wp_send_json_error( array( 'message' => __( 'Report not found.', 'wp-helpdesk' ) ) );
		}

		// Use the PDF generator class
		$generator = new WPHD_PDF_Generator();
		$file_path = $generator->generate_handover_report_pdf( $report_id );

		if ( $file_path ) {
			wp_send_json_success( array( 'file_url' => $file_path ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to generate PDF file.', 'wp-helpdesk' ) ) );
		}
	}

	/**
	 * AJAX handler for getting report instructions.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_instructions() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
		}

		$report_id = isset( $_POST['report_id'] ) ? intval( $_POST['report_id'] ) : 0;

		if ( ! $report_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid report ID.', 'wp-helpdesk' ) ) );
		}

		$report = WPHD_Database::get_handover_report( $report_id );

		if ( ! $report ) {
			wp_send_json_error( array( 'message' => __( 'Report not found.', 'wp-helpdesk' ) ) );
		}

		wp_send_json_success( array( 'instructions' => $report->additional_instructions ) );
	}
	
	/**
	 * AJAX handler for searching reports.
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_reports() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
		}

		$search_term = isset( $_POST['search_term'] ) ? sanitize_text_field( $_POST['search_term'] ) : '';
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : '';
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : '';

		if ( strlen( $search_term ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter at least 2 characters to search.', 'wp-helpdesk' ) ) );
		}
		
		// Prevent DoS attacks from extremely long search terms
		if ( strlen( $search_term ) > 200 ) {
			wp_send_json_error( array( 'message' => __( 'Search term is too long. Please use fewer than 200 characters.', 'wp-helpdesk' ) ) );
		}

		$filters = array();
		if ( $date_from ) {
			$filters['date_from'] = $date_from;
		}
		if ( $date_to ) {
			$filters['date_to'] = $date_to;
		}

		$reports = WPHD_Database::search_handover_reports( $search_term, $filters );

		ob_start();
		$this->display_reports_table( $reports );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}
	
	/**
	 * AJAX handler for getting report details.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_report_details() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
		}

		$report_id = isset( $_POST['report_id'] ) ? intval( $_POST['report_id'] ) : 0;

		if ( ! $report_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid report ID.', 'wp-helpdesk' ) ) );
		}

		$report = WPHD_Database::get_handover_report( $report_id );

		if ( ! $report ) {
			wp_send_json_error( array( 'message' => __( 'Report not found.', 'wp-helpdesk' ) ) );
		}

		// Get report tickets grouped by section
		$tickets_data = array(
			'tasks_todo' => array(),
			'follow_up' => array(),
			'important_info' => array()
		);

		$report_tickets = WPHD_Database::get_handover_report_tickets( $report_id );

		foreach ( $report_tickets as $report_ticket ) {
			$ticket = get_post( $report_ticket->ticket_id );
			if ( ! $ticket ) {
				continue;
			}

			$ticket_data = array(
				'id' => $ticket->ID,
				'title' => $ticket->post_title,
				'special_instructions' => $report_ticket->special_instructions
			);

			$section = $report_ticket->section_type;
			if ( isset( $tickets_data[ $section ] ) ) {
				$tickets_data[ $section ][] = $ticket_data;
			}
		}

		// Get additional instructions
		$additional_instructions = WPHD_Database::get_additional_instructions( $report_id );

		wp_send_json_success( array(
			'report' => $report,
			'tickets' => $tickets_data,
			'additional_instructions_list' => $additional_instructions
		) );
	}
	
	/**
	 * AJAX handler for updating a report.
	 *
	 * @since 1.0.0
	 */
	public function ajax_update_report() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		if ( ! current_user_can( 'create_wphd_handover_reports' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-helpdesk' ) ) );
		}

		$report_id = isset( $_POST['report_id'] ) ? intval( $_POST['report_id'] ) : 0;
		$tickets_data_json = isset( $_POST['tickets_data'] ) ? sanitize_textarea_field( stripslashes( $_POST['tickets_data'] ) ) : '';
		$additional_instructions = isset( $_POST['additional_instructions'] ) ? wp_kses_post( $_POST['additional_instructions'] ) : '';

		if ( ! $report_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid report ID.', 'wp-helpdesk' ) ) );
		}

		$tickets_data = json_decode( $tickets_data_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ticket data.', 'wp-helpdesk' ) ) );
		}

		// Update additional instructions
		if ( ! empty( $additional_instructions ) ) {
			WPHD_Database::update_handover_report( $report_id, array(
				'additional_instructions' => $additional_instructions
			) );
		}

		// Clear existing tickets for this report
		global $wpdb;
		$wpdb->delete( 
			$wpdb->prefix . 'wphd_handover_report_tickets',
			array( 'report_id' => $report_id ),
			array( '%d' )
		);

		// Add updated tickets
		foreach ( $tickets_data as $section => $tickets ) {
			foreach ( $tickets as $index => $ticket ) {
				WPHD_Database::add_handover_report_ticket(
					$report_id,
					$ticket['ticket_id'],
					$section,
					isset( $ticket['special_instructions'] ) ? $ticket['special_instructions'] : '',
					$index
				);
			}
		}

		wp_send_json_success( array( 'message' => __( 'Report updated successfully.', 'wp-helpdesk' ) ) );
	}
}

<?php
/**
 * PDF Generator Class
 *
 * Generates PDF exports for handover reports.
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
 * Class WPHD_PDF_Generator
 *
 * Handles PDF generation for handover reports using HTML to PDF approach.
 *
 * @since 1.0.0
 */
class WPHD_PDF_Generator {

	/**
	 * Generate PDF file for handover report.
	 *
	 * @since 1.0.0
	 * @param int $report_id Report ID.
	 * @return string|false File URL on success, false on failure.
	 */
	public function generate_handover_report_pdf( $report_id ) {
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

		// Generate HTML content
		$html = $this->generate_pdf_html( $report, $creator_name, $tasks_todo, $follow_up, $important_info );

		// Create file with WordPress filesystem
		$upload_dir = wp_upload_dir();
		$filename   = 'handover-report-' . absint( $report_id ) . '-' . gmdate( 'Y-m-d-His' ) . '.html';
		$file_path  = trailingslashit( $upload_dir['path'] ) . sanitize_file_name( $filename );

		// Use WordPress filesystem
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		// Write to file using WordPress filesystem
		if ( ! $wp_filesystem->put_contents( $file_path, $html, FS_CHMOD_FILE ) ) {
			return false;
		}

		// Return download URL
		return trailingslashit( $upload_dir['url'] ) . sanitize_file_name( $filename );
	}

	/**
	 * Generate HTML content for PDF.
	 *
	 * @since 1.0.0
	 * @param object $report Report object.
	 * @param string $creator_name Creator display name.
	 * @param array  $tasks_todo Tasks to do tickets.
	 * @param array  $follow_up Follow-up tickets.
	 * @param array  $important_info Important info tickets.
	 * @return string HTML content.
	 */
	private function generate_pdf_html( $report, $creator_name, $tasks_todo, $follow_up, $important_info ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title><?php esc_html_e( 'Handover Report', 'wp-helpdesk' ); ?></title>
			<style>
				* {
					margin: 0;
					padding: 0;
					box-sizing: border-box;
				}
				body {
					font-family: Arial, sans-serif;
					font-size: 12px;
					color: #333;
					padding: 20px;
					background: #fff;
				}
				.header {
					text-align: center;
					margin-bottom: 30px;
					padding: 20px;
					background: #2271b1;
					color: #fff;
				}
				.header h1 {
					font-size: 24px;
					margin-bottom: 10px;
				}
				.section {
					margin-bottom: 30px;
					page-break-inside: avoid;
				}
				.section-header {
					background: #f0f0f1;
					padding: 10px 15px;
					border-left: 4px solid #2271b1;
					margin-bottom: 15px;
					font-size: 16px;
					font-weight: bold;
				}
				.meta-table {
					width: 100%;
					margin-bottom: 20px;
				}
				.meta-table td {
					padding: 8px;
					border-bottom: 1px solid #ddd;
				}
				.meta-table td:first-child {
					font-weight: bold;
					width: 150px;
				}
				.tickets-table {
					width: 100%;
					border-collapse: collapse;
					margin-bottom: 20px;
				}
				.tickets-table th,
				.tickets-table td {
					border: 1px solid #ddd;
					padding: 10px;
					text-align: left;
				}
				.tickets-table th {
					background: #2271b1;
					color: #fff;
					font-weight: bold;
				}
				.tickets-table tr:nth-child(even) {
					background: #f9f9f9;
				}
				.instructions-content {
					padding: 15px;
					background: #f9f9f9;
					border-left: 4px solid #2271b1;
					line-height: 1.6;
				}
				.footer {
					margin-top: 40px;
					padding-top: 20px;
					border-top: 2px solid #ddd;
					text-align: center;
					color: #666;
					font-size: 11px;
				}
				@media print {
					body {
						padding: 0;
					}
					.section {
						page-break-inside: avoid;
					}
				}
			</style>
		</head>
		<body>
			<div class="header">
				<h1><?php esc_html_e( 'HANDOVER REPORT', 'wp-helpdesk' ); ?></h1>
			</div>

			<div class="section">
				<div class="section-header"><?php esc_html_e( 'Shift Details', 'wp-helpdesk' ); ?></div>
				<table class="meta-table">
					<tr>
						<td><?php esc_html_e( 'Created By:', 'wp-helpdesk' ); ?></td>
						<td><?php echo esc_html( $creator_name ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Shift Type:', 'wp-helpdesk' ); ?></td>
						<td><?php echo esc_html( $this->get_shift_type_label( $report->shift_type ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Date:', 'wp-helpdesk' ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $report->created_at ) ); ?></td>
					</tr>
				</table>
			</div>

			<?php if ( ! empty( $tasks_todo ) ) : ?>
			<div class="section">
				<div class="section-header"><?php esc_html_e( 'Tasks To Be Done', 'wp-helpdesk' ); ?></div>
				<?php echo $this->render_tickets_table( $tasks_todo, 'tasks_todo' ); ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $follow_up ) ) : ?>
			<div class="section">
				<div class="section-header"><?php esc_html_e( 'Follow-up Tickets', 'wp-helpdesk' ); ?></div>
				<?php echo $this->render_tickets_table( $follow_up, 'follow_up' ); ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $important_info ) ) : ?>
			<div class="section">
				<div class="section-header"><?php esc_html_e( 'Important Information', 'wp-helpdesk' ); ?></div>
				<?php echo $this->render_tickets_table( $important_info, 'important_info' ); ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $report->additional_instructions ) ) : ?>
			<div class="section">
				<div class="section-header"><?php esc_html_e( 'Additional Instructions', 'wp-helpdesk' ); ?></div>
				<div class="instructions-content">
					<?php echo wp_kses_post( $report->additional_instructions ); ?>
				</div>
			</div>
			<?php endif; ?>

			<div class="footer">
				<?php
				echo esc_html(
					sprintf(
						__( 'Generated: %s', 'wp-helpdesk' ),
						current_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
					)
				);
				?>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render tickets table HTML.
	 *
	 * @since 1.0.0
	 * @param array  $tickets Array of ticket objects.
	 * @param string $section_type Section type (tasks_todo, follow_up, important_info).
	 * @return string HTML table.
	 */
	private function render_tickets_table( $tickets, $section_type = 'tasks_todo' ) {
		// Define columns for each section
		$section_columns = array(
			'tasks_todo' => array(
				'Ticket ID',
				'Ticket Title',
				'Reporter',
				'Category',
				'Creation Date & Time',
				'Due Date',
			),
			'follow_up' => array(
				'Ticket ID',
				'Ticket Title',
				'Reporter',
				'Category',
				'Priority',
				'Creation Date & Time',
			),
			'important_info' => array(
				'Ticket ID',
				'Title',
				'Reporter',
				'Priority',
				'Special Instructions',
			),
		);

		$columns = isset( $section_columns[ $section_type ] ) ? $section_columns[ $section_type ] : $section_columns['tasks_todo'];

		ob_start();
		?>
		<table class="tickets-table">
			<thead>
				<tr>
					<?php foreach ( $columns as $column_label ) : ?>
						<th><?php echo esc_html( $column_label ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tickets as $ticket_data ) : ?>
					<?php
					$ticket = get_post( $ticket_data->ticket_id );
					if ( ! $ticket ) {
						continue;
					}

					// Get ticket metadata
					$reporter_id = $ticket->post_author;
					$reporter = get_userdata( $reporter_id );
					$reporter_name = $reporter ? $reporter->display_name : __( 'Unknown', 'wp-helpdesk' );
					
					$category = get_post_meta( $ticket->ID, '_wphd_category', true );
					$priority = get_post_meta( $ticket->ID, '_wphd_priority', true );
					$due_date = get_post_meta( $ticket->ID, '_wphd_due_date', true );
					$created_date = get_the_date( 'M j, Y g:i A', $ticket->ID );

					// Get labels
					$category_label = $this->get_category_label( $category );
					$priority_label = $this->get_priority_label( $priority );
					$due_date_formatted = $due_date ? mysql2date( 'M j, Y', $due_date ) : __( 'N/A', 'wp-helpdesk' );
					?>
					<tr>
						<?php if ( 'important_info' === $section_type ) : ?>
							<td>#<?php echo esc_html( $ticket->ID ); ?></td>
							<td><?php echo esc_html( $ticket->post_title ); ?></td>
							<td><?php echo esc_html( $reporter_name ); ?></td>
							<td><?php echo esc_html( $priority_label ); ?></td>
							<td><?php echo esc_html( $ticket_data->special_instructions ); ?></td>
						<?php elseif ( 'follow_up' === $section_type ) : ?>
							<td>#<?php echo esc_html( $ticket->ID ); ?></td>
							<td><?php echo esc_html( $ticket->post_title ); ?></td>
							<td><?php echo esc_html( $reporter_name ); ?></td>
							<td><?php echo esc_html( $category_label ); ?></td>
							<td><?php echo esc_html( $priority_label ); ?></td>
							<td><?php echo esc_html( $created_date ); ?></td>
						<?php else : ?>
							<!-- tasks_todo -->
							<td>#<?php echo esc_html( $ticket->ID ); ?></td>
							<td><?php echo esc_html( $ticket->post_title ); ?></td>
							<td><?php echo esc_html( $reporter_name ); ?></td>
							<td><?php echo esc_html( $category_label ); ?></td>
							<td><?php echo esc_html( $created_date ); ?></td>
							<td><?php echo esc_html( $due_date_formatted ); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get category label.
	 *
	 * @since 1.0.0
	 * @param string $category Category slug.
	 * @return string Category label.
	 */
	private function get_category_label( $category ) {
		$categories = get_option( 'wphd_categories', array() );
		foreach ( $categories as $cat ) {
			if ( $cat['slug'] === $category ) {
				return $cat['name'];
			}
		}
		return ucfirst( $category );
	}

	/**
	 * Get priority label.
	 *
	 * @since 1.0.0
	 * @param string $priority Priority slug.
	 * @return string Priority label.
	 */
	private function get_priority_label( $priority ) {
		$priorities = get_option( 'wphd_priorities', array() );
		foreach ( $priorities as $p ) {
			if ( $p['slug'] === $priority ) {
				return $p['name'];
			}
		}
		return ucfirst( $priority );
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

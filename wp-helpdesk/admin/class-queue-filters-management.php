<?php
/**
 * Queue Filters Management Class
 *
 * Handles the admin interface for managing queue filters
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
 * Class WPHD_Queue_Filters_Management
 *
 * Manages the queue filters admin interface.
 *
 * @since 1.0.0
 */
class WPHD_Queue_Filters_Management {

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    WPHD_Queue_Filters_Management
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @since  1.0.0
	 * @return WPHD_Queue_Filters_Management
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
		add_action( 'admin_post_wphd_save_queue_filter', array( $this, 'handle_save_filter' ) );
		add_action( 'wp_ajax_wphd_delete_queue_filter', array( $this, 'ajax_delete_filter' ) );
		add_action( 'wp_ajax_wphd_set_default_filter', array( $this, 'ajax_set_default' ) );
		add_action( 'wp_ajax_wphd_preview_filter', array( $this, 'ajax_preview_filter' ) );
		add_action( 'wp_ajax_wphd_get_filter', array( $this, 'ajax_get_filter' ) );
		add_action( 'wp_ajax_wphd_search_users_for_filter', array( $this, 'ajax_search_users' ) );
	}

	/**
	 * Render the queue filters management page.
	 *
	 * @since 1.0.0
	 */
	public function render_management_page() {
		// Check permissions
		if ( ! WPHD_Access_Control::can_access( 'queue_filters_user_create' ) && ! WPHD_Access_Control::can_access( 'queue_filters_org_create' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage queue filters.', 'wp-helpdesk' ) );
		}

		// Get filters
		$user_id      = get_current_user_id();
		$user_filters = WPHD_Queue_Filters::get_user_filters( $user_id );
		$org_filters  = array();

		if ( WPHD_Access_Control::can_access( 'queue_filters_org_view' ) && class_exists( 'WPHD_Organizations' ) ) {
			$user_org = WPHD_Organizations::get_user_organization( $user_id );
			if ( $user_org ) {
				$org_filters = WPHD_Queue_Filters::get_organization_filters( $user_org->id );
			}
		}

		// Check if we're editing a filter
		$editing_filter = null;
		if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['filter_id'] ) ) {
			$filter_id = absint( $_GET['filter_id'] );
			if ( WPHD_Queue_Filters::can_edit_filter( $filter_id ) ) {
				$editing_filter = WPHD_Queue_Filters::get( $filter_id );
			}
		}

		?>
		<div class="wrap wp-helpdesk-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Queue Filters', 'wp-helpdesk' ); ?></h1>
			<?php if ( WPHD_Access_Control::can_access( 'queue_filters_user_create' ) || WPHD_Access_Control::can_access( 'queue_filters_org_create' ) ) : ?>
			<a href="#" class="page-title-action wphd-create-filter-btn">
				<?php esc_html_e( 'Add New Filter', 'wp-helpdesk' ); ?>
			</a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<?php settings_errors( 'wphd_queue_filters' ); ?>

			<div class="wphd-filters-management">
				<?php if ( ! empty( $user_filters ) ) : ?>
				<div class="wphd-filters-section">
					<h2><?php esc_html_e( 'My Filters', 'wp-helpdesk' ); ?></h2>
					<?php $this->render_filters_table( $user_filters, 'user' ); ?>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $org_filters ) ) : ?>
				<div class="wphd-filters-section" style="margin-top: 30px;">
					<h2><?php esc_html_e( 'Organization Filters', 'wp-helpdesk' ); ?></h2>
					<?php $this->render_filters_table( $org_filters, 'organization' ); ?>
				</div>
				<?php endif; ?>

				<?php if ( empty( $user_filters ) && empty( $org_filters ) ) : ?>
				<p><?php esc_html_e( 'No queue filters found. Create your first filter to get started.', 'wp-helpdesk' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Filter Modal -->
			<div id="wphd-filter-modal" class="wphd-modal" style="display: none;">
				<div class="wphd-modal-content">
					<span class="wphd-modal-close">&times;</span>
					<h2><?php echo $editing_filter ? esc_html__( 'Edit Filter', 'wp-helpdesk' ) : esc_html__( 'Create New Filter', 'wp-helpdesk' ); ?></h2>
					<?php $this->render_filter_form( $editing_filter ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render filters table.
	 *
	 * @since 1.0.0
	 * @param array  $filters Filters array.
	 * @param string $type    Filter type (user or organization).
	 */
	private function render_filters_table( $filters, $type ) {
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Description', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Sort', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Default', 'wp-helpdesk' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $filters as $filter ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $filter->name ); ?></strong>
					</td>
					<td><?php echo esc_html( $filter->description ); ?></td>
					<td>
						<?php
						// translators: %1$s: Sort field, %2$s: Sort order
						echo esc_html( sprintf( __( '%1$s (%2$s)', 'wp-helpdesk' ), ucfirst( $filter->sort_field ), $filter->sort_order ) );
						?>
					</td>
					<td>
						<?php if ( $filter->is_default ) : ?>
						<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
						<?php else : ?>
						<a href="#" class="wphd-set-default-btn" data-filter-id="<?php echo esc_attr( $filter->id ); ?>">
							<?php esc_html_e( 'Set as Default', 'wp-helpdesk' ); ?>
						</a>
						<?php endif; ?>
					</td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-helpdesk-tickets&filter_id=' . $filter->id ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Apply', 'wp-helpdesk' ); ?>
						</a>
						<?php if ( WPHD_Queue_Filters::can_edit_filter( $filter->id ) ) : ?>
						<a href="#" class="button button-small wphd-edit-filter-btn" data-filter-id="<?php echo esc_attr( $filter->id ); ?>">
							<?php esc_html_e( 'Edit', 'wp-helpdesk' ); ?>
						</a>
						<?php endif; ?>
						<?php if ( WPHD_Queue_Filters::can_delete_filter( $filter->id ) ) : ?>
						<a href="#" class="button button-small wphd-delete-filter-btn" data-filter-id="<?php echo esc_attr( $filter->id ); ?>">
							<?php esc_html_e( 'Delete', 'wp-helpdesk' ); ?>
						</a>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render filter form.
	 *
	 * @since 1.0.0
	 * @param object|null $filter Filter object or null for new filter.
	 */
	public function render_filter_form( $filter = null ) {
		$filter_config = $filter ? json_decode( $filter->filter_config, true ) : array();
		$statuses      = get_option( 'wphd_statuses', array() );
		$priorities    = get_option( 'wphd_priorities', array() );
		$categories    = get_option( 'wphd_categories', array() );

		// Get users for assignee selection
		$users = get_users( array( 'orderby' => 'display_name' ) );

		?>
		<form id="wphd-filter-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wphd_save_queue_filter">
			<?php wp_nonce_field( 'wphd_save_queue_filter', 'wphd_filter_nonce' ); ?>
			<?php if ( $filter ) : ?>
			<input type="hidden" name="filter_id" value="<?php echo esc_attr( $filter->id ); ?>">
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="filter_name"><?php esc_html_e( 'Filter Name', 'wp-helpdesk' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="text" name="filter_name" id="filter_name" class="regular-text" value="<?php echo $filter ? esc_attr( $filter->name ) : ''; ?>" required>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="filter_description"><?php esc_html_e( 'Description', 'wp-helpdesk' ); ?></label>
					</th>
					<td>
						<textarea name="filter_description" id="filter_description" class="large-text" rows="3"><?php echo $filter ? esc_textarea( $filter->description ) : ''; ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="filter_type"><?php esc_html_e( 'Filter Type', 'wp-helpdesk' ); ?></label>
					</th>
					<td>
						<select name="filter_type" id="filter_type">
							<option value="user" <?php selected( ! $filter || 'user' === $filter->filter_type ); ?>>
								<?php esc_html_e( 'Personal Filter', 'wp-helpdesk' ); ?>
							</option>
							<?php if ( WPHD_Access_Control::can_access( 'queue_filters_org_create' ) ) : ?>
							<option value="organization" <?php selected( $filter && 'organization' === $filter->filter_type ); ?>>
								<?php esc_html_e( 'Organization Filter', 'wp-helpdesk' ); ?>
							</option>
							<?php endif; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'wp-helpdesk' ); ?></th>
					<td>
						<select name="status[]" id="filter_status" class="wphd-select2" multiple style="width: 100%;">
							<?php foreach ( $statuses as $status ) : ?>
							<option value="<?php echo esc_attr( $status['slug'] ); ?>" <?php selected( in_array( $status['slug'], isset( $filter_config['status'] ) ? $filter_config['status'] : array(), true ) ); ?>>
								<?php echo esc_html( $status['name'] ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Priority', 'wp-helpdesk' ); ?></th>
					<td>
						<select name="priority[]" id="filter_priority" class="wphd-select2" multiple style="width: 100%;">
							<?php foreach ( $priorities as $priority ) : ?>
							<option value="<?php echo esc_attr( $priority['slug'] ); ?>" <?php selected( in_array( $priority['slug'], isset( $filter_config['priority'] ) ? $filter_config['priority'] : array(), true ) ); ?>>
								<?php echo esc_html( $priority['name'] ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Category', 'wp-helpdesk' ); ?></th>
					<td>
						<select name="category[]" id="filter_category" class="wphd-select2" multiple style="width: 100%;">
							<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category['slug'] ); ?>" <?php selected( in_array( $category['slug'], isset( $filter_config['category'] ) ? $filter_config['category'] : array(), true ) ); ?>>
								<?php echo esc_html( $category['name'] ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Assignee', 'wp-helpdesk' ); ?></th>
					<td>
						<?php
						$assignee_type = isset( $filter_config['assignee_type'] ) ? $filter_config['assignee_type'] : 'unassigned';
						$assignee_ids  = isset( $filter_config['assignee_ids'] ) ? $filter_config['assignee_ids'] : array();
						?>
						<div class="wphd-radio-group">
							<label>
								<input type="radio" name="assignee_type" value="unassigned" <?php checked( $assignee_type, 'unassigned' ); ?>>
								<?php esc_html_e( 'Unassigned', 'wp-helpdesk' ); ?>
							</label><br>
							<label>
								<input type="radio" name="assignee_type" value="me" <?php checked( $assignee_type, 'me' ); ?>>
								<?php esc_html_e( 'Assigned to me', 'wp-helpdesk' ); ?>
							</label><br>
							<label>
								<input type="radio" name="assignee_type" value="specific" <?php checked( $assignee_type, 'specific' ); ?>>
								<?php esc_html_e( 'Specific users', 'wp-helpdesk' ); ?>
							</label><br>
							<label>
								<input type="radio" name="assignee_type" value="any" <?php checked( $assignee_type, 'any' ); ?>>
								<?php esc_html_e( 'Any assignee', 'wp-helpdesk' ); ?>
							</label>
						</div>

						<div class="wphd-assignee-specific" style="margin-top: 10px; <?php echo 'specific' !== $assignee_type ? 'display: none;' : ''; ?>">
							<select name="assignee_ids[]" id="filter_assignee_ids" class="wphd-user-select2" multiple="multiple" style="width: 100%;">
								<?php
								// Pre-populate selected users.
								if ( ! empty( $assignee_ids ) ) {
									foreach ( $assignee_ids as $user_id ) {
										$user = get_userdata( $user_id );
										if ( $user ) {
											?>
											<option value="<?php echo esc_attr( $user->ID ); ?>" selected>
												<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
											</option>
											<?php
										}
									}
								}
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Type to search for users', 'wp-helpdesk' ); ?></p>
						</div>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Date Created', 'wp-helpdesk' ); ?></th>
					<td>
						<select name="date_created_operator" id="date_created_operator">
							<option value=""><?php esc_html_e( 'Any time', 'wp-helpdesk' ); ?></option>
							<option value="today" <?php selected( isset( $filter_config['date_created']['operator'] ) && 'today' === $filter_config['date_created']['operator'] ); ?>><?php esc_html_e( 'Today', 'wp-helpdesk' ); ?></option>
							<option value="yesterday" <?php selected( isset( $filter_config['date_created']['operator'] ) && 'yesterday' === $filter_config['date_created']['operator'] ); ?>><?php esc_html_e( 'Yesterday', 'wp-helpdesk' ); ?></option>
							<option value="this_week" <?php selected( isset( $filter_config['date_created']['operator'] ) && 'this_week' === $filter_config['date_created']['operator'] ); ?>><?php esc_html_e( 'This week', 'wp-helpdesk' ); ?></option>
							<option value="last_week" <?php selected( isset( $filter_config['date_created']['operator'] ) && 'last_week' === $filter_config['date_created']['operator'] ); ?>><?php esc_html_e( 'Last week', 'wp-helpdesk' ); ?></option>
							<option value="this_month" <?php selected( isset( $filter_config['date_created']['operator'] ) && 'this_month' === $filter_config['date_created']['operator'] ); ?>><?php esc_html_e( 'This month', 'wp-helpdesk' ); ?></option>
							<option value="last_month" <?php selected( isset( $filter_config['date_created']['operator'] ) && 'last_month' === $filter_config['date_created']['operator'] ); ?>><?php esc_html_e( 'Last month', 'wp-helpdesk' ); ?></option>
							<option value="between" <?php selected( isset( $filter_config['date_created']['operator'] ) && 'between' === $filter_config['date_created']['operator'] ); ?>><?php esc_html_e( 'Between dates', 'wp-helpdesk' ); ?></option>
						</select>
						<div id="date_created_range" style="margin-top: 10px; display: none;">
							<input type="date" name="date_created_start" value="<?php echo isset( $filter_config['date_created']['start'] ) ? esc_attr( $filter_config['date_created']['start'] ) : ''; ?>">
							<span><?php esc_html_e( 'to', 'wp-helpdesk' ); ?></span>
							<input type="date" name="date_created_end" value="<?php echo isset( $filter_config['date_created']['end'] ) ? esc_attr( $filter_config['date_created']['end'] ) : ''; ?>">
						</div>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Reporter', 'wp-helpdesk' ); ?></th>
					<td>
						<?php $reporter_ids = isset( $filter_config['reporter_ids'] ) ? $filter_config['reporter_ids'] : array(); ?>
						<select name="reporter_ids[]" id="filter_reporter_ids" class="wphd-user-select2" multiple="multiple" style="width: 100%;">
							<?php
							// Pre-populate selected users.
							if ( ! empty( $reporter_ids ) ) {
								foreach ( $reporter_ids as $user_id ) {
									$user = get_userdata( $user_id );
									if ( $user ) {
										?>
										<option value="<?php echo esc_attr( $user->ID ); ?>" selected>
											<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
										</option>
										<?php
									}
								}
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'Type to search for users who created tickets', 'wp-helpdesk' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Search', 'wp-helpdesk' ); ?></th>
					<td>
						<input type="text" name="search_phrase" id="search_phrase" class="regular-text" value="<?php echo isset( $filter_config['search_phrase'] ) ? esc_attr( $filter_config['search_phrase'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Search in title and content', 'wp-helpdesk' ); ?>">
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Sort', 'wp-helpdesk' ); ?></th>
					<td>
						<select name="sort_field" id="sort_field">
							<option value="date" <?php selected( ! $filter || 'date' === $filter->sort_field ); ?>><?php esc_html_e( 'Date Created', 'wp-helpdesk' ); ?></option>
							<option value="modified" <?php selected( $filter && 'modified' === $filter->sort_field ); ?>><?php esc_html_e( 'Last Modified', 'wp-helpdesk' ); ?></option>
							<option value="title" <?php selected( $filter && 'title' === $filter->sort_field ); ?>><?php esc_html_e( 'Title', 'wp-helpdesk' ); ?></option>
						</select>
						<select name="sort_order" id="sort_order">
							<option value="DESC" <?php selected( ! $filter || 'DESC' === $filter->sort_order ); ?>><?php esc_html_e( 'Descending', 'wp-helpdesk' ); ?></option>
							<option value="ASC" <?php selected( $filter && 'ASC' === $filter->sort_order ); ?>><?php esc_html_e( 'Ascending', 'wp-helpdesk' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Set as Default', 'wp-helpdesk' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="is_default" value="1" <?php checked( $filter && $filter->is_default ); ?>>
							<?php esc_html_e( 'Use this filter as my default view', 'wp-helpdesk' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo $filter ? esc_html__( 'Update Filter', 'wp-helpdesk' ) : esc_html__( 'Create Filter', 'wp-helpdesk' ); ?></button>
				<button type="button" class="button wphd-modal-close"><?php esc_html_e( 'Cancel', 'wp-helpdesk' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Handle save filter form submission.
	 *
	 * @since 1.0.0
	 */
	public function handle_save_filter() {
		// Verify nonce
		if ( ! isset( $_POST['wphd_filter_nonce'] ) || ! wp_verify_nonce( $_POST['wphd_filter_nonce'], 'wphd_save_queue_filter' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-helpdesk' ) );
		}

		$filter_id = isset( $_POST['filter_id'] ) ? absint( $_POST['filter_id'] ) : 0;

		// Check permissions
		if ( $filter_id ) {
			if ( ! WPHD_Queue_Filters::can_edit_filter( $filter_id ) ) {
				wp_die( esc_html__( 'You do not have permission to edit this filter.', 'wp-helpdesk' ) );
			}
		} else {
			$filter_type = isset( $_POST['filter_type'] ) ? sanitize_text_field( $_POST['filter_type'] ) : 'user';
			if ( 'organization' === $filter_type ) {
				if ( ! WPHD_Access_Control::can_access( 'queue_filters_org_create' ) ) {
					wp_die( esc_html__( 'You do not have permission to create organization filters.', 'wp-helpdesk' ) );
				}
			} else {
				if ( ! WPHD_Access_Control::can_access( 'queue_filters_user_create' ) ) {
					wp_die( esc_html__( 'You do not have permission to create personal filters.', 'wp-helpdesk' ) );
				}
			}
		}

		// Build filter config
		$filter_config = array();

		if ( ! empty( $_POST['status'] ) && is_array( $_POST['status'] ) ) {
			$filter_config['status'] = array_map( 'sanitize_text_field', $_POST['status'] );
		}

		if ( ! empty( $_POST['priority'] ) && is_array( $_POST['priority'] ) ) {
			$filter_config['priority'] = array_map( 'sanitize_text_field', $_POST['priority'] );
		}

		if ( ! empty( $_POST['category'] ) && is_array( $_POST['category'] ) ) {
			$filter_config['category'] = array_map( 'sanitize_text_field', $_POST['category'] );
		}

		if ( ! empty( $_POST['assignee_type'] ) ) {
			$filter_config['assignee_type'] = sanitize_text_field( $_POST['assignee_type'] );
			if ( 'specific' === $filter_config['assignee_type'] && ! empty( $_POST['assignee_ids'] ) && is_array( $_POST['assignee_ids'] ) ) {
				$filter_config['assignee_ids'] = array_map( 'absint', $_POST['assignee_ids'] );
			}
		}

		if ( ! empty( $_POST['date_created_operator'] ) ) {
			$filter_config['date_created'] = array(
				'operator' => sanitize_text_field( $_POST['date_created_operator'] ),
			);
			if ( 'between' === $filter_config['date_created']['operator'] ) {
				if ( ! empty( $_POST['date_created_start'] ) ) {
					$filter_config['date_created']['start'] = sanitize_text_field( $_POST['date_created_start'] );
				}
				if ( ! empty( $_POST['date_created_end'] ) ) {
					$filter_config['date_created']['end'] = sanitize_text_field( $_POST['date_created_end'] );
				}
			}
		}

		if ( ! empty( $_POST['reporter_ids'] ) && is_array( $_POST['reporter_ids'] ) ) {
			$filter_config['reporter_ids'] = array_map( 'absint', $_POST['reporter_ids'] );
		}

		if ( ! empty( $_POST['search_phrase'] ) ) {
			$filter_config['search_phrase'] = sanitize_text_field( $_POST['search_phrase'] );
		}

		// Prepare filter data
		$data = array(
			'name'          => sanitize_text_field( $_POST['filter_name'] ),
			'description'   => sanitize_textarea_field( $_POST['filter_description'] ),
			'filter_config' => wp_json_encode( $filter_config ),
			'sort_field'    => sanitize_text_field( $_POST['sort_field'] ),
			'sort_order'    => sanitize_text_field( $_POST['sort_order'] ),
			'is_default'    => isset( $_POST['is_default'] ) ? 1 : 0,
		);

		if ( ! $filter_id ) {
			$data['filter_type'] = isset( $_POST['filter_type'] ) ? sanitize_text_field( $_POST['filter_type'] ) : 'user';
			if ( 'organization' === $data['filter_type'] && class_exists( 'WPHD_Organizations' ) ) {
				$user_org = WPHD_Organizations::get_user_organization( get_current_user_id() );
				if ( $user_org ) {
					$data['organization_id'] = $user_org->id;
				}
			}
		}

		// Save filter
		if ( $filter_id ) {
			$result = WPHD_Queue_Filters::update( $filter_id, $data );
		} else {
			$result = WPHD_Queue_Filters::create( $data );
		}

		// Redirect with message
		$redirect_url = add_query_arg(
			array(
				'page' => 'wp-helpdesk-queue-filters',
			),
			admin_url( 'admin.php' )
		);

		if ( $result ) {
			add_settings_error(
				'wphd_queue_filters',
				'filter_saved',
				$filter_id ? __( 'Filter updated successfully.', 'wp-helpdesk' ) : __( 'Filter created successfully.', 'wp-helpdesk' ),
				'success'
			);
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			$redirect_url = add_query_arg( 'settings-updated', 'true', $redirect_url );
		} else {
			add_settings_error(
				'wphd_queue_filters',
				'filter_error',
				__( 'Failed to save filter. Please try again.', 'wp-helpdesk' ),
				'error'
			);
			set_transient( 'settings_errors', get_settings_errors(), 30 );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX handler for deleting a filter.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_filter() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		$filter_id = isset( $_POST['filter_id'] ) ? absint( $_POST['filter_id'] ) : 0;

		if ( ! $filter_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid filter ID.', 'wp-helpdesk' ) ) );
		}

		if ( ! WPHD_Queue_Filters::can_delete_filter( $filter_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete this filter.', 'wp-helpdesk' ) ) );
		}

		$result = WPHD_Queue_Filters::delete( $filter_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Filter deleted successfully.', 'wp-helpdesk' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete filter.', 'wp-helpdesk' ) ) );
		}
	}

	/**
	 * AJAX handler for setting a filter as default.
	 *
	 * @since 1.0.0
	 */
	public function ajax_set_default() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		$filter_id = isset( $_POST['filter_id'] ) ? absint( $_POST['filter_id'] ) : 0;

		if ( ! $filter_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid filter ID.', 'wp-helpdesk' ) ) );
		}

		if ( ! WPHD_Queue_Filters::can_edit_filter( $filter_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this filter.', 'wp-helpdesk' ) ) );
		}

		$result = WPHD_Queue_Filters::update( $filter_id, array( 'is_default' => 1 ) );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Filter set as default.', 'wp-helpdesk' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to set filter as default.', 'wp-helpdesk' ) ) );
		}
	}

	/**
	 * AJAX handler for previewing filter results.
	 *
	 * @since 1.0.0
	 */
	public function ajax_preview_filter() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		$filter_config = isset( $_POST['filter_config'] ) ? json_decode( wp_unslash( $_POST['filter_config'] ), true ) : array();

		if ( empty( $filter_config ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid filter configuration.', 'wp-helpdesk' ) ) );
		}

		$result = WPHD_Queue_Filter_Builder::get_tickets( $filter_config, 'date', 'DESC', 10, 1 );

		$html = '<ul>';
		foreach ( $result['tickets'] as $ticket ) {
			$html .= '<li><a href="' . esc_url( admin_url( 'admin.php?page=wp-helpdesk-tickets&ticket_id=' . $ticket->ID ) ) . '">#' . esc_html( $ticket->ID ) . ' - ' . esc_html( $ticket->post_title ) . '</a></li>';
		}
		$html .= '</ul>';

		if ( empty( $result['tickets'] ) ) {
			$html = '<p>' . esc_html__( 'No tickets match this filter.', 'wp-helpdesk' ) . '</p>';
		}

		wp_send_json_success( array(
			'html'  => $html,
			'count' => $result['total'],
		) );
	}

	/**
	 * AJAX handler for getting filter details.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_filter() {
		check_ajax_referer( 'wphd_nonce', 'nonce' );

		$filter_id = isset( $_POST['filter_id'] ) ? absint( $_POST['filter_id'] ) : 0;

		if ( ! $filter_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid filter ID.', 'wp-helpdesk' ) ) );
		}

		$filter = WPHD_Queue_Filters::get( $filter_id );

		if ( ! $filter ) {
			wp_send_json_error( array( 'message' => __( 'Filter not found.', 'wp-helpdesk' ) ) );
		}

		if ( ! WPHD_Queue_Filters::can_edit_filter( $filter_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this filter.', 'wp-helpdesk' ) ) );
		}

		$filter->filter_config = json_decode( $filter->filter_config, true );

		wp_send_json_success( $filter );
	}

	/**
	 * AJAX handler to search users for filter.
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_users() {
		check_ajax_referer( 'wphd_queue_filter_nonce', 'nonce' );

		if ( ! WPHD_Access_Control::can_access( 'queue_filters_user_create' ) &&
		     ! WPHD_Access_Control::can_access( 'queue_filters_org_create' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'wp-helpdesk' ) ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$users = get_users(
			array(
				'search'         => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'number'         => 20,
				'orderby'        => 'display_name',
				'order'          => 'ASC',
			)
		);

		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'   => $user->ID,
				'text' => $user->display_name . ' (' . $user->user_email . ')',
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}
}

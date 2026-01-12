<?php
/**
 * Custom Fields Admin Page
 *
 * Handles the admin interface for managing custom ticket fields.
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
 * Class WPHD_Custom_Fields_Page
 *
 * Manages the custom fields admin interface.
 *
 * @since 1.0.0
 */
class WPHD_Custom_Fields_Page {

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    WPHD_Custom_Fields_Page
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @since  1.0.0
	 * @return WPHD_Custom_Fields_Page
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
		add_action( 'admin_post_wphd_save_custom_fields', array( $this, 'handle_save_custom_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue custom fields admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'help-desk_page_wp-helpdesk-custom-fields' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wp-helpdesk-custom-fields',
			WPHD_PLUGIN_URL . 'assets/css/custom-fields.css',
			array(),
			WPHD_VERSION
		);

		wp_enqueue_script(
			'wp-helpdesk-custom-fields',
			WPHD_PLUGIN_URL . 'assets/js/custom-fields.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			WPHD_VERSION,
			true
		);

		wp_localize_script(
			'wp-helpdesk-custom-fields',
			'wphdCustomFields',
			array(
				'nonce'   => wp_create_nonce( 'wphd_custom_fields_nonce' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Are you sure you want to delete this custom field? This action cannot be undone.', 'wp-helpdesk' ),
					'fieldKey'      => __( 'Field key must be lowercase letters, numbers, and underscores only.', 'wp-helpdesk' ),
				),
			)
		);
	}

	/**
	 * Render the custom fields page.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-helpdesk' ) );
		}

		$custom_fields = get_option( 'wphd_custom_fields', array() );

		// Sort by order
		if ( ! empty( $custom_fields ) ) {
			uasort(
				$custom_fields,
				function( $a, $b ) {
					$order_a = isset( $a['order'] ) ? intval( $a['order'] ) : 0;
					$order_b = isset( $b['order'] ) ? intval( $b['order'] ) : 0;
					return $order_a - $order_b;
				}
			);
		}

		?>
		<div class="wrap wp-helpdesk-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Custom Fields', 'wp-helpdesk' ); ?></h1>
			<button type="button" class="page-title-action" id="wphd-add-custom-field">
				<?php esc_html_e( 'Add Custom Field', 'wp-helpdesk' ); ?>
			</button>
			<hr class="wp-header-end">

			<?php settings_errors( 'wphd_custom_fields' ); ?>

			<p><?php esc_html_e( 'Custom fields allow you to add additional information to tickets. Configure field types, display locations, and more.', 'wp-helpdesk' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wphd-custom-fields-form">
				<input type="hidden" name="action" value="wphd_save_custom_fields">
				<?php wp_nonce_field( 'wphd_save_custom_fields', 'wphd_custom_fields_nonce' ); ?>

				<table class="wp-list-table widefat fixed striped" id="wphd-custom-fields-table">
					<thead>
						<tr>
							<th style="width: 40px;"><?php esc_html_e( 'Order', 'wp-helpdesk' ); ?></th>
							<th style="width: 60px;"><?php esc_html_e( 'Enabled', 'wp-helpdesk' ); ?></th>
							<th style="width: 200px;"><?php esc_html_e( 'Field Label', 'wp-helpdesk' ); ?> <span class="required">*</span></th>
							<th style="width: 150px;"><?php esc_html_e( 'Field Key', 'wp-helpdesk' ); ?> <span class="required">*</span></th>
							<th style="width: 150px;"><?php esc_html_e( 'Type', 'wp-helpdesk' ); ?> <span class="required">*</span></th>
							<th style="width: 150px;"><?php esc_html_e( 'Display Location', 'wp-helpdesk' ); ?></th>
							<th style="width: 60px;"><?php esc_html_e( 'Required', 'wp-helpdesk' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'Actions', 'wp-helpdesk' ); ?></th>
						</tr>
					</thead>
					<tbody id="wphd-custom-fields-tbody">
						<?php if ( empty( $custom_fields ) ) : ?>
							<tr class="wphd-no-fields-row">
								<td colspan="8" style="text-align: center; padding: 40px;">
									<?php esc_html_e( 'No custom fields yet. Click "Add Custom Field" to create one.', 'wp-helpdesk' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $custom_fields as $field_key => $field_data ) : ?>
								<?php $this->render_field_row( $field_key, $field_data ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Save All Custom Fields', 'wp-helpdesk' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Field Row Template (hidden) -->
		<script type="text/template" id="wphd-field-row-template">
			<?php $this->render_field_row( '__FIELD_KEY__', array(), true ); ?>
		</script>
		<?php
	}

	/**
	 * Render a single field row.
	 *
	 * @since 1.0.0
	 * @param string $field_key  Field key.
	 * @param array  $field_data Field data.
	 * @param bool   $is_template Whether this is a template row.
	 */
	private function render_field_row( $field_key, $field_data = array(), $is_template = false ) {
		$enabled          = isset( $field_data['enabled'] ) ? (bool) $field_data['enabled'] : true;
		$label            = isset( $field_data['label'] ) ? $field_data['label'] : '';
		$type             = isset( $field_data['type'] ) ? $field_data['type'] : 'text';
		$display_location = isset( $field_data['display_location'] ) ? $field_data['display_location'] : 'flex1';
		$required         = isset( $field_data['required'] ) ? (bool) $field_data['required'] : false;
		$order            = isset( $field_data['order'] ) ? intval( $field_data['order'] ) : 0;
		$options          = isset( $field_data['options'] ) ? $field_data['options'] : array();

		$row_class = $is_template ? 'wphd-field-row-template' : 'wphd-field-row';
		$key_attr  = $is_template ? '__FIELD_KEY__' : esc_attr( $field_key );
		$key_value = $is_template ? '' : esc_attr( $field_key );

		?>
		<tr class="<?php echo esc_attr( $row_class ); ?>" data-field-key="<?php echo $key_attr; ?>">
			<td class="wphd-drag-handle" style="cursor: move; text-align: center;">
				<span class="dashicons dashicons-menu"></span>
				<input type="hidden" name="fields[<?php echo $key_attr; ?>][order]" value="<?php echo esc_attr( $order ); ?>" class="wphd-field-order">
			</td>
			<td style="text-align: center;">
				<input type="checkbox" name="fields[<?php echo $key_attr; ?>][enabled]" value="1" <?php checked( $enabled, true ); ?>>
			</td>
			<td>
				<input type="text" name="fields[<?php echo $key_attr; ?>][label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text wphd-field-label" placeholder="<?php esc_attr_e( 'e.g., Department', 'wp-helpdesk' ); ?>" required>
			</td>
			<td>
				<input type="text" name="fields[<?php echo $key_attr; ?>][key]" value="<?php echo $key_value; ?>" class="regular-text wphd-field-key" placeholder="<?php esc_attr_e( 'e.g., department', 'wp-helpdesk' ); ?>" pattern="[a-z0-9_]+" <?php echo $is_template ? '' : 'readonly'; ?> required>
				<?php if ( ! $is_template ) : ?>
					<p class="description"><?php esc_html_e( 'Key cannot be changed after creation', 'wp-helpdesk' ); ?></p>
				<?php endif; ?>
			</td>
			<td>
				<select name="fields[<?php echo $key_attr; ?>][type]" class="wphd-field-type" required>
					<option value="dropdown" <?php selected( $type, 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'wp-helpdesk' ); ?></option>
					<option value="text" <?php selected( $type, 'text' ); ?>><?php esc_html_e( 'Short Text', 'wp-helpdesk' ); ?></option>
					<option value="textarea" <?php selected( $type, 'textarea' ); ?>><?php esc_html_e( 'Rich Text', 'wp-helpdesk' ); ?></option>
					<option value="checkbox" <?php selected( $type, 'checkbox' ); ?>><?php esc_html_e( 'Checkbox', 'wp-helpdesk' ); ?></option>
					<option value="date" <?php selected( $type, 'date' ); ?>><?php esc_html_e( 'Date', 'wp-helpdesk' ); ?></option>
					<option value="url" <?php selected( $type, 'url' ); ?>><?php esc_html_e( 'URL', 'wp-helpdesk' ); ?></option>
					<option value="user_mention" <?php selected( $type, 'user_mention' ); ?>><?php esc_html_e( 'User Mention', 'wp-helpdesk' ); ?></option>
				</select>
			</td>
			<td>
				<select name="fields[<?php echo $key_attr; ?>][display_location]" required>
					<option value="flex1" <?php selected( $display_location, 'flex1' ); ?>><?php esc_html_e( 'Flex Column 1', 'wp-helpdesk' ); ?></option>
					<option value="flex2" <?php selected( $display_location, 'flex2' ); ?>><?php esc_html_e( 'Flex Column 2', 'wp-helpdesk' ); ?></option>
					<option value="none" <?php selected( $display_location, 'none' ); ?>><?php esc_html_e( 'Hidden', 'wp-helpdesk' ); ?></option>
				</select>
			</td>
			<td style="text-align: center;">
				<input type="checkbox" name="fields[<?php echo $key_attr; ?>][required]" value="1" <?php checked( $required, true ); ?>>
			</td>
			<td>
				<button type="button" class="button button-small wphd-configure-options-btn" data-field-key="<?php echo $key_attr; ?>" <?php echo 'dropdown' === $type ? '' : 'style="display:none;"'; ?>>
					<?php esc_html_e( 'Options', 'wp-helpdesk' ); ?>
				</button>
				<button type="button" class="button button-small button-link-delete wphd-delete-field-btn" data-field-key="<?php echo $key_attr; ?>">
					<?php esc_html_e( 'Delete', 'wp-helpdesk' ); ?>
				</button>
			</td>
		</tr>
		<tr class="wphd-field-options-row" data-field-key="<?php echo $key_attr; ?>" <?php echo 'dropdown' !== $type ? 'style="display:none;"' : ''; ?>>
			<td colspan="8" style="padding-left: 60px; background: #f9f9f9;">
				<h4><?php esc_html_e( 'Dropdown Options', 'wp-helpdesk' ); ?></h4>
				<div class="wphd-dropdown-options">
					<?php if ( ! empty( $options ) ) : ?>
						<?php foreach ( $options as $index => $option ) : ?>
							<div class="wphd-dropdown-option">
								<input type="text" name="fields[<?php echo $key_attr; ?>][options][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo isset( $option['label'] ) ? esc_attr( $option['label'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Option Label', 'wp-helpdesk' ); ?>" class="regular-text">
								<input type="text" name="fields[<?php echo $key_attr; ?>][options][<?php echo esc_attr( $index ); ?>][value]" value="<?php echo isset( $option['value'] ) ? esc_attr( $option['value'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'value', 'wp-helpdesk' ); ?>" class="regular-text">
								<button type="button" class="button button-small wphd-remove-option-btn"><?php esc_html_e( 'Remove', 'wp-helpdesk' ); ?></button>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="wphd-dropdown-option">
							<input type="text" name="fields[<?php echo $key_attr; ?>][options][0][label]" placeholder="<?php esc_attr_e( 'Option Label', 'wp-helpdesk' ); ?>" class="regular-text">
							<input type="text" name="fields[<?php echo $key_attr; ?>][options][0][value]" placeholder="<?php esc_attr_e( 'value', 'wp-helpdesk' ); ?>" class="regular-text">
							<button type="button" class="button button-small wphd-remove-option-btn"><?php esc_html_e( 'Remove', 'wp-helpdesk' ); ?></button>
						</div>
					<?php endif; ?>
				</div>
				<button type="button" class="button button-small wphd-add-option-btn" data-field-key="<?php echo $key_attr; ?>">
					<?php esc_html_e( '+ Add Option', 'wp-helpdesk' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Handle save custom fields form submission.
	 *
	 * @since 1.0.0
	 */
	public function handle_save_custom_fields() {
		// Check nonce
		if ( ! isset( $_POST['wphd_custom_fields_nonce'] ) || ! wp_verify_nonce( $_POST['wphd_custom_fields_nonce'], 'wphd_save_custom_fields' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-helpdesk' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save custom fields.', 'wp-helpdesk' ) );
		}

		$fields = isset( $_POST['fields'] ) ? $_POST['fields'] : array();
		$custom_fields = array();

		foreach ( $fields as $field_key => $field_data ) {
			// Skip template rows
			if ( '__FIELD_KEY__' === $field_key ) {
				continue;
			}

			// Validate field key
			if ( empty( $field_data['key'] ) || ! preg_match( '/^[a-z0-9_]+$/', $field_data['key'] ) ) {
				add_settings_error(
					'wphd_custom_fields',
					'invalid_key',
					sprintf(
						/* translators: %s: Field label */
						__( 'Field "%s" has an invalid key. Keys must be lowercase letters, numbers, and underscores only.', 'wp-helpdesk' ),
						sanitize_text_field( $field_data['label'] )
					),
					'error'
				);
				continue;
			}

			// Use the field key from the data (not the loop key which might be old)
			$actual_key = sanitize_key( $field_data['key'] );

			// Validate label
			if ( empty( $field_data['label'] ) ) {
				add_settings_error(
					'wphd_custom_fields',
					'missing_label',
					sprintf(
						/* translators: %s: Field key */
						__( 'Field "%s" is missing a label.', 'wp-helpdesk' ),
						$actual_key
					),
					'error'
				);
				continue;
			}

			// Sanitize and validate field data
			$custom_fields[ $actual_key ] = array(
				'enabled'          => isset( $field_data['enabled'] ),
				'label'            => sanitize_text_field( $field_data['label'] ),
				'type'             => sanitize_text_field( $field_data['type'] ),
				'display_location' => sanitize_text_field( $field_data['display_location'] ),
				'required'         => isset( $field_data['required'] ),
				'order'            => isset( $field_data['order'] ) ? intval( $field_data['order'] ) : 0,
			);

			// Handle dropdown options
			if ( 'dropdown' === $field_data['type'] && isset( $field_data['options'] ) && is_array( $field_data['options'] ) ) {
				$options = array();
				foreach ( $field_data['options'] as $option_data ) {
					if ( ! empty( $option_data['label'] ) && ! empty( $option_data['value'] ) ) {
						$options[] = array(
							'label' => sanitize_text_field( $option_data['label'] ),
							'value' => sanitize_key( $option_data['value'] ),
						);
					}
				}
				$custom_fields[ $actual_key ]['options'] = $options;
			}
		}

		// Save to database
		update_option( 'wphd_custom_fields', $custom_fields );

		// Add success message
		add_settings_error(
			'wphd_custom_fields',
			'fields_saved',
			__( 'Custom fields saved successfully.', 'wp-helpdesk' ),
			'success'
		);

		// Redirect back to the custom fields page
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=wp-helpdesk-custom-fields&settings-updated=true' ) );
		exit;
	}
}

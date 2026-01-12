<?php
/**
 * Field Registry Class
 *
 * Single source of truth for all ticket fields (core and custom).
 * Provides field definitions, operators, and options for filters, forms, and reports.
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
 * Class WPHD_Field_Registry
 *
 * Central registry for all ticket fields.
 *
 * @since 1.0.0
 */
class WPHD_Field_Registry {

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    WPHD_Field_Registry
	 */
	private static $instance = null;

	/**
	 * Registered fields.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $fields = array();

	/**
	 * Get the singleton instance of this class.
	 *
	 * @since  1.0.0
	 * @return WPHD_Field_Registry
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
		$this->register_core_fields();
		$this->register_custom_fields();
	}

	/**
	 * Register core ticket fields.
	 *
	 * @since 1.0.0
	 */
	private function register_core_fields() {
		// Status field.
		$this->register_field(
			'status',
			array(
				'label'       => __( 'Status', 'wp-helpdesk' ),
				'type'        => 'select',
				'meta_key'    => '_wphd_status',
				'data_source' => 'wphd_statuses',
				'operators'   => array( 'in', 'not_in', 'equals', 'not_equals' ),
				'filterable'  => true,
				'sortable'    => true,
				'searchable'  => false,
				'custom'      => false,
			)
		);

		// Priority field.
		$this->register_field(
			'priority',
			array(
				'label'       => __( 'Priority', 'wp-helpdesk' ),
				'type'        => 'select',
				'meta_key'    => '_wphd_priority',
				'data_source' => 'wphd_priorities',
				'operators'   => array( 'in', 'not_in', 'equals', 'not_equals' ),
				'filterable'  => true,
				'sortable'    => true,
				'searchable'  => false,
				'custom'      => false,
			)
		);

		// Category field.
		$this->register_field(
			'category',
			array(
				'label'       => __( 'Category', 'wp-helpdesk' ),
				'type'        => 'select',
				'meta_key'    => '_wphd_category',
				'data_source' => 'wphd_categories',
				'operators'   => array( 'in', 'not_in', 'equals', 'not_equals' ),
				'filterable'  => true,
				'sortable'    => true,
				'searchable'  => false,
				'custom'      => false,
			)
		);

		// Assignee field.
		$this->register_field(
			'assignee',
			array(
				'label'       => __( 'Assignee', 'wp-helpdesk' ),
				'type'        => 'user_select',
				'meta_key'    => '_wphd_assignee',
				'data_source' => 'users',
				'operators'   => array( 'in', 'not_in', 'equals', 'not_equals', 'empty', 'not_empty' ),
				'filterable'  => true,
				'sortable'    => false,
				'searchable'  => false,
				'custom'      => false,
			)
		);

		// Reporter field (ticket author).
		$this->register_field(
			'reporter',
			array(
				'label'       => __( 'Reporter', 'wp-helpdesk' ),
				'type'        => 'user_select',
				'post_field'  => 'post_author',
				'data_source' => 'users',
				'operators'   => array( 'in', 'not_in', 'equals', 'not_equals' ),
				'filterable'  => true,
				'sortable'    => false,
				'searchable'  => false,
				'custom'      => false,
			)
		);

		// Date created field.
		$this->register_field(
			'date_created',
			array(
				'label'       => __( 'Date Created', 'wp-helpdesk' ),
				'type'        => 'date',
				'post_field'  => 'post_date',
				'operators'   => array( 'equals', 'not_equals', 'before', 'after', 'between', 'today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month' ),
				'filterable'  => true,
				'sortable'    => true,
				'searchable'  => false,
				'custom'      => false,
			)
		);

		// Date modified field.
		$this->register_field(
			'date_modified',
			array(
				'label'       => __( 'Date Modified', 'wp-helpdesk' ),
				'type'        => 'date',
				'post_field'  => 'post_modified',
				'operators'   => array( 'equals', 'not_equals', 'before', 'after', 'between', 'today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month' ),
				'filterable'  => true,
				'sortable'    => true,
				'searchable'  => false,
				'custom'      => false,
			)
		);

		// Title field.
		$this->register_field(
			'title',
			array(
				'label'       => __( 'Title', 'wp-helpdesk' ),
				'type'        => 'text',
				'post_field'  => 'post_title',
				'operators'   => array( 'contains', 'not_contains', 'equals', 'not_equals', 'starts_with', 'ends_with' ),
				'filterable'  => true,
				'sortable'    => true,
				'searchable'  => true,
				'custom'      => false,
			)
		);

		// Content field.
		$this->register_field(
			'content',
			array(
				'label'       => __( 'Content', 'wp-helpdesk' ),
				'type'        => 'textarea',
				'post_field'  => 'post_content',
				'operators'   => array( 'contains', 'not_contains' ),
				'filterable'  => true,
				'sortable'    => false,
				'searchable'  => true,
				'custom'      => false,
			)
		);
	}

	/**
	 * Register custom fields from the database.
	 *
	 * @since 1.0.0
	 */
	private function register_custom_fields() {
		$custom_fields = get_option( 'wphd_custom_fields', array() );

		if ( empty( $custom_fields ) || ! is_array( $custom_fields ) ) {
			return;
		}

		foreach ( $custom_fields as $field_key => $field_data ) {
			// Skip if field is disabled.
			if ( isset( $field_data['enabled'] ) && ! $field_data['enabled'] ) {
				continue;
			}

			$field_type = isset( $field_data['type'] ) ? $field_data['type'] : 'text';
			$operators  = $this->get_operators_for_type( $field_type );

			$this->register_field(
				$field_key,
				array(
					'label'       => isset( $field_data['label'] ) ? $field_data['label'] : $field_key,
					'type'        => $this->map_custom_field_type( $field_type ),
					'meta_key'    => '_wphd_custom_' . $field_key,
					'data_source' => ( 'dropdown' === $field_type && isset( $field_data['options'] ) ) ? $field_data['options'] : null,
					'operators'   => $operators,
					'filterable'  => true,
					'sortable'    => true,
					'searchable'  => in_array( $field_type, array( 'text', 'textarea' ), true ),
					'custom'      => true,
					'custom_data' => $field_data,
				)
			);
		}
	}

	/**
	 * Map custom field type to registry type.
	 *
	 * @since  1.0.0
	 * @param  string $custom_type Custom field type.
	 * @return string Registry field type.
	 */
	private function map_custom_field_type( $custom_type ) {
		$type_map = array(
			'dropdown'     => 'select',
			'text'         => 'text',
			'textarea'     => 'textarea',
			'checkbox'     => 'checkbox',
			'date'         => 'date',
			'url'          => 'url',
			'user_mention' => 'user_select',
		);

		return isset( $type_map[ $custom_type ] ) ? $type_map[ $custom_type ] : 'text';
	}

	/**
	 * Get operators for a field type.
	 *
	 * @since  1.0.0
	 * @param  string $type Field type.
	 * @return array Operators.
	 */
	private function get_operators_for_type( $type ) {
		$operators_map = array(
			'select'       => array( 'in', 'not_in', 'equals', 'not_equals', 'empty', 'not_empty' ),
			'text'         => array( 'contains', 'not_contains', 'equals', 'not_equals', 'starts_with', 'ends_with', 'empty', 'not_empty' ),
			'textarea'     => array( 'contains', 'not_contains', 'empty', 'not_empty' ),
			'date'         => array( 'equals', 'not_equals', 'before', 'after', 'between', 'empty', 'not_empty' ),
			'checkbox'     => array( 'equals', 'not_equals' ),
			'user_select'  => array( 'in', 'not_in', 'equals', 'not_equals', 'empty', 'not_empty' ),
			'url'          => array( 'contains', 'not_contains', 'equals', 'not_equals', 'empty', 'not_empty' ),
			'dropdown'     => array( 'in', 'not_in', 'equals', 'not_equals', 'empty', 'not_empty' ),
		);

		return isset( $operators_map[ $type ] ) ? $operators_map[ $type ] : array( 'equals', 'not_equals' );
	}

	/**
	 * Register a field.
	 *
	 * @since  1.0.0
	 * @param  string $key  Field key.
	 * @param  array  $args Field arguments.
	 * @return bool True on success, false on failure.
	 */
	public function register_field( $key, $args ) {
		// Validate required arguments.
		if ( empty( $key ) || empty( $args['label'] ) || empty( $args['type'] ) ) {
			return false;
		}

		// Default values.
		$defaults = array(
			'label'       => '',
			'type'        => 'text',
			'meta_key'    => null,
			'post_field'  => null,
			'data_source' => null,
			'operators'   => array( 'equals', 'not_equals' ),
			'filterable'  => true,
			'sortable'    => false,
			'searchable'  => false,
			'custom'      => false,
			'custom_data' => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Store field.
		$this->fields[ $key ] = $args;

		return true;
	}

	/**
	 * Get all registered fields.
	 *
	 * @since  1.0.0
	 * @return array Fields.
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Get filterable fields.
	 *
	 * @since  1.0.0
	 * @return array Filterable fields.
	 */
	public function get_filterable_fields() {
		return array_filter(
			$this->fields,
			function( $field ) {
				return ! empty( $field['filterable'] );
			}
		);
	}

	/**
	 * Get sortable fields.
	 *
	 * @since  1.0.0
	 * @return array Sortable fields.
	 */
	public function get_sortable_fields() {
		return array_filter(
			$this->fields,
			function( $field ) {
				return ! empty( $field['sortable'] );
			}
		);
	}

	/**
	 * Get searchable fields.
	 *
	 * @since  1.0.0
	 * @return array Searchable fields.
	 */
	public function get_searchable_fields() {
		return array_filter(
			$this->fields,
			function( $field ) {
				return ! empty( $field['searchable'] );
			}
		);
	}

	/**
	 * Get custom fields.
	 *
	 * @since  1.0.0
	 * @return array Custom fields.
	 */
	public function get_custom_fields() {
		return array_filter(
			$this->fields,
			function( $field ) {
				return ! empty( $field['custom'] );
			}
		);
	}

	/**
	 * Get a specific field.
	 *
	 * @since  1.0.0
	 * @param  string $key Field key.
	 * @return array|null Field definition or null if not found.
	 */
	public function get_field( $key ) {
		return isset( $this->fields[ $key ] ) ? $this->fields[ $key ] : null;
	}

	/**
	 * Get field options.
	 *
	 * @since  1.0.0
	 * @param  string $key Field key.
	 * @return array Field options.
	 */
	public function get_field_options( $key ) {
		$field = $this->get_field( $key );

		if ( ! $field || empty( $field['data_source'] ) ) {
			return array();
		}

		$data_source = $field['data_source'];

		// If data_source is an array, return it directly (custom field options).
		if ( is_array( $data_source ) ) {
			return $data_source;
		}

		// Handle option-based data sources.
		if ( in_array( $data_source, array( 'wphd_statuses', 'wphd_priorities', 'wphd_categories' ), true ) ) {
			$options = get_option( $data_source, array() );
			$result  = array();

			foreach ( $options as $option ) {
				$result[] = array(
					'value' => $option['slug'],
					'label' => $option['name'],
				);
			}

			return $result;
		}

		// Handle users data source.
		if ( 'users' === $data_source ) {
			$users  = get_users( array( 'orderby' => 'display_name' ) );
			$result = array();

			foreach ( $users as $user ) {
				$result[] = array(
					'value' => $user->ID,
					'label' => $user->display_name . ' (' . $user->user_email . ')',
				);
			}

			return $result;
		}

		return array();
	}

	/**
	 * Get field operators.
	 *
	 * @since  1.0.0
	 * @param  string $key Field key.
	 * @return array Operators.
	 */
	public function get_field_operators( $key ) {
		$field = $this->get_field( $key );

		if ( ! $field || empty( $field['operators'] ) ) {
			return array();
		}

		return $field['operators'];
	}

	/**
	 * Get operator labels.
	 *
	 * @since  1.0.0
	 * @return array Operator labels.
	 */
	public function get_operator_labels() {
		return array(
			'equals'       => __( 'Equals', 'wp-helpdesk' ),
			'not_equals'   => __( 'Not Equals', 'wp-helpdesk' ),
			'in'           => __( 'In', 'wp-helpdesk' ),
			'not_in'       => __( 'Not In', 'wp-helpdesk' ),
			'contains'     => __( 'Contains', 'wp-helpdesk' ),
			'not_contains' => __( 'Does Not Contain', 'wp-helpdesk' ),
			'starts_with'  => __( 'Starts With', 'wp-helpdesk' ),
			'ends_with'    => __( 'Ends With', 'wp-helpdesk' ),
			'before'       => __( 'Before', 'wp-helpdesk' ),
			'after'        => __( 'After', 'wp-helpdesk' ),
			'between'      => __( 'Between', 'wp-helpdesk' ),
			'empty'        => __( 'Is Empty', 'wp-helpdesk' ),
			'not_empty'    => __( 'Is Not Empty', 'wp-helpdesk' ),
			'today'        => __( 'Today', 'wp-helpdesk' ),
			'yesterday'    => __( 'Yesterday', 'wp-helpdesk' ),
			'last_7_days'  => __( 'Last 7 Days', 'wp-helpdesk' ),
			'last_30_days' => __( 'Last 30 Days', 'wp-helpdesk' ),
			'this_month'   => __( 'This Month', 'wp-helpdesk' ),
			'last_month'   => __( 'Last Month', 'wp-helpdesk' ),
		);
	}

	/**
	 * Check if a field exists.
	 *
	 * @since  1.0.0
	 * @param  string $key Field key.
	 * @return bool True if field exists, false otherwise.
	 */
	public function field_exists( $key ) {
		return isset( $this->fields[ $key ] );
	}
}

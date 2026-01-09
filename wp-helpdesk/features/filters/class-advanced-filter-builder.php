<?php
/**
 * Advanced Filter Builder Class
 *
 * Handles building complex queries from filter configurations
 * with support for multiple criteria, logical operators, and real-time preview
 *
 * @package     WP_HelpDesk
 * @subpackage  Features/Filters
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPHD_Advanced_Filter_Builder
 *
 * Builds advanced WP_Query arguments from filter configuration.
 *
 * @since 1.0.0
 */
class WPHD_Advanced_Filter_Builder {

	/**
	 * Instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    WPHD_Advanced_Filter_Builder
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @since  1.0.0
	 * @return WPHD_Advanced_Filter_Builder
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Build query from advanced filter configuration.
	 *
	 * @since  1.0.0
	 * @param  array  $config       Filter configuration.
	 * @param  int    $per_page     Number of results per page.
	 * @param  int    $page         Current page number.
	 * @param  bool   $count_only   Whether to return only count.
	 * @return array|int            Query results or count.
	 */
	public static function execute_filter( $config, $per_page = 20, $page = 1, $count_only = false ) {
		$query_args = self::build_query_args( $config );

		if ( $count_only ) {
			$query_args['posts_per_page'] = -1;
			$query_args['fields'] = 'ids';
			$query = new WP_Query( $query_args );
			return $query->found_posts;
		}

		$query_args['posts_per_page'] = $per_page;
		$query_args['paged'] = $page;

		$query = new WP_Query( $query_args );

		return array(
			'tickets'      => $query->posts,
			'total'        => $query->found_posts,
			'pages'        => $query->max_num_pages,
			'current_page' => $page,
		);
	}

	/**
	 * Build WP_Query arguments from filter configuration.
	 *
	 * @since  1.0.0
	 * @param  array $config Filter configuration.
	 * @return array WP_Query arguments.
	 */
	public static function build_query_args( $config ) {
		$args = array(
			'post_type'      => 'wphd_ticket',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		// Apply organization-based visibility
		if ( class_exists( 'WPHD_Organization_Permissions' ) ) {
			$visible_ticket_ids = WPHD_Organization_Permissions::get_visible_ticket_ids();
			if ( 'all' !== $visible_ticket_ids ) {
				if ( empty( $visible_ticket_ids ) ) {
					$args['post__in'] = array( 0 ); // No results
				} else {
					$args['post__in'] = $visible_ticket_ids;
				}
			}
		}

		// Process filter groups
		if ( ! empty( $config['groups'] ) && is_array( $config['groups'] ) ) {
			$meta_query = self::build_meta_query( $config['groups'] );
			if ( ! empty( $meta_query ) ) {
				$args['meta_query'] = $meta_query;
			}

			// Handle date queries
			$date_query = self::build_date_query( $config['groups'] );
			if ( ! empty( $date_query ) ) {
				$args['date_query'] = $date_query;
			}

			// Handle text search
			$search = self::extract_search_query( $config['groups'] );
			if ( ! empty( $search ) ) {
				$args['s'] = $search;
			}
		}

		// Apply sorting
		if ( ! empty( $config['sort'] ) ) {
			$sort_field = sanitize_text_field( $config['sort']['field'] ?? 'created_at' );
			$sort_order = strtoupper( $config['sort']['order'] ?? 'DESC' );
			$sort_order = in_array( $sort_order, array( 'ASC', 'DESC' ), true ) ? $sort_order : 'DESC';

			switch ( $sort_field ) {
				case 'created_at':
					$args['orderby'] = 'date';
					$args['order'] = $sort_order;
					break;
				case 'modified_at':
					$args['orderby'] = 'modified';
					$args['order'] = $sort_order;
					break;
				case 'title':
					$args['orderby'] = 'title';
					$args['order'] = $sort_order;
					break;
				default:
					$args['orderby'] = 'date';
					$args['order'] = 'DESC';
			}
		} else {
			$args['orderby'] = 'date';
			$args['order'] = 'DESC';
		}

		return $args;
	}

	/**
	 * Build meta query from filter groups.
	 *
	 * @since  1.0.0
	 * @param  array $groups Filter groups.
	 * @return array Meta query array.
	 */
	private static function build_meta_query( $groups ) {
		$meta_query = array();
		$has_conditions = false;

		foreach ( $groups as $group ) {
			if ( empty( $group['conditions'] ) || ! is_array( $group['conditions'] ) ) {
				continue;
			}

			$group_logic = ! empty( $group['logic'] ) ? strtoupper( $group['logic'] ) : 'AND';
			$group_logic = in_array( $group_logic, array( 'AND', 'OR' ), true ) ? $group_logic : 'AND';

			$group_conditions = array( 'relation' => $group_logic );

			foreach ( $group['conditions'] as $condition ) {
				if ( empty( $condition['field'] ) ) {
					continue;
				}

				$field = sanitize_text_field( $condition['field'] );
				$operator = ! empty( $condition['operator'] ) ? $condition['operator'] : 'equals';
				$value = $condition['value'] ?? '';

				// Skip date fields (handled separately)
				if ( in_array( $field, array( 'created_date', 'modified_date', 'resolved_date', 'due_date' ), true ) ) {
					continue;
				}

				// Skip text search (handled separately)
				if ( 'text_search' === $field ) {
					continue;
				}

				$meta_condition = self::build_meta_condition( $field, $operator, $value );
				if ( ! empty( $meta_condition ) ) {
					$group_conditions[] = $meta_condition;
					$has_conditions = true;
				}
			}

			if ( count( $group_conditions ) > 1 ) {
				$meta_query[] = $group_conditions;
			}
		}

		if ( ! $has_conditions ) {
			return array();
		}

		// If multiple groups, wrap with OR logic
		if ( count( $meta_query ) > 1 ) {
			array_unshift( $meta_query, array( 'relation' => 'OR' ) );
		}

		return $meta_query;
	}

	/**
	 * Build a single meta condition.
	 *
	 * @since  1.0.0
	 * @param  string $field    Field name.
	 * @param  string $operator Operator.
	 * @param  mixed  $value    Value.
	 * @return array|null       Meta condition or null.
	 */
	private static function build_meta_condition( $field, $operator, $value ) {
		$meta_key_map = array(
			'status'   => '_wphd_status',
			'priority' => '_wphd_priority',
			'category' => '_wphd_category',
			'assignee' => '_wphd_assignee',
			'reporter' => '_wphd_reporter',
			'tags'     => '_wphd_tags',
		);

		if ( ! isset( $meta_key_map[ $field ] ) ) {
			return null;
		}

		$meta_key = $meta_key_map[ $field ];

		switch ( $operator ) {
			case 'equals':
				return array(
					'key'   => $meta_key,
					'value' => sanitize_text_field( $value ),
				);

			case 'not_equals':
				return array(
					'key'     => $meta_key,
					'value'   => sanitize_text_field( $value ),
					'compare' => '!=',
				);

			case 'in':
				if ( ! is_array( $value ) ) {
					$value = array( $value );
				}
				return array(
					'key'     => $meta_key,
					'value'   => array_map( 'sanitize_text_field', $value ),
					'compare' => 'IN',
				);

			case 'not_in':
				if ( ! is_array( $value ) ) {
					$value = array( $value );
				}
				return array(
					'key'     => $meta_key,
					'value'   => array_map( 'sanitize_text_field', $value ),
					'compare' => 'NOT IN',
				);

			case 'exists':
				return array(
					'key'     => $meta_key,
					'compare' => 'EXISTS',
				);

			case 'not_exists':
				return array(
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				);

			default:
				return null;
		}
	}

	/**
	 * Build date query from filter groups.
	 *
	 * @since  1.0.0
	 * @param  array $groups Filter groups.
	 * @return array Date query array.
	 */
	private static function build_date_query( $groups ) {
		$date_conditions = array();

		foreach ( $groups as $group ) {
			if ( empty( $group['conditions'] ) || ! is_array( $group['conditions'] ) ) {
				continue;
			}

			foreach ( $group['conditions'] as $condition ) {
				if ( empty( $condition['field'] ) ) {
					continue;
				}

				$field = sanitize_text_field( $condition['field'] );

				// Only process date fields
				if ( ! in_array( $field, array( 'created_date', 'modified_date' ), true ) ) {
					continue;
				}

				$operator = ! empty( $condition['operator'] ) ? $condition['operator'] : 'equals';
				$value = $condition['value'] ?? '';

				$date_condition = self::build_date_condition( $field, $operator, $value );
				if ( ! empty( $date_condition ) ) {
					$date_conditions[] = $date_condition;
				}
			}
		}

		if ( empty( $date_conditions ) ) {
			return array();
		}

		if ( count( $date_conditions ) > 1 ) {
			array_unshift( $date_conditions, array( 'relation' => 'AND' ) );
		}

		return $date_conditions;
	}

	/**
	 * Build a date condition.
	 *
	 * @since  1.0.0
	 * @param  string $field    Field name.
	 * @param  string $operator Operator.
	 * @param  mixed  $value    Value.
	 * @return array|null       Date condition or null.
	 */
	private static function build_date_condition( $field, $operator, $value ) {
		$column_map = array(
			'created_date'  => 'post_date',
			'modified_date' => 'post_modified',
		);

		if ( ! isset( $column_map[ $field ] ) ) {
			return null;
		}

		$column = $column_map[ $field ];

		switch ( $operator ) {
			case 'after':
				return array(
					'column' => $column,
					'after'  => sanitize_text_field( $value ),
				);

			case 'before':
				return array(
					'column' => $column,
					'before' => sanitize_text_field( $value ),
				);

			case 'between':
				if ( ! is_array( $value ) || count( $value ) < 2 ) {
					return null;
				}
				return array(
					'column' => $column,
					'after'  => sanitize_text_field( $value[0] ),
					'before' => sanitize_text_field( $value[1] ),
				);

			default:
				return null;
		}
	}

	/**
	 * Extract text search query from filter groups.
	 *
	 * @since  1.0.0
	 * @param  array $groups Filter groups.
	 * @return string Search query.
	 */
	private static function extract_search_query( $groups ) {
		foreach ( $groups as $group ) {
			if ( empty( $group['conditions'] ) || ! is_array( $group['conditions'] ) ) {
				continue;
			}

			foreach ( $group['conditions'] as $condition ) {
				if ( empty( $condition['field'] ) ) {
					continue;
				}

				if ( 'text_search' === $condition['field'] && ! empty( $condition['value'] ) ) {
					return sanitize_text_field( $condition['value'] );
				}
			}
		}

		return '';
	}

	/**
	 * Validate filter configuration.
	 *
	 * @since  1.0.0
	 * @param  array $config Filter configuration.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_config( $config ) {
		if ( ! is_array( $config ) ) {
			return new WP_Error( 'invalid_config', __( 'Filter configuration must be an array.', 'wp-helpdesk' ) );
		}

		if ( empty( $config['groups'] ) || ! is_array( $config['groups'] ) ) {
			return new WP_Error( 'missing_groups', __( 'Filter must have at least one group.', 'wp-helpdesk' ) );
		}

		foreach ( $config['groups'] as $index => $group ) {
			if ( ! is_array( $group ) ) {
				return new WP_Error( 'invalid_group', sprintf( __( 'Group %d is invalid.', 'wp-helpdesk' ), $index ) );
			}

			if ( empty( $group['conditions'] ) || ! is_array( $group['conditions'] ) ) {
				return new WP_Error( 'missing_conditions', sprintf( __( 'Group %d must have conditions.', 'wp-helpdesk' ), $index ) );
			}

			foreach ( $group['conditions'] as $cond_index => $condition ) {
				if ( empty( $condition['field'] ) ) {
					return new WP_Error( 'missing_field', sprintf( __( 'Condition %d in group %d is missing field.', 'wp-helpdesk' ), $cond_index, $index ) );
				}
			}
		}

		return true;
	}

	/**
	 * Get available filter operators by field type.
	 *
	 * @since  1.0.0
	 * @param  string $field_type Field type.
	 * @return array Operators.
	 */
	public static function get_operators_for_field( $field_type ) {
		$operators = array(
			'select_single'   => array(
				'equals'     => __( 'Equals', 'wp-helpdesk' ),
				'not_equals' => __( 'Not Equals', 'wp-helpdesk' ),
			),
			'select_multiple' => array(
				'in'     => __( 'In', 'wp-helpdesk' ),
				'not_in' => __( 'Not In', 'wp-helpdesk' ),
			),
			'date'            => array(
				'after'   => __( 'After', 'wp-helpdesk' ),
				'before'  => __( 'Before', 'wp-helpdesk' ),
				'between' => __( 'Between', 'wp-helpdesk' ),
			),
			'text'            => array(
				'contains'     => __( 'Contains', 'wp-helpdesk' ),
				'not_contains' => __( 'Does Not Contain', 'wp-helpdesk' ),
			),
		);

		return isset( $operators[ $field_type ] ) ? $operators[ $field_type ] : array();
	}
}

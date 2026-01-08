# Queue Filters System Documentation

## Overview

The Queue Filters system allows users to create, save, and apply custom ticket filters in the WP Help Desk plugin. It supports both personal (user-level) and shared (organization-level) filters with comprehensive filtering criteria.

## Features

### Filter Types
- **Personal Filters**: User-specific filters visible only to the creator
- **Organization Filters**: Shared filters visible to all members of an organization

### Filter Criteria
- **Status**: Multi-select status filtering
- **Priority**: Multi-select priority filtering  
- **Category**: Multi-select category filtering
- **Assignee**: 
  - Assigned to me
  - Specific users (multi-select)
  - Unassigned tickets
  - Any assignee
- **Reporter**: Filter by ticket creator
- **Date Created**: 
  - Today, Yesterday
  - This week, Last week
  - This month, Last month
  - This year
  - Between dates (custom range)
  - Before/After specific date
- **SLA Status**:
  - Breached (first response or resolution)
  - Met (completed within SLA)
  - At Risk (approaching deadline)
  - None (no SLA tracking)
- **Full-text Search**: Search in ticket title and content
- **Organization**: Filter by organization membership

### Sorting Options
- Date Created (default)
- Last Modified
- Title
- Ascending or Descending order

## Database Schema

### Table: `wphd_queue_filters`

```sql
CREATE TABLE {prefix}wphd_queue_filters (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    filter_type enum('user', 'organization') NOT NULL DEFAULT 'user',
    user_id bigint(20) unsigned DEFAULT NULL,
    organization_id bigint(20) unsigned DEFAULT NULL,
    filter_config longtext NOT NULL,
    sort_field varchar(50) DEFAULT 'date',
    sort_order enum('ASC', 'DESC') DEFAULT 'DESC',
    is_default tinyint(1) DEFAULT 0,
    display_order int(11) DEFAULT 0,
    created_by bigint(20) unsigned NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY organization_id (organization_id),
    KEY filter_type (filter_type),
    KEY created_by (created_by),
    KEY is_default (is_default)
)
```

### Filter Configuration JSON

The `filter_config` column stores a JSON object with the following structure:

```json
{
  "status": ["open", "in-progress"],
  "priority": ["high", "critical"],
  "category": ["technical", "billing"],
  "assignee_type": "me",
  "assignee_ids": [5, 12],
  "reporter_ids": [3, 8],
  "date_created": {
    "operator": "today",
    "start": "2026-01-01",
    "end": "2026-01-31"
  },
  "sla_first_response": "breached",
  "sla_resolution": "at_risk",
  "search_phrase": "login issue",
  "organization_ids": [2, 5]
}
```

## Access Control Permissions

The system implements 7 new permission features:

1. **queue_filters_user_create**: Create personal filters (default: true)
2. **queue_filters_user_edit**: Edit own personal filters (default: true)
3. **queue_filters_user_delete**: Delete own personal filters (default: true)
4. **queue_filters_org_create**: Create organization filters (default: false)
5. **queue_filters_org_edit**: Edit organization filters (default: false)
6. **queue_filters_org_delete**: Delete organization filters (default: false)
7. **queue_filters_org_view**: View and use organization filters (default: true)

## PHP Classes

### WPHD_Queue_Filters
**Location**: `wp-helpdesk/features/queue-filters/class-queue-filters.php`

Core CRUD operations for queue filters.

#### Public Methods:
- `create($data)`: Create a new filter
- `update($filter_id, $data)`: Update existing filter
- `delete($filter_id)`: Delete a filter
- `get($filter_id)`: Get single filter by ID
- `get_user_filters($user_id)`: Get user's personal filters
- `get_organization_filters($org_id)`: Get organization filters
- `get_all_filters($user_id)`: Get both user and org filters
- `can_create_user_filter($user_id)`: Check create permission
- `can_create_org_filter($org_id, $user_id)`: Check org create permission
- `can_edit_filter($filter_id, $user_id)`: Check edit permission
- `can_delete_filter($filter_id, $user_id)`: Check delete permission
- `create_default_filters($user_id)`: Create default filters for new user

#### Default Filters Created:
1. Open Tickets
2. In Progress
3. My Tickets (set as default)
4. Unassigned
5. Created Today
6. Closed (if closed statuses exist)
7. High Priority (if high priority levels exist)
8. SLA Breached

### WPHD_Queue_Filter_Builder
**Location**: `wp-helpdesk/features/queue-filters/class-queue-filter-builder.php`

Converts filter configuration to WP_Query arguments and executes queries.

#### Public Methods:
- `build_query_args($filter_config, $sort_field, $sort_order)`: Build WP_Query args
- `get_tickets($filter_config, $sort_field, $sort_order, $per_page, $page)`: Execute filter and return tickets

#### Private Methods:
- `build_date_query($date_config)`: Build date query from config
- `get_orderby_field($sort_field)`: Map sort field to WP_Query orderby
- `filter_by_sla($tickets, $filter_config)`: Post-filter tickets by SLA status
- `check_sla_match($sla_log, $sla_type, $criteria)`: Check if SLA matches criteria

### WPHD_Queue_Filters_Management
**Location**: `wp-helpdesk/admin/class-queue-filters-management.php`

Admin interface for managing queue filters.

#### Public Methods:
- `render_management_page()`: Main management page
- `render_filter_form($filter)`: Create/edit form
- `handle_save_filter()`: Form submission handler
- `ajax_delete_filter()`: AJAX delete handler
- `ajax_set_default()`: AJAX set default handler
- `ajax_preview_filter()`: AJAX preview handler
- `ajax_get_filter()`: AJAX get filter details handler

## JavaScript API

**Location**: `wp-helpdesk/assets/js/queue-filters.js`

The `WPHD_QueueFilters` object provides:

### Methods:
- `init()`: Initialize the filter system
- `applyFilter(e)`: Apply selected filter
- `openFilterModal(e)`: Open create/edit modal
- `editFilter(e)`: Load filter for editing
- `deleteFilter(e)`: Delete a filter
- `setDefaultFilter(e)`: Set filter as default
- `previewFilter(e)`: Preview filter results
- `saveFilter(e)`: Save filter form

### Localized Data (wpHelpDesk object):
```javascript
{
  ajaxUrl: string,
  adminUrl: string,
  nonce: string,
  ticketsUrl: string,
  i18n: {
    confirm_delete: string,
    error_loading_filter: string,
    error_deleting_filter: string,
    error_setting_default: string,
    error_preview: string,
    filter_name_required: string,
    loading: string,
    select_placeholder: string
  }
}
```

## User Interface

### Tickets Page Integration
The filter selector is displayed at the top of the tickets page with:
- Dropdown showing personal and organization filters
- "New Filter" button (if user has create permission)
- "Clear Filter" button (when filter is active)
- Active filter indicator showing name, description, and ticket count

### Queue Filters Management Page
Accessible via **Help Desk > Queue Filters** menu:
- Lists all personal filters
- Lists all organization filters (if user has view permission)
- Create, edit, delete actions for each filter
- Set default filter option
- Apply filter directly from management page

### Filter Modal
Modal interface for creating/editing filters with:
- Basic info: Name, Description, Filter Type
- Filter criteria sections for all supported options
- Sort field and order selection
- Set as default checkbox
- Preview button to test filter before saving
- Form validation

## Usage Examples

### Creating a Filter Programmatically

```php
$filter_data = array(
    'name' => 'Urgent Technical Issues',
    'description' => 'High priority technical tickets',
    'filter_type' => 'user',
    'filter_config' => wp_json_encode(array(
        'status' => array('open', 'in-progress'),
        'priority' => array('high', 'critical'),
        'category' => array('technical'),
    )),
    'sort_field' => 'date',
    'sort_order' => 'DESC',
    'is_default' => 0,
);

$filter_id = WPHD_Queue_Filters::create($filter_data);
```

### Applying a Filter

```php
$filter = WPHD_Queue_Filters::get($filter_id);
$filter_config = json_decode($filter->filter_config, true);

$result = WPHD_Queue_Filter_Builder::get_tickets(
    $filter_config,
    $filter->sort_field,
    $filter->sort_order,
    20, // per page
    1   // page number
);

// $result contains:
// - 'tickets': Array of WP_Post objects
// - 'total': Total number of matching tickets
// - 'pages': Total number of pages
```

### Checking Permissions

```php
// Check if user can create personal filter
if (WPHD_Queue_Filters::can_create_user_filter()) {
    // Show create button
}

// Check if user can edit specific filter
if (WPHD_Queue_Filters::can_edit_filter($filter_id)) {
    // Show edit button
}

// Check if user can create org filter for their organization
$user_org = WPHD_Organizations::get_user_organization(get_current_user_id());
if ($user_org && WPHD_Queue_Filters::can_create_org_filter($user_org->id)) {
    // Show create organization filter option
}
```

## Security Considerations

1. **Nonce Verification**: All AJAX requests verify nonces using 'wphd_nonce'
2. **Permission Checks**: Every CRUD operation checks appropriate permissions
3. **Data Sanitization**: All user inputs are sanitized before storage
4. **SQL Injection Prevention**: Uses prepared statements via $wpdb
5. **XSS Prevention**: All output is escaped appropriately
6. **Access Control**: Respects organization-based visibility permissions

## Performance Considerations

1. **Indexed Fields**: Database table has indexes on commonly queried fields
2. **Query Optimization**: Uses WP_Query for efficient database queries
3. **Pagination Support**: Built-in pagination for large result sets
4. **SLA Post-Filtering**: SLA filtering done after main query for accuracy
5. **Caching**: Compatible with WordPress object caching

## Hooks and Filters

Currently, the system doesn't expose specific hooks, but future enhancements could include:

- `wphd_before_filter_create`: Action before creating filter
- `wphd_after_filter_create`: Action after creating filter
- `wphd_filter_query_args`: Filter to modify query arguments
- `wphd_filter_results`: Filter to modify filter results
- `wphd_default_filters`: Filter to customize default filters

## Limitations

1. **SLA Filtering**: Requires additional database query and post-processing
2. **Date Filters**: Only supports `date_created`, not `date_updated` or `date_resolved`
3. **Search**: Basic WordPress search, doesn't search in comments
4. **Pagination**: Fixed at 50 tickets per page in tickets view
5. **Export**: No built-in export of filtered results (future enhancement)

## Future Enhancements

1. Save search in comments as filter criterion
2. Add date_updated and date_resolved filters
3. Filter export/import functionality
4. Scheduled filter reports via email
5. Public filters (visible to all users)
6. Filter templates
7. Advanced boolean logic for filters (AND/OR combinations)
8. Bulk actions on filtered results
9. Filter usage analytics
10. Filter sharing via URL

## Testing

To test the Queue Filters system:

1. **Database**: Verify table creation after plugin activation
2. **Permissions**: Test with different user roles
3. **Default Filters**: Check that default filters are created for new users
4. **Personal Filters**: Create, edit, delete personal filters
5. **Organization Filters**: Test with organization membership
6. **Filter Criteria**: Test each filter criterion type
7. **Sorting**: Verify sort options work correctly
8. **SLA Filtering**: Test with tickets having SLA data
9. **Search**: Test search phrase filtering
10. **UI**: Test modal, form validation, AJAX operations
11. **Mobile**: Test responsive design on mobile devices

## Troubleshooting

### Filters Not Showing
- Check user has appropriate permissions
- Verify user_id/organization_id are set correctly
- Check database table exists

### Filter Returns No Results
- Verify filter configuration JSON is valid
- Check organization visibility permissions
- Ensure ticket post_status is 'publish'

### AJAX Errors
- Verify nonce is correct ('wphd_nonce')
- Check user has permission for the action
- Review JavaScript console for errors

### Default Filters Not Created
- Check admin_init hook is firing
- Verify user is on a wp-helpdesk page
- Check for existing filters before creation

## Support

For issues or questions about the Queue Filters system:
1. Check this documentation
2. Review the code comments in the class files
3. Check the GitHub repository issues
4. Contact the development team

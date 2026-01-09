# Advanced Ticket Filtering System

## Overview

The Advanced Ticket Filtering System provides a powerful, flexible way to filter tickets in the WP Help Desk plugin. It extends the existing queue filters with multi-criteria filtering, logical operators, real-time preview, and comprehensive filter management.

## Features

### Multi-Criteria Filtering

The system supports filtering by multiple criteria simultaneously:

- **Status** - Filter by one or more ticket statuses
- **Priority** - Filter by priority levels
- **Category** - Filter by ticket categories
- **Assignee** - Filter by assigned user
- **Reporter** - Filter by ticket creator
- **Date Ranges** - Filter by creation date, modification date, etc.
- **Text Search** - Search in ticket title, description, and comments
- **Tags** - Filter by ticket tags (if implemented)

### Logical Operators

Build complex filter logic using AND/OR operators:

- **AND Logic** - All conditions in a group must match
- **OR Logic** - At least one condition in a group must match
- **Multiple Groups** - Combine groups with different logic

### Filter Management

Save and reuse filters:

- **Personal Filters** - Visible only to the creator
- **Organization Filters** - Shared within an organization
- **Default Filters** - Automatically applied when viewing tickets
- **Quick Access** - One-click access to saved filters

### Real-Time Preview

See how many tickets match your filter before applying it:

- Live count updates as you build the filter
- Instant feedback on filter effectiveness

## Technical Architecture

### Core Classes

#### WPHD_Advanced_Filter_Builder

Located: `/wp-helpdesk/features/filters/class-advanced-filter-builder.php`

Responsible for converting filter configurations into WP_Query arguments.

**Key Methods:**

```php
// Execute a filter and return results
WPHD_Advanced_Filter_Builder::execute_filter($config, $per_page = 20, $page = 1, $count_only = false);

// Build WP_Query arguments from configuration
WPHD_Advanced_Filter_Builder::build_query_args($config);

// Validate filter configuration
WPHD_Advanced_Filter_Builder::validate_config($config);
```

#### WPHD_Filter_Manager

Located: `/wp-helpdesk/features/filters/class-filter-manager.php`

Manages CRUD operations for saved filters.

**Key Methods:**

```php
// Create a new filter
$filter_id = WPHD_Filter_Manager::create($data);

// Update existing filter
WPHD_Filter_Manager::update($filter_id, $data);

// Delete a filter
WPHD_Filter_Manager::delete($filter_id);

// Get filter by ID
$filter = WPHD_Filter_Manager::get($filter_id);

// Get user's filters
$filters = WPHD_Filter_Manager::get_user_filters($user_id);

// Get organization filters
$filters = WPHD_Filter_Manager::get_organization_filters($org_id);

// Get default filter
$filter = WPHD_Filter_Manager::get_default_filter($user_id);
```

### Database Schema

Uses the existing `wp_wphd_queue_filters` table:

```sql
CREATE TABLE wp_wphd_queue_filters (
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

### Filter Configuration Format

Filters are stored as JSON with the following structure:

```json
{
  "groups": [
    {
      "logic": "AND",
      "conditions": [
        {
          "field": "status",
          "operator": "in",
          "value": ["open", "in-progress"]
        },
        {
          "field": "priority",
          "operator": "equals",
          "value": "high"
        },
        {
          "field": "created_date",
          "operator": "after",
          "value": "2024-01-01"
        }
      ]
    }
  ],
  "sort": {
    "field": "created_at",
    "order": "DESC"
  }
}
```

## User Guide

### Creating a Filter

1. Navigate to **Help Desk > Queue Filters**
2. Click **"Create New Filter"**
3. Enter a filter name and optional description
4. Add filter conditions:
   - Select a field (e.g., Status)
   - Choose an operator (e.g., In)
   - Enter or select values
5. Click **"Preview"** to see how many tickets match
6. Click **"Save Filter"** to save

### Using Saved Filters

1. Navigate to **Help Desk > All Tickets**
2. Click on a saved filter from the filter dropdown
3. Tickets will be filtered automatically
4. Active filters are shown as chips below the toolbar

### Setting a Default Filter

1. Go to **Help Desk > Queue Filters**
2. Find the filter you want to set as default
3. Click **"Set as Default"**
4. This filter will be applied automatically when viewing tickets

### Sharing Filters (Organization Admins)

Organization admins can create shared filters:

1. When creating/editing a filter, select **"Organization"** as the filter type
2. Save the filter
3. All organization members will see this filter in their list

## Developer Guide

### Extending Filter Fields

To add custom filter fields, use the filter builder's extensible architecture:

```php
// Add custom meta field filtering
add_filter('wphd_filter_meta_fields', function($fields) {
    $fields['custom_field'] = '_wphd_custom_field';
    return $fields;
});
```

### Creating Filters Programmatically

```php
// Create a filter
$filter_data = array(
    'name' => 'My Custom Filter',
    'description' => 'Filters for high priority open tickets',
    'filter_config' => array(
        'groups' => array(
            array(
                'logic' => 'AND',
                'conditions' => array(
                    array(
                        'field' => 'status',
                        'operator' => 'equals',
                        'value' => 'open'
                    ),
                    array(
                        'field' => 'priority',
                        'operator' => 'equals',
                        'value' => 'high'
                    )
                )
            )
        ),
        'sort' => array(
            'field' => 'created_at',
            'order' => 'DESC'
        )
    ),
    'filter_type' => 'user'
);

$filter_id = WPHD_Filter_Manager::create($filter_data);
```

### Executing Filters Programmatically

```php
// Load a saved filter
$filter = WPHD_Filter_Manager::get($filter_id);

// Decode configuration
$config = json_decode($filter->filter_config, true);

// Execute filter
$results = WPHD_Advanced_Filter_Builder::execute_filter($config, 20, 1);

// $results contains:
// - tickets: array of WP_Post objects
// - total: total number of matching tickets
// - pages: total number of pages
// - current_page: current page number
```

### AJAX Endpoints

The system provides several AJAX endpoints:

#### Save Filter
```javascript
jQuery.ajax({
    url: wpHelpDesk.ajaxUrl,
    type: 'POST',
    data: {
        action: 'wphd_save_filter',
        nonce: wpHelpDesk.nonce,
        name: 'Filter Name',
        description: 'Filter Description',
        filter_config: JSON.stringify(config),
        filter_type: 'user',
        is_default: false
    },
    success: function(response) {
        console.log(response.data.filter_id);
    }
});
```

#### Preview Filter
```javascript
jQuery.ajax({
    url: wpHelpDesk.ajaxUrl,
    type: 'POST',
    data: {
        action: 'wphd_preview_filter',
        nonce: wpHelpDesk.nonce,
        filter_config: JSON.stringify(config)
    },
    success: function(response) {
        console.log(response.data.count); // Number of matching tickets
    }
});
```

## Access Control

The filtering system integrates with the existing access control system:

### Permissions

- `queue_filters_user_create` - Create personal filters
- `queue_filters_user_edit` - Edit own personal filters
- `queue_filters_user_delete` - Delete own personal filters
- `queue_filters_org_create` - Create organization filters
- `queue_filters_org_edit` - Edit organization filters
- `queue_filters_org_delete` - Delete organization filters
- `queue_filters_org_view` - View organization filters

### Checking Permissions

```php
// Check if user can create personal filters
if (WPHD_Access_Control::can_access('queue_filters_user_create')) {
    // Allow filter creation
}

// Check if user can create org filters
if (WPHD_Access_Control::can_access('queue_filters_org_create')) {
    // Allow org filter creation
}
```

## Best Practices

### Filter Performance

1. **Use Specific Criteria** - More specific filters return results faster
2. **Limit Date Ranges** - Narrow date ranges improve performance
3. **Avoid Too Many Groups** - Keep filter groups to a reasonable number
4. **Test with Preview** - Use preview to validate filter performance

### Filter Organization

1. **Descriptive Names** - Use clear, descriptive filter names
2. **Add Descriptions** - Document what each filter does
3. **Regular Cleanup** - Remove unused filters periodically
4. **Share Common Filters** - Use organization filters for team-wide needs

### Security

1. **Validate Input** - All filter inputs are validated
2. **Permission Checks** - Permissions are enforced at every level
3. **SQL Injection Protection** - All queries use WP_Query and prepared statements
4. **XSS Prevention** - All output is properly escaped

## Troubleshooting

### Filter Not Returning Expected Results

1. Check the filter configuration for typos
2. Verify the values exist (e.g., status slug is correct)
3. Use preview to see the ticket count
4. Check organization visibility settings

### Permission Errors

1. Verify user has required permissions
2. Check organization membership if using org filters
3. Ensure admin has set up access control correctly

### Performance Issues

1. Simplify complex filters
2. Narrow date ranges
3. Reduce number of filter groups
4. Check server resources

## Migration from Queue Filters

The advanced filtering system is fully backward compatible with the existing queue filters. No migration is needed, and both systems work side-by-side.

### Differences

- **Queue Filters**: Simple, predefined filter options
- **Advanced Filters**: Complex, multi-criteria filtering with logical operators

### When to Use Each

- Use **Queue Filters** for simple, quick filtering
- Use **Advanced Filters** for complex filtering needs

## API Reference

### Filter Configuration Object

```typescript
interface FilterConfig {
    groups: FilterGroup[];
    sort: {
        field: string;
        order: 'ASC' | 'DESC';
    };
}

interface FilterGroup {
    logic: 'AND' | 'OR';
    conditions: FilterCondition[];
}

interface FilterCondition {
    field: string;
    operator: string;
    value: any;
}
```

### Available Operators

- **equals** - Exact match
- **not_equals** - Does not match
- **in** - Matches any of multiple values
- **not_in** - Does not match any of multiple values
- **after** - Date is after specified date
- **before** - Date is before specified date
- **between** - Date is between two dates
- **exists** - Field has any value
- **not_exists** - Field is empty

### Available Fields

- `status` - Ticket status
- `priority` - Ticket priority
- `category` - Ticket category
- `assignee` - Assigned user ID
- `reporter` - Ticket creator ID
- `created_date` - Creation date
- `modified_date` - Last modified date
- `text_search` - Full text search
- `tags` - Ticket tags

## Support

For issues or questions:

1. Check this documentation
2. Review the code comments
3. Check the WordPress Help Desk support forums
4. Contact the development team

## Changelog

### Version 1.0.0
- Initial release of Advanced Ticket Filtering System
- Multi-criteria filtering
- Logical operators (AND/OR)
- Real-time preview
- Filter management (save, edit, delete)
- Personal and organization filters
- Default filter support
- Full access control integration
- Mobile-responsive design

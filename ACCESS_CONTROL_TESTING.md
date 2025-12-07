# Access Control Testing Guide

This document describes how to test the comprehensive access control system.

## Prerequisites

1. WordPress installation with WP Help Desk plugin activated
2. Multiple test users with different roles (Editor, Author, Contributor, Subscriber)
3. At least one organization with members

## Test Cases

### 1. Role-Based Permissions

#### Test 1.1: Configure Role Permissions
1. Login as Administrator
2. Navigate to **Help Desk > Settings > Access Control**
3. Verify the permission matrix displays all roles (except Administrator) and all features
4. Uncheck "View Reports" for Subscriber role
5. Click "Save Changes"
6. Verify success message appears

#### Test 1.2: Verify Menu Visibility
1. Login as a Subscriber user
2. Navigate to Help Desk menu
3. Verify "Reports" menu item is NOT visible
4. Login as an Editor user
5. Verify "Reports" menu item IS visible (if enabled for Editors)

#### Test 1.3: Verify Direct URL Protection
1. Login as a Subscriber user
2. Attempt to access Reports page directly via URL: `/wp-admin/admin.php?page=wp-helpdesk-reports`
3. Verify you see "You do not have permission to access reports" error
4. Verify you cannot access the page

### 2. Organization-Level Permissions

#### Test 2.1: Configure Organization Access Control
1. Login as Administrator
2. Navigate to **Help Desk > Organizations**
3. Edit an existing organization or create a new one
4. Go to the "Access Control" tab
5. Select "Custom Permissions for this Organization"
6. Configure custom permissions (e.g., disable "Create Tickets")
7. Click "Save Access Control Settings"
8. Verify success message appears

#### Test 2.2: Verify Organization Override Works
1. Add a test user to the organization configured in Test 2.1
2. Login as that user
3. Verify that organization permissions override role permissions
4. If "Create Tickets" was disabled, verify:
   - "Add New" menu item is NOT visible under Help Desk
   - Direct URL access to add ticket page is blocked

#### Test 2.3: Test Role Defaults Mode
1. Login as Administrator
2. Edit the organization from Test 2.1
3. Go to "Access Control" tab
4. Select "Use Role Defaults"
5. Save settings
6. Login as organization member
7. Verify permissions now match their WordPress role defaults

### 3. Administrator Access

#### Test 3.1: Verify Admin Bypass
1. Login as Administrator
2. Configure role permissions to deny all access for Editors
3. Verify as Administrator you can still access all pages
4. Confirm administrators always bypass permission checks

### 4. Dashboard Access

#### Test 4.1: Test Dashboard Permission
1. Disable "Dashboard" access for Contributor role
2. Login as a Contributor
3. Verify you cannot access Help Desk dashboard
4. Enable "Dashboard" access
5. Verify access is restored

### 5. Ticket Operations

#### Test 5.1: Test Ticket Creation
1. Disable "Create Tickets" for Author role
2. Login as Author
3. Verify "Add New" menu is hidden
4. Attempt direct URL access to add ticket page
5. Verify access is denied

#### Test 5.2: Test Ticket Viewing
1. Disable "View Tickets List" for Subscriber
2. Login as Subscriber
3. Verify "All Tickets" menu is hidden
4. Attempt direct URL access to tickets page
5. Verify access is denied

#### Test 5.3: Test Ticket Details
1. Disable "View Ticket Details" for a role
2. Login as that role
3. Attempt to view a specific ticket
4. Verify access is denied

### 6. Comments

#### Test 6.1: Test Internal Comments
1. Disable "View Internal Comments" for Author role
2. Create a ticket with an internal comment (as Admin)
3. Login as Author
4. View the ticket
5. Verify internal comments are NOT visible
6. Login as Editor (with permission)
7. Verify internal comments ARE visible

### 7. Reports Access

#### Test 7.1: Test Reports Permission
1. Disable "View Reports" for all non-admin roles
2. Login as Editor, Author, Contributor, and Subscriber
3. Verify Reports menu is hidden for all
4. Verify direct URL access is blocked for all

### 8. Extensibility

#### Test 8.1: Test Filter Hook
1. Add custom code to register a new controllable feature:
```php
add_filter('wphd_controllable_features', function($features) {
    $features['custom_feature'] = array(
        'label' => 'Custom Feature',
        'description' => 'A custom feature added via filter',
        'default' => false,
    );
    return $features;
});
```
2. Navigate to Settings > Access Control
3. Verify "Custom Feature" appears in the matrix
4. Configure permissions for the custom feature
5. Use `WPHD_Access_Control::can_access('custom_feature')` in code
6. Verify the permission check works correctly

## Expected Results

- ✅ All menu items respect permission settings
- ✅ Direct URL access is protected for all pages
- ✅ Organization permissions override role permissions
- ✅ Administrators always have full access
- ✅ Permission changes take effect immediately
- ✅ No errors or warnings in PHP error log
- ✅ Settings are persisted correctly across page loads
- ✅ Custom features can be added via filters

## Known Limitations

- Custom post type admin pages (Categories, Statuses, Priorities) are admin-only and not controlled by this system
- Organization management is admin-only
- Settings pages are admin-only

## Troubleshooting

### Permissions Not Working
1. Clear WordPress object cache if using persistent caching
2. Verify user is properly assigned to their role
3. Check if organization settings are overriding role permissions
4. Verify as admin that permissions are saved correctly

### Menu Items Not Hiding
1. Clear browser cache
2. Log out and log back in
3. Verify permissions are configured correctly in Settings > Access Control
4. Check if user belongs to an organization with custom permissions

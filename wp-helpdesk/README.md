# WP Help Desk

A WordPress Help Desk plugin for managing support tickets, shift handovers, and analytics.

## Features

- **Ticket Management** with custom statuses and priorities
- **SLA Tracking** with first response and resolution times
- **Shift Handovers** for team communication
- **Analytics Dashboard** with charts and metrics
- **Comprehensive Access Control** - Control page and feature access at role and organization levels
- **Organizations** - Group users and apply organization-specific permissions
- **Customizable Settings**

## Access Control

The plugin includes a robust permission system that allows administrators to:

### Role-Based Permissions
- Configure default permissions for each WordPress role via **Settings > Access Control**
- Control access to:
  - Dashboard
  - Ticket viewing, creation, editing, and deletion
  - Comment management and internal comments
  - Reports and analytics
  - Categories, statuses, and priorities viewing

### Organization-Level Overrides
- Organizations can override role-based permissions with custom settings
- Configure via **Organizations > Edit Organization > Access Control tab**
- Choose between:
  - **Use Role Defaults** - Members inherit permissions from their WordPress role
  - **Custom Permissions** - Define organization-specific access rules

### Key Benefits
- Administrators always have full access
- Menu items are automatically hidden based on permissions
- Direct URL access is protected
- Extensible via WordPress filters for custom features

## Installation

1. Upload to `/wp-content/plugins/wp-helpdesk/`
2. Activate the plugin
3. Go to Help Desk > Settings to configure
4. Configure access control via Settings > Access Control (optional)

## Requirements

- WordPress 5.0+
- PHP 7.4+

## License

GPL v2 or later
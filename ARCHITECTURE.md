# Access Control System Architecture

## Component Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        WordPress Admin UI                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌──────────────────────┐    ┌────────────────────────────────┐   │
│  │  Settings Page       │    │  Organization Edit Page        │   │
│  │  "Access Control"    │    │  "Access Control" Tab          │   │
│  │                      │    │                                 │   │
│  │  ┌──────────────┐   │    │  ┌──────────────────────────┐  │   │
│  │  │ Role Matrix  │   │    │  │ • Use Role Defaults      │  │   │
│  │  │ ┌──┬──┬──┬──┐│   │    │  │ • Custom Permissions     │  │   │
│  │  │ │Ed│Au│Co│Su││   │    │  │                          │  │   │
│  │  │ ├──┼──┼──┼──┤│   │    │  │ ┌──────────────────────┐ │  │   │
│  │  │ │☑ │☑ │☑ │☐ ││   │    │  │ │  Feature List        │ │  │   │
│  │  │ │☑ │☑ │☐ │☐ ││   │    │  │ │  ┌────────────────┐  │ │  │   │
│  │  │ └──┴──┴──┴──┘│   │    │  │ │  │ Dashboard  [☑] │  │ │  │   │
│  │  └──────────────┘   │    │  │ │  │ Tickets    [☑] │  │ │  │   │
│  └──────────────────────┘    │  │ │  │ Reports    [☐] │  │ │  │   │
│                               │  │ │  └────────────────┘  │ │  │   │
│                               │  │ └──────────────────────┘ │  │   │
│                               │  └──────────────────────────┘  │   │
│                               └────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                                     │
                                     │ Form Submission
                                     ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      WPHD_Admin_Menu Class                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  handle_save_settings()                                              │
│  handle_save_organization_access_control()                           │
│                                                                       │
│  ├─► Validate nonce                                                  │
│  ├─► Check capabilities                                              │
│  ├─► Sanitize input                                                  │
│  └─► Call save methods ──────────────────┐                          │
└──────────────────────────────────────────│──────────────────────────┘
                                            │
                                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   WPHD_Access_Control Class                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  save_role_permissions($permissions)                                 │
│  └─► update_option('wphd_role_permissions', $permissions)           │
│                                                                       │
│  can_access($feature_key, $user_id)  ◄──── Called from menu/pages  │
│  │                                                                    │
│  ├─► Is Admin? ─────────────────────────► YES: Return TRUE         │
│  │                                                                    │
│  ├─► Get User Organization                                           │
│  │   │                                                                │
│  │   ├─► Has Organization? ──────────────► YES                      │
│  │   │   │                                                            │
│  │   │   ├─► get_organization_permissions($org_id)                  │
│  │   │   │   │                                                        │
│  │   │   │   ├─► Get org settings from DB                           │
│  │   │   │   ├─► Check access_control_mode                          │
│  │   │   │   └─► Return custom permission if exists                 │
│  │   │   │                                                            │
│  │   │   └─► Return permission value                                │
│  │   │                                                                │
│  │   └─► NO: Continue to role check                                 │
│  │                                                                    │
│  ├─► Get User Role                                                   │
│  │   │                                                                │
│  │   └─► get_role_permissions($role)                                │
│       │                                                               │
│       ├─► get_option('wphd_role_permissions')                       │
│       └─► Return permission value or feature default                │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
                                     │
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          WordPress Database                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  wp_options table:                                                   │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ option_name: wphd_role_permissions                          │   │
│  │ option_value: {                                              │   │
│  │   editor: { dashboard: true, reports: true, ... },          │   │
│  │   author: { dashboard: true, reports: false, ... },         │   │
│  │   ...                                                        │   │
│  │ }                                                            │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                       │
│  wphd_organizations table:                                           │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ settings column (serialized):                               │   │
│  │ {                                                            │   │
│  │   access_control_mode: 'custom',                            │   │
│  │   access_control: {                                          │   │
│  │     dashboard: true,                                         │   │
│  │     tickets_list: true,                                      │   │
│  │     reports: false,                                          │   │
│  │     ...                                                       │   │
│  │   }                                                          │   │
│  │ }                                                            │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

## Permission Resolution Flow

```
User Request for Feature Access
        │
        ▼
   ┌─────────┐
   │ Is Admin?│────── YES ──────► ALLOW ACCESS
   └─────────┘
        │
        NO
        │
        ▼
   ┌────────────────────┐
   │ User in Organization?│
   └────────────────────┘
        │
        ├─── YES ─────────────────┐
        │                          │
        │                          ▼
        │              ┌──────────────────────┐
        │              │ Custom Mode Enabled? │
        │              └──────────────────────┘
        │                          │
        │                          ├─── YES ───► Check org access_control array
        │                          │                      │
        │                          │                      ├─── Found ───► Return value
        │                          │                      │
        │                          │                      └─── Not Found ───┐
        │                          │                                         │
        │                          └─── NO ─────────────────────────────────┤
        │                                                                     │
        └─── NO ──────────────────────────────────────────────────────────────┘
                                                                             │
                                                                             ▼
                                                              ┌────────────────────────┐
                                                              │ Check Role Permissions │
                                                              └────────────────────────┘
                                                                             │
                                                                             ├─── Found ───► Return value
                                                                             │
                                                                             └─── Not Found ───┐
                                                                                               │
                                                                                               ▼
                                                                             ┌─────────────────────────┐
                                                                             │ Return Feature Default │
                                                                             └─────────────────────────┘
```

## Menu Integration Flow

```
WordPress Admin Init
        │
        ▼
register_admin_menu()
        │
        ├─► Always Show: Main Menu Item (Help Desk)
        │
        ├─► can_access('dashboard') ?
        │   └─► YES: Show Dashboard submenu
        │   └─► NO: Hide Dashboard submenu
        │
        ├─► can_access('tickets_list') ?
        │   └─► YES: Show All Tickets submenu
        │   └─► NO: Hide All Tickets submenu
        │
        ├─► can_access('ticket_create') ?
        │   └─► YES: Show Add New submenu
        │   └─► NO: Hide Add New submenu
        │
        ├─► can_access('reports') ?
        │   └─► YES: Show Reports submenu
        │   └─► NO: Hide Reports submenu
        │
        └─► Always Show: Admin-only items (manage_options)
            ├─► Categories
            ├─► Statuses
            ├─► Priorities
            ├─► Organizations
            └─► Settings
```

## Page Access Protection

```
User visits page URL
        │
        ▼
render_*_page() method
        │
        ├─► can_access('feature_key') ?
        │
        ├─── YES ──────► Render page content
        │
        └─── NO ───────► wp_die('You do not have permission...')
```

## Data Storage Structure

```
WordPress Options:
└── wphd_role_permissions
    ├── editor
    │   ├── dashboard: true
    │   ├── tickets_list: true
    │   ├── ticket_create: true
    │   ├── ticket_edit: true
    │   ├── reports: true
    │   └── ...
    ├── author
    │   ├── dashboard: true
    │   ├── tickets_list: true
    │   ├── ticket_create: true
    │   ├── reports: false
    │   └── ...
    └── ...

Organizations Table:
└── wphd_organizations
    └── settings (serialized)
        ├── view_organization_tickets: true
        ├── can_create_tickets: true
        ├── access_control_mode: 'custom'
        └── access_control
            ├── dashboard: true
            ├── tickets_list: true
            ├── ticket_create: false
            ├── reports: false
            └── ...
```

## Legend

```
☑  = Checkbox checked (permission granted)
☐  = Checkbox unchecked (permission denied)
Ed = Editor role
Au = Author role
Co = Contributor role
Su = Subscriber role
─► = Data/Control flow
│  = Vertical flow
├─ = Branch point
└─ = End point
```

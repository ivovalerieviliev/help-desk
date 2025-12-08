# Shifts Feature Testing Guide

## Overview
This document provides comprehensive testing procedures for the Shifts feature in WP Help Desk plugin.

## Prerequisites
- WordPress installation with WP Help Desk plugin activated
- At least one organization created
- Multiple user roles available (Administrator, Editor, Subscriber)
- Access to WordPress admin panel

## Test Environment Setup

### 1. Database Verification
```sql
-- Check if shifts table exists
SHOW TABLES LIKE 'wp_wphd_shifts';

-- Verify table structure
DESCRIBE wp_wphd_shifts;

-- Expected columns:
-- id, organization_id, name, start_time, end_time, timezone, 
-- created_by, created_at, updated_at
```

### 2. Access Control Configuration
1. Navigate to **Settings → Access Control**
2. Verify "View Shifts" and "Manage Shifts" features are listed
3. Configure permissions for test roles

### 3. Create Test Organization
1. Go to **Help Desk → Organizations**
2. Create a test organization named "Test Org"
3. Note the organization ID

## Test Cases

### TC-001: Database Table Creation
**Objective:** Verify shifts table is created on plugin activation

**Steps:**
1. Deactivate WP Help Desk plugin
2. Reactivate WP Help Desk plugin
3. Check database for `wp_wphd_shifts` table

**Expected Result:**
- Table exists with correct structure
- All columns present with correct data types
- Index on organization_id exists

**Status:** [ ] Pass [ ] Fail

---

### TC-002: UI - Shifts Tab Visibility
**Objective:** Verify Shifts tab appears in organization edit page

**Steps:**
1. Log in as Administrator
2. Navigate to **Help Desk → Organizations**
3. Click "Edit" on any organization
4. Look for "Shifts" tab in the tab menu

**Expected Result:**
- Shifts tab is visible
- Tab is clickable
- Clicking tab shows shifts interface

**Status:** [ ] Pass [ ] Fail

---

### TC-003: Create Shift - Valid Data
**Objective:** Successfully create a shift with valid data

**Steps:**
1. Navigate to organization edit → Shifts tab
2. Fill in form:
   - Name: "Morning Shift"
   - Start Time: 09:00
   - End Time: 17:00
   - Timezone: Select your local timezone
3. Click "Add Shift"

**Expected Result:**
- Success message appears
- Shift appears in list immediately
- All fields displayed correctly

**Status:** [ ] Pass [ ] Fail

---

### TC-004: Create Shift - Missing Required Fields
**Objective:** Validate required field enforcement

**Steps:**
1. Navigate to Shifts tab
2. Leave Name field empty
3. Click "Add Shift"

**Expected Result:**
- Error message: "Please fill in all required fields"
- Shift is NOT created

**Status:** [ ] Pass [ ] Fail

---

### TC-005: Create Shift - Invalid Time Range
**Objective:** Validate start time < end time rule

**Steps:**
1. Fill in form:
   - Name: "Invalid Shift"
   - Start Time: 17:00
   - End Time: 09:00
2. Click "Add Shift"

**Expected Result:**
- Error message: "Start time must be before end time"
- Shift is NOT created

**Status:** [ ] Pass [ ] Fail

---

### TC-006: Create Shift - Invalid Timezone
**Objective:** Validate timezone handling

**Steps:**
1. Using browser dev tools, modify timezone dropdown to include invalid value
2. Try to submit form with invalid timezone

**Expected Result:**
- Server-side validation catches invalid timezone
- Defaults to UTC or rejects request

**Status:** [ ] Pass [ ] Fail

---

### TC-007: View Shifts List
**Objective:** Display all shifts for an organization

**Steps:**
1. Create 3 different shifts
2. Navigate to Shifts tab
3. Verify all shifts are displayed

**Expected Result:**
- All 3 shifts visible in table
- Columns: Name, Start Time, End Time, Timezone, Actions
- Data displayed correctly

**Status:** [ ] Pass [ ] Fail

---

### TC-008: Edit Shift - Inline Editing
**Objective:** Successfully edit shift via inline editing

**Steps:**
1. Click "Edit" on a shift
2. Modify name to "Updated Shift"
3. Modify start time to 10:00
4. Click "Save"

**Expected Result:**
- Inline form appears with current values
- Changes are saved
- Success message appears
- List refreshes with new data

**Status:** [ ] Pass [ ] Fail

---

### TC-009: Edit Shift - Cancel Editing
**Objective:** Cancel editing restores original values

**Steps:**
1. Click "Edit" on a shift
2. Modify any field
3. Click "Cancel"

**Expected Result:**
- Original values restored
- No changes saved
- Edit mode exits

**Status:** [ ] Pass [ ] Fail

---

### TC-010: Delete Shift
**Objective:** Successfully delete a shift

**Steps:**
1. Click "Delete" on a shift
2. Confirm deletion in dialog
3. Observe list

**Expected Result:**
- Confirmation dialog appears
- After confirming, shift is removed
- Success message appears
- List refreshes without deleted shift

**Status:** [ ] Pass [ ] Fail

---

### TC-011: Delete Shift - Cancel
**Objective:** Canceling deletion preserves shift

**Steps:**
1. Click "Delete" on a shift
2. Click "Cancel" in confirmation dialog

**Expected Result:**
- Shift is NOT deleted
- Shift remains in list

**Status:** [ ] Pass [ ] Fail

---

### TC-012: Permission - Admin Access
**Objective:** Administrators can view and manage shifts

**Steps:**
1. Log in as Administrator
2. Navigate to Shifts tab
3. Attempt to create, edit, delete shifts

**Expected Result:**
- All operations succeed
- All buttons visible
- Full access granted

**Status:** [ ] Pass [ ] Fail

---

### TC-013: Permission - Role-Based View Only
**Objective:** Users with only shifts_view can view but not manage

**Steps:**
1. Configure Editor role: shifts_view = true, shifts_manage = false
2. Log in as Editor
3. Navigate to Shifts tab

**Expected Result:**
- Shifts list is visible
- Add/Edit/Delete buttons are NOT visible
- Read-only access

**Status:** [ ] Pass [ ] Fail

---

### TC-014: Permission - No View Access
**Objective:** Users without shifts_view cannot see shifts

**Steps:**
1. Configure Subscriber role: shifts_view = false
2. Log in as Subscriber
3. Try to access organization edit page

**Expected Result:**
- Shifts tab is NOT visible
- Direct URL access denied
- Permission error message

**Status:** [ ] Pass [ ] Fail

---

### TC-015: Permission - Organization Override
**Objective:** Org-level permissions override role defaults

**Steps:**
1. Configure global: Editor shifts_manage = false
2. Configure specific org: Access Control mode = Custom, shifts_manage = true
3. Log in as Editor who belongs to that org
4. Navigate to that org's Shifts tab

**Expected Result:**
- Editor CAN manage shifts for this org only
- Add/Edit/Delete buttons visible

**Status:** [ ] Pass [ ] Fail

---

### TC-016: History Logging - Create
**Objective:** Shift creation is logged

**Steps:**
1. Create a new shift
2. Navigate to organization → Change Log tab
3. Look for recent entry

**Expected Result:**
- Log entry shows "shift_created"
- Shift name recorded
- User and timestamp recorded

**Status:** [ ] Pass [ ] Fail

---

### TC-017: History Logging - Edit
**Objective:** Shift editing is logged

**Steps:**
1. Edit an existing shift
2. Navigate to Change Log tab

**Expected Result:**
- Log entry shows "shift_edited"
- Old and new values recorded
- User and timestamp recorded

**Status:** [ ] Pass [ ] Fail

---

### TC-018: History Logging - Delete
**Objective:** Shift deletion is logged

**Steps:**
1. Delete a shift
2. Navigate to Change Log tab

**Expected Result:**
- Log entry shows "shift_deleted"
- Deleted shift name recorded
- User and timestamp recorded

**Status:** [ ] Pass [ ] Fail

---

### TC-019: REST API - Get Organization Shifts
**Objective:** REST endpoint returns shifts for organization

**Steps:**
1. Use REST client (Postman/curl)
2. Request: `GET /wp-json/wphd/v1/organizations/1/shifts`
3. Include authentication

**Expected Result:**
- 200 status code
- JSON array of shifts
- All shift fields present

**Status:** [ ] Pass [ ] Fail

---

### TC-020: REST API - Create Shift
**Objective:** REST endpoint creates shift

**Steps:**
1. Request: `POST /wp-json/wphd/v1/organizations/1/shifts`
2. Body:
```json
{
  "name": "API Test Shift",
  "start_time": "08:00:00",
  "end_time": "16:00:00",
  "timezone": "America/New_York"
}
```

**Expected Result:**
- 201 status code
- Shift created in database
- Response includes shift_id

**Status:** [ ] Pass [ ] Fail

---

### TC-021: REST API - Update Shift
**Objective:** REST endpoint updates shift

**Steps:**
1. Request: `PUT /wp-json/wphd/v1/shifts/1`
2. Body:
```json
{
  "name": "Updated API Shift",
  "start_time": "09:00:00"
}
```

**Expected Result:**
- 200 status code
- Shift updated in database
- Success message returned

**Status:** [ ] Pass [ ] Fail

---

### TC-022: REST API - Delete Shift
**Objective:** REST endpoint deletes shift

**Steps:**
1. Request: `DELETE /wp-json/wphd/v1/shifts/1`

**Expected Result:**
- 200 status code
- Shift removed from database
- Success message returned

**Status:** [ ] Pass [ ] Fail

---

### TC-023: REST API - Permission Denied
**Objective:** REST API enforces permissions

**Steps:**
1. Log in as user without shifts_manage
2. Try to create shift via REST API

**Expected Result:**
- 403 Forbidden status
- Permission error message
- No shift created

**Status:** [ ] Pass [ ] Fail

---

### TC-024: REST API - Invalid Timezone
**Objective:** REST API validates timezone

**Steps:**
1. Request: `POST /wp-json/wphd/v1/organizations/1/shifts`
2. Include invalid timezone: "Invalid/Timezone"

**Expected Result:**
- 400 Bad Request status
- Error message: "Invalid timezone specified"
- No shift created

**Status:** [ ] Pass [ ] Fail

---

### TC-025: Timezone Display
**Objective:** Timezones are displayed correctly

**Steps:**
1. Create shift with timezone "America/Los_Angeles"
2. View shift in list

**Expected Result:**
- Timezone column shows "America/Los_Angeles"
- Timezone is stored correctly in database

**Status:** [ ] Pass [ ] Fail

---

### TC-026: Multiple Organizations
**Objective:** Shifts are isolated per organization

**Steps:**
1. Create shifts in Organization A
2. Create shifts in Organization B
3. View each organization's Shifts tab

**Expected Result:**
- Organization A shows only its shifts
- Organization B shows only its shifts
- No cross-contamination

**Status:** [ ] Pass [ ] Fail

---

### TC-027: Database Repair Tool
**Objective:** Shifts table created via database repair

**Steps:**
1. Manually drop shifts table from database
2. Navigate to Settings → Tools
3. Click "Repair Database"

**Expected Result:**
- Shifts table recreated
- Success message shown
- Table structure correct

**Status:** [ ] Pass [ ] Fail

---

### TC-028: Empty State
**Objective:** Appropriate message when no shifts exist

**Steps:**
1. Navigate to Shifts tab for new organization
2. Observe list area

**Expected Result:**
- Message: "No shifts found."
- Clean, professional appearance

**Status:** [ ] Pass [ ] Fail

---

### TC-029: AJAX Error Handling
**Objective:** Network errors handled gracefully

**Steps:**
1. Open browser dev tools → Network tab
2. Set throttling to "Offline"
3. Try to create a shift

**Expected Result:**
- Error message: "Network error. Please try again."
- No silent failures
- UI remains responsive

**Status:** [ ] Pass [ ] Fail

---

### TC-030: Performance - Large Dataset
**Objective:** UI performs well with many shifts

**Steps:**
1. Create 50+ shifts
2. Navigate to Shifts tab
3. Observe load time and responsiveness

**Expected Result:**
- List loads quickly (< 2 seconds)
- Editing/deleting remains responsive
- No UI lag

**Status:** [ ] Pass [ ] Fail

---

### TC-031: XSS Protection
**Objective:** HTML injection prevented

**Steps:**
1. Create shift with name: `<script>alert('XSS')</script>`
2. View shifts list

**Expected Result:**
- Script tag displayed as text, not executed
- No alert appears
- HTML properly escaped

**Status:** [ ] Pass [ ] Fail

---

### TC-032: SQL Injection Protection
**Objective:** SQL injection prevented

**Steps:**
1. Using REST API or direct POST, try SQL injection:
   - Name: `'; DROP TABLE wp_wphd_shifts; --`
2. Check database

**Expected Result:**
- Shift name stored as-is
- No SQL executed
- Table intact
- Proper escaping in place

**Status:** [ ] Pass [ ] Fail

---

## Test Summary

| Category | Total Tests | Passed | Failed |
|----------|-------------|--------|--------|
| Database | 1 | | |
| UI | 9 | | |
| Validation | 3 | | |
| Permissions | 4 | | |
| History | 3 | | |
| REST API | 7 | | |
| Misc | 5 | | |
| **TOTAL** | **32** | | |

## Known Issues
(Document any known issues discovered during testing)

## Notes
(Add any additional observations or recommendations)

## Sign-Off

**Tester Name:** ___________________
**Date:** ___________________
**Result:** [ ] Approved [ ] Rejected
**Signature:** ___________________

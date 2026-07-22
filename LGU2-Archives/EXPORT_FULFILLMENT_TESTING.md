# Export Request Fulfillment Flow - Testing Guide

## Overview
This guide provides detailed testing procedures to validate the asynchronous Export Request Fulfillment implementation.

---

## Pre-Testing Requirements

### 1. Database Setup
```bash
# Run migration
mysql -u root -p my_database < migrations/001_export_fulfillment_setup.sql
```

### 2. Test Data
Ensure your database has:
- At least 1 request record in `requests` table
- At least 5-10 files in `archive_files` table
- At least 2 folders in `archive_folders` table

### 3. Staging Directory
```bash
mkdir -p storage/temp_exports
chmod 755 storage/temp_exports
```

### 4. Browser Setup
- Use Chrome/Firefox/Safari Developer Tools
- Open F12 and keep Network tab visible
- Keep Console tab open for errors

---

## Test Suite 1: Modal Opening & Closing

### Test 1.1: Detail Modal Opens on Request Click
**Steps**:
1. Navigate to `export.php`
2. Locate request cards in grid
3. Click on any request card
4. Observe Modal #1 opens

**Expected Result**:
- ✅ Modal overlay with dark background appears
- ✅ Modal #1 displays request details:
  - Requester name populated
  - Department populated
  - Version field populated
  - Purpose field populated
  - Notes field populated
- ✅ Close button (X) visible in top right
- ✅ Request status displayed

**Pass/Fail**: _____

---

### Test 1.2: Detail Modal Closes
**Steps**:
1. Open Detail Modal (from Test 1.1)
2. Click X button in top right corner
3. Observe modal closes

**Expected Result**:
- ✅ Modal disappears
- ✅ Dark overlay removed
- ✅ Page scrollable again
- ✅ No JavaScript errors in console

**Pass/Fail**: _____

---

### Test 1.3: Storage Modal Opens from Detail Modal
**Steps**:
1. Open Detail Modal (from Test 1.1)
2. Click "Open Storage" button
3. Observe Modal #2 appears

**Expected Result**:
- ✅ Modal #2 overlays on top of Modal #1
- ✅ Dark overlay updated
- ✅ Storage browser title shows "Storage Browser"
- ✅ Search input box visible
- ✅ Folder tabs visible (if folders exist)
- ✅ File list showing loading state initially
- ✅ Files load within 2 seconds

**Pass/Fail**: _____

---

### Test 1.4: Storage Modal Closes
**Steps**:
1. Open Storage Modal (from Test 1.3)
2. Click X button to close
3. Observe modal closes

**Expected Result**:
- ✅ Modal #2 closes
- ✅ Returns to Modal #1 (still visible)
- ✅ Dark overlay remains (covering page, not modal)
- ✅ Can still interact with Modal #1

**Alternative**: Click Cancel button should also close Modal #2

**Pass/Fail**: _____

---

## Test Suite 2: File Browsing & Search

### Test 2.1: Files Load in Storage Modal
**Steps**:
1. Open Storage Modal (from Test 1.3)
2. Wait 2 seconds for files to load
3. Observe file list

**Expected Result**:
- ✅ Loading spinner shows briefly
- ✅ File list appears with multiple rows
- ✅ Each file row shows:
  - File icon (type-specific: PDF red, DOC blue, XLS green)
  - File name
  - File size (formatted: e.g., "1.95 MB")
- ✅ Pagination working if 50+ files
- ✅ No console errors

**Pass/Fail**: _____

---

### Test 2.2: File Search Functionality
**Steps**:
1. Open Storage Modal
2. Type "ordinance" in search box
3. Wait 1 second for results
4. Observe filtered file list

**Expected Result**:
- ✅ File list updates in real-time
- ✅ Only files with "ordinance" in name appear
- ✅ File count reduces
- ✅ Clearing search shows all files again

**Pass/Fail**: _____

---

### Test 2.3: Folder Navigation
**Steps**:
1. Open Storage Modal
2. Click on a folder tab (e.g., "Ordinances")
3. Observe file list updates

**Expected Result**:
- ✅ File list refreshes with loading spinner
- ✅ Only files from selected folder appear
- ✅ Folder tab appears highlighted/selected
- ✅ Can switch between folders
- ✅ Files count matches folder contents

**Pass/Fail**: _____

---

## Test Suite 3: Context Menu & File Selection

### Test 3.1: Three-Dot Menu Appears on Hover
**Steps**:
1. Open Storage Modal with files loaded
2. Hover over a file row
3. Observe three-dot menu button

**Expected Result**:
- ✅ Three-dot menu button appears (⋮)
- ✅ Button appears on right side of file row
- ✅ Button is semi-transparent initially
- ✅ Becomes more opaque on hover
- ✅ Button is clickable

**Pass/Fail**: _____

---

### Test 3.2: Context Menu Opens with "Make Copy" Option
**Steps**:
1. Three-dot menu visible (from Test 3.1)
2. Click three-dot menu button
3. Observe context menu appears

**Expected Result**:
- ✅ Dropdown menu appears near cursor
- ✅ Menu shows option: "Make Copy for Export"
- ✅ Menu has file icon next to text
- ✅ Option is clickable
- ✅ Only one menu visible at a time

**Pass/Fail**: _____

---

### Test 3.3: Multiple Files Can Have Context Menu
**Steps**:
1. Open Storage Modal
2. Hover over file 1 → click menu
3. Observe menu appears
4. Click elsewhere to close menu
5. Hover over file 2 → click menu
6. Observe new menu appears

**Expected Result**:
- ✅ Can interact with different file menus
- ✅ Previous menu closes when new file selected
- ✅ No UI errors or overlapping menus
- ✅ Each file independent

**Pass/Fail**: _____

---

## Test Suite 4: File Staging Process

### Test 4.1: "Make Copy for Export" Initiates Staging
**Steps**:
1. Open Storage Modal with files loaded
2. Click file context menu
3. Click "Make Copy for Export"
4. Observe staging begins

**Expected Result**:
- ✅ Context menu closes
- ✅ Loading indicator shows (spinner or disabled state)
- ✅ No UI errors
- ✅ AJAX call visible in Network tab:
  - POST to `api/stage-export-copy.php`
  - Request includes `file_id` and `request_id`
  - Status 200 (OK)

**Pass/Fail**: _____

---

### Test 4.2: Storage Modal Auto-Closes After Staging
**Steps**:
1. Complete Test 4.1 "Make Copy for Export"
2. Wait for AJAX response
3. Observe modal behavior

**Expected Result**:
- ✅ Storage Modal #2 closes automatically
- ✅ Returns to Detail Modal #1
- ✅ Smooth transition (no jarring close)
- ✅ Page doesn't reload
- ✅ No console errors

**Pass/Fail**: _____

---

### Test 4.3: Staged Attachment Badge Appears
**Steps**:
1. Complete Test 4.2 (file staged)
2. Observe Detail Modal #1

**Expected Result**:
- ✅ New badge appears showing:
  - Green checkmark icon
  - Text: "Staged Attachment: [filename]"
  - Subtitle: "[file size] · Ready for export"
- ✅ Badge has green background (emerald-50 color)
- ✅ Badge positioned before Export button
- ✅ Clear visual confirmation file is staged

**Pass/Fail**: _____

---

### Test 4.4: Export Button Becomes Enabled
**Steps**:
1. Complete Test 4.3 (staged badge visible)
2. Observe Export Package button

**Expected Result**:
- ✅ Button changes from disabled (gray) to enabled (bright green)
- ✅ Button text still readable
- ✅ Cursor changes to pointer when hovering
- ✅ Button is clickable

**Before State**:
```
[ Export Package ]  (gray, disabled)
```

**After State**:
```
[ Export Package ]  (bright green, enabled)
```

**Pass/Fail**: _____

---

### Test 4.5: Success Toast After Staging
**Steps**:
1. Complete file staging (Test 4.1-4.3)
2. Observe notification

**Expected Result**:
- ✅ Success toast appears (green background)
- ✅ Message: "File staged successfully!"
- ✅ Toast fades out after 3 seconds
- ✅ OR console message logged if no toast

**Pass/Fail**: _____

---

## Test Suite 5: Export Package Execution

### Test 5.1: Export Button Click Initiates Processing
**Steps**:
1. Have file staged (from Test 4.3)
2. Click "Export Package" button
3. Observe export processing begins

**Expected Result**:
- ✅ Button shows loading state:
  - Spinner icon appears
  - Text changes to "Processing..."
  - Button becomes disabled
- ✅ Dark overlay may appear (optional loading indicator)
- ✅ Network tab shows POST to `api/process-export.php`
- ✅ Request includes `request_id`
- ✅ Response status 200 (OK)

**Pass/Fail**: _____

---

### Test 5.2: Database Updated After Export
**Steps**:
1. Complete Test 5.1 (export processed)
2. Run SQL query to verify

**SQL Query**:
```sql
SELECT id, status, staged_file_name, fulfilled_at 
FROM requests 
WHERE id = [request_id_used]
LIMIT 1;
```

**Expected Result**:
- ✅ `status` changed from "Pending" to "Released"
- ✅ `fulfilled_at` timestamp populated (current time)
- ✅ `staged_file_name` still contains file name

**Pass/Fail**: _____

---

### Test 5.3: Audit Log Created
**Steps**:
1. Complete Test 5.1 (export processed)
2. Run SQL query to verify

**SQL Query**:
```sql
SELECT user_id, action, request_id, details, timestamp 
FROM audit_logs 
WHERE request_id = [request_id_used] 
ORDER BY timestamp DESC 
LIMIT 1;
```

**Expected Result**:
- ✅ New row exists in `audit_logs` table
- ✅ `action` = "Export Request Fulfilled"
- ✅ `request_id` matches current request
- ✅ `details` contains file name and request info
- ✅ `timestamp` is current time
- ✅ `user_id` is logged-in user ID

**Pass/Fail**: _____

---

### Test 5.4: Success Toast After Export
**Steps**:
1. Complete Test 5.1 (export processed)
2. Observe notification

**Expected Result**:
- ✅ Success toast appears (green background)
- ✅ Message: "Export request fulfilled successfully!"
- ✅ Toast visible for 2 seconds
- ✅ Automatically closes

**Pass/Fail**: _____

---

### Test 5.5: Modal Auto-Closes After Export
**Steps**:
1. Complete Test 5.1 (export processed)
2. Wait 2 seconds
3. Observe modal behavior

**Expected Result**:
- ✅ Detail Modal #1 closes automatically
- ✅ Page returns to request grid
- ✅ Smooth transition (fade out)
- ✅ No page reload (smooth UX)

**Pass/Fail**: _____

---

### Test 5.6: Request Status Updated in Grid
**Steps**:
1. Complete entire export flow (Tests 5.1-5.5)
2. Observe request card in grid

**Expected Result**:
- ✅ Request status changed to "Released"
- ✅ Status indicator updated visually
- ✅ If list auto-refreshes, card shows new status
- ✅ Can verify with page refresh

**Pass/Fail**: _____

---

## Test Suite 6: Error Handling

### Test 6.1: File Not Found Error
**Steps**:
1. Manually delete a file from storage
2. Try to stage deleted file's copy
3. Observe error handling

**Expected Result**:
- ✅ Error toast appears (red background)
- ✅ Message: "Original file not found on server"
- ✅ Detail Modal remains open
- ✅ Export button stays disabled
- ✅ No page crash or console errors

**Pass/Fail**: _____

---

### Test 6.2: Invalid Request ID Error
**Steps**:
1. Manually call API with invalid request_id:
   ```bash
   curl "http://localhost/LGU2-Archives/api/fetch-request-details.php?request_id=99999"
   ```
2. Observe response

**Expected Result**:
- ✅ API returns 404 status
- ✅ JSON response: `{"success": false, "error": "Request not found"}`
- ✅ Frontend handles gracefully

**Pass/Fail**: _____

---

### Test 6.3: Missing Session/Unauthorized
**Steps**:
1. Logout (destroy session)
2. Try to call API endpoints manually
3. Observe error

**Expected Result**:
- ✅ API returns 401 status
- ✅ JSON response: `{"success": false, "error": "Unauthorized"}`
- ✅ Frontend redirects to login

**Pass/Fail**: _____

---

### Test 6.4: No File Staged Error
**Steps**:
1. Open Detail Modal
2. Click "Export Package" WITHOUT staging file
3. Observe error handling

**Expected Result**:
- ✅ Export button disabled (should prevent this)
- ✅ If somehow bypassed, error toast appears
- ✅ Message indicates no file staged
- ✅ Request not processed

**Pass/Fail**: _____

---

### Test 6.5: Network Error Handling
**Steps**:
1. Open Developer Tools
2. Go to Network tab
3. Throttle to "Offline"
4. Try file staging
5. Observe error handling

**Expected Result**:
- ✅ Error toast appears after timeout
- ✅ Message: "Error staging file copy"
- ✅ Modal stays open
- ✅ Can retry action after going online

**Pass/Fail**: _____

---

## Test Suite 7: UI/UX Validation

### Test 7.1: Modal Styling Consistency
**Steps**:
1. Open Detail Modal #1
2. Check styling:
   - Background color
   - Border color
   - Shadow effects
   - Font sizes
   - Icon colors
3. Open Storage Modal #2
4. Compare styling

**Expected Result**:
- ✅ Both modals have consistent styling
- ✅ Dark mode compatible (dark colors applied)
- ✅ Light mode compatible (light colors applied)
- ✅ No broken layouts
- ✅ All text readable

**Pass/Fail**: _____

---

### Test 7.2: Responsive Design - Desktop
**Steps**:
1. Set browser width to 1920px
2. Open Detail Modal
3. Check layout

**Expected Result**:
- ✅ Modal properly positioned (centered)
- ✅ All content visible without scrolling
- ✅ Buttons properly sized
- ✅ Text not cut off

**Pass/Fail**: _____

---

### Test 7.3: Responsive Design - Tablet
**Steps**:
1. Set browser width to 768px
2. Open Storage Modal
3. Check layout

**Expected Result**:
- ✅ Modal responsive (narrower)
- ✅ File list scrollable if needed
- ✅ Buttons still clickable
- ✅ Touch-friendly sizes

**Pass/Fail**: _____

---

### Test 7.4: Responsive Design - Mobile
**Steps**:
1. Set browser width to 375px (mobile)
2. Open Detail Modal
3. Check layout

**Expected Result**:
- ✅ Modal takes most screen width (with padding)
- ✅ Content scrollable vertically
- ✅ Buttons remain accessible
- ✅ No horizontal scroll needed

**Pass/Fail**: _____

---

### Test 7.5: Dark Mode Toggle
**Steps**:
1. Click dark mode toggle
2. Open Detail Modal
3. Check colors change
4. Toggle back to light mode
5. Check colors revert

**Expected Result**:
- ✅ Modal colors change appropriately
- ✅ Text remains readable
- ✅ Icons change color
- ✅ Backgrounds properly styled
- ✅ Both modes work correctly

**Pass/Fail**: _____

---

### Test 7.6: Keyboard Navigation
**Steps**:
1. Open Detail Modal
2. Press Tab key repeatedly
3. Navigate through elements
4. Press Enter to activate buttons

**Expected Result**:
- ✅ Tab order logical (top to bottom)
- ✅ All buttons focusable
- ✅ Enter key activates focused buttons
- ✅ Escape key closes modal (optional)
- ✅ Visual focus indicator visible

**Pass/Fail**: _____

---

## Test Suite 8: Performance & Load Testing

### Test 8.1: Modal Open Performance
**Steps**:
1. Open Developer Tools → Performance tab
2. Start recording
3. Click request card to open Detail Modal
4. Stop recording
5. Check timing

**Expected Result**:
- ✅ Modal opens within 500ms
- ✅ AJAX fetch starts immediately
- ✅ Network waterfall shows sequential requests
- ✅ No long blocking operations

**Pass/Fail**: _____

---

### Test 8.2: File List Loading Performance
**Steps**:
1. Open Storage Modal with 100+ files
2. Measure load time
3. Check pagination works

**Expected Result**:
- ✅ Initial load within 1 second
- ✅ Pagination works smoothly
- ✅ Each page loads quickly
- ✅ No memory leaks (check Dev Tools)

**Pass/Fail**: _____

---

### Test 8.3: Search Performance
**Steps**:
1. Open Storage Modal
2. Type search query letter by letter
3. Monitor Network tab

**Expected Result**:
- ✅ Search debounced (not every keystroke)
- ✅ Only 1-2 requests per search term
- ✅ Results update within 500ms
- ✅ No excessive API calls

**Pass/Fail**: _____

---

## Test Suite 9: Integration Tests

### Test 9.1: Complete Flow - Happy Path
**Steps**:
1. Login to system
2. Navigate to `export.php`
3. Click request card
4. Click "Open Storage"
5. Search for a file
6. Click file context menu
7. Click "Make Copy"
8. Verify staged badge appears
9. Click "Export Package"
10. Wait for success

**Expected Result**:
- ✅ All steps complete without errors
- ✅ Request status changes to "Released"
- ✅ Audit log entry created
- ✅ File copied to staging directory
- ✅ Success toast appears
- ✅ Modals close automatically

**Pass/Fail**: _____

---

### Test 9.2: Multiple Requests in Sequence
**Steps**:
1. Complete Test 9.1 (export first request)
2. Click another request card
3. Complete export flow again
4. Repeat 3+ times

**Expected Result**:
- ✅ Each request processes independently
- ✅ No state leakage between requests
- ✅ All requests show "Released" status
- ✅ All audit logs created
- ✅ Multiple files in staging directory

**Pass/Fail**: _____

---

### Test 9.3: File Staging & Re-staging
**Steps**:
1. Stage file A
2. Before exporting, click "Open Storage"
3. Stage file B (different file)
4. Observe staged attachment updated
5. Click "Export Package"

**Expected Result**:
- ✅ First file replaced by second file
- ✅ Badge shows file B name
- ✅ Only file B gets exported
- ✅ Export processes with file B

**Pass/Fail**: _____

---

### Test 9.4: Session Persistence
**Steps**:
1. Login
2. Start export flow
3. Keep browser open for 10 minutes
4. Complete export

**Expected Result**:
- ✅ Session remains valid
- ✅ No unexpected logouts
- ✅ Export still works
- ✅ AJAX calls succeed

**Pass/Fail**: _____

---

## Test Suite 10: Browser Compatibility

### Test 10.1: Chrome/Edge
**Steps**:
1. Open export.php in Chrome
2. Run complete flow (Test 9.1)

**Expected Result**:
- ✅ All features work
- ✅ No console errors
- ✅ Modals display correctly
- ✅ Network calls successful

**Browser Version**: _____
**Pass/Fail**: _____

---

### Test 10.2: Firefox
**Steps**:
1. Open export.php in Firefox
2. Run complete flow (Test 9.1)

**Expected Result**:
- ✅ All features work
- ✅ No console errors
- ✅ Modals display correctly
- ✅ Network calls successful

**Browser Version**: _____
**Pass/Fail**: _____

---

### Test 10.3: Safari
**Steps**:
1. Open export.php in Safari
2. Run complete flow (Test 9.1)

**Expected Result**:
- ✅ All features work
- ✅ No console errors
- ✅ Modals display correctly
- ✅ Network calls successful

**Browser Version**: _____
**Pass/Fail**: _____

---

## Test Results Summary

| Test Suite | Tests | Passed | Failed | Notes |
|-----------|-------|--------|--------|-------|
| 1. Modal Opening | 4 | ___ | ___ | |
| 2. File Browsing | 3 | ___ | ___ | |
| 3. Context Menu | 3 | ___ | ___ | |
| 4. File Staging | 5 | ___ | ___ | |
| 5. Export Package | 6 | ___ | ___ | |
| 6. Error Handling | 5 | ___ | ___ | |
| 7. UI/UX | 6 | ___ | ___ | |
| 8. Performance | 3 | ___ | ___ | |
| 9. Integration | 4 | ___ | ___ | |
| 10. Browser Compat | 3 | ___ | ___ | |
| **TOTAL** | **42** | **___** | **___** | |

---

## Sign-Off

- **QA Tester**: ___________________
- **Date**: ___________________
- **Status**: [ ] Pass  [ ] Fail  [ ] Conditional Pass

**Comments**:
_______________________________________________________________________________

_______________________________________________________________________________

---

## Issues Found

| ID | Test Case | Issue | Severity | Status |
|----|-----------|-------|----------|--------|
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |

---

**Last Updated**: July 22, 2026  
**Version**: 1.0.0

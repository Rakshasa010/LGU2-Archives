# Export Request Fulfillment Flow - Implementation Guide

## Overview

This document describes the complete asynchronous Export Request Fulfillment Flow implementation for the LGU2 Archives system. The workflow enables seamless, single-page request processing without page reloads using AJAX, modals, and real-time state management.

---

## Architecture Overview

### Technology Stack
- **Frontend**: HTML5, Tailwind CSS, Vanilla JavaScript (Fetch API)
- **Backend**: PHP with MySQL
- **State Management**: Client-side JavaScript state with AJAX API calls
- **UI Components**: Modal overlays with backdrop blur, context menus, loading states

### User Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│  1. NOTIFICATION / REQUEST LIST SCREEN                          │
│     - Export Request Cards Grid                                 │
│     - User clicks: "Review & Process" (card click)              │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  2. FULL DETAILS MODAL (Modal #1)                               │
│     - Requester Name, Department, Purpose, Notes                │
│     - Request Details: Version, Needed Date, Submitted Date     │
│     - [Open Storage] Button                                      │
│     - [Export Package] Button (DISABLED until file staged)       │
└─────────────────────────────────────────────────────────────────┘
                              ↓
                    User clicks: Open Storage
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  3. STORAGE BROWSER MODAL (Modal #2 - Overlays Modal #1)        │
│     - Dynamic Folder/File Tree from Database                    │
│     - Search Functionality                                      │
│     - File List with Icons & Context Menus (⋮)                  │
│     - Menu Option: "Make Copy for Export"                       │
└─────────────────────────────────────────────────────────────────┘
                              ↓
              User clicks: Make Copy for Export
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  4. STAGING & COPY LOGIC (Backend Processing)                   │
│     - AJAX POST to /api/stage-export-copy.php                   │
│     - Server: Copies file to /storage/temp_exports/             │
│     - Returns: {staged_file_id, file_name, file_size}           │
│     - Database: Updates requests table with staged info          │
└─────────────────────────────────────────────────────────────────┘
                              ↓
          Modal #2 Auto-closes, returns to Modal #1
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  5. UPDATED STATE ON MODAL #1                                   │
│     - Shows: "Staged Attachment: [file_name] (Ready)" Badge     │
│     - [Export Package] Button: NOW ENABLED (Bright Green)       │
│     - User can now proceed or select different file             │
└─────────────────────────────────────────────────────────────────┘
                              ↓
                  User clicks: Export Package
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  6. EXECUTION & FINALIZATION (Backend Processing)               │
│     - AJAX POST to /api/process-export.php                      │
│     - Server: Updates request status to 'Released'              │
│     - Logs: Audit entry created                                 │
│     - Returns: Success confirmation                             │
│     - UI: Shows success toast & closes modal(s)                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## File Structure

### New Backend API Files

#### 1. `api/fetch-request-details.php`
**Purpose**: Fetch complete metadata for a specific export request

**Method**: GET
**Parameters**: 
- `request_id` (int) - ID of the request to fetch

**Response**:
```json
{
  "success": true,
  "data": {
    "id": 5,
    "requester_name": "John Doe",
    "department": "Planning Office",
    "contact_info": "john@example.com",
    "document_title": "Zoning Ordinance 2023",
    "requested_version": "v2.1",
    "purpose": "Review and compliance",
    "notes": "Please provide latest version",
    "status": "Pending",
    "date_requested": "2026-07-22 10:30:00",
    "needed_by_date": "2026-07-25",
    "staged_file_id": null,
    "staged_file_name": null,
    "staged_file_size": null
  }
}
```

#### 2. `api/fetch-storage-files.php`
**Purpose**: Return hierarchical folder and file structure from database

**Method**: GET
**Parameters**:
- `folder_id` (int, optional) - Specific folder to browse
- `search` (string, optional) - Search query for files
- `page` (int, optional) - Pagination page number

**Response**:
```json
{
  "success": true,
  "data": {
    "folders": [
      {
        "id": 1,
        "type": "folder",
        "name": "Ordinances",
        "slug": "ordinances",
        "description": "City ordinances"
      }
    ],
    "files": [
      {
        "id": 42,
        "type": "file",
        "name": "Zoning_Ordinance_2023.pdf",
        "file_type": "application/pdf",
        "size": 2048576,
        "size_formatted": "1.95 MB",
        "uploaded_at": "2026-07-20 15:30:00",
        "version": "2.1"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 50,
      "total": 125,
      "pages": 3
    }
  }
}
```

#### 3. `api/stage-export-copy.php`
**Purpose**: Create temporary copy of file in staging directory

**Method**: POST
**Request Body**:
```json
{
  "file_id": 42,
  "request_id": 5
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "staged_file_id": "export_5_1726932134_a1b2c3d4",
    "file_name": "Zoning_Ordinance_2023.pdf",
    "file_size": 2048576,
    "file_size_formatted": "1.95 MB",
    "staged_at": "2026-07-22 10:45:00"
  }
}
```

**Server Actions**:
1. Validates file existence and access
2. Creates `storage/temp_exports/` if not exists
3. Copies original file to staging area with unique name
4. Updates `requests` table with `staged_file_id`, `staged_file_name`, `staged_file_size`
5. Creates audit log entry: "File Staged for Export"
6. Returns file metadata

#### 4. `api/process-export.php`
**Purpose**: Finalize export request and mark as fulfilled

**Method**: POST
**Request Body**:
```json
{
  "request_id": 5
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "request_id": 5,
    "status": "Released",
    "message": "Export request fulfilled successfully",
    "file_name": "Zoning_Ordinance_2023.pdf",
    "fulfilled_at": "2026-07-22 10:46:30"
  }
}
```

**Server Actions**:
1. Validates request and staged file exist
2. Updates `requests.status = 'Released'` and sets `fulfilled_at` timestamp
3. Creates audit log entry: "Export Request Fulfilled"
4. Returns completion confirmation
5. Transaction with automatic rollback on error

---

### Frontend Implementation

#### Main File: `export.php`
The main export request page with integrated modals and filtering.

**Key Updates**:
- Removed old detail modal UI - replaced with new dual-modal system
- Added Detail Modal (Modal #1) with staged file status display
- Added Storage Browser Modal (Modal #2) with dynamic file tree
- Kept existing chart initialization and request grid logic
- Connected to new `assets/js/export-fulfillment.js`

#### New JavaScript: `assets/js/export-fulfillment.js`
Comprehensive AJAX-driven state machine handling the complete workflow.

**Core Functions**:

##### Modal Management
```javascript
openDetailModal(requestId)      // Opens Modal #1, fetches request details
closeDetailModal()              // Closes Modal #1
openStorageModal()              // Opens Modal #2 with file browser
closeStorageModal()             // Closes Modal #2
```

##### AJAX API Functions
```javascript
fetchRequestDetails(requestId)  // GET api/fetch-request-details.php
loadStorageFiles(folderId, search) // GET api/fetch-storage-files.php
stageExportCopy(file)           // POST api/stage-export-copy.php
processExport()                 // POST api/process-export.php
```

##### UI Rendering
```javascript
populateDetailModal(requestData)     // Updates Modal #1 with request info
renderStorageContent(data)           // Renders folders and files in Modal #2
createFileRow(file)                  // Creates individual file row with context menu
showFileContextMenu(file, trigger)   // Three-dot menu with "Make Copy" option
```

##### State Management
```javascript
currentDetailRequest       // Current request ID being processed
currentDetailRequestData   // Full request metadata
currentStagedFile         // Staged file object {id, name, size}
currentFilter             // Active filters (type, status, search, sort)
```

---

## Database Schema Requirements

### Requests Table (Updates Required)
```sql
ALTER TABLE requests ADD COLUMN IF NOT EXISTS staged_file_id VARCHAR(255);
ALTER TABLE requests ADD COLUMN IF NOT EXISTS staged_file_name VARCHAR(255);
ALTER TABLE requests ADD COLUMN IF NOT EXISTS staged_file_size INT;
ALTER TABLE requests ADD COLUMN IF NOT EXISTS fulfilled_at TIMESTAMP NULL;
```

### Archive Files Table (Required)
Must exist with columns:
- `id` (INT PRIMARY KEY)
- `file_name` (VARCHAR)
- `file_type` (VARCHAR) - MIME type or extension
- `file_size` (INT)
- `file_path` (VARCHAR) - Path relative to storage root
- `archive_folder_id` (INT) - Foreign key
- `uploaded_at` (TIMESTAMP)
- `version` (VARCHAR)

### Archive Folders Table (Required)
Must exist with columns:
- `id` (INT PRIMARY KEY)
- `name` (VARCHAR)
- `slug` (VARCHAR)
- `description` (TEXT)
- `created_at` (TIMESTAMP)

### Audit Logs Table (Required)
For logging all staging and export actions:
```sql
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(255) NOT NULL,
  file_id INT,
  request_id INT,
  details TEXT,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## User Experience Details

### Modal #1: Full Details Modal

**Layout**:
- Header: Document icon + title + status + close button
- Content sections:
  - Requester information (name, department)
  - Request details (version, needed date, submitted info)
  - Purpose and notes
  - Staged attachment status (appears after file copied)
  - Footer buttons: "Open Storage" | "Export Package" (disabled/enabled)

**States**:
1. **Initial**: "Export Package" button disabled (gray)
2. **File Staging Loading**: Shows spinner during AJAX call
3. **File Staged**: "Staged Attachment" badge appears, button turns green and enabled
4. **Exporting**: Button shows loading spinner
5. **Success**: Button shows checkmark, auto-closes after 2 seconds

### Modal #2: Storage Browser Modal

**Layout**:
- Header: Title + close button
- Search bar: Real-time file search
- Folder tabs: Quick-jump between main folders
- File list: Scrollable list with dynamic loading
- Footer: Cancel button

**File Row Features**:
- File icon (type-specific colors)
- File name + size
- Three-dot menu button (appears on hover)
- Context menu: "Make Copy for Export" option

**Search Behavior**:
- Filters files by name in real-time
- Debounced API calls
- Maintains folder context

### Context Menu (Three-Dot Menu)

**Trigger**: Click three-dot button on file row
**Options**:
- "Make Copy for Export" - Stages the file and returns to Modal #1

**Behavior**:
- Shows near mouse position
- Closes on selection or click outside
- Only one menu visible at a time

---

## Error Handling

### API Error Responses

All endpoints return consistent error format:
```json
{
  "success": false,
  "error": "Error description message"
}
```

**HTTP Status Codes**:
- `200` - Success
- `400` - Invalid request parameters
- `401` - Unauthorized (not logged in)
- `404` - Resource not found
- `500` - Server error

### Frontend Error Handling

```javascript
// Catch network errors
.catch(error => {
  showError('Error message');
})

// Check JSON response
if (!data.success) {
  showError(data.error);
}

// Validate state before operations
if (!currentDetailRequest) {
  showError('No request selected');
}
```

### Toast Notifications

Uses existing `UI_ENH.toast()` utility if available:
```javascript
showSuccess(message)    // Green toast
showError(message)      // Red toast
showInfo(message)       // Blue toast
```

Falls back to `console.log()` if toast unavailable.

---

## Security Considerations

### Authentication
- All API endpoints require `$_SESSION['user_id']`
- Returns 401 if not logged in

### File Access
- Files copied from existing archive storage
- Staged files in `storage/temp_exports/` with unique names
- Audit logs all copy and export operations

### SQL Injection Prevention
- All database queries use prepared statements with bound parameters
- Input validation on all API parameters

### CSRF Protection
- Can be added via additional token validation if needed
- Fetch API automatically includes same-origin headers

### Data Validation
- File IDs and request IDs validated as integers
- File paths sanitized
- User input escaped in HTML output

---

## Testing Checklist

### Backend API Testing

```bash
# 1. Test fetch-request-details.php
curl "http://localhost/LGU2-Archives/api/fetch-request-details.php?request_id=5"

# 2. Test fetch-storage-files.php
curl "http://localhost/LGU2-Archives/api/fetch-storage-files.php?page=1"

# 3. Test stage-export-copy.php
curl -X POST http://localhost/LGU2-Archives/api/stage-export-copy.php \
  -H "Content-Type: application/json" \
  -d '{"file_id":42,"request_id":5}'

# 4. Test process-export.php
curl -X POST http://localhost/LGU2-Archives/api/process-export.php \
  -H "Content-Type: application/json" \
  -d '{"request_id":5}'
```

### Frontend Integration Testing

1. **Modal Opening**
   - [ ] Click request card → Modal #1 opens
   - [ ] Request details populate correctly
   - [ ] Export button is disabled initially

2. **Storage Browser**
   - [ ] Click "Open Storage" → Modal #2 opens
   - [ ] Files load from database
   - [ ] Folder tabs appear
   - [ ] Search filters files
   - [ ] Close Modal #2 → returns to Modal #1

3. **File Staging**
   - [ ] Click file context menu → "Make Copy" appears
   - [ ] Click "Make Copy" → loading spinner shows
   - [ ] File gets copied to staging area
   - [ ] Modal #2 closes automatically
   - [ ] "Staged Attachment" badge appears in Modal #1
   - [ ] Export button becomes enabled (green)

4. **Export Processing**
   - [ ] Click "Export Package" → loading spinner shows
   - [ ] Request status changes to "Released"
   - [ ] Audit log entry created
   - [ ] Success toast appears
   - [ ] Modal(s) close after 2 seconds
   - [ ] Page refreshes or updates request status

5. **Error Handling**
   - [ ] Network error → error toast shows
   - [ ] Invalid request ID → 404 error handled
   - [ ] Permission denied → 401 error handled
   - [ ] File not found → graceful error message

### Database Verification

```sql
-- Check staged file info
SELECT id, document_title, status, staged_file_name, fulfilled_at 
FROM requests WHERE id = 5;

-- Check audit logs
SELECT user_id, action, request_id, details, timestamp 
FROM audit_logs 
WHERE request_id = 5 
ORDER BY timestamp DESC;

-- Verify temp exports
SELECT COUNT(*) FROM storage/temp_exports/;
```

---

## Deployment Notes

### Directory Structure Required

```
LGU2-Archives/
├── export.php
├── api/
│   ├── fetch-request-details.php
│   ├── fetch-storage-files.php
│   ├── stage-export-copy.php
│   └── process-export.php
├── assets/
│   └── js/
│       └── export-fulfillment.js
├── storage/
│   ├── (existing files)
│   └── temp_exports/  (create this directory with 755 permissions)
├── authdatabase.php
└── (other files)
```

### Permissions

```bash
# Set temp exports directory permissions
chmod 755 storage/temp_exports/
chown www-data:www-data storage/temp_exports/
```

### Configuration

Update `export.php` database queries if your table/column names differ:
- `requests` table columns: `id`, `requester_name`, `department`, `date_requested`, `status`, `staged_file_id`, `staged_file_name`, `staged_file_size`, `fulfilled_at`
- `archive_files` table: `id`, `file_name`, `file_type`, `file_size`, `file_path`, `archive_folder_id`
- `archive_folders` table: `id`, `name`, `slug`, `description`

### Performance Optimization

1. **Database Indexing**:
   ```sql
   CREATE INDEX idx_requests_status ON requests(status);
   CREATE INDEX idx_requests_staged ON requests(staged_file_id);
   CREATE INDEX idx_archive_files_folder ON archive_files(archive_folder_id);
   ```

2. **API Pagination**: Storage browser limits to 50 files per page

3. **Cleanup**: Periodically delete old temp exports older than 7 days:
   ```bash
   find storage/temp_exports/ -type f -mtime +7 -delete
   ```

---

## Future Enhancements

1. **Bulk Export**: Select multiple files and export together
2. **Email Integration**: Send download link to requester
3. **Export History**: Track and display past exports
4. **Advanced Search**: Filter by date, file type, size
5. **Permission Levels**: Role-based access control for file viewing
6. **Compression**: Auto-zip multiple staged files
7. **Notifications**: Real-time updates via WebSocket
8. **Analytics Dashboard**: Track export metrics and trends

---

## Support & Troubleshooting

### Common Issues

**Q: Modal #2 doesn't show files**
- A: Check `archive_files` table has data, verify `archive_folder_id` references exist

**Q: "File not found on server" error**
- A: Verify `file_path` in database matches actual storage location, check directory permissions

**Q: Staged file never appears in Modal #1**
- A: Check `requests` table has `staged_file_id` column, verify AJAX response contains data

**Q: Export button stays disabled**
- A: Ensure `currentStagedFile` is set after staging, check browser console for AJAX errors

**Q: Audit logs not created**
- A: Verify `audit_logs` table exists, check `user_id` in session is valid

### Debug Tips

1. Open browser Developer Tools (F12)
2. Go to Network tab to monitor AJAX requests
3. Check Console for JavaScript errors
4. Verify API responses in Network → Response tab
5. Check server logs: `php -S localhost:8000 -t path/to/project`

---

## Contact & Documentation

For questions or issues, refer to:
- Database schema documentation
- API response examples in this file
- Test cases in Testing Checklist section
- Console logs for real-time debugging

Last Updated: July 22, 2026
Version: 1.0.0

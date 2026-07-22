# Export Request Fulfillment Flow - Quick Start Guide

## 🚀 5-Minute Setup

### Step 1: Database Setup (2 minutes)
```bash
# Option A: Using MySQL CLI
mysql -u root -p my_database < migrations/001_export_fulfillment_setup.sql

# Option B: Using phpMyAdmin
# 1. Open phpMyAdmin
# 2. Select your database
# 3. Go to "Import" tab
# 4. Upload migrations/001_export_fulfillment_setup.sql
# 5. Click "Go"
```

### Step 2: Directory Setup (1 minute)
```bash
# Create staging directory
mkdir -p storage/temp_exports
chmod 755 storage/temp_exports
```

### Step 3: Verify Installation (2 minutes)
1. Open browser and navigate to: `http://localhost/LGU2-Archives/export.php`
2. Look for the "Request Copy" page with request cards
3. Click on any request card → Modal #1 should open
4. Click "Open Storage" → Modal #2 should open with files
5. Select a file's context menu → Click "Make Copy for Export"

---

## 📁 File Structure Reference

**New files created:**
```
LGU2-Archives/
├── export.php (UPDATED)
├── api/
│   ├── fetch-request-details.php (NEW)
│   ├── fetch-storage-files.php (NEW)
│   ├── stage-export-copy.php (NEW)
│   └── process-export.php (NEW)
├── assets/js/
│   └── export-fulfillment.js (NEW)
├── migrations/
│   └── 001_export_fulfillment_setup.sql (NEW)
├── EXPORT_FULFILLMENT_IMPLEMENTATION.md (NEW - Full Docs)
└── storage/
    └── temp_exports/ (CREATE - For staging copies)
```

---

## 🎯 Complete User Workflow

### Scenario: Fulfilling an Export Request

**Start**: User on `export.php` page
```
1. Sees list of pending export requests
2. Clicks on a request card
   ↓
3. Modal #1 (Detail Modal) opens showing:
   - Requester name: "John Doe"
   - Department: "Planning Office"
   - Requested file: "Zoning Ordinance 2023"
   - Version: "Latest"
   - Purpose: "Review compliance"
   - Export button: DISABLED (grayed out)
   
4. Clicks "Open Storage" button
   ↓
5. Modal #2 (Storage Browser) overlays Modal #1 showing:
   - Folder tabs: "Ordinances", "Meeting Records", etc.
   - File search box
   - List of files in selected folder
   
6. Searches for "Zoning" or navigates to Ordinances folder
7. Finds "Zoning_Ordinance_2023.pdf"
8. Hovers over file row → three-dot menu appears (⋮)
9. Clicks menu → "Make Copy for Export" option appears
10. Clicks "Make Copy for Export"
    ↓
11. Loading spinner shows
    ↓
    [Backend processing: Copy file to staging area]
    ↓
12. Modal #2 closes automatically
13. Back to Modal #1, now showing:
    - Green badge: "Staged Attachment: Zoning_Ordinance_2023.pdf (Ready)"
    - Export button: NOW ENABLED (bright green)
    
14. Clicks "Export Package" button
    ↓
15. Loading spinner shows
    ↓
    [Backend processing: Update request status, create audit log]
    ↓
16. Success toast notification appears
17. Modal #1 closes
18. Page reloads or updates showing request status as "Released"

End: Request fulfilled successfully ✓
```

---

## 🔄 API Quick Reference

### 1️⃣ Fetch Request Details
```bash
GET /api/fetch-request-details.php?request_id=5
```
**Response**: Request metadata (requester, department, status, etc.)

### 2️⃣ Fetch Storage Files
```bash
GET /api/fetch-storage-files.php?page=1&folder_id=1&search=zoning
```
**Response**: Folders and files list with pagination

### 3️⃣ Stage Export Copy
```bash
POST /api/stage-export-copy.php
Content-Type: application/json
{
  "file_id": 42,
  "request_id": 5
}
```
**Response**: Staged file metadata (staged_file_id, file_name, file_size)

### 4️⃣ Process Export
```bash
POST /api/process-export.php
Content-Type: application/json
{
  "request_id": 5
}
```
**Response**: Success confirmation with new status "Released"

---

## 🧪 Testing the Implementation

### Quick Manual Test

```bash
# 1. Login to your system
# 2. Navigate to export.php

# 3. Test Modal #1 opening
#    - Click request card
#    - Verify modal appears with request details

# 4. Test Modal #2 opening
#    - Click "Open Storage" button
#    - Verify modal appears with files

# 5. Test file staging
#    - Click file's three-dot menu
#    - Click "Make Copy for Export"
#    - Verify loading spinner shows
#    - Wait for completion

# 6. Test export completion
#    - Click "Export Package" button
#    - Verify success message
#    - Check request status changed to "Released"
```

### Using cURL (Terminal)

```bash
# Test 1: Fetch request details
curl "http://localhost/LGU2-Archives/api/fetch-request-details.php?request_id=1"

# Test 2: Fetch storage files
curl "http://localhost/LGU2-Archives/api/fetch-storage-files.php?page=1"

# Test 3: Stage export copy (requires authentication session)
curl -X POST http://localhost/LGU2-Archives/api/stage-export-copy.php \
  -H "Content-Type: application/json" \
  -d '{"file_id":1,"request_id":1}'

# Test 4: Process export
curl -X POST http://localhost/LGU2-Archives/api/process-export.php \
  -H "Content-Type: application/json" \
  -d '{"request_id":1}'
```

### Browser Developer Tools (F12)

1. **Network Tab**:
   - Perform any action in modals
   - Watch API calls in Network tab
   - Check response JSON for errors
   - Verify HTTP 200 status

2. **Console Tab**:
   - Look for JavaScript errors
   - Check toast notifications
   - See console.log() debug messages

3. **Application Tab**:
   - View session cookies
   - Check localStorage (if used)

---

## ⚙️ Configuration

### Database Table Names
If your tables have different names, update these files:

**export.php**:
```php
// Line ~120 - Fetch requests
$requestsStmt = $conn->prepare("SELECT * FROM requests ORDER BY ...");
```

**api/fetch-request-details.php**:
```php
// Change "requests" table name if needed
$stmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
```

**api/fetch-storage-files.php**:
```php
// Change "archive_files" and "archive_folders" if needed
$folderQuery = $conn->prepare("SELECT id, name, slug, description FROM archive_folders ...");
$stmt = $conn->prepare("SELECT id, file_name, file_type, file_size, ... FROM archive_files ...");
```

**api/stage-export-copy.php**:
```php
// Change "archive_files" table name if needed
$stmt = $conn->prepare("SELECT file_name, file_path, file_size FROM archive_files WHERE id = ?");
```

### Storage Path
Change staging directory in `api/stage-export-copy.php`:
```php
// Line ~50
$stagingDir = '../storage/temp_exports';  // Change this path if needed
```

---

## 🐛 Common Issues & Solutions

### ❌ "No files found" in Storage Modal

**Cause**: `archive_files` table is empty or doesn't exist

**Solution**:
```sql
-- Check if table exists
SHOW TABLES LIKE 'archive_files';

-- If not exists, run migration
source migrations/001_export_fulfillment_setup.sql;

-- If table exists, insert sample data
INSERT INTO archive_files (file_name, file_type, file_size, file_path, archive_folder_id)
VALUES ('Test_File.pdf', 'application/pdf', 1024000, 'test/test.pdf', 1);
```

### ❌ "Export button stays disabled"

**Cause**: Staging failed silently

**Solution**:
1. Open browser F12 → Network tab
2. Click "Make Copy" action
3. Check the `stage-export-copy.php` response
4. Look for error message
5. Common: File doesn't exist on server path

### ❌ "File not found on server"

**Cause**: `file_path` in database doesn't match actual file location

**Solution**:
```bash
# Check where files actually are
find /path/to/storage -name "*.pdf" | head -20

# Update database paths to match
# Or verify archive_files.file_path matches your structure
```

### ❌ "Audit logs not appearing"

**Cause**: `audit_logs` table doesn't exist

**Solution**:
```bash
# Run migration to create table
mysql -u root -p my_database < migrations/001_export_fulfillment_setup.sql

# Or manually create:
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### ❌ "Modal doesn't close after export"

**Cause**: JavaScript error or missing element

**Solution**:
1. Check browser console for errors
2. Verify `detail-modal` and `storage-modal` IDs exist in HTML
3. Check API response is successful (success: true)

---

## 📊 Database Queries for Monitoring

### Check recent export requests
```sql
SELECT id, requester_name, document_title, status, staged_file_name, fulfilled_at
FROM requests
WHERE status IN ('Pending', 'Released')
ORDER BY date_requested DESC
LIMIT 20;
```

### View all staged files
```sql
SELECT id, requester_name, staged_file_name, staged_file_size, fulfilled_at
FROM requests
WHERE staged_file_id IS NOT NULL;
```

### Check audit activity
```sql
SELECT user_id, action, request_id, details, timestamp
FROM audit_logs
WHERE action IN ('File Staged for Export', 'Export Request Fulfilled')
ORDER BY timestamp DESC
LIMIT 50;
```

### Find unprocessed requests
```sql
SELECT id, requester_name, document_title, date_requested, needed_by_date
FROM requests
WHERE status = 'Pending'
AND needed_by_date <= CURDATE()
ORDER BY needed_by_date ASC;
```

---

## 🔐 Security Checklist

- [ ] All API endpoints check `$_SESSION['user_id']` (authentication)
- [ ] All queries use prepared statements with bound parameters (SQL injection prevention)
- [ ] File paths are validated and sanitized
- [ ] Temp exports directory has proper permissions (755)
- [ ] Audit logs track all file operations
- [ ] Consider adding CSRF token validation to POST requests
- [ ] Consider rate limiting on file staging to prevent abuse
- [ ] Regularly cleanup old temp files (older than 7 days)

---

## 📝 Code Examples for Developers

### How to extend: Adding email notification
```javascript
// In export-fulfillment.js, after successful export:
if (data.success) {
    // Send email to requester
    fetch('api/send-export-email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            request_id: currentDetailRequest,
            recipient_email: currentDetailRequestData.contact_info
        })
    });
}
```

### How to extend: Adding file preview
```javascript
// In export-fulfillment.js, modify createFileRow():
const fileRow = document.createElement('div');
fileRow.innerHTML += `
    <button class="preview-btn" data-file-id="${file.id}">
        <i class="bi bi-eye"></i>
    </button>
`;
fileRow.querySelector('.preview-btn').addEventListener('click', () => {
    window.open(`preview.php?file_id=${file.id}`, '_blank');
});
```

### How to extend: Bulk staging
```javascript
// In export-fulfillment.js:
function bulkStageFiles(fileIds) {
    fileIds.forEach(fileId => {
        const file = { id: fileId };
        stageExportCopy(file);
    });
}
```

---

## 📚 Additional Resources

- **Full Documentation**: `EXPORT_FULFILLMENT_IMPLEMENTATION.md`
- **Database Setup**: `migrations/001_export_fulfillment_setup.sql`
- **Main Implementation**: `export.php` + `assets/js/export-fulfillment.js`
- **API Endpoints**: `api/*.php` files

---

## ✅ Deployment Checklist

- [ ] Run database migration: `001_export_fulfillment_setup.sql`
- [ ] Create `storage/temp_exports/` directory
- [ ] Set proper directory permissions (755)
- [ ] Test all modals on production
- [ ] Test file staging with actual files
- [ ] Verify audit logs are created
- [ ] Check temp file cleanup schedule
- [ ] Monitor error logs for issues
- [ ] Train users on new workflow
- [ ] Document any customizations

---

**Version**: 1.0.0  
**Last Updated**: July 22, 2026  
**Status**: Ready for Production ✅

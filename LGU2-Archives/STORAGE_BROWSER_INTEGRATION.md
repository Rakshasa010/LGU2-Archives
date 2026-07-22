# Storage Browser Integration Fix

## Issue
The Storage Browser modal in export.php was showing "Error loading files" because the API endpoints were not properly integrated with your existing database schema.

## Solution
Updated the API endpoints to use your actual database schema:

### Database Tables Being Used
```
archive_folders (id, name)
archive_files (id, name, file_path, file_size, folder_id, created_at)
```

### Updated Files

#### 1. `api/fetch-storage-files.php`
**Changes:**
- Changed column names: `file_name` → `name`
- Changed column names: `archive_folder_id` → `folder_id`
- Updated folder query to select only: `id, name`
- Removed non-existent columns: `slug`, `description`, `version`, `uploaded_at`
- Added file type detection based on file extension

**Key Query:**
```sql
SELECT id, name, file_path, file_size, created_at FROM archive_files
WHERE folder_id = ? ORDER BY name ASC
```

#### 2. `api/stage-export-copy.php`
**Changes:**
- Changed column names to match your schema
- Added multiple file path resolution (tries different locations)
- Improved error handling for file not found scenarios

**Key Query:**
```sql
SELECT name, file_path, file_size FROM archive_files WHERE id = ?
```

#### 3. `assets/js/export-fulfillment.js`
**Changes:**
- Added console logging for debugging API calls
- Enhanced error messages to show actual error details
- Better error state display in UI

## Testing the Integration

### Step 1: Check Your Database
```sql
-- Verify archive_folders table has data
SELECT COUNT(*) FROM archive_folders;

-- Verify archive_files table has data
SELECT COUNT(*) FROM archive_files;

-- Check file structure
SELECT id, name, file_path, file_size, folder_id FROM archive_files LIMIT 5;
```

### Step 2: Test the API Directly
Open browser Developer Console (F12) and try:
```javascript
// Test fetching storage files
fetch('http://localhost/LGU2-Archives/api/fetch-storage-files.php?page=1')
  .then(r => r.json())
  .then(d => console.log(d))
```

### Step 3: Test in Browser
1. Go to `http://localhost/LGU2-Archives/export.php`
2. Click any request card
3. Click "Open Storage" button
4. Watch the console (F12 → Console tab) for debug messages
5. Files should now load

## Debugging Steps

### If still getting "Error loading files"

**Step 1: Check API Response**
```
1. Open Browser F12 (Developer Tools)
2. Go to Network tab
3. Click "Open Storage" button
4. Look for request to "fetch-storage-files.php"
5. Check the Response tab - what error is shown?
```

**Step 2: Check Console Logs**
```
1. Open Browser F12 → Console tab
2. Look for messages starting with [StorageAPI]
3. These will show the actual API error
```

**Step 3: Common Issues & Solutions**

| Error | Cause | Solution |
|-------|-------|----------|
| "HTTP 500" | Server error | Check error logs, verify database connection |
| "no such table" | Table doesn't exist | Run the migration or create tables manually |
| "Unknown column" | Column name mismatch | Check your actual database schema |
| "file path not found" | Path issue | Update file_path in database or check storage location |

**Step 4: Manual Database Check**
```sql
-- Run this to see actual data structure
DESC archive_files;
DESC archive_folders;

-- Check if data exists
SELECT * FROM archive_files LIMIT 1;
SELECT * FROM archive_folders LIMIT 1;
```

## File Path Resolution

The updated `stage-export-copy.php` tries multiple path locations:

1. **Direct path**: As stored in database
2. **Relative with ../**: `../` + path
3. **Uploads directory**: `../uploads/` + path

This handles different storage configurations.

## API Response Format

### Successful Response
```json
{
  "success": true,
  "data": {
    "folders": [
      {"id": 1, "type": "folder", "name": "Ordinances"}
    ],
    "files": [
      {
        "id": 42,
        "type": "file",
        "name": "Document.pdf",
        "file_type": "application/pdf",
        "path": "documents/doc.pdf",
        "size": 1024000,
        "size_formatted": "1000 KB",
        "uploaded_at": "2026-07-22 10:30:00"
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

### Error Response
```json
{
  "success": false,
  "error": "Detailed error message explaining what went wrong"
}
```

## Console Debug Output

When testing, you should see messages like:
```
[StorageAPI] Fetching: api/fetch-storage-files.php?page=1
[StorageAPI] Response status: 200
[StorageAPI] Response data: {...}
```

## Performance Notes

- **Pagination**: Limited to 50 files per page (configurable in API)
- **Search**: Real-time filtering, debounced for performance
- **Folder switching**: Instant folder navigation

## Future Enhancements

If you need to:

### Add additional columns to file display
Edit `assets/js/export-fulfillment.js` → `createFileRow()` function

### Change pagination limit
Edit `api/fetch-storage-files.php` → Change `$limit = 50;`

### Support more file types
Edit `assets/js/export-fulfillment.js` → `getFileIcon()` function

### Add file preview
Create new modal triggered from context menu

## Support

If still having issues:

1. Check browser console for [StorageAPI] messages
2. Review the actual HTTP response in Network tab
3. Verify database has archive_files and archive_folders tables
4. Check that files in database have valid file_path values
5. Ensure storage directories exist and are readable

---

**Status**: ✅ Integration Complete
**Last Updated**: July 22, 2026

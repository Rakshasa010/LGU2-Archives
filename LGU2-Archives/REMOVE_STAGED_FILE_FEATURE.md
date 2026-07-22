# Remove Staged File Feature

## Overview
Added an "X" button to remove/cancel a staged file from an export request.

## What Changed

### 1. **UI - Added Remove Button** (`export.php`)
Added an "X" button in the staged attachment container:
```html
<button 
    id="remove-staged-file-btn" 
    type="button"
    class="inline-flex items-center justify-center w-8 h-8 rounded-full 
           bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 
           hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors"
    title="Remove staged file">
    <i class="bi bi-x-lg text-sm"></i>
</button>
```

**Location:** Next to the staged file name in the green success box

### 2. **JavaScript Handler** (`assets/js/export-fulfillment.js`)
Added `removeStagedFile()` function that:
- ✅ Shows confirmation dialog
- ✅ Calls API to remove staged file
- ✅ Hides the staged attachment container
- ✅ Disables the Export button
- ✅ Shows success message

### 3. **Backend API** (`api/remove-staged-file.php`)
New endpoint that:
- ✅ Validates session and request ID
- ✅ Deletes physical file from `storage/temp_exports/`
- ✅ Clears database fields: `staged_file_id`, `staged_file_name`, `staged_file_size`
- ✅ Logs action to `notifications` table (category: "Export Cancelled")

## How It Works

### User Flow:
1. User stages a file → Green box appears with filename
2. User sees red "X" button next to filename
3. User clicks "X" → Confirmation dialog appears
4. User confirms → File removed
5. Green box disappears
6. Export button becomes disabled again
7. User can select a different file

### Technical Flow:
```
[User clicks X button]
    ↓
[Confirmation dialog: "Remove the staged file?"]
    ↓ (User confirms)
[API Call: remove-staged-file.php]
    ↓
[Delete physical file from temp_exports/]
    ↓
[UPDATE requests SET staged_file_id = NULL, ...]
    ↓
[Log to notifications table]
    ↓
[Return success]
    ↓
[Hide green box, disable Export button]
    ↓
[Show success toast]
```

## Notification Logged
When a staged file is removed:
- **Time:** Current time (e.g., "02:45 PM")
- **Date:** Current date
- **Content:** "Staged file removed from export request #[id] by user #[user_id]"
- **About:** "Export Cancelled"
- **Status:** Unread

## Testing

### Test Steps:
1. Open export.php
2. Click any request card
3. Click "Open Storage"
4. Click "Make a Copy" on any file
5. **✅ Green box appears** with staged file
6. **✅ Red "X" button appears** on the right side
7. Click the "X" button
8. **✅ Confirmation dialog appears**
9. Click "OK"
10. **✅ Green box disappears**
11. **✅ Export button becomes disabled**
12. Check `audit-logs.php` → **✅ "Export Cancelled" notification appears**

## Files Modified
- `export.php` - Added remove button HTML
- `assets/js/export-fulfillment.js` - Added removeStagedFile() function and event listener
- `api/remove-staged-file.php` - New API endpoint (created)

## Notes
- Physical file deletion is non-fatal (logs warning if fails)
- Notification logging is non-fatal (doesn't break the operation)
- Confirmation dialog prevents accidental removals
- Works seamlessly with existing export fulfillment flow

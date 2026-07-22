# Database Column Check - Export Fulfillment

## Quick Test

Run this in your browser console (F12):

```javascript
fetch('check-requests-table.php')
    .then(r => r.json())
    .then(d => {
        console.log('=== DATABASE CHECK RESULTS ===');
        console.log('Table exists:', d.table_exists);
        console.log('Needs fix:', d.needs_fix);
        console.log('Present columns:', d.present_columns);
        console.log('Missing columns:', d.missing_columns);
        
        if (d.needs_fix && d.fix_sql) {
            console.log('\n=== RUN THIS SQL TO FIX ===');
            console.log(d.fix_sql);
        }
        
        console.log('\n=== FULL RESPONSE ===');
        console.log(d);
    });
```

## What This Tests

This checks if your `requests` table has these required columns:
- ✅ `id` (int)
- ✅ `staged_file_id` (varchar) - **REQUIRED FOR EXPORT**
- ✅ `staged_file_name` (varchar) - **REQUIRED FOR EXPORT**
- ✅ `staged_file_size` (int) - **REQUIRED FOR EXPORT**
- ✅ `status` (varchar)
- ✅ `fulfilled_at` (datetime)

## If Columns Are Missing

The script will generate the SQL to add them. Example:

```sql
ALTER TABLE requests
ADD COLUMN staged_file_id VARCHAR(100) NULL,
ADD COLUMN staged_file_name VARCHAR(255) NULL,
ADD COLUMN staged_file_size INT NULL,
ADD COLUMN fulfilled_at DATETIME NULL;
```

## How to Run the Fix

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Select your database
3. Click "SQL" tab
4. Paste the generated SQL
5. Click "Go"

## Alternative: Run Migration Script

If you prefer, I can create a migration PHP script that automatically adds the missing columns.

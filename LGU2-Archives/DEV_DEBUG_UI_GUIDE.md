# 🛠 LGU2 Archives — Developer Helper: Debugging & Professional UI Guide

A quick-reference for **debugging** this system and **building UI** that matches the existing professional look.

**Stack:** PHP 7.2+ · MySQL (XAMPP) · vanilla JS · Tailwind (CDN) · Chart.js · jQuery + DataTables · Toastify · a small Node/Express service (`server.js`, port 3000)

---

## 1. Architecture Map — Where Everything Lives

```
C:\xampp\htdocs\LGU2-Archives\
├── LGU2-Archives\            ← main app (all PHP pages)
│   ├── authdatabase.php      ← DB connection + auto-schema bootstrap
│   ├── *.php                 ← pages (login, storage, export, report_analytics, ...)
│   ├── api\                  ← JSON endpoints (fetch-request-details.php, stage-export-copy.php, ...)
│   ├── includes\             ← header_scripts.php, footer_scripts.php, sidebar-centralized.php
│   ├── assets\
│   │   ├── css\              ← vars.css (tokens), skeletons.css, per-page CSS
│   │   └── js\               ← one JS file per page + theme-head.js, ui-enhancements.js
│   ├── uploads\              ← stored files
│   └── storage\temp_exports\ ← staging area for export flow
├── server.js                 ← Node/Express service (port 3000)
├── routes\files.js           ← Node file upload API
└── lib\storage.js            ← Node upload-dir helper
```

**Database:** `las_lgu2_archives` — main tables: `users`, `legislative_records`, `archive_files`, `requests`, `notifications`, `audit_logs`, `folders`.

---

# PART A — DEBUGGING

## 2. Where Errors Actually Go

| Problem | Where to look |
|---|---|
| PHP fatal / parse / runtime error | XAMPP logs (below) |
| API call returns wrong data | Browser DevTools → **Network** tab, then PHP log |
| Button / modal / JS does nothing | Browser DevTools → **Console** tab |
| Feature works on some browsers only | Console errors + check Network for blocked resources (CDN) |
| MySQL error | phpMyAdmin → run the query manually, or check PHP log |
| Node service down | Terminal running `node server.js` (port 3000) |

### PHP log locations (XAMPP)
```
C:\xampp\htdocs\LGU2-Archives\LGU2-Archives\check-error-log.php   ← open in browser
C:\xampp\php\logs\php_error_log
C:\xampp\apache\logs\error.log
```

### Use the repo's own debug tools
- `check-error-log.php` — shows PHP config + last 50 log lines
- `check-database-files.php` — sanity check files vs DB
- `test-storage-api.php`, `test_email.php`, `test_mail.php`, `debug_otp.php` — one-off API testers
- `storage_status.php` — storage health
- `check-requests-table.php` — export flow state

> ⚠️ These are developer tools. **Remove or password-protect them before production.**

---

## 3. The Debugging Workflow (follow in order)

```
1. Reproduce → note exact click / input that breaks
2. Browser Console → find red errors + the file:line
3. Browser Network → find failing request (name, status, payload, response)
4. If API → check PHP error_log() lines (see §4)
5. If DB → run the SQL manually in phpMyAdmin
6. Fix → clear cache (Ctrl+Shift+R) → retest
```

### 3.1 Console debugging
The app already logs aggressively. Prefix your logs so they're greppable:

```js
console.log('[MY-FEATURE] ✅ loaded', payload);
console.error('[MY-FEATURE] ❌ error', err);
console.warn('[MY-FEATURE] ⚠️ weird state', state);
```

### 3.2 Network tab quick reads
- **Status 401** → session expired / not logged in (JS fetch missing credentials, or PHP `session_start()` missing).
- **Status 500** → PHP exception; read the response body — most API endpoints return `{success:false, error:'...', type:'...'}`.
- **Status 404** → wrong path or file missing on disk (see §5.2).
- **`failed to load resource`** → CDN unreachable or wrong relative path (`assets/...` vs `../assets/...`).

### 3.3 PHP-side error patterns already used in this repo
```php
ini_set('display_errors', 0);   // keep off for API endpoints
ini_set('log_errors', 1);
error_log("=== FEATURE NAME CALLED ===");
error_log("Received input: " . $input);
error_log("ERROR: " . $conn->error);
```

---

## 4. API Endpoint Contract (stick to this when adding/debugging APIs)

**Every** PHP API in `api/` follows this shape:

```php
<?php
// 1. JSON + logging setup
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("=== MY API CALLED ===");

// 2. Auth guard
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// 3. DB + JSON header
require '../authdatabase.php';
header('Content-Type: application/json');

// 4. Read input (POST JSON body)
$input = json_decode(file_get_contents('php://input'), true);

// 5. Validate
if (empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id']);
    exit;
}

// 6. Query — ALWAYS prepared statements
$stmt = $conn->prepare("SELECT * FROM archive_files WHERE id = ?");
$stmt->bind_param("i", (int)$input['id']);
$stmt->execute();
$res  = $stmt->get_result();
$data = $res->fetch_assoc();

// 7. Respond
if (!$data) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}
echo json_encode(['success' => true, 'data' => $data]);
```

**Success:** `{ "success": true, "data": {...} }`
**Failure:** `{ "success": false, "error": "message" }` (+ optional `"debug"`, `"type"`).

> 🔴 The #1 bug in this repo: calling `echo json_encode(...)` **after** HTML/PHP warnings were already printed → invalid JSON → frontend `res.json()` throws. Kill warnings at the top (see above).

---

## 5. Common Bugs in THIS Codebase & Fixes

### 5.1 "401 Unauthorized" out of nowhere
- Fetch calls need cookies. Use same-origin (default) or:
  ```js
  fetch(url, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
  ```
- Check the page ran `session_start()` and the login actually set `$_SESSION['user_id']`.

### 5.2 File "not found" despite being in the DB
Files are stored relative to the app folder. `stage-export-copy.php` already tries 3 fallbacks — mimic this pattern:
```php
if (!file_exists($path)) { $path = '../' . $path; }
if (!file_exists($path)) { $path = '../uploads/' . basename($path); }
```
Log the final resolved path with `error_log()`.

### 5.3 "Button click does nothing"
- Check the JS file is actually loaded (Network tab → JS).
- Check `document.getElementById(...)` returned `null` → element id mismatch or script runs before DOM ready. The repo wraps page logic in:
  ```js
  document.addEventListener('DOMContentLoaded', () => { ... });
  ```
  or uses the IIFE + `window.*` exposure pattern (see `export-fulfillment.js`).
- Check for **duplicate ids** or **duplicate include** of the same JS file.

### 5.4 Modals open behind other elements
Follow the repo z-index convention: overlay `fixed inset-0 z-50`, stacked modals go higher (`z-[60]`, `z-[70]`). See `CLICKABLE_BUTTON_DEBUG.md` and `THREE_DOT_MENU_FIX.md` for past fixes.

### 5.5 "Works locally, broken after deploy" (cPanel)
- Case-sensitive file paths on Linux (e.g., `Assets/` vs `assets/`).
- Wrong `BASE` / sub-folder path — the app assumes it lives at `/LGU2-Archives/`.
- See `debug-cpanel-charts-missing.md` and `DEPLOYMENT_CHECKLIST.md`.

### 5.6 Chart.js shows blank card
- Chart container must have a defined height (`style="height:300px"` or a wrapper with h-64).
- Charts must init **after** the canvas exists (`DOMContentLoaded`).
- See `debug-charts-blank-card.md`.

### 5.7 Database column missing (schema drift)
The app auto-creates missing columns at startup — copy that pattern:
```php
$cols = ['new_col' => "VARCHAR(100) DEFAULT NULL"];
foreach ($cols as $col => $def) {
    $exists = $conn->query("SHOW COLUMNS FROM some_table LIKE '$col'");
    if ($exists && $exists->num_rows === 0) {
        $conn->query("ALTER TABLE some_table ADD COLUMN $col $def");
    }
}
```

### 5.8 Node service (server.js) not responding
- `node server.js` → must print `Server running on http://localhost:3000`.
- Check nothing else is on port 3000: `netstat -ano | findstr :3000`.
- `routes/files.js` persists metadata to `files-meta.json`; `lib/storage.js` writes to `uploads/`. Check folder write permissions.

---

## 6. Debugging Checklist (paste into a ticket)

```
[ ] Reproduced: ______
[ ] Browser console errors: ______
[ ] Network request failing: URL=______ Status=______ Response=______
[ ] PHP log line of interest: ______
[ ] DB query result if applicable: ______
[ ] Node service running? yes/no
[ ] Dark/light mode affected? yes/no
```

---

# PART B — BUILDING PROFESSIONAL UI

## 7. Design System (match this, don't invent new)

### 7.1 Theme & dark mode — THE core convention
- **Canonical theme key:** `localStorage.getItem('theme')` → `'dark' | 'light'` (set by `theme-toggle.js`).
- `theme-head.js` (in `<head>`) applies the `dark` class **before paint** (no flicker) and migrates legacy `archive-theme`.
- **Two parallel systems you must keep in sync:**
  1. **Tailwind dark:** use `dark:` variants everywhere.
  2. **CSS vars** (`assets/css/vars.css`) for component CSS files:
     ```css
     :root        { --bg:#f8fafc; --card:#ffffff; --muted:#6b7280; --text:#0f172a; --accent:#dc2626; --accent-2:#f97316; }
     [data-theme="dark"] { --bg:#0f172a; --card:#0b1220; --muted:#9ca3af; --text:#e6eef8; --accent:#fb7185; --accent-2:#f97316; }
     ```

**Rule:** every new UI must have a tested `dark:` look. Never hardcode `#fff` / `#000`.

### 7.2 Brand colors (Tailwind config in `includes/header_scripts.php`)
```js
tailwind.config = { darkMode:'class', theme:{ extend:{ colors:{ primary:{ DEFAULT:'#dc2626', light:'#f97316' } } } } }
```
Use `primary` / `primary-light` instead of ad-hoc reds.

### 7.3 Loading the standard toolkit (from `includes/header_scripts.php`)
Tailwind CDN · Chart.js · jQuery 3.6 · DataTables 1.13 · Toastify · `vars.css` · `skeletons.css` · `mobile-responsive.css` · `theme-head.js` · `ui-enhancements.js`

> CDN-based → **offline dev breaks everything.** Keep local copies if you demo without internet.

---

## 8. Reusable Component Patterns

### 8.1 Toast notifications (use this, not `alert()`)
`ui-enhancements.js` exposes a toast helper built on Toastify:

```js
// success
Toastify({ text:'File uploaded successfully 🎉', duration:3000, gravity:'top', position:'right',
  backgroundColor:'linear-gradient(90deg,#16a34a,#15803d)' }).showToast();
// error
Toastify({ text:'Something went wrong', duration:4000, gravity:'top', position:'right',
  backgroundColor:'linear-gradient(90deg,#dc2626,#b91c1c)' }).showToast();
```

**Why:** native `alert()` blocks the thread and looks unprofessional — never ship with it.

### 8.2 Modal — canonical markup
```html
<!-- overlay -->
<div id="example-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" aria-modal="true" role="dialog" aria-labelledby="example-title">
  <!-- backdrop -->
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" data-close-modal></div>
  <!-- card -->
  <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-auto transform transition-all duration-300">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-slate-700">
      <h3 id="example-title" class="text-lg font-semibold text-gray-900 dark:text-white">Title</h3>
      <button data-close-modal class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl leading-none" aria-label="Close">&times;</button>
    </div>
    <div class="p-6">
      <!-- content -->
    </div>
    <div class="px-6 py-4 border-t border-gray-200 dark:border-slate-700 flex justify-end gap-3">
      <button data-close-modal class="px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700">Cancel</button>
      <button id="example-submit" class="px-4 py-2 rounded-lg bg-gradient-to-r from-primary to-primary-light text-white font-semibold hover:opacity-90">Save</button>
    </div>
  </div>
</div>
```
```js
// open
document.getElementById('example-modal').classList.remove('hidden');
// close (backdrop + buttons + Escape)
document.querySelectorAll('[data-close-modal]').forEach(el =>
  el.addEventListener('click', () => document.getElementById('example-modal').classList.add('hidden')));
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.getElementById('example-modal').classList.add('hidden');
});
```

### 8.3 Primary button (brand gradient)
```html
<button class="px-4 py-2 rounded-lg bg-gradient-to-r from-primary to-primary-light text-white font-semibold hover:opacity-90 transition-all">
  Save
</button>
```
Disabled state (e.g., export button until file staged):
```html
<button disabled class="px-4 py-2 rounded-lg bg-gray-300 dark:bg-slate-700 text-gray-500 dark:text-gray-400 cursor-not-allowed">
  Export Package
</button>
```

### 8.4 Card (list rows / dashboard tiles)
```html
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 hover:shadow-md transition-all">
  <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Title</h3>
  <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Subtitle or description</p>
</div>
```

### 8.5 Status badge
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
  bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">Released</span>
<span class="... bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">Pending</span>
<span class="... bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Draft</span>
```

### 8.6 DataTables (the repo standard for tables)
```js
$('#my-table').DataTable({
  pageLength: 25,
  order: [[0, 'desc']],
  language: { search: 'Filter:' },
  dom: 'frtip'
});
```
Ensure the `<table>` has `class="min-w-full"` and `id`, and add `responsive: true` for mobile.

### 8.7 Skeleton loading (use `skeletons.css` classes already shipped)
Show skeleton placeholders while fetching, then swap in real data. Check `assets/js/skeleton-auto.js` + existing `.skeleton` classes for how the app already does it.

### 8.8 Empty states — always design one
```html
<div class="text-center py-12">
  <div class="text-4xl mb-3">📂</div>
  <p class="text-gray-600 dark:text-gray-300 font-medium">No files yet</p>
  <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Upload a file to get started.</p>
  <button class="mt-4 px-4 py-2 rounded-lg bg-gradient-to-r from-primary to-primary-light text-white font-semibold">Upload</button>
</div>
```

---

## 9. JavaScript Conventions

1. **One file per page** in `assets/js/`, named after the page.
2. **IIFE or `DOMContentLoaded`** — never rely on script order at the bottom of the page.
3. **Expose cross-page helpers on `window`** (see `window.copyStorageFile` in `export-fulfillment.js`).
4. **Centralize DOM lookups** at the top of the file (see the `// ===== DOM Elements =====` block).
5. **Fetch pattern** (matches repo):
   ```js
   const res = await fetch('api/stage-export-copy.php', {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({ file_id, request_id })
   });
   const json = await res.json();
   if (!json.success) throw new Error(json.error);
   ```
6. Wrap risky features in `try/catch`; on failure show a Toastify error, never a silent fail.
7. Guard with early `return` when required elements are missing (`if (!el) return;`).

---

## 10. Accessibility & Responsiveness (the app targets WCAG AA)

- Every button/modal needs an `aria-label` or visible text; modals get `role="dialog"` + `aria-modal="true"`.
- Keyboard: modals close on `Escape`; focusable elements use native `<button>`/`<a>`.
- Use semantic tags (`<main>`, `<nav>`, `<header>`, `<table>`).
- Mobile: rely on Tailwind responsive prefixes (`sm:` `md:` `lg:`) + `assets/css/mobile-responsive.css`; test at 375px, 768px, 1280px.
- Color: never rely on color alone — pair with icons/text (e.g., status badges include text).

---

## 11. Performance & Consistency Checklist (before calling UI "done")

```
[ ] Dark mode looks good (toggle + hard-refresh test)
[ ] Uses primary gradient for main CTAs, no raw #dc2626 literals
[ ] Toasts instead of alert()
[ ] Modal closes on backdrop click + Escape
[ ] DataTables / pagination used for long lists
[ ] Skeleton or loading state while fetching
[ ] Empty state designed
[ ] Mobile responsive (375/768/1280) — no horizontal scroll
[ ] aria-label / role present on interactive overlays
[ ] No console errors in DevTools
[ ] JS wrapped in DOMContentLoaded or IIFE
[ ] SQL uses prepared statements only
[ ] New API endpoint follows the contract in §4 (success/data/error JSON)
```

---

## 12. Related Docs in This Repo

| Need | Read |
|---|---|
| Export flow setup | `START_HERE.md`, `EXPORT_FULFILLMENT_QUICKSTART.md` |
| Staging/export internals | `EXPORT_FULFILLMENT_IMPLEMENTATION.md` |
| Storage browser | `STORAGE_BROWSER_INTEGRATION.md`, `STORAGE_ENHANCEMENTS.md` |
| Modal z-index / click bugs | `CLICKABLE_BUTTON_DEBUG.md`, `THREE_DOT_MENU_FIX.md`, `FINAL_FIX_CLICKABLE.md` |
| cPanel charts / deploy | `debug-cpanel-charts-missing.md`, `DEPLOYMENT_CHECKLIST.md` |
| Testing | `EXPORT_FULFILLMENT_TESTING.md` |

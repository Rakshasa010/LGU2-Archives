<?php
// audit-logs.php
// Lists recent notifications with time, date, content, and about; supports ?id= to highlight.

$notifications = [
    ['id'=>1,'time' => '10:00 AM', 'date' => '2026-01-19', 'content' => 'New document uploaded: Ordinance No. 123', 'about' => 'Document Upload', 'status'=>'unread'],
    ['id'=>2,'time' => '11:00 AM', 'date' => '2026-01-19', 'content' => 'System update completed', 'about' => 'System Maintenance', 'status'=>'read'],
    ['id'=>3,'time' => '11:30 AM', 'date' => '2026-01-19', 'content' => 'New user registered: Juan Dela Cruz', 'about' => 'User Registration', 'status'=>'unread'],
    ['id'=>4,'time' => '12:15 PM', 'date' => '2026-01-19', 'content' => 'Document approved: Resolution No. 456', 'about' => 'Approval', 'status'=>'read'],
    ['id'=>5,'time' => '01:02 PM', 'date' => '2026-01-19', 'content' => 'Profile picture updated for Maria', 'about' => 'Profile Update', 'status'=>'unread'],
    ['id'=>6,'time' => '02:20 PM', 'date' => '2026-01-18', 'content' => 'User permissions changed for user #34', 'about' => 'Permissions', 'status'=>'read'],
    ['id'=>7,'time' => '03:45 PM', 'date' => '2026-01-17', 'content' => 'New comment on Ordinance No. 78', 'about' => 'Comment', 'status'=>'read'],
    ['id'=>8,'time' => '04:10 PM', 'date' => '2026-01-16', 'content' => 'Scheduled backup completed', 'about' => 'Backup', 'status'=>'read'],
    ['id'=>9,'time' => '08:00 AM', 'date' => '2026-01-15', 'content' => 'Batch import finished (25 records)', 'about' => 'Import', 'status'=>'unread'],
    ['id'=>10,'time' => '09:30 AM', 'date' => '2026-01-14', 'content' => 'Access revoked for user #12', 'about' => 'Security', 'status'=>'read'],
    ['id'=>11,'time' => '10:15 AM', 'date' => '2026-01-13', 'content' => 'Tagging updated for 3 documents', 'about' => 'Metadata', 'status'=>'read'],
    ['id'=>12,'time' => '11:50 AM', 'date' => '2026-01-12', 'content' => 'New message from admin', 'about' => 'Message', 'status'=>'unread']
];

$selectedId = isset($_GET['id']) ? intval($_GET['id']) : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Audit Logs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <style>
        .highlight { background: linear-gradient(90deg, rgba(255,238,230,0.6), rgba(255,246,240,0.4)); }
    </style>
</head>
<body class="bg-gray-50 dark:bg-slate-900 text-gray-900 dark:text-gray-100 min-h-screen">
    <div class="max-w-5xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-3">
                <a href="archives-landing.php" class="px-3 py-1 bg-gray-100 dark:bg-slate-700 rounded text-sm text-gray-700 dark:text-gray-200 hover:opacity-90">&larr; Dashboard</a>
                <button id="themeToggleAudit" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Toggle theme">
                    <svg id="moonIconAudit" class="w-5 h-5 text-gray-700 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    <svg id="sunIconAudit" class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                </button>
                <h1 class="text-2xl font-bold">Audit Logs</h1>
            </div>
            <div>
                <a href="archives-landing.php" class="text-sm text-red-600 hover:underline">Back to Dashboard</a>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-4 overflow-x-auto">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-2">
                    <button id="filter-all" class="px-3 py-1 rounded bg-gray-100 dark:bg-slate-700 text-sm">All</button>
                    <button id="filter-unread" class="px-3 py-1 rounded bg-red-50 text-red-600 text-sm">Unread</button>
                    <span id="unread-count" class="ml-3 text-sm text-gray-600 dark:text-gray-300"></span>
                </div>
                <div class="flex items-center space-x-2">
                    <input id="searchInput" type="search" placeholder="Search notifications" class="px-3 py-1 border rounded bg-gray-50 dark:bg-slate-700 text-sm">
                    <a href="?" class="text-sm text-gray-500 hover:underline">Reset</a>
                </div>
            </div>

            <table class="w-full text-left table-auto">
                <thead>
                    <tr class="text-sm text-gray-600 dark:text-gray-300">
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Time</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Content</th>
                        <th class="px-3 py-2">About</th>
                        <th class="px-3 py-2">Action</th>
                    </tr>
                </thead>
                <tbody id="notesBody">
                <?php foreach ($notifications as $note): ?>
                    <?php $isSelected = ($selectedId !== null && $selectedId === $note['id']); ?>
                    <tr id="note-<?php echo $note['id']; ?>" data-id="<?php echo $note['id']; ?>" data-status="<?php echo $note['status']; ?>" class="border-t <?php echo $isSelected ? 'highlight' : ''; ?>">
                        <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200"><?php echo $note['id']; ?></td>
                        <td class="px-3 py-2 text-sm"><?php echo $note['time']; ?></td>
                        <td class="px-3 py-2 text-sm"><?php echo $note['date']; ?></td>
                        <td class="px-3 py-2 text-sm">
                            <a href="?id=<?php echo $note['id']; ?>" class="text-gray-800 dark:text-gray-100 hover:underline block"><?php echo htmlspecialchars($note['content']); ?></a>
                        </td>
                        <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars($note['about']); ?></td>
                        <td class="px-3 py-2 text-sm">
                            <button class="mark-read-btn px-2 py-1 text-xs rounded bg-gray-100 dark:bg-slate-700">Mark Read</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (function(){
            var selected = <?php echo $selectedId !== null ? $selectedId : 'null'; ?>;
            if (selected !== null) {
                var el = document.getElementById('note-' + selected);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    // small flash to draw attention
                    el.classList.add('ring-2', 'ring-red-300');
                    setTimeout(function(){ el.classList.remove('ring-2', 'ring-red-300'); }, 2200);
                }
            }
            // initialize read state from localStorage
            var stored = {};
            try { stored = JSON.parse(localStorage.getItem('audit_read') || '{}'); } catch(e){ stored = {}; }

            function updateRowStatus(id, status) {
                var tr = document.querySelector('[data-id="'+id+'"]');
                if (!tr) return;
                tr.setAttribute('data-status', status);
                if (status === 'read') {
                    tr.classList.remove('bg-yellow-50');
                    tr.querySelector('.mark-read-btn').textContent = 'Mark Read';
                } else {
                    tr.classList.add('bg-yellow-50');
                }
            }

            // Apply stored statuses
            Object.keys(stored).forEach(function(k){
                var val = stored[k];
                updateRowStatus(k, val);
            });

            // mark read buttons
            document.querySelectorAll('.mark-read-btn').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    var tr = btn.closest('tr');
                    var id = tr.getAttribute('data-id');
                    // toggle
                    var cur = tr.getAttribute('data-status') || 'unread';
                    var next = (cur === 'unread') ? 'read' : 'unread';
                    stored[id] = next;
                    localStorage.setItem('audit_read', JSON.stringify(stored));
                    tr.setAttribute('data-status', next);
                    // visual
                    if (next === 'read') {
                        tr.classList.remove('highlight');
                        tr.style.opacity = '0.9';
                        btn.textContent = 'Mark Read';
                    } else {
                        tr.classList.add('highlight');
                        btn.textContent = 'Mark Read';
                    }
                    updateUnreadCount();
                });
            });

            // filtering
            var notesBody = document.getElementById('notesBody');
            var filterAll = document.getElementById('filter-all');
            var filterUnread = document.getElementById('filter-unread');
            var searchInput = document.getElementById('searchInput');
            var unreadCountEl = document.getElementById('unread-count');

            function updateUnreadCount(){
                var unread = 0;
                document.querySelectorAll('#notesBody tr').forEach(function(r){ if (r.getAttribute('data-status') === 'unread') unread++; });
                unreadCountEl.textContent = unread + ' unread';
            }

            filterAll.addEventListener('click', function(){
                document.querySelectorAll('#notesBody tr').forEach(function(r){ r.style.display = ''; });
            });

            filterUnread.addEventListener('click', function(){
                document.querySelectorAll('#notesBody tr').forEach(function(r){ r.style.display = (r.getAttribute('data-status') === 'unread') ? '' : 'none'; });
            });

            searchInput.addEventListener('input', function(){
                var q = (this.value || '').toLowerCase();
                document.querySelectorAll('#notesBody tr').forEach(function(r){
                    var text = r.textContent.toLowerCase();
                    r.style.display = text.indexOf(q) !== -1 ? '' : 'none';
                });
            });

            updateUnreadCount();
            // Theme handling for audit-logs
            function applyTheme(t){ if (t==='dark') document.documentElement.classList.add('dark'); else document.documentElement.classList.remove('dark'); }
            var savedTheme = localStorage.getItem('theme') || 'light'; applyTheme(savedTheme);
            var themeBtn = document.getElementById('themeToggleAudit');
            themeBtn?.addEventListener('click', function(){ var cur = document.documentElement.classList.contains('dark') ? 'dark' : 'light'; var next = (cur==='dark') ? 'light' : 'dark'; applyTheme(next); localStorage.setItem('theme', next); });
        })();
    </script>
</body>
</html>

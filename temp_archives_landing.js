// Sidebar toggle functionality
const sidebarToggle = document.getElementById('sidebar-toggle');
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const sidebar = document.getElementById('sidebar');
const mobileSidebar = document.getElementById('mobile-sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');
const closeMobileSidebar = document.getElementById('close-mobile-sidebar');

// Desktop sidebar toggle
sidebarToggle?.addEventListener('click', () => {
    sidebar?.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', sidebar?.classList.contains('sidebar-collapsed'));
});

// Mobile sidebar toggle
mobileMenuBtn?.addEventListener('click', () => {
    mobileSidebar?.classList.remove('-translate-x-full');
    sidebarOverlay?.classList.remove('opacity-0', 'pointer-events-none');
    sidebarOverlay?.classList.add('opacity-100', 'pointer-events-auto');
});

closeMobileSidebar?.addEventListener('click', () => {
    mobileSidebar?.classList.add('-translate-x-full');
    sidebarOverlay?.classList.add('opacity-0', 'pointer-events-none');
    sidebarOverlay?.classList.remove('opacity-100', 'pointer-events-auto');
});

sidebarOverlay?.addEventListener('click', () => {
    mobileSidebar?.classList.add('-translate-x-full');
    sidebarOverlay?.classList.add('opacity-0', 'pointer-events-none');
    sidebarOverlay?.classList.remove('opacity-100', 'pointer-events-auto');
});

// Profile dropdown
const profileBtn = document.getElementById('profile-btn');
const profileDropdown = document.getElementById('profile-dropdown');

// Notification dropdown (simple toggle)
const notifBtn = document.getElementById('notification-btn');
const notifDropdown = document.getElementById('notification-dropdown');
const notifCount = document.getElementById('notif-count');

profileBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    notifDropdown?.classList.add('hidden');
    profileDropdown?.classList.toggle('hidden');
});

notifBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    profileDropdown?.classList.add('hidden');
    notifDropdown?.classList.toggle('hidden');
    try {
        var ids = Array.from(document.querySelectorAll('#notif-list [data-id]')).map(function(el){ return el.getAttribute('data-id'); });
        if (ids.length > 0) {
            fetch('notifications_log.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'event_type='+encodeURIComponent('alert_shown')+'&ids='+encodeURIComponent(JSON.stringify(ids))
            }).then(function(){});
        }
    } catch(e){}
});

document.addEventListener('click', (e) => {
    if (!e.target.closest || !e.target.closest('#profile-dropdown')) {
        profileDropdown?.classList.add('hidden');
    }
    if (!e.target.closest || !e.target.closest('#notification-dropdown')) {
        notifDropdown?.classList.add('hidden');
    }
});

// Restore sidebar state
if (localStorage.getItem('sidebarCollapsed') === 'true') {
    sidebar?.classList.add('sidebar-collapsed');
}

// Render Latest Files (fetched from server once on load)
(function () {
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function fmtBytes(b) {
        if (!b || b <= 0) return '';
        var u = ['B', 'KB', 'MB', 'GB', 'TB'];
        var e = Math.floor(Math.log(b) / Math.log(1024));
        return (b / Math.pow(1024, e)).toFixed(1) + '\u00a0' + u[e];
    }

    // Simple colour map for legislative types
    var TYPE_COLOUR = {
        'Ordinance':        'text-orange-600 dark:text-orange-400',
        'Resolution':       'text-blue-600   dark:text-blue-400',
        'Public Hearing':   'text-green-600  dark:text-green-400',
        'Meeting':          'text-purple-600 dark:text-purple-400',
        'Billing':          'text-red-600    dark:text-red-400'
    };

    function buildRow(file) {
        var title    = escapeHtml(file.title  || 'Untitled');
        var size     = fmtBytes(file.size_bytes);
        var date     = escapeHtml(file.date   || '');
        var iconCls  = 'bi bi-file-earmark-text text-gray-500 dark:text-gray-400';
        var metaParts = [];

        var href = '#';

        if (file.source === 'archive') {
            iconCls = 'bi bi-file-earmark text-blue-500';
            if (file.folder_name) metaParts.push(escapeHtml(file.folder_name));
            if (size)             metaParts.push(size);
            if (date)             metaParts.push(date);
            href = 'folder_view.php?id=' + encodeURIComponent(file.folder_id || '') + '&highlight=' + encodeURIComponent(file.id || '');
        } else {
            // legislative
            var tc = TYPE_COLOUR[file.type] || 'text-gray-500 dark:text-gray-400';
            iconCls = 'bi bi-file-text ' + tc;
            if (file.type)   metaParts.push('<span class="font-medium">' + escapeHtml(file.type) + '</span>');
            if (file.author) metaParts.push(escapeHtml(file.author));
            if (size)        metaParts.push(size);
            if (date)        metaParts.push(date);
            href = file.folder_id
                ? 'folder_view.php?id=' + encodeURIComponent(file.folder_id) + '&legislative=true&highlight=' + encodeURIComponent(file.id || '')
                : '#';
        }

        var meta = metaParts.join(' <span class="opacity-40">┬╖</span> ');

        return '<a href="' + href + '" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700/60 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-slate-600 group">' +
            '<i class="' + iconCls + ' text-xl flex-shrink-0"></i>' +
            '<div class="min-w-0 flex-1">' +
                '<div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate" title="' + title + '">' + title + '</div>' +
                '<div class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">' + meta + '</div>' +
            '</div>' +
        '</a>';
    }

    function renderLatest() {
        var container = document.getElementById('latestFilesList');
        if (!container) return;

        // Show loading state immediately so skeleton / stale content is never visible
        container.innerHTML =
            '<div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">' +
            '<i class="bi bi-arrow-clockwise text-lg block mb-1 opacity-60"></i>Loading recent filesΓÇª</div>';

        fetch('fetch_latest_files.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && Array.isArray(data.files) && data.files.length > 0) {
                    container.innerHTML = data.files.map(buildRow).join('');
                } else {
                    container.innerHTML =
                        '<div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">' +
                        '<i class="bi bi-folder2-open text-2xl block mb-2 opacity-50"></i>No recent files found.</div>';
                }
            })
            .catch(function () {
                container.innerHTML =
                    '<div class="py-6 text-center text-sm text-red-500 dark:text-red-400">' +
                    '<i class="bi bi-exclamation-circle text-xl block mb-1"></i>Could not load files. Please refresh.</div>';
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderLatest);
    } else {
        renderLatest();
    }
    // Expose so other scripts (e.g. after upload) can trigger a refresh manually
    window.refreshLatestFiles = renderLatest;
})();

(function(){
    var storageKey = 'archive_opened_at';
    function getMap() {
        try {
            var raw = localStorage.getItem(storageKey);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }
    function setMap(map) {
        try {
            localStorage.setItem(storageKey, JSON.stringify(map));
        } catch (e) {}
    }
    function formatTime(value) {
        var date = new Date(value);
        if (Number.isNaN(date.getTime())) return null;
        var now = Date.now();
        var diffMs = Math.max(0, now - date.getTime());
        var seconds = Math.floor(diffMs / 1000);
        if (seconds < 60) return 'just now';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + (minutes === 1 ? ' minute ago' : ' minutes ago');
        var hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + (hours === 1 ? ' hour ago' : ' hours ago');
        var days = Math.floor(hours / 24);
        if (days < 30) return days + (days === 1 ? ' day ago' : ' days ago');
        var months = Math.floor(days / 30);
        if (months < 12) return months + (months === 1 ? ' month ago' : ' months ago');
        var years = Math.floor(months / 12);
        return years + (years === 1 ? ' year ago' : ' years ago');
    }
    function updateMeta(map) {
        document.querySelectorAll('[data-archive-meta]').forEach(function(el){
            var id = el.getAttribute('data-archive-meta');
            var stored = map[id];
            var formatted = stored ? formatTime(stored) : null;
            el.textContent = formatted ? ('Last opened: ' + formatted) : 'Last opened: Not yet opened';
        });
    }
    function bindClicks(map) {
        document.querySelectorAll('a[data-archive]').forEach(function(link){
            link.addEventListener('click', function(){
                var id = link.getAttribute('data-archive');
                if (!id) return;
                map[id] = Date.now();
                setMap(map);
                updateMeta(map);
            });
        });
    }
    function init() {
        var map = getMap();
        updateMeta(map);
        bindClicks(map);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

// Notifications: fetch latest and unread count (grouped) + mark all read
(function(){
    function groupItems(items) {
        var groups = { Security: [], Export: [], Activity: [], Other: [] };
        (items||[]).forEach(function(n){
            var key = String(n.about || '').toLowerCase();
            if (key.indexOf('security') !== -1) groups.Security.push(n);
            else if (key.indexOf('export') !== -1) groups.Export.push(n);
            else if (key.indexOf('login') !== -1 || key.indexOf('download') !== -1 || key.indexOf('activity') !== -1) groups.Activity.push(n);
            else groups.Other.push(n);
        });
        return groups;
    }
    function renderGroup(title, items){
        if (!items.length) return '';
        var section = '<div class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500 mt-2 mb-1 px-1">'+title+'</div>';
        section += items.map(renderItem).join('');
        return section;
    }
    function renderItem(n){
        var href = n.link ? n.link : ('audit-logs.php?id='+encodeURIComponent(n.id));
        var badge = (n.status === 'unread') ? ' ring-2 ring-red-200' : '';
        var textWeight = (n.status === 'unread') ? 'font-semibold' : 'font-medium';
        return '<a href="'+href+'" data-id="'+n.id+'" class="flex items-center space-x-3 py-2 border-b border-gray-200 dark:border-slate-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-md'+badge+'">'+
               '<div class="flex-shrink-0"><span class="block w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">'+
               '<i class="bi bi-bell text-red-600 dark:text-red-400"></i></span></div>'+
               '<div class="flex-1 min-w-0">'+
               '<p class="text-sm '+textWeight+' text-gray-800 dark:text-gray-200 truncate">'+escapeHtml(n.content)+'</p>'+
               '<p class="text-xs text-gray-500 dark:text-gray-400">'+escapeHtml(n.date)+' '+escapeHtml(n.time)+'</p>'+
               '</div></a>';
    }
    function renderNotifList(items){
        var container = document.getElementById('notif-list');
        if (!container) return;
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="text-sm text-gray-600 dark:text-gray-400">No notifications</div>';
            return;
        }
        var g = groupItems(items);
        var html = '';
        html += renderGroup('Security', g.Security);
        html += renderGroup('Export', g.Export);
        html += renderGroup('Activity', g.Activity);
        html += renderGroup('Other', g.Other);
        html += '<div class="pt-2"><button id="mark-all-read" class="w-full px-3 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200">Mark all as read</button></div>';
        container.innerHTML = html;
        container.querySelectorAll('a[data-id]').forEach(function(a){
            a.addEventListener('click', function(){
                var id = a.getAttribute('data-id');
                try {
                    fetch('notifications_update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id='+encodeURIComponent(id)+'&status=read'
                    }).then(function(){});
                } catch(e){}
                a.classList.remove('ring-2','ring-red-200');
                var p = a.querySelector('p.text-sm');
                if (p) { p.classList.remove('font-semibold'); p.classList.add('font-medium'); }
            });
        });
        var btnAll = document.getElementById('mark-all-read');
        if (btnAll) {
            btnAll.addEventListener('click', function(){
                var anchors = Array.from(container.querySelectorAll('a[data-id]'));
                anchors.forEach(function(a){
                    var id = a.getAttribute('data-id');
                    try {
                        fetch('notifications_update.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'id='+encodeURIComponent(id)+'&status=read'
                        }).then(function(){});
                    } catch(e){}
                    a.classList.remove('ring-2','ring-red-200');
                    var p = a.querySelector('p.text-sm');
                    if (p) { p.classList.remove('font-semibold'); p.classList.add('font-medium'); }
                });
                notifCount && (notifCount.textContent = '0', notifCount.style.display = 'none');
            });
        }
    }
    function escapeHtml(s){
        if (typeof s !== 'string') return '';
        return s.replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]); });
    }
    function fetchLatest(){
        fetch('notifications_fetch.php?page_size=5&page=1').then(function(r){ return r.json(); }).then(function(d){
            if (d && d.success) renderNotifList(d.items||[]);
        }).catch(function(){});
    }
    function fetchUnread(){
        fetch('notifications_fetch.php?status=unread&page_size=1&page=1').then(function(r){ return r.json(); }).then(function(d){
            if (!notifCount) return;
            var total = (d && d.success) ? (d.total||0) : 0;
            notifCount.textContent = String(total);
            notifCount.style.display = total > 0 ? 'inline-flex' : 'none';
        }).catch(function(){});
    }
    function refresh(){
        fetchLatest();
        fetchUnread();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refresh);
    } else {
        refresh();
    }
    window.addEventListener('focus', refresh);
})();

// Keyboard shortcuts
(function(){
    var buffer = [];
    function focusSearch(){
        var el = document.getElementById('legislativeSearchInput');
        if (el) { el.focus(); el.select && el.select(); }
    }
    function handle(e){
        var tag = (e.target && (e.target.tagName||'')).toLowerCase();
        var inInput = tag === 'input' || tag === 'textarea' || e.target.isContentEditable;
        if (!inInput && e.key === '/') {
            e.preventDefault();
            focusSearch();
            return;
        }
        buffer.push(e.key.toLowerCase());
        if (buffer.length > 2) buffer.shift();
        if (!inInput && buffer.join(' ') === 'g r') {
            window.location.href = 'report_analytics.php';
        }
    }
    document.addEventListener('keydown', handle);
})();

// Layout and compact mode toggles
(function(){
    var grid = document.getElementById('foldersGrid');
    function applyLayout(mode){
        if (!grid) return;
        var isList = mode === 'list';
        grid.classList.toggle('sm:grid-cols-2', !isList);
        grid.classList.toggle('lg:grid-cols-4', !isList);
    }
    function loadLayout(){
        var m = localStorage.getItem('dashboardLayout') || 'grid';
        applyLayout(m);
    }
    function toggleLayout(){
        var m = localStorage.getItem('dashboardLayout') || 'grid';
        var next = m === 'grid' ? 'list' : 'grid';
        localStorage.setItem('dashboardLayout', next);
        applyLayout(next);
    }
    var layoutBtn = document.getElementById('layoutToggle');
    layoutBtn && layoutBtn.addEventListener('click', toggleLayout);
    loadLayout();

    function applyCompact(flag){
        document.body.classList.toggle('compact', !!flag);
    }
    function loadCompact(){
        var c = localStorage.getItem('compactMode') === 'true';
        applyCompact(c);
    }
    function toggleCompact(){
        var c = localStorage.getItem('compactMode') === 'true';
        var next = !c;
        localStorage.setItem('compactMode', String(next));
        applyCompact(next);
    }
    var compactBtn = document.getElementById('compactToggle');
    compactBtn && compactBtn.addEventListener('click', toggleCompact);
    loadCompact();
})();

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

// Render Recent Views on landing
(function(){
    function renderRecent() {
        if (window.RecentViews && typeof window.RecentViews.renderTo === 'function') {
            window.RecentViews.renderTo('latestFilesList');
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderRecent);
    } else {
        renderRecent();
    }
    window.addEventListener('focus', renderRecent);
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

// Notifications: fetch latest and unread count
(function(){
    function renderNotifList(items){
        var container = document.getElementById('notif-list');
        if (!container) return;
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="text-sm text-gray-600 dark:text-gray-400">No notifications</div>';
            return;
        }
        var html = items.map(function(n){
            var href = n.link ? n.link : ('audit-logs.php?id='+encodeURIComponent(n.id));
            var badge = '';
            var textWeight = (n.status === 'unread') ? 'font-semibold' : 'font-medium';
            if (n.status === 'unread') badge = ' ring-2 ring-red-200';
            return '<a href="'+href+'" data-id="'+n.id+'" class="flex items-center space-x-3 py-2 border-b border-gray-200 dark:border-slate-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-md'+badge+'">'+
                   '<div class="flex-shrink-0"><span class="block w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">'+
                   '<i class="bi bi-bell text-red-600 dark:text-red-400"></i></span></div>'+
                   '<div class="flex-1 min-w-0">'+
                   '<p class="text-sm '+textWeight+' text-gray-800 dark:text-gray-200 truncate">'+escapeHtml(n.content)+'</p>'+
                   '<p class="text-xs text-gray-500 dark:text-gray-400">'+escapeHtml(n.date)+' '+escapeHtml(n.time)+'</p>'+
                   '</div></a>';
        }).join('');
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

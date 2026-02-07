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

profileBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    profileDropdown?.classList.toggle('hidden');
});

document.addEventListener('click', () => {
    profileDropdown?.classList.add('hidden');
});

// Notification dropdown (simple toggle)
const notifBtn = document.getElementById('notification-btn');
const notifDropdown = document.getElementById('notification-dropdown');
const notifCount = document.getElementById('notif-count');

notifBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
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
    // keep existing profile behavior
    // hide notification dropdown when clicking outside
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
            if (n.status === 'unread') badge = ' ring-2 ring-red-200';
            return '<a href="'+href+'" data-id="'+n.id+'" class="flex items-center space-x-3 py-2 border-b border-gray-200 dark:border-slate-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-md'+badge+'">'+
                   '<div class="flex-shrink-0"><span class="block w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">'+
                   '<i class="bi bi-bell text-red-600 dark:text-red-400"></i></span></div>'+
                   '<div class="flex-1 min-w-0">'+
                   '<p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">'+escapeHtml(n.content)+'</p>'+
                   '<p class="text-xs text-gray-500 dark:text-gray-400">'+escapeHtml(n.date)+' '+escapeHtml(n.time)+'</p>'+
                   '</div></a>';
        }).join('');
        container.innerHTML = html;
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

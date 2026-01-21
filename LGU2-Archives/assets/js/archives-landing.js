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

notifBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    notifDropdown?.classList.toggle('hidden');
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

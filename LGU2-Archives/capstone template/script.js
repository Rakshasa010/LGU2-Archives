// LRMS System JavaScript - Updated with Mobile Support and Responsive Features

// Theme Toggle
function initThemeToggle() {
    const html = document.documentElement;
    
    // Check for saved theme preference and apply on load
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        html.classList.add('dark');
        updateThemeIcon(true);
    } else {
        updateThemeIcon(false);
    }
    // Note: Click handler is set via onclick="toggleTheme()" in HTML
}

function updateThemeIcon(isDark) {
    const darkIcon = document.querySelector('.dark-mode-icon');
    const lightIcon = document.querySelector('.light-mode-icon');
    
    if (darkIcon && lightIcon) {
        if (isDark) {
            darkIcon.classList.add('hidden');
            lightIcon.classList.remove('hidden');
        } else {
            darkIcon.classList.remove('hidden');
            lightIcon.classList.add('hidden');
        }
    }
}

// Mobile Sidebar Toggle with Animations
function initMobileSidebar() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const closeMobileSidebar = document.getElementById('close-mobile-sidebar');
    
    function openMobileSidebar() {
        // Show overlay with fade and blur
        sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
        sidebarOverlay.classList.add('opacity-100', 'pointer-events-auto');
        
        // Slide in sidebar
        mobileSidebar.classList.remove('-translate-x-full');
        mobileSidebar.classList.add('translate-x-0');
        
        // Animate menu items with stagger effect
        const menuItems = mobileSidebar.querySelectorAll('nav a, nav > div');
        menuItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                item.style.transition = 'all 0.3s ease-out';
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, 50 + (index * 30));
        });
        
        // Animate header
        const header = mobileSidebar.querySelector('.sidebar-header');
        if (header) {
            header.style.opacity = '0';
            header.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                header.style.transition = 'all 0.3s ease-out';
                header.style.opacity = '1';
                header.style.transform = 'translateY(0)';
            }, 100);
        }
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }
    
    function closeMobileSidebarFn() {
        // Hide overlay with fade
        sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
        sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
        
        // Slide out sidebar
        mobileSidebar.classList.add('-translate-x-full');
        mobileSidebar.classList.remove('translate-x-0');
        
        // Restore body scroll
        document.body.style.overflow = '';
    }
    
    mobileMenuBtn?.addEventListener('click', openMobileSidebar);
    closeMobileSidebar?.addEventListener('click', closeMobileSidebarFn);
    sidebarOverlay?.addEventListener('click', closeMobileSidebarFn);
    
    // Close sidebar on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileSidebar && !mobileSidebar.classList.contains('-translate-x-full')) {
            closeMobileSidebarFn();
        }
    });
}

// Desktop Sidebar Toggle
function initDesktopSidebarToggle() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.flex-1.flex.flex-col.overflow-hidden');
    
    if (!sidebarToggle || !sidebar) return;
    
    // Check saved preference - default to expanded
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    // Ensure sidebar is visible on desktop
    if (window.innerWidth >= 768) {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        
        if (isCollapsed) {
            sidebar.classList.remove('sidebar-expanded', 'w-64');
            sidebar.classList.add('sidebar-collapsed');
            sidebarToggle.classList.add('sidebar-hidden');
        } else {
            sidebar.classList.add('sidebar-expanded', 'w-64');
            sidebar.classList.remove('sidebar-collapsed');
        }
    }
    
    sidebarToggle.addEventListener('click', function() {
        const isExpanded = sidebar.classList.contains('sidebar-expanded');
        
        // Add button press animation
        this.style.transform = 'scale(0.9)';
        setTimeout(() => {
            this.style.transform = '';
        }, 150);
        
        if (isExpanded) {
            // Collapse sidebar with animation
            sidebar.classList.remove('sidebar-expanded', 'w-64');
            sidebar.classList.add('sidebar-collapsed');
            this.classList.add('sidebar-hidden');
            localStorage.setItem('sidebarCollapsed', 'true');
            
            // Animate main content expansion
            if (mainContent) {
                mainContent.style.transform = 'scale(1.005)';
                setTimeout(() => {
                    mainContent.style.transform = '';
                }, 400);
            }
        } else {
            // Expand sidebar with animation
            sidebar.classList.remove('sidebar-collapsed');
            sidebar.classList.add('sidebar-expanded', 'w-64');
            this.classList.remove('sidebar-hidden');
            localStorage.setItem('sidebarCollapsed', 'false');
        }
    });
}

// Dropdown Toggle (Updated to handle multiple dropdowns)
function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    const icon = document.getElementById(dropdownId + '-icon');
    
    // Close all other dropdowns
    document.querySelectorAll('[id$="-dropdown"]').forEach(d => {
        if (d.id !== dropdownId && d.classList.contains('show')) {
            d.classList.remove('show');
            d.classList.add('hidden');
        }
    });
    
    if (dropdown) {
        if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            dropdown.classList.add('hidden');
            if (icon) icon.classList.remove('rotate');
        } else {
            dropdown.classList.remove('hidden');
            dropdown.classList.add('show');
            if (icon) icon.classList.add('rotate');
        }
    }
}

// Mobile Sidebar Toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
}

// Clear All Notifications
function clearAllNotifications() {
    const notificationsDropdown = document.getElementById('notifications-dropdown');
    const notificationItems = notificationsDropdown?.querySelectorAll('[data-notification-id]');
    notificationItems?.forEach(item => item.remove());
    showToast('All notifications cleared', 'success');
    toggleDropdown('notifications-dropdown');
}

// Focus Search
function focusSearch() {
    const searchInput = document.getElementById('quick-search');
    if (searchInput) {
        searchInput.focus();
        searchInput.select();
    }
}

// Notifications Dropdown
function initNotificationsDropdown() {
    const notifBtn = document.getElementById('notifications-btn');
    const notifDropdown = document.getElementById('notifications-dropdown');
    
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('hidden');
            // Close profile dropdown if open
            const profileDropdown = document.getElementById('profile-dropdown');
            if (profileDropdown) {
                profileDropdown.classList.add('hidden');
            }
        });
    }
}

// Profile Dropdown
function initProfileDropdown() {
    const profileBtn = document.getElementById('profile-btn');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
            // Close notifications dropdown if open
            const notifDropdown = document.getElementById('notifications-dropdown');
            if (notifDropdown) {
                notifDropdown.classList.add('hidden');
            }
        });
    }
}

// Close dropdowns when clicking outside
function initClickOutside() {
    document.addEventListener('click', (e) => {
        const notifDropdown = document.getElementById('notifications-dropdown');
        const profileDropdown = document.getElementById('profile-dropdown');
        const notifBtn = document.getElementById('notifications-btn');
        const profileBtn = document.getElementById('profile-btn');
        
        if (notifDropdown && !notifBtn.contains(e.target)) {
            notifDropdown.classList.add('hidden');
        }
        
        if (profileDropdown && !profileBtn.contains(e.target)) {
            profileDropdown.classList.add('hidden');
        }
    });
}

// Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

// Toast Notification (Updated)
// Note: showToast function has been moved and updated above in the DOMContentLoaded section
// The function below is kept for reference but the main one is used

function getToastIcon(type) {
    switch(type) {
        case 'success': return 'check-circle-fill';
        case 'error': return 'x-circle-fill';
        case 'warning': return 'exclamation-triangle-fill';
        case 'info': return 'info-circle-fill';
        default: return 'info-circle-fill';
    }
}

// Document Upload with improved feedback
function handleDocumentUpload(event) {
    if (event) event.preventDefault();
    showToast('Document uploaded successfully!', 'success');
    closeModal('upload-modal');
}

// File select handler
function handleFileSelect(event) {
    const file = event.target.files[0];
    const fileName = document.getElementById('file-name');
    if (fileName && file) {
        fileName.textContent = file.name;
        fileName.classList.add('text-green-600', 'font-medium');
    }
}

// Search Functionality
function initSearch() {
    const searchInput = document.getElementById('quick-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            console.log('Searching for:', query);
            // Implement search logic here
        });
    }
}

// Delete Confirmation
function confirmDelete(itemName) {
    if (confirm(`Are you sure you want to delete "${itemName}"?`)) {
        showToast(`"${itemName}" has been deleted.`, 'success');
        return true;
    }
    return false;
}

// Edit Item
function editItem(itemId) {
    console.log('Editing item:', itemId);
    showToast('Edit functionality would open here', 'info');
}

// View Item
function viewItem(itemId) {
    console.log('Viewing item:', itemId);
    showToast('View functionality would open here', 'info');
}

// Navigation
function navigateTo(section) {
    console.log('Navigating to:', section);
    
    // Remove active class from all nav items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to clicked item
    event.target.closest('.nav-item').classList.add('active');
    
    // Hide all sections
    document.querySelectorAll('[id$="-section"]').forEach(section => {
        section.classList.add('hidden');
    });
    
    // Show selected section
    const targetSection = document.getElementById(section + '-section');
    if (targetSection) {
        targetSection.classList.remove('hidden');
    }
}

// Filter Table
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (input && table) {
        input.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            }
        });
    }
}

// Sort Table
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        return aText.localeCompare(bText);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Export Data
function exportData(format) {
    console.log('Exporting data as:', format);
    showToast(`Exporting data as ${format.toUpperCase()}...`, 'info');
    setTimeout(() => {
        showToast(`Data exported successfully as ${format.toUpperCase()}!`, 'success');
    }, 2000);
}

// Mark Notification as Read
function markAsRead(notificationId) {
    console.log('Marking notification as read:', notificationId);
    const notifElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
    if (notifElement) {
        notifElement.classList.remove('unread');
        notifElement.style.opacity = '0.6';
    }
}

// Clear All Notifications
function clearAllNotifications() {
    if (confirm('Are you sure you want to clear all notifications?')) {
        const notifList = document.querySelector('#notifications-dropdown .space-y-2');
        if (notifList) {
            notifList.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No notifications</p>';
        }
        showToast('All notifications cleared', 'success');
    }
}

// Initialize Chart (using Chart.js if available)
function initCharts() {
    // Example chart initialization
    if (typeof Chart !== 'undefined') {
        const ctx = document.getElementById('activity-chart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Documents',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }
}

// Auto-expand dropdown if a sub-item is active
function autoExpandActiveDropdown() {
    const activeSubItem = document.querySelector('.nav-item-sub.active');
    if (activeSubItem) {
        const dropdown = activeSubItem.closest('.dropdown-content');
        if (dropdown) {
            dropdown.classList.remove('hidden');
            dropdown.classList.add('show');
            const dropdownId = dropdown.id;
            const icon = document.getElementById(dropdownId + '-icon');
            if (icon) {
                icon.classList.add('rotate');
            }
        }
    }
}

// Show Section - Navigate between sections
function showSection(sectionId) {
    console.log('Showing section:', sectionId);
    
    // Update page title
    const pageTitle = document.querySelector('nav h2');
    if (pageTitle) {
        const titles = {
            'dashboard': 'Dashboard',
            'documents': 'Document Management',
            'search': 'Advanced Search',
            'analytics': 'Reports & Analytics',
            'users': 'User Management',
            'audit': 'Audit Logs',
            'profile': 'My Profile',
            'settings': 'Settings',
            'help': 'Help & Support'
        };
        pageTitle.textContent = titles[sectionId] || 'Dashboard';
    }
    
    // Update active nav item on desktop sidebar
    document.querySelectorAll('#sidebar .nav-item').forEach(item => {
        item.classList.remove('active', 'bg-red-700');
        if (item.getAttribute('data-section') === sectionId) {
            item.classList.add('active');
        }
    });
    
    // Update active nav item on mobile sidebar
    document.querySelectorAll('#mobile-sidebar nav a').forEach(item => {
        item.classList.remove('bg-red-700');
    });
    const mobileActiveItem = document.querySelector(`#mobile-sidebar nav a[onclick*="${sectionId}"]`);
    if (mobileActiveItem) {
        mobileActiveItem.classList.add('bg-red-700');
    }
    
    // Close mobile sidebar if open
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    if (mobileSidebar && !mobileSidebar.classList.contains('-translate-x-full')) {
        mobileSidebar.classList.add('-translate-x-full');
        mobileSidebar.classList.remove('translate-x-0');
        sidebarOverlay?.classList.add('opacity-0', 'pointer-events-none');
        sidebarOverlay?.classList.remove('opacity-100', 'pointer-events-auto');
        document.body.style.overflow = '';
    }
    
    // Show toast for demo purposes
    showToast(`Navigated to ${sectionId.charAt(0).toUpperCase() + sectionId.slice(1)}`, 'info');
    
    return false;
}

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        showToast('Logging out...', 'info');
        
        // Clear any stored session data
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('currentUser');
        sessionStorage.clear();
        
        // Redirect to login page with logout success message
        setTimeout(() => {
            window.location.href = 'login.html?logout=success';
        }, 1000);
    }
    return false;
}

// Toggle Notifications Dropdown
function toggleNotifications() {
    const notifDropdown = document.getElementById('notifications-dropdown');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    // Close profile dropdown if open
    if (profileDropdown) {
        profileDropdown.classList.add('hidden');
    }
    
    // Toggle notifications dropdown
    if (notifDropdown) {
        notifDropdown.classList.toggle('hidden');
    }
}

// Toggle Profile Menu
function toggleProfileMenu() {
    const notifDropdown = document.getElementById('notifications-dropdown');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    // Close notifications dropdown if open
    if (notifDropdown) {
        notifDropdown.classList.add('hidden');
    }
    
    // Toggle profile dropdown
    if (profileDropdown) {
        profileDropdown.classList.toggle('hidden');
    }
}

// Toggle Theme (for onclick attribute)
function toggleTheme() {
    const html = document.documentElement;
    html.classList.toggle('dark');
    const isDark = html.classList.contains('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateThemeIcon(isDark);
}

// ========================================
// Initialize all functions on DOM ready
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize theme toggle
    initThemeToggle();
    
    // Initialize mobile sidebar
    initMobileSidebar();
    
    // Initialize notifications dropdown
    initNotificationsDropdown();
    
    // Initialize profile dropdown
    initProfileDropdown();
    
    // Initialize click outside handler
    initClickOutside();
    
    console.log('All initializations complete');
});

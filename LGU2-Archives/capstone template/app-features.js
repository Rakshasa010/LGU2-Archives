// ==============================
// LLRM SYSTEM - FULL FEATURES
// ==============================

// Global Data Store
const AppData = {
    documents: [],
    users: [],
    notifications: [],
    auditLogs: [],
    currentUser: {
        id: 1,
        name: 'Admin User',
        email: 'admin@lgu.gov.ph',
        role: 'Administrator',
        profilePicture: null
    }
};

// Initialize App
document.addEventListener('DOMContentLoaded', function() {
    initializeData();
    showSection('dashboard');
    loadNotifications();
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+K for search
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            document.getElementById('quick-search').focus();
        }
    });
    
    // Setup drag and drop
    setupDragAndDrop();
});

// Initialize Sample Data
function initializeData() {
    // Sample Documents
    AppData.documents = [
        {
            id: 1,
            reference: 'ORD-2025-001',
            title: 'Annual Budget Ordinance 2025',
            type: 'ordinance',
            status: 'approved',
            date: '2025-01-15',
            uploadedBy: 'Admin User',
            uploadedAt: '2025-01-15 10:30 AM',
            fileSize: '2.5 MB',
            description: 'Annual budget allocation for fiscal year 2025',
            tags: ['budget', 'finance', '2025'],
            views: 45,
            downloads: 12
        },
        {
            id: 2,
            reference: 'RES-2025-042',
            title: 'COVID-19 Response Resolution',
            type: 'resolution',
            status: 'approved',
            date: '2025-02-01',
            uploadedBy: 'Officer User',
            uploadedAt: '2025-02-01 02:15 PM',
            fileSize: '1.8 MB',
            description: 'Resolution for enhanced COVID-19 response measures',
            tags: ['health', 'covid-19', 'emergency'],
            views: 78,
            downloads: 23
        },
        {
            id: 3,
            reference: 'SM-2025-11',
            title: 'Regular Session Minutes - November 2025',
            type: 'session',
            status: 'pending',
            date: '2025-11-20',
            uploadedBy: 'Staff User',
            uploadedAt: '2025-11-21 09:00 AM',
            fileSize: '3.2 MB',
            description: 'Minutes of the regular session held on November 20, 2025',
            tags: ['session', 'minutes', 'november'],
            views: 12,
            downloads: 3
        },
        {
            id: 4,
            reference: 'AG-2025-12',
            title: 'December Session Agenda',
            type: 'agenda',
            status: 'draft',
            date: '2025-12-01',
            uploadedBy: 'Admin User',
            uploadedAt: '2025-11-28 04:45 PM',
            fileSize: '856 KB',
            description: 'Agenda for the December regular session',
            tags: ['agenda', 'december', 'session'],
            views: 8,
            downloads: 2
        }
    ];
    
    // Sample Users
    AppData.users = [
        { id: 1, name: 'Admin User', email: 'admin@lgu.gov.ph', role: 'Administrator', status: 'active', department: 'IT Department', lastLogin: '2025-12-02 08:30 AM' },
        { id: 2, name: 'Officer Smith', email: 'officer@lgu.gov.ph', role: 'Officer', status: 'active', department: 'Legislative', lastLogin: '2025-12-02 07:15 AM' },
        { id: 3, name: 'Staff Jones', email: 'staff@lgu.gov.ph', role: 'Staff', status: 'active', department: 'Records', lastLogin: '2025-12-01 05:20 PM' },
        { id: 4, name: 'Viewer Brown', email: 'viewer@lgu.gov.ph', role: 'Viewer', status: 'inactive', department: 'Public', lastLogin: '2025-11-30 03:10 PM' }
    ];
    
    // Sample Notifications
    AppData.notifications = [
        { id: 1, title: 'New document uploaded', message: 'Ordinance No. 2025-001', time: '2 hours ago', type: 'document', read: false },
        { id: 2, title: 'Document approved', message: 'Resolution No. 2025-042', time: '5 hours ago', type: 'approval', read: false },
        { id: 3, title: 'New user registered', message: 'Viewer Brown joined the system', time: '1 day ago', type: 'user', read: true }
    ];
    
    // Sample Audit Logs
    AppData.auditLogs = [
        { id: 1, user: 'Admin User', action: 'upload', description: 'Uploaded document ORD-2025-001', timestamp: '2025-12-02 10:30 AM', ipAddress: '192.168.1.100' },
        { id: 2, user: 'Officer Smith', action: 'approve', description: 'Approved document RES-2025-042', timestamp: '2025-12-02 09:15 AM', ipAddress: '192.168.1.101' },
        { id: 3, user: 'Staff Jones', action: 'update', description: 'Updated document SM-2025-11', timestamp: '2025-12-01 04:45 PM', ipAddress: '192.168.1.102' },
        { id: 4, user: 'Admin User', action: 'delete', description: 'Deleted draft document AG-2024-15', timestamp: '2025-12-01 02:20 PM', ipAddress: '192.168.1.100' }
    ];
}

// Section Management
function showSection(sectionName) {
    const contentArea = document.getElementById('content-area');
    
    // Update active nav item
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.section === sectionName) {
            item.classList.add('active');
        }
    });
    
    // Close mobile sidebar
    if (window.innerWidth < 768) {
        toggleSidebar();
    }
    
    // Load section content
    switch(sectionName) {
        case 'dashboard':
            renderDashboard();
            break;
        case 'documents':
            renderDocuments();
            break;
        case 'search':
            renderSearch();
            break;
        case 'analytics':
            renderAnalytics();
            break;
        case 'users':
            renderUsers();
            break;
        case 'audit':
            renderAudit();
            break;
        case 'profile':
            renderProfile();
            break;
        default:
            contentArea.innerHTML = '<p class="text-gray-600">Section not found</p>';
    }
}

// ==============================
// DASHBOARD MODULE
// ==============================
function renderDashboard() {
    const totalDocs = AppData.documents.length;
    const approvedDocs = AppData.documents.filter(d => d.status === 'approved').length;
    const pendingDocs = AppData.documents.filter(d => d.status === 'pending').length;
    const activeUsers = AppData.users.filter(u => u.status === 'active').length;
    
    const html = `
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-red-600 to-red-800 rounded-2xl shadow-xl p-8 mb-6 text-white transform hover:scale-[1.01] transition-all duration-300 animate-fade-in">
            <div class="flex items-center justify-between">
                <div class="animate-slide-in-left">
                    <h1 class="text-3xl font-bold mb-2">Welcome back, ${AppData.currentUser.name}! 👋</h1>
                    <p class="text-red-100 text-lg">Here's what's happening with your legislative records today.</p>
                </div>
                <div class="hidden lg:block animate-slide-in-right">
                    <i class="bi bi-speedometer2 text-8xl opacity-20"></i>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 transform hover:scale-105 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in-up animation-delay-100 group cursor-pointer">
                <div class="flex items-center">
                    <div class="flex-shrink-0 transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <div class="bg-red-100 rounded-lg p-3">
                            <i class="bi bi-file-earmark-text text-red-600 text-3xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600 transition-colors duration-200 group-hover:text-red-600">Total Documents</p>
                        <p class="text-2xl font-bold text-gray-900 transform transition-all duration-300 group-hover:scale-110">${totalDocs}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 transform hover:scale-105 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in-up animation-delay-200 group cursor-pointer">
                <div class="flex items-center">
                    <div class="flex-shrink-0 transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <div class="bg-green-100 rounded-lg p-3">
                            <i class="bi bi-check-circle text-green-600 text-3xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600 transition-colors duration-200 group-hover:text-green-600">Approved</p>
                        <p class="text-2xl font-bold text-gray-900 transform transition-all duration-300 group-hover:scale-110">${approvedDocs}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 transform hover:scale-105 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in-up animation-delay-300 group cursor-pointer">
                <div class="flex items-center">
                    <div class="flex-shrink-0 transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <div class="bg-yellow-100 rounded-lg p-3">
                            <i class="bi bi-clock-history text-yellow-600 text-3xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600 transition-colors duration-200 group-hover:text-yellow-600">Pending</p>
                        <p class="text-2xl font-bold text-gray-900 transform transition-all duration-300 group-hover:scale-110">${pendingDocs}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 transform hover:scale-105 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in-up animation-delay-400 group cursor-pointer">
                <div class="flex items-center">
                    <div class="flex-shrink-0 transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <div class="bg-purple-100 rounded-lg p-3">
                            <i class="bi bi-people text-purple-600 text-3xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600 transition-colors duration-200 group-hover:text-purple-600">Active Users</p>
                        <p class="text-2xl font-bold text-gray-900 transform transition-all duration-300 group-hover:scale-110">${activeUsers}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Document Types Chart -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-6 transform hover:shadow-xl transition-all duration-300 animate-fade-in-up animation-delay-500">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Documents by Type</h2>
                <div class="chart-container" style="position: relative; height: 280px; max-height: 280px;">
                    <canvas id="documentTypesChart"></canvas>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-600">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h2>
                <div class="space-y-3">
                    <button onclick="openModal('upload-modal')" class="btn-primary w-full flex items-center justify-center">
                        <i class="bi bi-upload mr-2"></i>Upload Document
                    </button>
                    <button onclick="showSection('search')" class="btn-outline w-full flex items-center justify-center">
                        <i class="bi bi-search mr-2"></i>Advanced Search
                    </button>
                    <button onclick="showSection('analytics')" class="btn-outline w-full flex items-center justify-center">
                        <i class="bi bi-bar-chart mr-2"></i>View Reports
                    </button>
                    <button onclick="showSection('users')" class="btn-outline w-full flex items-center justify-center">
                        <i class="bi bi-people mr-2"></i>Manage Users
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Documents -->
        <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-800">Recent Documents</h2>
                <button onclick="showSection('documents')" class="text-sm text-red-600 hover:text-red-700 font-medium">View All →</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        ${AppData.documents.slice(0, 5).map(doc => `
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">${doc.reference}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">${doc.title}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">${capitalizeFirstLetter(doc.type)}</td>
                                <td class="px-6 py-4">${getStatusBadge(doc.status)}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">${doc.date}</td>
                                <td class="px-6 py-4 text-sm">
                                    <button onclick="viewDocument(${doc.id})" class="text-red-600 hover:text-red-700 mr-2" title="View">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('content-area').innerHTML = html;
    
    // Initialize chart
    setTimeout(() => renderDocumentTypesChart(), 100);
}

function renderDocumentTypesChart() {
    const ctx = document.getElementById('documentTypesChart');
    if (!ctx) return;
    
    const typeCounts = {};
    AppData.documents.forEach(doc => {
        typeCounts[doc.type] = (typeCounts[doc.type] || 0) + 1;
    });
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(typeCounts).map(t => capitalizeFirstLetter(t)),
            datasets: [{
                data: Object.values(typeCounts),
                backgroundColor: [
                    '#dc2626',
                    '#16a34a',
                    '#2563eb',
                    '#f59e0b',
                    '#8b5cf6'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
}

// ==============================
// DOCUMENTS MODULE
// ==============================
function renderDocuments() {
    const html = `
        <div class="mb-6 animate-fade-in">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h1 class="text-2xl font-bold text-gray-800">Document Management</h1>
                <button onclick="openModal('upload-modal')" class="btn-primary">
                    <i class="bi bi-upload mr-2"></i>Upload New Document
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-100">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <select id="filterType" class="input-field" onchange="filterDocuments()">
                    <option value="">All Types</option>
                    <option value="ordinance">Ordinance</option>
                    <option value="resolution">Resolution</option>
                    <option value="session">Session Minutes</option>
                    <option value="agenda">Agenda</option>
                </select>
                <select id="filterStatus" class="input-field" onchange="filterDocuments()">
                    <option value="">All Status</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="draft">Draft</option>
                </select>
                <input type="text" id="searchDocs" class="input-field" placeholder="Search documents..." oninput="filterDocuments()">
                <button onclick="resetFilters()" class="btn-outline">
                    <i class="bi bi-arrow-clockwise mr-2"></i>Reset
                </button>
            </div>
        </div>

        <!-- Documents Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade-in-up animation-delay-200">
            <div class="overflow-x-auto">
                <table class="min-w-full" id="documentsTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100" onclick="sortDocuments('reference')">
                                Reference <i class="bi bi-arrow-down-up text-xs"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100" onclick="sortDocuments('title')">
                                Title <i class="bi bi-arrow-down-up text-xs"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100" onclick="sortDocuments('type')">
                                Type <i class="bi bi-arrow-down-up text-xs"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100" onclick="sortDocuments('status')">
                                Status <i class="bi bi-arrow-down-up text-xs"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100" onclick="sortDocuments('date')">
                                Date <i class="bi bi-arrow-down-up text-xs"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="documentsList" class="divide-y divide-gray-200">
                        <!-- Populated by filterDocuments() -->
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('content-area').innerHTML = html;
    filterDocuments();
}

function filterDocuments() {
    const typeFilter = document.getElementById('filterType')?.value || '';
    const statusFilter = document.getElementById('filterStatus')?.value || '';
    const searchTerm = document.getElementById('searchDocs')?.value.toLowerCase() || '';
    
    let filtered = AppData.documents.filter(doc => {
        const matchesType = !typeFilter || doc.type === typeFilter;
        const matchesStatus = !statusFilter || doc.status === statusFilter;
        const matchesSearch = !searchTerm || 
            doc.title.toLowerCase().includes(searchTerm) ||
            doc.reference.toLowerCase().includes(searchTerm) ||
            doc.description.toLowerCase().includes(searchTerm);
        
        return matchesType && matchesStatus && matchesSearch;
    });
    
    const tbody = document.getElementById('documentsList');
    if (!tbody) return;
    
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No documents found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtered.map(doc => `
        <tr class="hover:bg-gray-50 transition">
            <td class="px-6 py-4 text-sm font-medium text-gray-900">${doc.reference}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${doc.title}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${capitalizeFirstLetter(doc.type)}</td>
            <td class="px-6 py-4">${getStatusBadge(doc.status)}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${formatDate(doc.date)}</td>
            <td class="px-6 py-4 text-sm space-x-2">
                <button onclick="viewDocument(${doc.id})" class="text-blue-600 hover:text-blue-700" title="View">
                    <i class="bi bi-eye"></i>
                </button>
                <button onclick="editDocument(${doc.id})" class="text-green-600 hover:text-green-700" title="Edit">
                    <i class="bi bi-pencil"></i>
                </button>
                <button onclick="deleteDocument(${doc.id})" class="text-red-600 hover:text-red-700" title="Delete">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function sortDocuments(field) {
    AppData.documents.sort((a, b) => {
        if (a[field] < b[field]) return -1;
        if (a[field] > b[field]) return 1;
        return 0;
    });
    filterDocuments();
}

function resetFilters() {
    document.getElementById('filterType').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('searchDocs').value = '';
    filterDocuments();
}

function viewDocument(id) {
    const doc = AppData.documents.find(d => d.id === id);
    if (!doc) return;
    
    document.getElementById('view-title').textContent = doc.title;
    document.getElementById('view-reference').textContent = doc.reference;
    document.getElementById('view-type').textContent = capitalizeFirstLetter(doc.type);
    document.getElementById('view-status').innerHTML = getStatusBadge(doc.status);
    document.getElementById('view-date').textContent = formatDate(doc.date);
    document.getElementById('view-uploaded-by').textContent = doc.uploadedBy;
    document.getElementById('view-uploaded-at').textContent = doc.uploadedAt;
    document.getElementById('view-size').textContent = doc.fileSize;
    document.getElementById('view-views').textContent = doc.views;
    document.getElementById('view-downloads').textContent = doc.downloads;
    document.getElementById('view-description').textContent = doc.description;
    document.getElementById('view-tags').innerHTML = doc.tags.map(tag => 
        `<span class="tag">${tag}</span>`
    ).join('');
    
    openModal('view-modal');
}

function editDocument(id) {
    const doc = AppData.documents.find(d => d.id === id);
    if (!doc) return;
    
    showNotification('Edit functionality would open a form here', 'info');
}

function deleteDocument(id) {
    if (!confirm('Are you sure you want to delete this document?')) return;
    
    const index = AppData.documents.findIndex(d => d.id === id);
    if (index > -1) {
        AppData.documents.splice(index, 1);
        filterDocuments();
        showNotification('Document deleted successfully', 'success');
        
        // Add audit log
        addAuditLog('delete', `Deleted document ID ${id}`);
    }
}

// ==============================
// SEARCH MODULE
// ==============================
function renderSearch() {
    const html = `
        <div class="mb-6 animate-fade-in">
            <h1 class="text-2xl font-bold text-gray-800">Advanced Search</h1>
            <p class="text-gray-600 mt-1">Use filters to find specific documents</p>
        </div>

        <!-- Advanced Search Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-100">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Keywords</label>
                    <input type="text" id="advSearchKeywords" class="input-field" placeholder="Search keywords...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>
                    <input type="text" id="advSearchReference" class="input-field" placeholder="e.g., ORD-2025-001">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                    <select id="advSearchType" class="input-field">
                        <option value="">All Types</option>
                        <option value="ordinance">Ordinance</option>
                        <option value="resolution">Resolution</option>
                        <option value="session">Session Minutes</option>
                        <option value="agenda">Agenda</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="advSearchStatus" class="input-field">
                        <option value="">All Status</option>
                        <option value="approved">Approved</option>
                        <option value="pending">Pending</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                    <input type="date" id="advSearchDateFrom" class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                    <input type="date" id="advSearchDateTo" class="input-field">
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button onclick="performAdvancedSearch()" class="btn-primary">
                    <i class="bi bi-search mr-2"></i>Search
                </button>
                <button onclick="clearAdvancedSearch()" class="btn-outline">
                    <i class="bi bi-x-circle mr-2"></i>Clear
                </button>
            </div>
        </div>

        <!-- Search Results -->
        <div id="searchResults" class="space-y-4">
            <!-- Results populated by performAdvancedSearch() -->
        </div>
    `;
    
    document.getElementById('content-area').innerHTML = html;
}

function performAdvancedSearch() {
    const keywords = document.getElementById('advSearchKeywords').value.toLowerCase();
    const reference = document.getElementById('advSearchReference').value.toLowerCase();
    const type = document.getElementById('advSearchType').value;
    const status = document.getElementById('advSearchStatus').value;
    const dateFrom = document.getElementById('advSearchDateFrom').value;
    const dateTo = document.getElementById('advSearchDateTo').value;
    
    const results = AppData.documents.filter(doc => {
        const matchesKeywords = !keywords || 
            doc.title.toLowerCase().includes(keywords) ||
            doc.description.toLowerCase().includes(keywords) ||
            doc.tags.some(tag => tag.toLowerCase().includes(keywords));
        
        const matchesReference = !reference || doc.reference.toLowerCase().includes(reference);
        const matchesType = !type || doc.type === type;
        const matchesStatus = !status || doc.status === status;
        const matchesDateFrom = !dateFrom || new Date(doc.date) >= new Date(dateFrom);
        const matchesDateTo = !dateTo || new Date(doc.date) <= new Date(dateTo);
        
        return matchesKeywords && matchesReference && matchesType && matchesStatus && matchesDateFrom && matchesDateTo;
    });
    
    const resultsContainer = document.getElementById('searchResults');
    
    if (results.length === 0) {
        resultsContainer.innerHTML = `
            <div class="bg-white rounded-xl shadow-md p-12 text-center animate-fade-in">
                <i class="bi bi-search text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600 text-lg">No documents found matching your search criteria</p>
            </div>
        `;
        return;
    }
    
    resultsContainer.innerHTML = `
        <div class="bg-white rounded-xl shadow-md p-6 mb-4 animate-fade-in">
            <p class="text-sm text-gray-600">Found <strong>${results.length}</strong> document(s)</p>
        </div>
        ${results.map(doc => `
            <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-all duration-300 animate-fade-in-up">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 class="text-lg font-bold text-gray-800">${doc.title}</h3>
                            ${getStatusBadge(doc.status)}
                        </div>
                        <p class="text-sm text-gray-600 mb-2">${doc.reference} • ${capitalizeFirstLetter(doc.type)} • ${formatDate(doc.date)}</p>
                        <p class="text-gray-700 mb-3">${doc.description}</p>
                        <div class="flex items-center gap-4 text-sm text-gray-500">
                            <span><i class="bi bi-eye mr-1"></i>${doc.views} views</span>
                            <span><i class="bi bi-download mr-1"></i>${doc.downloads} downloads</span>
                            <span><i class="bi bi-file-earmark mr-1"></i>${doc.fileSize}</span>
                        </div>
                        <div class="mt-3">
                            ${doc.tags.map(tag => `<span class="tag">${tag}</span>`).join('')}
                        </div>
                    </div>
                    <div class="ml-4 flex gap-2">
                        <button onclick="viewDocument(${doc.id})" class="btn-primary text-sm">
                            <i class="bi bi-eye mr-1"></i>View
                        </button>
                    </div>
                </div>
            </div>
        `).join('')}
    `;
}

function clearAdvancedSearch() {
    document.getElementById('advSearchKeywords').value = '';
    document.getElementById('advSearchReference').value = '';
    document.getElementById('advSearchType').value = '';
    document.getElementById('advSearchStatus').value = '';
    document.getElementById('advSearchDateFrom').value = '';
    document.getElementById('advSearchDateTo').value = '';
    document.getElementById('searchResults').innerHTML = '';
}

// ==============================
// ANALYTICS MODULE
// ==============================
function renderAnalytics() {
    const html = `
        <div class="mb-6 animate-fade-in">
            <h1 class="text-2xl font-bold text-gray-800">Reports & Analytics</h1>
            <p class="text-gray-600 mt-1">View detailed reports and statistics</p>
        </div>

        <!-- Analytics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Monthly Uploads</h3>
                    <i class="bi bi-graph-up text-2xl text-red-600"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900">24</p>
                <p class="text-sm text-green-600 mt-2"><i class="bi bi-arrow-up mr-1"></i>12% from last month</p>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-200">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Total Views</h3>
                    <i class="bi bi-eye text-2xl text-blue-600"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900">1,234</p>
                <p class="text-sm text-green-600 mt-2"><i class="bi bi-arrow-up mr-1"></i>8% from last month</p>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-300">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Total Downloads</h3>
                    <i class="bi bi-download text-2xl text-green-600"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900">567</p>
                <p class="text-sm text-red-600 mt-2"><i class="bi bi-arrow-down mr-1"></i>3% from last month</p>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-400">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Documents Over Time</h3>
                <canvas id="documentsOverTimeChart"></canvas>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-500">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Documents by Status</h3>
                <canvas id="documentsByStatusChart"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-600">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Top Uploaders</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-3">
                                <span class="text-red-600 font-bold">AU</span>
                            </div>
                            <span class="text-gray-800">Admin User</span>
                        </div>
                        <span class="text-gray-600 font-medium">12 documents</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                <span class="text-blue-600 font-bold">OS</span>
                            </div>
                            <span class="text-gray-800">Officer Smith</span>
                        </div>
                        <span class="text-gray-600 font-medium">8 documents</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                <span class="text-green-600 font-bold">SJ</span>
                            </div>
                            <span class="text-gray-800">Staff Jones</span>
                        </div>
                        <span class="text-gray-600 font-medium">4 documents</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-700">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Popular Documents</h3>
                <div class="space-y-3">
                    ${AppData.documents.slice(0, 5).map(doc => `
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-800">${doc.title}</p>
                                <p class="text-xs text-gray-500">${doc.reference}</p>
                            </div>
                            <span class="text-sm text-gray-600">${doc.views} views</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('content-area').innerHTML = html;
    
    setTimeout(() => {
        renderDocumentsOverTimeChart();
        renderDocumentsByStatusChart();
    }, 100);
}

function renderDocumentsOverTimeChart() {
    const ctx = document.getElementById('documentsOverTimeChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Documents Uploaded',
                data: [12, 19, 15, 25, 22, 30, 28, 35, 32, 40, 38, 45],
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220, 38, 38, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function renderDocumentsByStatusChart() {
    const ctx = document.getElementById('documentsByStatusChart');
    if (!ctx) return;
    
    const statusCounts = {
        approved: 0,
        pending: 0,
        draft: 0
    };
    
    AppData.documents.forEach(doc => {
        if (statusCounts.hasOwnProperty(doc.status)) {
            statusCounts[doc.status]++;
        }
    });
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Approved', 'Pending', 'Draft'],
            datasets: [{
                label: 'Documents',
                data: [statusCounts.approved, statusCounts.pending, statusCounts.draft],
                backgroundColor: ['#16a34a', '#f59e0b', '#6b7280']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// ==============================
// USERS MODULE
// ==============================
function renderUsers() {
    const html = `
        <div class="mb-6 animate-fade-in">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
                <button onclick="openAddUserModal()" class="btn-primary">
                    <i class="bi bi-person-plus mr-2"></i>Add New User
                </button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade-in-up animation-delay-100">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        ${AppData.users.map(user => `
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-3">
                                            <span class="text-red-600 font-bold">${getInitials(user.name)}</span>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900">${user.name}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">${user.email}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">${user.role}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">${user.department}</td>
                                <td class="px-6 py-4">${getUserStatusBadge(user.status)}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">${user.lastLogin}</td>
                                <td class="px-6 py-4 text-sm space-x-2">
                                    <button onclick="editUser(${user.id})" class="text-blue-600 hover:text-blue-700" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button onclick="toggleUserStatus(${user.id})" class="text-yellow-600 hover:text-yellow-700" title="Toggle Status">
                                        <i class="bi bi-toggle-on"></i>
                                    </button>
                                    <button onclick="deleteUser(${user.id})" class="text-red-600 hover:text-red-700" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('content-area').innerHTML = html;
}

function openAddUserModal() {
    showNotification('Add user form would appear here', 'info');
}

function editUser(id) {
    const user = AppData.users.find(u => u.id === id);
    if (!user) return;
    
    showNotification(`Edit user: ${user.name}`, 'info');
}

function toggleUserStatus(id) {
    const user = AppData.users.find(u => u.id === id);
    if (!user) return;
    
    user.status = user.status === 'active' ? 'inactive' : 'active';
    renderUsers();
    showNotification(`User status updated to ${user.status}`, 'success');
    addAuditLog('update', `Changed status of user ${user.name} to ${user.status}`);
}

function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    
    const index = AppData.users.findIndex(u => u.id === id);
    if (index > -1) {
        const user = AppData.users[index];
        AppData.users.splice(index, 1);
        renderUsers();
        showNotification('User deleted successfully', 'success');
        addAuditLog('delete', `Deleted user ${user.name}`);
    }
}

// ==============================
// AUDIT MODULE
// ==============================
function renderAudit() {
    const html = `
        <div class="mb-6 animate-fade-in">
            <h1 class="text-2xl font-bold text-gray-800">Audit Logs</h1>
            <p class="text-gray-600 mt-1">Track all system activities and changes</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-100">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <select id="filterAction" class="input-field" onchange="filterAuditLogs()">
                    <option value="">All Actions</option>
                    <option value="upload">Upload</option>
                    <option value="approve">Approve</option>
                    <option value="update">Update</option>
                    <option value="delete">Delete</option>
                </select>
                <input type="text" id="filterUser" class="input-field" placeholder="Filter by user..." oninput="filterAuditLogs()">
                <input type="date" id="filterDate" class="input-field" onchange="filterAuditLogs()">
                <button onclick="resetAuditFilters()" class="btn-outline">
                    <i class="bi bi-arrow-clockwise mr-2"></i>Reset
                </button>
            </div>
        </div>

        <!-- Audit Logs Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade-in-up animation-delay-200">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="auditLogsList" class="divide-y divide-gray-200">
                        <!-- Populated by filterAuditLogs() -->
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('content-area').innerHTML = html;
    filterAuditLogs();
}

function filterAuditLogs() {
    const actionFilter = document.getElementById('filterAction')?.value || '';
    const userFilter = document.getElementById('filterUser')?.value.toLowerCase() || '';
    const dateFilter = document.getElementById('filterDate')?.value || '';
    
    let filtered = AppData.auditLogs.filter(log => {
        const matchesAction = !actionFilter || log.action === actionFilter;
        const matchesUser = !userFilter || log.user.toLowerCase().includes(userFilter);
        const matchesDate = !dateFilter || log.timestamp.includes(dateFilter);
        
        return matchesAction && matchesUser && matchesDate;
    });
    
    const tbody = document.getElementById('auditLogsList');
    if (!tbody) return;
    
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No audit logs found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtered.map(log => `
        <tr class="hover:bg-gray-50 transition">
            <td class="px-6 py-4 text-sm text-gray-700">${log.timestamp}</td>
            <td class="px-6 py-4 text-sm font-medium text-gray-900">${log.user}</td>
            <td class="px-6 py-4">${getActionBadge(log.action)}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${log.description}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${log.ipAddress}</td>
        </tr>
    `).join('');
}

function resetAuditFilters() {
    document.getElementById('filterAction').value = '';
    document.getElementById('filterUser').value = '';
    document.getElementById('filterDate').value = '';
    filterAuditLogs();
}

function addAuditLog(action, description) {
    const newLog = {
        id: AppData.auditLogs.length + 1,
        user: AppData.currentUser.name,
        action: action,
        description: description,
        timestamp: new Date().toLocaleString(),
        ipAddress: '192.168.1.100'
    };
    
    AppData.auditLogs.unshift(newLog);
}

// ==============================
// PROFILE MODULE
// ==============================
function renderProfile() {
    const currentUser = AppData.currentUser;
    const stats = {
        documents: AppData.documents.filter(d => d.uploadedBy === currentUser.name).length,
        activities: 117,
        memberSince: 'Nov 2025',
        lastActive: '13m ago'
    };
    
    const html = `
        <!-- Profile Header Banner -->
        <div class="bg-gradient-to-r from-red-600 to-red-800 rounded-2xl shadow-xl p-8 mb-6 text-white animate-fade-in">
            <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                <!-- Profile Picture -->
                <div class="relative">
                    <input type="file" id="profilePictureInput" accept="image/*" class="hidden" onchange="handleProfilePictureUpload(event)">
                    ${currentUser.profilePicture ? 
                        `<img id="profileImage" src="${currentUser.profilePicture}" alt="Profile" class="w-32 h-32 rounded-full border-4 border-white shadow-lg object-cover">` :
                        `<div id="profileImage" class="w-32 h-32 rounded-full bg-white border-4 border-white shadow-lg flex items-center justify-center">
                            <span class="text-red-600 text-4xl font-bold">${getInitials(currentUser.name)}</span>
                        </div>`
                    }
                    <button onclick="document.getElementById('profilePictureInput').click()" class="absolute bottom-0 right-0 bg-white text-red-600 rounded-full w-10 h-10 flex items-center justify-center hover:bg-gray-100 transform hover:scale-110 transition-all duration-200 shadow-lg">
                        <i class="bi bi-camera-fill"></i>
                    </button>
                </div>
                
                <!-- User Info -->
                <div class="flex-1 text-center md:text-left">
                    <h1 class="text-3xl font-bold mb-2">${currentUser.name}</h1>
                    <p class="text-red-100 text-lg mb-3">${currentUser.email}</p>
                    <div class="flex flex-wrap gap-2 justify-center md:justify-start">
                        <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm flex items-center gap-2">
                            <i class="bi bi-person-badge"></i> ${currentUser.role}
                        </span>
                        <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm flex items-center gap-2">
                            <i class="bi bi-building"></i> IT Department
                        </span>
                        <span class="bg-green-400 bg-opacity-90 px-3 py-1 rounded-full text-sm flex items-center gap-2">
                            <i class="bi bi-check-circle-fill"></i> Active
                        </span>
                    </div>
                </div>

                <!-- Edit Profile Button -->
                <div class="flex items-center">
                    <button onclick="toggleEditMode()" class="bg-white text-red-600 px-6 py-2 rounded-lg font-medium hover:bg-gray-100 transform hover:scale-105 transition-all duration-200 shadow-lg flex items-center gap-2">
                        <i class="bi bi-pencil"></i> Edit Profile
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-100">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-600 text-sm">Documents</span>
                    <i class="bi bi-file-earmark-text text-2xl text-red-600"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900">${stats.documents}</p>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-200">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-600 text-sm">Activities</span>
                    <i class="bi bi-activity text-2xl text-green-600"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900">${stats.activities}</p>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-300">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-600 text-sm">Member Since</span>
                    <i class="bi bi-calendar-check text-2xl text-purple-600"></i>
                </div>
                <p class="text-2xl font-bold text-gray-900">${stats.memberSince}</p>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-400">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-600 text-sm">Last Active</span>
                    <i class="bi bi-clock-history text-2xl text-blue-600"></i>
                </div>
                <p class="text-2xl font-bold text-gray-900">${stats.lastActive}</p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Personal Information -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-500">
                    <div class="flex items-center gap-3 mb-6">
                        <i class="bi bi-person-circle text-2xl text-red-600"></i>
                        <h2 class="text-xl font-bold text-gray-800">Personal Information</h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                            <input type="text" id="editFullName" class="input-field" value="${currentUser.name}" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                            <input type="text" id="editUsername" class="input-field" value="admin" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" id="editEmail" class="input-field" value="${currentUser.email}" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" id="editPhone" class="input-field" value="1954654564" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <input type="text" id="editDepartment" class="input-field" value="IT Department" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                            <input type="text" id="editPosition" class="input-field" value="secretary" disabled>
                        </div>
                    </div>
                    
                    <div id="saveProfileBtn" class="mt-6 hidden">
                        <button onclick="saveProfile()" class="btn-primary mr-3">
                            <i class="bi bi-save mr-2"></i>Save Changes
                        </button>
                        <button onclick="toggleEditMode()" class="btn-outline">
                            <i class="bi bi-x-circle mr-2"></i>Cancel
                        </button>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-600">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <i class="bi bi-clock-history text-2xl text-red-600"></i>
                            <h2 class="text-xl font-bold text-gray-800">Recent Activity</h2>
                        </div>
                        <a href="#" onclick="showSection('audit'); return false;" class="text-sm text-red-600 hover:text-red-700 font-medium">View All</a>
                    </div>
                    
                    <div class="space-y-4">
                        ${AppData.auditLogs.filter(log => log.user === currentUser.name).slice(0, 5).map(log => `
                            <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-check-circle text-blue-600 text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800">${log.description}</p>
                                    <p class="text-xs text-gray-500 mt-1">${log.timestamp}</p>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>

            <!-- Account Security -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-700">
                    <div class="flex items-center gap-3 mb-6">
                        <i class="bi bi-shield-check text-2xl text-red-600"></i>
                        <h2 class="text-xl font-bold text-gray-800">Account Security</h2>
                    </div>
                    
                    <div class="space-y-4">
                        <button onclick="openChangePasswordModal()" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition group">
                            <div class="flex items-center gap-3">
                                <i class="bi bi-key text-xl text-gray-600 group-hover:text-red-600 transition"></i>
                                <div class="text-left">
                                    <p class="text-sm font-medium text-gray-800">Change Password</p>
                                    <p class="text-xs text-gray-500">Update your password</p>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-gray-400"></i>
                        </button>

                        <button onclick="showNotification('Two-Factor Authentication coming soon', 'info')" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition group">
                            <div class="flex items-center gap-3">
                                <i class="bi bi-shield-lock text-xl text-gray-600 group-hover:text-red-600 transition"></i>
                                <div class="text-left">
                                    <p class="text-sm font-medium text-gray-800">Two-Factor Auth</p>
                                    <p class="text-xs text-gray-500">Not enabled</p>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-gray-400"></i>
                        </button>

                        <button onclick="showSection('audit')" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition group">
                            <div class="flex items-center gap-3">
                                <i class="bi bi-clock-history text-xl text-gray-600 group-hover:text-red-600 transition"></i>
                                <div class="text-left">
                                    <p class="text-sm font-medium text-gray-800">Login History</p>
                                    <p class="text-xs text-gray-500">View recent logins</p>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-gray-400"></i>
                        </button>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-800">
                    <div class="flex items-center gap-3 mb-6">
                        <i class="bi bi-link-45deg text-2xl text-red-600"></i>
                        <h2 class="text-xl font-bold text-gray-800">Quick Links</h2>
                    </div>
                    
                    <div class="space-y-3">
                        <button onclick="showNotification('Account Settings coming soon', 'info')" class="w-full flex items-center gap-3 p-3 text-left hover:bg-gray-50 rounded-lg transition">
                            <i class="bi bi-gear text-gray-600"></i>
                            <span class="text-sm text-gray-700">Account Settings</span>
                        </button>
                        <button onclick="showSection('documents')" class="w-full flex items-center gap-3 p-3 text-left hover:bg-gray-50 rounded-lg transition">
                            <i class="bi bi-files text-gray-600"></i>
                            <span class="text-sm text-gray-700">My Documents</span>
                        </button>
                        <button onclick="showNotification('Help Center coming soon', 'info')" class="w-full flex items-center gap-3 p-3 text-left hover:bg-gray-50 rounded-lg transition">
                            <i class="bi bi-question-circle text-gray-600"></i>
                            <span class="text-sm text-gray-700">Help Center</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('content-area').innerHTML = html;
}

// Profile Picture Upload Handler
function handleProfilePictureUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file type
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        showNotification('Please upload a valid image file (JPEG, PNG, GIF, or WEBP)', 'error');
        return;
    }
    
    // Validate file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
        showNotification('File size must be less than 5MB', 'error');
        return;
    }
    
    // Read and display the image
    const reader = new FileReader();
    reader.onload = function(e) {
        AppData.currentUser.profilePicture = e.target.result;
        
        // Update profile image
        const profileImage = document.getElementById('profileImage');
        if (profileImage) {
            if (profileImage.tagName === 'IMG') {
                profileImage.src = e.target.result;
            } else {
                // Replace div with img
                const img = document.createElement('img');
                img.id = 'profileImage';
                img.src = e.target.result;
                img.alt = 'Profile';
                img.className = 'w-32 h-32 rounded-full border-4 border-white shadow-lg object-cover';
                profileImage.parentNode.replaceChild(img, profileImage);
            }
        }
        
        // Update navbar profile picture
        updateNavbarProfilePicture(e.target.result);
        
        showNotification('Profile picture updated successfully!', 'success');
        addAuditLog('update', 'Updated profile picture');
    };
    reader.readAsDataURL(file);
}

// Update navbar profile picture
function updateNavbarProfilePicture(imageUrl) {
    // Update top navbar profile picture
    const navProfilePic = document.querySelector('#profile-menu');
    if (navProfilePic) {
        // Check if it already has an image
        const existingImg = navProfilePic.querySelector('img');
        if (existingImg) {
            existingImg.src = imageUrl;
        } else {
            // Replace icon with image
            navProfilePic.innerHTML = `<img src="${imageUrl}" alt="Profile" class="w-8 h-8 rounded-full border-2 border-white object-cover">`;
        }
    }
    
    // Update sidebar profile picture
    const sidebarProfilePic = document.querySelector('#sidebar-profile-pic');
    if (sidebarProfilePic) {
        const existingImg = sidebarProfilePic.querySelector('img');
        if (existingImg) {
            existingImg.src = imageUrl;
        } else {
            sidebarProfilePic.innerHTML = `<img src="${imageUrl}" alt="Profile" class="w-10 h-10 rounded-full border-2 border-white object-cover">`;
        }
    }
}

// Toggle edit mode
let isEditMode = false;
function toggleEditMode() {
    isEditMode = !isEditMode;
    
    const fields = ['editFullName', 'editUsername', 'editEmail', 'editPhone', 'editDepartment', 'editPosition'];
    const saveBtn = document.getElementById('saveProfileBtn');
    
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.disabled = !isEditMode;
            if (isEditMode) {
                field.classList.add('border-red-300', 'focus:border-red-500');
            } else {
                field.classList.remove('border-red-300', 'focus:border-red-500');
            }
        }
    });
    
    if (saveBtn) {
        if (isEditMode) {
            saveBtn.classList.remove('hidden');
        } else {
            saveBtn.classList.add('hidden');
        }
    }
}

// Save profile changes
function saveProfile() {
    AppData.currentUser.name = document.getElementById('editFullName').value;
    AppData.currentUser.email = document.getElementById('editEmail').value;
    
    showNotification('Profile updated successfully!', 'success');
    addAuditLog('update', 'Updated profile information');
    
    toggleEditMode();
    renderProfile();
}

// Open change password modal
function openChangePasswordModal() {
    const modal = document.createElement('div');
    modal.id = 'changePasswordModal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-800">Change Password</h2>
                    <button onclick="closeChangePasswordModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="bi bi-x-lg text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                        <input type="password" id="currentPassword" class="input-field" placeholder="Enter current password">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                        <input type="password" id="newPassword" class="input-field" placeholder="Enter new password">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                        <input type="password" id="confirmPassword" class="input-field" placeholder="Confirm new password">
                    </div>
                </div>
            </div>
            
            <div class="p-6 border-t border-gray-200 flex gap-3">
                <button onclick="changePassword()" class="btn-primary flex-1">
                    <i class="bi bi-key mr-2"></i>Update Password
                </button>
                <button onclick="closeChangePasswordModal()" class="btn-outline flex-1">
                    Cancel
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function closeChangePasswordModal() {
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.remove();
    }
}

function changePassword() {
    const current = document.getElementById('currentPassword').value;
    const newPass = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    
    if (!current || !newPass || !confirm) {
        showNotification('Please fill in all password fields', 'error');
        return;
    }
    
    if (newPass !== confirm) {
        showNotification('New passwords do not match', 'error');
        return;
    }
    
    if (newPass.length < 6) {
        showNotification('Password must be at least 6 characters', 'error');
        return;
    }
    
    closeChangePasswordModal();
    showNotification('Password changed successfully!', 'success');
    addAuditLog('update', 'Changed account password');
}

function updateProfile() {
    showNotification('Profile updated successfully', 'success');
    addAuditLog('update', 'Updated profile information');
}

function changePassword() {
    showNotification('Password changed successfully', 'success');
    addAuditLog('update', 'Changed account password');
}

function updateProfile() {
    showNotification('Profile updated successfully', 'success');
    addAuditLog('update', 'Updated profile information');
}

function changePassword() {
    showNotification('Password changed successfully', 'success');
    addAuditLog('update', 'Changed account password');
}

// ==============================
// UPLOAD DOCUMENT FUNCTIONALITY
// ==============================
function handleDocumentUpload(event) {
    event.preventDefault();
    
    const formData = {
        reference: document.getElementById('docReference').value,
        title: document.getElementById('docTitle').value,
        type: document.getElementById('docType').value,
        date: document.getElementById('docDate').value,
        status: document.getElementById('docStatus').value,
        description: document.getElementById('docDescription').value,
        tags: document.getElementById('docTags').value.split(',').map(t => t.trim())
    };
    
    // Validate
    if (!formData.reference || !formData.title || !formData.type || !formData.date) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }
    
    // Create new document
    const newDoc = {
        id: AppData.documents.length + 1,
        ...formData,
        uploadedBy: AppData.currentUser.name,
        uploadedAt: new Date().toLocaleString(),
        fileSize: '1.2 MB',
        views: 0,
        downloads: 0
    };
    
    AppData.documents.unshift(newDoc);
    
    // Close modal and reset form
    closeModal('upload-modal');
    document.getElementById('uploadForm').reset();
    
    // Show success notification
    showNotification('Document uploaded successfully', 'success');
    
    // Add audit log
    addAuditLog('upload', `Uploaded document ${formData.reference}`);
    
    // Refresh if on documents page
    if (document.getElementById('documentsList')) {
        filterDocuments();
    }
}

// Setup drag and drop
function setupDragAndDrop() {
    const dropzone = document.getElementById('dropzone');
    if (!dropzone) return;
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.add('border-red-600', 'bg-red-50');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.remove('border-red-600', 'bg-red-50');
        }, false);
    });
    
    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            showNotification(`File "${files[0].name}" ready to upload`, 'info');
        }
    }, false);
}

// ==============================
// UTILITY FUNCTIONS
// ==============================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function loadNotifications() {
    const notifsList = document.getElementById('notifications-list');
    if (!notifsList) return;
    
    const unreadCount = AppData.notifications.filter(n => !n.read).length;
    const badge = document.getElementById('notif-badge');
    
    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
    
    notifsList.innerHTML = AppData.notifications.map(notif => `
        <a href="#" class="block px-4 py-3 hover:bg-gray-50 transition ${!notif.read ? 'bg-blue-50' : ''}">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    ${getNotificationIcon(notif.type)}
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-gray-800">${notif.title}</p>
                    <p class="text-xs text-gray-600">${notif.message}</p>
                    <p class="text-xs text-gray-500 mt-1">${notif.time}</p>
                </div>
            </div>
        </a>
    `).join('');
}

function showNotification(message, type = 'info') {
    const colors = {
        success: 'bg-green-100 text-green-800 border-green-300',
        error: 'bg-red-100 text-red-800 border-red-300',
        info: 'bg-blue-100 text-blue-800 border-blue-300',
        warning: 'bg-yellow-100 text-yellow-800 border-yellow-300'
    };
    
    const icons = {
        success: 'bi-check-circle-fill',
        error: 'bi-x-circle-fill',
        info: 'bi-info-circle-fill',
        warning: 'bi-exclamation-triangle-fill'
    };
    
    const notif = document.createElement('div');
    notif.className = `fixed top-4 right-4 ${colors[type]} px-6 py-4 rounded-lg shadow-lg border-2 flex items-center gap-3 z-50 animate-fade-in`;
    notif.innerHTML = `
        <i class="bi ${icons[type]} text-xl"></i>
        <span class="font-medium">${message}</span>
    `;
    
    document.body.appendChild(notif);
    
    setTimeout(() => {
        notif.classList.add('opacity-0', 'transform', 'translate-x-full');
        setTimeout(() => notif.remove(), 300);
    }, 3000);
}

function getStatusBadge(status) {
    const badges = {
        approved: '<span class="badge badge-success">Approved</span>',
        pending: '<span class="badge badge-warning">Pending</span>',
        draft: '<span class="badge badge-secondary">Draft</span>'
    };
    return badges[status] || '<span class="badge badge-secondary">Unknown</span>';
}

function getUserStatusBadge(status) {
    const badges = {
        active: '<span class="badge badge-success">Active</span>',
        inactive: '<span class="badge badge-secondary">Inactive</span>'
    };
    return badges[status] || '<span class="badge badge-secondary">Unknown</span>';
}

function getActionBadge(action) {
    const badges = {
        upload: '<span class="badge badge-info">Upload</span>',
        approve: '<span class="badge badge-success">Approve</span>',
        update: '<span class="badge badge-warning">Update</span>',
        delete: '<span class="badge badge-danger">Delete</span>'
    };
    return badges[action] || '<span class="badge badge-secondary">' + action + '</span>';
}

function getNotificationIcon(type) {
    const icons = {
        document: '<i class="bi bi-file-earmark-text text-red-600"></i>',
        approval: '<i class="bi bi-check-circle text-green-600"></i>',
        user: '<i class="bi bi-person text-blue-600"></i>'
    };
    return icons[type] || '<i class="bi bi-bell text-gray-600"></i>';
}

function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase();
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

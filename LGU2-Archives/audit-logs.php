<?php
// audit-logs.php
// Lists recent notifications with time, date, content, and about; supports ?id= to highlight.
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?php
    // audit-logs.php
    // Lists recent notifications with time, date, content, and about; supports ?id= to highlight.

    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    require 'authdatabase.php';
    $user_id = $_SESSION['user_id'];
    $user_data = null;
    $stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
    $stmt->close();
    // Ensure notifications table exists (safety in case database.sql not yet applied)
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        time VARCHAR(20) NOT NULL,
        date DATE NOT NULL,
        content VARCHAR(255) NOT NULL,
        about VARCHAR(100) NOT NULL,
        status ENUM('unread','read') NOT NULL DEFAULT 'unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Load notifications from DB
    $notifications = [];
    if ($res = $conn->query("SELECT id, time, date, content, about, status FROM notifications ORDER BY date DESC, id DESC")) {
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
        }
    }

    $conn->close();

    $display_name = $user_data['full_name'] ?? 'User';
    $profile_picture = $user_data['profile_picture'] ?? null;

    $selectedId = isset($_GET['id']) ? intval($_GET['id']) : null;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Audit Logs - Document Management | City of Valenzuela</title>
        <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
        <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="assets/js/archives-landing-head.js"></script>
        <script src="assets/js/theme-head.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/archives-landing.css">
        <link rel="stylesheet" href="assets/css/audit-logs.css">
    </head>
    <body class="bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200">
        <!-- Mobile Sidebar Overlay -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden opacity-0 pointer-events-none transition-all duration-300 ease-out"></div>
    
        <!-- Mobile Sidebar -->
        <div id="mobile-sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:hidden w-72 bg-gradient-to-b from-red-800 to-red-900 text-white z-50 transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden flex flex-col shadow-2xl">
            <div class="p-4 border-b border-red-700/50 sidebar-header">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3 sidebar-logo">
                        <div class="bg-white rounded-full p-1.5 shadow-lg">
                            <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" class="w-9 h-9 object-contain">
                        </div>
                        <div>
                            <h1 class="text-lg font-bold tracking-tight">LAS</h1>
                            <p class="text-xs text-red-200">City of Valenzuela</p>
                        </div>
                    </div>
                    <button id="close-mobile-sidebar" class="text-white/80 p-2 hover:bg-red-700/50 hover:text-white rounded-lg transition-all duration-200 hover:rotate-90">
                        <i class="bi bi-x-lg text-xl"></i>
                    </button>
                </div>
            </div>
            <nav class="flex-1 py-4 px-3 overflow-y-auto">
                <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-speedometer2 mr-3 text-lg"></i>
                    <span>Dashboard Archives</span>
                </a>
                <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-folder mr-3 text-lg"></i>
                    <span>Main Storage Archives</span>
                </a>
                <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-trash mr-3 text-lg"></i>
                    <span>Recently Deleted</span>
                </a>

                <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-trash mr-3 text-lg"></i>
                    <span>Export</span>
                </a>

                <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-trash mr-3 text-lg"></i>
                    <span>Backup</span>
                </a>

                 <!-- ANALYTICS Section -->
            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-graph-up mr-3 text-lg"></i>
                    <span>Reports & Analytics</span>
                </a>
            </div>
            
            <!-- ADMINISTRATION Section -->
            <div class="mt-4 pt-4 border-t border-red-700/50">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
                <a href="burgersettings.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-people mr-3 text-lg"></i>
                    <span>User Management</span>
                </a>
                <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                    <i class="bi bi-shield-check mr-3 text-lg"></i>
                    <span>Audit Logs</span>
                </a>
            </div>
            
            <!-- Storage Bar -->
            <div class="mt-6 pt-4 border-t border-red-700/50 px-2">
                <div class="text-xs font-semibold text-red-200 mb-2 px-2">Storage Status</div>
                <div class="bg-red-900/40 backdrop-blur rounded-lg p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-red-100">Storage Usage</span>
                        <span class="text-xs font-bold text-white">2%</span>
                    </div>
                    <div class="w-full bg-red-900/60 rounded-full h-2 overflow-hidden mb-2">
                        <div class="bg-white h-full rounded-full" style="width: 2%;"></div>
                    </div>
                    <div class="text-xs text-red-100">1.0 GB of 50.0 GB</div>
                </div>
            </div>
            </nav>

        </div>

        <div class="flex h-screen overflow-hidden">
            <!-- Desktop Sidebar -->
            <aside id="sidebar" class="sidebar sidebar-expanded w-64 bg-gradient-to-b from-red-800 to-red-900 text-white flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out h-screen fixed md:relative z-30 -translate-x-full md:translate-x-0">
                <div class="p-6 border-b border-red-700 sidebar-logo">
                    <a href="archives-landing.php" class="flex items-center space-x-3 hover:opacity-80 transition-all duration-300 transform hover:scale-105 group">
                        <div class="bg-white rounded-full shadow-md flex items-center justify-center overflow-hidden transform transition-all duration-300 group-hover:scale-110 group-hover:rotate-6" style="width: 70px; height: 70px;">
                            <img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" style="width: 100%; height: 100%;" class="object-contain">
                        </div>
                        <div class="transform transition-all duration-300 group-hover:translate-x-1 sidebar-text">
                            <h1 class="text-lg font-bold">LAS</h1>
                            <p class="text-xs text-red-200">City of Valenzuela</p>
                        </div>
                    </a>
                </div>
                <!-- Navigation Menu (same as archives dashboard) -->
                <nav class="flex-1 overflow-y-auto py-4">
                    <div class="px-4 space-y-1">
                        <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-speedometer2 mr-3"></i>
                            <span class="sidebar-text">Dashboard Archives</span>
                        </a>
                        <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-folder mr-3"></i>
                            <span class="sidebar-text">Main Storage Archives</span>
                        </a>
                        <a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-cloud-upload mr-3"></i>
                            <span class="sidebar-text">Export</span>
                        </a>

                        <a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-cloud-upload mr-3"></i>
                            <span class="sidebar-text">Backup</span>
                        </a>

                        <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-trash mr-3"></i>
                            <span class="sidebar-text">Recently Deleted</span>
                        </a>
                    </div>

                    <!-- ANALYTICS Section -->
                    <div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
                        <div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
                        <a href="report_analytics.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-graph-up mr-3"></i>
                            <span class="sidebar-text">Reports & Analytics</span>
                        </a>
                    </div>

                    <!-- ADMINISTRATION Section -->
                    <div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
                        <div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
                        <a href="burgersettings.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-people mr-3"></i>
                            <span class="sidebar-text">User Management</span>
                        </a>
                        <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                            <i class="bi bi-shield-check mr-3"></i>
                            <span class="sidebar-text">Audit Logs</span>
                        </a>
                    </div>

                    <!-- Storage Bar -->
                    <div class="mt-6 pt-4 mx-4 border-t border-red-700/50">
                        <div class="text-xs font-semibold text-red-200 mb-2 px-2">Storage Status</div>
                        <div class="bg-red-900/40 backdrop-blur rounded-lg p-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-red-100">Storage Usage</span>
                                <span class="text-xs font-bold text-white">2%</span>
                            </div>
                            <div class="w-full bg-red-900/60 rounded-full h-2 overflow-hidden mb-2">
                                <div class="bg-white h-full rounded-full" style="width: 2%;"></div>
                            </div>
                            <div class="text-xs text-red-100">1.0 GB of 50.0 GB</div>
                        </div>
                    </div>
                </nav>
            </aside>

            <!-- Main Content -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Header / Navbar -->
                <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40 transition-colors duration-200">
                    <div class="px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between items-center h-16">
                            <div class="flex items-center">
                                <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                    <i class="bi bi-list text-2xl"></i>
                                </button>
                                <a href="archives-landing.php" class="ml-2 inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-slate-700 hover:bg-gray-100 dark:hover:bg-slate-600 border border-gray-200 dark:border-slate-600 transition-all duration-200" title="Back to Dashboard" aria-label="Back to Dashboard">
                                    <span class="text-2xl leading-none">&larr;</span>
                                </a>
                            </div>
                            <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                                <div class="ml-2 md:ml-4 min-w-0">
                                    <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Audit Logs</h2>
                                </div>
                            </div>
                            <div class="flex items-center space-x-1 md:space-x-4">
                                <button id="themeToggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors" title="Toggle theme">
                                    <svg id="moonIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                    </svg>
                                    <svg id="sunIcon" class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                </button>
                                <div class="relative">
                                    <button id="profile-btn" class="flex items-center space-x-3 p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition duration-200">
                                        <?php if ($profile_picture && file_exists('uploads/profile_pictures/' . $profile_picture)): ?>
                                            <img src="uploads/profile_pictures/<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border border-gray-300 dark:border-gray-600">
                                        <?php else: ?>
                                            <div class="bg-red-600 rounded-full w-8 h-8 flex items-center justify-center text-white">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="hidden sm:block text-left">
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate max-w-[120px] md:max-w-none"><?php echo htmlspecialchars($display_name); ?></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Administrator</p>
                                        </div>
                                        <i class="bi bi-chevron-down text-gray-600 dark:text-gray-400 text-xs hidden sm:inline"></i>
                                    </button>
                                    <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50 transition-colors duration-200">
                                        <div class="py-2">
                                            <a href="burgersettings.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
                                                <i class="bi bi-gear mr-2"></i>Settings
                                            </a>
                                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer">
                                                <i class="bi bi-box-arrow-right mr-2"></i>Logout
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- Main Content Area -->
                <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                        <div class="space-y-6">
                            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <div class="flex items-center space-x-3">
                                        <h1 class="text-2xl font-bold">Audit Logs</h1>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-4 overflow-x-auto">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center space-x-2">
                                            <button id="filter-all" class="px-3 py-1 rounded bg-gray-100 dark:bg-slate-700 text-sm">All</button>
                                            <select id="filter-status" class="px-2 py-1 rounded border text-sm">
                                                <option value="">Status</option>
                                                <option value="unread">Unread</option>
                                                <option value="read">Read</option>
                                            </select>
                                            <select id="filter-about" class="px-2 py-1 rounded border text-sm">
                                                <option value="">About</option>
                                            </select>
                                            <input id="filter-from" type="date" class="px-2 py-1 rounded border text-sm">
                                            <input id="filter-to" type="date" class="px-2 py-1 rounded border text-sm">
                                            <select id="page-size" class="px-2 py-1 rounded border text-sm">
                                                <option value="10">10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                            </select>
                                            <span id="unread-count" class="ml-3 text-sm text-gray-600 dark:text-gray-300"></span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <input id="searchInput" type="search" placeholder="Search notifications" class="px-3 py-1 border rounded bg-gray-50 dark:bg-slate-700 text-sm">
                                            <a href="?" class="text-sm text-gray-500 hover:underline">Reset</a>
                                            <div id="paginationControls" class="ml-2 flex items-center space-x-2">
                                                <button id="page-prev" class="px-2 py-1 rounded border text-sm">Prev</button>
                                                <span id="page-info" class="text-sm text-gray-600 dark:text-gray-300">1</span>
                                                <button id="page-next" class="px-2 py-1 rounded border text-sm">Next</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="overflow-hidden">
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
                                            <?php $isSelected = ($selectedId !== null && $selectedId === (int)$note['id']); ?>
                                            <tr id="note-<?php echo (int)$note['id']; ?>" data-id="<?php echo (int)$note['id']; ?>" data-status="<?php echo htmlspecialchars($note['status']); ?>" class="border-t <?php echo $isSelected ? 'highlight' : ''; ?>">
                                                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200"><?php echo (int)$note['id']; ?></td>
                                                <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($note['time']); ?></td>
                                                <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($note['date']); ?></td>
                                                <td class="px-3 py-2 text-sm">
                                                    <?php if (!empty($note['link'])): ?>
                                                        <a href="<?php echo htmlspecialchars($note['link']); ?>" class="text-gray-800 dark:text-gray-100 hover:underline block"><?php echo htmlspecialchars($note['content']); ?></a>
                                                    <?php else: ?>
                                                        <span class="text-gray-800 dark:text-gray-100 block"><?php echo htmlspecialchars($note['content']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars($note['about']); ?></td>
                                                <td class="px-3 py-2 text-sm">
                                                    <button class="mark-read-btn px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors" type="button">Mark Read</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <script src="assets/js/archives-landing.js"></script>
        <script src="assets/js/audit-logs.js"></script>
        <script src="assets/js/theme-toggle.js"></script>
    </body>
    </html>

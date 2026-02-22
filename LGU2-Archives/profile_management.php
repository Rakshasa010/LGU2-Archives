<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'authdatabase.php';

$user_id = $_SESSION['user_id'];

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$extraCols = [];
$cr = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('nickname','birthplace','birthdate','address')");
if ($cr) { while ($r = $cr->fetch_assoc()) { $extraCols[] = $r['COLUMN_NAME']; } }
$selectCols = "username, email, full_name, profile_picture, role";
if (!empty($extraCols)) { $selectCols .= ", " . implode(", ", $extraCols); }
$sql = "SELECT $selectCols FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$display_name = $user['full_name'] ?? 'User';
$profile_picture = $user['profile_picture'] ?? null;
$is_admin = isset($user['role']) && strtolower($user['role']) === 'admin';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#b91c1c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Account Settings - LAS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/archives-landing-head.js"></script>
    <script src="assets/js/theme-head.js"></script>
    <link rel="stylesheet" href="assets/css/archives-landing.css">
    <style>[x-cloak] { display: none !important; }</style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
    <link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
    <style>
        /* Hide scrollbars but keep scrolling */
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }

        /* Ensure the desktop sidebar is fixed to the very top */
        #sidebar { top: 0; left: 0; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-900 dark:to-slate-800 text-gray-900 dark:text-gray-100 transition-colors duration-200">
    <!-- Mobile overlay when sidebar open -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden opacity-0 pointer-events-none transition-all duration-300" aria-hidden="true"></div>

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
            <nav class="flex-1 py-4 px-3 overflow-hidden">
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

                <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-cloud-upload mr-3 text-lg"></i>
                    <span>Export</span>
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
                <?php if ($is_admin): ?>
                <a href="user_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                    <i class="bi bi-people mr-3 text-lg"></i>
                    <span>User Management</span>
                </a>
                <?php endif; ?>
                <a href="profile_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                    <i class="bi bi-person mr-3 text-lg"></i>
                    <span>Profile</span>
                </a>
                <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
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

        <div class="flex flex-col md:flex-row min-h-screen md:h-screen overflow-hidden">
            <!-- Desktop Sidebar (hidden on mobile by default; mobile sidebar is separate) -->
            <aside id="sidebar" class="sidebar sidebar-expanded w-64 bg-gradient-to-b from-red-800 to-red-900 text-white flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out h-screen fixed md:relative z-30 -translate-x-full md:translate-x-0 top-0 left-0">
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
                <nav class="flex-1 overflow-hidden py-4">
                    <div class="px-4 space-y-1">
                        <a href="archives-landing.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-speedometer2 mr-3"></i>
                            <span class="sidebar-text">Dashboard Archives</span>
                        </a>
                        <a href="storage.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-folder mr-3"></i>
                            <span class="sidebar-text">Main Storage Archives</span>
                        </a>
                        <a href="export.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-cloud-upload mr-3"></i>
                            <span class="sidebar-text">Export</span>
                        </a>

                        <a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-trash mr-3"></i>
                            <span class="sidebar-text">Recently Deleted</span>
                        </a>

                        <a href="version_tracking.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                            <i class="bi bi-book mr-3"></i>
                            <span class="sidebar-text">Version Tracking</span>
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
                    <?php if ($is_admin): ?>
                    <a href="user_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                        <i class="bi bi-people mr-3"></i>
                        <span class="sidebar-text">User Management</span>
                    </a>
                    <?php endif; ?>
                    
                    <a href="profile_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
                        <i class="bi bi-person mr-3"></i>
                        <span class="sidebar-text">Profile</span>
                    </a>
                    
                        <a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
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
    <!-- Main Content (scrollable on mobile) -->
    <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden w-full">

     <!-- Header / Navbar -->
            <nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40 transition-colors duration-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <div class="flex items-center">
                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-all duration-200">
                                <i class="bi bi-list text-2xl"></i>
                            </button>
                        </div>
                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
                            <div class="ml-2 md:ml-4 min-w-0">
                                <h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Profile Management</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Manage user profiles and permissions</p>
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
                            <!-- Notification Dropdown (placed beside theme toggle) -->
                            <div class="relative">
                                <button id="notification-btn" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors relative" title="Notifications">
                                    <i class="bi bi-bell-fill text-xl text-gray-700 dark:text-gray-300"></i>
                                    <span id="notif-count" class="absolute -top-1 -right-1 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-red-600 bg-red-100 rounded-full">3</span>
                                </button>

                                <div id="notification-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-80 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50">
                                    <div class="p-4">
                                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">Notifications</div>
                                        <div id="notif-list" class="space-y-2">
                                            <div class="text-sm text-gray-600 dark:text-gray-400">Loading notifications...</div>
                                        </div>
                                    </div>

                                    <div class="px-4 py-2 border-t border-gray-200 dark:border-slate-700">
                                        <a href="audit-logs.php" class="block text-center text-sm font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                            View All Notifications
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="relative">
                                <button id="profile-btn" class="flex items-center space-x-3 p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition duration-200">
                                <?php if ($profile_picture && file_exists('uploads/profile_pictures/' . $profile_picture)): ?>
                                    <img src="uploads/profile_pictures/<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border border-gray-300 dark:border-gray-600">
                                <?php elseif ($profile_picture && file_exists($profile_picture)): ?>
                                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border border-gray-300 dark:border-gray-600">
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
                                <div id="profile-dropdown" class="hidden absolute left-1/2 transform -translate-x-1/2 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50 transition-colors duration-200">
                                    <div class="py-2">
                                        <a href="profile_management.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700">
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
    <div class="max-w-4xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6">
        <div class="space-y-6">
            

            <!-- Settings Form -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-4 sm:p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center">
                    <i class="bi bi-gear-fill w-5 h-5 mr-2 text-red-600"></i>
                    Account Settings
                </h2>

                <?php
                // Process form submission
                $upload_message = '';
                $upload_success = false;

                if ($_SERVER["REQUEST_METHOD"] == "POST") {
                    $full_name = trim($_POST['full_name']);
                    $email = trim($_POST['email']);
                    $username = trim($_POST['username']);
                    $profile_picture_path = $user['profile_picture'];
                    $nickname = trim($_POST['nickname'] ?? '');
                    $birthplace = trim($_POST['birthplace'] ?? '');
                    $birthdate = trim($_POST['birthdate'] ?? '');
                    $address = trim($_POST['address'] ?? '');

                    // Handle profile picture upload
                    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
                        $file = $_FILES['profile_picture'];
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        $max_size = 5 * 1024 * 1024; // 5MB

                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);

                        if (!in_array($mime_type, $allowed_types)) {
                            $upload_message = '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.</div>';
                        } elseif ($file['size'] > $max_size) {
                            $upload_message = '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">File size too large. Maximum size is 5MB.</div>';
                        } else {
                            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                            $target_path = $upload_dir . $new_filename;

                            if ($profile_picture_path && file_exists($profile_picture_path)) {
                                @unlink($profile_picture_path);
                            }

                            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                                $profile_picture_path = $target_path;
                                $upload_success = true;
                            } else {
                                $upload_message = '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">Failed to upload profile picture. Please try again.</div>';
                            }
                        }
                    }

                    // Check if username or email already exists for other users
                    $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
                    $stmt = $conn->prepare($check_sql);
                    $stmt->bind_param("ssi", $username, $email, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        echo '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">Username or email already exists.</div>';
                    } else {
                        $update_sql = "UPDATE users SET username = ?, email = ?, full_name = ?, profile_picture = ? WHERE id = ?";
                        $stmt = $conn->prepare($update_sql);
                        $stmt->bind_param("ssssi", $username, $email, $full_name, $profile_picture_path, $user_id);

                        if ($stmt->execute()) {
                            $cols = [];
                            $colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('nickname','birthplace','birthdate','address')");
                            if ($colRes) { while ($r = $colRes->fetch_assoc()) { $cols[] = $r['COLUMN_NAME']; } }
                            $upd = [];
                            if (in_array('nickname', $cols)) $upd[] = "nickname = '".$conn->real_escape_string($nickname)."'";
                            if (in_array('birthplace', $cols)) $upd[] = "birthplace = '".$conn->real_escape_string($birthplace)."'";
                            if (in_array('birthdate', $cols)) { $bd = $birthdate !== '' && strtotime($birthdate) !== false ? date('Y-m-d', strtotime($birthdate)) : null; if ($bd !== null) $upd[] = "birthdate = '".$conn->real_escape_string($bd)."'"; }
                            if (in_array('address', $cols)) $upd[] = "address = '".$conn->real_escape_string($address)."'";
                            if (!empty($upd)) { $conn->query("UPDATE users SET ".implode(', ', $upd)." WHERE id = ".(int)$user_id); }
                            $success_msg = $upload_success ? 'Profile picture and information updated successfully!' : 'Information updated successfully!';
                            echo '<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">' . htmlspecialchars($success_msg) . '</div>';
                            // Refresh user data
                            $sql = "SELECT $selectCols FROM users WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $user = $result->fetch_assoc();
                        } else {
                            echo '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">Update failed. Please try again.</div>';
                        }
                    }

                    if ($upload_message) {
                        echo $upload_message;
                    }

                    $stmt->close();
                }
                $conn->close();
                ?>

                <form action="profile_management.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Profile Picture Upload -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Profile Picture
                        </label>
                        <div class="flex items-center space-x-6">
                            <div class="flex-shrink-0">
                                <?php if ($user['profile_picture'] && file_exists($user['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Current Profile Picture" class="w-24 h-24 rounded-full object-cover border-2 border-gray-200 dark:border-slate-600">
                                <?php else: ?>
                                    <div class="w-24 h-24 bg-gradient-to-r from-red-600 to-orange-500 rounded-full flex items-center justify-center text-white text-3xl font-bold">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                       class="block w-full text-sm text-gray-500 dark:text-gray-400
                                              file:mr-4 file:py-2 file:px-4
                                              file:rounded-lg file:border-0
                                              file:text-sm file:font-semibold
                                              file:bg-red-50 file:text-red-700
                                              hover:file:bg-red-100
                                              dark:file:bg-slate-700 dark:file:text-slate-200
                                              dark:hover:file:bg-slate-600
                                              cursor-pointer">
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">JPEG, PNG, GIF, or WebP. Max size: 5MB</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Full Name
                            </label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors">
                        </div>

                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                </svg>
                                Username
                            </label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors">
                        </div>

                        <div class="md:col-span-2">
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Email Address
                            </label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                        <div>
                            <label for="nickname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nickname</label>
                            <input type="text" id="nickname" name="nickname" value="<?php echo htmlspecialchars($user['nickname'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors">
                        </div>
                        <div>
                            <label for="birthplace" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Birthplace</label>
                            <input type="text" id="birthplace" name="birthplace" value="<?php echo htmlspecialchars($user['birthplace'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors">
                        </div>
                        <div>
                            <label for="birthdate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Birthdate</label>
                            <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars(isset($user['birthdate']) && $user['birthdate'] ? date('Y-m-d', strtotime($user['birthdate'])) : ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors">
                            <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                <?php
                                $ageTxt = '';
                                if (!empty($user['birthdate']) && strtotime($user['birthdate']) !== false) {
                                    $d1 = new DateTime(date('Y-m-d', strtotime($user['birthdate'])));
                                    $d2 = new DateTime('today');
                                    $ageTxt = $d1->diff($d2)->y . ' years old';
                                }
                                echo htmlspecialchars($ageTxt);
                                ?>
                            </p>
                        </div>
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Complete Address</label>
                            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors">
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 pt-4">
                        <button type="submit" class="flex-1 bg-gradient-to-r from-red-600 to-orange-500 text-white py-3 px-6 rounded-lg font-semibold hover:from-red-700 hover:to-orange-600 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Save Changes
                        </button>
                        <a href="archives-landing.php" class="px-6 py-3 bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to Archives
                        </a>
                    </div>
                </form>
            </div>

            <!-- Logout Section -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    Account Actions
                </h2>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Need to sign out? You can logout from your account here.</p>
                <a href="logout.php" class="inline-flex items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition-colors shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    Logout
                </a>
            </div>
        </div>
    </div>
    </div>

    <script src="assets/js/archives-landing.js"></script>
    <script src="assets/js/theme-toggle.js"></script>
    <script src="assets/js/archives.js"></script>
</body>
</html>
 

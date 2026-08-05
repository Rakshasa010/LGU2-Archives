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
    $cr = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('nickname','birthplace','birthdate','address','two_factor_enabled')");
    if ($cr) { while ($r = $cr->fetch_assoc()) { $extraCols[] = $r['COLUMN_NAME']; } }
    $reqCols = ['nickname','birthplace','birthdate','address','two_factor_enabled'];
    $missingCols = array_diff($reqCols, $extraCols);
    foreach ($missingCols as $mcol) {
        if ($mcol === 'birthdate') $ctype = 'DATE';
        elseif ($mcol === 'two_factor_enabled') $ctype = 'TINYINT(1) DEFAULT 1';
        else $ctype = 'VARCHAR(255)';
        $conn->query("ALTER TABLE users ADD COLUMN $mcol $ctype");
        $extraCols[] = $mcol;
    }
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

// Fetch archive folders for sidebar
$archive_folders = [];
$folders_result = $conn->query("SELECT id, name, slug FROM archive_folders ORDER BY created_at DESC");
if ($folders_result && $folders_result->num_rows > 0) {
    while ($row = $folders_result->fetch_assoc()) {
        $archive_folders[] = $row;
    }
}
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
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <script src="assets/js/mobile-responsive.js"></script>
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
<body class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-900 dark:to-slate-800 text-gray-900 dark:text-gray-100 transition-colors duration-200">
    <?php
    $sidebar_active_page = '';
    $sidebar_include_overlay = false;
    require_once 'includes/sidebar-centralized.php';
    ?>

    <div>
    <!-- Main Content (scrollable on mobile) -->
    <div class="flex flex-col min-h-screen w-full md:ml-64">

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
                                            <i class="bi bi-gear mr-2"></i>Account Settings
                                        </a>
                                        <form action="logout.php" method="POST" class="block w-full">
                                            <button type="submit" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer w-full text-left">
                                                <i class="bi bi-box-arrow-right mr-2"></i>Logout
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10">
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                Account Settings
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your profile, personal info, and security</p>
        </div>

                <?php
                // Process form submission
                $upload_message = '';
                $upload_success = false;

                if ($_SERVER["REQUEST_METHOD"] == "POST") {
                    
                    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
                        $current_pass = $_POST['current_password'];
                        $new_pass = $_POST['new_password'];
                        $confirm_pass = $_POST['confirm_password'];

                        $sql = "SELECT password FROM users WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $u = $res->fetch_assoc();

                        if (!password_verify($current_pass, $u['password'])) {
                            echo '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">Current password is incorrect.</div>';
                        } elseif ($new_pass !== $confirm_pass) {
                            echo '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">New passwords do not match.</div>';
                        } elseif (strlen($new_pass) < 6) {
                            echo '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">New password must be at least 6 characters.</div>';
                        } else {
                            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $upd->bind_param("si", $hashed, $user_id);
                            if ($upd->execute()) {
                                echo '<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">Password updated successfully!</div>';
                                // Log to notifications
                                $conn->query("CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY, time VARCHAR(20) NOT NULL, date DATE NOT NULL, content VARCHAR(255) NOT NULL, about VARCHAR(100) NOT NULL, status ENUM('unread','read') NOT NULL DEFAULT 'unread', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                                $nt = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?, ?, ?, ?, ?)");
                                $ntime = date('h:i A'); $ndate = date('Y-m-d'); $ncontent = ($is_admin ? 'Admin: ' : 'User: ') . ($user['username'] ?? 'User') . ' changed password.'; $nabout = 'Security'; $nstatus = 'unread';
                                $nt->bind_param("sssss", $ntime, $ndate, $ncontent, $nabout, $nstatus);
                                $nt->execute();
                            } else {
                                echo '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">Failed to update password.</div>';
                            }
                        }
                    } elseif (isset($_POST['action']) && $_POST['action'] === 'toggle_2fa') {
                        $new_2fa_status = (isset($_POST['two_factor_enabled']) && $_POST['two_factor_enabled'] == '1') ? 1 : 0;
                        $conn->query("UPDATE users SET two_factor_enabled = $new_2fa_status WHERE id = $user_id");
                        echo '<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">Two-Factor Authentication settings updated!</div>';
                        
                        $sql = "SELECT $selectCols FROM users WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                    } else {
                        // Profile Info Update
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
                            
                            // Add Notification for Profile Update
                            $notif_time = date('h:i A');
                            $notif_date = date('Y-m-d');
                            $notif_content = ($is_admin ? 'Admin profile updated: ' : 'User profile updated: ') . $username;
                            $notif_about = 'Profile Update';
                            $notif_status = 'unread';
                            
                            // Ensure notifications table exists
                            $conn->query("CREATE TABLE IF NOT EXISTS notifications (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                time VARCHAR(20) NOT NULL,
                                date DATE NOT NULL,
                                content VARCHAR(255) NOT NULL,
                                about VARCHAR(100) NOT NULL,
                                status ENUM('unread','read') NOT NULL DEFAULT 'unread',
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                            )");

                            $nt = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?, ?, ?, ?, ?)");
                            if ($nt) {
                                $nt->bind_param("sssss", $notif_time, $notif_date, $notif_content, $notif_about, $notif_status);
                                $nt->execute();
                                $nt->close();
                            }

                            $success_msg = $upload_success ? 'Profile picture and information updated successfully!' : 'Information updated successfully!';
                            echo '<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">' . htmlspecialchars($success_msg) . '</div>';
                            // Refresh user data
                            $sql = "SELECT $selectCols FROM users WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $user = $result->fetch_assoc();
                            // Refresh header bindings with latest data
                            $display_name = $user['full_name'] ?? $display_name;
                            $profile_picture = $user['profile_picture'] ?? $profile_picture;
                            $is_admin = isset($user['role']) && strtolower($user['role']) === 'admin';
                        } else {
                            echo '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">Update failed. Please try again.</div>';
                        }
                    }

                    if ($upload_message) {
                        echo $upload_message;
                    }
                } // This properly closes the main POST if block
                }
                ?>

                <form action="profile_management.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <!-- SECTION 1: PROFILE PICTURE -->
                    <div class="bg-white dark:bg-[#111520] rounded-2xl border border-gray-200 dark:border-slate-700/60 p-6 sm:p-8 shadow-sm transition-colors">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                            <i class="bi bi-person-bounding-box text-gray-400"></i> Profile Picture
                        </h3>
                        
                        <div class="flex items-center gap-6">
                            <div class="flex-shrink-0">
                                <?php if ($user['profile_picture'] && file_exists($user['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile" class="w-24 h-24 rounded-full object-cover border-[3px] border-gray-200 dark:border-slate-600 shadow-sm transition-all">
                                <?php else: ?>
                                    <div class="w-24 h-24 bg-gradient-to-br from-red-500 to-red-700 rounded-full flex items-center justify-center text-white text-3xl font-bold shadow-md border-[3px] border-red-800/30">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <label for="profile_picture" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-700/50 hover:bg-gray-200 dark:hover:bg-slate-600/70 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 cursor-pointer transition-colors shadow-sm">
                                        <i class="bi bi-person-fill-up"></i> Choose File
                                    </label>
                                    <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="hidden">
                                    <span class="text-sm text-gray-400 dark:text-gray-500" id="file-chosen-text">No file chosen</span>
                                </div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-start gap-1.5">
                                    <i class="bi bi-info-circle mt-0.5"></i> 
                                    <span>JPEG, PNG, GIF, or WebP · Max size: 5MB</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: PERSONAL INFORMATION -->
                    <div class="bg-white dark:bg-[#111520] rounded-2xl border border-gray-200 dark:border-slate-700/60 p-6 sm:p-8 shadow-sm transition-colors">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                            <i class="bi bi-person-lines-fill text-gray-400"></i> Personal Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="full_name" class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1.5"><i class="bi bi-person"></i> Full Name</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600/80 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-gray-50 dark:bg-[#252321] text-gray-900 dark:text-gray-100 transition-colors">
                            </div>
                            <div>
                                <label for="username" class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1.5"><i class="bi bi-at"></i> Username</label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600/80 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-gray-50 dark:bg-[#252321] text-gray-900 dark:text-gray-100 transition-colors">
                            </div>
                            <div class="md:col-span-2">
                                <label for="email" class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1.5"><i class="bi bi-envelope"></i> Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600/80 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-gray-50 dark:bg-[#252321] text-gray-900 dark:text-gray-100 transition-colors">
                            </div>
                            <div>
                                <label for="nickname" class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1.5"><i class="bi bi-hash"></i> Nickname</label>
                                <input type="text" id="nickname" name="nickname" value="<?php echo htmlspecialchars($user['nickname'] ?? ''); ?>" placeholder="Optional nickname"
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600/80 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-gray-50 dark:bg-[#252321] text-gray-900 dark:text-gray-100 transition-colors">
                            </div>
                            <div>
                                <label for="birthplace" class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1.5"><i class="bi bi-geo-alt"></i> Birthplace</label>
                                <input type="text" id="birthplace" name="birthplace" value="<?php echo htmlspecialchars($user['birthplace'] ?? ''); ?>" placeholder="City, Province"
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600/80 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-gray-50 dark:bg-[#252321] text-gray-900 dark:text-gray-100 transition-colors">
                            </div>
                            <div>
                                <label for="birthdate" class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1.5"><i class="bi bi-calendar-event"></i> Birthdate</label>
                                <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars(isset($user['birthdate']) && $user['birthdate'] ? date('Y-m-d', strtotime($user['birthdate'])) : ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600/80 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-gray-50 dark:bg-[#252321] text-gray-900 dark:text-gray-100 transition-colors">
                            </div>
                            <div>
                                <label for="address" class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1.5"><i class="bi bi-house"></i> Complete Address</label>
                                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" placeholder="Street, City, Province"
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600/80 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-gray-50 dark:bg-[#252321] text-gray-900 dark:text-gray-100 transition-colors">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: SECURITY -->
                    <div class="bg-white dark:bg-[#111520] rounded-2xl border border-gray-200 dark:border-slate-700/60 p-6 sm:p-8 shadow-sm transition-colors">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                            <i class="bi bi-shield-lock text-gray-400"></i> Security
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-slate-700/60 bg-gray-50 dark:bg-[#1a1d24]">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-500">
                                        <i class="bi bi-unlock-fill text-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100">Password</div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Regularly update your password</div>
                                    </div>
                                </div>
                                <button type="button" onclick="openPasswordModal()" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">Change &rsaquo;</button>
                            </div>

                            <div class="flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-slate-700/60 bg-gray-50 dark:bg-[#1a1d24]">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-500">
                                        <i class="bi bi-phone-vibrate text-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100">Two-Factor Auth</div>
                                        <?php $twofa_active = !isset($user['two_factor_enabled']) || $user['two_factor_enabled'] == 1; ?>
                                        <div class="text-[11px] <?php echo $twofa_active ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-500'; ?> font-medium">
                                            <?php echo $twofa_active ? 'OTP via email enabled' : 'Currently disabled'; ?>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" onclick="openTwoFactorModal()" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">Manage &rsaquo;</button>
                            </div>
                        </div>
                    </div>

                    <!-- ACTIONS -->
                    <div class="flex flex-col sm:flex-row gap-4 pt-2">
                        <button type="submit" class="flex-1 px-6 py-4 bg-gray-900 dark:bg-[#0f1117] hover:bg-black dark:hover:bg-black text-white rounded-xl font-medium transition-colors border border-gray-800 dark:border-slate-700/60 shadow-sm flex items-center justify-center gap-2">
                            <i class="bi bi-floppy"></i> Save Changes
                        </button>
                        <a href="archives-landing.php" class="flex-1 px-6 py-4 bg-white dark:bg-[#0f1117] hover:bg-gray-50 dark:hover:bg-slate-900 text-gray-800 dark:text-gray-100 rounded-xl font-medium transition-colors border border-gray-200 dark:border-slate-700/60 shadow-sm flex items-center justify-center gap-2">
                            <i class="bi bi-box-arrow-left"></i> Back to Archives
                        </a>
                    </div>
                </form>
    <?php include 'includes/footer.php'; ?>
    </div>
    </div>

    <script src="assets/js/archives-landing.js?v=2"></script>
    <script src="assets/js/theme-toggle.js"></script>
    <script src="assets/js/archives.js"></script>

    <script>
        (function() {
            function fetchAndUpdateStorage() {
                fetch('archives-landing.php?action=get_storage_data')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const pct = data.percentage;
                            const usedText = data.usedText;
                            const totalText = data.totalText;
                            const fileCount = data.fileCount;

                            ['mobile', 'desktop'].forEach(prefix => {
                                const bar = document.getElementById(prefix + '-storage-bar');
                                const pctEl = document.getElementById(prefix + '-storage-pct');
                                const textEl = document.getElementById(prefix + '-storage-text');
                                const filesEl = document.getElementById(prefix + '-storage-files');
                                
                                if (bar) bar.style.width = pct + '%';
                                if (pctEl) pctEl.textContent = pct + '%';
                                if (textEl) textEl.textContent = usedText + ' of ' + totalText;
                                if (filesEl) filesEl.textContent = fileCount + ' files tracked';
                            });
                        }
                    }).catch(err => console.warn('Storage fetch error:', err));
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fetchAndUpdateStorage);
            } else {
                fetchAndUpdateStorage();
            }
            setInterval(fetchAndUpdateStorage, 60000);
        })();

        // File chooser updater
        const fileInput = document.getElementById('profile_picture');
        const fileText = document.getElementById('file-chosen-text');
        if (fileInput && fileText) {
            fileInput.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    fileText.textContent = e.target.files[0].name;
                } else {
                    fileText.textContent = 'No file chosen';
                }
            });
        }
    </script>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm transition-opacity duration-300 opacity-0" aria-hidden="true">
        <div class="bg-white dark:bg-[#111520] rounded-2xl w-full max-w-md p-6 border border-gray-200 dark:border-slate-700 shadow-2xl transform scale-95 transition-transform duration-300" id="passwordModalInner">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="bi bi-key-fill text-emerald-500"></i> Change Password
                </h3>
                <button type="button" onclick="closePasswordModal()" class="text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form action="profile_management.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Current Password</label>
                    <input type="password" name="current_password" required class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600/80 rounded-lg focus:ring-2 focus:ring-red-500 bg-gray-50 dark:bg-[#252321] text-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">New Password</label>
                    <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600/80 rounded-lg focus:ring-2 focus:ring-red-500 bg-gray-50 dark:bg-[#252321] text-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6" class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600/80 rounded-lg focus:ring-2 focus:ring-red-500 bg-gray-50 dark:bg-[#252321] text-gray-900 dark:text-white">
                </div>
                <div class="pt-2 flex justify-end gap-3">
                    <button type="button" onclick="closePasswordModal()" class="px-5 py-2.5 rounded-lg bg-gray-200 dark:bg-slate-700/50 text-gray-800 dark:text-gray-200 font-medium hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium transition-colors shadow-lg shadow-emerald-600/20">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Two-Factor Authentication Modal -->
    <div id="twofaModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm transition-opacity duration-300 opacity-0" aria-hidden="true">
        <div class="bg-white dark:bg-[#111520] rounded-2xl w-full max-w-sm p-6 border border-gray-200 dark:border-slate-700 shadow-2xl transform scale-95 transition-transform duration-300" id="twofaModalInner">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="bi bi-shield-lock-fill text-amber-500"></i> Two-Factor Auth 
                </h3>
                <button type="button" onclick="closeTwoFactorModal()" class="text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-5">
                Securing your account with a One-Time Password (OTP) sent to your email whenever you sign in. This deeply improves account safety.
            </p>
            <form action="profile_management.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="toggle_2fa">
                
                <div class="flex items-center gap-3 bg-gray-50 dark:bg-[#1a1d24] p-3 rounded-xl border border-gray-200 dark:border-slate-700/50 mb-3 cursor-pointer" onclick="document.getElementById('2fa_on').checked = true">
                    <input type="radio" id="2fa_on" name="two_factor_enabled" value="1" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 dark:focus:ring-emerald-600" <?php echo (!isset($user['two_factor_enabled']) || $user['two_factor_enabled'] == 1) ? 'checked' : ''; ?>>
                    <div class="flex flex-col">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Enable 2FA</span>
                        <span class="text-[11px] text-gray-500 dark:text-gray-400">Highly recommended</span>
                    </div>
                </div>

                <div class="flex items-center gap-3 bg-gray-50 dark:bg-[#1a1d24] p-3 rounded-xl border border-gray-200 dark:border-slate-700/50 cursor-pointer" onclick="document.getElementById('2fa_off').checked = true">
                    <input type="radio" id="2fa_off" name="two_factor_enabled" value="0" class="w-4 h-4 text-red-600 focus:ring-red-500 dark:focus:ring-red-600" <?php echo (isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 0) ? 'checked' : ''; ?>>
                    <div class="flex flex-col">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Disable 2FA</span>
                        <span class="text-[11px] text-gray-500 dark:text-gray-400">Account is less secure</span>
                    </div>
                </div>

                <div class="pt-3">
                    <button type="submit" class="w-full px-5 py-3 rounded-xl bg-gray-900 hover:bg-black dark:bg-[#252321] dark:hover:bg-slate-700 text-white font-medium transition-colors border border-gray-700">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts for Modals -->
    <script>
        function openPasswordModal() {
            const m = document.getElementById('passwordModal');
            const i = document.getElementById('passwordModalInner');
            m.classList.remove('hidden');
            setTimeout(() => { m.classList.remove('opacity-0'); i.classList.remove('scale-95'); i.classList.add('scale-100'); }, 10);
        }
        function closePasswordModal() {
            const m = document.getElementById('passwordModal');
            const i = document.getElementById('passwordModalInner');
            m.classList.add('opacity-0'); i.classList.remove('scale-100'); i.classList.add('scale-95');
            setTimeout(() => { m.classList.add('hidden'); }, 300);
        }

        function openTwoFactorModal() {
            const m = document.getElementById('twofaModal');
            const i = document.getElementById('twofaModalInner');
            m.classList.remove('hidden');
            setTimeout(() => { m.classList.remove('opacity-0'); i.classList.remove('scale-95'); i.classList.add('scale-100'); }, 10);
        }
        function closeTwoFactorModal() {
            const m = document.getElementById('twofaModal');
            const i = document.getElementById('twofaModalInner');
            m.classList.add('opacity-0'); i.classList.remove('scale-100'); i.classList.add('scale-95');
            setTimeout(() => { m.classList.add('hidden'); }, 300);
        }
    </script>
</body>
</html>

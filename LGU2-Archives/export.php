<?php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit();
}

require 'authdatabase.php';
$user_id = $_SESSION['user_id'];
$user_data = null;
$stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
	$user_data = $res->fetch_assoc();
}
$stmt->close();
$conn->close();

$display_name = $user_data['full_name'] ?? 'User';
$profile_picture = $user_data['profile_picture'] ?? null;

// Mock notifications about export requests for already stored documents
$mock_notifications = [
	['time' => '09:12 AM', 'date' => date('Y-m-d'), 'content' => 'Export requested for "Ordinance 2021-05" — file already stored. Generated export queued.', 'about' => 'Export Request', 'status' => 'unread'],
	['time' => '08:45 AM', 'date' => date('Y-m-d', strtotime('-1 day')), 'content' => 'Export requested for "Public Hearing Minutes - Jan 2020" — existing file used.', 'about' => 'Export Request', 'status' => 'read'],
	['time' => 'Yesterday', 'date' => date('Y-m-d', strtotime('-2 day')), 'content' => 'Export requested for "Building Permits 2019" — document found in archive.', 'about' => 'Export Request', 'status' => 'read'],
];
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Export - Document Management | City of Valenzuela</title>
	<link rel="icon" type="image/png" href="Images/Val-logo/valenzuela logo.webp">
	<link rel="apple-touch-icon" href="Images/Val-logo/valenzuela logo.webp">
	<script src="https://cdn.tailwindcss.com"></script>
	<script src="assets/js/archives-landing-head.js"></script>
	<script src="assets/js/theme-head.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
	<link rel="stylesheet" href="assets/css/archives-landing.css">
	<link rel="stylesheet" href="assets/css/audit-logs.css">
</head>
<body class="bg-gray-100 dark:bg-slate-900 font-sans antialiased transition-colors duration-200 min-h-screen">
	<!-- Desktop Sidebar (copied from audit-logs) -->
	<div class="flex h-screen overflow-hidden">
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
					<a href="#" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">
						<i class="bi bi-cloud-upload mr-3"></i>
						<span class="sidebar-text">Export</span>
					</a>
					<a href="recent_deleted.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
						<i class="bi bi-trash mr-3"></i>
						<span class="sidebar-text">Recently Deleted</span>
					</a>
				</div>

				<div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
					<div class="text-xs font-semibold text-red-200 mb-2 px-2">ANALYTICS</div>
					<a href="report_analytics.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
						<i class="bi bi-graph-up mr-3"></i>
						<span class="sidebar-text">Reports & Analytics</span>
					</a>
				</div>

				<div class="mt-4 pt-4 mx-4 border-t border-red-700/50">
					<div class="text-xs font-semibold text-red-200 mb-2 px-2">ADMINISTRATION</div>
					<a href="profile_management.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
						<i class="bi bi-people mr-3"></i>
						<span class="sidebar-text">User Management</span>
					</a>
					<a href="audit-logs.php" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
						<i class="bi bi-shield-check mr-3"></i>
						<span class="sidebar-text">Audit Logs</span>
					</a>
				</div>

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
		<div class="flex-1 flex flex-col overflow-hidden ml-64">
			<nav class="bg-white dark:bg-slate-800 shadow-md border-b border-gray-200 dark:border-slate-700 sticky top-0 z-40 transition-colors duration-200">
				<div class="px-4 sm:px-6 lg:px-8">
					<div class="flex justify-between items-center h-16">
						<div class="flex items-center">
							<a href="archives-landing.php" class="ml-2 inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-slate-700 hover:bg-gray-100 dark:hover:bg-slate-600 border border-gray-200 dark:border-slate-600 transition-all duration-200" title="Back to Dashboard">
								<span class="text-2xl leading-none">&larr;</span>
							</a>
							<img src="Images/Val-logo/valenzuela logo.webp" alt="Valenzuela Logo" class="ml-3 h-10 w-auto object-contain hidden md:block">
						</div>
						<div class="flex-1 flex items-center justify-center md:justify-start min-w-0">
							<div class="ml-2 md:ml-4 min-w-0">
								<h2 id="page-title" class="text-base md:text-xl font-bold text-gray-800 dark:text-gray-100">Export</h2>
							</div>
						</div>
						<div class="flex items-center space-x-4">
							<div class="hidden sm:block text-left">
								<p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate max-w-[120px] md:max-w-none"><?php echo htmlspecialchars($display_name); ?></p>
								<p class="text-xs text-gray-500 dark:text-gray-400">Administrator</p>
							</div>
						</div>
					</div>
				</div>
			</nav>

			<main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-slate-900 p-6">
				<div class="max-w-5xl mx-auto space-y-6">
					<div class="bg-white dark:bg-slate-800/95 rounded-xl shadow-lg border border-gray-200 dark:border-slate-600/80 p-6">
						<h3 class="text-lg font-semibold mb-4">Recent Export Requests (mock)</h3>
						<div class="space-y-4">
							<?php foreach ($mock_notifications as $n): ?>
								<div class="flex items-start gap-4 p-4 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-sm transition-colors <?php echo $n['status'] === 'unread' ? 'ring-1 ring-red-200 dark:ring-red-700' : ''; ?>">
									<div class="flex-shrink-0 mt-1">
										<div class="w-10 h-10 rounded-lg bg-red-50 dark:bg-red-900/30 flex items-center justify-center text-red-700 dark:text-red-300">
											<i class="bi bi-cloud-arrow-up-fill text-xl"></i>
										</div>
									</div>
									<div class="flex-1">
										<div class="flex items-center justify-between">
											<div class="text-sm text-gray-700 dark:text-gray-200 font-medium"><?php echo htmlspecialchars($n['content']); ?></div>
											<div class="text-xs text-gray-500 dark:text-gray-400 ml-4"><?php echo htmlspecialchars($n['time']); ?></div>
										</div>
										<div class="mt-2 flex items-center justify-between">
											<div class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($n['about']); ?> • <?php echo htmlspecialchars($n['date']); ?></div>
											<div>
												<button class="px-3 py-1 text-xs rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200">View</button>
											</div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</main>
		</div>
	</div>

	<script src="assets/js/archives-landing.js"></script>
	<script src="assets/js/theme-toggle.js"></script>
</body>
</html>


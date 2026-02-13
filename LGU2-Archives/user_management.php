<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require 'authdatabase.php';
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$me = $res->fetch_assoc();
$stmt->close();
$is_admin = isset($me['role']) && strtolower($me['role']) === 'admin';
if (!$is_admin) {
    header("Location: archives-landing.php");
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($target > 0 && in_array($action, ['approve','reject'], true)) {
        $info = null;
        $gi = $conn->prepare("SELECT full_name, username, email FROM users WHERE id = ?");
        if ($gi) { $gi->bind_param("i", $target); $gi->execute(); $r = $gi->get_result(); if ($r) { $info = $r->fetch_assoc(); } $gi->close(); }
        $newStatus = $action === 'approve' ? 'active' : 'rejected';
        $up = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $up->bind_param("si", $newStatus, $target);
        if ($up->execute()) {
            $message = $action === 'approve' ? 'User approved.' : 'User rejected.';
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
            // Add notification entry
            $nt = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?, ?, ?, ?, ?)");
            if ($nt) {
                $ntime = date('h:i A');
                $ndate = date('Y-m-d');
                $ncontent = ($action === 'approve' ? 'User approved: ' : 'User rejected: ') . ($info['full_name'] ?? 'User') . ' (' . ($info['username'] ?? '') . ')';
                $nabout = 'User Management';
                $nstatus = 'unread';
                $nt->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $nt->execute();
                $nt->close();
            }
        } else {
            $message = 'Failed to update user.';
        }
        $up->close();
    }
}

$pending = [];
if ($q = $conn->query("SELECT id, username, email, full_name, created_at FROM users WHERE status = 'pending' ORDER BY created_at DESC")) {
    while ($row = $q->fetch_assoc()) { $pending[] = $row; }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="Images/Val-logo/valenzuela logo.webp">
</head>
<body class="bg-gray-100 dark:bg-slate-900 text-gray-900 dark:text-white min-h-screen">
    <div class="max-w-5xl mx-auto p-6">
        <div class="mb-6">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-red-600 to-orange-500 bg-clip-text text-transparent">User Management</h1>
            <p class="text-gray-600 dark:text-gray-400">Approve or reject newly registered users</p>
        </div>
        <?php if ($message !== ''): ?>
            <div class="mb-4 p-3 rounded-lg border <?php echo strpos($message, 'Failed') === false ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
                <div class="font-semibold">Pending Approvals</div>
                <span class="text-sm text-gray-600 dark:text-gray-400"><?php echo count($pending); ?> pending</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registered</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                        <?php if (empty($pending)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-600 dark:text-gray-400">No pending users</td>
                            </tr>
                        <?php else: foreach ($pending as $u): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($u['created_at']); ?></td>
                                <td class="px-6 py-4">
                                    <form method="post" class="inline-block">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded hover:bg-green-700">Approve</button>
                                    </form>
                                    <form method="post" class="inline-block ml-2">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-700">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">
            <a href="archives-landing.php" class="inline-flex items-center px-4 py-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-semibold hover:bg-gray-50 dark:hover:bg-slate-700">
                <span class="mr-2">←</span> Back to Archives
            </a>
        </div>
    </div>
</body>
</html>

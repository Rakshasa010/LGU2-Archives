 <?php
require_once 'authdatabase.php';

header('Content-Type: application/json');

// Ensure database structure exists
function ensure_structure($conn) {
    // Create folders table
    $conn->query("CREATE TABLE IF NOT EXISTS legislative_folders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        parent_id INT NULL,
        type VARCHAR(50) NOT NULL, -- To scope folders to specific pages (Ordinance, Meeting, etc.)
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");

    // Add folder_id to legislative_records if not exists
    $check = $conn->query("SHOW COLUMNS FROM legislative_records LIKE 'folder_id'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE legislative_records ADD COLUMN folder_id INT NULL");
        $conn->query("ALTER TABLE legislative_records ADD CONSTRAINT fk_leg_folder FOREIGN KEY (folder_id) REFERENCES legislative_folders(id) ON DELETE SET NULL");
    }
    // Add version and parent_version_id columns if missing (for versioning support)
    $checkVer = $conn->query("SHOW COLUMNS FROM legislative_records LIKE 'version'");
    if ($checkVer->num_rows == 0) {
        // add version column with default 1
        $conn->query("ALTER TABLE legislative_records ADD COLUMN version INT NOT NULL DEFAULT 1");
    }
    $checkParent = $conn->query("SHOW COLUMNS FROM legislative_records LIKE 'parent_version_id'");
    if ($checkParent->num_rows == 0) {
        $conn->query("ALTER TABLE legislative_records ADD COLUMN parent_version_id INT NULL");
        // optional FK omitted to avoid circular issues on older schemas
    }
}

// Initialize structure
ensure_structure($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

switch ($action) {
    case 'create_folder':
        $name = $_POST['name'] ?? 'New Folder';
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $type = $_POST['type'] ?? 'General'; // Ordinance, Meeting, etc.
        
        $stmt = $conn->prepare("INSERT INTO legislative_folders (name, parent_id, type, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sisi", $name, $parent_id, $type, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id, 'name' => $name]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;

    case 'move_item':
        // Move file or folder
        $item_type = $_POST['item_type'] ?? ''; // 'file' or 'folder'
        $item_id = (int)($_POST['item_id'] ?? 0);
        $target_folder_id = !empty($_POST['target_folder_id']) ? (int)$_POST['target_folder_id'] : null;

        if ($item_type === 'file') {
            $stmt = $conn->prepare("UPDATE legislative_records SET folder_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $target_folder_id, $item_id);
        } elseif ($item_type === 'folder') {
            // Prevent circular move
            if ($item_id === $target_folder_id) {
                echo json_encode(['success' => false, 'message' => 'Cannot move folder into itself']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE legislative_folders SET parent_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $target_folder_id, $item_id);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid item type']);
            exit;
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;

    case 'upload_file':
        if (!isset($_FILES['file'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            exit;
        }

        $file = $_FILES['file'];
        $folder_id = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
        $type = $_POST['type'] ?? 'Other';
        $title = $_POST['fileName'] ?? $file['name']; // Use fileName input if available
        $author = $_POST['fileAuthor'] ?? 'Unknown';
        $date = $_POST['fileDate'] ?? date('Y-m-d');
        $month = date('F', strtotime($date));
        $year = date('Y', strtotime($date));

        // Check for duplicate
        if (!isset($_POST['force_version'])) {
            $checkSql = "SELECT id, version FROM legislative_records WHERE title = ? AND " . ($folder_id ? "folder_id = ?" : "folder_id IS NULL") . " AND parent_version_id IS NULL";
            $checkStmt = $conn->prepare($checkSql);
            if ($folder_id) $checkStmt->bind_param("si", $title, $folder_id);
            else $checkStmt->bind_param("s", $title);
            
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result();
            if ($row = $checkRes->fetch_assoc()) {
                echo json_encode([
                    'success' => false, 
                    'duplicate' => true, 
                    'existing_id' => $row['id'],
                    'message' => 'File already exists. Create new version?'
                ]);
                exit;
            }
        }

        // Handle versioning
        $version = 1;
        $parent_version_id = null;
        
        if (isset($_POST['parent_id'])) {
            $parent_id = (int)$_POST['parent_id'];
            // Get max version from the family of versions
            $verSql = "SELECT MAX(version) as max_ver FROM legislative_records WHERE id = ? OR parent_version_id = ?";
            $verStmt = $conn->prepare($verSql);
            $verStmt->bind_param("ii", $parent_id, $parent_id);
            $verStmt->execute();
            $verRes = $verStmt->get_result();
            if ($vRow = $verRes->fetch_assoc()) {
                $version = ($vRow['max_ver'] ?? 1) + 1;
            }
            $parent_version_id = $parent_id;
        }

        // Create upload directory
        $target_dir = "uploads/legislative/";
        
        // Enhance folder structure: uploads/legislative/{Type}/{Year}/
        if ($folder_id) {
            // If inside a specific folder, maybe append folder name or ID to keep it clean?
            // For now, let's at least organize by Type and Year
        }
        
        // Clean type for folder name
        $clean_type = preg_replace('/[^a-zA-Z0-9]/', '', $type);
        $target_dir .= $clean_type . '/';
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $target_dir .= $year . '/';
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

        // Append version to filename on disk to avoid overwrite
        $safe_name = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $file['name']);
        $filename = 'v' . $version . '_' . $safe_name;
        
        // Ensure unique filename if somehow version collision happens (though DB versioning should prevent this)
        $target_path = $target_dir . $filename;
        $counter = 1;
        while (file_exists($target_path)) {
             $filename = 'v' . $version . '_' . $counter . '_' . $safe_name;
             $target_path = $target_dir . $filename;
             $counter++;
        }

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Check if columns exist (graceful fallback if DB update failed)
            $cols = $conn->query("SHOW COLUMNS FROM legislative_records LIKE 'version'");
            if ($cols->num_rows > 0) {
                $stmt = $conn->prepare("INSERT INTO legislative_records (title, type, month, year, author, file_path, folder_id, version, parent_version_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssiii", $title, $type, $month, $year, $author, $target_path, $folder_id, $version, $parent_version_id);
            } else {
                // Fallback for old schema
                $stmt = $conn->prepare("INSERT INTO legislative_records (title, type, month, year, author, file_path, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssi", $title, $type, $month, $year, $author, $target_path, $folder_id);
            }
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id, 'version' => $version]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move file']);
        }
        break;

    case 'get_file':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, title, type, month, year, author, created_at, last_accessed, version, parent_version_id FROM legislative_records WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($file = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'file' => $file]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found']);
        }
        break;

    case 'get_versions':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        
        // Fetch all versions in the family (parent + children)
        // First find the root parent
        $findParent = $conn->prepare("SELECT id, parent_version_id FROM legislative_records WHERE id = ?");
        $findParent->bind_param("i", $id);
        $findParent->execute();
        $res = $findParent->get_result();
        $row = $res->fetch_assoc();
        
        $root_id = $row['parent_version_id'] ? $row['parent_version_id'] : $id;
        
        $sql = "SELECT id, title, version, created_at, author FROM legislative_records WHERE id = ? OR parent_version_id = ? ORDER BY version DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $root_id, $root_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $versions = [];
        while ($v = $result->fetch_assoc()) {
            $versions[] = $v;
        }
        
        echo json_encode(['success' => true, 'versions' => $versions]);
        break;

    case 'get_files':
        $type = $_GET['type'] ?? '';
        $allowed_types = ['Ordinance', 'Resolution', 'Billing', 'Public Hearing', 'Meeting'];
        
        $sql = "SELECT id, title, type, month, year, author, created_at, last_accessed, version 
                FROM legislative_records 
                WHERE parent_version_id IS NULL";
        
        $params = [];
        $types = "";
        
        if ($type && in_array($type, $allowed_types)) {
            $sql .= " AND type = ?";
            $params[] = $type;
            $types = "s";
        } elseif ($type === 'All') {
             $sql .= " AND type IN ('Ordinance', 'Resolution', 'Billing', 'Public Hearing', 'Meeting')";
        }
        
        $sql .= " ORDER BY year DESC, month DESC, created_at DESC";
        
        $stmt = $conn->prepare($sql);
        if ($type && $type !== 'All' && in_array($type, $allowed_types)) {
            $stmt->bind_param($types, $type);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $files = [];
        while ($row = $result->fetch_assoc()) {
            $files[] = $row;
        }
        
        echo json_encode(['success' => true, 'files' => $files]);
        break;

    case 'get_archive_files':
        $folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
        if (!$folder_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid folder ID']);
            exit;
        }
        
        $sql = "SELECT id, name, file_path, author, created_at, version 
                FROM archive_files 
                WHERE folder_id = ?
                ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $folder_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $files = [];
        while ($row = $result->fetch_assoc()) {
            $files[] = $row;
        }
        
        echo json_encode(['success' => true, 'files' => $files]);
        break;

    case 'get_contents':
        // For dynamic loading (optional, currently pages fetch via PHP)
        break;
    
    case 'delete_record':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
            break;
        }
        // Fetch all related versions (root family)
        $find = $conn->prepare("SELECT id, parent_version_id FROM legislative_records WHERE id = ?");
        $find->bind_param("i", $id);
        $find->execute();
        $res = $find->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $find->close();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
            break;
        }
        $root_id = $row['parent_version_id'] ? (int)$row['parent_version_id'] : $id;
        $filesToDelete = [];
        $stmt = $conn->prepare("SELECT id, file_path FROM legislative_records WHERE id = ? OR parent_version_id = ?");
        $stmt->bind_param("ii", $root_id, $root_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($r = $result->fetch_assoc()) {
            $filesToDelete[] = $r;
        }
        $stmt->close();
        // Delete physical files (best-effort)
        foreach ($filesToDelete as $f) {
            $path = $f['file_path'] ?? '';
            if ($path) {
                $abs = (strpos($path, DIRECTORY_SEPARATOR) === 0) ? $path : (__DIR__ . DIRECTORY_SEPARATOR . $path);
                if (file_exists($abs)) @unlink($abs);
            }
        }
        // Delete DB rows
        $del = $conn->prepare("DELETE FROM legislative_records WHERE id = ? OR parent_version_id = ?");
        $del->bind_param("ii", $root_id, $root_id);
        if ($del->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        $del->close();
        break;
    
    case 'delete_folder':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid folder ID']);
            break;
        }
        // Ensure no child folders or records remain
        $childCount = 0;
        if ($rs = $conn->query("SELECT COUNT(*) as c FROM legislative_folders WHERE parent_id = " . $id)) {
            $r = $rs->fetch_assoc(); $childCount += (int)$r['c'];
        }
        if ($rs2 = $conn->query("SELECT COUNT(*) as c FROM legislative_records WHERE folder_id = " . $id)) {
            $r2 = $rs2->fetch_assoc(); $childCount += (int)$r2['c'];
        }
        if ($childCount > 0) {
            echo json_encode(['success' => false, 'message' => 'Folder not empty']);
            break;
        }
        $stmt = $conn->prepare("DELETE FROM legislative_folders WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>

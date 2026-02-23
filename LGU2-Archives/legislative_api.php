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
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

        // Append version to filename on disk to avoid overwrite
        $filename = time() . '_v' . $version . '_' . preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $file['name']);
        $target_path = $target_dir . $filename;

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

    case 'get_contents':
        // For dynamic loading (optional, currently pages fetch via PHP)
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>
<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// RBAC: Determine access level
$user_role = $_SESSION['user_role'];
$user_email = $_SESSION['user_email'];
$user_campus_raw = $_SESSION['user_campus'] ?? null;

// Campus name normalization
$campus_name_map = [
    'Malvar' => 'JPLPC Malvar',
    'Nasugbu' => 'ARASOF Nasugbu',
    'Pablo Borbon' => 'Pablo Borbon',
    'Alangilan' => 'Alangilan',
    'Lipa' => 'Lipa',
    'JPLPC Malvar' => 'JPLPC Malvar',
    'ARASOF Nasugbu' => 'ARASOF Nasugbu'
];
$user_campus = $campus_name_map[$user_campus_raw] ?? $user_campus_raw;

$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
$isCentralHead = in_array($user_email, $centralHeadEmails);
$canManage = !$isCentralHead;

// Check write permission
if (!$canManage) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to archive inventory items']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get item ID from request
    $input = json_decode(file_get_contents('php://input'), true);
    $item_id = $input['item_id'] ?? '';
    
    if (empty($item_id)) {
        echo json_encode(['success' => false, 'message' => 'Item ID is required']);
        exit;
    }
    
    // Check if inventory table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'inventory'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo json_encode(['success' => false, 'message' => 'Inventory table does not exist']);
        exit;
    }
    
    // Check if item exists
    $stmt = $pdo->prepare("SELECT id, item_name, name, campus FROM inventory WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    // Verify campus access for campus-specific users
    $canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && in_array($user_role, ['head', 'staff'])));
    
    if (!$canViewAll) {
        // Check if item campus matches user campus (both formats)
        $item_campus = $item['campus'];
        $campus_match = false;
        
        if ($user_campus === 'JPLPC Malvar') {
            $campus_match = ($item_campus === 'JPLPC Malvar' || $item_campus === 'Malvar');
        } elseif ($user_campus === 'ARASOF Nasugbu') {
            $campus_match = ($item_campus === 'ARASOF Nasugbu' || $item_campus === 'Nasugbu');
        } else {
            $campus_match = ($item_campus === $user_campus);
        }
        
        if (!$campus_match) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to archive this item']);
            exit;
        }
    }
    
    // Check if status column exists, if not add it
    $columns = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'status'")->rowCount();
    if ($columns == 0) {
        $pdo->exec("ALTER TABLE inventory ADD COLUMN status ENUM('available','borrowed','maintenance','archived') DEFAULT 'available' AFTER quantity");
    }
    
    // Archive the item by setting status to 'archived'
    $stmt = $pdo->prepare("UPDATE inventory SET status = 'archived', updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$item_id]);
    
    if ($result) {
        $item_name = $item['item_name'] ?: $item['name'] ?: 'Item';
        echo json_encode([
            'success' => true, 
            'message' => 'Item "' . $item_name . '" archived successfully! You can restore it from the Archives module.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to archive item']);
    }
    
} catch (Exception $e) {
    error_log("Error archiving inventory item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while archiving the item']);
}
?>

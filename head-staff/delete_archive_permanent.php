<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Check authentication - Only heads and staff
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
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

$canViewAll = ($user_campus === 'Pablo Borbon');

$pdo = getDBConnection();

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$id = intval($input['id'] ?? 0);

if (empty($type) || $id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    switch ($type) {
        case 'event':
            // Verify campus access before deleting
            $stmt = $pdo->prepare("SELECT campus, event_poster FROM events WHERE id = ? AND status = 'archived'");
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Event not found or not archived']);
                exit();
            }
            
            // Check campus permission
            if (!$canViewAll) {
                $event_campus = $event['campus'];
                $campus_match = false;
                
                if ($user_campus === 'JPLPC Malvar') {
                    $campus_match = ($event_campus === 'JPLPC Malvar' || $event_campus === 'Malvar');
                } elseif ($user_campus === 'ARASOF Nasugbu') {
                    $campus_match = ($event_campus === 'ARASOF Nasugbu' || $event_campus === 'Nasugbu');
                } else {
                    $campus_match = ($event_campus === $user_campus);
                }
                
                if (!$campus_match) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this event']);
                    exit();
                }
            }
            
            // Delete related records
            $stmt = $pdo->prepare("DELETE FROM event_participants WHERE event_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $pdo->prepare("DELETE FROM event_evaluations WHERE event_id = ?");
            $stmt->execute([$id]);
            
            // Delete the event
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            // Delete event poster file if exists
            if ($result && $event['event_poster'] && file_exists('../' . $event['event_poster'])) {
                @unlink('../' . $event['event_poster']);
            }
            break;
            
        case 'inventory':
            // Verify campus access before deleting
            $stmt = $pdo->prepare("SELECT campus FROM inventory WHERE id = ? AND status = 'archived'");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Inventory item not found or not archived']);
                exit();
            }
            
            // Check campus permission
            if (!$canViewAll) {
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
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this item']);
                    exit();
                }
            }
            
            // Check if item is currently borrowed
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowing_requests WHERE item_id = ? AND status IN ('approved', 'borrowed')");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Cannot delete item - it has active borrow requests']);
                exit();
            }
            
            // Delete the inventory item
            $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt->execute([$id]);
            break;
            
        default:
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            exit();
    }
    
    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Item permanently deleted']);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete item']);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete archive error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

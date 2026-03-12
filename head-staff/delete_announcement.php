<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated as staff
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'central', 'director'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
        exit;
    }
    
    // Delete the announcement (soft delete by setting is_active = 0)
    $stmt = $pdo->prepare("UPDATE announcements SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete announcement']);
    }
    
} catch (Exception $e) {
    error_log("Error deleting announcement: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the announcement']);
}
?>

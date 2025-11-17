<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get event ID from request
    $input = json_decode(file_get_contents('php://input'), true);
    $event_id = $input['event_id'] ?? '';
    
    if (empty($event_id)) {
        echo json_encode(['success' => false, 'message' => 'Event ID is required']);
        exit;
    }
    
    // Check if event exists and get image path for cleanup
    $stmt = $pdo->prepare("SELECT id, event_poster, title FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    // Delete the event from database
    // Note: Related records in event_participants and event_evaluations will be automatically
    // deleted due to ON DELETE CASCADE constraints
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $result = $stmt->execute([$event_id]);
    
    if ($result) {
        // Delete associated image file if it exists
        if ($event['event_poster'] && file_exists('../' . $event['event_poster'])) {
            @unlink('../' . $event['event_poster']);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Event "' . $event['title'] . '" deleted successfully!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete event']);
    }
    
} catch (Exception $e) {
    error_log("Error deleting event: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the event']);
}
?>
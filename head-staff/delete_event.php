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
    $stmt = $pdo->prepare("SELECT id, image_path, title FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    // Start transaction to ensure both event and announcements are deleted together
    $pdo->beginTransaction();
    
    try {
        // First, delete any announcements related to this event
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $deleted_announcements = $stmt->rowCount();
        
        // Then delete the event from database
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $result = $stmt->execute([$event_id]);
        
        if ($result) {
            // Commit the transaction
            $pdo->commit();
            
            // Delete associated image file if it exists
            if ($event['image_path'] && file_exists('../' . $event['image_path'])) {
                unlink('../' . $event['image_path']);
            }
            
            $message = 'Event "' . $event['title'] . '" deleted successfully!';
            if ($deleted_announcements > 0) {
                $message .= " Also deleted $deleted_announcements related announcement(s).";
            }
            
            echo json_encode([
                'success' => true, 
                'message' => $message
            ]);
        } else {
            $pdo->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete event']);
        }
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error deleting event: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the event']);
}
?>
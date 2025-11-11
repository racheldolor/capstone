<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    $event_id = $_GET['id'] ?? '';
    
    if (empty($event_id)) {
        echo json_encode(['success' => false, 'message' => 'Event ID is required']);
        exit;
    }
    
    // Get event details
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            description,
            start_date,
            end_date,
            location,
            campus,
            category,
            cultural_groups,
            image_path,
            status,
            created_at,
            updated_at
        FROM events 
        WHERE id = ?
    ");
    
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    // Decode cultural groups from JSON
    $event['cultural_groups'] = json_decode($event['cultural_groups'], true) ?? [];
    
    // Format dates for form display
    $event['start_date_formatted'] = date('Y-m-d', strtotime($event['start_date']));
    $event['end_date_formatted'] = date('Y-m-d', strtotime($event['end_date']));
    
    echo json_encode([
        'success' => true,
        'event' => $event
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching event: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load event details']);
}
?>
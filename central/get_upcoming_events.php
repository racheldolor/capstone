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
    
    // Get current month and year
    $current_month = date('Y-m');
    $start_of_month = $current_month . '-01';
    $end_of_month = date('Y-m-t'); // Last day of current month
    
    // Get events for current month only, ordered by start date
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
            status
        FROM events 
        WHERE status = 'active' 
        AND start_date >= ? 
        AND start_date <= ?
        AND start_date >= CURDATE()
        ORDER BY start_date ASC, title ASC
        LIMIT 5
    ");
    
    $stmt->execute([$start_of_month, $end_of_month]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process events data
    foreach ($events as &$event) {
        // Decode cultural groups from JSON
        $event['cultural_groups'] = json_decode($event['cultural_groups'], true) ?? [];
        
        // Format dates
        $event['start_date_formatted'] = date('M j, Y', strtotime($event['start_date']));
        $event['end_date_formatted'] = date('M j, Y', strtotime($event['end_date']));
        
        // Calculate days until event
        $days_until = (strtotime($event['start_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
        $event['days_until'] = max(0, floor($days_until));
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events,
        'total_count' => count($events)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching upcoming events: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load upcoming events']);
}
?>
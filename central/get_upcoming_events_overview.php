<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get upcoming events (next 30 days)
    $upcomingStmt = $pdo->prepare("
        SELECT 
            e.id,
            e.title,
            e.start_date,
            e.end_date,
            e.location,
            e.category,
            e.cultural_groups,
            COUNT(ep.student_id) as registered_count
        FROM events e
        LEFT JOIN event_participants ep ON e.id = ep.event_id
        WHERE e.start_date >= CURDATE() 
        AND e.start_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        GROUP BY e.id, e.title, e.start_date, e.end_date, e.location, e.category, e.cultural_groups
        ORDER BY e.start_date ASC
        LIMIT 10
    ");
    $upcomingStmt->execute();
    $upcomingEvents = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process cultural groups for each event
    foreach ($upcomingEvents as &$event) {
        $event['cultural_groups_array'] = json_decode($event['cultural_groups'], true) ?: [];
        $event['days_until'] = (new DateTime($event['start_date']))->diff(new DateTime())->days;
    }
    
    // Get event statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_upcoming,
            COUNT(CASE WHEN e.start_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as this_week,
            COUNT(CASE WHEN e.category = 'training' THEN 1 END) as trainings,
            COUNT(CASE WHEN e.category = 'performance' THEN 1 END) as performances
        FROM events e
        WHERE e.start_date >= CURDATE() 
        AND e.start_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent registrations (simplified - just show events with participants)
    $recentRegistrationsStmt = $pdo->prepare("
        SELECT 
            e.title as event_title,
            COUNT(ep.student_id) as new_registrations
        FROM events e
        INNER JOIN event_participants ep ON e.id = ep.event_id
        WHERE e.start_date >= CURDATE()
        GROUP BY e.id, e.title
        HAVING COUNT(ep.student_id) > 0
        ORDER BY new_registrations DESC
        LIMIT 5
    ");
    $recentRegistrationsStmt->execute();
    $recentRegistrations = $recentRegistrationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'upcoming_events' => $upcomingEvents,
        'statistics' => $stats,
        'recent_registrations' => $recentRegistrations
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch upcoming events data: ' . $e->getMessage()
    ]);
}
?>
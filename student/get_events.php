<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated as student
if (!isset($_SESSION['logged_in']) || $_SESSION['user_table'] !== 'student_artists') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get student's cultural group and campus
    $studentStmt = $pdo->prepare("SELECT cultural_group, campus FROM student_artists WHERE id = ?");
    $studentStmt->execute([$_SESSION['user_id']]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    $studentCulturalGroup = $student['cultural_group'];
    $studentCampus = $student['campus'];
    
    // Get events filtered by cultural group and campus, including participation status
    $eventsStmt = $pdo->prepare("
        SELECT e.*, 
               (ep.student_id IS NOT NULL AND ep.attendance_status != 'cancelled') as has_joined,
               ep.registration_date as joined_at,
               ep.attendance_status,
               CASE 
                   WHEN e.end_date < CURDATE() THEN 'ended'
                   WHEN e.start_date <= CURDATE() AND e.end_date >= CURDATE() THEN 'ongoing'
                   ELSE 'upcoming'
               END as event_status
        FROM events e
        LEFT JOIN event_participants ep ON e.id = ep.event_id AND ep.student_id = ?
        WHERE (e.cultural_groups LIKE ? OR e.cultural_groups LIKE ? OR e.cultural_groups = '[]')
        AND (e.venue = ? OR e.venue IS NULL OR e.venue = '')
        AND e.end_date >= CURDATE()
        AND e.status IN ('published', 'ongoing')
        ORDER BY e.start_date ASC
        LIMIT 50
    ");
    
    $groupPattern1 = '%"' . $studentCulturalGroup . '"%';
    $groupPattern2 = '%' . $studentCulturalGroup . '%';
    
    $eventsStmt->execute([$_SESSION['user_id'], $groupPattern1, $groupPattern2, $studentCampus]);
    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format events for display
    foreach ($events as &$event) {
        $event['cultural_groups_array'] = json_decode($event['cultural_groups'], true) ?: [];
        $event['formatted_start_date'] = date('F j, Y', strtotime($event['start_date']));
        $event['formatted_end_date'] = date('F j, Y', strtotime($event['end_date']));
        $event['is_multi_day'] = $event['start_date'] !== $event['end_date'];
        $event['has_joined'] = (bool)$event['has_joined'];
        $event['can_join'] = !$event['has_joined'] && $event['event_status'] === 'upcoming';
        $event['show_join_button'] = $event['event_status'] === 'upcoming';
        $event['joined_at_formatted'] = $event['joined_at'] ? date('F j, Y g:i A', strtotime($event['joined_at'])) : null;
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events,
        'student_group' => $studentCulturalGroup,
        'student_campus' => $studentCampus
    ]);
    
} catch (Exception $e) {
    error_log("Error getting events: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching events']);
}
?>
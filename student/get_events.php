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
    
    // Get student's cultural group
    $studentStmt = $pdo->prepare("SELECT cultural_group FROM student_artists WHERE id = ?");
    $studentStmt->execute([$_SESSION['user_id']]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    $studentCulturalGroup = $student['cultural_group'];
    
    // Get upcoming events for this student's cultural group
    $eventsStmt = $pdo->prepare("
        SELECT *
        FROM events
        WHERE (cultural_groups LIKE ? OR cultural_groups LIKE ?)
        AND start_date >= CURDATE()
        AND status = 'active'
        ORDER BY start_date ASC
        LIMIT 20
    ");
    
    $groupPattern1 = '%"' . $studentCulturalGroup . '"%';
    $groupPattern2 = '%' . $studentCulturalGroup . '%';
    
    $eventsStmt->execute([$groupPattern1, $groupPattern2]);
    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format events for display
    foreach ($events as &$event) {
        $event['cultural_groups_array'] = json_decode($event['cultural_groups'], true) ?: [];
        $event['formatted_start_date'] = date('F j, Y', strtotime($event['start_date']));
        $event['formatted_end_date'] = date('F j, Y', strtotime($event['end_date']));
        $event['is_multi_day'] = $event['start_date'] !== $event['end_date'];
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events,
        'student_group' => $studentCulturalGroup
    ]);
    
} catch (Exception $e) {
    error_log("Error getting events: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching events']);
}
?>
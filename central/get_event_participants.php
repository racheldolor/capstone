<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated as head or staff
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if event_id is provided
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

$event_id = (int)$_GET['event_id'];

try {
    $pdo = getDBConnection();
    
    // First, get event details
    $eventStmt = $pdo->prepare("
        SELECT id, title, start_date, end_date, location, category
        FROM events 
        WHERE id = ?
    ");
    $eventStmt->execute([$event_id]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    // Get participants for this event
    $participantsStmt = $pdo->prepare("
        SELECT 
            ep.student_id,
            ep.joined_at,
            sa.first_name,
            sa.middle_name,
            sa.last_name,
            sa.sr_code,
            sa.email,
            sa.cultural_group,
            sa.campus,
            sa.college,
            sa.program,
            sa.year_level,
            sa.contact_number
        FROM event_participants ep
        JOIN student_artists sa ON ep.student_id = sa.id
        WHERE ep.event_id = ?
        ORDER BY ep.joined_at ASC
    ");
    
    $participantsStmt->execute([$event_id]);
    $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format participant data
    foreach ($participants as &$participant) {
        $participant['full_name'] = trim(
            ($participant['first_name'] ?? '') . ' ' . 
            ($participant['middle_name'] ?? '') . ' ' . 
            ($participant['last_name'] ?? '')
        );
        $participant['formatted_joined_date'] = date('F j, Y g:i A', strtotime($participant['joined_at']));
    }
    
    // Format event data
    $event['formatted_start_date'] = date('F j, Y', strtotime($event['start_date']));
    $event['formatted_end_date'] = date('F j, Y', strtotime($event['end_date']));
    $event['is_multi_day'] = $event['start_date'] !== $event['end_date'];
    
    echo json_encode([
        'success' => true,
        'event' => $event,
        'participants' => $participants,
        'participants_count' => count($participants)
    ]);
    
} catch (Exception $e) {
    error_log("Error getting event participants: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching participants data'
    ]);
}
?>
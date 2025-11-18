<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated as head, staff, or central
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
    
    // First, check if events table exists and get event details
    $eventStmt = $pdo->prepare("
        SELECT id, title, start_date, end_date, location, category
        FROM events 
        WHERE id = ?
    ");
    $eventStmt->execute([$event_id]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found (ID: ' . $event_id . ')']);
        exit;
    }
    
    // Get participants for this event
    $participantsStmt = $pdo->prepare("
        SELECT 
            ep.student_id,
            ep.registration_date,
            ep.attendance_status,
            ep.payment_status,
            ep.student_name,
            ep.student_sr_code,
            ep.student_email,
            ep.student_contact,
            ep.student_campus,
            ep.student_program,
            ep.student_year,
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
        LEFT JOIN student_artists sa ON ep.student_id = sa.id
        WHERE ep.event_id = ?
        ORDER BY ep.registration_date ASC
    ");
    
    $participantsStmt->execute([$event_id]);
    $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format participant data
    foreach ($participants as &$participant) {
        // Use student_artists data if available, otherwise fall back to event_participants data
        $first_name = $participant['first_name'] ?: $participant['student_name'];
        $middle_name = $participant['middle_name'] ?: '';
        $last_name = $participant['last_name'] ?: '';
        
        // If we don't have separate names from student_artists, try to parse student_name
        if (!$participant['first_name'] && $participant['student_name']) {
            $name_parts = explode(' ', $participant['student_name']);
            $first_name = $name_parts[0] ?? '';
            $last_name = end($name_parts) ?? '';
            if (count($name_parts) > 2) {
                $middle_name = $name_parts[1] ?? '';
            }
        }
        
        $participant['full_name'] = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
        $participant['formatted_registration_date'] = date('F j, Y g:i A', strtotime($participant['registration_date']));
        
        // Use student_artists data when available, otherwise use event_participants stored data
        $participant['display_sr_code'] = $participant['sr_code'] ?: $participant['student_sr_code'];
        $participant['display_email'] = $participant['email'] ?: $participant['student_email'];
        $participant['display_campus'] = $participant['campus'] ?: $participant['student_campus'];
        $participant['display_program'] = $participant['program'] ?: $participant['student_program'];
        $participant['display_year'] = $participant['year_level'] ?: $participant['student_year'];
        $participant['display_contact'] = $participant['contact_number'] ?: $participant['student_contact'];
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
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
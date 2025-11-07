<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$pdo = getDBConnection();
$student_id = $_SESSION['user_id'];

try {
    // First, get the student's information to understand which table they're from
    $student_info = null;
    
    // Check student_artists table first
    $stmt = $pdo->prepare("SELECT id, email FROM student_artists WHERE id = ?");
    $stmt->execute([$student_id]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student_info) {
        // If not found in student_artists, check users table
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_info) {
            // Try to find corresponding student_artists record by email
            $stmt = $pdo->prepare("SELECT id, email FROM student_artists WHERE email = ?");
            $stmt->execute([$user_info['email']]);
            $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (!$student_info) {
        echo json_encode(['success' => false, 'message' => 'Student record not found']);
        exit();
    }
    
    // Now get events using the correct student_id from student_artists table
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            e.id,
            e.title,
            e.description,
            e.start_date,
            e.end_date,
            e.location,
            e.category,
            e.cultural_groups,
            ep.joined_at as joined_date,
            DATE_FORMAT(e.start_date, '%M %d, %Y') as formatted_start_date,
            DATE_FORMAT(e.end_date, '%M %d, %Y') as formatted_end_date,
            CASE 
                WHEN e.start_date > NOW() THEN 'upcoming'
                WHEN e.start_date <= NOW() AND e.end_date >= NOW() THEN 'ongoing'
                WHEN e.end_date < NOW() THEN 'completed'
                ELSE 'unknown'
            END as date_status,
            CASE 
                WHEN e.start_date = e.end_date THEN false
                ELSE true
            END as is_multi_day
        FROM events e
        INNER JOIN event_participants ep ON e.id = ep.event_id
        WHERE ep.student_id = ?
        ORDER BY e.start_date DESC
    ");
    
    $stmt->execute([$student_info['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process cultural groups for each event
    foreach ($events as &$event) {
        if ($event['cultural_groups']) {
            $event['cultural_groups_array'] = explode(',', $event['cultural_groups']);
        } else {
            $event['cultural_groups_array'] = [];
        }
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);
    
} catch (Exception $e) {
    error_log("Get student events error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving events']);
}
?>
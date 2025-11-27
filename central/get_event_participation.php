<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated as head or staff
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// RBAC: Determine access level
$user_role = $_SESSION['user_role'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_campus = $_SESSION['user_campus'] ?? null;

$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
$isCentralHead = in_array($user_email, $centralHeadEmails);
$canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && $user_role === 'central'));
$canManage = !$isCentralHead;

// Build campus filter for SQL
$campusFilter = '';
$campusParams = [];
if (!$canViewAll && $user_campus) {
    $campusFilter = ' WHERE e.campus = ?';
    $campusParams[] = $user_campus;
}

try {
    $pdo = getDBConnection();
    
    // Get all events with participation statistics and campus filtering
    $whereClause = $campusFilter ? $campusFilter : '';
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.title,
            e.description,
            e.location,
            e.start_date,
            e.end_date,
            e.category,
            e.cultural_groups,
            e.status,
            e.campus,
            COUNT(ep.student_id) as participants_count,
            e.created_at
        FROM events e
        LEFT JOIN event_participants ep ON e.id = ep.event_id
        " . $whereClause . "
        GROUP BY e.id, e.title, e.description, e.location, e.start_date, e.end_date, 
                 e.category, e.cultural_groups, e.status, e.campus, e.created_at
        ORDER BY e.created_at DESC
    ");
    
    $stmt->execute($campusParams);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for display
    foreach ($events as &$event) {
        $event['cultural_groups_array'] = json_decode($event['cultural_groups'], true) ?: [];
        $event['formatted_start_date'] = date('F j, Y', strtotime($event['start_date']));
        $event['formatted_end_date'] = date('F j, Y', strtotime($event['end_date']));
        $event['formatted_created_date'] = date('F j, Y g:i A', strtotime($event['created_at']));
        $event['is_multi_day'] = $event['start_date'] !== $event['end_date'];
        $event['participants_count'] = (int)$event['participants_count'];
        
        // Determine event status based on dates
        $today = date('Y-m-d');
        $event_start = date('Y-m-d', strtotime($event['start_date']));
        $event_end = date('Y-m-d', strtotime($event['end_date']));
        
        if ($event_start > $today) {
            $event['date_status'] = 'upcoming';
        } elseif ($event_end < $today) {
            $event['date_status'] = 'completed';
        } else {
            $event['date_status'] = 'ongoing';
        }
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events,
        'total_events' => count($events),
        'total_participants' => array_sum(array_column($events, 'participants_count'))
    ]);
    
} catch (Exception $e) {
    error_log("Error getting event participation: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching event participation data'
    ]);
}
?>
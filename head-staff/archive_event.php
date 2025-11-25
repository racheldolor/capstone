<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// RBAC: Determine access level
$user_role = $_SESSION['user_role'];
$user_email = $_SESSION['user_email'];
$user_campus_raw = $_SESSION['user_campus'] ?? null;

// Campus name normalization
$campus_name_map = [
    'Malvar' => 'JPLPC Malvar',
    'Nasugbu' => 'ARASOF Nasugbu',
    'Pablo Borbon' => 'Pablo Borbon',
    'Alangilan' => 'Alangilan',
    'Lipa' => 'Lipa',
    'JPLPC Malvar' => 'JPLPC Malvar',
    'ARASOF Nasugbu' => 'ARASOF Nasugbu'
];
$user_campus = $campus_name_map[$user_campus_raw] ?? $user_campus_raw;

$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
$isCentralHead = in_array($user_email, $centralHeadEmails);
$canManage = !$isCentralHead;

// Check write permission
if (!$canManage) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to archive events']);
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
    
    // Check if event exists
    $stmt = $pdo->prepare("SELECT id, title, campus FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    // Verify campus access for campus-specific users
    $canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && in_array($user_role, ['head', 'staff'])));
    
    if (!$canViewAll) {
        // Check if event campus matches user campus (both formats)
        $event_campus = $event['campus'];
        $campus_match = false;
        
        if ($user_campus === 'JPLPC Malvar') {
            $campus_match = ($event_campus === 'JPLPC Malvar' || $event_campus === 'Malvar');
        } elseif ($user_campus === 'ARASOF Nasugbu') {
            $campus_match = ($event_campus === 'ARASOF Nasugbu' || $event_campus === 'Nasugbu');
        } else {
            $campus_match = ($event_campus === $user_campus);
        }
        
        if (!$campus_match) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to archive this event']);
            exit;
        }
    }
    
    // Archive the event by setting status to 'archived'
    $stmt = $pdo->prepare("UPDATE events SET status = 'archived', updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$event_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Event "' . $event['title'] . '" archived successfully! You can restore it from the Archives module.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to archive event']);
    }
    
} catch (Exception $e) {
    error_log("Error archiving event: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while archiving the event']);
}
?>

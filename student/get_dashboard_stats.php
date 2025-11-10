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
$user_table = $_SESSION['user_table'] ?? 'users';

try {
    // Get user email for cross-table lookups
    $user_email = null;
    if ($user_table === 'student_artists') {
        $stmt = $pdo->prepare("SELECT email FROM student_artists WHERE id = ?");
        $stmt->execute([$student_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_email = $user_data['email'] ?? null;
    } else {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_email = $user_data['email'] ?? null;
    }
    
    // Count upcoming events (events this month)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM events 
        WHERE status = 'active' 
        AND start_date >= CURDATE() 
        AND MONTH(start_date) = MONTH(CURDATE()) 
        AND YEAR(start_date) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $upcoming_events = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Count currently borrowed costumes for this student
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM borrowing_requests 
        WHERE student_id = ? 
        AND status IN ('approved') 
        AND current_status IN ('active', 'pending_return')
    ");
    $stmt->execute([$student_id]);
    $borrowed_costumes = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Count total performances/events this student has participated in
    $performance_count = 0;
    
    // First try with current student_id
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM event_participants ep
        JOIN events e ON ep.event_id = e.id
        WHERE ep.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $performance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // If no results and we have email, try to find by email match
    if ($performance_count == 0 && $user_email) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM event_participants ep
            JOIN events e ON ep.event_id = e.id
            JOIN student_artists sa ON ep.student_id = sa.id
            WHERE sa.email = ?
        ");
        $stmt->execute([$user_email]);
        $performance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }
    
    // Get student's cultural group for announcements filtering
    $studentCulturalGroup = null;
    if ($user_table === 'student_artists') {
        $stmt = $pdo->prepare("SELECT cultural_group FROM student_artists WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        $studentCulturalGroup = $student['cultural_group'] ?? null;
    }
    
    // Count new announcements (posted in last 7 days, excluding finished events, for student's cultural group)
    if ($studentCulturalGroup) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM announcements a
            LEFT JOIN events e ON a.event_id = e.id
            WHERE a.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND (a.target_groups LIKE ? OR a.target_groups LIKE ?)
            AND (a.event_id IS NULL OR e.end_date >= CURDATE())
        ");
        $groupPattern1 = '%"' . $studentCulturalGroup . '"%';
        $groupPattern2 = '%' . $studentCulturalGroup . '%';
        $stmt->execute([$groupPattern1, $groupPattern2]);
        $new_announcements = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } else {
        // Fallback for students without cultural group
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM announcements a
            LEFT JOIN events e ON a.event_id = e.id
            WHERE a.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND (a.event_id IS NULL OR e.end_date >= CURDATE())
        ");
        $stmt->execute();
        $new_announcements = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'upcoming_events' => $upcoming_events,
            'borrowed_costumes' => $borrowed_costumes,
            'total_performances' => $performance_count,
            'new_announcements' => $new_announcements
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Dashboard stats API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving dashboard statistics'
    ]);
}
?>
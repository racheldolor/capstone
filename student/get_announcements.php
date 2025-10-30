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
    
    // Get announcements for this student's cultural group
    $announcementsStmt = $pdo->prepare("
        SELECT a.*, e.start_date, e.end_date, e.location, e.category
        FROM announcements a
        LEFT JOIN events e ON a.event_id = e.id
        WHERE a.target_groups LIKE ? OR a.target_groups LIKE ?
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    
    $groupPattern1 = '%"' . $studentCulturalGroup . '"%';
    $groupPattern2 = '%' . $studentCulturalGroup . '%';
    
    $announcementsStmt->execute([$groupPattern1, $groupPattern2]);
    $announcements = $announcementsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'announcements' => $announcements,
        'student_group' => $studentCulturalGroup
    ]);
    
} catch (Exception $e) {
    error_log("Error getting announcements: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching announcements']);
}
?>
<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $student_id = intval($input['student_id']);
    
    if (empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
        exit();
    }
    
    $pdo = getDBConnection();
    
    // Get student profile from student_artists table
    $stmt = $pdo->prepare("
        SELECT * FROM student_artists 
        WHERE id = ? AND status IN ('active', 'suspended')
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }
    
    // Get the student's original application to fetch performance_type (desired cultural group)
    $stmt = $pdo->prepare("
        SELECT performance_type 
        FROM applications 
        WHERE sr_code = ? OR email = ?
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$student['sr_code'], $student['email']]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Parse and clean the performance_type to extract just the group name
    $desired_group = null;
    if ($application && $application['performance_type']) {
        $performance_type = $application['performance_type'];
        // Extract group name after the colon (e.g., "dance: Indak Yaman Dance Varsity" -> "Indak Yaman Dance Varsity")
        if (strpos($performance_type, ':') !== false) {
            $desired_group = trim(substr($performance_type, strpos($performance_type, ':') + 1));
        } else {
            $desired_group = $performance_type;
        }
    }
    
    // Add desired cultural group to student data
    $student['desired_cultural_group'] = $desired_group;
    
    echo json_encode([
        'success' => true, 
        'student' => $student
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching student profile: ' . $e->getMessage()
    ]);
}
?>
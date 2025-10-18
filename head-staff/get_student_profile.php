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
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }
    
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
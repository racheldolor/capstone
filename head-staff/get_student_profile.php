<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'central', 'admin', 'director'])) {
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
    
    // Get participation records
    $stmt = $pdo->prepare("
        SELECT participation_date as date, event_name as title, 
               participation_level as level, rank_award as rank
        FROM student_participation_records
        WHERE student_id = ?
        ORDER BY participation_date DESC
    ");
    $stmt->execute([$student_id]);
    $participation = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get affiliation records
    $stmt = $pdo->prepare("
        SELECT position, organization, years_active as year
        FROM student_affiliation_records
        WHERE student_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$student_id]);
    $affiliation = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add participation and affiliation to student data
    $student['participation'] = $participation;
    $student['affiliation'] = $affiliation;
    
    // Store the student_id for reference
    $student['student_id'] = $student_id;
    
    // profile_photo is already in the student_artists data, no need to fetch from applications
    
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
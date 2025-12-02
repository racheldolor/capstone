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
    $cultural_group = trim($input['cultural_group']);
    
    if (empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
        exit();
    }
    
    // Validate cultural group options
    $valid_groups = [
        '', 
        'Dulaang Batangan',
        'BatStateU Dance Company', 
        'Diwayanis Dance Theatre',
        'BatStateU Band',
        'Indak Yaman Dance Varsity',
        'Ritmo Voice',
        'Sandugo Dance Group',
        'Areglo Band',
        'Teatro Aliwana',
        'The Levites',
        'Melophiles',
        'Sindayog'
    ];
    if (!in_array($cultural_group, $valid_groups)) {
        echo json_encode(['success' => false, 'message' => 'Invalid cultural group']);
        exit();
    }
    
    $pdo = getDBConnection();
    
    // Check if cultural_group column exists, if not add it
    try {
        $stmt = $pdo->prepare("SELECT cultural_group FROM student_artists LIMIT 1");
        $stmt->execute();
    } catch (Exception $e) {
        // Column doesn't exist, add it
        $pdo->exec("ALTER TABLE student_artists ADD COLUMN cultural_group VARCHAR(100) DEFAULT NULL");
    }
    
    // Check student status first
    $statusStmt = $pdo->prepare("SELECT status FROM student_artists WHERE id = ?");
    $statusStmt->execute([$student_id]);
    $studentStatus = $statusStmt->fetchColumn();
    
    if ($studentStatus === 'suspended') {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot update cultural group assignment - student account is suspended'
        ]);
        exit();
    }
    
    // Update the cultural group assignment (only for active students)
    $stmt = $pdo->prepare("
        UPDATE student_artists 
        SET cultural_group = ? 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$cultural_group ?: null, $student_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Cultural group assignment updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Student not found or no changes made'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating cultural group: ' . $e->getMessage()
    ]);
}
?>
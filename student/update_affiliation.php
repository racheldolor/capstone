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
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    if ($action === 'add') {
        // Add new affiliation record directly to student table
        $stmt = $pdo->prepare("
            INSERT INTO student_affiliation_records 
            (student_id, position, organization, years_active) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $student_id,
            $data['position'] ?? '',
            $data['organization'] ?? '',
            $data['years_active'] ?? ''
        ]);
        
        $newId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Affiliation record added',
            'id' => $newId
        ]);
        
    } elseif ($action === 'delete') {
        // Delete affiliation record
        $stmt = $pdo->prepare("
            DELETE FROM student_affiliation_records 
            WHERE id = ? AND student_id = ?
        ");
        
        $stmt->execute([
            $data['id'],
            $student_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Affiliation record deleted'
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Error updating affiliation: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

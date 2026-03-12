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
        // Add new competition record directly to student table
        $stmt = $pdo->prepare("
            INSERT INTO student_competition_records 
            (student_id, competition_date, event_name, competition_level, rank_award) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $student_id,
            $data['date'] ?? null,
            $data['event_name'] ?? '',
            $data['level'] ?? null,
            $data['rank_award'] ?? ''
        ]);
        
        $newId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Competition record added',
            'id' => $newId
        ]);
        
    } elseif ($action === 'delete') {
        // Delete competition record
        $stmt = $pdo->prepare("
            DELETE FROM student_competition_records 
            WHERE id = ? AND student_id = ?
        ");
        
        $stmt->execute([
            $data['id'],
            $student_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Competition record deleted'
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Error updating competition: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

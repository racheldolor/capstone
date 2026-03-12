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
        // Add new participation record directly to student table
        $stmt = $pdo->prepare("
            INSERT INTO student_participation_records 
            (student_id, participation_date, event_name, venue, participation_level, rank_award) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $student_id,
            $data['date'] ?? null,
            $data['event_name'] ?? '',
            $data['venue'] ?? '',
            $data['level'] ?? null,
            $data['rank_award'] ?? ''
        ]);
        
        $newId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Participation record added',
            'id' => $newId
        ]);
        
    } elseif ($action === 'delete') {
        // Delete participation record
        $stmt = $pdo->prepare("
            DELETE FROM student_participation_records 
            WHERE id = ? AND student_id = ?
        ");
        
        $stmt->execute([
            $data['id'],
            $student_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Participation record deleted'
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Error updating participation: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

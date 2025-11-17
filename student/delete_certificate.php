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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['certificate_id'])) {
        echo json_encode(['success' => false, 'message' => 'Certificate ID is required']);
        exit();
    }
    
    $certificate_id = $input['certificate_id'];
    
    // Get certificate info before deleting (to remove file)
    $stmt = $pdo->prepare("
        SELECT certificate_file as file_path 
        FROM student_certificates 
        WHERE id = ? AND student_id = ?
    ");
    $stmt->execute([$certificate_id, $student_id]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificate) {
        echo json_encode(['success' => false, 'message' => 'Certificate not found or access denied']);
        exit();
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM student_certificates WHERE id = ? AND student_id = ?");
    $stmt->execute([$certificate_id, $student_id]);
    
    if ($stmt->rowCount() > 0) {
        // Delete physical file
        $file_path = '../' . $certificate['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        echo json_encode(['success' => true, 'message' => 'Certificate deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Certificate not found']);
    }
    
} catch (Exception $e) {
    error_log("Delete certificate error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting certificate']);
}
?>
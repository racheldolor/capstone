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
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            certificate_name as title, 
            description, 
            issued_date as date_received, 
            certificate_file as file_path, 
            created_at as uploaded_at,
            certificate_type,
            certificate_number
        FROM student_certificates 
        WHERE student_id = ?
        ORDER BY issued_date DESC, created_at DESC
    ");
    
    $stmt->execute([$student_id]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates and ensure file paths are accessible
    foreach ($certificates as &$cert) {
        $cert['date_received'] = date('Y-m-d', strtotime($cert['date_received']));
        $cert['uploaded_at'] = date('M j, Y g:i A', strtotime($cert['uploaded_at']));
        
        // Ensure file path is web-accessible from student directory
        $cert['file_path'] = '../' . $cert['file_path'];
    }
    
    echo json_encode([
        'success' => true,
        'certificates' => $certificates
    ]);
    
} catch (Exception $e) {
    error_log("Get certificates error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving certificates']);
}
?>
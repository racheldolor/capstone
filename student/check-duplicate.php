<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'check_sr_code':
            $srCode = $_POST['sr_code'] ?? '';
            
            if (empty($srCode)) {
                echo json_encode(['exists' => false]);
                exit;
            }
            
            // Check in student_artists table for existing SR codes
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_artists WHERE sr_code = ?");
            $stmt->execute([$srCode]);
            $count = $stmt->fetchColumn();
            
            echo json_encode(['exists' => $count > 0]);
            break;
            
        case 'check_email':
            $email = $_POST['email'] ?? '';
            
            if (empty($email)) {
                echo json_encode(['exists' => false]);
                exit;
            }
            
            // Check in student_artists table for existing emails
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_artists WHERE email = ?");
            $stmt->execute([$email]);
            $count = $stmt->fetchColumn();
            
            echo json_encode(['exists' => $count > 0]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Duplicate check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
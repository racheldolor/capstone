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
    // Validate required fields
    if (!isset($_POST['title']) || !isset($_POST['date']) || !isset($_FILES['certificate_file'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $date_received = $_POST['date'];
    $file = $_FILES['certificate_file'];
    
    if (empty($title) || empty($date_received)) {
        echo json_encode(['success' => false, 'message' => 'Title and date are required']);
        exit();
    }
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File upload error']);
        exit();
    }
    
    // Check file size (5MB limit)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
        exit();
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF files are allowed']);
        exit();
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/certificates/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'cert_' . $student_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    $relative_path = 'uploads/certificates/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit();
    }
    
    // Save to database
    $stmt = $pdo->prepare("
        INSERT INTO student_certificates (student_id, title, description, date_received, file_path, uploaded_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$student_id, $title, $description, $date_received, $relative_path]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Certificate uploaded successfully',
        'certificate_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    // Clean up uploaded file if database insert fails
    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }
    
    error_log("Certificate upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
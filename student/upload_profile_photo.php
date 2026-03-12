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
    if (!isset($_FILES['profile_photo'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit();
    }
    
    $file = $_FILES['profile_photo'];
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/pjpeg'];
    $allowedExtensions = ['jpg', 'jpeg', 'jfif', 'png'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, JFIF, and PNG files are allowed']);
        exit();
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
        exit();
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/profile_photos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Use student ID as filename to replace old photo (one photo per student)
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $student_id . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Delete old profile photo files for this student (all extensions)
    $stmt = $pdo->prepare("SELECT profile_photo FROM student_artists WHERE id = ?");
    $stmt->execute([$student_id]);
    $currentStudent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentStudent && !empty($currentStudent['profile_photo']) && file_exists('../' . $currentStudent['profile_photo'])) {
        unlink('../' . $currentStudent['profile_photo']);
    }
    
    // Also delete any other files with this student's ID (different extensions)
    foreach (['jpg', 'jpeg', 'jfif', 'png'] as $ext) {
        $oldFile = $uploadDir . 'profile_' . $student_id . '.' . $ext;
        if (file_exists($oldFile) && $oldFile !== $filepath) {
            unlink($oldFile);
        }
    }
    
    // Upload new file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relativePath = 'uploads/profile_photos/' . $filename;
        
        // Update student_artists table (this is the main table used for profile display)
        $stmt = $pdo->prepare("UPDATE student_artists SET profile_photo = ?, updated_at = NOW() WHERE id = ?");
        $updateResult = $stmt->execute([$relativePath, $student_id]);
        
        // Also update applications table if exists
        try {
            $stmt = $pdo->prepare("UPDATE applications SET profile_photo = ? WHERE submitted_by = ? OR email = (SELECT email FROM student_artists WHERE id = ?)");
            $stmt->execute([$relativePath, $student_id, $student_id]);
        } catch (Exception $e) {
            error_log("Could not update applications table: " . $e->getMessage());
        }
        
        // Verify it was saved
        $stmt = $pdo->prepare("SELECT profile_photo FROM student_artists WHERE id = ?");
        $stmt->execute([$student_id]);
        $verify = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile photo uploaded successfully',
            'photo_url' => $relativePath,
            'verified_path' => $verify['profile_photo'] ?? null,
            'file_exists' => file_exists($filepath)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
    
} catch (Exception $e) {
    error_log("Error uploading profile photo: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

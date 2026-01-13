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
    // Get student's current photo from student_artists table (main source)
    $stmt = $pdo->prepare("SELECT profile_photo FROM student_artists WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student && !empty($student['profile_photo'])) {
        $photoPath = '../' . $student['profile_photo'];
        
        // Delete file if exists
        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
        
        // Update student_artists table (primary)
        $stmt = $pdo->prepare("UPDATE student_artists SET profile_photo = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$student_id]);
        
        // Also update applications table if exists
        try {
            $stmt = $pdo->prepare("UPDATE applications SET profile_photo = NULL WHERE submitted_by = ? OR email = (SELECT email FROM student_artists WHERE id = ?)");
            $stmt->execute([$student_id, $student_id]);
        } catch (Exception $e) {
            error_log("Could not update applications table: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile photo deleted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No profile photo to delete']);
    }
    
} catch (Exception $e) {
    error_log("Error deleting profile photo: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

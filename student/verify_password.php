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
    // Get posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Password is required']);
        exit();
    }
    
    $password = $data['password'];
    $user_table = $_SESSION['user_table'] ?? 'users';
    
    // Get user's stored password
    if ($user_table === 'student_artists') {
        $stmt = $pdo->prepare("SELECT password FROM student_artists WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    }
    
    $stmt->execute([$student_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        echo json_encode(['success' => true, 'message' => 'Password verified']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    }
    
} catch (Exception $e) {
    error_log("Error verifying password: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error verifying password'
    ]);
}

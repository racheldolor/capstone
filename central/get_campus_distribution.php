<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get campus distribution
    $stmt = $pdo->prepare("
        SELECT 
            campus,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM student_artists WHERE status = 'active')), 1) as percentage
        FROM student_artists 
        WHERE status = 'active' AND campus IS NOT NULL
        GROUP BY campus 
        ORDER BY count DESC
    ");
    $stmt->execute();
    $campusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $totalStmt = $pdo->prepare("SELECT COUNT(*) as total FROM student_artists WHERE status = 'active'");
    $totalStmt->execute();
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Prepare response
    $response = [
        'success' => true,
        'campusDistribution' => $campusData,
        'totalStudents' => $total
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch campus distribution data'
    ]);
}
?>
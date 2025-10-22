<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get cultural group distribution
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN cultural_group IS NULL OR cultural_group = '' THEN 'Not Assigned'
                ELSE cultural_group
            END as group_name,
            COUNT(*) as count
        FROM student_artists 
        WHERE status = 'active'
        GROUP BY CASE 
            WHEN cultural_group IS NULL OR cultural_group = '' THEN 'Not Assigned'
            ELSE cultural_group
        END
        ORDER BY count DESC
    ");
    $stmt->execute();
    $groupData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $totalStmt = $pdo->prepare("SELECT COUNT(*) as total FROM student_artists WHERE status = 'active'");
    $totalStmt->execute();
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Prepare response
    $response = [
        'success' => true,
        'groupDistribution' => $groupData,
        'totalStudents' => $total
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch cultural group distribution data'
    ]);
}
?>
<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// RBAC: Determine access level
$user_role = $_SESSION['user_role'];
$user_email = $_SESSION['user_email'];
$user_campus = $_SESSION['user_campus'] ?? null;

// Central Head identification
$centralHeadEmails = [
    'mark.central@g.batstate-u.edu.ph',
];

$isCentralHead = in_array($user_email, $centralHeadEmails);
$canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && $user_role === 'central'));

// Build campus filter
$campusFilter = '';
$campusParams = [];
if (!$canViewAll && $user_campus) {
    $campusFilter = ' AND campus = ?';
    $campusParams[] = $user_campus;
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get campus distribution with RBAC filtering
    $sql = "
        SELECT 
            campus,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM student_artists WHERE status = 'active'" . $campusFilter . ")), 1) as percentage
        FROM student_artists 
        WHERE status = 'active' AND campus IS NOT NULL" . $campusFilter . "
        GROUP BY campus 
        ORDER BY count DESC
    ";
    $stmt = $pdo->prepare($sql);
    // Execute with campus parameters for both the subquery and main query
    $executeParams = array_merge($campusParams, $campusParams);
    $stmt->execute($executeParams);
    $campusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count with RBAC filtering
    $totalSql = "SELECT COUNT(*) as total FROM student_artists WHERE status = 'active'" . $campusFilter;
    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute($campusParams);
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
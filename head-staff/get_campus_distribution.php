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

$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
$isCentralHead = in_array($user_email, $centralHeadEmails);
$canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && in_array($user_role, ['head', 'staff'])));

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get search parameter
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build WHERE clause for search
    $whereConditions = ["status = 'active'", "campus IS NOT NULL"];
    $params = [];
    
    // Apply campus filter for campus-specific users
    if (!$canViewAll && $user_campus) {
        $whereConditions[] = "campus = ?";
        $params[] = $user_campus;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR sr_code LIKE ?)";
        $searchParam = "%$search%";
        $searchParams = array_fill(0, 5, $searchParam);
        $params = array_merge($params, $searchParams);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get campus distribution with search filter
    $sql = "
        SELECT 
            campus,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM student_artists WHERE $whereClause)), 1) as percentage
        FROM student_artists 
        WHERE $whereClause
        GROUP BY campus 
        ORDER BY count DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, $params)); // params twice for subquery
    $campusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count with search filter
    $totalSql = "SELECT COUNT(*) as total FROM student_artists WHERE $whereClause";
    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute($params);
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Prepare response
    $response = [
        'success' => true,
        'campusDistribution' => $campusData,
        'totalStudents' => $total,
        'searchApplied' => !empty($search)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch campus distribution data: ' . $e->getMessage()
    ]);
}
?>
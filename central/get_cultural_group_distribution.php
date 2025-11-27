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
    
    // Define all available cultural groups
    $allGroups = [
        'Dulaang Batangan',
        'BatStateU Dance Company', 
        'Diwayanis Dance Theatre',
        'BatStateU Band',
        'Indak Yaman Dance Varsity',
        'Ritmo Voice',
        'Sandugo Dance Group',
        'Areglo Band',
        'Teatro Aliwana',
        'The Levites',
        'Melophiles',
        'Sindayog'
    ];
    
    // Get actual student counts for each group with RBAC filtering
    $sql = "
        SELECT 
            CASE 
                WHEN cultural_group IS NULL OR cultural_group = '' THEN 'Not Assigned'
                ELSE cultural_group
            END as group_name,
            COUNT(*) as count
        FROM student_artists 
        WHERE status = 'active'" . $campusFilter . "
        GROUP BY CASE 
            WHEN cultural_group IS NULL OR cultural_group = '' THEN 'Not Assigned'
            ELSE cultural_group
        END
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($campusParams);
    $actualCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create associative array for easy lookup
    $countLookup = [];
    foreach ($actualCounts as $row) {
        $countLookup[$row['group_name']] = $row['count'];
    }
    
    // Build complete group data including groups with 0 students
    $groupData = [];
    foreach ($allGroups as $groupName) {
        $groupData[] = [
            'group_name' => $groupName,
            'count' => isset($countLookup[$groupName]) ? $countLookup[$groupName] : 0
        ];
    }
    
    // Sort by count (descending)
    usort($groupData, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    // Get total count with RBAC filtering
    $totalSql = "SELECT COUNT(*) as total FROM student_artists WHERE status = 'active'" . $campusFilter;
    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute($campusParams);
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
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
    
    // Get search and campus parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filterCampus = isset($_GET['campus']) ? trim($_GET['campus']) : '';
    
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
    
    // Build WHERE clause for search
    $whereConditions = ["status = 'active'"];
    $params = [];
    
    // Apply campus filter
    // Use filter campus from dropdown if provided, otherwise apply user's campus for non-admin users
    if ($filterCampus) {
        $whereConditions[] = "campus = ?";
        $params[] = $filterCampus;
    } elseif (!$canViewAll && $user_campus) {
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
    
    // Get actual student counts for each group with search filter
    $sql = "
        SELECT 
            CASE 
                WHEN cultural_group IS NULL OR cultural_group = '' THEN 'Not Assigned'
                ELSE cultural_group
            END as group_name,
            COUNT(*) as count
        FROM student_artists 
        WHERE $whereClause
        GROUP BY CASE 
            WHEN cultural_group IS NULL OR cultural_group = '' THEN 'Not Assigned'
            ELSE cultural_group
        END
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
    
    // Get total count with search filter
    $totalSql = "SELECT COUNT(*) as total FROM student_artists WHERE $whereClause";
    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute($params);
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Prepare response
    $response = [
        'success' => true,
        'groupDistribution' => $groupData,
        'totalStudents' => $total,
        'searchApplied' => !empty($search)
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
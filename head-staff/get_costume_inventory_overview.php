<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// RBAC: Determine access level
$user_role = $_SESSION['user_role'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_campus = $_SESSION['user_campus'] ?? null;

$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
$isCentralHead = in_array($user_email, $centralHeadEmails);
$canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && in_array($user_role, ['head', 'staff'])));

// Normalize campus names to full format
$campus_name_map = [
    'Malvar' => 'JPLPC Malvar',
    'Nasugbu' => 'ARASOF Nasugbu',
    'Pablo Borbon' => 'Pablo Borbon',
    'Alangilan' => 'Alangilan',
    'Lipa' => 'Lipa',
    'JPLPC Malvar' => 'JPLPC Malvar',
    'ARASOF Nasugbu' => 'ARASOF Nasugbu'
];
$normalized_campus = $campus_name_map[$user_campus] ?? $user_campus;

// Build campus filter - always exclude archived items
$campusFilter = "WHERE status != 'archived'";
$campusParams = [];

if (!$canViewAll && $normalized_campus) {
    // For Malvar and Nasugbu, check both short and full names
    if ($normalized_campus === 'JPLPC Malvar') {
        $campusFilter .= ' AND (campus = ? OR campus = ?)';
        $campusParams = ['JPLPC Malvar', 'Malvar'];
    } elseif ($normalized_campus === 'ARASOF Nasugbu') {
        $campusFilter .= ' AND (campus = ? OR campus = ?)';
        $campusParams = ['ARASOF Nasugbu', 'Nasugbu'];
    } else {
        $campusFilter .= ' AND campus = ?';
        $campusParams = [$normalized_campus];
    }
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Drop old costume_inventory table if it exists to avoid confusion
    try {
        $pdo->exec("DROP TABLE IF EXISTS costume_inventory");
    } catch (Exception $e) {
        // Ignore errors
    }
    
    // Check if inventory table exists
    $tableExists = false;
    try {
        $checkTable = $pdo->query("SELECT 1 FROM inventory LIMIT 1");
        $tableExists = true;
    } catch (Exception $e) {
        // Table doesn't exist, return empty data
        $tableExists = false;
    }
    
    if ($tableExists) {
        // Get inventory statistics using the real inventory table with campus filtering
        // Note: Available items must have quantity > 0 AND status = 'available'
        // Items with quantity = 0 are unavailable regardless of their status
        $statsSql = "
            SELECT 
                COUNT(*) as total_items,
                COUNT(CASE WHEN status = 'available' AND quantity > 0 THEN 1 END) as available_items,
                COUNT(CASE WHEN quantity = 0 OR status = 'unavailable' THEN 1 END) as unavailable_items,
                COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_items,
                COUNT(CASE WHEN condition_status IN ('poor', 'damaged') THEN 1 END) as damaged_items,
                COUNT(CASE WHEN condition_status = 'excellent' THEN 1 END) as excellent_items
            FROM inventory
            " . $campusFilter . "
        ";
        $statsStmt = $pdo->prepare($statsSql);
        $statsStmt->execute($campusParams);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Borrowed items count: number of inventory rows currently marked as 'borrowed'.
        // This aligns with dashboard expectation (distinct items showing Borrowed status).
        $borrowedFilter = "WHERE status = 'borrowed'";
        $borrowedParams = [];
        if (!$canViewAll && $normalized_campus) {
            if ($normalized_campus === 'JPLPC Malvar') {
                $borrowedFilter .= " AND (campus = ? OR campus = ?)";
                $borrowedParams = ['JPLPC Malvar', 'Malvar'];
            } elseif ($normalized_campus === 'ARASOF Nasugbu') {
                $borrowedFilter .= " AND (campus = ? OR campus = ?)";
                $borrowedParams = ['ARASOF Nasugbu', 'Nasugbu'];
            } else {
                $borrowedFilter .= " AND campus = ?";
                $borrowedParams = [$normalized_campus];
            }
        }
        $borrowedSql = "SELECT COUNT(*) AS borrowed_count FROM inventory $borrowedFilter";
        $borrowedStmt = $pdo->prepare($borrowedSql);
        $borrowedStmt->execute($borrowedParams);
        $stats['borrowed_items'] = (int)$borrowedStmt->fetch(PDO::FETCH_ASSOC)['borrowed_count'];
        
        // Debug: Log the actual query results
        error_log("Costume Inventory Debug - Campus: " . ($normalized_campus ?? 'all') . " - Stats: " . json_encode($stats));
        
        // Get items by category with campus filtering (exclude archived)
        // Only count items with quantity > 0 as available
        $categoryFilter = $campusFilter . " AND category IS NOT NULL";
        $groupSql = "
            SELECT 
                category,
                COUNT(*) as item_count,
                COUNT(CASE WHEN status = 'available' AND quantity > 0 THEN 1 END) as available_count
            FROM inventory
            " . $categoryFilter . "
            GROUP BY category
            ORDER BY item_count DESC
            LIMIT 6
        ";
        $groupStmt = $pdo->prepare($groupSql);
        $groupStmt->execute($campusParams);
        $groupBreakdown = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get items needing attention (poor condition or maintenance) with campus filtering (exclude archived)
        $attentionFilter = $campusFilter . " AND (condition_status IN ('poor', 'damaged') OR status = 'maintenance')";
        $attentionSql = "
            SELECT 
                item_name,
                category,
                condition_status,
                status,
                updated_at as last_used
            FROM inventory
            " . $attentionFilter . "
            ORDER BY 
                CASE condition_status 
                    WHEN 'damaged' THEN 1 
                    WHEN 'poor' THEN 2 
                    ELSE 3 
                END,
                updated_at DESC
            LIMIT 5
        ";
        $attentionStmt = $pdo->prepare($attentionSql);
        $attentionStmt->execute($campusParams);
        $itemsNeedingAttention = $attentionStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate utilization rate (borrowed / total available)
        $availableForBorrow = $stats['available_items'] + $stats['borrowed_items'];
        $utilizationRate = $availableForBorrow > 0 ? round(($stats['borrowed_items'] / $availableForBorrow) * 100) : 0;
    } else {
        // No inventory table exists, return empty data
        $stats = [
            'total_items' => 0,
            'available_items' => 0,
            'borrowed_items' => 0,
            'maintenance_items' => 0,
            'damaged_items' => 0,
            'excellent_items' => 0
        ];
        $groupBreakdown = [];
        $itemsNeedingAttention = [];
        $utilizationRate = 0;
    }
    
    $response = [
        'success' => true,
        'statistics' => array_merge($stats, ['utilization_rate' => $utilizationRate]),
        'group_breakdown' => $groupBreakdown,
        'items_needing_attention' => $itemsNeedingAttention
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch costume inventory data: ' . $e->getMessage()
    ]);
}
?>
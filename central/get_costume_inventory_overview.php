<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central'])) {
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
$canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && $user_role === 'central'));

// Build campus filter
$campusFilter = '';
$campusParams = [];
if (!$canViewAll && $user_campus) {
    $campusFilter = ' WHERE campus = ?';
    $campusParams[] = $user_campus;
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
        $statsSql = "
            SELECT 
                COUNT(*) as total_items,
                COUNT(CASE WHEN status = 'available' THEN 1 END) as available_items,
                COUNT(CASE WHEN status = 'borrowed' THEN 1 END) as borrowed_items,
                COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_items,
                COUNT(CASE WHEN condition_status IN ('poor', 'damaged') THEN 1 END) as damaged_items,
                COUNT(CASE WHEN condition_status = 'excellent' THEN 1 END) as excellent_items
            FROM inventory
            " . $campusFilter . "
        ";
        $statsStmt = $pdo->prepare($statsSql);
        $statsStmt->execute($campusParams);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Log the actual query results
        error_log("Costume Inventory Debug - Stats: " . json_encode($stats));
        
        // Get items by category (since there's no cultural_group in inventory table) with campus filtering
        $categoryWhere = $campusFilter ? str_replace('WHERE', 'WHERE category IS NOT NULL AND', $campusFilter) : 'WHERE category IS NOT NULL';
        $groupSql = "
            SELECT 
                category,
                COUNT(*) as item_count,
                COUNT(CASE WHEN status = 'available' THEN 1 END) as available_count
            FROM inventory
            " . $categoryWhere . "
            GROUP BY category
            ORDER BY item_count DESC
            LIMIT 6
        ";
        $groupStmt = $pdo->prepare($groupSql);
        $groupStmt->execute($campusParams);
        $groupBreakdown = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get items needing attention (poor condition or maintenance) with campus filtering
        $attentionWhere = $campusFilter ? $campusFilter . ' AND (condition_status IN (\'poor\', \'damaged\') OR status = \'maintenance\')' : 'WHERE condition_status IN (\'poor\', \'damaged\') OR status = \'maintenance\'';
        $attentionSql = "
            SELECT 
                item_name,
                category,
                condition_status,
                status,
                updated_at as last_used
            FROM inventory
            " . $attentionWhere . "
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
<?php
// Prevent any output before JSON
ob_start();

// Set content type to JSON and turn off error display
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

session_start();

// Check if user is logged in and is admin (head or staff)
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// RBAC: Determine access level
$user_role = $_SESSION['user_role'];
$user_email = $_SESSION['user_email'];
$user_campus_raw = $_SESSION['user_campus'] ?? null;

// Campus name normalization
$campus_name_map = [
    'Malvar' => 'JPLPC Malvar',
    'Nasugbu' => 'ARASOF Nasugbu',
    'Pablo Borbon' => 'Pablo Borbon',
    'Lemery' => 'Lemery',
    'Rosario' => 'Rosario',
    'Balayan' => 'Balayan',
    'Mabini' => 'Mabini',
    'San Juan' => 'San Juan',
    'Lobo' => 'Lobo'
];
$user_campus = $campus_name_map[$user_campus_raw] ?? $user_campus_raw;

$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
$isCentralHead = in_array($user_email, $centralHeadEmails);
$canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && in_array($user_role, ['head', 'staff'])));

// Build campus filter with support for both short and full campus names
$campusFilter = '';
$campusParams = [];
if (!$canViewAll && $user_campus) {
    if ($user_campus === 'JPLPC Malvar') {
        $campusFilter = ' AND (campus = ? OR campus = ?)';
        $campusParams = ['JPLPC Malvar', 'Malvar'];
    } elseif ($user_campus === 'ARASOF Nasugbu') {
        $campusFilter = ' AND (campus = ? OR campus = ?)';
        $campusParams = ['ARASOF Nasugbu', 'Nasugbu'];
    } else {
        $campusFilter = ' AND campus = ?';
        $campusParams = [$user_campus];
    }
}

// Database connection using centralized config
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

try {
    // Check if inventory table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'inventory'")->rowCount() > 0;
    
    if (!$tableExists) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'costumes' => [],
            'equipment' => []
        ]);
        exit;
    }

    // Fetch costumes
    $costumesSQL = "SELECT * FROM inventory WHERE category = 'costume' AND (status IS NULL OR status != 'archived')" . $campusFilter . " ORDER BY created_at DESC";
    $costumesStmt = $pdo->prepare($costumesSQL);
    $costumesStmt->execute($campusParams);
    $costumes = $costumesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each costume, fetch all active borrowers and calculate quantities
    foreach ($costumes as &$costume) {
        // Get total borrowed quantity for this item
        $borrowedQtySQL = "
            SELECT COALESCE(SUM(
                CASE 
                    WHEN JSON_VALID(br.approved_items) THEN
                        JSON_EXTRACT(br.approved_items, CONCAT('$[', json_idx.idx, '].quantity'))
                    ELSE 1
                END
            ), 0) as total_borrowed
            FROM borrowing_requests br
            CROSS JOIN (
                SELECT 0 as idx UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
            ) json_idx
            WHERE br.status = 'approved'
            AND br.current_status IN ('active', 'pending_return')
            AND JSON_VALID(br.approved_items)
            AND JSON_EXTRACT(br.approved_items, CONCAT('$[', json_idx.idx, '].id')) = ?
        ";
        $borrowedQtyStmt = $pdo->prepare($borrowedQtySQL);
        $borrowedQtyStmt->execute([$costume['id']]);
        $borrowedQty = $borrowedQtyStmt->fetchColumn() ?: 0;
        
        // Calculate total quantity - if there are borrowed items but quantity seems low, 
        // assume total = current quantity + borrowed (fixing corrupted data)
        $current_qty = intval($costume['quantity']);
        if ($borrowedQty > 0 && $current_qty < $borrowedQty) {
            // Data seems corrupted, fix it
            $total_qty = $current_qty + $borrowedQty;
        } else {
            // If there are borrowed items and current qty seems reasonable, 
            // the total should be current + borrowed
            $total_qty = $borrowedQty > 0 ? $current_qty + $borrowedQty : $current_qty;
        }
        
        // Set calculated quantities
        $costume['total_quantity'] = $total_qty;
        $costume['available_quantity'] = $current_qty; // Current DB value is actually available
        $costume['borrowed_quantity'] = $borrowedQty;
        
        $borrowersSQL = "
            SELECT 
                br.student_name,
                br.dates_of_use as borrow_date,
                br.due_date as return_due_date,
                br.student_email,
                br.approved_items
            FROM borrowing_requests br
            WHERE br.status = 'approved'
                AND br.current_status IN ('active', 'pending_return')
                AND JSON_SEARCH(br.approved_items, 'one', ?, NULL, '$[*].id') IS NOT NULL
            ORDER BY br.dates_of_use DESC
        ";
        $borrowersStmt = $pdo->prepare($borrowersSQL);
        $borrowersStmt->execute([$costume['id']]);
        $costume['borrowers'] = $borrowersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For backward compatibility, set single borrower fields if there's at least one
        if (!empty($costume['borrowers'])) {
            $costume['borrower_name'] = $costume['borrowers'][0]['student_name'];
            $costume['borrow_date'] = $costume['borrowers'][0]['borrow_date'];
            $costume['return_due_date'] = $costume['borrowers'][0]['return_due_date'];
            $costume['borrower_email'] = $costume['borrowers'][0]['student_email'];
        }
    }

    // Fetch equipment with campus filtering
    $equipmentSQL = "SELECT * FROM inventory WHERE category = 'equipment' AND (status IS NULL OR status != 'archived')" . $campusFilter . " ORDER BY created_at DESC";
    $equipmentStmt = $pdo->prepare($equipmentSQL);
    $equipmentStmt->execute($campusParams);
    $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each equipment, fetch all active borrowers and calculate quantities
    foreach ($equipment as &$item) {
        // Get total borrowed quantity for this item
        $borrowedQtySQL = "
            SELECT COALESCE(SUM(
                CASE 
                    WHEN JSON_VALID(br.approved_items) THEN
                        JSON_EXTRACT(br.approved_items, CONCAT('$[', json_idx.idx, '].quantity'))
                    ELSE 1
                END
            ), 0) as total_borrowed
            FROM borrowing_requests br
            CROSS JOIN (
                SELECT 0 as idx UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
            ) json_idx
            WHERE br.status = 'approved'
            AND br.current_status IN ('active', 'pending_return')
            AND JSON_VALID(br.approved_items)
            AND JSON_EXTRACT(br.approved_items, CONCAT('$[', json_idx.idx, '].id')) = ?
        ";
        $borrowedQtyStmt = $pdo->prepare($borrowedQtySQL);
        $borrowedQtyStmt->execute([$item['id']]);
        $borrowedQty = $borrowedQtyStmt->fetchColumn() ?: 0;
        
        // Calculate total quantity - if there are borrowed items but quantity seems low, 
        // assume total = current quantity + borrowed (fixing corrupted data)
        $current_qty = intval($item['quantity']);
        if ($borrowedQty > 0 && $current_qty < $borrowedQty) {
            // Data seems corrupted, fix it
            $total_qty = $current_qty + $borrowedQty;
        } else {
            // If there are borrowed items and current qty seems reasonable, 
            // the total should be current + borrowed
            $total_qty = $borrowedQty > 0 ? $current_qty + $borrowedQty : $current_qty;
        }
        
        // Set calculated quantities
        $item['total_quantity'] = $total_qty;
        $item['available_quantity'] = $current_qty; // Current DB value is actually available
        $item['borrowed_quantity'] = $borrowedQty;
        
        $borrowersSQL = "
            SELECT 
                br.student_name,
                br.dates_of_use as borrow_date,
                br.due_date as return_due_date,
                br.student_email,
                br.approved_items
            FROM borrowing_requests br
            WHERE br.status = 'approved'
                AND br.current_status IN ('active', 'pending_return')
                AND JSON_SEARCH(br.approved_items, 'one', ?, NULL, '$[*].id') IS NOT NULL
            ORDER BY br.dates_of_use DESC
        ";
        $borrowersStmt = $pdo->prepare($borrowersSQL);
        $borrowersStmt->execute([$item['id']]);
        $item['borrowers'] = $borrowersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For backward compatibility, set single borrower fields if there's at least one
        if (!empty($item['borrowers'])) {
            $item['borrower_name'] = $item['borrowers'][0]['student_name'];
            $item['borrow_date'] = $item['borrowers'][0]['borrow_date'];
            $item['return_due_date'] = $item['borrowers'][0]['return_due_date'];
            $item['borrower_email'] = $item['borrowers'][0]['student_email'];
        }
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'costumes' => $costumes,
        'equipment' => $equipment
    ]);

} catch(PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage()]);
}
?>
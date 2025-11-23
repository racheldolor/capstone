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
$user_campus = $_SESSION['user_campus'] ?? null;

$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
$isCentralHead = in_array($user_email, $centralHeadEmails);
$canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && in_array($user_role, ['head', 'staff'])));

// Build campus filter
$campusFilter = '';
$campusParams = [];
if (!$canViewAll && $user_campus) {
    $campusFilter = ' AND campus = ?';
    $campusParams[] = $user_campus;
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
    $costumesSQL = "SELECT * FROM inventory WHERE category = 'costume'" . $campusFilter . " ORDER BY created_at DESC";
    $costumesStmt = $pdo->prepare($costumesSQL);
    $costumesStmt->execute($campusParams);
    $costumes = $costumesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each costume, fetch all active borrowers
    foreach ($costumes as &$costume) {
        $borrowersSQL = "
            SELECT 
                br.student_name,
                br.start_date as borrow_date,
                br.end_date as return_due_date,
                br.student_email,
                br.student_course
            FROM borrowing_requests br
            WHERE br.item_id = ? 
                AND br.status IN ('approved', 'borrowed') 
                AND br.current_status IN ('active', 'pending_return')
            ORDER BY br.start_date DESC
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
            $costume['borrower_course'] = $costume['borrowers'][0]['student_course'];
        }
    }

    // Fetch equipment with campus filtering
    $equipmentSQL = "SELECT * FROM inventory WHERE category = 'equipment'" . $campusFilter . " ORDER BY created_at DESC";
    $equipmentStmt = $pdo->prepare($equipmentSQL);
    $equipmentStmt->execute($campusParams);
    $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each equipment, fetch all active borrowers
    foreach ($equipment as &$item) {
        $borrowersSQL = "
            SELECT 
                br.student_name,
                br.start_date as borrow_date,
                br.end_date as return_due_date,
                br.student_email,
                br.student_course
            FROM borrowing_requests br
            WHERE br.item_id = ? 
                AND br.status IN ('approved', 'borrowed') 
                AND br.current_status IN ('active', 'pending_return')
            ORDER BY br.start_date DESC
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
            $item['borrower_course'] = $item['borrowers'][0]['student_course'];
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
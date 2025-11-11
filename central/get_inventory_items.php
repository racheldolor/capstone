<?php
// Prevent any output before JSON
ob_start();

// Set content type to JSON and turn off error display
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

session_start();

// Check if user is logged in and is admin (head or staff)
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'capstone_culture_arts';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
    $costumesSQL = "SELECT * FROM inventory WHERE category = 'costume' ORDER BY created_at DESC";
    $costumesStmt = $pdo->query($costumesSQL);
    $costumes = $costumesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch equipment
    $equipmentSQL = "SELECT * FROM inventory WHERE category = 'equipment' ORDER BY created_at DESC";
    $equipmentStmt = $pdo->query($equipmentSQL);
    $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);

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
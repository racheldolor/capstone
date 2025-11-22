<?php
// Prevent any output before JSON
ob_start();

// Set content type to JSON and turn off error display
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// Start session
session_start();

// Check if user is logged in and is admin (head or staff)
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get user's campus for the inventory item
$user_campus = $_SESSION['user_campus'] ?? null;

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

// Get JSON input
$json_input = file_get_contents('php://input');
$input = json_decode($json_input, true);

if (!$input) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid input data. Received: ' . $json_input]);
    exit;
}

// Validate required fields
$required_fields = ['name', 'category', 'condition'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
        exit;
    }
}

// Validate category values
if (!in_array($input['category'], ['costume', 'equipment'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid category. Must be costume or equipment.']);
    exit;
}

// Validate condition values
if (!in_array($input['condition'], ['good', 'worn-out', 'bad'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid condition. Must be good, worn-out, or bad.']);
    exit;
}

try {
    // Create inventory table if it doesn't exist
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_name VARCHAR(255) NOT NULL,
            category ENUM('costume', 'equipment') NOT NULL,
            condition_status ENUM('good', 'worn-out', 'bad') NOT NULL,
            status ENUM('available', 'borrowed') DEFAULT 'available',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ";
    $pdo->exec($createTableSQL);

    // Insert the new item with campus
    $sql = "INSERT INTO inventory (item_name, category, condition_status, status, description, campus) 
            VALUES (:item_name, :category, :condition_status, :status, :description, :campus)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':item_name' => trim($input['name']),
        ':category' => $input['category'],
        ':condition_status' => $input['condition'],
        ':status' => 'available', // Always set to available as requested
        ':description' => trim($input['description'] ?? ''),
        ':campus' => $user_campus
    ]);

    if ($result) {
        ob_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Item added successfully!',
            'item_id' => $pdo->lastInsertId()
        ]);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to insert item']);
    }

} catch(PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage()]);
}
?>
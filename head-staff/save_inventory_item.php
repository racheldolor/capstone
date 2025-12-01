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

// Database connection using centralized config
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
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
$required_fields = ['name', 'category', 'condition', 'quantity'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        ob_clean();
        echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
        exit;
    }
}

// Validate quantity
$quantity = intval($input['quantity']);
if ($quantity < 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
    exit;
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
            quantity INT DEFAULT 0,
            condition_status ENUM('good', 'worn-out', 'bad') NOT NULL,
            status ENUM('available', 'borrowed', 'maintenance', 'archived') DEFAULT 'available',
            description TEXT,
            campus VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ";
    $pdo->exec($createTableSQL);

    // Check if quantity column exists, if not add it
    $columns = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'quantity'")->rowCount();
    if ($columns == 0) {
        $pdo->exec("ALTER TABLE inventory ADD COLUMN quantity INT DEFAULT 0 AFTER category");
    }

    // Check if campus column exists, if not add it
    $columns = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'campus'")->rowCount();
    if ($columns == 0) {
        $pdo->exec("ALTER TABLE inventory ADD COLUMN campus VARCHAR(100) AFTER description");
    }

    // Auto-set status based on quantity
    // Only set to borrowed if qty is 0 AND there's an actual borrow record
    // Otherwise, unavailable for qty=0, or available for qty>0
    $auto_status = $quantity <= 0 ? 'unavailable' : 'available';

    // Check if editing existing item
    if (isset($input['id']) && !empty($input['id'])) {
        // Update existing item
        // Get current quantities to preserve borrowed amount
        $current_stmt = $pdo->prepare("SELECT quantity, available_quantity FROM inventory WHERE id = :id");
        $current_stmt->execute([':id' => $input['id']]);
        $current = $current_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            $old_total = intval($current['quantity']);
            $old_available = intval($current['available_quantity']);
            $currently_borrowed = $old_total - $old_available; // Calculate current borrowed amount
            
            // New available = new total - currently borrowed (preserve borrowed amount)
            $new_available = $quantity - $currently_borrowed;
            $new_available = max(0, $new_available); // Ensure it's not negative
        } else {
            $new_available = $quantity; // If no record found, set available = total
        }
        
        $sql = "UPDATE inventory 
                SET item_name = :item_name, 
                    category = :category, 
                    quantity = :quantity,
                    available_quantity = :available_quantity,
                    condition_status = :condition_status, 
                    status = :status, 
                    description = :description,
                    updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':item_name' => trim($input['name']),
            ':category' => $input['category'],
            ':quantity' => $quantity,
            ':available_quantity' => $new_available,
            ':condition_status' => $input['condition'],
            ':status' => $auto_status,
            ':description' => trim($input['description'] ?? ''),
            ':id' => $input['id']
        ]);

        if ($result) {
            ob_clean();
            echo json_encode([
                'success' => true, 
                'message' => 'Item updated successfully!',
                'item_id' => $input['id']
            ]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to update item']);
        }
    } else {
        // Insert new item with campus
        $sql = "INSERT INTO inventory (item_name, category, quantity, condition_status, status, description, campus) 
                VALUES (:item_name, :category, :quantity, :condition_status, :status, :description, :campus)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':item_name' => trim($input['name']),
            ':category' => $input['category'],
            ':quantity' => $quantity,
            ':condition_status' => $input['condition'],
            ':status' => $auto_status,
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
    }

} catch(PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage()]);
}
?>
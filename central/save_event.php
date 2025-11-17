<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get form data
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $campus = $_POST['municipality'] ?? '';
    $category = $_POST['category'] ?? '';
    $cultural_groups = isset($_POST['cultural_groups']) && is_array($_POST['cultural_groups']) ? $_POST['cultural_groups'] : [];
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($start_date) || empty($end_date) || empty($location) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }
    
    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date']);
        exit;
    }
    
    // Create events table if it doesn't exist
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            location VARCHAR(255) NOT NULL,
            campus VARCHAR(100),
            category VARCHAR(100) NOT NULL,
            cultural_groups TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status ENUM('active', 'cancelled', 'completed') DEFAULT 'active'
        )
    ";
    $pdo->exec($createTableSQL);
    
    // Convert cultural groups array to JSON
    // Ensure we always have a valid array (empty or with values)
    $cultural_groups_json = json_encode(is_array($cultural_groups) ? $cultural_groups : []);
    
    // Insert event into database
    $stmt = $pdo->prepare("
        INSERT INTO events (title, description, start_date, end_date, location, venue, category, cultural_groups, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', ?)
    ");
    
    $result = $stmt->execute([
        $title,
        $description,
        $start_date,
        $end_date,
        $location,
        $location, // venue is same as location
        $category,
        $cultural_groups_json,
        $_SESSION['user_id'] ?? null
    ]);
    
    if ($result) {
        $event_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Event saved successfully!',
            'event_id' => $event_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save event']);
    }
    
} catch (Exception $e) {
    error_log("Error saving event: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving the event']);
}
?>
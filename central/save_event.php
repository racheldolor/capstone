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
    $cultural_groups = $_POST['cultural_groups'] ?? [];
    
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
    
    // Create announcements table if it doesn't exist
    $createAnnouncementsTableSQL = "
        CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            event_id INT,
            target_groups TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
        )
    ";
    $pdo->exec($createAnnouncementsTableSQL);
    
    // Convert cultural groups array to JSON
    $cultural_groups_json = json_encode($cultural_groups);
    
    // Insert event into database
    $stmt = $pdo->prepare("
        INSERT INTO events (title, description, start_date, end_date, location, campus, category, cultural_groups, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $title,
        $description,
        $start_date,
        $end_date,
        $location,
        $campus,
        $category,
        $cultural_groups_json,
        $_SESSION['user_id'] ?? null
    ]);
    
    if ($result) {
        $event_id = $pdo->lastInsertId();
        
        // Create announcement for students in concerned cultural groups
        if (!empty($cultural_groups)) {
            $announcement_title = "New Event: " . $title;
            $announcement_message = "A new event has been scheduled for your cultural group.\n\n";
            $announcement_message .= "Event: " . $title . "\n";
            $announcement_message .= "Date: " . date('F j, Y', strtotime($start_date));
            if ($start_date !== $end_date) {
                $announcement_message .= " - " . date('F j, Y', strtotime($end_date));
            }
            $announcement_message .= "\nLocation: " . $location . "\n";
            $announcement_message .= "Category: " . $category . "\n\n";
            $announcement_message .= "Please check the Events & Trainings section for full details.";
            
            $announcementStmt = $pdo->prepare("
                INSERT INTO announcements (title, message, event_id, target_groups) 
                VALUES (?, ?, ?, ?)
            ");
            
            $announcementStmt->execute([
                $announcement_title,
                $announcement_message,
                $event_id,
                $cultural_groups_json
            ]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Event saved successfully and notifications sent to concerned students!',
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
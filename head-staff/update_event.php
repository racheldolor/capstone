<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
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
    
    // Debug: Log all POST data
    error_log("UPDATE EVENT - POST DATA: " . print_r($_POST, true));
    error_log("UPDATE EVENT - FILES DATA: " . print_r($_FILES, true));
    
    // Get form data
    $event_id = $_POST['event_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $venue = $_POST['municipality'] ?? ''; // municipality field maps to venue column
    $category = $_POST['category'] ?? '';
    $cultural_groups = isset($_POST['cultural_groups']) && is_array($_POST['cultural_groups']) ? $_POST['cultural_groups'] : [];
    
    // Debug: Log extracted values
    error_log("UPDATE EVENT - Extracted values:");
    error_log("  Event ID: $event_id");
    error_log("  Title: $title");
    error_log("  Description: $description");
    error_log("  Start Date: $start_date");
    error_log("  End Date: $end_date");
    error_log("  Location: $location");
    error_log("  Venue: $venue");
    error_log("  Category: $category");
    error_log("  Cultural Groups: " . print_r($cultural_groups, true));
    
    // Validate required fields
    if (empty($event_id) || empty($title) || empty($description) || empty($start_date) || empty($end_date) || empty($location) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }
    
    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date']);
        exit;
    }
    
    // Check if event exists and user has permission to edit
    $stmt = $pdo->prepare("SELECT id, event_poster FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $existing_event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing_event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    // Handle file upload if present
    $event_poster = $existing_event['event_poster']; // Keep existing image by default
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/events/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_filename = 'event_' . time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            // Delete old image if it exists
            if ($event_poster && file_exists('../' . $event_poster)) {
                unlink('../' . $event_poster);
            }
            $event_poster = 'assets/events/' . $new_filename;
        }
    }
    
    // Convert cultural groups array to JSON
    // Ensure we always have a valid array (empty or with values)
    $cultural_groups_json = json_encode(is_array($cultural_groups) ? $cultural_groups : []);
    
    // Debug: Log final values before update
    error_log("UPDATE EVENT - About to execute UPDATE with:");
    error_log("  Event ID: $event_id");
    error_log("  Title: $title");
    error_log("  Venue: $venue");
    error_log("  Category: $category");
    error_log("  Cultural Groups JSON: $cultural_groups_json");
    error_log("  Event Poster: $event_poster");
    
    // Update event in database
    $stmt = $pdo->prepare("
        UPDATE events 
        SET title = ?, description = ?, start_date = ?, end_date = ?, location = ?, 
            venue = ?, category = ?, cultural_groups = ?, event_poster = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $title,
        $description,
        $start_date,
        $end_date,
        $location,
        $venue,
        $category,
        $cultural_groups_json,
        $event_poster,
        $event_id
    ]);
    
    $rowsAffected = $stmt->rowCount();
    error_log("UPDATE EVENT - Rows affected: $rowsAffected");
    
    if ($result) {
        // Log successful update
        error_log("Event updated successfully: ID=$event_id, Title=$title, Rows=$rowsAffected");
        echo json_encode([
            'success' => true, 
            'message' => 'Event updated successfully!',
            'event_id' => $event_id,
            'rows_affected' => $rowsAffected
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Failed to update event: " . print_r($errorInfo, true));
        echo json_encode(['success' => false, 'message' => 'Failed to update event: ' . $errorInfo[2]]);
    }
    
} catch (Exception $e) {
    error_log("Error updating event: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the event']);
}
?>
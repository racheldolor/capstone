<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// RBAC: Determine access level
$user_role = $_SESSION['user_role'];
$user_email = $_SESSION['user_email'];
$user_campus = $_SESSION['user_campus'] ?? null;

$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
$isCentralHead = in_array($user_email, $centralHeadEmails);
$canManage = !$isCentralHead;

// Check write permission
if (!$canManage) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to create events']);
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
    
    // Auto-set campus for non-Pablo Borbon users (staff, head, central)
    // Only Pablo Borbon users and admin can choose campus
    $canChooseCampus = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && in_array($user_role, ['head', 'staff', 'central'])));
    if (!$canChooseCampus && $user_campus) {
        $campus = $user_campus;
    }
    
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
    
    // Convert cultural groups array to JSON
    // Ensure we always have a valid array (empty or with values)
    $cultural_groups_json = json_encode(is_array($cultural_groups) ? $cultural_groups : []);
    
    // Insert event into database with campus field
    $stmt = $pdo->prepare("
        INSERT INTO events (title, description, start_date, end_date, location, venue, category, cultural_groups, status, created_by, campus) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', ?, ?)
    ");
    
    $result = $stmt->execute([
        $title,
        $description,
        $start_date,
        $end_date,
        $location,
        $location, // venue is the specific location
        $category,
        $cultural_groups_json,
        $_SESSION['user_id'] ?? null,
        $campus // campus field for filtering
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
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
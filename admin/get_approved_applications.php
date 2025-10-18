<?php
session_start();
require_once '../student/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    // First, get all approved applications
    $stmt = $conn->prepare("
        SELECT a.id, a.sr_code, a.first_name, a.middle_name, a.last_name, a.full_name, a.email, a.application_status, 
               a.approved_at, a.reviewed_by,
               IFNULL(CONCAT(reviewer.first_name, ' ', reviewer.last_name), 'Unknown') as reviewer_name
        FROM applications a
        LEFT JOIN users reviewer ON a.reviewed_by = reviewer.id
        WHERE a.application_status = 'approved'
        ORDER BY a.approved_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $allApproved = $result->fetch_all(MYSQLI_ASSOC);
    
    $applications = [];
    
    // Filter out applications that already have accounts
    foreach ($allApproved as $app) {
        $email = $app['email'];
        
        // Check if user exists in users table
        $userCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $userCheck->bind_param("s", $email);
        $userCheck->execute();
        $userExists = $userCheck->get_result()->num_rows > 0;
        $userCheck->close();
        
        // Check if user exists in student_artists table
        $artistCheck = $conn->prepare("SELECT id FROM student_artists WHERE email = ?");
        $artistCheck->bind_param("s", $email);
        $artistCheck->execute();
        $artistExists = $artistCheck->get_result()->num_rows > 0;
        $artistCheck->close();
        
        // If no account exists in either table, add to results
        if (!$userExists && !$artistExists) {
            $applications[] = $app;
        }
    }

    // Format the applications data
    foreach ($applications as &$app) {
        // Handle null approved_at
        if ($app['approved_at']) {
            $app['approved_at'] = date('M d, Y H:i', strtotime($app['approved_at']));
        } else {
            $app['approved_at'] = 'Recently approved';
        }
        
        // Use the separate name fields from database if available, fallback to parsing full_name
        if (empty($app['first_name']) && !empty($app['full_name'])) {
            // Fallback: Split full name into first and last name for old records
            $nameParts = explode(' ', trim($app['full_name']));
            if (count($nameParts) >= 2) {
                $app['last_name'] = array_pop($nameParts); // Get last word as last name
                $app['first_name'] = implode(' ', $nameParts); // Everything else as first name
            } else {
                $app['first_name'] = $app['full_name'];
                $app['last_name'] = '';
            }
        }
        // Ensure we have values even if fields are null
        $app['first_name'] = $app['first_name'] ?: '';
        $app['middle_name'] = $app['middle_name'] ?: '';
        $app['last_name'] = $app['last_name'] ?: '';
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'applications' => $applications,
        'count' => count($applications)
    ]);

} catch (Exception $e) {
    error_log("Error fetching approved applications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch approved applications: ' . $e->getMessage()]);
}

// Close connection
$conn->close();
?>
<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
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
$canManage = !$isCentralHead;

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

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get all pending applications from the applications table
    $stmt = $pdo->prepare("
        SELECT 
            id,
            performance_type,
            consent,
            first_name,
            middle_name,
            last_name,
            full_name,
            address,
            present_address,
            date_of_birth,
            age,
            gender,
            place_of_birth,
            email,
            contact_number,
            father_name,
            mother_name,
            guardian,
            guardian_contact,
            campus,
            college,
            sr_code,
            year_level,
            program,
            first_semester_units,
            second_semester_units,
            profile_photo,
            signature_image,
            certification,
            signature_date,
            submitted_at,
            application_status
        FROM applications 
        WHERE (application_status = 'pending' OR application_status IS NULL)" . $campusFilter . "
        ORDER BY submitted_at DESC
    ");
    
    $stmt->execute($campusParams);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for better display
    foreach ($applications as &$app) {
        // Format date
        if ($app['date_of_birth']) {
            $app['date_of_birth'] = date('M d, Y', strtotime($app['date_of_birth']));
        }
        
        // Format created_at
        if ($app['submitted_at']) {
            $app['submitted_at'] = date('M d, Y g:i A', strtotime($app['submitted_at']));
        }
        
        // Handle null values
        $app['present_address'] = $app['present_address'] ?: $app['address'];
        $app['guardian'] = $app['guardian'] ?: 'N/A';
        $app['guardian_contact'] = $app['guardian_contact'] ?: 'N/A';
        $app['place_of_birth'] = $app['place_of_birth'] ?: 'N/A';
        $app['father_name'] = $app['father_name'] ?: 'N/A';
        $app['mother_name'] = $app['mother_name'] ?: 'N/A';
        
        // Capitalize gender
        $app['gender'] = ucfirst($app['gender']);
        
        // Format performance type
        $app['performance_type'] = $app['performance_type'] ?: 'Not specified';
    }
    
    echo json_encode([
        'success' => true,
        'applications' => $applications,
        'count' => count($applications)
    ]);

} catch (Exception $e) {
    error_log("Error fetching applications: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch applications: ' . $e->getMessage()
    ]);
}
?>
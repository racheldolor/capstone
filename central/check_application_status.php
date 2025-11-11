<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Enable CORS for AJAX requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $pdo = getDBConnection();
    
    // Get SR Code from request
    $input = json_decode(file_get_contents('php://input'), true);
    $srCode = $input['sr_code'] ?? $_POST['sr_code'] ?? $_GET['sr_code'] ?? null;
    
    if (!$srCode) {
        throw new Exception('SR Code is required');
    }
    
    // Clean the SR Code input
    $srCode = trim($srCode);
    
    // Get application status by SR Code
    $stmt = $pdo->prepare("
        SELECT 
            id,
            first_name,
            middle_name,
            last_name,
            full_name,
            sr_code,
            email,
            campus,
            college,
            program,
            year_level,
            performance_type,
            application_status,
            submitted_at,
            approved_at,
            reviewed_by
        FROM applications 
        WHERE sr_code = ?
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    
    $stmt->execute([$srCode]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        echo json_encode([
            'success' => false,
            'message' => 'No application found with this SR Code',
            'status' => 'not_found'
        ]);
        exit();
    }
    
    // Get reviewer information if available
    $reviewerName = null;
    if ($application['reviewed_by']) {
        $stmt = $pdo->prepare("
            SELECT CONCAT(first_name, ' ', last_name) as reviewer_name 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$application['reviewed_by']]);
        $reviewer = $stmt->fetch(PDO::FETCH_ASSOC);
        $reviewerName = $reviewer['reviewer_name'] ?? 'Staff Member';
    }
    
    // Format dates
    $submittedDate = $application['submitted_at'] ? 
        date('M d, Y g:i A', strtotime($application['submitted_at'])) : null;
    
    $reviewDate = $application['approved_at'] ? 
        date('M d, Y g:i A', strtotime($application['approved_at'])) : null;
    
    // Determine status message and next steps
    $statusMessage = '';
    $nextSteps = '';
    $statusColor = '';
    
    switch ($application['application_status']) {
        case 'pending':
            $statusMessage = 'Your application is currently under review';
            $nextSteps = 'Please wait for the review process to complete. You will be contacted within 3-5 business days.';
            $statusColor = '#ffc107'; // Yellow
            break;
            
        case 'approved':
            $statusMessage = 'Congratulations! Your application has been approved';
            $nextSteps = 'You will receive an email with your login credentials and next steps to join the cultural group.';
            $statusColor = '#28a745'; // Green
            break;
            
        case 'rejected':
            $statusMessage = 'Your application was not approved at this time';
            $nextSteps = 'You may reapply in the next semester. Please contact the Culture and Arts office for feedback.';
            $statusColor = '#dc3545'; // Red
            break;
            
        case 'under_review':
            $statusMessage = 'Your application is currently being reviewed';
            $nextSteps = 'The review process is in progress. Please check back in 1-2 business days.';
            $statusColor = '#17a2b8'; // Blue
            break;
            
        case 'requires_documents':
            $statusMessage = 'Additional documents are required for your application';
            $nextSteps = 'Please submit the required documents to complete your application review.';
            $statusColor = '#fd7e14'; // Orange
            break;
            
        default:
            $statusMessage = 'Application status is being processed';
            $nextSteps = 'Please contact the Culture and Arts office for more information.';
            $statusColor = '#6c757d'; // Gray
    }
    
    echo json_encode([
        'success' => true,
        'application' => [
            'id' => $application['id'],
            'first_name' => $application['first_name'],
            'middle_name' => $application['middle_name'],
            'last_name' => $application['last_name'],
            'full_name' => $application['full_name'],
            'sr_code' => $application['sr_code'],
            'email' => $application['email'],
            'campus' => $application['campus'],
            'college' => $application['college'],
            'program' => $application['program'],
            'year_level' => $application['year_level'],
            'performance_type' => $application['performance_type'],
            'status' => $application['application_status'],
            'status_message' => $statusMessage,
            'next_steps' => $nextSteps,
            'status_color' => $statusColor,
            'submitted_date' => $submittedDate,
            'review_date' => $reviewDate,
            'reviewed_by' => $reviewerName
        ]
    ]);

} catch (Exception $e) {
    error_log("Error checking application status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error checking application status: ' . $e->getMessage(),
        'status' => 'error'
    ]);
}
?>
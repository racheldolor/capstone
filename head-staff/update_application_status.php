<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['application_id']) || !isset($input['status'])) {
        throw new Exception('Missing required parameters');
    }
    
    $applicationId = (int)$input['application_id'];
    $status = $input['status'];
    
    // Validate status
    if (!in_array($status, ['approved', 'rejected'])) {
        throw new Exception('Invalid status value');
    }
    
    $pdo = getDBConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Get application details
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            throw new Exception('Application not found');
        }
        
        // Update application status
        $stmt = $pdo->prepare("
            UPDATE applications 
            SET application_status = ?, 
                updated_at = CURRENT_TIMESTAMP,
                reviewed_by = ?,
                approved_at = ?
            WHERE id = ?
        ");
        
        $reviewedBy = $_SESSION['user_id'] ?? null;
        $approvedAt = ($status === 'approved') ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$status, $reviewedBy, $approvedAt, $applicationId]);

        // NOTE: User account creation is now handled by admin through notifications
        // This allows admin to have control over when user accounts are created
        /*
        // If approved, create user account
        if ($status === 'approved') {
            // Check if user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$application['email']]);
            $existingUser = $stmt->fetch();
            
            if (!$existingUser) {
                // Create user account
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        email, 
                        password, 
                        first_name, 
                        last_name, 
                        role, 
                        status,
                        sr_code,
                        campus,
                        college,
                        program,
                        year_level,
                        contact_number,
                        created_at
                    ) VALUES (?, ?, ?, ?, 'student', 'active', ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                
                // Generate default password (student can change later)
                $defaultPassword = password_hash('student123', PASSWORD_DEFAULT);
                
                // Split full name into first and last name
                $nameParts = explode(' ', $application['full_name'], 2);
                $firstName = $nameParts[0];
                $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
                
                $stmt->execute([
                    $application['email'],
                    $defaultPassword,
                    $firstName,
                    $lastName,
                    $application['sr_code'],
                    $application['campus'],
                    $application['college'],
                    $application['program'],
                    $application['year_level'],
                    $application['contact_number']
                ]);
                
                $userId = $pdo->lastInsertId();
            }
        }
        */
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, target_id, details, created_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $action = $status === 'approved' ? 'Application Approved' : 'Application Rejected';
        $details = "Application for " . $application['full_name'] . " has been " . $status;
        
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $applicationId,
            $details
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Application {$status} successfully",
            'status' => $status
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error updating application status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update application status: ' . $e->getMessage()
    ]);
}
?>
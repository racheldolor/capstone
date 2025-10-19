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

        // If approved, add to student_artists table
        if ($status === 'approved') {
            // Check if student already exists in student_artists table
            $stmt = $pdo->prepare("SELECT id FROM student_artists WHERE sr_code = ? OR email = ?");
            $stmt->execute([$application['sr_code'], $application['email']]);
            $existingStudent = $stmt->fetch();
            
            if (!$existingStudent) {
                // Add to student_artists table
                $stmt = $pdo->prepare("
                    INSERT INTO student_artists (
                        sr_code,
                        first_name,
                        middle_name,
                        last_name,
                        email,
                        password,
                        campus,
                        college,
                        program,
                        year_level,
                        contact_number,
                        status,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)
                ");
                
                // Generate default password (student can change later)
                $defaultPassword = password_hash('student123', PASSWORD_DEFAULT);
                
                // Split full name into first, middle, and last name
                $nameParts = explode(' ', trim($application['full_name']));
                $firstName = $application['first_name'] ?? $nameParts[0];
                $lastName = $application['last_name'] ?? end($nameParts);
                $middleName = null;
                
                if (count($nameParts) > 2) {
                    // Extract middle name(s)
                    $middleParts = array_slice($nameParts, 1, -1);
                    $middleName = implode(' ', $middleParts);
                } elseif (count($nameParts) === 2 && !$application['middle_name']) {
                    // If only 2 parts and no middle name field, leave middleName as null
                } else {
                    $middleName = $application['middle_name'];
                }
                
                $stmt->execute([
                    $application['sr_code'],
                    $firstName,
                    $middleName,
                    $lastName,
                    $application['email'],
                    $defaultPassword,
                    $application['campus'],
                    $application['college'],
                    $application['program'],
                    $application['year_level'],
                    $application['contact_number']
                ]);
                
                $studentId = $pdo->lastInsertId();
            }
        }
        
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
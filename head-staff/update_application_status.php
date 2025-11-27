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
$user_campus = $_SESSION['user_campus'] ?? null;

$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
$isCentralHead = in_array($user_email, $centralHeadEmails);
$canManage = !$isCentralHead;

// Check write permission
if (!$canManage) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to update applications']);
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
        
        // Verify campus access for campus-specific users
        if ($user_role !== 'admin' && $user_role !== 'central') {
            if ($user_campus && $application['campus'] !== $user_campus) {
                throw new Exception('You do not have permission to update this application');
            }
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

        // For both approved and rejected applications, create student account
        if ($status === 'approved' || $status === 'rejected') {
            // Check if student already exists in student_artists table
            $stmt = $pdo->prepare("SELECT id FROM student_artists WHERE sr_code = ? OR email = ?");
            $stmt->execute([$application['sr_code'], $application['email']]);
            $existingStudent = $stmt->fetch();
            
            // Check if student was previously deleted by admin
            $stmt = $pdo->prepare("SELECT id FROM deleted_students WHERE sr_code = ? OR email = ?");
            $stmt->execute([$application['sr_code'], $application['email']]);
            $deletedStudent = $stmt->fetch();
            
            if (!$existingStudent && !$deletedStudent) {
                // Determine the student status
                $studentStatus = 'suspended'; // Both approved and rejected students start as suspended
                $isArchived = ($status === 'rejected') ? 1 : 0; // Only rejected students are archived
                
                // First, try to insert with is_archived column
                try {
                    // Add to student_artists table with all available data from application
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
                            address,
                            present_address,
                            date_of_birth,
                            age,
                            gender,
                            place_of_birth,
                            father_name,
                            mother_name,
                            guardian,
                            guardian_contact,
                            performance_type,
                            first_semester_units,
                            second_semester_units,
                            status,
                            is_archived,
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ");
                    
                    // Generate default password using SR code (student can change later)
                    $defaultPassword = password_hash($application['sr_code'], PASSWORD_DEFAULT);
                    
                    // Use existing name fields if available, otherwise split full_name
                    $firstName = $application['first_name'];
                    $middleName = $application['middle_name'];
                    $lastName = $application['last_name'];
                    
                    // If name fields are empty, try to split full_name
                    if (empty($firstName) && !empty($application['full_name'])) {
                        $nameParts = explode(' ', trim($application['full_name']));
                        $firstName = $nameParts[0];
                        $lastName = count($nameParts) > 1 ? end($nameParts) : '';
                        
                        if (count($nameParts) > 2) {
                            // Extract middle name(s)
                            $middleParts = array_slice($nameParts, 1, -1);
                            $middleName = implode(' ', $middleParts);
                        }
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
                        $application['contact_number'],
                        $application['address'],
                        $application['present_address'],
                        $application['date_of_birth'],
                        $application['age'],
                        $application['gender'],
                        $application['place_of_birth'],
                        $application['father_name'],
                        $application['mother_name'],
                        $application['guardian'],
                        $application['guardian_contact'],
                        $application['performance_type'],
                        $application['first_semester_units'],
                        $application['second_semester_units'],
                        $studentStatus,
                        $isArchived
                    ]);
                    
                } catch (Exception $e) {
                    // If is_archived column doesn't exist, try without it
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
                            address,
                            present_address,
                            date_of_birth,
                            age,
                            gender,
                            place_of_birth,
                            father_name,
                            mother_name,
                            guardian,
                            guardian_contact,
                            performance_type,
                            first_semester_units,
                            second_semester_units,
                            status,
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ");
                    
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
                        $application['contact_number'],
                        $application['address'],
                        $application['present_address'],
                        $application['date_of_birth'],
                        $application['age'],
                        $application['gender'],
                        $application['place_of_birth'],
                        $application['father_name'],
                        $application['mother_name'],
                        $application['guardian'],
                        $application['guardian_contact'],
                        $application['performance_type'],
                        $application['first_semester_units'],
                        $application['second_semester_units'],
                        $studentStatus
                    ]);
                }
                
                $studentId = $pdo->lastInsertId();
            }
        }
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, target_id, details, created_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $action = $status === 'approved' ? 'Application Approved' : 'Application Rejected';
        $fullName = trim(($application['first_name'] ?? '') . ' ' . ($application['middle_name'] ?? '') . ' ' . ($application['last_name'] ?? ''));
        if (empty($fullName)) {
            $fullName = $application['full_name'] ?? 'Unknown Student';
        }
        $details = "Application for " . $fullName . " has been " . $status;
        
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $applicationId,
            $details
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        $message = "Application {$status} successfully";
        if ($status === 'approved') {
            $message .= " and student account created with suspended status";
        } elseif ($status === 'rejected') {
            $message .= " and student account created as suspended and archived";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
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
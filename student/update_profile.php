<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$pdo = getDBConnection();
$student_id = $_SESSION['user_id'];
$user_table = $_SESSION['user_table'] ?? 'users';

try {
    // Get posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit();
    }
    
    // Update student_artists table
    $stmt = $pdo->prepare("
        UPDATE student_artists 
        SET 
            first_name = ?,
            middle_name = ?,
            last_name = ?,
            date_of_birth = ?,
            age = ?,
            gender = ?,
            place_of_birth = ?,
            email = ?,
            contact_number = ?,
            address = ?,
            present_address = ?,
            father_name = ?,
            mother_name = ?,
            guardian = ?,
            guardian_contact = ?,
            campus = ?,
            college = ?,
            sr_code = ?,
            year_level = ?,
            program = ?,
            first_semester_units = ?,
            second_semester_units = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $data['first_name'],
        $data['middle_name'],
        $data['last_name'],
        $data['date_of_birth'],
        $data['age'],
        $data['gender'],
        $data['place_of_birth'],
        $data['email'],
        $data['contact_number'],
        $data['address'],
        $data['present_address'],
        $data['father_name'],
        $data['mother_name'],
        $data['guardian'],
        $data['guardian_contact'],
        $data['campus'],
        $data['college'],
        $data['sr_code'],
        $data['year_level'],
        $data['program'],
        $data['first_semester_units'],
        $data['second_semester_units'],
        $student_id
    ]);
    
    if ($result) {
        // Process pending participation changes
        if (isset($data['pendingChanges']) && isset($data['pendingChanges']['participation'])) {
            $participation = $data['pendingChanges']['participation'];
            
            // Add new participation records
            if (!empty($participation['toAdd'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO student_participation_records 
                    (student_id, participation_date, event_name, participation_level, rank_award)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($participation['toAdd'] as $record) {
                    $stmt->execute([
                        $student_id,
                        $record['date'],
                        $record['event_name'],
                        $record['level'],
                        $record['rank_award']
                    ]);
                }
            }
            
            // Delete participation records
            if (!empty($participation['toDelete'])) {
                $stmt = $pdo->prepare("DELETE FROM student_participation_records WHERE id = ? AND student_id = ?");
                foreach ($participation['toDelete'] as $id) {
                    $stmt->execute([$id, $student_id]);
                }
            }
        }
        
        // Process pending affiliation changes
        if (isset($data['pendingChanges']) && isset($data['pendingChanges']['affiliation'])) {
            $affiliation = $data['pendingChanges']['affiliation'];
            
            // Add new affiliation records
            if (!empty($affiliation['toAdd'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO student_affiliation_records 
                    (student_id, position, organization, years_active)
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($affiliation['toAdd'] as $record) {
                    $stmt->execute([
                        $student_id,
                        $record['position'],
                        $record['organization'],
                        $record['years_active']
                    ]);
                }
            }
            
            // Delete affiliation records
            if (!empty($affiliation['toDelete'])) {
                $stmt = $pdo->prepare("DELETE FROM student_affiliation_records WHERE id = ? AND student_id = ?");
                foreach ($affiliation['toDelete'] as $id) {
                    $stmt->execute([$id, $student_id]);
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update profile'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error updating profile: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Error updating profile: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]
    ]);
}

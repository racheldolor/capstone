<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$pdo = getDBConnection();
$student_id = $_SESSION['user_id'];

try {
    // Get student email and SR code
    $user_table = $_SESSION['user_table'] ?? 'users';
    $user_email = null;
    $sr_code = null;
    
    if ($user_table === 'student_artists') {
        $stmt = $pdo->prepare("SELECT email, sr_code FROM student_artists WHERE id = ?");
        $stmt->execute([$student_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_email = $user_data['email'] ?? null;
        $sr_code = $user_data['sr_code'] ?? null;
    } else {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_email = $user_data['email'] ?? null;
        
        // Try to get SR code from student_artists table
        if ($user_email) {
            $stmt = $pdo->prepare("SELECT sr_code FROM student_artists WHERE email = ?");
            $stmt->execute([$user_email]);
            $sr_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $sr_code = $sr_data['sr_code'] ?? null;
        }
    }
    
    if (!$user_email) {
        echo json_encode(['success' => false, 'message' => 'User email not found', 'debug' => ['user_table' => $user_table, 'student_id' => $student_id]]);
        exit();
    }
    
    // Get student data from student_artists table
    $application = null;
    
    // Try by student ID first if from student_artists table
    if ($user_table === 'student_artists') {
        $stmt = $pdo->prepare("SELECT * FROM student_artists WHERE id = ?");
        $stmt->execute([$student_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Try by email if not found
    if (!$application) {
        $stmt = $pdo->prepare("
            SELECT * FROM student_artists 
            WHERE TRIM(LOWER(email)) = TRIM(LOWER(?))
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_email]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Try by SR code if still not found
    if (!$application && $sr_code) {
        $stmt = $pdo->prepare("
            SELECT * FROM student_artists 
            WHERE TRIM(sr_code) = TRIM(?)
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$sr_code]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$application) {
        echo json_encode([
            'success' => false, 
            'message' => 'No profile found. Please contact the administrator.', 
            'debug' => [
                'searching_for' => ['email' => $user_email, 'sr_code' => $sr_code, 'student_id' => $student_id],
                'user_table' => $user_table
            ]
        ]);
        exit();
    }
    
    // Get participation records from student_participation_records table
    $participations = [];
    try {
        $stmt = $pdo->prepare("
            SELECT id, participation_date, event_name as activity_title, participation_level as level, rank_award
            FROM student_participation_records 
            WHERE student_id = ?
            ORDER BY participation_date DESC, id DESC
        ");
        $stmt->execute([$application['id']]);
        $participations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Participation records error: " . $e->getMessage());
    }
    
    // Get affiliation records from student_affiliation_records table
    $affiliations = [];
    try {
        $stmt = $pdo->prepare("
            SELECT id, position as affiliation_position, organization as organization_name, years_active as year
            FROM student_affiliation_records 
            WHERE student_id = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$application['id']]);
        $affiliations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Affiliation records error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'application' => $application,
        'participations' => $participations,
        'affiliations' => $affiliations
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching application data: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching application data: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>

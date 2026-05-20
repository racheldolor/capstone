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

// Resolve the actual student_artists id for profile updates
$profile_id = null;
$student_email = null;
$student_sr_code = null;

if ($user_table === 'student_artists') {
    $stmt = $pdo->prepare("SELECT id, email, sr_code FROM student_artists WHERE id = ?");
    $stmt->execute([$student_id]);
    $student_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student_row) {
        $profile_id = (int)$student_row['id'];
        $student_email = $student_row['email'] ?? null;
        $student_sr_code = $student_row['sr_code'] ?? null;
    }
} else {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $student_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $student_email = $student_row['email'] ?? null;

    if ($student_email) {
        $stmt = $pdo->prepare("SELECT id, sr_code FROM student_artists WHERE TRIM(LOWER(email)) = TRIM(LOWER(?)) ORDER BY id DESC LIMIT 1");
        $stmt->execute([$student_email]);
        $artist_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($artist_row) {
            $profile_id = (int)$artist_row['id'];
            $student_sr_code = $artist_row['sr_code'] ?? null;
        }
    }
}

if (!$profile_id && $student_sr_code) {
    $stmt = $pdo->prepare("SELECT id FROM student_artists WHERE TRIM(sr_code) = TRIM(?) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$student_sr_code]);
    $artist_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($artist_row) {
        $profile_id = (int)$artist_row['id'];
    }
}

try {
    // Get posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit();
    }
    
    if (!$profile_id) {
        echo json_encode(['success' => false, 'message' => 'Student profile not found']);
        exit();
    }

    $pdo->beginTransaction();
    $insertErrors = [];
    $changeCounts = [
        'participation_added' => 0,
        'participation_deleted' => 0,
        'competition_added' => 0,
        'competition_deleted' => 0,
        'affiliation_added' => 0,
        'affiliation_deleted' => 0
    ];

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
            instructors = ?,
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
        $data['instructors'],
        $profile_id
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
                
                foreach ($participation['toAdd'] as $index => $record) {
                    $ok = $stmt->execute([
                        $profile_id,
                        $record['date'],
                        $record['event_name'],
                        $record['level'],
                        $record['rank_award']
                    ]);
                    if (!$ok) {
                        $insertErrors[] = [
                            'type' => 'participation_add',
                            'index' => $index,
                            'error' => $stmt->errorInfo()
                        ];
                    } else {
                        $changeCounts['participation_added']++;
                    }
                }
            }
            
            // Delete participation records
            if (!empty($participation['toDelete'])) {
                $stmt = $pdo->prepare("DELETE FROM student_participation_records WHERE id = ? AND student_id = ?");
                foreach ($participation['toDelete'] as $index => $id) {
                    $ok = $stmt->execute([$id, $profile_id]);
                    if (!$ok) {
                        $insertErrors[] = [
                            'type' => 'participation_delete',
                            'index' => $index,
                            'error' => $stmt->errorInfo()
                        ];
                    } else {
                        $changeCounts['participation_deleted']++;
                    }
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
                
                foreach ($affiliation['toAdd'] as $index => $record) {
                    $ok = $stmt->execute([
                        $profile_id,
                        $record['position'],
                        $record['organization'],
                        $record['years_active']
                    ]);
                    if (!$ok) {
                        $insertErrors[] = [
                            'type' => 'affiliation_add',
                            'index' => $index,
                            'error' => $stmt->errorInfo()
                        ];
                    } else {
                        $changeCounts['affiliation_added']++;
                    }
                }
            }
            
            // Delete affiliation records
            if (!empty($affiliation['toDelete'])) {
                $stmt = $pdo->prepare("DELETE FROM student_affiliation_records WHERE id = ? AND student_id = ?");
                foreach ($affiliation['toDelete'] as $index => $id) {
                    $ok = $stmt->execute([$id, $profile_id]);
                    if (!$ok) {
                        $insertErrors[] = [
                            'type' => 'affiliation_delete',
                            'index' => $index,
                            'error' => $stmt->errorInfo()
                        ];
                    } else {
                        $changeCounts['affiliation_deleted']++;
                    }
                }
            }
        }

        // Process pending competition changes
        if (isset($data['pendingChanges']) && isset($data['pendingChanges']['competition'])) {
            $competition = $data['pendingChanges']['competition'];

            // Add new competition records
            if (!empty($competition['toAdd'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO student_competition_records
                    (student_id, competition_date, event_name, competition_level, rank_award)
                    VALUES (?, ?, ?, ?, ?)
                ");

                foreach ($competition['toAdd'] as $index => $record) {
                    $ok = $stmt->execute([
                        $profile_id,
                        $record['date'],
                        $record['event_name'],
                        $record['level'],
                        $record['rank_award']
                    ]);
                    if (!$ok) {
                        $insertErrors[] = [
                            'type' => 'competition_add',
                            'index' => $index,
                            'error' => $stmt->errorInfo()
                        ];
                    } else {
                        $changeCounts['competition_added']++;
                    }
                }
            }

            // Delete competition records
            if (!empty($competition['toDelete'])) {
                $stmt = $pdo->prepare("DELETE FROM student_competition_records WHERE id = ? AND student_id = ?");
                foreach ($competition['toDelete'] as $index => $id) {
                    $ok = $stmt->execute([$id, $profile_id]);
                    if (!$ok) {
                        $insertErrors[] = [
                            'type' => 'competition_delete',
                            'index' => $index,
                            'error' => $stmt->errorInfo()
                        ];
                    } else {
                        $changeCounts['competition_deleted']++;
                    }
                }
            }
        }

        if (!empty($insertErrors)) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Profile update failed while saving records',
                'debug' => [
                    'profile_id' => $profile_id,
                    'errors' => $insertErrors
                ]
            ]);
            exit();
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'debug' => [
                'profile_id' => $profile_id,
                'changes' => $changeCounts,
                'pending_received' => [
                    'participation' => isset($data['pendingChanges']['participation']) ? count($data['pendingChanges']['participation']['toAdd'] ?? []) + count($data['pendingChanges']['participation']['toDelete'] ?? []) : 0,
                    'competition' => isset($data['pendingChanges']['competition']) ? count($data['pendingChanges']['competition']['toAdd'] ?? []) + count($data['pendingChanges']['competition']['toDelete'] ?? []) : 0,
                    'affiliation' => isset($data['pendingChanges']['affiliation']) ? count($data['pendingChanges']['affiliation']['toAdd'] ?? []) + count($data['pendingChanges']['affiliation']['toDelete'] ?? []) : 0
                ]
            ]
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update profile'
        ]);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
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

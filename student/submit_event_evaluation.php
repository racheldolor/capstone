<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Authentication check - only students can submit evaluations
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit();
    }
    
    // Validate required fields
    $requiredFields = ['event_id', 'q1_rating', 'q2_rating', 'q3_rating', 'q4_rating', 'q5_rating', 'q6_rating', 'q7_rating', 'q8_rating', 'q9_rating', 'q10_rating', 'q11_rating', 'q12_rating', 'q13_opinion', 'q14_suggestions', 'q15_comments'];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === null || trim($input[$field]) === '') {
            echo json_encode(['success' => false, 'message' => "Missing required field: " . $field]);
            exit();
        }
    }
    
    // Validate rating values (1-5) for questions 1-12 only
    $ratingFields = ['q1_rating', 'q2_rating', 'q3_rating', 'q4_rating', 'q5_rating', 'q6_rating', 'q7_rating', 'q8_rating', 'q9_rating', 'q10_rating', 'q11_rating', 'q12_rating'];
    
    foreach ($ratingFields as $field) {
        $rating = intval($input[$field]);
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => "Invalid rating value for $field. Must be between 1 and 5."]);
            exit();
        }
    }
    
    $eventId = intval($input['event_id']);
    $studentId = intval($_SESSION['user_id']);
    $q13_opinion = trim($input['q13_opinion']);
    $q14_suggestions = trim($input['q14_suggestions']);
    $q15_comments = trim($input['q15_comments']);
    
    // Validate event exists and student participated
    $eventCheckQuery = "SELECT e.id, e.title, e.status, e.end_date 
                        FROM events e 
                        JOIN event_participants ep ON e.id = ep.event_id 
                        WHERE e.id = ? AND ep.student_id = ?";
    
    $stmt = $pdo->prepare($eventCheckQuery);
    $stmt->execute([$eventId, $studentId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found or you did not participate in this event']);
        exit();
    }
    
    // Check if event is completed (past end date)
    $currentDate = date('Y-m-d');
    if ($event['end_date'] >= $currentDate) {
        echo json_encode(['success' => false, 'message' => 'Can only evaluate events that have ended']);
        exit();
    }
    
    // Check if evaluation already exists
    $existingEvalQuery = "SELECT id FROM event_evaluations WHERE event_id = ? AND student_id = ?";
    $stmt = $pdo->prepare($existingEvalQuery);
    $stmt->execute([$eventId, $studentId]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already submitted an evaluation for this event']);
        exit();
    }
    
    // Insert evaluation
    $insertQuery = "INSERT INTO event_evaluations 
                    (event_id, student_id, q1_rating, q2_rating, q3_rating, q4_rating, q5_rating, 
                     q6_rating, q7_rating, q8_rating, q9_rating, q10_rating, q11_rating, q12_rating, 
                     q13_opinion, q14_suggestions, q15_comments, submitted_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($insertQuery);
    $success = $stmt->execute([
        $eventId,
        $studentId,
        intval($input['q1_rating']),
        intval($input['q2_rating']),
        intval($input['q3_rating']),
        intval($input['q4_rating']),
        intval($input['q5_rating']),
        intval($input['q6_rating']),
        intval($input['q7_rating']),
        intval($input['q8_rating']),
        intval($input['q9_rating']),
        intval($input['q10_rating']),
        intval($input['q11_rating']),
        intval($input['q12_rating']),
        $q13_opinion,
        $q14_suggestions,
        $q15_comments
    ]);
    
    if ($success) {
        $evaluationId = $pdo->lastInsertId();
        
        // Log the successful submission
        error_log("Event evaluation submitted - ID: $evaluationId, Event: {$event['title']}, Student: $studentId");
        
        echo json_encode([
            'success' => true,
            'message' => 'Evaluation submitted successfully',
            'evaluation_id' => $evaluationId,
            'event_title' => $event['title']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit evaluation']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in submit_event_evaluation.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in submit_event_evaluation.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your evaluation']);
}
?>
<?php
session_start();
require_once '../config/database.php';

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Check if user is logged in and has proper role
    if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $pdo = getDBConnection();

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $request_id = $input['request_id'] ?? null;
    $action = $input['action'] ?? null;

    if (!$request_id || $action !== 'confirm_return') {
        throw new Exception('Invalid request parameters');
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get return request details
        $stmt = $pdo->prepare("
            SELECT rr.*, br.student_id 
            FROM return_requests rr
            JOIN borrowing_requests br ON rr.borrowing_request_id = br.id
            WHERE rr.id = ? AND rr.status = 'pending'
        ");
        $stmt->execute([$request_id]);
        $return_request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$return_request) {
            throw new Exception('Return request not found or already processed');
        }

        // Update return request status to completed
        $stmt = $pdo->prepare("
            UPDATE return_requests 
            SET status = 'completed', 
                completed_at = NOW(), 
                completed_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $request_id]);

        // Check if item was returned with damage
        $has_damage = strpos($return_request['condition_notes'], 'with damage') !== false;
        
        if ($has_damage) {
            // Don't update main inventory - item should already be in repair_items
            // Just mark as processed
            error_log("Item {$return_request['item_name']} returned with damage - routed to repairs");
        } else {
            // Update inventory item status back to available for good condition items
            $stmt = $pdo->prepare("
                UPDATE inventory 
                SET available_quantity = available_quantity + ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$return_request['quantity_returned'], $return_request['item_id']]);
        }

        // Check if all items for this borrowing request have been returned
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as pending_count
            FROM return_requests 
            WHERE borrowing_request_id = ? AND status = 'pending'
        ");
        $stmt->execute([$return_request['borrowing_request_id']]);
        $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

        // If no pending returns left, update borrowing request status
        if ($pending_count == 0) {
            $stmt = $pdo->prepare("
                UPDATE borrowing_requests 
                SET current_status = 'returned', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$return_request['borrowing_request_id']]);
        }

        // Commit transaction first
        $pdo->commit();

        // Log the admin action (after successful commit)
        try {
            logAdminAction(
                $pdo, 
                $_SESSION['user_id'], 
                "Confirmed return", 
                $return_request['student_id'], 
                "Confirmed return of item: {$return_request['item_name']} (ID: {$return_request['item_id']})"
            );
        } catch (Exception $log_error) {
            // Don't fail the whole operation if logging fails
            error_log("Admin action logging failed: " . $log_error->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Return confirmed successfully. Item is now available for borrowing.',
            'data' => [
                'request_id' => $request_id,
                'item_id' => $return_request['item_id'],
                'item_name' => $return_request['item_name']
            ]
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
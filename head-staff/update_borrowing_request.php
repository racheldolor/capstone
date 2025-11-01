<?php
session_start();
require_once '../config/database.php';

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Start output buffering to prevent any stray output
ob_start();

try {
    // Check if user is logged in and has proper role
    if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $pdo = getDBConnection();

    // Ensure the table has the necessary columns for review tracking
    try {
        $pdo->exec("ALTER TABLE borrowing_requests ADD COLUMN IF NOT EXISTS review_notes TEXT");
        $pdo->exec("ALTER TABLE borrowing_requests ADD COLUMN IF NOT EXISTS reviewed_by INT");
        $pdo->exec("ALTER TABLE borrowing_requests ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP NULL");
        $pdo->exec("ALTER TABLE borrowing_requests ADD COLUMN IF NOT EXISTS approved_items JSON NULL");
    } catch (Exception $e) {
        // Columns might already exist, ignore error
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $request_id = $input['request_id'] ?? null;
    $action = $input['action'] ?? $input['status'] ?? null; // Support both 'action' and 'status' for backward compatibility
    $notes = $input['notes'] ?? '';
    $selected_items = $input['selected_items'] ?? [];

    // Validate input
    if (!$request_id || !$action) {
        throw new Exception('Request ID and action are required');
    }

    if (!in_array($action, ['approve', 'approved', 'reject', 'rejected'])) {
        throw new Exception('Invalid action. Must be approve/approved or reject/rejected');
    }

    // Normalize action to status
    $status = ($action === 'approve' || $action === 'approved') ? 'approved' : 'rejected';

    // Start transaction
    $pdo->beginTransaction();

    try {
        // If approving with selected items, validate and update inventory
        if ($status === 'approved' && !empty($selected_items)) {
            // Validate selected items exist and are available
            $item_ids = array_map(function($item) { return $item['id']; }, $selected_items);
            $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
            
            $stmt = $pdo->prepare("
                SELECT id, name, status 
                FROM inventory 
                WHERE id IN ($placeholders) AND status = 'available'
            ");
            $stmt->execute($item_ids);
            $available_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($available_items) !== count($selected_items)) {
                throw new Exception('Some selected items are no longer available');
            }

            // Update inventory status to 'borrowed'
            $stmt = $pdo->prepare("
                UPDATE inventory 
                SET status = 'borrowed', updated_at = CURRENT_TIMESTAMP 
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($item_ids);

            // Store approved items as JSON in the request
            $approved_items_json = json_encode($selected_items);
        } else {
            $approved_items_json = null;
        }

        // Update the borrowing request
        $stmt = $pdo->prepare("
            UPDATE borrowing_requests 
            SET status = ?, 
                updated_at = CURRENT_TIMESTAMP,
                review_notes = ?,
                reviewed_by = ?,
                reviewed_at = CURRENT_TIMESTAMP,
                approved_items = ?,
                due_date = ?,
                current_status = ?
            WHERE id = ?
        ");

        // Set due date to estimated return date if approving
        $due_date = null;
        $current_status = null;
        if ($status === 'approved') {
            // Get the estimated return date from the request
            $temp_stmt = $pdo->prepare("SELECT estimated_return_date FROM borrowing_requests WHERE id = ?");
            $temp_stmt->execute([$request_id]);
            $temp_request = $temp_stmt->fetch(PDO::FETCH_ASSOC);
            
            $due_date = $temp_request['estimated_return_date'] ?? null;
            $current_status = 'active';
        }

        $result = $stmt->execute([
            $status,
            $notes,
            $_SESSION['user_id'],
            $approved_items_json,
            $due_date,
            $current_status,
            $request_id
        ]);

        if (!$result) {
            throw new Exception('Failed to update request status');
        }

        // Get the updated request details for logging
        $stmt = $pdo->prepare("SELECT * FROM borrowing_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception('Request not found');
        }

        // Create a detailed log message
        $log_message = "Requester: {$request['requester_name']}, Status: $status";
        if ($status === 'approved' && !empty($selected_items)) {
            $item_names = array_map(function($item) { return $item['name']; }, $selected_items);
            $log_message .= ", Approved items: " . implode(', ', $item_names);
        }

        // Log the admin action (after successful commit)
        try {
            logAdminAction(
                $pdo, 
                $_SESSION['user_id'], 
                "Borrowing request $status", 
                $request['student_id'], 
                $log_message
            );
        } catch (Exception $log_error) {
            // Don't fail the whole operation if logging fails
            error_log("Admin action logging failed: " . $log_error->getMessage());
        }

        // Commit transaction
        $pdo->commit();

        // Clean any output before sending JSON
        ob_clean();

        // Send success response
        echo json_encode([
            'success' => true,
            'message' => "Request has been $status successfully" . 
                        ($status === 'approved' && !empty($selected_items) ? 
                        " with " . count($selected_items) . " items" : ""),
            'data' => [
                'request_id' => $request_id,
                'status' => $status,
                'approved_items_count' => $status === 'approved' ? count($selected_items) : 0
            ]
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Clean any output before sending error JSON
    ob_clean();
    
    // Log the error for debugging
    error_log("Error in update_borrowing_request.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>
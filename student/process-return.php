<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php?section=costume-borrowing");
    exit();
}

try {
    $pdo = getDBConnection();
    $student_id = $_SESSION['user_id'];
    
    // Get the selected items and condition from POST data
    $selected_items = $_POST['selected_items'] ?? [];
    $condition = $_POST['condition'] ?? [];
    $multiple_requests = $_POST['multiple_requests'] ?? false;
    
    if (empty($selected_items)) {
        $_SESSION['error_message'] = 'Missing required information. Please select at least one item to return.';
        header("Location: return-form.php");
        exit();
    }
    
    if (empty($condition)) {
        $_SESSION['error_message'] = 'Missing required information. Please select the condition of returned items.';
        header("Location: return-form.php");
        exit();
    }
    
    // Process condition notes
    $condition_notes = [];
    $has_damage = false;
    if (in_array('good_condition', $condition)) {
        $condition_notes[] = 'Properties returned in good condition';
    }
    if (in_array('with_damage', $condition)) {
        $condition_notes[] = 'Properties returned with damage';
        $has_damage = true;
    }
    $condition_text = implode('; ', $condition_notes);
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Prepare the insert statement for return requests (include quantity_returned)
        $stmt = $pdo->prepare("
            INSERT INTO return_requests (borrowing_request_id, student_id, item_id, item_name, quantity_returned, condition_notes, status, requested_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $processed_borrowing_requests = [];
        
        // Process each selected item
        foreach ($selected_items as $item_json) {
            $item_data = json_decode($item_json, true);
            if (!$item_data || !isset($item_data['id'], $item_data['name'], $item_data['borrowing_request_id'])) {
                continue; // Skip invalid items
            }
            
            // Validate that this borrowing request belongs to the student
            if (!in_array($item_data['borrowing_request_id'], $processed_borrowing_requests)) {
                $validate_stmt = $pdo->prepare("
                    SELECT id FROM borrowing_requests 
                    WHERE id = ? AND student_id = ? AND status = 'approved' AND current_status = 'active'
                ");
                $validate_stmt->execute([$item_data['borrowing_request_id'], $student_id]);
                
                if (!$validate_stmt->fetch()) {
                    throw new Exception("Invalid borrowing request: " . $item_data['borrowing_request_id']);
                }
                
                $processed_borrowing_requests[] = $item_data['borrowing_request_id'];
            }
            
            // Determine quantity to return (default to 1)
            $qty = 1;
            if (isset($item_data['quantity'])) {
                $qty = max(1, intval($item_data['quantity']));
            } elseif (isset($item_data['qty'])) {
                $qty = max(1, intval($item_data['qty']));
            }

            // Insert return request for this item, including quantity
            $stmt->execute([
                $item_data['borrowing_request_id'],
                $student_id,
                $item_data['id'],
                $item_data['name'],
                $qty,
                $condition_text
            ]);
            
            // If item is returned with damage, create repair entry
            if ($has_damage) {
                $repair_stmt = $pdo->prepare("
                    INSERT INTO repair_items (item_id, item_name, category, quantity, repair_status, date_reported, reported_by_student_id, notes)
                    VALUES (?, ?, (SELECT category FROM inventory WHERE id = ?), ?, 'damaged', NOW(), ?, 'Item returned with damage')
                ");
                $repair_stmt->execute([
                    $item_data['id'],
                    $item_data['name'],
                    $item_data['id'],
                    $qty,
                    $student_id
                ]);
            }
        }
        
        // Update all affected borrowing requests to pending_return status
        if (!empty($processed_borrowing_requests)) {
            $update_stmt = $pdo->prepare("
                UPDATE borrowing_requests 
                SET current_status = 'pending_return', updated_at = NOW()
                WHERE id = ? AND student_id = ?
            ");
            
            foreach ($processed_borrowing_requests as $borrowing_request_id) {
                $update_stmt->execute([$borrowing_request_id, $student_id]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = 'Return request submitted successfully. Please wait for staff confirmation.';
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error processing return request: " . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while processing your return request. Please try again.';
}

header("Location: dashboard.php?section=costume-borrowing");
exit();
?>
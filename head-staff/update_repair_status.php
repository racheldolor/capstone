<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['item_id']) || !isset($input['status'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit();
    }
    
    $repair_item_id = $input['item_id'];
    $new_status = $input['status'];
    
    // Validate status
    $valid_statuses = ['damaged', 'under_repair', 'repaired'];
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit();
    }
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    try {
        // Get repair item details
        $stmt = $pdo->prepare("SELECT * FROM repair_items WHERE id = ?");
        $stmt->execute([$repair_item_id]);
        $repair_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$repair_item) {
            throw new Exception('Repair item not found');
        }
        
        // Update repair status
        $stmt = $pdo->prepare("
            UPDATE repair_items 
            SET repair_status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $repair_item_id]);
        
        // If marked as repaired, return item to main inventory
        if ($new_status === 'repaired') {
            // Check if item exists in main inventory
            $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
            $stmt->execute([$repair_item['item_id']]);
            $inventory_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($inventory_item) {
                // Add quantity back to inventory - ONLY update available_quantity, NOT total quantity
                $stmt = $pdo->prepare("
                    UPDATE inventory 
                    SET available_quantity = available_quantity + ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $repair_item['quantity'], 
                    $repair_item['item_id']
                ]);
                
                // If this item was reported by a student (damaged during return), 
                // find and close the corresponding borrowing request
                if ($repair_item['reported_by_student_id']) {
                    $stmt = $pdo->prepare("
                        UPDATE borrowing_requests 
                        SET current_status = 'completed'
                        WHERE student_id = ? 
                        AND current_status IN ('active', 'pending_return')
                        AND JSON_SEARCH(approved_items, 'one', ?, NULL, '$[*].id') IS NOT NULL
                    ");
                    $stmt->execute([
                        $repair_item['reported_by_student_id'], 
                        $repair_item['item_id']
                    ]);
                }
            } else {
                // Create new inventory item if it doesn't exist
                $stmt = $pdo->prepare("
                    INSERT INTO inventory (item_name, category, quantity, available_quantity, condition_status, status, created_at)
                    VALUES (?, ?, ?, ?, 'good', 'available', NOW())
                ");
                $stmt->execute([
                    $repair_item['item_name'],
                    $repair_item['category'],
                    $repair_item['quantity'],
                    $repair_item['quantity']
                ]);
            }
            
            // Remove from repair items
            $stmt = $pdo->prepare("DELETE FROM repair_items WHERE id = ?");
            $stmt->execute([$repair_item_id]);
        }
        
        $pdo->commit();
        
        $message = '';
        switch ($new_status) {
            case 'under_repair':
                $message = 'Item marked as under repair';
                break;
            case 'repaired':
                $message = 'Item marked as repaired and returned to inventory';
                break;
            default:
                $message = 'Status updated successfully';
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error updating repair status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update repair status: ' . $e->getMessage()
    ]);
}
?>
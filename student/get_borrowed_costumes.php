<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Check if user is logged in as student
    if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'student') {
        throw new Exception('Unauthorized access');
    }

    // Database connection using centralized config
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDBConnection();

    $student_id = $_SESSION['user_id'];

    // Get all borrow requests for this student (not just approved ones)
    $stmt = $pdo->prepare("
        SELECT 
            br.id,
            br.student_name,
            br.student_email,
            br.item_category,
            br.dates_of_use,
            br.end_date as estimated_return_date,
            br.due_date,
            br.quantity_approved,
            br.item_name,
            br.item_id,
            br.created_at,
            br.approved_date as reviewed_at,
            br.status,
            br.current_status,
            br.approval_notes as review_notes,
            br.approved_items
        FROM borrowing_requests br
        WHERE br.student_id = ? 
        ORDER BY br.created_at DESC
    ");
    
    $stmt->execute([$student_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $all_requests = [];

    foreach ($requests as $request) {
        if ($request['status'] === 'approved' || $request['status'] === 'borrowed') {
            // If staff approved specific items, use those instead of what student typed
            if (!empty($request['approved_items'])) {
                $approved_items = json_decode($request['approved_items'], true);
                
                if (is_array($approved_items) && count($approved_items) > 0) {
                    // Create an entry for each approved item
                    foreach ($approved_items as $approved_item) {
                        $all_requests[] = [
                            'id' => $request['id'],
                            'request_id' => $request['id'],
                            'item_id' => $approved_item['id'],
                            'item_name' => $approved_item['name'],
                            'quantity' => $approved_item['quantity'],
                            'item_type' => $request['item_category'],
                            'category' => $request['item_category'],
                            'condition' => null,
                            'current_status' => $request['current_status'] ?? 'active',
                            'dates_of_use' => $request['dates_of_use'],
                            'estimated_return_date' => $request['estimated_return_date'],
                            'request_date' => $request['created_at'],
                            'approved_date' => $request['reviewed_at'],
                            'due_date' => $request['due_date'] ?? $request['estimated_return_date'],
                            'status' => $request['status'],
                            'review_notes' => $request['review_notes'],
                            'requested_item' => $request['item_name'],
                            'display_type' => 'approved_item'
                        ];
                    }
                } else {
                    // Fallback to old behavior if approved_items is empty
                    $all_requests[] = [
                        'id' => $request['id'],
                        'request_id' => $request['id'],
                        'item_id' => $request['item_id'],
                        'item_name' => $request['item_name'],
                        'item_type' => $request['item_category'],
                        'category' => $request['item_category'],
                        'condition' => null,
                        'current_status' => $request['current_status'] ?? 'active',
                        'dates_of_use' => $request['dates_of_use'],
                        'estimated_return_date' => $request['estimated_return_date'],
                        'request_date' => $request['created_at'],
                        'approved_date' => $request['reviewed_at'],
                        'due_date' => $request['due_date'] ?? $request['estimated_return_date'],
                        'status' => $request['status'],
                        'review_notes' => $request['review_notes'],
                        'display_type' => 'approved_item'
                    ];
                }
            } else {
                // Fallback to old behavior if approved_items is null
                $all_requests[] = [
                    'id' => $request['id'],
                    'request_id' => $request['id'],
                    'item_id' => $request['item_id'],
                    'item_name' => $request['item_name'],
                    'item_type' => $request['item_category'],
                    'category' => $request['item_category'],
                    'condition' => null,
                    'current_status' => $request['current_status'] ?? 'active',
                    'dates_of_use' => $request['dates_of_use'],
                    'estimated_return_date' => $request['estimated_return_date'],
                    'request_date' => $request['created_at'],
                    'approved_date' => $request['reviewed_at'],
                    'due_date' => $request['due_date'] ?? $request['estimated_return_date'],
                    'status' => $request['status'],
                    'review_notes' => $request['review_notes'],
                    'display_type' => 'approved_item'
                ];
            }
        } else {
            // For pending/rejected requests - show what they requested
            $all_requests[] = [
                'id' => $request['id'],
                'request_id' => $request['id'],
                'item_id' => $request['item_id'],
                'item_name' => $request['item_name'] ?? 'Requested item',
                'item_type' => $request['item_category'] ?? 'request',
                'category' => $request['item_category'] ?? 'mixed',
                'condition' => null,
                'current_status' => $request['current_status'] ?? null,
                'dates_of_use' => $request['dates_of_use'],
                'estimated_return_date' => $request['estimated_return_date'],
                'request_date' => $request['created_at'],
                'approved_date' => $request['reviewed_at'],
                'due_date' => $request['due_date'] ?? $request['estimated_return_date'],
                'status' => $request['status'],
                'review_notes' => $request['review_notes'],
                'display_type' => 'request_summary'
            ];
        }
    }

    // Return the results
    echo json_encode([
        'success' => true,
        'requests' => $all_requests,
        'total_requests' => count($all_requests)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
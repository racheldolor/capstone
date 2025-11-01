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

    // Database connection
    $host = 'localhost';
    $dbname = 'capstone_culture_arts';
    $username = 'root';
    $password = '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $student_id = $_SESSION['user_id'];

    // Get all borrow requests for this student (not just approved ones)
    $stmt = $pdo->prepare("
        SELECT 
            br.id,
            br.requester_name,
            br.email,
            br.equipment_categories,
            br.dates_of_use,
            br.estimated_return_date,
            br.due_date,
            br.approved_items,
            br.created_at,
            br.reviewed_at,
            br.status,
            br.current_status,
            br.review_notes
        FROM borrowing_requests br
        WHERE br.student_id = ? 
        ORDER BY br.created_at DESC
    ");
    
    $stmt->execute([$student_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $all_requests = [];

    foreach ($requests as $request) {
        if ($request['status'] === 'approved' && $request['approved_items']) {
            // For approved requests, show the specific approved items
            $approved_items = json_decode($request['approved_items'], true);
            
            if ($approved_items && is_array($approved_items)) {
                foreach ($approved_items as $item) {
                    // Get additional item details from inventory
                    $item_stmt = $pdo->prepare("
                        SELECT name, category, condition_status, status 
                        FROM inventory 
                        WHERE id = ?
                    ");
                    $item_stmt->execute([$item['id']]);
                    $item_details = $item_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($item_details) {
                        $all_requests[] = [
                            'id' => $request['id'], // Add the borrowing request ID
                            'request_id' => $request['id'],
                            'item_id' => $item['id'],
                            'item_name' => $item_details['name'],
                            'item_type' => $item['type'],
                            'category' => $item_details['category'],
                            'condition' => $item_details['condition_status'],
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
                }
            }
        } else {
            // For pending/rejected requests, show the requested categories
            $equipment_categories = json_decode($request['equipment_categories'], true);
            $requested_items = [];
            
            if ($equipment_categories && is_array($equipment_categories)) {
                foreach ($equipment_categories as $category => $items) {
                    if ($items) {
                        if (is_array($items)) {
                            $requested_items = array_merge($requested_items, $items);
                        } else {
                            $requested_items[] = $items;
                        }
                    }
                }
            }
            
            $all_requests[] = [
                'id' => $request['id'], // Add the borrowing request ID
                'request_id' => $request['id'],
                'item_id' => null,
                'item_name' => !empty($requested_items) ? implode(', ', $requested_items) : 'Various items requested',
                'item_type' => 'request',
                'category' => 'mixed',
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
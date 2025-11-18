<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();

    // Get parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Build WHERE conditions
    $where_conditions = [];
    $params = [];

    if (!empty($status)) {
        $where_conditions[] = "status = ?";
        $params[] = $status;
    }

    if (!empty($search)) {
        $where_conditions[] = "(student_name LIKE ? OR student_email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM borrowing_requests $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_requests = $count_stmt->fetchColumn();

    // Calculate pagination
    $total_pages = ceil($total_requests / $limit);
    $offset = ($page - 1) * $limit;

    // Get borrowing requests
    $sql = "SELECT * FROM borrowing_requests $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process the requests
    foreach ($requests as &$request) {
        // Format dates using the correct field names from database schema
        if (!empty($request['start_date'])) {
            $request['date_of_request_formatted'] = date('M j, Y', strtotime($request['start_date']));
        }

        if (!empty($request['end_date'])) {
            $request['estimated_return_date_formatted'] = date('M j, Y', strtotime($request['end_date']));
        }

        if (!empty($request['created_at'])) {
            $request['created_at_formatted'] = date('M j, Y g:i A', strtotime($request['created_at']));
        }

        // Ensure we have the student name (already in correct field)
        if (empty($request['student_name'])) {
            $request['student_name'] = 'Unknown Student';
        }
        
        // Ensure we have item name or provide a default
        if (empty($request['item_name'])) {
            $request['item_name'] = 'Equipment request (details not specified)';
        }
    }

    // Send response
    echo json_encode([
        'success' => true,
        'data' => $requests,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_requests' => $total_requests,
            'limit' => $limit
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>
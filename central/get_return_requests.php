<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Log the start of the request
    error_log("Return requests API called with parameters: " . print_r($_GET, true));
    
    // Check if user is logged in and has proper role
    if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central'])) {
        error_log("Unauthorized access attempt. Session: " . print_r($_SESSION, true));
        throw new Exception('Unauthorized access');
    }

    $pdo = getDBConnection();
    error_log("Database connection successful");

    // Check if required tables exist and get their structure
    $tables_to_check = ['return_requests', 'student_artists', 'borrowing_requests'];
    foreach ($tables_to_check as $table) {
        $check_sql = "SHOW TABLES LIKE '$table'";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute();
        $exists = $check_stmt->fetch();
        if (!$exists) {
            error_log("Table '$table' does not exist");
            throw new Exception("Required table '$table' does not exist in database");
        }
        error_log("Table '$table' exists");
        
        // Get column information for borrowing_requests table
        if ($table === 'borrowing_requests') {
            $columns_sql = "DESCRIBE $table";
            $columns_stmt = $pdo->prepare($columns_sql);
            $columns_stmt->execute();
            $columns = $columns_stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Columns in $table: " . print_r(array_column($columns, 'Field'), true));
        }
    }

    // Get query parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $limit = 10; // Number of records per page
    $offset = ($page - 1) * $limit;

    error_log("Query parameters - Page: $page, Status: '$status', Search: '$search'");

    // Build WHERE conditions
    $where_conditions = ['1=1'];
    $params = [];

    if (!empty($status)) {
        $where_conditions[] = 'rr.status = ?';
        $params[] = $status;
        error_log("Added status filter: $status");
    }

    if (!empty($search)) {
        $where_conditions[] = '(sa.first_name LIKE ? OR sa.last_name LIKE ? OR rr.item_name LIKE ?)';
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        error_log("Added search filter: $search");
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    error_log("WHERE clause: $where_clause");
    error_log("Parameters: " . print_r($params, true));

    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as total
        FROM return_requests rr
        JOIN student_artists sa ON rr.student_id = sa.id
        $where_clause
    ";
    error_log("Count SQL: $count_sql");
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    error_log("Total count: $total_count");

    // Get return requests with student information
    $sql = "
        SELECT 
            rr.id,
            rr.borrowing_request_id,
            rr.student_id,
            rr.item_id,
            rr.item_name,
            rr.condition_notes,
            rr.status,
            rr.requested_at,
            rr.completed_at,
            rr.completed_by,
            sa.first_name,
            sa.last_name,
            sa.sr_code,
            sa.email,
            CONCAT(sa.first_name, ' ', sa.last_name) as student_name,
            br.created_at as request_date,
            br.estimated_return_date as due_date
        FROM return_requests rr
        JOIN student_artists sa ON rr.student_id = sa.id
        LEFT JOIN borrowing_requests br ON rr.borrowing_request_id = br.id
        $where_clause
        ORDER BY rr.requested_at DESC
        LIMIT $limit OFFSET $offset
    ";

    error_log("Main SQL: $sql");
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $return_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Found " . count($return_requests) . " return requests");

    // Calculate pagination info
    $total_pages = ceil($total_count / $limit);

    echo json_encode([
        'success' => true,
        'requests' => $return_requests,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_count' => $total_count,
            'per_page' => $limit
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_return_requests.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>
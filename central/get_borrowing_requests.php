<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login.']);
    exit;
}

// RBAC: Determine access level
$user_role = $_SESSION['user_role'] ?? null;
$user_email = $_SESSION['user_email'] ?? null;
$user_campus = $_SESSION['user_campus'] ?? null;

$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
$isCentralHead = in_array($user_email, $centralHeadEmails);
$canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && $user_role === 'central'));

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
    
    // Apply campus filter for campus-specific users
    if (!$canViewAll && $user_campus) {
        $where_conditions[] = "br.student_campus = ?";
        $params[] = $user_campus;
    }

    if (!empty($status)) {
        $where_conditions[] = "br.status = ?";
        $params[] = $status;
    }

    if (!empty($search)) {
        $where_conditions[] = "(br.student_name LIKE ? OR br.student_email LIKE ? OR br.item_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM borrowing_requests br $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_requests = $count_stmt->fetchColumn();

    // Calculate pagination
    $total_pages = ceil($total_requests / $limit);
    $offset = ($page - 1) * $limit;

    // Get borrowing requests
    $sql = "SELECT br.* FROM borrowing_requests br $where_clause ORDER BY br.created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process the requests
    foreach ($requests as &$request) {
        // Format dates
        if (!empty($request['start_date'])) {
            $request['date_of_request_formatted'] = date('M j, Y', strtotime($request['start_date']));
        }

        if (!empty($request['end_date'])) {
            $request['estimated_return_date_formatted'] = date('M j, Y', strtotime($request['end_date']));
        }

        if (!empty($request['created_at'])) {
            $request['created_at_formatted'] = date('M j, Y g:i A', strtotime($request['created_at']));
        }

        // Ensure we have the student name
        if (empty($request['student_name'])) {
            $request['student_name'] = 'Unknown Student';
        }
        
        // Ensure we have item name
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
        ],
        'debug' => [
            'can_view_all' => $canViewAll,
            'user_campus' => $user_campus,
            'user_role' => $user_role
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_borrowing_requests.php (central): " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>
<?php
session_start();
require_once '../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Check authentication
    if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login.']);
        exit;
    }

    // RBAC: Determine access level
    $user_role = $_SESSION['user_role'] ?? '';
    $user_email = $_SESSION['user_email'] ?? '';
    $user_campus = $_SESSION['user_campus'] ?? null;

    $centralHeadEmails = ['mark.central@g.batstate-u.edu.ph'];
    $isCentralHead = in_array($user_email, $centralHeadEmails);
    $canViewAll = ($user_role === 'admin' || ($user_campus === 'Pablo Borbon' && in_array($user_role, ['head', 'staff'])));

    $pdo = getDBConnection();

    // Get query parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // Build WHERE conditions
    $where_conditions = [];
    $params = [];

    // Apply campus filter for campus-specific users (via student_artists table)
    if (!$canViewAll && $user_campus) {
        $where_conditions[] = 'sa.campus = ?';
        $params[] = $user_campus;
    }

    if (!empty($status)) {
        $where_conditions[] = 'rr.status = ?';
        $params[] = $status;
    }

    if (!empty($search)) {
        $where_conditions[] = '(sa.first_name LIKE ? OR sa.last_name LIKE ? OR rr.item_name LIKE ?)';
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as total
        FROM return_requests rr
        JOIN student_artists sa ON rr.student_id = sa.id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get return requests with student information
    $sql = "
        SELECT 
            rr.id,
            rr.borrowing_request_id,
            rr.student_id,
            rr.item_id,
            rr.item_name,
            rr.item_category,
            rr.return_condition,
            rr.condition_notes,
            rr.status,
            rr.requested_at,
            rr.completed_at,
            sa.first_name,
            sa.last_name,
            sa.sr_code,
            sa.email,
            sa.campus,
            CONCAT(sa.first_name, ' ', sa.last_name) as student_name,
            br.start_date as borrow_date,
            br.end_date as due_date
        FROM return_requests rr
        JOIN student_artists sa ON rr.student_id = sa.id
        LEFT JOIN borrowing_requests br ON rr.borrowing_request_id = br.id
        $where_clause
        ORDER BY rr.requested_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $return_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        ],
        'debug' => [
            'can_view_all' => $canViewAll,
            'user_campus' => $user_campus,
            'user_role' => $user_role
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_return_requests.php (head-staff): " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}
?>
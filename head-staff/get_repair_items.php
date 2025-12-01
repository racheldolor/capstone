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

try {
    $pdo = getDBConnection();
    
    // Get parameters
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 10);
    $repair_status = $_GET['repair_status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($repair_status)) {
        $where_conditions[] = "repair_status = ?";
        $params[] = $repair_status;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(item_name LIKE ? OR category LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM repair_items $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetchColumn();
    
    // Get repair items
    $sql = "
        SELECT ri.*, 
               sa.first_name, sa.last_name, sa.sr_code
        FROM repair_items ri
        LEFT JOIN student_artists sa ON ri.reported_by_student_id = sa.id
        $where_clause
        ORDER BY ri.date_reported DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $repair_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    $formatted_items = [];
    foreach ($repair_items as $item) {
        $formatted_items[] = [
            'id' => $item['id'],
            'item_id' => $item['item_id'],
            'item_name' => $item['item_name'],
            'category' => ucfirst($item['category']),
            'quantity' => $item['quantity'],
            'repair_status' => $item['repair_status'],
            'date_reported' => date('M j, Y', strtotime($item['date_reported'])),
            'reported_by' => $item['first_name'] ? $item['first_name'] . ' ' . $item['last_name'] : 'System',
            'notes' => $item['notes']
        ];
    }
    
    // Calculate pagination
    $total_pages = ceil($total_items / $limit);
    
    echo json_encode([
        'success' => true,
        'items' => $formatted_items,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total_items,
            'per_page' => $limit
        ]
    ]);

} catch (Exception $e) {
    error_log("Error fetching repair items: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch repair items'
    ]);
}
?>
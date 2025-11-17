<?php
session_start();
require_once '../config/database.php';

// Check if user is authenticated
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;
    
    // Get filter parameters
    $status_filter = $_GET['status'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    $campus_filter = $_GET['campus'] ?? '';
    $month_filter = $_GET['month'] ?? '';
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    // Apply status filter
    if (!empty($status_filter)) {
        if ($status_filter === 'active') {
            $where_conditions[] = "status IN ('published', 'ongoing')";
        } elseif ($status_filter === 'completed') {
            $where_conditions[] = "status = 'completed'";
        } elseif ($status_filter === 'cancelled') {
            $where_conditions[] = "status = 'cancelled'";
        } else {
            $where_conditions[] = "status = ?";
            $params[] = $status_filter;
        }
    } else {
        // Default: show all statuses
        $where_conditions[] = "1=1";
    }
    
    if (!empty($category_filter)) {
        $where_conditions[] = "category = ?";
        $params[] = $category_filter;
    }
    
    if (!empty($campus_filter)) {
        $where_conditions[] = "venue = ?";
        $params[] = $campus_filter;
    }
    
    if (!empty($month_filter)) {
        $where_conditions[] = "DATE_FORMAT(start_date, '%Y-%m') = ?";
        $params[] = $month_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE $where_clause");
    $count_stmt->execute($params);
    $total_events = $count_stmt->fetchColumn();
    
    // Get events with pagination
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            description,
            start_date,
            end_date,
            location,
            venue,
            category,
            cultural_groups,
            event_poster,
            status,
            created_at
        FROM events 
        WHERE $where_clause
        ORDER BY start_date DESC, title ASC
        LIMIT $limit OFFSET $offset
    ");
    
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process events data
    foreach ($events as &$event) {
        // Decode cultural groups from JSON
        $event['cultural_groups'] = json_decode($event['cultural_groups'], true) ?? [];
        
        // Format dates
        $event['start_date_formatted'] = date('M j, Y', strtotime($event['start_date']));
        $event['end_date_formatted'] = date('M j, Y', strtotime($event['end_date']));
        $event['created_at_formatted'] = date('M j, Y g:i A', strtotime($event['created_at']));
        
        // Calculate event status
        $today = date('Y-m-d');
        if ($event['start_date'] > $today) {
            $event['event_status'] = 'upcoming';
        } elseif ($event['end_date'] < $today) {
            $event['event_status'] = 'past';
        } else {
            $event['event_status'] = 'ongoing';
        }
        
        // Calculate days until/since event
        $days_diff = (strtotime($event['start_date']) - strtotime($today)) / (60 * 60 * 24);
        $event['days_difference'] = floor($days_diff);
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total_events / $limit),
            'total_events' => $total_events,
            'per_page' => $limit
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching all events: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load events']);
}
?>
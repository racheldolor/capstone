<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff'])) {
    header('Location: ../index.php');
    exit();
}

$pdo = getDBConnection();

// Pagination for student artists
$students_per_page = 5;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get dashboard statistics
try {
    // Count student artists from student_artists table
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM student_artists WHERE status = 'active'");
    $stmt->execute();
    $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count cultural groups (for now, we'll simulate this data)
    $cultural_groups = 0; // Will be implemented later
    
    // Count scheduled events (for now, we'll simulate this data)
    $scheduled_events = 0; // Will be implemented later
    
    // Count worn out costumes (for now, we'll simulate this data)
    $worn_costumes = 0; // Will be implemented later
    
} catch (Exception $e) {
    $student_count = 0;
    $cultural_groups = 0;
    $scheduled_events = 0;
    $worn_costumes = 0;
}

// Get approved student artists with pagination
try {
    $where_conditions = ["status = 'active'"];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR sr_code LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM student_artists $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_students = $count_stmt->fetchColumn();
    
    // Calculate pagination
    $total_pages = ceil($total_students / $students_per_page);
    $offset = ($current_page - 1) * $students_per_page;
    
    // Get paginated students
    try {
        // Try with cultural_group column first
        $sql = "SELECT id, sr_code, first_name, middle_name, last_name, email, campus, program, year_level, cultural_group, created_at 
                FROM student_artists $where_clause 
                ORDER BY id ASC 
                LIMIT $students_per_page OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // If cultural_group column doesn't exist, add it and try again
        try {
            $pdo->exec("ALTER TABLE student_artists ADD COLUMN cultural_group VARCHAR(100) DEFAULT NULL");
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            // If still failing, try without cultural_group column
            $sql = "SELECT id, sr_code, first_name, middle_name, last_name, email, campus, program, year_level, created_at 
                    FROM student_artists $where_clause 
                    ORDER BY id ASC 
                    LIMIT $students_per_page OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Add empty cultural_group to each student
            foreach ($students as &$student) {
                $student['cultural_group'] = null;
            }
        }
    }
    
} catch (Exception $e) {
    $students = [];
    $total_students = 0;
    $total_pages = 0;
    // Debug: Log the error
    error_log("Error fetching students: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Culture and Arts - BatStateU TNEU</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        /* Header */
        .header {
            background: white;
            color: #dc2626;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #e0e0e0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .header-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            color: #333;
        }

        .logout-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #b91c1c;
        }

        /* Main Container */
        .main-container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            min-height: calc(100vh - 70px);
        }

        .nav-menu {
            list-style: none;
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.5rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #555;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background: #f8f9fa;
            color: #dc2626;
            border-left-color: #dc2626;
        }

        .nav-link.active {
            background: #fee2e2;
            color: #dc2626;
            border-left-color: #dc2626;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #dc2626;
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }

        .card-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #dc2626;
        }

        .card-subtitle {
            font-size: 0.85rem;
            color: #888;
            margin-top: 0.5rem;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .panel-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }

        .expand-btn {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .expand-btn:hover {
            background: #e9ecef;
            color: #dc2626;
        }

        .panel-content {
            padding: 2rem;
            text-align: center;
            color: #888;
            font-style: italic;
        }

        /* Chart Area */
        .chart-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }

        .chart-header {
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }

        .chart-content {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 8px;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #333;
        }

        .add-btn {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        /* Student Profiles Specific Styles */
        .search-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .search-container {
            max-width: 300px;
        }

        .search-container label {
            display: block;
            margin-bottom: 0.5rem;
            color: #666;
            font-weight: 500;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-bottom: 2px solid #dc2626;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .student-overview {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .overview-left {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .chart-placeholder {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #ddd;
        }

        .chart-content {
            text-align: center;
            color: #888;
        }

        .chart-content p {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .chart-content small {
            font-size: 0.875rem;
            color: #aaa;
        }

        /* Campus Distribution Styles */
        .campus-distribution {
            display: flex;
            gap: 2rem;
            height: 300px;
        }

        .chart-section {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .pie-chart-container {
            width: 200px;
            height: 200px;
            position: relative;
        }

        .pie-chart {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            position: relative;
        }

        .chart-legend {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.75rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 50%;
        }

        .legend-text {
            font-size: 0.9rem;
            color: #333;
        }

        .bar-chart-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .bar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .bar-label {
            width: 80px;
            font-size: 0.85rem;
            color: #666;
            text-align: right;
        }

        .bar-track {
            flex: 1;
            height: 24px;
            background: #f0f0f0;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            border-radius: 12px;
            transition: width 0.8s ease;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
        }

        .bar-value {
            font-size: 0.75rem;
            color: white;
            font-weight: 600;
        }

        .record-count-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            height: 200px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            padding: 0.75rem;
        }

        .cultural-group-list {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            max-height: 170px;
            overflow-y: auto;
            width: 100%;
            padding: 0.25rem;
        }

        .group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0.6rem;
            background: white;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            font-size: 0.8rem;
            min-height: 32px;
        }

        .group-name {
            font-weight: 500;
            color: #333;
            flex: 1;
            text-align: left;
        }

        .group-count {
            font-weight: 600;
            color: #dc2626;
            background: #fee2e2;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            min-width: 30px;
            text-align: center;
        }

        /* Events Management Styles */
        .events-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .events-left,
        .events-right {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .input-panel,
        .upcoming-panel {
            height: 100%;
        }

        .panel-header-event {
            background: #4285F4;
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .panel-header-upcoming {
            background: #28a745;
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .panel-title-event,
        .panel-title-upcoming {
            margin: 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .event-form {
            padding: 1.5rem;
        }

        .event-form .form-group {
            margin-bottom: 1.2rem;
        }

        .event-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
            font-size: 0.9rem;
        }

        .event-form input,
        .event-form textarea,
        .event-form select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: border-color 0.3s ease;
        }

        .event-form input::placeholder,
        .event-form textarea::placeholder {
            color: #999;
            font-size: 0.9rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .event-form input:focus,
        .event-form textarea:focus,
        .event-form select:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .event-form textarea {
            min-height: 80px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .save-event-btn {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.95rem;
            width: 100%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .save-event-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .events-list {
            padding: 1.5rem;
            min-height: 400px;
        }

        .empty-events {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 300px;
            text-align: center;
            color: #888;
        }

        .empty-events p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: #666;
        }

        .empty-events small {
            font-size: 0.9rem;
            color: #aaa;
        }

        .event-item {
            padding: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .event-item:hover {
            border-color: #dc2626;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.1);
        }

        .event-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .event-date {
            color: #dc2626;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .event-location {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }

        .event-category {
            display: inline-block;
            background: #f8f9fa;
            color: #666;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .events-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e0e0e0;
            background: #f8f9fa;
            text-align: center;
        }

        .view-all-link {
            color: #dc2626;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .view-all-link:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .events-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .record-number {
            font-size: 3rem;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 0.5rem;
        }

        .record-label {
            font-size: 1rem;
            color: #666;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .record-sublabel {
            font-size: 0.875rem;
            color: #888;
        }

        .overview-right {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .record-count-card h3 {
            color: #666;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .record-content {
            height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            color: #888;
        }

        .record-content p {
            margin-bottom: 0.5rem;
        }

        .table-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: #dc2626;
            color: white;
        }

        .table-header-row {
            display: grid;
            grid-template-columns: 120px 1fr 200px 250px 120px;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .header-col {
            padding: 0 0.5rem;
        }

        .table-body {
            min-height: 200px;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            text-align: center;
            color: #888;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .empty-state small {
            color: #aaa;
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e0e0e0;
            background: #f8f9fa;
        }

        .pagination-info {
            color: #666;
            font-size: 0.875rem;
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn:not(:disabled):hover {
            background: #f8f9fa;
            border-color: #dc2626;
        }

        .pagination-number {
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination-number.active {
            background: #dc2626;
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
            }

            .nav-menu {
                display: flex;
                overflow-x: auto;
                padding: 1rem;
            }

            .nav-item {
                flex-shrink: 0;
                margin: 0 0.25rem;
            }

            .nav-link {
                padding: 0.75rem 1rem;
                border-left: none;
                border-bottom: 3px solid transparent;
                border-radius: 8px;
                white-space: nowrap;
            }

            .nav-link:hover,
            .nav-link.active {
                border-left: none;
                border-bottom-color: #dc2626;
            }

            .dashboard-cards {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }

        .applications-modal {
            width: 95%;
            max-width: 1200px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.5rem;
        }

        .close {
            color: #666;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close:hover {
            color: #ff5a5a;
            background-color: #f0f0f0;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .loading-spinner {
            text-align: center;
            padding: 2rem;
            color: #666;
            font-size: 1.1rem;
        }

        .applications-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .application-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }

        .student-info h3 {
            margin: 0;
            color: #333;
            font-size: 1.2rem;
        }

        .student-details {
            margin: 0.25rem 0 0 0;
            color: #666;
            font-size: 0.9rem;
        }

        .application-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .details-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .details-btn:hover {
            background: #5a6268;
        }

        .approve-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .approve-btn:hover {
            background: #218838;
        }

        .deny-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .deny-btn:hover {
            background: #c82333;
        }

        .application-details {
            padding: 1.5rem;
            border-top: 1px solid #eee;
            background: white;
            border-radius: 0 0 8px 8px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .detail-section h4 {
            margin: 0 0 1rem 0;
            color: #333;
            font-size: 1.1rem;
            border-bottom: 2px solid #ff5a5a;
            padding-bottom: 0.5rem;
        }

        .detail-section p {
            margin: 0.5rem 0;
            color: #555;
            line-height: 1.4;
        }

        .detail-section strong {
            color: #333;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
            font-size: 1.1rem;
        }

        .error {
            color: #dc3545;
            text-align: center;
            padding: 1rem;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <img src="../assets/OCA Logo.png" alt="BatStateU Logo" class="logo">
            <h1 class="header-title">Culture and Arts - Dashboard</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span>👤</span>
                <span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
            </div>
            <button class="logout-btn" onclick="logout()">Logout</button>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="#" class="nav-link active" data-section="dashboard">
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="student-profiles">
                            Student Artist Profiles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="events-trainings">
                            Events & Trainings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="reports-analytics">
                            Reports & Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="costume-inventory">
                            Costume Inventory
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Section -->
            <section class="content-section active" id="dashboard">
                <div class="page-header">
                    <h1 class="page-title">Dashboard Overview</h1>
                </div>

                <!-- Dashboard Cards -->
                <div class="dashboard-cards">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Student Artist</div>
                        </div>
                        <div class="card-number"><?= $student_count ?></div>
                        <div class="card-subtitle">Active student performers</div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Cultural Groups</div>
                        </div>
                        <div class="card-number"><?= $cultural_groups ?></div>
                        <div class="card-subtitle">Registered cultural groups</div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Events Scheduled</div>
                        </div>
                        <div class="card-number"><?= $scheduled_events ?></div>
                        <div class="card-subtitle">Upcoming events</div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Worn Out Costumes</div>
                        </div>
                        <div class="card-number"><?= $worn_costumes ?></div>
                        <div class="card-subtitle">Need replacement</div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <div class="content-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Student Artist Overview</h3>
                            <button class="expand-btn">+</button>
                        </div>
                        <div class="panel-content">
                            No data available
                        </div>
                    </div>

                    <div class="content-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Upcoming Events & Trainings</h3>
                            <button class="expand-btn">+</button>
                        </div>
                        <div class="panel-content">
                            No upcoming events scheduled
                        </div>
                    </div>

                    <div class="content-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Costume Inventory Status</h3>
                            <button class="expand-btn">+</button>
                        </div>
                        <div class="panel-content">
                            No inventory data available
                        </div>
                    </div>
                </div>

                <!-- Performance Trend Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Performance Trend Line</h3>
                    </div>
                    <div class="chart-content">
                        No performance data available
                    </div>
                </div>
            </section>

            <!-- Student Artist Profiles Section -->
            <section class="content-section" id="student-profiles">
                <div class="page-header">
                    <h1 class="page-title">Student Artist Profiles</h1>
                    <button class="add-btn" onclick="openApplicationsModal()">
                        <span>+</span>
                        View Applications
                    </button>
                </div>

                <!-- Search and Filter Section -->
                <div class="search-section">
                    <form method="GET" style="display: flex; gap: 1rem; align-items: end;">
                        <input type="hidden" name="section" value="student-profiles">
                        <div class="search-container">
                            <label for="studentSearch">Search Students</label>
                            <input type="text" id="studentSearch" name="search" placeholder="Name, Email, or SR Code" 
                                   class="search-input" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <button type="submit" style="background: #dc2626; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; cursor: pointer;">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="?section=student-profiles" style="background: #6c757d; color: white; padding: 0.75rem 1rem; border-radius: 4px; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Dashboard Overview -->
                <div class="student-overview">
                    <div class="overview-left">
                        <h3 style="margin-bottom: 1rem; color: #666;">Campus Distribution</h3>
                        <div id="campusDistributionChart" class="campus-distribution">
                            <div class="chart-section">
                                <div class="pie-chart-container">
                                    <canvas id="pieChart" width="200" height="200"></canvas>
                                </div>
                                <div class="chart-legend" id="chartLegend">
                                    <!-- Legend will be populated by JavaScript -->
                                </div>
                            </div>
                            <div class="bar-chart-container" id="barChart">
                                <!-- Bar chart will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="overview-right">
                        <div class="record-count-card">
                            <h3>Cultural Groups</h3>
                            <div class="record-count-section" id="culturalGroupCounts">
                                <div class="record-number"><?= $total_students ?></div>
                                <div class="record-label">student artists</div>
                                <div class="record-sublabel">Loading group distribution...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student Profiles Table -->
                <div class="table-section">
                    <div class="table-header">
                        <div class="table-header-row">
                            <div class="header-col">STUDENT ID</div>
                            <div class="header-col">FULL NAME</div>
                            <div class="header-col">CULTURAL GROUP</div>
                            <div class="header-col">EMAIL</div>
                            <div class="header-col">ACTIONS</div>
                        </div>
                    </div>
                    
                    <div class="table-body">
                        <?php if (empty($students)): ?>
                            <div class="empty-state">
                                <?php if (!empty($search)): ?>
                                    <p>No student artists found matching "<?= htmlspecialchars($search) ?>"</p>
                                    <small>Try a different search term or clear the search.</small>
                                <?php else: ?>
                                    <p>No student artist profiles found.</p>
                                    <small>Click "View Applications" to approve new student artists.</small>
                                    <br><br>
                                    <small style="color: #dc2626;">Debug: Total students in database: <?= $total_students ?></small>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <div class="table-row" style="display: grid; grid-template-columns: 120px 1fr 200px 250px 120px; padding: 1rem; border-bottom: 1px solid #e0e0e0; align-items: center;">
                                    <div style="padding: 0 0.5rem; font-weight: 600; color: #dc2626;">
                                        <?= htmlspecialchars($student['sr_code']) ?>
                                    </div>
                                    <div style="padding: 0 0.5rem;">
                                        <?= htmlspecialchars(trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'])) ?>
                                    </div>
                                    <div style="padding: 0 0.5rem;">
                                        <?php if (!empty($student['cultural_group'])): ?>
                                            <span style="background: #28a745; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                                <?= htmlspecialchars($student['cultural_group']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #f0f0f0; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; color: #666;">
                                                Not Assigned
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="padding: 0 0.5rem; font-size: 0.9rem;">
                                        <?= htmlspecialchars($student['email']) ?>
                                    </div>
                                    <div style="padding: 0 0.5rem;">
                                        <button onclick="viewStudentProfile(<?= $student['id'] ?>)" 
                                                style="background: #6c757d; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                                            View
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="pagination">
                        <span class="pagination-info">
                            Showing <?= empty($students) ? 0 : (($current_page - 1) * $students_per_page) + 1 ?> to 
                            <?= min($current_page * $students_per_page, $total_students) ?> of <?= $total_students ?> entries
                        </span>
                        <div class="pagination-controls">
                            <?php if ($current_page > 1): ?>
                                <a href="?section=student-profiles&page=<?= $current_page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                                   class="pagination-btn">Previous</a>
                            <?php else: ?>
                                <button class="pagination-btn" disabled>Previous</button>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="pagination-number active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?section=student-profiles&page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                                       class="pagination-number" style="text-decoration: none; color: #666;"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?section=student-profiles&page=<?= $current_page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                                   class="pagination-btn">Next</a>
                            <?php else: ?>
                                <button class="pagination-btn" disabled>Next</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Events & Trainings Section -->
            <section class="content-section" id="events-trainings">
                <div class="page-header">
                    <h1 class="page-title">Events Management</h1>
                    <button class="add-btn" onclick="openAddEventModal()">
                        <span>+</span>
                        Add New Event
                    </button>
                </div>

                <!-- Main Content Grid -->
                <div class="events-grid">
                    <!-- Left Side - Input New Event -->
                    <div class="events-left">
                        <div class="input-panel">
                            <div class="panel-header-event">
                                <h3 class="panel-title-event">📅 Input New Event</h3>
                            </div>
                            <form id="eventForm" class="event-form">
                                <div class="form-group">
                                    <label for="eventTitle">Event Title*</label>
                                    <input type="text" id="eventTitle" name="title" placeholder="Enter event title" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="eventDescription">Description*</label>
                                    <textarea id="eventDescription" name="description" placeholder="Enter event description" required></textarea>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="startDate">Start Date*</label>
                                        <input type="date" id="startDate" name="start_date" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="endDate">End Date*</label>
                                        <input type="date" id="endDate" name="end_date" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="eventLocation">Location*</label>
                                    <input type="text" id="eventLocation" name="location" placeholder="Enter event location" required>
                                </div>

                                <div class="form-group">
                                    <label for="municipality">Campus</label>
                                    <select id="municipality" name="municipality">
                                        <option value="Pablo Borbon">Pablo Borbon</option>
                                        <option value="Alangilan">Alangilan</option>
                                        <option value="Lipa">Lipa</option>
                                        <option value="ARASOF Nasugbu">ARASOF Nasugbu</option>
                                        <option value="JPLPC Malvar">JPLPC Malvar</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="eventImage">Event Image</label>
                                    <input type="file" id="eventImage" name="image" accept="image/*">
                                </div>

                                <div class="form-group">
                                    <label for="eventCategory">Category*</label>
                                    <select id="eventCategory" name="category" required>
                                        <option value="">Select category</option>
                                        <option value="Training">Training</option>
                                        <option value="Performance">Performance</option>
                                        <option value="Competition">Competition</option>
                                        <option value="Workshop">Workshop</option>
                                        <option value="Cultural Event">Cultural Event</option>
                                        <option value="Festival">Festival</option>
                                    </select>
                                </div>

                                <button type="submit" class="save-event-btn">
                                    💾 Save Event
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Right Side - Upcoming Events -->
                    <div class="events-right">
                        <div class="upcoming-panel">
                            <div class="panel-header-upcoming">
                                <h3 class="panel-title-upcoming">📋 Upcoming Events</h3>
                            </div>
                            <div class="events-list" id="eventsList">
                                <!-- Events will be loaded here -->
                                <div class="empty-events">
                                    <p>No upcoming events scheduled</p>
                                    <small>Add a new event to get started</small>
                                </div>
                            </div>
                            <div class="events-footer">
                                <a href="#" class="view-all-link" onclick="viewAllEvents()">View All Events →</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Reports & Analytics Section -->
            <section class="content-section" id="reports-analytics">
                <div class="page-header">
                    <h1 class="page-title">Reports & Analytics</h1>
                    <button class="add-btn">
                        <span>+</span>
                        Generate Report
                    </button>
                </div>
                <div class="content-panel">
                    <div class="panel-content">
                        Reports and analytics coming soon...
                    </div>
                </div>
            </section>

            <!-- Costume Inventory Section -->
            <section class="content-section" id="costume-inventory">
                <div class="page-header">
                    <h1 class="page-title">Costume Inventory</h1>
                    <button class="add-btn">
                        <span>+</span>
                        Add Costume
                    </button>
                </div>
                <div class="content-panel">
                    <div class="panel-content">
                        Costume inventory management coming soon...
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Applications Modal -->
    <div id="applicationsModal" class="modal" style="display: none;">
        <div class="modal-content applications-modal">
            <div class="modal-header">
                <h2>Student Applications</h2>
                <span class="close" onclick="closeApplicationsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="applicationsLoading" style="display: none;">
                    <div class="loading-spinner">Loading applications...</div>
                </div>
                <div id="applicationsContent">
                    <!-- Applications will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Student Profile Modal -->
    <div id="studentProfileModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px; width: 90%;">
            <div class="modal-header">
                <h2>Student Profile</h2>
                <span class="close" onclick="closeStudentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="studentLoading" style="display: none;">
                    <div class="loading-spinner">Loading student profile...</div>
                </div>
                <div id="studentContent">
                    <!-- Student profile will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            const contentSections = document.querySelectorAll('.content-section');

            // Check URL parameter for active section
            const urlParams = new URLSearchParams(window.location.search);
            const activeSection = urlParams.get('section') || 'dashboard';

            // Set active section based on URL parameter
            navLinks.forEach(l => l.classList.remove('active'));
            contentSections.forEach(s => s.classList.remove('active'));

            const activeLink = document.querySelector(`[data-section="${activeSection}"]`);
            const activeContentSection = document.getElementById(activeSection);

            if (activeLink && activeContentSection) {
                activeLink.classList.add('active');
                activeContentSection.classList.add('active');
            } else {
                // Default to dashboard if section not found
                document.querySelector('[data-section="dashboard"]').classList.add('active');
                document.getElementById('dashboard').classList.add('active');
            }

            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links and sections
                    navLinks.forEach(l => l.classList.remove('active'));
                    contentSections.forEach(s => s.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Show corresponding section
                    const sectionId = this.dataset.section;
                    const targetSection = document.getElementById(sectionId);
                    if (targetSection) {
                        targetSection.classList.add('active');
                    }

                    // Update URL without page reload
                    const newUrl = `${window.location.pathname}?section=${sectionId}`;
                    window.history.pushState({}, '', newUrl);
                });
            });

            // Expand button functionality
            const expandBtns = document.querySelectorAll('.expand-btn');
            expandBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    this.textContent = this.textContent === '+' ? '−' : '+';
                });
            });
        });

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../index.php';
            }
        }

        // Student Profiles functionality
        function openAddStudentModal() {
            alert('Add student modal functionality coming soon!');
        }

        function viewStudentProfile(studentId) {
            const modal = document.getElementById('studentProfileModal');
            modal.style.display = 'flex';
            loadStudentProfile(studentId);
        }

        // Student Profile Modal Functions
        function closeStudentModal() {
            const modal = document.getElementById('studentProfileModal');
            modal.style.display = 'none';
        }

        function loadStudentProfile(studentId) {
            const loadingDiv = document.getElementById('studentLoading');
            const contentDiv = document.getElementById('studentContent');
            
            loadingDiv.style.display = 'block';
            contentDiv.style.display = 'none';
            
            fetch('get_student_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ student_id: studentId })
            })
            .then(response => response.json())
            .then(data => {
                loadingDiv.style.display = 'none';
                contentDiv.style.display = 'block';
                
                if (data.success) {
                    displayStudentProfile(data.student);
                } else {
                    contentDiv.innerHTML = '<p class="error">Error loading student profile: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                loadingDiv.style.display = 'none';
                contentDiv.style.display = 'block';
                contentDiv.innerHTML = '<p class="error">Error loading student profile: ' + error.message + '</p>';
            });
        }

        function displayStudentProfile(student) {
            const contentDiv = document.getElementById('studentContent');
            const currentCulturalGroup = student.cultural_group || '';
            
            const html = `
                <div style="margin-bottom: 2rem;">
                    <div>
                        <h3 style="color: #dc2626; margin-bottom: 1rem; border-bottom: 2px solid #dc2626; padding-bottom: 0.5rem;">Personal Information</h3>
                        <div style="space-y: 0.75rem;">
                            <p><strong>SR Code:</strong> ${student.sr_code}</p>
                            <p><strong>Full Name:</strong> ${student.first_name} ${student.middle_name || ''} ${student.last_name}</p>
                            <p><strong>Email:</strong> ${student.email}</p>
                            <p><strong>Campus:</strong> ${student.campus}</p>
                            <p><strong>Program:</strong> ${student.program}</p>
                            <p><strong>Year Level:</strong> ${student.year_level}</p>
                            <p><strong>Date Registered:</strong> ${new Date(student.created_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                </div>
                
                <div style="border-top: 1px solid #e0e0e0; padding-top: 1.5rem;">
                    <h3 style="color: #dc2626; margin-bottom: 1rem;">Cultural Group Assignment</h3>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <label for="culturalGroup" style="font-weight: 600;">Assign to Group:</label>
                        <select id="culturalGroup" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; min-width: 200px;">
                            <option value="">Not Assigned</option>
                            <option value="Dulaang Batangan" ${currentCulturalGroup === 'Dulaang Batangan' ? 'selected' : ''}>Dulaang Batangan</option>
                            <option value="BatStateU Dance Company" ${currentCulturalGroup === 'BatStateU Dance Company' ? 'selected' : ''}>BatStateU Dance Company</option>
                            <option value="Diwayanis Dance Theatre" ${currentCulturalGroup === 'Diwayanis Dance Theatre' ? 'selected' : ''}>Diwayanis Dance Theatre</option>
                            <option value="BatStateU Band" ${currentCulturalGroup === 'BatStateU Band' ? 'selected' : ''}>BatStateU Band</option>
                            <option value="Indak Yaman Dance Varsity" ${currentCulturalGroup === 'Indak Yaman Dance Varsity' ? 'selected' : ''}>Indak Yaman Dance Varsity</option>
                            <option value="Ritmo Voice" ${currentCulturalGroup === 'Ritmo Voice' ? 'selected' : ''}>Ritmo Voice</option>
                            <option value="Sandugo Dance Group" ${currentCulturalGroup === 'Sandugo Dance Group' ? 'selected' : ''}>Sandugo Dance Group</option>
                            <option value="Areglo Band" ${currentCulturalGroup === 'Areglo Band' ? 'selected' : ''}>Areglo Band</option>
                            <option value="Teatro Aliwana" ${currentCulturalGroup === 'Teatro Aliwana' ? 'selected' : ''}>Teatro Aliwana</option>
                            <option value="The Levites" ${currentCulturalGroup === 'The Levites' ? 'selected' : ''}>The Levites</option>
                            <option value="Melophiles" ${currentCulturalGroup === 'Melophiles' ? 'selected' : ''}>Melophiles</option>
                            <option value="Sindayog" ${currentCulturalGroup === 'Sindayog' ? 'selected' : ''}>Sindayog</option>
                        </select>
                        <button onclick="updateCulturalGroup(${student.id})" style="background: #dc2626; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">
                            Update Assignment
                        </button>
                    </div>
                </div>
            `;
            
            contentDiv.innerHTML = html;
        }

        function updateCulturalGroup(studentId) {
            const select = document.getElementById('culturalGroup');
            const culturalGroup = select.value;
            
            fetch('update_cultural_group.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    student_id: studentId, 
                    cultural_group: culturalGroup 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cultural group assignment updated successfully!');
                    // Reload the page to reflect changes in the table
                    window.location.reload();
                } else {
                    alert('Error updating cultural group: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating cultural group: ' + error.message);
            });
        }

        // Applications Modal Functions
        function openApplicationsModal() {
            const modal = document.getElementById('applicationsModal');
            modal.style.display = 'flex';
            loadApplications();
        }

        function closeApplicationsModal() {
            const modal = document.getElementById('applicationsModal');
            modal.style.display = 'none';
        }

        function loadApplications() {
            const loadingDiv = document.getElementById('applicationsLoading');
            const contentDiv = document.getElementById('applicationsContent');
            
            loadingDiv.style.display = 'block';
            contentDiv.style.display = 'none';
            
            fetch('get_applications.php')
                .then(response => response.json())
                .then(data => {
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                    
                    if (data.success) {
                        displayApplications(data.applications);
                    } else {
                        contentDiv.innerHTML = '<p class="error">Error loading applications: ' + data.message + '</p>';
                    }
                })
                .catch(error => {
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                    contentDiv.innerHTML = '<p class="error">Error loading applications: ' + error.message + '</p>';
                });
        }

        function displayApplications(applications) {
            const contentDiv = document.getElementById('applicationsContent');
            
            if (applications.length === 0) {
                contentDiv.innerHTML = '<p class="empty-state">No pending applications found.</p>';
                return;
            }
            
            let html = '<div class="applications-list">';
            applications.forEach((app, index) => {
                html += `
                    <div class="application-item">
                        <div class="application-header">
                            <div class="student-info">
                                <h3>${app.full_name}</h3>
                                <p class="student-details">${app.sr_code} • ${app.email}</p>
                            </div>
                            <div class="application-actions">
                                <button class="details-btn" onclick="toggleApplicationDetails(${index})">
                                    <span id="toggle-${index}">▼</span> Details
                                </button>
                                <button class="approve-btn" onclick="updateApplicationStatus(${app.id}, 'approved')">
                                    Approve
                                </button>
                                <button class="deny-btn" onclick="updateApplicationStatus(${app.id}, 'rejected')">
                                    Reject
                                </button>
                            </div>
                        </div>
                        <div class="application-details" id="details-${index}" style="display: none;">
                            <div class="details-grid">
                                <div class="detail-section">
                                    <h4>Personal Information</h4>
                                    <p><strong>Address:</strong> ${app.address}</p>
                                    <p><strong>Present Address:</strong> ${app.present_address || 'Same as above'}</p>
                                    <p><strong>Date of Birth:</strong> ${app.date_of_birth}</p>
                                    <p><strong>Age:</strong> ${app.age}</p>
                                    <p><strong>Gender:</strong> ${app.gender}</p>
                                    <p><strong>Place of Birth:</strong> ${app.place_of_birth}</p>
                                    <p><strong>Contact:</strong> ${app.contact_number}</p>
                                </div>
                                <div class="detail-section">
                                    <h4>Family Information</h4>
                                    <p><strong>Father:</strong> ${app.father_name}</p>
                                    <p><strong>Mother:</strong> ${app.mother_name}</p>
                                    <p><strong>Guardian:</strong> ${app.guardian || 'N/A'}</p>
                                    <p><strong>Guardian Contact:</strong> ${app.guardian_contact || 'N/A'}</p>
                                </div>
                                <div class="detail-section">
                                    <h4>Educational Information</h4>
                                    <p><strong>Campus:</strong> ${app.campus}</p>
                                    <p><strong>College:</strong> ${app.college}</p>
                                    <p><strong>Program:</strong> ${app.program}</p>
                                    <p><strong>Year Level:</strong> ${app.year_level}</p>
                                    <p><strong>Units:</strong> 1st Sem: ${app.first_semester_units}, 2nd Sem: ${app.second_semester_units}</p>
                                </div>
                                <div class="detail-section">
                                    <h4>Performance Type</h4>
                                    <p>${app.performance_type}</p>
                                </div>
                                <div class="detail-section">
                                    <h4>Consent</h4>
                                    <p>${app.consent === 'yes' ? 'Agreed to data privacy terms' : 'Did not agree to data privacy terms'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            contentDiv.innerHTML = html;
        }

        function toggleApplicationDetails(index) {
            const details = document.getElementById(`details-${index}`);
            const toggle = document.getElementById(`toggle-${index}`);
            
            if (details.style.display === 'none') {
                details.style.display = 'block';
                toggle.textContent = '▲';
            } else {
                details.style.display = 'none';
                toggle.textContent = '▼';
            }
        }

        function updateApplicationStatus(applicationId, status) {
            if (!confirm(`Are you sure you want to ${status} this application?`)) {
                return;
            }
            
            fetch('update_application_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    application_id: applicationId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Application ${status} successfully!`);
                    loadApplications(); // Reload the applications list
                } else {
                    alert('Error updating application: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating application: ' + error.message);
            });
        }

        // Search functionality for student profiles
        function initializeStudentSearch() {
            const searchInput = document.getElementById('studentSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    // Search functionality will be implemented with backend integration
                    console.log('Searching for:', searchTerm);
                });
            }
        }

        // Initialize search when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            initializeStudentSearch();
            loadCampusDistribution();
            loadCulturalGroupDistribution();
        });

        // Campus Distribution Functions
        function loadCampusDistribution() {
            console.log('Loading campus distribution...');
            fetch('get_campus_distribution.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Campus distribution response:', data);
                    if (data.success) {
                        displayCampusDistribution(data.campusDistribution, data.totalStudents);
                    } else {
                        console.error('Failed to load campus distribution:', data.error);
                        showEmptyCampusChart();
                    }
                })
                .catch(error => {
                    console.error('Error loading campus distribution:', error);
                    showEmptyCampusChart();
                });
        }

        function displayCampusDistribution(campusData, totalStudents) {
            console.log('Campus data received:', campusData);
            
            if (!campusData || campusData.length === 0) {
                showEmptyCampusChart();
                return;
            }

            // Colors for different campuses
            const colors = [
                '#4285F4', // Blue
                '#FF6B35', // Orange  
                '#9C27B0', // Purple
                '#4CAF50', // Green
                '#FF9800', // Amber
                '#607D8B'  // Blue Grey
            ];

            // Draw pie chart
            drawPieChart(campusData, colors);
            
            // Draw legend
            drawLegend(campusData, colors);
            
            // Draw bar chart
            drawBarChart(campusData, colors);
        }

        function drawPieChart(campusData, colors) {
            const canvas = document.getElementById('pieChart');
            const ctx = canvas.getContext('2d');
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = 80;

            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // If only one campus, draw full circle
            if (campusData.length === 1) {
                ctx.beginPath();
                ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
                ctx.fillStyle = colors[0];
                ctx.fill();
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.stroke();
                return;
            }

            let currentAngle = -Math.PI / 2; // Start from top
            
            campusData.forEach((campus, index) => {
                const sliceAngle = (campus.percentage / 100) * 2 * Math.PI;
                
                // Draw slice
                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
                ctx.closePath();
                ctx.fillStyle = colors[index % colors.length];
                ctx.fill();
                
                // Draw border
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.stroke();
                
                currentAngle += sliceAngle;
            });
        }

        function drawLegend(campusData, colors) {
            const legendContainer = document.getElementById('chartLegend');
            legendContainer.innerHTML = '';
            
            campusData.forEach((campus, index) => {
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                
                legendItem.innerHTML = `
                    <div class="legend-color" style="background: ${colors[index % colors.length]}"></div>
                    <div class="legend-text">${campus.campus} (${campus.percentage}%)</div>
                `;
                
                legendContainer.appendChild(legendItem);
            });
        }

        function drawBarChart(campusData, colors) {
            const barContainer = document.getElementById('barChart');
            barContainer.innerHTML = '';
            
            const maxCount = Math.max(...campusData.map(c => c.count));
            
            campusData.forEach((campus, index) => {
                const barItem = document.createElement('div');
                barItem.className = 'bar-item';
                
                const percentage = (campus.count / maxCount) * 100;
                
                barItem.innerHTML = `
                    <div class="bar-label">${campus.campus}</div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: ${percentage}%; background: ${colors[index % colors.length]}">
                            <span class="bar-value">${campus.count}</span>
                        </div>
                    </div>
                `;
                
                barContainer.appendChild(barItem);
            });
        }

        function showEmptyCampusChart() {
            const chartContainer = document.getElementById('campusDistributionChart');
            chartContainer.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; height: 300px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #ddd;">
                    <div style="text-align: center; color: #888;">
                        <p style="font-size: 1rem; margin-bottom: 0.5rem;">No campus distribution data available</p>
                        <small style="font-size: 0.875rem; color: #aaa;">Chart will appear when student data is available</small>
                    </div>
                </div>
            `;
        }

        // Cultural Group Distribution Functions
        function loadCulturalGroupDistribution() {
            console.log('Loading cultural group distribution...');
            fetch('get_cultural_group_distribution.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Cultural group distribution response:', data);
                    if (data.success) {
                        displayCulturalGroupDistribution(data.groupDistribution, data.totalStudents);
                    } else {
                        console.error('Failed to load cultural group distribution:', data.error);
                        showEmptyGroupChart();
                    }
                })
                .catch(error => {
                    console.error('Error loading cultural group distribution:', error);
                    showEmptyGroupChart();
                });
        }

        function displayCulturalGroupDistribution(groupData, totalStudents) {
            const container = document.getElementById('culturalGroupCounts');
            
            if (!groupData || groupData.length === 0) {
                showEmptyGroupChart();
                return;
            }

            let html = '<div class="cultural-group-list">';

            groupData.forEach(group => {
                html += `
                    <div class="group-item">
                        <span class="group-name">${group.group_name}</span>
                        <span class="group-count">${group.count}</span>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        function showEmptyGroupChart() {
            const container = document.getElementById('culturalGroupCounts');
            container.innerHTML = `
                <div class="record-number">0</div>
                <div class="record-label">student artists</div>
                <div class="record-sublabel">No groups assigned</div>
            `;
        }

        // Events Management Functions
        function openAddEventModal() {
            // For now, just focus on the form - can be expanded to a modal later
            document.getElementById('eventTitle').focus();
        }

        function viewAllEvents() {
            // Placeholder for view all events functionality
            alert('View all events functionality will be implemented soon!');
        }

        // Event Form Submission
        document.addEventListener('DOMContentLoaded', function() {
            const eventForm = document.getElementById('eventForm');
            if (eventForm) {
                eventForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    saveEvent();
                });
            }
        });

        function saveEvent() {
            const form = document.getElementById('eventForm');
            const formData = new FormData(form);
            
            // Add some validation
            const title = formData.get('title');
            const description = formData.get('description');
            const startDate = formData.get('start_date');
            const endDate = formData.get('end_date');
            const location = formData.get('location');
            const category = formData.get('category');

            if (!title || !description || !startDate || !endDate || !location || !category) {
                alert('Please fill in all required fields');
                return;
            }

            if (new Date(startDate) > new Date(endDate)) {
                alert('Start date cannot be after end date');
                return;
            }

            // Here you would typically send the data to a server
            // For now, we'll just show a success message and reset the form
            alert('Event saved successfully!');
            form.reset();
            
            // You can also update the events list here
            loadUpcomingEvents();
        }

        function loadUpcomingEvents() {
            // Placeholder function to load events from server
            // This would typically fetch from a database
            const eventsList = document.getElementById('eventsList');
            
            // For demo purposes, show empty state
            eventsList.innerHTML = `
                <div class="empty-events">
                    <p>No upcoming events scheduled</p>
                    <small>Add a new event to get started</small>
                </div>
            `;
        }
    </script>
</body>
</html>

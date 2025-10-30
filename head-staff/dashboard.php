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

        .add-btn.secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
        }

        .add-btn.secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 0 0.25rem;
            transition: all 0.3s ease;
        }

        .action-btn.small {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        .action-btn.approve {
            background: #10b981;
            color: white;
        }

        .action-btn.approve:hover {
            background: #059669;
        }

        .action-btn.reject {
            background: #ef4444;
            color: white;
        }

        .action-btn.reject:hover {
            background: #dc2626;
        }

        .action-btn.view {
            background: #6b7280;
            color: white;
        }

        .action-btn.view:hover {
            background: #4b5563;
        }

        /* Table Styles for Borrow Requests */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .table-container th,
        .table-container td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-container th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }

        .table-container tr:hover {
            background: #f9fafb;
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
            display: flex;
            flex-direction: column;
        }

        .events-list {
            flex: 1;
            overflow-y: auto;
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

        /* Multi-select styles */
        .multi-select-container {
            position: relative;
        }

        .multi-select-display {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 0.75rem;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 20px;
        }

        .multi-select-display:hover {
            border-color: #ccc;
        }

        .multi-select-display .placeholder {
            color: #888;
        }

        .multi-select-display .selected-groups {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .selected-group-tag {
            background: #dc2626;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .remove-tag {
            cursor: pointer;
            font-weight: bold;
        }

        .dropdown-arrow {
            transition: transform 0.3s ease;
        }

        .dropdown-arrow.open {
            transform: rotate(180deg);
        }

        .multi-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .checkbox-item:hover {
            background: #f8f9fa;
        }

        .checkbox-item input[type="checkbox"] {
            margin: 0 0.5rem 0 0;
            width: 16px;
            height: 16px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .checkbox-item span {
            line-height: 1.4;
            font-size: 0.9rem;
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
            margin-top: auto;
            flex-shrink: 0;
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
                        <button type="submit" style="background: #dc2626; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; height: 36px; box-sizing: border-box; font-size: 14px;">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="?section=student-profiles" style="background: #6c757d; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; box-sizing: border-box; height: 36px; font-size: 14px;">Clear</a>
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
                    <h1 class="page-title">Events & Trainings</h1>
                </div>

                <!-- Main Content Grid -->
                <div class="events-grid">
                    <!-- Left Side - Input New Event -->
                    <div class="events-left">
                        <div class="input-panel">
                            <div class="panel-header-event">
                                <h3 class="panel-title-event">Input New Event</h3>
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
                                    <label for="culturalGroups">Cultural Group(s) Concerned</label>
                                    <div class="multi-select-container">
                                        <div class="multi-select-display" id="culturalGroupsDisplay" onclick="toggleCulturalGroupsDropdown()">
                                            <span class="placeholder">Select cultural groups...</span>
                                            <span class="dropdown-arrow">▼</span>
                                        </div>
                                        <div class="multi-select-dropdown" id="culturalGroupsDropdown" style="display: none;">
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="Dulaang Batangan">
                                                <span>Dulaang Batangan</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="BatStateU Dance Company">
                                                <span>BatStateU Dance Company</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="Diwayanis Dance Theatre">
                                                <span>Diwayanis Dance Theatre</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="BatStateU Band">
                                                <span>BatStateU Band</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="Indak Yaman Dance Varsity">
                                                <span>Indak Yaman Dance Varsity</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="Ritmo Voice">
                                                <span>Ritmo Voice</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="Sandugo Dance Group">
                                                <span>Sandugo Dance Group</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="Areglo Band">
                                                <span>Areglo Band</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="Teatro Aliwana">
                                                <span>Teatro Aliwana</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="The Levites">
                                                <span>The Levites</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="Melophiles">
                                                <span>Melophiles</span>
                                            </label>
                                            <label class="checkbox-item">
                                                <input type="checkbox" name="cultural_groups[]" value="Sindayog">
                                                <span>Sindayog</span>
                                            </label>
                                        </div>
                                    </div>
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

                                <div class="form-buttons" style="display: flex; gap: 0.5rem;">
                                    <button type="submit" class="save-event-btn">
                                        Save Event
                                    </button>
                                    <button type="button" class="cancel-event-btn" onclick="cancelEdit()" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600; display: none;">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Right Side - Upcoming Events -->
                    <div class="events-right">
                        <div class="upcoming-panel">
                            <div class="panel-header-upcoming">
                                <h3 class="panel-title-upcoming">Upcoming Events</h3>
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
                    <div style="display: flex; gap: 1rem;">
                        <button class="add-btn">
                            <span>+</span>
                            Add Costume
                        </button>
                        <button class="add-btn secondary" onclick="openBorrowRequests()">
                            Borrow Requests
                        </button>
                    </div>
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

    <!-- All Events Modal -->
    <div id="allEventsModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 1200px; width: 95%;">
            <div class="modal-header">
                <h2>All Events</h2>
                <span class="close" onclick="closeAllEventsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="eventsFilters" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div>
                        <label for="statusFilter">Status:</label>
                        <select id="statusFilter" onchange="loadAllEvents()">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label for="categoryFilter">Category:</label>
                        <select id="categoryFilter" onchange="loadAllEvents()">
                            <option value="">All Categories</option>
                            <option value="Training">Training</option>
                            <option value="Performance">Performance</option>
                            <option value="Competition">Competition</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Cultural Event">Cultural Event</option>
                            <option value="Festival">Festival</option>
                        </select>
                    </div>
                    <div>
                        <label for="campusFilter">Campus:</label>
                        <select id="campusFilter" onchange="loadAllEvents()">
                            <option value="">All Campuses</option>
                            <option value="Pablo Borbon">Pablo Borbon</option>
                            <option value="Alangilan">Alangilan</option>
                            <option value="Lipa">Lipa</option>
                            <option value="ARASOF Nasugbu">ARASOF Nasugbu</option>
                            <option value="JPLPC Malvar">JPLPC Malvar</option>
                        </select>
                    </div>
                    <div>
                        <label for="monthFilter">Month:</label>
                        <input type="month" id="monthFilter" onchange="loadAllEvents()">
                    </div>
                </div>
                <div id="allEventsLoading" style="display: none;">
                    <div class="loading-spinner">Loading events...</div>
                </div>
                <div id="allEventsContent">
                    <!-- Events will be loaded here -->
                </div>
                <div id="eventsPagination" style="margin-top: 1rem; text-align: center;">
                    <!-- Pagination will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Borrow Requests Modal -->
    <div id="borrowRequestsModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 1000px; width: 95%;">
            <div class="modal-header">
                <h2>Student Borrow Requests</h2>
                <span class="close" onclick="closeBorrowRequestsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="borrowRequestsFilters" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div>
                        <label for="statusRequestFilter">Status:</label>
                        <select id="statusRequestFilter" onchange="loadBorrowRequests()">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="">All Status</option>
                        </select>
                    </div>
                </div>
                <div id="borrowRequestsLoading" style="text-align: center; padding: 2rem;">
                    <p>Loading borrow requests...</p>
                </div>
                <div id="borrowRequestsContent" style="display: none;">
                    <div class="table-container">
                        <table id="borrowRequestsTable">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Items Requested</th>
                                    <th>Request Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="borrowRequestsTableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="borrowRequestsPagination" style="margin-top: 1rem; text-align: center;">
                    <!-- Pagination will be loaded here -->
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
                
                // Load upcoming events if Events & Trainings section is initially active
                if (activeSection === 'events-trainings') {
                    loadUpcomingEvents();
                }
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
                        
                        // Load upcoming events when Events & Trainings section is activated
                        if (sectionId === 'events-trainings') {
                            loadUpcomingEvents();
                        }
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
            const modal = document.getElementById('allEventsModal');
            modal.style.display = 'flex';
            loadAllEvents();
        }

        function closeAllEventsModal() {
            const modal = document.getElementById('allEventsModal');
            modal.style.display = 'none';
        }

        // Costume Inventory Functions
        function openBorrowRequests() {
            // Show the borrow requests modal
            const modal = document.getElementById('borrowRequestsModal');
            modal.style.display = 'flex';
            loadBorrowRequests();
        }

        function closeBorrowRequestsModal() {
            const modal = document.getElementById('borrowRequestsModal');
            modal.style.display = 'none';
        }

        function loadBorrowRequests(page = 1) {
            const loadingDiv = document.getElementById('borrowRequestsLoading');
            const contentDiv = document.getElementById('borrowRequestsContent');
            const tableBody = document.getElementById('borrowRequestsTableBody');
            
            loadingDiv.style.display = 'block';
            contentDiv.style.display = 'none';
            
            // Get filter value
            const status = document.getElementById('statusRequestFilter').value;
            
            // Here you would make an actual API call to fetch borrow requests
            // For now, show empty table
            setTimeout(() => {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: #666;">
                            No borrow requests found
                        </td>
                    </tr>
                `;
                
                loadingDiv.style.display = 'none';
                contentDiv.style.display = 'block';
            }, 500);
        }
        
        function getStatusBadge(status) {
            const badges = {
                'pending': '<span class="status-badge pending">Pending</span>',
                'approved': '<span class="status-badge approved">Approved</span>',
                'rejected': '<span class="status-badge rejected">Rejected</span>'
            };
            return badges[status] || status;
        }
        
        function getRequestActions(requestId, status) {
            if (status === 'pending') {
                return `
                    <button class="action-btn small approve" onclick="approveRequest(${requestId})">Approve</button>
                    <button class="action-btn small reject" onclick="rejectRequest(${requestId})">Reject</button>
                `;
            } else {
                return `<button class="action-btn small view" onclick="viewRequest(${requestId})">View</button>`;
            }
        }
        
        function approveRequest(requestId) {
            if (confirm('Are you sure you want to approve this borrow request?')) {
                // Here you would make an API call to approve the request
                alert('Request approved successfully!');
                loadBorrowRequests(); // Reload the list
            }
        }
        
        function rejectRequest(requestId) {
            if (confirm('Are you sure you want to reject this borrow request?')) {
                // Here you would make an API call to reject the request
                alert('Request rejected successfully!');
                loadBorrowRequests(); // Reload the list
            }
        }
        
        function viewRequest(requestId) {
            // Here you would show detailed view of the request
            alert('Viewing request details for ID: ' + requestId);
        }

        function loadAllEvents(page = 1) {
            const loadingDiv = document.getElementById('allEventsLoading');
            const contentDiv = document.getElementById('allEventsContent');
            
            loadingDiv.style.display = 'block';
            contentDiv.style.display = 'none';
            
            // Get filter values
            const status = document.getElementById('statusFilter').value;
            const category = document.getElementById('categoryFilter').value;
            const campus = document.getElementById('campusFilter').value;
            const month = document.getElementById('monthFilter').value;
            
            // Build query parameters
            const params = new URLSearchParams({
                page: page,
                limit: 10,
                status: status
            });
            
            if (category) params.append('category', category);
            if (campus) params.append('campus', campus);
            if (month) params.append('month', month);
            
            fetch(`get_all_events.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                    
                    if (data.success) {
                        displayAllEvents(data.events);
                        displayEventsPagination(data.pagination);
                    } else {
                        contentDiv.innerHTML = '<p class="error">Error loading events: ' + data.message + '</p>';
                    }
                })
                .catch(error => {
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                    contentDiv.innerHTML = '<p class="error">Error loading events: ' + error.message + '</p>';
                });
        }

        function displayAllEvents(events) {
            const contentDiv = document.getElementById('allEventsContent');
            
            if (events.length === 0) {
                contentDiv.innerHTML = '<p style="text-align: center; color: #666; padding: 2rem;">No events found with the selected filters.</p>';
                return;
            }

            let html = '<div style="display: grid; gap: 1rem;">';
            events.forEach(event => {
                const culturalGroups = event.cultural_groups.length > 0 
                    ? event.cultural_groups.join(', ') 
                    : 'All groups';

                const statusColor = event.event_status === 'upcoming' ? '#28a745' : 
                                  event.event_status === 'ongoing' ? '#ffc107' : '#6c757d';

                html += `
                    <div style="border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; background: white;">
                        <div style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: start;">
                            <div>
                                <h4 style="margin: 0 0 0.5rem 0; color: #333; font-size: 1.2rem;">${event.title}</h4>
                                <p style="margin: 0 0 0.75rem 0; color: #666; line-height: 1.4;">${event.description}</p>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; font-size: 0.9rem;">
                                    <div><strong>Date:</strong> ${event.start_date_formatted} - ${event.end_date_formatted}</div>
                                    <div><strong>Location:</strong> ${event.location}</div>
                                    <div><strong>Category:</strong> ${event.category}</div>
                                    ${event.campus ? `<div><strong>Campus:</strong> ${event.campus}</div>` : ''}
                                    <div><strong>Cultural Groups:</strong> ${culturalGroups}</div>
                                    <div><strong>Created:</strong> ${event.created_at_formatted}</div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="display: inline-block; background: ${statusColor}; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; margin-bottom: 0.5rem;">
                                    ${event.event_status}
                                </div>
                                ${event.days_difference >= 0 ? 
                                    `<div style="font-size: 0.8rem; color: #666;">In ${event.days_difference} day(s)</div>` :
                                    `<div style="font-size: 0.8rem; color: #666;">${Math.abs(event.days_difference)} day(s) ago</div>`
                                }
                                <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    <button onclick="editEvent(${event.id})" style="background: #6c757d; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                                        Edit
                                    </button>
                                    <button onclick="deleteEvent(${event.id}, '${event.title.replace(/'/g, "\\'")}'); " style="background: #dc3545; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';

            contentDiv.innerHTML = html;
        }

        function displayEventsPagination(pagination) {
            const paginationDiv = document.getElementById('eventsPagination');
            
            if (pagination.total_pages <= 1) {
                paginationDiv.innerHTML = '';
                return;
            }

            let html = '<div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem;">';
            
            // Previous button
            if (pagination.current_page > 1) {
                html += `<button onclick="loadAllEvents(${pagination.current_page - 1})" style="padding: 0.5rem 1rem; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Previous</button>`;
            }
            
            // Page numbers
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const isActive = i === pagination.current_page;
                html += `<button onclick="loadAllEvents(${i})" style="padding: 0.5rem 0.75rem; border: 1px solid ${isActive ? '#dc2626' : '#ddd'}; background: ${isActive ? '#dc2626' : 'white'}; color: ${isActive ? 'white' : '#333'}; border-radius: 4px; cursor: pointer;">${i}</button>`;
            }
            
            // Next button
            if (pagination.current_page < pagination.total_pages) {
                html += `<button onclick="loadAllEvents(${pagination.current_page + 1})" style="padding: 0.5rem 1rem; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Next</button>`;
            }
            
            html += '</div>';
            html += `<div style="text-align: center; margin-top: 0.5rem; font-size: 0.9rem; color: #666;">
                Showing ${pagination.total_events} total events
            </div>`;
            
            paginationDiv.innerHTML = html;
        }

        // Event Management Functions
        let isEditMode = false;
        let currentEditingEventId = null;

        function editEvent(eventId) {
            isEditMode = true;
            currentEditingEventId = eventId;
            
            // Load event data
            fetch(`get_event.php?id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateEventForm(data.event);
                        // Scroll to form
                        document.getElementById('eventTitle').scrollIntoView({ behavior: 'smooth' });
                        // Update form title and button
                        document.querySelector('.panel-title-event').textContent = 'Edit Event';
                        document.querySelector('.save-event-btn').textContent = 'Update Event';
                        document.querySelector('.cancel-event-btn').style.display = 'inline-block';
                        // Close modal if open
                        closeAllEventsModal();
                    } else {
                        alert('Error loading event: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading event details');
                });
        }

        function populateEventForm(event) {
            // Populate form fields
            document.getElementById('eventTitle').value = event.title;
            document.getElementById('eventDescription').value = event.description;
            document.getElementById('startDate').value = event.start_date_formatted;
            document.getElementById('endDate').value = event.end_date_formatted;
            document.getElementById('eventLocation').value = event.location;
            document.getElementById('municipality').value = event.campus || '';
            document.getElementById('eventCategory').value = event.category;
            
            // Handle cultural groups
            const checkboxes = document.querySelectorAll('input[name="cultural_groups[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = event.cultural_groups.includes(checkbox.value);
            });
            updateCulturalGroupsDisplay();
        }

        function cancelEdit() {
            isEditMode = false;
            currentEditingEventId = null;
            // Reset form
            document.getElementById('eventForm').reset();
            updateCulturalGroupsDisplay();
            // Update form title and button
            document.querySelector('.panel-title-event').textContent = 'Input New Event';
            document.querySelector('.save-event-btn').textContent = 'Save Event';
            document.querySelector('.cancel-event-btn').style.display = 'none';
        }

        function deleteEvent(eventId, eventTitle) {
            if (!confirm(`Are you sure you want to delete the event "${eventTitle}"? This action cannot be undone.`)) {
                return;
            }
            
            fetch('delete_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ event_id: eventId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Refresh events lists
                    loadUpcomingEvents();
                    if (document.getElementById('allEventsModal').style.display === 'flex') {
                        loadAllEvents();
                    }
                } else {
                    alert('Error deleting event: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting event');
            });
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

        // Cultural Groups Multi-select Functions
        function toggleCulturalGroupsDropdown() {
            const dropdown = document.getElementById('culturalGroupsDropdown');
            const arrow = document.querySelector('.dropdown-arrow');
            
            if (dropdown.style.display === 'none') {
                dropdown.style.display = 'block';
                arrow.classList.add('open');
            } else {
                dropdown.style.display = 'none';
                arrow.classList.remove('open');
            }
        }

        function updateCulturalGroupsDisplay() {
            const checkboxes = document.querySelectorAll('input[name="cultural_groups[]"]:checked');
            const display = document.getElementById('culturalGroupsDisplay');
            const placeholder = display.querySelector('.placeholder');
            const arrow = display.querySelector('.dropdown-arrow');
            
            // Remove existing tags
            const existingTags = display.querySelector('.selected-groups');
            if (existingTags) {
                existingTags.remove();
            }
            
            if (checkboxes.length === 0) {
                placeholder.style.display = 'inline';
                placeholder.textContent = 'Select cultural groups...';
            } else {
                placeholder.style.display = 'none';
                
                const selectedGroupsContainer = document.createElement('div');
                selectedGroupsContainer.className = 'selected-groups';
                
                checkboxes.forEach(checkbox => {
                    const tag = document.createElement('span');
                    tag.className = 'selected-group-tag';
                    tag.innerHTML = `
                        ${checkbox.value}
                        <span class="remove-tag" onclick="removeCulturalGroup('${checkbox.value}')">&times;</span>
                    `;
                    selectedGroupsContainer.appendChild(tag);
                });
                
                display.insertBefore(selectedGroupsContainer, arrow);
            }
        }

        function removeCulturalGroup(groupName) {
            const checkbox = document.querySelector(`input[name="cultural_groups[]"][value="${groupName}"]`);
            if (checkbox) {
                checkbox.checked = false;
                updateCulturalGroupsDisplay();
            }
        }

        // Add event listeners for cultural groups checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name="cultural_groups[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateCulturalGroupsDisplay);
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const container = document.querySelector('.multi-select-container');
                if (container && !container.contains(event.target)) {
                    const dropdown = document.getElementById('culturalGroupsDropdown');
                    const arrow = document.querySelector('.dropdown-arrow');
                    dropdown.style.display = 'none';
                    arrow.classList.remove('open');
                }
            });
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

            // Show loading state
            const saveBtn = document.querySelector('.save-event-btn');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = isEditMode ? 'Updating...' : 'Saving...';
            saveBtn.disabled = true;

            // Add event ID for edit mode
            if (isEditMode && currentEditingEventId) {
                formData.append('event_id', currentEditingEventId);
            }

            // Determine endpoint
            const endpoint = isEditMode ? 'update_event.php' : 'save_event.php';

            // Send data to server
            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(isEditMode ? 'Event updated successfully!' : 'Event saved successfully!');
                    form.reset();
                    updateCulturalGroupsDisplay(); // Reset multi-select display
                    
                    // Reset edit mode
                    if (isEditMode) {
                        cancelEdit();
                    }
                    
                    loadUpcomingEvents(); // Refresh events list
                } else {
                    alert('Error saving event: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the event');
            })
            .finally(() => {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            });
        }

        function loadUpcomingEvents() {
            const eventsList = document.getElementById('eventsList');
            
            // Show loading state
            eventsList.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <p>Loading upcoming events...</p>
                </div>
            `;
            
            fetch('get_upcoming_events.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayUpcomingEvents(data.events);
                    } else {
                        eventsList.innerHTML = `
                            <div class="empty-events">
                                <p>Error loading events</p>
                                <small>${data.message}</small>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading events:', error);
                    eventsList.innerHTML = `
                        <div class="empty-events">
                            <p>Error loading upcoming events</p>
                            <small>Please try again later</small>
                        </div>
                    `;
                });
        }

        function displayUpcomingEvents(events) {
            const eventsList = document.getElementById('eventsList');
            
            if (events.length === 0) {
                eventsList.innerHTML = `
                    <div class="empty-events">
                        <p>No upcoming events scheduled</p>
                        <small>Add a new event to get started</small>
                    </div>
                `;
                return;
            }

            let html = '';
            events.forEach(event => {
                const culturalGroups = event.cultural_groups.length > 0 
                    ? event.cultural_groups.slice(0, 2).join(', ') + 
                      (event.cultural_groups.length > 2 ? ` +${event.cultural_groups.length - 2} more` : '')
                    : 'All groups';

                html += `
                    <div class="event-item">
                        <div class="event-title">${event.title}</div>
                        <div class="event-date">${event.start_date_formatted}</div>
                        <div class="event-location">${event.location}</div>
                        <div class="event-category">${event.category}</div>
                        ${event.campus ? `<div style="font-size: 0.8rem; color: #888; margin-top: 0.25rem;">${event.campus}</div>` : ''}
                        <div style="font-size: 0.8rem; color: #666; margin-top: 0.25rem;">${culturalGroups}</div>
                        ${event.days_until === 0 ? '<div style="font-size: 0.8rem; color: #dc2626; font-weight: 600; margin-top: 0.25rem;">Today!</div>' : 
                          event.days_until === 1 ? '<div style="font-size: 0.8rem; color: #dc2626; font-weight: 600; margin-top: 0.25rem;">Tomorrow</div>' :
                          `<div style="font-size: 0.8rem; color: #888; margin-top: 0.25rem;">In ${event.days_until} day(s)</div>`}
                        <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem;">
                            <button onclick="editEvent(${event.id})" style="background: #6c757d; color: white; border: none; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.7rem;">
                                Edit
                            </button>
                            <button onclick="deleteEvent(${event.id}, '${event.title.replace(/'/g, "\\'")}'); " style="background: #dc3545; color: white; border: none; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.7rem;">
                                Delete
                            </button>
                        </div>
                    </div>
                `;
            });

            eventsList.innerHTML = html;
        }
    </script>
</body>
</html>

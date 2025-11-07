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
    // Count student artists from student_artists table (active and suspended)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM student_artists WHERE status IN ('active', 'suspended')");
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

// Get student artists with pagination (active and suspended)
try {
    $where_conditions = ["status IN ('active', 'suspended')"];
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
    
    // Get paginated students - try with cultural_group column first
    try {
        $sql = "SELECT id, sr_code, first_name, middle_name, last_name, email, campus, program, year_level, cultural_group, status, created_at 
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
            $sql = "SELECT id, sr_code, first_name, middle_name, last_name, email, campus, program, year_level, status, created_at 
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
            position: fixed;
            top: 70px;
            left: 0;
            z-index: 50;
            overflow: hidden;
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
            margin-left: 280px;
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

        /* Condition Badges */
        .condition-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .condition-badge.condition-good {
            background: #d1fae5;
            color: #065f46;
        }

        .condition-badge.condition-worn-out {
            background: #fef3c7;
            color: #92400e;
        }

        .condition-badge.condition-bad {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Status badges for inventory */
        .status-badge.status-available {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.status-borrowed {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.status-maintenance {
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
                position: relative;
                top: 0;
                left: 0;
                overflow-x: auto;
            }
            
            .main-content {
                margin-left: 0;
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

        .modal.show {
            display: flex;
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

        /* Search input styling */
        #applicationSearchInput:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
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
                            <div class="header-col">SR-CODE</div>
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
                                            <span style="background: #17a2b8; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
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
                </div>

                <!-- Event Participation Table -->
                <div class="content-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Event Participation</h3>
                    </div>
                    <div class="panel-content" id="eventParticipationContent">
                        <div class="loading-state" id="participationLoading">
                            <p>Loading event participation data...</p>
                        </div>
                        <div class="participation-table-container" id="participationTableContainer" style="display: none;">
                            <!-- Event participation table will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Event Participation Chart -->
                <div class="content-panel" id="participationChartPanel" style="display: none; margin-top: 2rem;">
                    <div class="panel-header">
                        <h3 class="panel-title">Event Participation Chart</h3>
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <button id="allEventsBtn" class="chart-control-btn active" style="padding: 0.5rem 1rem; border: 1px solid #dc2626; border-radius: 4px; background: #dc2626; color: white; cursor: pointer; font-weight: 600;">
                                All Events
                            </button>
                            <button id="individualEventBtn" class="chart-control-btn" style="padding: 0.5rem 1rem; border: 1px solid #ddd; border-radius: 4px; background: white; color: #333; cursor: pointer; font-weight: 600;">
                                Individual Events
                            </button>
                            <span id="selectedEventDisplay" style="margin-left: 1rem; color: #666; font-style: italic; display: none;">
                                No event selected
                            </span>
                        </div>
                    </div>
                    <div class="panel-content">
                        <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <canvas id="participationChart" width="800" height="400" style="max-width: 100%; height: auto;"></canvas>
                        </div>
                        <div id="chartLegendContainer" style="margin-top: 1rem; display: flex; justify-content: center; flex-wrap: wrap; gap: 1rem;">
                            <!-- Legend will be populated here -->
                        </div>
                        <!-- Descriptive Analytics Section -->
                        <div id="chartAnalyticsContainer" style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #dc2626;">
                            <h4 style="margin: 0 0 1rem 0; color: #333; font-size: 1.1rem; font-weight: 600;">📊 Analytics & Insights</h4>
                            <div id="chartAnalyticsContent">
                                <!-- Analytics will be populated here -->
                                <p style="color: #666; margin: 0;">Select a chart view to see detailed analytics</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Event Selection Modal -->
                <div id="eventSelectionModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
                    <div style="background: white; border-radius: 8px; max-width: 600px; width: 90%; max-height: 70vh; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div style="padding: 1.5rem; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; color: #333; font-size: 1.2rem;">Select Event</h3>
                            <button onclick="closeEventSelectionModal()" style="background: none; border: none; font-size: 1.5rem; color: #666; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
                        </div>
                        <!-- Search Bar -->
                        <div style="padding: 1rem; border-bottom: 1px solid #e0e0e0;">
                            <div style="position: relative; max-width: 100%;">
                                <input 
                                    type="text" 
                                    id="eventSearchInput" 
                                    placeholder="Search events by title, category, or location..." 
                                    style="width: 100%; padding: 10px 40px 10px 15px; border: 1px solid #ddd; border-radius: 25px; font-size: 14px; outline: none; transition: border-color 0.3s; box-sizing: border-box;"
                                    oninput="filterEvents()"
                                >
                                <span style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #666; font-size: 16px;">🔍</span>
                            </div>
                        </div>
                        <div id="eventSelectionList" style="max-height: 350px; overflow-y: auto; padding: 1rem;">
                            <!-- Event list will be populated here -->
                        </div>
                    </div>
                </div>
            </section>

            <!-- Costume Inventory Section -->
            <section class="content-section" id="costume-inventory">
                <div class="page-header">
                    <h1 class="page-title">Costume Inventory</h1>
                    <div style="display: flex; gap: 1rem;">
                        <button class="add-btn" onclick="openAddItemModal()">
                            <span>+</span>
                            Add Costume
                        </button>
                        <button class="add-btn" onclick="openBorrowRequests()">
                            Borrow Requests
                        </button>
                        <button class="add-btn" onclick="openReturns()">
                            Returns
                        </button>
                    </div>
                </div>

                <!-- Inventory Grid -->
                <div class="inventory-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1.5rem;">
                    <!-- Costumes Table -->
                    <div class="inventory-left">
                        <div class="inventory-panel">
                            <div style="padding: 1rem; border-bottom: 1px solid #e0e0e0;">
                                <h3 style="margin: 0; color: #333; font-size: 1.25rem; font-weight: 600;">Costumes</h3>
                            </div>
                            <div class="inventory-table-container">
                                <div class="table-section">
                                    <div class="table-header">
                                        <div class="table-header-row" style="grid-template-columns: 1fr 120px 120px;">
                                            <div class="header-col">NAME</div>
                                            <div class="header-col">CONDITION</div>
                                            <div class="header-col">STATUS</div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-body" id="costumesTableBody">
                                        <!-- Costumes will be loaded dynamically -->
                                        <div class="empty-state" style="padding: 2rem; text-align: center; color: #666;">
                                            <p>No costumes found.</p>
                                            <small>Click "Add Costume" to get started.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Equipment Table -->
                    <div class="inventory-right">
                        <div class="inventory-panel">
                            <div style="padding: 1rem; border-bottom: 1px solid #e0e0e0;">
                                <h3 style="margin: 0; color: #333; font-size: 1.25rem; font-weight: 600;">Equipment</h3>
                            </div>
                            <div class="inventory-table-container">
                                <div class="table-section">
                                    <div class="table-header">
                                        <div class="table-header-row" style="grid-template-columns: 1fr 120px 120px;">
                                            <div class="header-col">NAME</div>
                                            <div class="header-col">CONDITION</div>
                                            <div class="header-col">STATUS</div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-body" id="equipmentTableBody">
                                        <!-- Equipment will be loaded dynamically -->
                                        <div class="empty-state" style="padding: 2rem; text-align: center; color: #666;">
                                            <p>No equipment found.</p>
                                            <small>Click "Add Costume" to get started.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                <!-- Search Bar -->
                <div style="margin-bottom: 1.5rem;">
                    <div style="position: relative; max-width: 400px;">
                        <input 
                            type="text" 
                            id="applicationSearchInput" 
                            placeholder="Search by student name..." 
                            style="width: 100%; padding: 10px 40px 10px 15px; border: 1px solid #ddd; border-radius: 25px; font-size: 14px; outline: none; transition: border-color 0.3s;"
                            oninput="filterApplications()"
                        >
                        <span style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #666; font-size: 16px;">🔍</span>
                    </div>
                </div>
                
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
                    <div>
                        <label for="requestSearchInput">Search:</label>
                        <input type="text" id="requestSearchInput" placeholder="Search by name or email..." 
                               style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; width: 250px;"
                               onkeyup="debounceSearch(loadBorrowRequests, 500)">
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
                                    <th>Student Info</th>
                                    <th>Items Requested</th>
                                    <th>Dates</th>
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

    <!-- Returns Modal -->
    <div id="returnsModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 1200px; width: 95%;">
            <div class="modal-header">
                <h2>Return Requests</h2>
                <span class="close" onclick="closeReturnsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="returnsFilters" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div>
                        <label for="statusReturnFilter">Status:</label>
                        <select id="statusReturnFilter" onchange="loadReturnRequests()">
                            <option value="pending">Pending Returns</option>
                            <option value="completed">Completed Returns</option>
                            <option value="">All Status</option>
                        </select>
                    </div>
                    <div>
                        <label for="searchReturnFilter">Search:</label>
                        <input type="text" id="searchReturnFilter" placeholder="Student name or item name..." 
                               onkeyup="debounceReturnSearch()" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <button onclick="loadReturnRequests()" style="padding: 0.5rem 1rem; background: #dc2626; color: white; border: none; border-radius: 4px;">
                        Refresh
                    </button>
                </div>
                <div id="returnsLoading" style="text-align: center; padding: 2rem;">
                    <p>Loading return requests...</p>
                </div>
                <div id="returnsContent" style="display: none;">
                    <div class="table-container">
                        <table id="returnRequestsTable">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Item Name</th>
                                    <th>Request Date</th>
                                    <th>Condition Notes</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="returnRequestsTableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="returnsPagination" style="margin-top: 1rem; text-align: center;">
                    <!-- Pagination will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="addItemModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px; width: 90%;">
            <div class="modal-header">
                <h2>Add Costume/Equipment</h2>
                <span class="close" onclick="closeAddItemModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addItemForm" style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group">
                        <label for="itemName">Name*</label>
                        <input type="text" id="itemName" name="name" placeholder="Enter item name" required 
                               style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                    </div>
                    
                    <div class="form-group">
                        <label for="itemCategory">Category*</label>
                        <select id="itemCategory" name="category" required 
                                style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                            <option value="">Select category</option>
                            <option value="costume">Costume</option>
                            <option value="equipment">Equipment</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="itemCondition">Condition*</label>
                        <select id="itemCondition" name="condition" required 
                                style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                            <option value="">Select condition</option>
                            <option value="good">Good</option>
                            <option value="worn-out">Worn-out</option>
                            <option value="bad">Bad</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="itemDescription">Description (Optional)</label>
                        <textarea id="itemDescription" name="description" placeholder="Enter item description" 
                                  style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; font-family: inherit; min-height: 80px; resize: vertical;"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1rem;">
                        <button type="button" onclick="closeAddItemModal()" 
                                style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; cursor: pointer; font-size: 1rem;">
                            Cancel
                        </button>
                        <button type="submit" 
                                style="background: #dc2626; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; cursor: pointer; font-size: 1rem;">
                            Save Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Item Selection Modal -->
    <div id="itemSelectionModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 1200px; width: 95%;">
            <div class="modal-header">
                <h2>Select Items to Approve</h2>
            </div>
            <div class="modal-body">
                <!-- Search bar -->
                <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: end;">
                    <div>
                        <label for="itemSearchInput">Search Items:</label>
                        <input type="text" id="itemSearchInput" placeholder="Search by name..." 
                               style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; width: 300px;"
                               onkeyup="searchAvailableItems()">
                    </div>
                    <div>
                        <label for="categoryFilterSelect">Category:</label>
                        <select id="categoryFilterSelect" onchange="filterItemsByCategory()" 
                                style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">All Items</option>
                            <option value="costume">Costumes</option>
                            <option value="equipment">Equipment</option>
                        </select>
                    </div>
                </div>

                <!-- Item selection grid -->
                <div class="inventory-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <!-- Costumes Table -->
                    <div class="inventory-left">
                        <div class="inventory-panel">
                            <div style="padding: 1rem; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
                                <h3 style="margin: 0; color: #333; font-size: 1.25rem; font-weight: 600;">Available Costumes</h3>
                                <span id="selectedCostumesCount" style="color: #dc2626; font-weight: 600;">0 selected</span>
                            </div>
                            <div class="inventory-table-container" style="max-height: 400px; overflow-y: auto;">
                                <div class="table-section">
                                    <div class="table-header">
                                        <div class="table-header-row" style="grid-template-columns: 40px 1fr 120px 120px;">
                                            <div class="header-col">
                                                <input type="checkbox" id="selectAllCostumes" onchange="toggleSelectAllCostumes()">
                                            </div>
                                            <div class="header-col">NAME</div>
                                            <div class="header-col">CONDITION</div>
                                            <div class="header-col">STATUS</div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-body" id="availableCostumesBody">
                                        <!-- Available costumes will be loaded here -->
                                        <div class="empty-state" style="padding: 2rem; text-align: center; color: #666;">
                                            <p>Loading available costumes...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Equipment Table -->
                    <div class="inventory-right">
                        <div class="inventory-panel">
                            <div style="padding: 1rem; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
                                <h3 style="margin: 0; color: #333; font-size: 1.25rem; font-weight: 600;">Available Equipment</h3>
                                <span id="selectedEquipmentCount" style="color: #dc2626; font-weight: 600;">0 selected</span>
                            </div>
                            <div class="inventory-table-container" style="max-height: 400px; overflow-y: auto;">
                                <div class="table-section">
                                    <div class="table-header">
                                        <div class="table-header-row" style="grid-template-columns: 40px 1fr 120px 120px;">
                                            <div class="header-col">
                                                <input type="checkbox" id="selectAllEquipment" onchange="toggleSelectAllEquipment()">
                                            </div>
                                            <div class="header-col">NAME</div>
                                            <div class="header-col">CONDITION</div>
                                            <div class="header-col">STATUS</div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-body" id="availableEquipmentBody">
                                        <!-- Available equipment will be loaded here -->
                                        <div class="empty-state" style="padding: 2rem; text-align: center; color: #666;">
                                            <p>Loading available equipment...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div style="display: flex; gap: 1rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid #e0e0e0;">
                    <button type="button" onclick="closeItemSelectionModal()" 
                            style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; cursor: pointer; font-size: 1rem;">
                        Cancel
                    </button>
                    <button type="button" onclick="approveWithSelectedItems()" 
                            style="background: #28a745; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; cursor: pointer; font-size: 1rem;" 
                            id="approveButton" disabled>
                        Approve Request (0 items)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Participants Modal -->
    <div id="eventParticipantsModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 900px; width: 95%;">
            <div class="modal-header">
                <h2 id="participantsModalTitle">Event Participants</h2>
                <span class="close" onclick="closeParticipantsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="eventDetailsSection" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div id="eventDetailsContent">
                        <!-- Event details will be loaded here -->
                    </div>
                </div>
                <div id="participantsLoading" style="text-align: center; padding: 2rem;">
                    <p>Loading participants...</p>
                </div>
                <div id="participantsContent" style="display: none;">
                    <div class="participants-summary" style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <h4 style="margin: 0; color: #333;">Participants List</h4>
                        <span id="participantsCount" style="background: #dc2626; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">0 participants</span>
                    </div>
                    <div class="table-container">
                        <table id="participantsTable" style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <thead style="background: #dc2626; color: white;">
                                <tr>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Student Info</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Cultural Group</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Academic Info</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Joined Date</th>
                                </tr>
                            </thead>
                            <tbody id="participantsTableBody">
                                <!-- Participants will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                    <div id="noParticipantsMessage" style="text-align: center; padding: 3rem; color: #666; display: none;">
                        <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No participants yet</p>
                        <small>Students who join this event will appear here</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variable to store applications data for filtering
        let allApplications = [];

        // Utility function to format dates
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            const contentSections = document.querySelectorAll('.content-section');

            console.log('Navigation initialized with', navLinks.length, 'links and', contentSections.length, 'sections');

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
                
                // Load event participation if Reports & Analytics section is initially active
                if (activeSection === 'reports-analytics') {
                    loadEventParticipation();
                }
                
                // Load inventory items if Costume Inventory section is initially active
                if (activeSection === 'costume-inventory') {
                    loadInventoryItems();
                }
            } else {
                // Default to dashboard if section not found
                const defaultLink = document.querySelector('[data-section="dashboard"]');
                const defaultSection = document.getElementById('dashboard');
                if (defaultLink) defaultLink.classList.add('active');
                if (defaultSection) defaultSection.classList.add('active');
            }

            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    console.log('Navigation clicked:', this.dataset.section);
                    
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
                        console.log('Activated section:', sectionId);
                        
                        // Load upcoming events when Events & Trainings section is activated
                        if (sectionId === 'events-trainings') {
                            loadUpcomingEvents();
                        }
                        
                        // Load event participation when Reports & Analytics section is activated
                        if (sectionId === 'reports-analytics') {
                            loadEventParticipation();
                        }
                        
                        // Load inventory items when Costume Inventory section is activated
                        if (sectionId === 'costume-inventory') {
                            loadInventoryItems();
                        }
                    } else {
                        console.error('Target section not found:', sectionId);
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

            // Chart controls event listeners
            const allEventsBtn = document.getElementById('allEventsBtn');
            const individualEventBtn = document.getElementById('individualEventBtn');
            
            if (allEventsBtn) {
                allEventsBtn.addEventListener('click', function() {
                    setActiveChartButton('all');
                    if (typeof updateChart === 'function') {
                        updateChart();
                    }
                });
            }
            
            if (individualEventBtn) {
                individualEventBtn.addEventListener('click', function() {
                    if (typeof openEventSelectionModal === 'function') {
                        openEventSelectionModal();
                    }
                });
            }
        });

        // Utility function for debounced search
        let searchTimeout;
        function debounceSearch(func, delay) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => func(1), delay); // Reset to page 1 for new search
        }

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
                    
                    ${student.desired_cultural_group ? `
                    <div style="margin-bottom: 1rem; padding: 0.75rem; background: #f8f9fa; border-left: 4px solid #17a2b8; border-radius: 4px;">
                        <p style="margin: 0; color: #333;"><strong>Applied for:</strong> <span style="color: #17a2b8; font-weight: 600;">${student.desired_cultural_group}</span></p>
                        <small style="color: #666; font-style: italic;">This is the cultural group the student originally wanted to join</small>
                    </div>
                    ` : ''}
                    
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <label for="culturalGroup" style="font-weight: 600;">Current Assignment:</label>
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
            const searchInput = document.getElementById('applicationSearchInput');
            
            modal.style.display = 'none';
            // Clear search input when closing modal
            if (searchInput) {
                searchInput.value = '';
            }
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
            // Store applications globally for filtering
            allApplications = applications;
            
            const contentDiv = document.getElementById('applicationsContent');
            
            if (applications.length === 0) {
                contentDiv.innerHTML = '<p class="empty-state">No pending applications found.</p>';
                return;
            }
            
            renderApplicationsList(applications);
        }

        function renderApplicationsList(applications) {
            const contentDiv = document.getElementById('applicationsContent');
            
            if (applications.length === 0) {
                contentDiv.innerHTML = '<p class="empty-state">No applications match your search.</p>';
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

        function filterApplications() {
            const searchTerm = document.getElementById('applicationSearchInput').value.toLowerCase().trim();
            
            if (searchTerm === '') {
                renderApplicationsList(allApplications);
                return;
            }
            
            const filteredApplications = allApplications.filter(app => {
                const fullName = app.full_name.toLowerCase();
                const firstName = (app.first_name || '').toLowerCase();
                const middleName = (app.middle_name || '').toLowerCase();
                const lastName = (app.last_name || '').toLowerCase();
                const srCode = (app.sr_code || '').toLowerCase();
                const email = (app.email || '').toLowerCase();
                
                return fullName.includes(searchTerm) ||
                       firstName.includes(searchTerm) ||
                       middleName.includes(searchTerm) ||
                       lastName.includes(searchTerm) ||
                       srCode.includes(searchTerm) ||
                       email.includes(searchTerm);
            });
            
            renderApplicationsList(filteredApplications);
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
            
            // Get filter values
            const status = document.getElementById('statusRequestFilter').value;
            const search = document.getElementById('requestSearchInput') ? document.getElementById('requestSearchInput').value : '';
            
            // Build query parameters
            const params = new URLSearchParams({
                page: page,
                limit: 10
            });
            
            if (status) params.append('status', status);
            if (search) params.append('search', search);
            
            fetch(`get_borrowing_requests.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                    
                    if (data.success) {
                        displayBorrowRequests(data.data);
                        displayRequestsPagination(data.pagination);
                    } else {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: #dc3545;">
                                    Error loading requests: ${data.error}
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading borrow requests:', error);
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                    
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #dc3545;">
                                Error loading borrow requests. Please try again.
                            </td>
                        </tr>
                    `;
                });
        }
        
        function displayBorrowRequests(requests) {
            const tableBody = document.getElementById('borrowRequestsTableBody');
            
            if (!requests || requests.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: #666;">
                            No borrow requests found
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            requests.forEach(request => {
                // Format equipment categories
                let equipmentList = '';
                if (request.equipment_categories) {
                    const categories = [];
                    for (const [category, items] of Object.entries(request.equipment_categories)) {
                        if (items) {
                            // Handle both string and array formats
                            if (Array.isArray(items) && items.length > 0) {
                                categories.push(`${category.charAt(0).toUpperCase() + category.slice(1)}: ${items.join(', ')}`);
                            } else if (typeof items === 'string' && items.trim().length > 0) {
                                categories.push(`${category.charAt(0).toUpperCase() + category.slice(1)}: ${items.trim()}`);
                            }
                        }
                    }
                    equipmentList = categories.join('<br>');
                }
                
                html += `
                    <tr>
                        <td style="padding: 0.75rem;">
                            <div style="font-weight: 600;">${request.student_name || request.requester_name}</div>
                            <div style="font-size: 0.8rem; color: #666;">${request.email}</div>
                            <div style="font-size: 0.8rem; color: #666;">${request.student_campus || request.campus || ''}</div>
                            <div style="font-size: 0.8rem; color: #666;">${request.created_at_formatted || ''}</div>
                        </td>
                        <td style="padding: 0.75rem;">
                            <div style="font-size: 0.85rem; max-width: 200px;">${equipmentList}</div>
                        </td>
                        <td style="padding: 0.75rem;">
                            <div style="font-size: 0.85rem;">${request.dates_of_use || 'Not specified'}</div>
                            <div style="font-size: 0.8rem; color: #666;">Return: ${request.estimated_return_date_formatted || 'Not specified'}</div>
                        </td>
                        <td style="padding: 0.75rem;">
                            ${getStatusBadge(request.status)}
                        </td>
                        <td style="padding: 0.75rem;">
                            ${getRequestActions(request.id, request.status)}
                        </td>
                    </tr>
                `;
            });
            
            tableBody.innerHTML = html;
        }
        
        function displayRequestsPagination(pagination) {
            const paginationDiv = document.getElementById('borrowRequestsPagination');
            if (!paginationDiv) return;
            
            if (pagination.total_pages <= 1) {
                paginationDiv.innerHTML = '';
                return;
            }
            
            let html = '<div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1rem;">';
            
            // Previous button
            if (pagination.current_page > 1) {
                html += `<button class="pagination-btn" onclick="loadBorrowRequests(${pagination.current_page - 1})">Previous</button>`;
            }
            
            // Page numbers
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === pagination.current_page ? 'active' : '';
                html += `<button class="pagination-number ${activeClass}" onclick="loadBorrowRequests(${i})">${i}</button>`;
            }
            
            // Next button
            if (pagination.current_page < pagination.total_pages) {
                html += `<button class="pagination-btn" onclick="loadBorrowRequests(${pagination.current_page + 1})">Next</button>`;
            }
            
            html += '</div>';
            html += `<div style="text-align: center; margin-top: 0.5rem; font-size: 0.9rem; color: #666;">
                Showing ${((pagination.current_page - 1) * pagination.limit) + 1} to ${Math.min(pagination.current_page * pagination.limit, pagination.total_requests)} of ${pagination.total_requests} requests
            </div>`;
            
            paginationDiv.innerHTML = html;
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
                    <button class="action-btn small approve" onclick="openApprovalModal(${requestId})">Approve</button>
                    <button class="action-btn small reject" onclick="rejectRequest(${requestId})">Reject</button>
                `;
            } else {
                return `<button class="action-btn small view" onclick="viewRequest(${requestId})">View</button>`;
            }
        }
        
        function openApprovalModal(requestId) {
            openItemSelectionModal(requestId);
        }
        
        function approveRequest(requestId) {
            if (confirm('Are you sure you want to approve this borrow request?')) {
                updateRequestStatus(requestId, 'approved');
            }
        }
        
        function rejectRequest(requestId) {
            if (confirm('Are you sure you want to reject this borrow request?')) {
                updateRequestStatus(requestId, 'rejected');
            }
        }
        
        function updateRequestStatus(requestId, status) {
            fetch('update_borrowing_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadBorrowRequests(); // Reload the borrow requests list
                    
                    // If the request was approved, also refresh inventory to show updated status
                    if (status === 'approved') {
                        loadInventoryItems(); // Refresh inventory to show items as "borrowed"
                    }
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error updating request:', error);
                alert('Error updating request. Please try again.');
            });
        }
        
        function viewRequest(requestId) {
            // Here you would show detailed view of the request
            alert('Viewing request details for ID: ' + requestId);
        }

        // Returns Functions
        function openReturns() {
            // Show the returns modal
            const modal = document.getElementById('returnsModal');
            modal.style.display = 'flex';
            loadReturnRequests();
        }

        function closeReturnsModal() {
            const modal = document.getElementById('returnsModal');
            modal.style.display = 'none';
        }

        function loadReturnRequests(page = 1) {
            const loadingDiv = document.getElementById('returnsLoading');
            const contentDiv = document.getElementById('returnsContent');
            const tableBody = document.getElementById('returnRequestsTableBody');
            
            loadingDiv.style.display = 'block';
            contentDiv.style.display = 'none';
            
            // Get filter values
            const status = document.getElementById('statusReturnFilter').value;
            const search = document.getElementById('searchReturnFilter').value;
            
            // API call to fetch return requests
            fetch('get_return_requests.php?' + new URLSearchParams({
                page: page,
                status: status,
                search: search
            }))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReturnRequests(data.requests);
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                } else {
                    tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #666; padding: 2rem;">Error loading return requests: ${data.message}</td></tr>`;
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #666; padding: 2rem;">Error loading return requests</td></tr>`;
                loadingDiv.style.display = 'none';
                contentDiv.style.display = 'block';
            });
        }

        function displayReturnRequests(requests) {
            const tableBody = document.getElementById('returnRequestsTableBody');
            
            if (!requests || requests.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #666; padding: 2rem;">No return requests found</td></tr>`;
                return;
            }

            let html = '';
            requests.forEach(request => {
                const statusBadge = getReturnRequestStatusBadge(request.status);
                const actions = getReturnRequestActions(request.id, request.status);
                
                html += `
                    <tr>
                        <td>${request.student_name}</td>
                        <td>${request.item_name}</td>
                        <td>${formatDate(request.requested_at)}</td>
                        <td>${request.condition_notes || '-'}</td>
                        <td>${statusBadge}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            });
            
            tableBody.innerHTML = html;
        }

        let returnSearchTimeout;
        function debounceReturnSearch() {
            clearTimeout(returnSearchTimeout);
            returnSearchTimeout = setTimeout(() => {
                loadReturnRequests();
            }, 500);
        }
        
        function getReturnRequestStatusBadge(status) {
            const badges = {
                'pending': '<span class="status-badge pending">Pending</span>',
                'completed': '<span class="status-badge approved">Completed</span>'
            };
            return badges[status] || status;
        }
        
        function getReturnRequestActions(requestId, status) {
            if (status === 'pending') {
                return `
                    <button class="action-btn small approve" onclick="confirmReturn(${requestId})">Confirm Return</button>
                    <button class="action-btn small view" onclick="viewReturnRequest(${requestId})">View Details</button>
                `;
            } else {
                return `
                    <button class="action-btn small view" onclick="viewReturnRequest(${requestId})">View Details</button>
                `;
            }
        }
        
        function confirmReturn(requestId) {
            if (confirm('Are you sure you want to confirm this return? This will mark the items as returned and available.')) {
                fetch('process_return_confirmation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        action: 'confirm_return'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Return confirmed successfully!');
                        loadReturnRequests(); // Refresh the return requests list
                        loadInventoryItems(); // Refresh the inventory to show updated status
                    } else {
                        alert('Error confirming return: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error confirming return: ' + error.message);
                });
            }
        }
        
        function viewReturnRequest(requestId) {
            // TODO: Implement view return request details
            alert('View return request details for ID: ' + requestId);
        }

        function getReturnRequestActions(requestId, status) {
            if (status === 'pending') {
                return `
                    <button class="action-btn small confirm" onclick="confirmReturn(${requestId})" style="background: #10b981; color: white; margin-right: 0.5rem;">
                        Confirm
                    </button>
                    <button class="action-btn small view" onclick="viewReturnRequest(${requestId})">
                        View
                    </button>
                `;
            } else {
                return `<button class="action-btn small view" onclick="viewReturnRequest(${requestId})">View</button>`;
            }
        }

        function getReturnRequestStatusBadge(status) {
            const statusConfig = {
                'pending': { text: 'Pending', color: '#f59e0b', bg: '#fef3c7' },
                'confirmed': { text: 'Confirmed', color: '#10b981', bg: '#d1fae5' },
                'cancelled': { text: 'Cancelled', color: '#ef4444', bg: '#fee2e2' }
            };
            
            const config = statusConfig[status] || { text: status, color: '#6b7280', bg: '#f3f4f6' };
            
            return `
                <span style="
                    display: inline-block;
                    padding: 0.25rem 0.5rem;
                    border-radius: 0.375rem;
                    font-size: 0.75rem;
                    font-weight: 500;
                    color: ${config.color};
                    background-color: ${config.bg};
                ">
                    ${config.text}
                </span>
            `;
        }

        // Add Item Functions
        function openAddItemModal() {
            const modal = document.getElementById('addItemModal');
            modal.style.display = 'flex';
            // Reset form
            document.getElementById('addItemForm').reset();
        }

        function closeAddItemModal() {
            const modal = document.getElementById('addItemModal');
            modal.style.display = 'none';
        }

        // Handle Add Item Form Submission
        document.addEventListener('DOMContentLoaded', function() {
            const addItemForm = document.getElementById('addItemForm');
            if (addItemForm) {
                addItemForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const itemData = {
                        name: formData.get('name'),
                        category: formData.get('category'),
                        condition: formData.get('condition'),
                        description: formData.get('description'),
                        status: 'available' // Automatically set to available
                    };
                    
                    // Send data to backend
                    fetch('save_inventory_item.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(itemData)
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers.get('content-type'));
                        
                        // Check if response is actually JSON
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            return response.text().then(text => {
                                throw new Error('Server returned non-JSON response: ' + text);
                            });
                        }
                        
                        return response.json();
                    })
                    .then(data => {
                        console.log('Parsed data:', data);
                        if (data.success) {
                            alert('Item added successfully! Status automatically set to "Available".');
                            closeAddItemModal();
                            loadInventoryItems(); // Reload the tables
                        } else {
                            alert('Error adding item: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Full error:', error);
                        alert('Error adding item: ' + error.message);
                    });
                });
            }
        });

        function loadInventoryItems() {
            // Load costumes and equipment from the database
            console.log('Loading inventory items...');
            
            fetch('get_inventory_items.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCostumes(data.costumes);
                        displayEquipment(data.equipment);
                    } else {
                        console.error('Error loading inventory:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading inventory:', error);
                });
        }

        function displayCostumes(costumes) {
            const tableBody = document.getElementById('costumesTableBody');
            if (!costumes || costumes.length === 0) {
                tableBody.innerHTML = '<div class="empty-state" style="padding: 2rem; text-align: center; color: #666;"><p>No costumes found.</p><small>Click "Add Costume" to get started.</small></div>';
                return;
            }
            
            let html = '';
            costumes.forEach(costume => {
                html += '<div class="table-row" style="display: grid; grid-template-columns: 1fr 120px 120px; padding: 1rem; border-bottom: 1px solid #e0e0e0; align-items: center;">';
                html += '<div style="padding: 0 0.5rem;">' + costume.name + '</div>';
                html += '<div style="padding: 0 0.5rem;">' + getConditionBadge(costume.condition_status) + '</div>';
                html += '<div style="padding: 0 0.5rem;">' + getInventoryStatusBadge(costume.status) + '</div>';
                html += '</div>';
            });
            tableBody.innerHTML = html;
        }

        function displayEquipment(equipment) {
            const tableBody = document.getElementById('equipmentTableBody');
            if (!equipment || equipment.length === 0) {
                tableBody.innerHTML = '<div class="empty-state" style="padding: 2rem; text-align: center; color: #666;"><p>No equipment found.</p><small>Click "Add Costume" to get started.</small></div>';
                return;
            }
            
            let html = '';
            equipment.forEach(item => {
                html += '<div class="table-row" style="display: grid; grid-template-columns: 1fr 120px 120px; padding: 1rem; border-bottom: 1px solid #e0e0e0; align-items: center;">';
                html += '<div style="padding: 0 0.5rem;">' + item.name + '</div>';
                html += '<div style="padding: 0 0.5rem;">' + getConditionBadge(item.condition_status) + '</div>';
                html += '<div style="padding: 0 0.5rem;">' + getInventoryStatusBadge(item.status) + '</div>';
                html += '</div>';
            });
            tableBody.innerHTML = html;
        }

        function getConditionBadge(condition) {
            const badges = {
                'good': '<span style="background: #28a745; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">Good</span>',
                'worn-out': '<span style="background: #dc3545; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">Worn-out</span>',
                'bad': '<span style="background: #dc3545; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">Bad</span>'
            };
            return badges[condition] || condition;
        }

        function getInventoryStatusBadge(status) {
            const badges = {
                'available': '<span style="background: #28a745; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">Available</span>',
                'borrowed': '<span style="background: #6c757d; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">Borrowed</span>'
            };
            return badges[status] || status;
        }

        // Load inventory items when costume inventory section is activated
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const sectionId = this.dataset.section;
                    if (sectionId === 'costume-inventory') {
                        // Load inventory items when costume inventory section is activated
                        setTimeout(() => {
                            loadInventoryItems();
                        }, 100);
                    }
                });
            });

            // Load inventory if costume-inventory is the initial active section
            const urlParams = new URLSearchParams(window.location.search);
            const activeSection = urlParams.get('section');
            if (activeSection === 'costume-inventory') {
                setTimeout(() => {
                    loadInventoryItems();
                }, 100);
            }
        });

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

                html += '<div style="border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; background: white;">';
                html += '<div style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: start;">';
                html += '<div>';
                html += '<h4 style="margin: 0 0 0.5rem 0; color: #333; font-size: 1.2rem;">' + event.title + '</h4>';
                html += '<p style="margin: 0 0 0.75rem 0; color: #666; line-height: 1.4;">' + event.description + '</p>';
                html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; font-size: 0.9rem;">';
                html += '<div><strong>Date:</strong> ' + event.start_date_formatted + ' - ' + event.end_date_formatted + '</div>';
                html += '<div><strong>Location:</strong> ' + event.location + '</div>';
                html += '<div><strong>Category:</strong> ' + event.category + '</div>';
                if (event.campus) {
                    html += '<div><strong>Campus:</strong> ' + event.campus + '</div>';
                }
                html += '<div><strong>Cultural Groups:</strong> ' + culturalGroups + '</div>';
                html += '<div><strong>Created:</strong> ' + event.created_at_formatted + '</div>';
                html += '</div>';
                html += '</div>';
                html += '<div style="text-align: right;">';
                html += '<div style="display: inline-block; background: ' + statusColor + '; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; margin-bottom: 0.5rem;">';
                html += event.event_status;
                html += '</div>';
                if (event.days_difference >= 0) {
                    html += '<div style="font-size: 0.8rem; color: #666;">In ' + event.days_difference + ' day(s)</div>';
                } else {
                    html += '<div style="font-size: 0.8rem; color: #666;">' + Math.abs(event.days_difference) + ' day(s) ago</div>';
                }
                html += '<div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">';
                html += '<button onclick="editEvent(' + event.id + ')" style="background: #6c757d; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Edit</button>';
                html += '<button onclick="deleteEvent(' + event.id + ', \'' + event.title.replace(/'/g, "\\'") + '\')" style="background: #dc3545; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Delete</button>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
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
                html += '<button onclick="loadAllEvents(' + (pagination.current_page - 1) + ')" style="padding: 0.5rem 1rem; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Previous</button>';
            }
            
            // Page numbers
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const isActive = i === pagination.current_page;
                const bgColor = isActive ? '#dc2626' : 'white';
                const textColor = isActive ? 'white' : '#333';
                const borderColor = isActive ? '#dc2626' : '#ddd';
                html += '<button onclick="loadAllEvents(' + i + ')" style="padding: 0.5rem 0.75rem; border: 1px solid ' + borderColor + '; background: ' + bgColor + '; color: ' + textColor + '; border-radius: 4px; cursor: pointer;">' + i + '</button>';
            }
            
            // Next button
            if (pagination.current_page < pagination.total_pages) {
                html += '<button onclick="loadAllEvents(' + (pagination.current_page + 1) + ')" style="padding: 0.5rem 1rem; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Next</button>';
            }
            
            html += '</div>';
            html += '<div style="text-align: center; margin-top: 0.5rem; font-size: 0.9rem; color: #666;">Showing ' + pagination.total_events + ' total events</div>';
            
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
            if (!confirm('Are you sure you want to delete the event "' + eventTitle + '"? This action cannot be undone.')) {
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

        // Item Selection Modal Functions
        let currentRequestId = null;
        let availableItems = { costumes: [], equipment: [] };
        let selectedItems = [];

        function openItemSelectionModal(requestId) {
            currentRequestId = requestId;
            const modal = document.getElementById('itemSelectionModal');
            modal.style.display = 'flex';
            loadAvailableItems();
        }

        function closeItemSelectionModal() {
            const modal = document.getElementById('itemSelectionModal');
            modal.style.display = 'none';
            currentRequestId = null;
            selectedItems = [];
            clearItemSelection();
        }

        function loadAvailableItems() {
            const search = document.getElementById('itemSearchInput').value;
            const category = document.getElementById('categoryFilterSelect').value;
            
            let url = 'get_available_items.php';
            const params = new URLSearchParams();
            
            if (search) params.append('search', search);
            if (category) params.append('category', category);
            
            if (params.toString()) {
                url += '?' + params.toString();
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        availableItems = {
                            costumes: data.costumes,
                            equipment: data.equipment
                        };
                        displayAvailableItems();
                    } else {
                        console.error('Error loading available items:', data.message);
                        displayError('Error loading items: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayError('Error loading items: ' + error.message);
                });
        }

        function displayAvailableItems() {
            displayCostumeItems();
            displayEquipmentItems();
        }

        function displayCostumeItems() {
            const tbody = document.getElementById('availableCostumesBody');
            
            if (availableItems.costumes.length === 0) {
                tbody.innerHTML = `
                    <div class="empty-state" style="padding: 2rem; text-align: center; color: #666;">
                        <p>No available costumes found.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            availableItems.costumes.forEach(costume => {
                const isSelected = selectedItems.some(item => item.id === costume.id && item.type === 'costume');
                
                html += `
                    <div class="table-row" style="display: grid; grid-template-columns: 40px 1fr 120px 120px; padding: 0.75rem; border-bottom: 1px solid #e0e0e0; align-items: center;">
                        <div style="padding: 0 0.5rem;">
                            <input type="checkbox" value="${costume.id}" 
                                   ${isSelected ? 'checked' : ''}
                                   onchange="toggleItemSelection('costume', ${costume.id}, '${costume.name}', this.checked)">
                        </div>
                        <div style="padding: 0 0.5rem; font-weight: 500;">
                            ${costume.name}
                        </div>
                        <div style="padding: 0 0.5rem;">
                            <span class="condition-badge condition-${costume.condition}">${costume.condition}</span>
                        </div>
                        <div style="padding: 0 0.5rem;">
                            <span class="status-badge status-${costume.status}">${costume.status}</span>
                        </div>
                    </div>
                `;
            });

            tbody.innerHTML = html;
        }

        function displayEquipmentItems() {
            const tbody = document.getElementById('availableEquipmentBody');
            
            if (availableItems.equipment.length === 0) {
                tbody.innerHTML = `
                    <div class="empty-state" style="padding: 2rem; text-align: center; color: #666;">
                        <p>No available equipment found.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            availableItems.equipment.forEach(equipment => {
                const isSelected = selectedItems.some(item => item.id === equipment.id && item.type === 'equipment');
                
                html += `
                    <div class="table-row" style="display: grid; grid-template-columns: 40px 1fr 120px 120px; padding: 0.75rem; border-bottom: 1px solid #e0e0e0; align-items: center;">
                        <div style="padding: 0 0.5rem;">
                            <input type="checkbox" value="${equipment.id}" 
                                   ${isSelected ? 'checked' : ''}
                                   onchange="toggleItemSelection('equipment', ${equipment.id}, '${equipment.name}', this.checked)">
                        </div>
                        <div style="padding: 0 0.5rem; font-weight: 500;">
                            ${equipment.name}
                        </div>
                        <div style="padding: 0 0.5rem;">
                            <span class="condition-badge condition-${equipment.condition}">${equipment.condition}</span>
                        </div>
                        <div style="padding: 0 0.5rem;">
                            <span class="status-badge status-${equipment.status}">${equipment.status}</span>
                        </div>
                    </div>
                `;
            });

            tbody.innerHTML = html;
        }

        function displayError(message) {
            const costumeBody = document.getElementById('availableCostumesBody');
            const equipmentBody = document.getElementById('availableEquipmentBody');
            
            const errorHtml = `
                <div class="empty-state" style="padding: 2rem; text-align: center; color: #dc2626;">
                    <p>${message}</p>
                </div>
            `;
            
            costumeBody.innerHTML = errorHtml;
            equipmentBody.innerHTML = errorHtml;
        }

        function toggleItemSelection(type, id, name, isChecked) {
            if (isChecked) {
                selectedItems.push({ type, id, name });
            } else {
                selectedItems = selectedItems.filter(item => !(item.type === type && item.id === id));
            }
            
            updateSelectionCounts();
            updateApproveButton();
        }

        function toggleSelectAllCostumes() {
            const selectAll = document.getElementById('selectAllCostumes');
            const checkboxes = document.querySelectorAll('#availableCostumesBody input[type="checkbox"]');
            
            checkboxes.forEach(checkbox => {
                const id = parseInt(checkbox.value);
                const costume = availableItems.costumes.find(c => c.id === id);
                
                if (costume) {
                    checkbox.checked = selectAll.checked;
                    toggleItemSelection('costume', id, costume.name, selectAll.checked);
                }
            });
        }

        function toggleSelectAllEquipment() {
            const selectAll = document.getElementById('selectAllEquipment');
            const checkboxes = document.querySelectorAll('#availableEquipmentBody input[type="checkbox"]');
            
            checkboxes.forEach(checkbox => {
                const id = parseInt(checkbox.value);
                const equipment = availableItems.equipment.find(e => e.id === id);
                
                if (equipment) {
                    checkbox.checked = selectAll.checked;
                    toggleItemSelection('equipment', id, equipment.name, selectAll.checked);
                }
            });
        }

        function updateSelectionCounts() {
            const costumeCount = selectedItems.filter(item => item.type === 'costume').length;
            const equipmentCount = selectedItems.filter(item => item.type === 'equipment').length;
            
            document.getElementById('selectedCostumesCount').textContent = `${costumeCount} selected`;
            document.getElementById('selectedEquipmentCount').textContent = `${equipmentCount} selected`;
        }

        function updateApproveButton() {
            const button = document.getElementById('approveButton');
            const totalSelected = selectedItems.length;
            
            if (totalSelected > 0) {
                button.disabled = false;
                button.textContent = `Approve Request (${totalSelected} items)`;
            } else {
                button.disabled = true;
                button.textContent = 'Approve Request (0 items)';
            }
        }

        function clearItemSelection() {
            selectedItems = [];
            updateSelectionCounts();
            updateApproveButton();
            
            // Clear checkboxes
            document.querySelectorAll('#itemSelectionModal input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
        }

        function searchAvailableItems() {
            loadAvailableItems();
        }

        function filterItemsByCategory() {
            loadAvailableItems();
        }

        function approveWithSelectedItems() {
            if (selectedItems.length === 0) {
                alert('Please select at least one item to approve.');
                return;
            }

            if (!currentRequestId) {
                alert('Error: No request selected.');
                return;
            }

            const confirmMessage = `Are you sure you want to approve this request with ${selectedItems.length} selected items?`;
            if (!confirm(confirmMessage)) {
                return;
            }

            // Send approval with selected items
            fetch('update_borrowing_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: currentRequestId,
                    action: 'approve',
                    selected_items: selectedItems
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert('Request approved successfully!');
                        closeItemSelectionModal();
                        loadBorrowRequests(); // Refresh the requests list
                    } else {
                        const errorMessage = data.message || data.error || 'Unknown error occurred';
                        alert('Error approving request: ' + errorMessage);
                    }
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    alert('Error: Invalid response from server');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error approving request: ' + error.message);
            });
        }

        // Load event participation data
        function loadEventParticipation() {
            console.log('Loading event participation data...');
            const loadingDiv = document.getElementById('participationLoading');
            const tableContainer = document.getElementById('participationTableContainer');
            
            loadingDiv.style.display = 'block';
            tableContainer.style.display = 'none';
            
            fetch('get_event_participation.php')
                .then(response => {
                    console.log('Fetch response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Event participation data received:', data);
                    loadingDiv.style.display = 'none';
                    
                    if (data.success) {
                        console.log('Success! Events count:', data.events.length);
                        displayEventParticipation(data.events);
                    } else {
                        console.error('API returned error:', data.message);
                        showParticipationError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading event participation:', error);
                    loadingDiv.style.display = 'none';
                    showParticipationError('Error loading event participation: ' + error.message);
                });
        }

        // Display event participation table
        function displayEventParticipation(events) {
            const tableContainer = document.getElementById('participationTableContainer');
            
            if (events.length === 0) {
                tableContainer.innerHTML = `
                    <div style="text-align: center; padding: 3rem; color: #666;">
                        <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No events found</p>
                        <small>Events will appear here once created</small>
                    </div>
                `;
            } else {
                let html = `
                    <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <thead style="background: #dc2626; color: white;">
                            <tr>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; font-style: normal;">Event Details</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; font-style: normal;">Date & Location</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; font-style: normal;">Cultural Groups</th>
                                <th style="padding: 1rem; text-align: center; font-weight: 600; font-style: normal;">Participants</th>
                                <th style="padding: 1rem; text-align: center; font-weight: 600; font-style: normal;">Status</th>
                                <th style="padding: 1rem; text-align: center; font-weight: 600; font-style: normal;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                events.forEach(event => {
                    const dateRange = event.is_multi_day 
                        ? `${event.formatted_start_date} - ${event.formatted_end_date}`
                        : event.formatted_start_date;
                    
                    const statusBadge = getEventStatusBadge(event.date_status);
                    const culturalGroups = event.cultural_groups_array.slice(0, 2).join(', ');
                    const moreGroups = event.cultural_groups_array.length > 2 ? ` +${event.cultural_groups_array.length - 2} more` : '';
                    
                    html += `
                        <tr style="border-bottom: 1px solid #e0e0e0;">
                            <td style="padding: 1rem; vertical-align: top;">
                                <div style="font-weight: 600; color: #333; margin-bottom: 0.25rem; font-style: normal;">${event.title}</div>
                                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem; font-style: normal; font-weight: 500;">${event.category}</div>
                                <div style="color: #888; font-size: 0.8rem; font-style: normal; font-weight: normal;">Created: ${event.formatted_created_date}</div>
                            </td>
                            <td style="padding: 1rem; vertical-align: top;">
                                <div style="color: #333; margin-bottom: 0.25rem; font-style: normal;">${dateRange}</div>
                                <div style="color: #666; font-size: 0.9rem; font-style: normal;">${event.location}</div>
                            </td>
                            <td style="padding: 1rem; vertical-align: top;">
                                <div style="color: #333; font-size: 0.9rem; font-style: normal;">${culturalGroups}${moreGroups}</div>
                            </td>
                            <td style="padding: 1rem; text-align: center; vertical-align: top;">
                                <span style="background: ${event.participants_count > 0 ? '#28a745' : '#6c757d'}; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; font-style: normal;">
                                    ${event.participants_count} ${event.participants_count === 1 ? 'participant' : 'participants'}
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: center; vertical-align: top;">
                                ${statusBadge}
                            </td>
                            <td style="padding: 1rem; text-align: center; vertical-align: top;">
                                <button onclick="viewEventParticipants(${event.id})" 
                                        style="background: #007bff; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">
                                    View Participants
                                </button>
                            </td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                    </table>
                `;
                tableContainer.innerHTML = html;
            }
            
            tableContainer.style.display = 'block';
            
            // Show and populate the chart
            showParticipationChart(events);
        }

        // Chart functionality
        let chartEvents = [];
        let currentChartMode = 'all';
        let selectedEventId = null;

        function showParticipationChart(events) {
            chartEvents = events;
            const chartPanel = document.getElementById('participationChartPanel');
            
            // Safety check - only show chart if panel exists
            if (!chartPanel) {
                console.warn('Chart panel not found');
                return;
            }
            
            chartPanel.style.display = 'block';
            
            // Initialize chart with all events
            updateChart();
        }

        function setActiveChartButton(mode) {
            const allEventsBtn = document.getElementById('allEventsBtn');
            const individualEventBtn = document.getElementById('individualEventBtn');
            const selectedEventDisplay = document.getElementById('selectedEventDisplay');
            
            if (!allEventsBtn || !individualEventBtn) return;
            
            currentChartMode = mode;
            
            if (mode === 'all') {
                allEventsBtn.style.background = '#dc2626';
                allEventsBtn.style.color = 'white';
                allEventsBtn.style.borderColor = '#dc2626';
                allEventsBtn.classList.add('active');
                
                individualEventBtn.style.background = 'white';
                individualEventBtn.style.color = '#333';
                individualEventBtn.style.borderColor = '#ddd';
                individualEventBtn.classList.remove('active');
                
                selectedEventDisplay.style.display = 'none';
                selectedEventId = null;
            } else {
                allEventsBtn.style.background = 'white';
                allEventsBtn.style.color = '#333';
                allEventsBtn.style.borderColor = '#ddd';
                allEventsBtn.classList.remove('active');
                
                individualEventBtn.style.background = '#dc2626';
                individualEventBtn.style.color = 'white';
                individualEventBtn.style.borderColor = '#dc2626';
                individualEventBtn.classList.add('active');
                
                selectedEventDisplay.style.display = 'inline';
            }
        }

        function openEventSelectionModal() {
            const modal = document.getElementById('eventSelectionModal');
            const eventList = document.getElementById('eventSelectionList');
            
            if (!modal || !eventList) {
                console.warn('Event selection modal not found');
                return;
            }
            
            populateEventSelectionModal();
            modal.style.display = 'flex';
        }

        function closeEventSelectionModal() {
            const modal = document.getElementById('eventSelectionModal');
            if (modal) {
                modal.style.display = 'none';
                // Clear search input when closing modal
                const searchInput = document.getElementById('eventSearchInput');
                if (searchInput) {
                    searchInput.value = '';
                }
            }
        }
        
        function filterEvents() {
            const searchInput = document.getElementById('eventSearchInput');
            if (!searchInput) return;
            
            const searchValue = searchInput.value;
            populateEventSelectionModal(searchValue);
        }
        
        function generateAllEventsAnalytics(events) {
            const analyticsContainer = document.getElementById('chartAnalyticsContent');
            if (!analyticsContainer || events.length === 0) return;
            
            // Calculate analytics
            const totalEvents = events.length;
            const totalParticipants = events.reduce((sum, event) => sum + event.participants_count, 0);
            const totalEligible = events.reduce((sum, event) => sum + getTotalCulturalGroupMembers(event), 0);
            const avgParticipationRate = totalEligible > 0 ? ((totalParticipants / totalEligible) * 100) : 0;
            
            // Find best and worst performing events
            const eventRates = events.map(event => {
                const eligible = getTotalCulturalGroupMembers(event);
                const rate = eligible > 0 ? (event.participants_count / eligible) * 100 : 0;
                return { event, rate, participants: event.participants_count };
            });
            
            const bestEvent = eventRates.reduce((prev, current) => (prev.rate > current.rate) ? prev : current);
            const worstEvent = eventRates.reduce((prev, current) => (prev.rate < current.rate) ? prev : current);
            
            // Calculate trends
            const highPerformingEvents = eventRates.filter(e => e.rate >= 70).length;
            const mediumPerformingEvents = eventRates.filter(e => e.rate >= 40 && e.rate < 70).length;
            const lowPerformingEvents = eventRates.filter(e => e.rate < 40).length;
            
            // Generate HTML
            const analyticsHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                    <!-- Overall Statistics -->
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 3px solid #28a745;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #28a745; font-size: 0.9rem; font-weight: 600;">📈 OVERALL PERFORMANCE</h5>
                        <div style="color: #333; font-size: 0.85rem; line-height: 1.4; font-style: normal;">
                            <p style="margin: 0.25rem 0;"><strong>Total Events:</strong> ${totalEvents}</p>
                            <p style="margin: 0.25rem 0;"><strong>Total Participants:</strong> ${totalParticipants.toLocaleString()}</p>
                            <p style="margin: 0.25rem 0;"><strong>Avg. Participation Rate:</strong> ${avgParticipationRate.toFixed(1)}%</p>
                        </div>
                    </div>
                    
                    <!-- Best Performance -->
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 3px solid #ffc107;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #ffc107; font-size: 0.9rem; font-weight: 600;">🏆 BEST PERFORMING</h5>
                        <div style="color: #333; font-size: 0.85rem; line-height: 1.4; font-style: normal;">
                            <p style="margin: 0.25rem 0;"><strong>${bestEvent.event.title}</strong></p>
                            <p style="margin: 0.25rem 0;">${bestEvent.rate.toFixed(1)}% participation rate</p>
                            <p style="margin: 0.25rem 0;">${bestEvent.participants} participant(s)</p>
                        </div>
                    </div>
                    
                    <!-- Performance Distribution -->
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 3px solid #6c757d;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #6c757d; font-size: 0.9rem; font-weight: 600;">📊 PERFORMANCE DISTRIBUTION</h5>
                        <div style="color: #333; font-size: 0.85rem; line-height: 1.4; font-style: normal;">
                            <p style="margin: 0.25rem 0;"><strong>High (≥70%):</strong> ${highPerformingEvents} event(s)</p>
                            <p style="margin: 0.25rem 0;"><strong>Medium (40-69%):</strong> ${mediumPerformingEvents} event(s)</p>
                            <p style="margin: 0.25rem 0;"><strong>Low (<40%):</strong> ${lowPerformingEvents} event(s)</p>
                        </div>
                    </div>
                </div>
                
                <!-- Insights & Recommendations -->
                <div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 6px; border-left: 3px solid #17a2b8;">
                    <h5 style="margin: 0 0 0.5rem 0; color: #17a2b8; font-size: 0.9rem; font-weight: 600;">💡 KEY INSIGHTS</h5>
                    <div style="color: #333; font-size: 0.85rem; line-height: 1.5; font-style: normal;">
                        ${generateInsights(eventRates, avgParticipationRate, totalEvents)}
                    </div>
                </div>
            `;
            
            analyticsContainer.innerHTML = analyticsHTML;
        }
        
        function generateIndividualEventAnalytics(event) {
            const analyticsContainer = document.getElementById('chartAnalyticsContent');
            if (!analyticsContainer) return;
            
            const totalMembers = getTotalCulturalGroupMembers(event);
            const participants = event.participants_count;
            const participationRate = totalMembers > 0 ? (participants / totalMembers) * 100 : 0;
            const remainingCapacity = totalMembers - participants;
            
            // Simulate registration progress data
            const progressData = [
                Math.round(participants * 0.2), // Week 1
                Math.round(participants * 0.4), // Week 2  
                Math.round(participants * 0.7), // Week 3
                Math.round(participants * 0.9), // Week 4
                participants // Final
            ];
            
            const finalWeekGrowth = progressData[4] - progressData[3];
            const peakGrowthWeek = progressData.reduce((maxIdx, val, idx, arr) => {
                if (idx === 0) return 0;
                const growth = val - arr[idx - 1];
                const maxGrowth = arr[maxIdx] - (maxIdx > 0 ? arr[maxIdx - 1] : 0);
                return growth > maxGrowth ? idx : maxIdx;
            }, 1);
            
            // Determine status and performance level
            let statusColor, statusText, performanceLevel;
            if (participationRate >= 80) {
                statusColor = '#28a745';
                statusText = 'Excellent Participation';
                performanceLevel = 'High';
            } else if (participationRate >= 60) {
                statusColor = '#ffc107';
                statusText = 'Good Participation';
                performanceLevel = 'Medium';
            } else if (participationRate >= 40) {
                statusColor = '#fd7e14';
                statusText = 'Moderate Participation';
                performanceLevel = 'Medium';
            } else {
                statusColor = '#dc3545';
                statusText = 'Low Participation';
                performanceLevel = 'Low';
            }
            
            const analyticsHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <!-- Event Overview -->
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 3px solid ${statusColor};">
                        <h5 style="margin: 0 0 0.5rem 0; color: ${statusColor}; font-size: 0.9rem; font-weight: 600;">🎯 EVENT OVERVIEW</h5>
                        <div style="color: #333; font-size: 0.85rem; line-height: 1.4; font-style: normal;">
                            <p style="margin: 0.25rem 0;"><strong>Status:</strong> ${statusText}</p>
                            <p style="margin: 0.25rem 0;"><strong>Participation Rate:</strong> ${participationRate.toFixed(1)}%</p>
                            <p style="margin: 0.25rem 0;"><strong>Registered:</strong> ${participants}/${totalMembers}</p>
                        </div>
                    </div>
                    
                    <!-- Registration Progress -->
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 3px solid #6f42c1;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #6f42c1; font-size: 0.9rem; font-weight: 600;">📈 REGISTRATION TRENDS</h5>
                        <div style="color: #333; font-size: 0.85rem; line-height: 1.4; font-style: normal;">
                            <p style="margin: 0.25rem 0;"><strong>Peak Growth:</strong> Week ${peakGrowthWeek + 1}</p>
                            <p style="margin: 0.25rem 0;"><strong>Final Week Growth:</strong> +${finalWeekGrowth}</p>
                            <p style="margin: 0.25rem 0;"><strong>Early Adopters:</strong> ${progressData[0]} (${((progressData[0]/participants)*100).toFixed(0)}%)</p>
                        </div>
                    </div>
                    
                    <!-- Capacity Analysis -->
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 3px solid #20c997;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #20c997; font-size: 0.9rem; font-weight: 600;">🎪 CAPACITY INSIGHTS</h5>
                        <div style="color: #333; font-size: 0.85rem; line-height: 1.4; font-style: normal;">
                            <p style="margin: 0.25rem 0;"><strong>Capacity Used:</strong> ${participationRate.toFixed(1)}%</p>
                            <p style="margin: 0.25rem 0;"><strong>Available Spots:</strong> ${remainingCapacity}</p>
                            <p style="margin: 0.25rem 0;"><strong>Performance Level:</strong> ${performanceLevel}</p>
                        </div>
                    </div>
                </div>
                
                <!-- Event-Specific Insights -->
                <div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 6px; border-left: 3px solid #e83e8c;">
                    <h5 style="margin: 0 0 0.5rem 0; color: #e83e8c; font-size: 0.9rem; font-weight: 600;">🔍 EVENT INSIGHTS</h5>
                    <div style="color: #333; font-size: 0.85rem; line-height: 1.5; font-style: normal;">
                        ${generateIndividualEventInsights(event, participationRate, progressData, remainingCapacity)}
                    </div>
                </div>
            `;
            
            analyticsContainer.innerHTML = analyticsHTML;
        }
        
        function generateInsights(eventRates, avgRate, totalEvents) {
            let insights = [];
            
            // Performance insights
            if (avgRate >= 70) {
                insights.push("🎉 <strong>Excellent overall engagement!</strong> Your events are performing well above average.");
            } else if (avgRate >= 50) {
                insights.push("👍 <strong>Good performance</strong> with room for improvement in some events.");
            } else {
                insights.push("⚠️ <strong>Low average participation.</strong> Consider reviewing event planning and promotion strategies.");
            }
            
            // Distribution insights
            const highCount = eventRates.filter(e => e.rate >= 70).length;
            const lowCount = eventRates.filter(e => e.rate < 40).length;
            
            if (highCount > totalEvents * 0.6) {
                insights.push("📈 <strong>Consistent high performance</strong> across most events indicates effective engagement strategies.");
            }
            
            if (lowCount > totalEvents * 0.3) {
                insights.push("🔍 <strong>Focus needed:</strong> Several events show low participation - consider targeted improvements.");
            }
            
            // Recommendations
            if (avgRate < 50) {
                insights.push("💡 <strong>Recommendations:</strong> Enhance promotion, adjust timing, or review event appeal to target audience.");
            }
            
            return insights.join('<br><br>');
        }
        
        function generateIndividualEventInsights(event, participationRate, progressData, remainingCapacity) {
            let insights = [];
            
            // Participation analysis
            if (participationRate >= 80) {
                insights.push("🌟 <strong>Outstanding participation!</strong> This event shows excellent community engagement.");
            } else if (participationRate >= 60) {
                insights.push("✅ <strong>Good participation rate.</strong> The event is well-received by the community.");
            } else if (participationRate >= 40) {
                insights.push("⚠️ <strong>Moderate engagement.</strong> Consider strategies to boost participation.");
            } else {
                insights.push("📉 <strong>Low participation.</strong> This event may need significant improvements or reconsideration.");
            }
            
            // Growth pattern analysis
            const finalGrowth = progressData[4] - progressData[3];
            if (finalGrowth > progressData[0]) {
                insights.push("🚀 <strong>Strong finale:</strong> Last-minute registrations exceeded early adoption, suggesting effective late-stage marketing.");
            } else if (finalGrowth < progressData[1] - progressData[0]) {
                insights.push("⏰ <strong>Early momentum:</strong> Most interest was captured early - consider extending early-bird promotions.");
            }
            
            // Capacity insights
            if (remainingCapacity > 0 && participationRate < 70) {
                insights.push(`📢 <strong>Growth opportunity:</strong> ${remainingCapacity} spots still available - consider additional promotion efforts.`);
            }
            
            return insights.join('<br><br>');
        }

        function populateEventSelectionModal(searchFilter = '') {
            const eventList = document.getElementById('eventSelectionList');
            if (!eventList) return;
            
            if (chartEvents.length === 0) {
                eventList.innerHTML = '<p style="text-align: center; color: #666; padding: 2rem;">No events available</p>';
                return;
            }
            
            // Filter events based on search input
            let filteredEvents = chartEvents;
            if (searchFilter.trim() !== '') {
                const searchLower = searchFilter.toLowerCase();
                filteredEvents = chartEvents.filter(event => {
                    return event.title.toLowerCase().includes(searchLower) ||
                           event.category.toLowerCase().includes(searchLower) ||
                           event.location.toLowerCase().includes(searchLower);
                });
            }
            
            if (filteredEvents.length === 0) {
                eventList.innerHTML = '<p style="text-align: center; color: #666; padding: 2rem;">No events match your search criteria</p>';
                return;
            }
            
            let html = '<div style="display: grid; gap: 0.5rem;">';
            filteredEvents.forEach(event => {
                const dateRange = event.is_multi_day 
                    ? `${event.formatted_start_date} - ${event.formatted_end_date}`
                    : event.formatted_start_date;
                
                const statusBadge = getEventStatusBadge(event.date_status);
                
                html += `
                    <div onclick="selectEventForChart(${event.id})" style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem; cursor: pointer; transition: all 0.2s; background: white;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                            <h4 style="margin: 0; color: #333; font-size: 1rem;">${event.title}</h4>
                            ${statusBadge}
                        </div>
                        <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">${event.category}</div>
                        <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">${dateRange}</div>
                        <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">${event.location}</div>
                        <div style="display: flex; justify-content: between; align-items: center;">
                            <span style="background: ${event.participants_count > 0 ? '#28a745' : '#6c757d'}; color: white; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                ${event.participants_count} ${event.participants_count === 1 ? 'participant' : 'participants'}
                            </span>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            eventList.innerHTML = html;
            
            // Add hover effects
            eventList.querySelectorAll('div[onclick]').forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.borderColor = '#dc2626';
                    this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                });
                item.addEventListener('mouseleave', function() {
                    this.style.borderColor = '#e0e0e0';
                    this.style.boxShadow = 'none';
                });
            });
        }

        function selectEventForChart(eventId) {
            const selectedEvent = chartEvents.find(e => e.id === eventId);
            if (!selectedEvent) return;
            
            selectedEventId = eventId;
            setActiveChartButton('individual');
            
            // Update selected event display
            const selectedEventDisplay = document.getElementById('selectedEventDisplay');
            if (selectedEventDisplay) {
                selectedEventDisplay.textContent = selectedEvent.title;
                selectedEventDisplay.style.color = '#dc2626';
                selectedEventDisplay.style.fontStyle = 'normal';
            }
            
            // Close modal and update chart
            closeEventSelectionModal();
            updateChart();
        }

        function updateChart() {
            if (currentChartMode === 'all') {
                drawAllEventsChart(chartEvents);
            } else if (currentChartMode === 'individual' && selectedEventId) {
                const selectedEvent = chartEvents.find(e => e.id === selectedEventId);
                if (selectedEvent) {
                    drawIndividualEventChart(selectedEvent);
                }
            } else {
                clearChart();
            }
        }

        function drawAllEventsChart(events) {
            const canvas = document.getElementById('participationChart');
            
            // Safety check
            if (!canvas) {
                console.warn('Chart canvas not found');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            
            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            if (events.length === 0) {
                drawEmptyChart(ctx, canvas);
                return;
            }
            
            // Chart dimensions and padding
            const padding = 60;
            const chartWidth = canvas.width - (padding * 2);
            const chartHeight = canvas.height - (padding * 2);
            const chartX = padding;
            const chartY = padding;
            
            // Prepare data points
            const dataPoints = events.map((event, index) => {
                const eligibleMembers = getTotalCulturalGroupMembers(event);
                const participationRate = eligibleMembers > 0 ? (event.participants_count / eligibleMembers) * 100 : 0;
                return {
                    x: index,
                    y: participationRate,
                    label: event.title,
                    participants: event.participants_count,
                    eligible: eligibleMembers
                };
            });
            
            if (dataPoints.length === 0) {
                drawEmptyChart(ctx, canvas);
                return;
            }
            
            // Find min and max values for scaling
            const maxY = Math.max(100, Math.max(...dataPoints.map(p => p.y)));
            const minY = 0;
            
            // Draw chart background
            ctx.fillStyle = '#f8f9fa';
            ctx.fillRect(chartX, chartY, chartWidth, chartHeight);
            
            // Draw grid lines
            ctx.strokeStyle = '#e0e0e0';
            ctx.lineWidth = 1;
            
            // Horizontal grid lines (Y-axis)
            for (let i = 0; i <= 5; i++) {
                const y = chartY + (chartHeight / 5) * i;
                ctx.beginPath();
                ctx.moveTo(chartX, y);
                ctx.lineTo(chartX + chartWidth, y);
                ctx.stroke();
                
                // Y-axis labels
                const value = maxY - (maxY / 5) * i;
                ctx.fillStyle = '#666';
                ctx.font = '12px Arial';
                ctx.textAlign = 'right';
                ctx.fillText(value.toFixed(0) + '%', chartX - 10, y + 4);
            }
            
            // Vertical grid lines (X-axis) 
            const stepX = chartWidth / Math.max(1, dataPoints.length - 1);
            for (let i = 0; i < dataPoints.length; i++) {
                const x = chartX + stepX * i;
                ctx.beginPath();
                ctx.moveTo(x, chartY);
                ctx.lineTo(x, chartY + chartHeight);
                ctx.stroke();
            }
            
            // Draw axes
            ctx.strokeStyle = '#333';
            ctx.lineWidth = 2;
            
            // Y-axis
            ctx.beginPath();
            ctx.moveTo(chartX, chartY);
            ctx.lineTo(chartX, chartY + chartHeight);
            ctx.stroke();
            
            // X-axis
            ctx.beginPath();
            ctx.moveTo(chartX, chartY + chartHeight);
            ctx.lineTo(chartX + chartWidth, chartY + chartHeight);
            ctx.stroke();
            
            // Draw line chart
            if (dataPoints.length > 1) {
                ctx.strokeStyle = '#dc2626';
                ctx.lineWidth = 3;
                ctx.beginPath();
                
                dataPoints.forEach((point, index) => {
                    const x = chartX + stepX * index;
                    const y = chartY + chartHeight - ((point.y - minY) / (maxY - minY)) * chartHeight;
                    
                    if (index === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });
                
                ctx.stroke();
            }
            
            // Draw data points
            dataPoints.forEach((point, index) => {
                const x = chartX + stepX * index;
                const y = chartY + chartHeight - ((point.y - minY) / (maxY - minY)) * chartHeight;
                
                // Draw point circle
                ctx.fillStyle = '#dc2626';
                ctx.beginPath();
                ctx.arc(x, y, 5, 0, 2 * Math.PI);
                ctx.fill();
                
                // Draw point border
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.stroke();
            });
            
            // Draw title
            ctx.fillStyle = '#333';
            ctx.font = 'bold 18px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Event Participation Trend', canvas.width / 2, 30);
            
            // Draw X-axis labels (event names)
            ctx.fillStyle = '#666';
            ctx.font = '10px Arial';
            ctx.textAlign = 'center';
            dataPoints.forEach((point, index) => {
                const x = chartX + stepX * index;
                const labelY = chartY + chartHeight + 15;
                
                // Truncate long event names
                let label = point.label;
                if (label.length > 12) {
                    label = label.substring(0, 10) + '...';
                }
                ctx.fillText(label, x, labelY);
            });
            
            // Draw Y-axis label
            ctx.save();
            ctx.translate(20, canvas.height / 2);
            ctx.rotate(-Math.PI / 2);
            ctx.fillStyle = '#666';
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Participation Rate (%)', 0, 0);
            ctx.restore();
            
            // Update legend
            const legendContainer = document.getElementById('chartLegendContainer');
            if (legendContainer) {
                const avgParticipation = dataPoints.length > 0 ? 
                    (dataPoints.reduce((sum, p) => sum + p.y, 0) / dataPoints.length).toFixed(1) : 0;
                
                legendContainer.innerHTML = `
                    <div style="display: flex; justify-content: center; gap: 2rem; margin-top: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 20px; height: 3px; background: #dc2626; border-radius: 2px;"></div>
                            <span style="font-size: 14px; color: #333;">Participation Rate Trend</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 14px; color: #333;">Average: ${avgParticipation}%</span>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 1rem; color: #666; font-size: 12px;">
                        Showing participation rates across ${events.length} event${events.length !== 1 ? 's' : ''}
                    </div>
                `;
            }
            
            // Generate analytics for all events
            generateAllEventsAnalytics(events);
        }

        function drawPieChartLegend(events, colors, totalParticipants) {
            const legendContainer = document.getElementById('chartLegendContainer');
            if (!legendContainer) return;
            
            let legendHTML = '';
            events.forEach((event, index) => {
                if (event.participants_count > 0) {
                    const color = colors[index % colors.length];
                    const percentage = ((event.participants_count / totalParticipants) * 100).toFixed(1);
                    legendHTML += `
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin: 0.25rem;">
                            <div style="width: 16px; height: 16px; background: ${color}; border-radius: 3px;"></div>
                            <span style="font-size: 12px; color: #333;">${event.title} (${event.participants_count} - ${percentage}%)</span>
                        </div>
                    `;
                }
            });
            legendContainer.innerHTML = legendHTML;
        }

        function drawIndividualEventChart(event) {
            const canvas = document.getElementById('participationChart');
            
            // Safety check
            if (!canvas) {
                console.warn('Chart canvas not found');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            
            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Chart dimensions and padding
            const padding = 60;
            const chartWidth = canvas.width - (padding * 2);
            const chartHeight = canvas.height - (padding * 2);
            const chartX = padding;
            const chartY = padding;
            
            // Get cultural groups involved in this event
            const culturalGroups = event.cultural_groups_array || ['All Groups'];
            const totalMembers = getTotalCulturalGroupMembers(event);
            const participants = event.participants_count;
            
            if (totalMembers === 0) {
                drawEmptyChart(ctx, canvas);
                return;
            }
            
            // Create simulated participation data over time (e.g., registration periods)
            // In a real scenario, this would come from historical data
            const timePoints = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Final'];
            const participationProgress = [
                Math.round(participants * 0.2), // 20% early registration
                Math.round(participants * 0.4), // 40% by week 2
                Math.round(participants * 0.7), // 70% by week 3
                Math.round(participants * 0.9), // 90% by week 4
                participants // Final count
            ];
            
            // Find max value for scaling
            const maxY = Math.max(totalMembers, Math.max(...participationProgress));
            const minY = 0;
            
            // Draw chart background
            ctx.fillStyle = '#f8f9fa';
            ctx.fillRect(chartX, chartY, chartWidth, chartHeight);
            
            // Draw grid lines
            ctx.strokeStyle = '#e0e0e0';
            ctx.lineWidth = 1;
            
            // Horizontal grid lines (Y-axis)
            for (let i = 0; i <= 5; i++) {
                const y = chartY + (chartHeight / 5) * i;
                ctx.beginPath();
                ctx.moveTo(chartX, y);
                ctx.lineTo(chartX + chartWidth, y);
                ctx.stroke();
                
                // Y-axis labels
                const value = Math.round(maxY - (maxY / 5) * i);
                ctx.fillStyle = '#666';
                ctx.font = '12px Arial';
                ctx.textAlign = 'right';
                ctx.fillText(value.toString(), chartX - 10, y + 4);
            }
            
            // Vertical grid lines (X-axis)
            const stepX = chartWidth / (timePoints.length - 1);
            for (let i = 0; i < timePoints.length; i++) {
                const x = chartX + stepX * i;
                ctx.beginPath();
                ctx.moveTo(x, chartY);
                ctx.lineTo(x, chartY + chartHeight);
                ctx.stroke();
            }
            
            // Draw axes
            ctx.strokeStyle = '#333';
            ctx.lineWidth = 2;
            
            // Y-axis
            ctx.beginPath();
            ctx.moveTo(chartX, chartY);
            ctx.lineTo(chartX, chartY + chartHeight);
            ctx.stroke();
            
            // X-axis
            ctx.beginPath();
            ctx.moveTo(chartX, chartY + chartHeight);
            ctx.lineTo(chartX + chartWidth, chartY + chartHeight);
            ctx.stroke();
            
            // Draw capacity line (total available spots)
            ctx.setLineDash([5, 5]);
            ctx.strokeStyle = '#999';
            ctx.lineWidth = 2;
            const capacityY = chartY + chartHeight - ((totalMembers - minY) / (maxY - minY)) * chartHeight;
            ctx.beginPath();
            ctx.moveTo(chartX, capacityY);
            ctx.lineTo(chartX + chartWidth, capacityY);
            ctx.stroke();
            ctx.setLineDash([]); // Reset line dash
            
            // Draw participation line
            ctx.strokeStyle = '#dc2626';
            ctx.lineWidth = 3;
            ctx.beginPath();
            
            participationProgress.forEach((count, index) => {
                const x = chartX + stepX * index;
                const y = chartY + chartHeight - ((count - minY) / (maxY - minY)) * chartHeight;
                
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            
            ctx.stroke();
            
            // Draw data points
            participationProgress.forEach((count, index) => {
                const x = chartX + stepX * index;
                const y = chartY + chartHeight - ((count - minY) / (maxY - minY)) * chartHeight;
                
                // Draw point circle
                ctx.fillStyle = '#dc2626';
                ctx.beginPath();
                ctx.arc(x, y, 5, 0, 2 * Math.PI);
                ctx.fill();
                
                // Draw point border
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.stroke();
                
                // Draw value labels on points
                ctx.fillStyle = '#333';
                ctx.font = '10px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(count.toString(), x, y - 10);
            });
            
            // Draw title
            ctx.fillStyle = '#333';
            ctx.font = 'bold 18px Arial';
            ctx.textAlign = 'center';
            ctx.fillText(event.title + ' - Registration Progress', canvas.width / 2, 30);
            
            // Draw X-axis labels
            ctx.fillStyle = '#666';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            timePoints.forEach((label, index) => {
                const x = chartX + stepX * index;
                ctx.fillText(label, x, chartY + chartHeight + 20);
            });
            
            // Draw Y-axis label
            ctx.save();
            ctx.translate(20, canvas.height / 2);
            ctx.rotate(-Math.PI / 2);
            ctx.fillStyle = '#666';
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Number of Participants', 0, 0);
            ctx.restore();
            
            // Draw capacity label
            ctx.fillStyle = '#999';
            ctx.font = '12px Arial';
            ctx.textAlign = 'left';
            ctx.fillText(`Capacity: ${totalMembers}`, chartX + chartWidth - 100, capacityY - 5);
            
            // Update legend
            const legendContainer = document.getElementById('chartLegendContainer');
            if (legendContainer) {
                const participationRate = ((participants / totalMembers) * 100).toFixed(1);
                
                legendContainer.innerHTML = `
                    <div style="display: flex; justify-content: center; gap: 2rem; margin-top: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 20px; height: 3px; background: #dc2626; border-radius: 2px;"></div>
                            <span style="font-size: 14px; color: #333;">Registration Progress</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 20px; height: 3px; background: #999; border-radius: 2px; border: 1px dashed #999;"></div>
                            <span style="font-size: 14px; color: #333;">Total Capacity</span>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 1rem; color: #666; font-size: 12px;">
                        Final Participation: ${participants} of ${totalMembers} (${participationRate}%) | 
                        Cultural Groups: ${culturalGroups.join(', ')}
                    </div>
                `;
            }
            
            // Generate analytics for individual event
            generateIndividualEventAnalytics(event);
        }

        function getTotalCulturalGroupMembers(event) {
            // This is a placeholder function
            // In a real implementation, you would fetch this data from the server
            // based on the event's cultural groups
            
            // For demonstration, we'll use a simple calculation
            // This should be replaced with actual data from your database
            
            if (!event.cultural_groups_array || event.cultural_groups_array.length === 0) {
                // If no specific groups, assume total student population
                return Math.max(event.participants_count * 3, 50); // Estimate
            }
            
            // Estimate based on cultural groups
            // You should replace this with actual queries to your database
            const estimatedMembersPerGroup = 25; // Average members per cultural group
            return event.cultural_groups_array.length * estimatedMembersPerGroup;
        }

        function drawEmptyChart(ctx, canvas) {
            ctx.fillStyle = '#666';
            ctx.font = '16px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('No events available', canvas.width / 2, canvas.height / 2);
        }

        function clearChart() {
            const canvas = document.getElementById('participationChart');
            
            // Safety check
            if (!canvas) {
                console.warn('Chart canvas not found');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            ctx.fillStyle = '#666';
            ctx.font = '16px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Select an event to view details', canvas.width / 2, canvas.height / 2);
            
            // Clear legend
            const legendContainer = document.getElementById('chartLegendContainer');
            if (legendContainer) {
                legendContainer.innerHTML = '';
            }
            
            // Clear analytics
            const analyticsContainer = document.getElementById('chartAnalyticsContent');
            if (analyticsContainer) {
                analyticsContainer.innerHTML = '<p style="color: #666; margin: 0;">Select a chart view to see detailed analytics</p>';
            }
        }


        // Get event status badge
        function getEventStatusBadge(dateStatus) {
            switch(dateStatus) {
                case 'upcoming':
                    return '<span style="background: #007bff; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; font-style: normal;">Upcoming</span>';
                case 'ongoing':
                    return '<span style="background: #28a745; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; font-style: normal;">Ongoing</span>';
                case 'completed':
                    return '<span style="background: #6c757d; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; font-style: normal;">Completed</span>';
                default:
                    return '<span style="background: #dc2626; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; font-style: normal;">Unknown</span>';
            }
        }

        // Show participation error
        function showParticipationError(message) {
            const tableContainer = document.getElementById('participationTableContainer');
            tableContainer.innerHTML = `
                <div style="text-align: center; padding: 3rem; color: #dc2626;">
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Error loading participation data</p>
                    <small>${message}</small>
                </div>
            `;
            tableContainer.style.display = 'block';
        }

        // View event participants
        function viewEventParticipants(eventId) {
            const modal = document.getElementById('eventParticipantsModal');
            const loadingDiv = document.getElementById('participantsLoading');
            const contentDiv = document.getElementById('participantsContent');
            
            // Clear previous content immediately
            document.getElementById('participantsModalTitle').textContent = 'Loading...';
            document.getElementById('eventDetailsContent').innerHTML = '';
            document.getElementById('participantsTableBody').innerHTML = '';
            document.getElementById('participantsCount').textContent = '0 participants';
            
            // Hide content and show loading
            modal.classList.add('show');
            modal.style.display = 'flex';
            loadingDiv.style.display = 'block';
            contentDiv.style.display = 'none';
            
            fetch(`get_event_participants.php?event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    loadingDiv.style.display = 'none';
                    
                    if (data.success) {
                        displayEventParticipants(data.event, data.participants);
                        contentDiv.style.display = 'block';
                    } else {
                        showParticipantsError(data.message);
                    }
                })
                .catch(error => {
                    loadingDiv.style.display = 'none';
                    showParticipantsError('Error loading participants: ' + error.message);
                });
        }

        // Display event participants
        function displayEventParticipants(event, participants) {
            // Update modal title and event details
            document.getElementById('participantsModalTitle').textContent = `Participants - ${event.title}`;
            
            const dateRange = event.is_multi_day 
                ? `${event.formatted_start_date} - ${event.formatted_end_date}`
                : event.formatted_start_date;
            
            document.getElementById('eventDetailsContent').innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <strong style="color: #333;">Event:</strong>
                        <div style="color: #666;">${event.title}</div>
                    </div>
                    <div>
                        <strong style="color: #333;">Date:</strong>
                        <div style="color: #666;">${dateRange}</div>
                    </div>
                    <div>
                        <strong style="color: #333;">Location:</strong>
                        <div style="color: #666;">${event.location}</div>
                    </div>
                    <div>
                        <strong style="color: #333;">Category:</strong>
                        <div style="color: #666;">${event.category}</div>
                    </div>
                </div>
            `;
            
            // Update participants count
            document.getElementById('participantsCount').textContent = `${participants.length} ${participants.length === 1 ? 'participant' : 'participants'}`;
            
            const tableBody = document.getElementById('participantsTableBody');
            const noParticipantsMsg = document.getElementById('noParticipantsMessage');
            const participantsSummary = document.querySelector('.participants-summary');
            const tableContainer = document.querySelector('.table-container');
            
            // Clear table body first
            tableBody.innerHTML = '';
            
            if (participants.length === 0) {
                participantsSummary.style.display = 'none';
                tableContainer.style.display = 'none';
                noParticipantsMsg.style.display = 'block';
            } else {
                participantsSummary.style.display = 'flex';
                tableContainer.style.display = 'block';
                noParticipantsMsg.style.display = 'none';
                
                let html = '';
                participants.forEach((participant, index) => {
                    const academicInfo = [participant.program, participant.year_level, participant.campus].filter(Boolean).join(' • ');
                    
                    html += `
                        <tr style="border-bottom: 1px solid #e0e0e0; ${index % 2 === 0 ? 'background: #f8f9fa;' : ''}">
                            <td style="padding: 1rem; vertical-align: top;">
                                <div style="font-weight: 600; color: #333; margin-bottom: 0.25rem;">${participant.full_name}</div>
                                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">${participant.sr_code || 'N/A'}</div>
                                <div style="color: #666; font-size: 0.8rem;">${participant.email}</div>
                            </td>
                            <td style="padding: 1rem; vertical-align: top;">
                                <div style="color: #333; font-weight: 500;">${participant.cultural_group || 'Not specified'}</div>
                            </td>
                            <td style="padding: 1rem; vertical-align: top;">
                                <div style="color: #333; font-size: 0.9rem;">${academicInfo || 'Not specified'}</div>
                                ${participant.college ? `<div style="color: #666; font-size: 0.8rem;">${participant.college}</div>` : ''}
                            </td>
                            <td style="padding: 1rem; vertical-align: top;">
                                <div style="color: #333; font-size: 0.9rem;">${participant.formatted_joined_date}</div>
                            </td>
                        </tr>
                    `;
                });
                
                tableBody.innerHTML = html;
            }
        }

        // Show participants error
        function showParticipantsError(message) {
            document.getElementById('participantsLoading').style.display = 'none';
            document.getElementById('participantsContent').innerHTML = `
                <div style="text-align: center; padding: 3rem; color: #dc2626;">
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Error loading participants</p>
                    <small>${message}</small>
                </div>
            `;
            document.getElementById('participantsContent').style.display = 'block';
        }

        // Close participants modal
        function closeParticipantsModal() {
            const modal = document.getElementById('eventParticipantsModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
            
            // Clear modal content
            document.getElementById('participantsModalTitle').textContent = 'Event Participants';
            document.getElementById('eventDetailsContent').innerHTML = '';
            document.getElementById('participantsTableBody').innerHTML = '';
            document.getElementById('participantsCount').textContent = '0 participants';
            
            // Reset display states
            document.getElementById('participantsLoading').style.display = 'none';
            document.getElementById('participantsContent').style.display = 'none';
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('eventParticipantsModal');
            if (event.target === modal) {
                closeParticipantsModal();
            }
            
            // Close event selection modal when clicking outside
            const eventSelectionModal = document.getElementById('eventSelectionModal');
            if (event.target === eventSelectionModal) {
                closeEventSelectionModal();
            }
        });
    </script>
</body>
</html>

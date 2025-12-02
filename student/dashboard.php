<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$pdo = getDBConnection();

// Get student information
$student_id = $_SESSION['user_id'];
$student_info = null;

try {
    // Check which table the user comes from
    $user_table = $_SESSION['user_table'] ?? 'users';
    
    if ($user_table === 'student_artists') {
        // User is from student_artists table
        $stmt = $pdo->prepare("SELECT * FROM student_artists WHERE id = ?");
        $stmt->execute([$student_id]);
        $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // User is from users table, try to find corresponding record in student_artists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_info) {
            // Try to find matching student record by email
            $stmt = $pdo->prepare("SELECT * FROM student_artists WHERE email = ?");
            $stmt->execute([$user_info['email']]);
            $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no matching record found, create a basic student info from users table
            if (!$student_info) {
                $student_info = [
                    'id' => $user_info['id'],
                    'first_name' => $user_info['first_name'],
                    'middle_name' => $user_info['middle_name'],
                    'last_name' => $user_info['last_name'],
                    'email' => $user_info['email'],
                    'sr_code' => null,
                    'campus' => null,
                    'college' => null,
                    'program' => null,
                    'year_level' => null,
                    'contact_number' => null,
                    'address' => null,
                    'status' => $user_info['status']
                ];
            }
        }
    }
    
    if (!$student_info) {
        // Try to get from applications table if not in student_artists
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ? AND status = 'approved'");
        $stmt->execute([$student_id]);
        $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching student info: " . $e->getMessage());
}

// Get dashboard statistics for student
try {
    // Get the actual student ID for queries (handle both user tables)
    $actual_student_id = $student_id;
    $user_email = null;
    
    // Get user email for cross-table lookups
    if ($user_table === 'student_artists') {
        $stmt = $pdo->prepare("SELECT email FROM student_artists WHERE id = ?");
        $stmt->execute([$student_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_email = $user_data['email'] ?? null;
    } else {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_email = $user_data['email'] ?? null;
    }
    
    // Count upcoming events (events this month)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM events 
        WHERE status = 'active' 
        AND start_date >= CURDATE() 
        AND MONTH(start_date) = MONTH(CURDATE()) 
        AND YEAR(start_date) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $upcoming_events = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Count currently borrowed costumes for this student
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM borrowing_requests 
        WHERE student_id = ? 
        AND status IN ('approved') 
        AND current_status IN ('active', 'pending_return')
    ");
    $stmt->execute([$student_id]);
    $borrowed_costumes = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Count total performances/events this student has participated in
    // Try both student_id and email-based lookup
    $performance_count = 0;
    
    // First try with current student_id
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM event_participants ep
        JOIN events e ON ep.event_id = e.id
        WHERE ep.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $performance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // If no results and we have email, try to find by email match
    if ($performance_count == 0 && $user_email) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM event_participants ep
            JOIN events e ON ep.event_id = e.id
            JOIN student_artists sa ON ep.student_id = sa.id
            WHERE sa.email = ?
        ");
        $stmt->execute([$user_email]);
        $performance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }
    
    $total_performances = $performance_count;
    
    // Count announcements (including events as announcements - matching get_announcements.php logic)
    // Get student's cultural group and campus for filtering
    $studentStmt = $pdo->prepare("SELECT cultural_group, campus FROM student_artists WHERE id = ?");
    $studentStmt->execute([$_SESSION['user_id']]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    
    $total_announcements = 0;
    
    if ($student) {
        $studentCulturalGroup = $student['cultural_group'];
        $studentCampus = $student['campus'];
        
        // Count regular announcements (active, published, not expired)
        $announcementsStmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM announcements a
            WHERE (
                (a.target_cultural_group LIKE ? OR a.target_cultural_group LIKE ? OR a.target_cultural_group = 'all')
                OR (a.target_audience = 'all' OR a.target_audience = 'students')
            )
            AND (a.target_campus = 'all' OR a.target_campus = ? OR a.target_campus IS NULL)
            AND a.is_active = 1
            AND a.is_published = 1
            AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
        ");
        
        $groupPattern1 = '%"' . $studentCulturalGroup . '"%';
        $groupPattern2 = '%' . $studentCulturalGroup . '%';
        
        $announcementsStmt->execute([$groupPattern1, $groupPattern2, $studentCampus]);
        $announcementCount = $announcementsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Count event announcements (upcoming and ongoing events)
        $eventsStmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM events
            WHERE (cultural_groups LIKE ? OR cultural_groups LIKE ? OR cultural_groups = '[]')
            AND (venue = ? OR venue IS NULL OR venue = '')
            AND end_date >= CURDATE()
            AND status IN ('published', 'ongoing')
        ");
        
        $eventsStmt->execute([$groupPattern1, $groupPattern2, $studentCampus]);
        $eventCount = $eventsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $total_announcements = $announcementCount + $eventCount;
    }
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $upcoming_events = 0;
    $borrowed_costumes = 0;
    $total_performances = 0;
    $total_announcements = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Culture and Arts</title>
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
            line-height: 1.6;
        }

        /* Prevent background scrolling when modals are open */
        body.modal-open {
            overflow: hidden;
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
            position: fixed;
            top: 70px;
            left: 0;
            height: calc(100vh - 70px);
            overflow-y: auto;
            z-index: 90;
        }

        .nav-menu {
            list-style: none;
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0;
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
            margin-left: 280px;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            color: #333;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #666;
            font-size: 1rem;
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
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-icon {
            font-size: 1.5rem;
            color: #dc2626;
        }

        .card-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 0.5rem;
        }

        .card-subtitle {
            color: #888;
            font-size: 0.9rem;
        }

        /* Content Panels */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .content-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .panel-header {
            background: #dc2626;
            color: white;
            padding: 1rem 1.5rem;
            margin: -1px -1px 0 -1px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-title {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .panel-content {
            padding: 1.5rem;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            text-align: center;
        }

        /* Upcoming Events Styles */
        .events-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .events-header {
            background: #28a745;
            color: white;
            padding: 1rem 1.5rem;
        }

        .events-list {
            padding: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .event-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .event-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .event-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .event-date {
            color: #dc2626;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .event-location {
            color: #666;
            font-size: 0.9rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #888;
        }

        /* Profile Styles */
        .profile-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .profile-role {
            opacity: 0.9;
        }

        .profile-details {
            padding: 1.5rem;
        }

        .detail-group {
            margin-bottom: 1.5rem;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            color: #666;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .dashboard-cards {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

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
                padding: 0.5rem 1rem;
            }
            
            .nav-item {
                margin: 0 0.25rem;
                white-space: nowrap;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .header {
                padding: 1rem;
            }
            
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Action Buttons */
        .action-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .action-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .action-btn.secondary {
            background: #6c757d;
        }

        .action-btn.secondary:hover {
            background: #5a6268;
        }

        /* Borrowed Costumes Styles */
        .borrowed-costumes-list {
            padding: 0;
            margin: 0;
        }

        #borrowedCostumesContent.panel-content {
            padding: 0;
            min-height: 0;
            margin: 0;
            display: block;
            align-items: unset;
            justify-content: unset;
            text-align: left;
        }

        /* Events Participated panel content styling for seamless table connection */
        #performance-record .content-panel .panel-content {
            padding: 0;
            min-height: 0;
            margin: 0;
            display: block;
            align-items: unset;
            justify-content: unset;
            text-align: left;
        }

        /* Ensure borrowed costumes panel header extends fully */
        #costume-borrowing .content-panel .panel-header {
            margin: -1px -1px 0 -1px;
        }

        .borrowed-costumes-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 0;
            overflow: hidden;
            margin: 0;
            margin-top: -1px; /* Pull table up to merge with header */
            margin-left: 0;
            margin-right: 0;
            border-left: none;
            border-right: none;
        }

        .borrowed-costumes-table thead {
            background: #dc2626;
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .borrowed-costumes-table th:first-child,
        .borrowed-costumes-table td:first-child {
            padding-left: 1.5rem;
        }

        .borrowed-costumes-table th:last-child,
        .borrowed-costumes-table td:last-child {
            padding-right: 1.5rem;
        }

        .borrowed-costumes-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .borrowed-costumes-table td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: top;
        }

        .borrowed-costumes-table tbody tr:hover {
            background: #f8f9fa;
        }

        .borrowed-costumes-table tbody tr:last-child td {
            border-bottom: none;
        }

        .item-name-cell {
            font-weight: 600;
            color: #333;
        }

        .item-category-cell {
            color: #666;
            font-size: 0.9rem;
            text-transform: capitalize;
        }

        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .item-description {
            font-size: 0.85rem;
            color: #666;
            line-height: 1.4;
        }

        .item-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-returned {
            background: #e5e7eb;
            color: #374151;
        }

        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
        }

        .date-cell {
            font-size: 0.9rem;
            color: #555;
        }

        .notes-cell {
            font-size: 0.85rem;
            color: #666;
            font-style: italic;
            max-width: 200px;
            word-wrap: break-word;
        }

        .loading-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .action-cell {
            text-align: left;
            padding: 0.5rem;
        }

        .return-btn {
            background: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .return-btn:hover {
            background: #218838;
        }

        .empty-borrowed-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #888;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .empty-borrowed-state p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .empty-borrowed-state small {
            color: #aaa;
        }

        /* Evaluation Modal Responsive Styles */
        .evaluation-question {
            transition: background-color 0.2s ease;
        }

        .evaluation-question:hover {
            background-color: #f8f9fa;
        }

        .evaluation-question input[type="radio"] {
            transform: scale(1.2);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            #eventEvaluationModal .evaluation-question {
                display: block !important;
                padding: 1rem 0.5rem;
            }
            
            #eventEvaluationModal .evaluation-question > div:first-child {
                margin-bottom: 1rem;
                font-weight: 600;
            }
            
            #eventEvaluationModal .evaluation-question > div:not(:first-child) {
                display: inline-block;
                margin: 0.25rem 0.5rem;
                text-align: left !important;
            }
            
            #eventEvaluationModal .evaluation-question input[type="radio"] {
                margin-right: 0.5rem;
            }
            
            #eventEvaluationModal > div {
                margin: 1rem;
                width: calc(100% - 2rem);
            }
            
            #eventEvaluationModal form {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <img src="../assets/OCA Logo.png" alt="BatStateU Logo" class="logo">
            <h1 class="header-title">Student Dashboard - Culture and Arts</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span>👤</span>
                <?php 
                $first_name = $student_info['first_name'] ?? 'Student';
                $campus = $student_info['campus'] ?? '';
                ?>
                <span><?= htmlspecialchars($first_name) ?></span>
                <?php if ($campus): ?>
                    <span style="background: #2196f3; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; margin-left: 10px; font-weight: 600;"><?= htmlspecialchars($campus) ?></span>
                <?php endif; ?>
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
                        <a href="#" class="nav-link" data-section="announcements">
                            Announcements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="upcoming-events">
                            Upcoming Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="performance-record">
                            Performance Record
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="costume-borrowing">
                            Costume Borrowing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="profile">
                            Profile
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
                    <p class="page-subtitle">Welcome to your Culture and Arts dashboard</p>
                </div>

                <!-- Dashboard Cards -->
                <div class="dashboard-cards">
                    <div class="dashboard-card stat-card">
                        <div class="card-header">
                            <div class="card-title">Announcements</div>
                            <div class="card-icon">📢</div>
                        </div>
                        <div class="card-number stat-number"><?= $total_announcements ?></div>
                        <div class="card-subtitle">New announcements</div>
                    </div>

                    <div class="dashboard-card stat-card">
                        <div class="card-header">
                            <div class="card-title">Upcoming Events</div>
                            <div class="card-icon">📅</div>
                        </div>
                        <div class="card-number stat-number"><?= $upcoming_events ?></div>
                        <div class="card-subtitle">Events this month</div>
                    </div>

                    <div class="dashboard-card stat-card">
                        <div class="card-header">
                            <div class="card-title">Performances</div>
                            <div class="card-icon">🎭</div>
                        </div>
                        <div class="card-number stat-number"><?= $total_performances ?></div>
                        <div class="card-subtitle">Total performances</div>
                    </div>

                    <div class="dashboard-card stat-card">
                        <div class="card-header">
                            <div class="card-title">Borrowed Costumes</div>
                            <div class="card-icon">👗</div>
                        </div>
                        <div class="card-number stat-number"><?= $borrowed_costumes ?></div>
                        <div class="card-subtitle">Currently borrowed</div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <div class="content-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Recent Announcements</h3>
                        </div>
                        <div class="panel-content">
                            <div class="empty-state">
                                <p>No recent announcements</p>
                                <small>Check back later for updates</small>
                            </div>
                        </div>
                    </div>

                    <div class="content-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Next Performance</h3>
                        </div>
                        <div class="panel-content">
                            <div class="empty-state">
                                <p>No upcoming performances</p>
                                <small>Your next performance will appear here</small>
                            </div>
                        </div>
                    </div>

                    <div class="content-panel">
                        <div class="panel-header">
                            <h3 class="panel-title">Costume Returns</h3>
                        </div>
                        <div class="panel-content">
                            <div class="empty-state">
                                <p>No costume returns due</p>
                                <small>Your borrowed costumes will appear here</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Announcements Section -->
            <section class="content-section" id="announcements">
                <div class="page-header">
                    <h1 class="page-title">Announcements</h1>
                    <p class="page-subtitle">Stay updated with the latest news and announcements</p>
                </div>

                <div class="content-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">All Announcements</h3>
                    </div>
                    <div class="panel-content">
                        <div class="empty-state">
                            <p>No announcements available</p>
                            <small>Announcements will appear here when posted</small>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Upcoming Events Section -->
            <section class="content-section" id="upcoming-events">
                <div class="page-header">
                    <h1 class="page-title">Upcoming Events</h1>
                    <p class="page-subtitle">View and manage your event participations</p>
                </div>

                <div class="events-container">
                    <div class="events-header">
                        <h3 class="panel-title">Events & Trainings</h3>
                    </div>
                    <div class="events-list" id="eventsList">
                        <div class="empty-state">
                            <p>No upcoming events</p>
                            <small>Events you're participating in will appear here</small>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Performance Record Section -->
            <section class="content-section" id="performance-record">
                <div class="page-header">
                    <h1 class="page-title">Performance Record</h1>
                    <p class="page-subtitle">Track your performance history, achievements, and upload certificates</p>
                </div>

                <!-- Certificate Upload Section -->
                <div class="content-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Certificates & Achievements</h3>
                        <button class="action-btn" onclick="openCertificateUploadModal()">
                            <span>+</span>
                            Upload Certificate
                        </button>
                    </div>
                    <div class="panel-content">
                        <div id="certificatesLoading" class="loading-state" style="display: none;">
                            <p>Loading certificates...</p>
                        </div>
                        <div id="certificatesContainer">
                            <!-- Certificates will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Events Participated Section -->
                <div class="content-panel" style="margin-top: 2rem;">
                    <div class="panel-header">
                        <h3 class="panel-title">Events Participated</h3>
                    </div>
                    <div class="panel-content">
                        <div id="eventsLoading" class="loading-state" style="display: none;">
                            <p>Loading your event history...</p>
                        </div>
                        <div id="eventsContainer">
                            <!-- Events will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Certificate Upload Modal -->
                <div id="certificateUploadModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
                    <div style="background: white; border-radius: 8px; max-width: 500px; width: 90%; max-height: 80vh; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div style="padding: 1.5rem; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; color: #333; font-size: 1.2rem;">Upload Certificate</h3>
                            <button onclick="closeCertificateUploadModal()" style="background: none; border: none; font-size: 1.5rem; color: #666; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
                        </div>
                        <form id="certificateUploadForm" style="padding: 1.5rem;">
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">Certificate Title *</label>
                                <input type="text" id="certificateTitle" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; font-family: inherit;">
                            </div>
                            
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">Description</label>
                                <textarea id="certificateDescription" rows="3" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; font-family: inherit; resize: vertical;"></textarea>
                            </div>
                            
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">Date Received *</label>
                                <input type="date" id="certificateDate" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                            </div>
                            
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">Certificate File *</label>
                                <input type="file" id="certificateFile" accept=".jpg,.jpeg,.png,.pdf" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                                <small style="color: #666; margin-top: 0.25rem; display: block;">Accepted formats: JPG, PNG, PDF (Max 5MB)</small>
                            </div>
                            
                            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                                <button type="button" onclick="closeCertificateUploadModal()" style="padding: 0.75rem 1.5rem; border: 1px solid #ddd; background: white; color: #333; border-radius: 4px; cursor: pointer;">Cancel</button>
                                <button type="submit" style="padding: 0.75rem 1.5rem; border: none; background: #dc2626; color: white; border-radius: 4px; cursor: pointer;">Upload Certificate</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Event Evaluation Modal -->
                <div id="eventEvaluationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; padding: 2rem 0;">
                    <div style="background: white; border-radius: 8px; max-width: 800px; width: 90%; margin: 0 auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div style="padding: 1.5rem; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; background: white;">
                            <h3 style="margin: 0; color: #333; font-size: 1.3rem;">Event Evaluation</h3>
                            <button onclick="closeEventEvaluationModal()" style="background: none; border: none; font-size: 1.5rem; color: #666; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
                        </div>
                        <form id="eventEvaluationForm" style="padding: 2rem;">
                            <div style="margin-bottom: 2rem; text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 6px;">
                                <h4 id="evaluationEventTitle" style="margin: 0; color: #333; font-size: 1.1rem;">Event Title</h4>
                                <p style="margin: 0.5rem 0 0 0; color: #666; font-size: 0.9rem;">Please rate the following aspects of the event on a scale of 1-5</p>
                            </div>

                            <!-- Likert Scale Questions -->
                            <div style="margin-bottom: 2rem;">
                                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; margin-bottom: 1rem; padding: 0.75rem; background: #dc2626; color: white; font-weight: 600; border-radius: 4px;">
                                    <div>Evaluation Criteria</div>
                                    <div style="text-align: center;">Strongly Disagree (1)</div>
                                    <div style="text-align: center;">Disagree (2)</div>
                                    <div style="text-align: center;">Neutral (3)</div>
                                    <div style="text-align: center;">Agree (4)</div>
                                    <div style="text-align: center;">Strongly Agree (5)</div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">1. Overall, how would you rate the seminar/training?</div>
                                    <div style="text-align: center;"><input type="radio" name="q1" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q1" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q1" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q1" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q1" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">2. How would you rate the appropriateness of time and the proper use of resources provided?</div>
                                    <div style="text-align: center;"><input type="radio" name="q2" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q2" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q2" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q2" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q2" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">3. Objectives and expectations were clearly communicated and achieved.</div>
                                    <div style="text-align: center;"><input type="radio" name="q3" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q3" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q3" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q3" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q3" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">4. Session activities were appropriate and relevant to the achievement of the learning objectives.</div>
                                    <div style="text-align: center;"><input type="radio" name="q4" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q4" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q4" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q4" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q4" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">5. Sufficient time was allotted for group discussion and comments.</div>
                                    <div style="text-align: center;"><input type="radio" name="q5" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q5" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q5" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q5" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q5" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">6. Materials and task were useful.</div>
                                    <div style="text-align: center;"><input type="radio" name="q6" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q6" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q6" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q6" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q6" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">7. The resource person/trainer displayed thorough knowledge of, and provided relevant insights on the topic/s discussed.</div>
                                    <div style="text-align: center;"><input type="radio" name="q7" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q7" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q7" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q7" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q7" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">8. The resource person/trainer thoroughly explained and processed the learning activities throughout the training.</div>
                                    <div style="text-align: center;"><input type="radio" name="q8" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q8" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q8" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q8" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q8" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">9. The resource person/trainer created a good learning environment, sustained the attention of the participants, and encouraged their participation in the training duration.</div>
                                    <div style="text-align: center;"><input type="radio" name="q9" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q9" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q9" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q9" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q9" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">10. The resource person/trainer used the time well, including some adjustments in the training schedule, if needed.</div>
                                    <div style="text-align: center;"><input type="radio" name="q10" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q10" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q10" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q10" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q10" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">11. The resource person/trainer demonstrated keenness to the participants' needs and other requirements related to the training.</div>
                                    <div style="text-align: center;"><input type="radio" name="q11" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q11" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q11" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q11" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q11" value="5" required></div>
                                </div>

                                <div class="evaluation-question" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 1rem;">
                                    <div style="font-weight: 500;">12. The venue or platform used was conducive for learning.</div>
                                    <div style="text-align: center;"><input type="radio" name="q12" value="1" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q12" value="2" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q12" value="3" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q12" value="4" required></div>
                                    <div style="text-align: center;"><input type="radio" name="q12" value="5" required></div>
                                </div>
                            </div>

                            <!-- Open-Ended Questions Section -->
                            <div style="margin-bottom: 2rem;">
                                <div style="margin-bottom: 1.5rem;">
                                    <label for="q13_opinion" style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: #333; font-size: 1rem;">13. Was the training helpful for you in the practice of your profession? Why or why not?</label>
                                    <textarea id="q13_opinion" name="q13_opinion" rows="3" required style="width: 100%; padding: 1rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; font-family: inherit; resize: vertical;" placeholder="Please share your opinion..."></textarea>
                                </div>

                                <div style="margin-bottom: 1.5rem;">
                                    <label for="q14_suggestions" style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: #333; font-size: 1rem;">14. What aspect of the training has been helpful to you? What other topics would you suggest for future trainings?</label>
                                    <textarea id="q14_suggestions" name="q14_suggestions" rows="3" required style="width: 100%; padding: 1rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; font-family: inherit; resize: vertical;" placeholder="Please share helpful aspects and your suggestions..."></textarea>
                                </div>

                                <div style="margin-bottom: 1.5rem;">
                                    <label for="q15_comments" style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: #333; font-size: 1rem;">15. Comments/Recommendations/Complaints:</label>
                                    <textarea id="q15_comments" name="q15_comments" rows="4" required style="width: 100%; padding: 1rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; font-family: inherit; resize: vertical;" placeholder="Please share your thoughts, suggestions, or additional feedback..."></textarea>
                                </div>
                            </div>

                            <!-- Hidden field for event ID -->
                            <input type="hidden" id="evaluationEventId" name="event_id" value="">

                            <!-- Submit Buttons -->
                            <div style="display: flex; gap: 1rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid #e0e0e0;">
                                <button type="button" onclick="closeEventEvaluationModal()" style="padding: 0.75rem 1.5rem; border: 1px solid #ddd; background: white; color: #333; border-radius: 4px; cursor: pointer; font-weight: 500;">Cancel</button>
                                <button type="submit" style="padding: 0.75rem 2rem; border: none; background: #17a2b8; color: white; border-radius: 4px; cursor: pointer; font-weight: 600;">Submit Evaluation</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Costume Borrowing Section -->
            <section class="content-section" id="costume-borrowing">
                <div class="page-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h1 class="page-title">Costume Borrowing</h1>
                            <p class="page-subtitle">Manage your costume borrowing and returns</p>
                        </div>
                        <button class="action-btn" onclick="openCostumeBorrowingForm()">
                            Costume Borrowing Form
                        </button>
                    </div>
                </div>

                <div class="content-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">My Borrowed Costumes</h3>
                    </div>
                    <div class="panel-content" id="borrowedCostumesContent">
                        <div class="loading-state" id="borrowedCostumesLoading">
                            <p>Loading borrowed costumes...</p>
                        </div>
                        <div class="borrowed-costumes-list" id="borrowedCostumesList" style="display: none;">
                            <!-- Borrowed costumes will be loaded here -->
                        </div>
                    </div>
                </div>
            </section>

            <!-- Profile Section -->
            <section class="content-section" id="profile">
                <div class="page-header">
                    <h1 class="page-title">My Profile</h1>
                    <p class="page-subtitle">View and manage your profile information</p>
                </div>

                <div class="profile-container">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            👤
                        </div>
                        <div class="profile-name">
                            <?= htmlspecialchars(($student_info['first_name'] ?? '') . ' ' . ($student_info['middle_name'] ?? '') . ' ' . ($student_info['last_name'] ?? '')) ?>
                        </div>
                        <div class="profile-role">
                            Student Artist
                            <?php if (!empty($student_info['cultural_group'])): ?>
                                - <?= htmlspecialchars($student_info['cultural_group']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="profile-details">
                        <?php if ($student_info): ?>
                            <div class="detail-group">
                                <div class="detail-label">SR-Code</div>
                                <div class="detail-value"><?= htmlspecialchars($student_info['sr_code'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?= htmlspecialchars($student_info['email'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Campus</div>
                                <div class="detail-value"><?= htmlspecialchars($student_info['campus'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Program</div>
                                <div class="detail-value"><?= htmlspecialchars($student_info['program'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Year Level</div>
                                <div class="detail-value"><?= htmlspecialchars($student_info['year_level'] ?? 'N/A') ?></div>
                            </div>
                            <?php if (!empty($student_info['contact_number'])): ?>
                            <div class="detail-group">
                                <div class="detail-label">Contact Number</div>
                                <div class="detail-value"><?= htmlspecialchars($student_info['contact_number']) ?></div>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>Profile information not available</p>
                                <small>Please contact the administrator</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Modal scroll prevention functions
        function preventBackgroundScroll() {
            document.body.classList.add('modal-open');
        }

        function allowBackgroundScroll() {
            document.body.classList.remove('modal-open');
        }

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
                        
                        // Load data specific to section
                        if (sectionId === 'announcements') {
                            loadAnnouncements();
                        } else if (sectionId === 'upcoming-events') {
                            loadUpcomingEvents();
                        } else if (sectionId === 'costume-borrowing') {
                            loadBorrowedCostumes();
                        }
                    }

                    // Update URL without page reload
                    const newUrl = `${window.location.pathname}?section=${sectionId}`;
                    window.history.pushState({}, '', newUrl);
                });
            });

            // Load initial data if needed
            if (activeSection === 'announcements') {
                loadAnnouncements();
            } else if (activeSection === 'upcoming-events') {
                loadUpcomingEvents();
            } else if (activeSection === 'costume-borrowing') {
                loadBorrowedCostumes();
            }

            // Click outside modal to close functionality
            const modals = ['certificateUploadModal', 'eventEvaluationModal'];

            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            // Call appropriate close function
                            if (modalId === 'certificateUploadModal') {
                                closeCertificateUploadModal();
                            } else if (modalId === 'eventEvaluationModal') {
                                closeEventEvaluationModal();
                            }
                        }
                    });
                }
            });
        });

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../index.php';
            }
        }

        // Load announcements
        function loadAnnouncements() {
            const announcementsContent = document.querySelector('#announcements .panel-content');
            
            // Show loading state
            announcementsContent.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <p>Loading announcements...</p>
                </div>
            `;
            
            fetch('get_announcements.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAnnouncements(data.announcements);
                    } else {
                        announcementsContent.innerHTML = `
                            <div class="empty-state">
                                <p>Error loading announcements</p>
                                <small>${data.message}</small>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    announcementsContent.innerHTML = `
                        <div class="empty-state">
                            <p>Error loading announcements</p>
                            <small>Please try again later</small>
                        </div>
                    `;
                });
        }

        // Display announcements
        function displayAnnouncements(announcements) {
            const announcementsContent = document.querySelector('#announcements .panel-content');
            
            if (announcements.length === 0) {
                announcementsContent.innerHTML = `
                    <div class="empty-state">
                        <p>No announcements available</p>
                        <small>Announcements will appear here when posted</small>
                    </div>
                `;
                return;
            }

            let html = '<div class="announcements-list">';
            announcements.forEach(announcement => {
                const createdDate = new Date(announcement.created_at).toLocaleDateString();
                const content = announcement.content || announcement.message || 'No content';
                const isPinned = announcement.is_pinned == 1;
                
                html += `
                    <div class="announcement-item" style="border-bottom: 1px solid #e0e0e0; padding: 1rem 0; ${isPinned ? 'background-color: #fff9e6; padding: 1rem; border-radius: 8px; margin-bottom: 0.5rem;' : ''}">
                        ${isPinned ? '<div style="color: #dc2626; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem;">📌 PINNED</div>' : ''}
                        <div class="announcement-header" style="margin-bottom: 0.5rem;">
                            <h4 style="color: #dc2626; margin: 0; font-size: 1.1rem;">${announcement.title}</h4>
                            <small style="color: #666;">${createdDate}</small>
                        </div>
                        <div class="announcement-content" style="white-space: pre-line; line-height: 1.5; color: #333;">
                            ${content}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            announcementsContent.innerHTML = html;
        }

        // Load upcoming events
        function loadUpcomingEvents() {
            const eventsList = document.getElementById('eventsList');
            
            // Show loading state
            eventsList.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <p>Loading upcoming events...</p>
                </div>
            `;
            
            fetch('get_events.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayEvents(data.events);
                    } else {
                        eventsList.innerHTML = `
                            <div class="empty-state">
                                <p>Error loading events</p>
                                <small>${data.message}</small>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    eventsList.innerHTML = `
                        <div class="empty-state">
                            <p>Error loading events</p>
                            <small>Please try again later</small>
                        </div>
                    `;
                });
        }

        // Display events (show all upcoming events - joined or not, hide when they start)
        function displayEvents(events) {
            const eventsList = document.getElementById('eventsList');
            
            // Filter to show only upcoming events (not yet started), regardless of join status
            const availableEvents = events.filter(event => event.event_status === 'upcoming');
            
            if (availableEvents.length === 0) {
                eventsList.innerHTML = `
                    <div class="empty-state">
                        <p>No upcoming events available</p>
                        <small>Events available for your cultural group and campus will appear here</small>
                    </div>
                `;
                return;
            }

            let html = '<div class="events-grid" style="display: grid; gap: 1rem;">';
            availableEvents.forEach(event => {
                const dateRange = event.is_multi_day 
                    ? `${event.formatted_start_date} - ${event.formatted_end_date}`
                    : event.formatted_start_date;
                
                const culturalGroups = Array.isArray(event.cultural_groups_array) && event.cultural_groups_array.length > 0
                    ? event.cultural_groups_array.join(', ')
                    : 'All groups';
                
                // Show join status badge
                let statusBadge = '';
                let actionButton = '';
                
                if (event.has_joined) {
                    statusBadge = '<span style="background: #28a745; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">✓ JOINED</span>';
                } else {
                    statusBadge = '<span style="background: #007bff; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">UPCOMING</span>';
                    actionButton = `<button class="action-btn" onclick="joinEvent(${event.id})" style="margin-left: auto; white-space: nowrap;">Join Event</button>`;
                }
                
                html += `
                    <div class="event-card" style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 1.5rem; background: white;">
                        <div class="event-header" style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <h3 style="color: #dc2626; margin: 0;">${event.title}</h3>
                                    ${statusBadge}
                                </div>
                                <div style="display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.9rem; color: #666;">
                                    <span>📅 ${dateRange}</span>
                                    <span>📍 ${event.location}</span>
                                    <span>🎭 ${event.category || 'Event'}</span>
                                </div>
                            </div>
                            ${actionButton}
                        </div>
                        <div class="event-description" style="margin-bottom: 1rem; line-height: 1.5; color: #333;">
                            ${event.description || 'No description available'}
                        </div>
                        <div class="event-groups" style="font-size: 0.9rem; color: #666; border-top: 1px solid #e0e0e0; padding-top: 0.75rem;">
                            <strong>For:</strong> ${culturalGroups}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            eventsList.innerHTML = html;
        }

        // Open costume borrowing form
        function openCostumeBorrowingForm() {
            window.location.href = 'costume-borrowing-form.php';
        }

        // Load borrowed costumes
        function loadBorrowedCostumes() {
            const loadingDiv = document.getElementById('borrowedCostumesLoading');
            const listDiv = document.getElementById('borrowedCostumesList');
            
            loadingDiv.style.display = 'block';
            listDiv.style.display = 'none';
            
            fetch('get_borrowed_costumes.php')
                .then(response => response.json())
                .then(data => {
                    loadingDiv.style.display = 'none';
                    listDiv.style.display = 'block';
                    
                    if (data.success) {
                        displayBorrowedCostumes(data.requests);
                    } else {
                        showBorrowedCostumesError(data.message);
                    }
                })
                .catch(error => {
                    loadingDiv.style.display = 'none';
                    listDiv.style.display = 'block';
                    showBorrowedCostumesError('Error loading borrowed costumes: ' + error.message);
                });
        }

        // Display borrowed costumes and all requests
        function displayBorrowedCostumes(requests) {
            const listDiv = document.getElementById('borrowedCostumesList');
            
            if (!requests || requests.length === 0) {
                listDiv.innerHTML = `
                    <div class="empty-borrowed-state">
                        <p>No borrow requests</p>
                        <small>Your borrow requests will be listed here</small>
                    </div>
                `;
                return;
            }

            let html = `
                <table class="borrowed-costumes-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Status</th>
                            <th>Request Date</th>
                            <th>Return Date</th>
                            <th>Usage Period</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            requests.forEach(request => {
                const statusClass = getRequestStatusClass(request);
                const formattedRequestDate = formatDate(request.request_date);
                const formattedDueDate = formatDate(request.due_date);
                
                // Determine if item is overdue - only after 11:59 PM on the due date
                const now = new Date();
                const dueDate = new Date(request.due_date);
                const endOfDueDate = new Date(dueDate);
                endOfDueDate.setHours(23, 59, 59, 999); // Set to 11:59:59 PM on due date
                const isOverdue = request.status === 'approved' && request.current_status === 'active' && endOfDueDate < now;
                
                // Determine actions based on status
                let actionsHtml = '';
                if (request.status === 'approved' && request.current_status === 'active') {
                    // Always show return button for active items, regardless of overdue status
                    actionsHtml = `<button class="action-btn return-btn" onclick="openReturnForm(${request.id})" style="background: #28a745; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Return Items</button>`;
                } else if (request.current_status === 'pending_return') {
                    actionsHtml = '<span style="color: #f59e0b; font-weight: 600;">Return Pending</span>';
                } else if (request.current_status === 'returned') {
                    actionsHtml = '<span style="color: #10b981; font-weight: 600;">Returned</span>';
                }
                
                // Build item name display - show approved item with quantity and requested item if different
                let itemNameDisplay = request.item_name;
                if (request.quantity) {
                    itemNameDisplay += ` (Qty: ${request.quantity})`;
                }
                if (request.requested_item && request.requested_item !== request.item_name) {
                    itemNameDisplay += `<br><small style="color: #6b7280;">Requested: ${request.requested_item}</small>`;
                }
                
                html += `
                    <tr>
                        <td class="item-name-cell">${itemNameDisplay}</td>
                        <td><span class="item-status ${statusClass}">${getRequestStatusText(request)}</span></td>
                        <td class="date-cell">${formattedRequestDate}</td>
                        <td class="date-cell">${formattedDueDate}</td>
                        <td class="date-cell">${request.dates_of_use || 'Not specified'}</td>
                        <td class="action-cell">${actionsHtml}</td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            listDiv.innerHTML = html;
        }

        // Get status class for styling
        function getRequestStatusClass(request) {
            switch(request.status) {
                case 'pending':
                    return 'status-pending';
                case 'approved':
                    if (request.current_status === 'returned') {
                        return 'status-returned';
                    }
                    if (request.due_date) {
                        const dueDate = new Date(request.due_date);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        dueDate.setHours(0, 0, 0, 0);
                        
                        if (dueDate < today) {
                            return 'status-overdue';
                        }
                    }
                    return 'status-active';
                case 'rejected':
                    return 'status-rejected';
                default:
                    return 'status-pending';
            }
        }

        // Get status text
        function getRequestStatusText(request) {
            switch(request.status) {
                case 'pending':
                    return 'Pending Review';
                case 'approved':
                    if (request.current_status === 'returned') {
                        return 'Returned';
                    }
                    if (request.current_status === 'pending_return') {
                        return 'Return Pending';
                    }
                    if (request.current_status === 'overdue') {
                        return 'Overdue';
                    }
                    if (request.due_date) {
                        const dueDate = new Date(request.due_date);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        dueDate.setHours(0, 0, 0, 0);
                        
                        if (dueDate < today) {
                            return 'Overdue';
                        }
                    }
                    return 'Active';
                case 'rejected':
                    return 'Rejected';
                default:
                    return request.status;
            }
        }

        // Get display category
        function getDisplayCategory(request) {
            if (request.display_type === 'approved_item') {
                return request.category.charAt(0).toUpperCase() + request.category.slice(1);
            } else {
                return 'Borrow Request';
            }
        }

        // Format date
        function formatDate(dateString) {
            if (!dateString) return 'Not specified';
            
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        // Show error message
        function showBorrowedCostumesError(message) {
            const listDiv = document.getElementById('borrowedCostumesList');
            listDiv.innerHTML = `
                <div class="empty-borrowed-state">
                    <p style="color: #dc2626;">Error loading borrowed costumes</p>
                    <small>${message}</small>
                </div>
            `;
        }

        // Open return form for a specific borrowing request
        function openReturnForm(requestId) {
            console.log('Opening return form for request ID:', requestId);
            if (!requestId) {
                console.error('No request ID provided');
                alert('Error: No request ID provided');
                return;
            }
            // Navigate directly to return form
            window.location.href = 'return-form.php?request_id=' + requestId;
        }

        // Join event function
        function joinEvent(eventId) {
            if (!confirm('Are you sure you want to join this event?')) {
                return;
            }

            const button = event.target;
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Joining...';

            fetch('join_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event_id: eventId,
                    action: 'join'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Successfully joined the event!');
                    // Reload events to update the display
                    loadUpcomingEvents();
                    // Reload dashboard stats and panels
                    loadDashboardStats();
                } else {
                    alert('Error joining event: ' + data.message);
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error joining event. Please try again.');
                button.disabled = false;
                button.textContent = originalText;
            });
        }

        // Leave event function
        function leaveEvent(eventId) {
            if (!confirm('Are you sure you want to leave this event?')) {
                return;
            }

            const button = event.target;
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Leaving...';

            fetch('join_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event_id: eventId,
                    action: 'leave'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Successfully left the event.');
                    // Reload events to update the display
                    loadUpcomingEvents();
                } else {
                    alert('Error leaving event: ' + data.message);
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error leaving event. Please try again.');
                button.disabled = false;
                button.textContent = originalText;
            });
        }

        // Performance Record Functions
        function loadPerformanceRecord() {
            loadCertificates();
            loadParticipatedEvents();
        }

        // Certificate Upload Functions
        function openCertificateUploadModal() {
            const modal = document.getElementById('certificateUploadModal');
            modal.style.display = 'flex';
            modal.style.overflowY = 'auto';
            modal.style.padding = '2rem 0';
            modal.style.justifyContent = 'unset';
            modal.style.alignItems = 'unset';
            
            // Update the modal content container for scrollability
            const modalContent = modal.querySelector('div');
            modalContent.style.margin = 'auto';
            modalContent.style.maxHeight = '90vh';
            modalContent.style.overflowY = 'auto';
            modalContent.style.overflow = 'auto';
            
            // Reset form
            document.getElementById('certificateUploadForm').reset();
            preventBackgroundScroll();
        }

        function closeCertificateUploadModal() {
            const modal = document.getElementById('certificateUploadModal');
            modal.style.display = 'none';
            allowBackgroundScroll();
        }

        // Handle certificate upload form submission
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('certificateUploadForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    uploadCertificate();
                });
            }

            // Handle evaluation form submission
            const evaluationForm = document.getElementById('eventEvaluationForm');
            if (evaluationForm) {
                evaluationForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitEventEvaluation();
                });
            }
        });

        function uploadCertificate() {
            const form = document.getElementById('certificateUploadForm');
            const formData = new FormData();
            
            const title = document.getElementById('certificateTitle').value;
            const description = document.getElementById('certificateDescription').value;
            const date = document.getElementById('certificateDate').value;
            const file = document.getElementById('certificateFile').files[0];
            
            if (!title || !date || !file) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Check file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }
            
            formData.append('title', title);
            formData.append('description', description);
            formData.append('date', date);
            formData.append('certificate_file', file);
            
            // Show loading state
            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Uploading...';
            submitBtn.disabled = true;
            
            fetch('upload_certificate.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Certificate uploaded successfully!');
                    closeCertificateUploadModal();
                    loadCertificates(); // Reload certificates
                } else {
                    alert('Error uploading certificate: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error uploading certificate. Please try again.');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }

        function loadCertificates() {
            const container = document.getElementById('certificatesContainer');
            const loading = document.getElementById('certificatesLoading');
            
            loading.style.display = 'block';
            container.innerHTML = '';
            
            fetch('get_student_certificates.php')
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    displayCertificates(data.certificates || []);
                })
                .catch(error => {
                    console.error('Error:', error);
                    loading.style.display = 'none';
                    container.innerHTML = '<p style="color: #dc2626; text-align: center; padding: 2rem;">Error loading certificates</p>';
                });
        }

        function displayCertificates(certificates) {
            const container = document.getElementById('certificatesContainer');
            
            if (certificates.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <p>No certificates uploaded</p>
                        <small>Upload your first certificate to get started</small>
                    </div>
                `;
                return;
            }
            
            let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">';
            certificates.forEach(cert => {
                const fileExtension = cert.file_path.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png'].includes(fileExtension);
                
                html += `
                    <div style="border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="aspect-ratio: 16/9; background: #f8f9fa; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            ${isImage ? 
                                `<img src="${cert.file_path}" alt="${cert.title}" style="width: 100%; height: 100%; object-fit: cover;">` :
                                `<div style="text-align: center; color: #666;">
                                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📄</div>
                                    <div>PDF Certificate</div>
                                </div>`
                            }
                        </div>
                        <div style="padding: 1rem;">
                            <h4 style="margin: 0 0 0.5rem 0; color: #333; font-size: 1rem;">${cert.title}</h4>
                            ${cert.description ? `<p style="margin: 0 0 0.5rem 0; color: #666; font-size: 0.9rem;">${cert.description}</p>` : ''}
                            <div style="color: #888; font-size: 0.8rem; margin-bottom: 1rem;">
                                Date: ${new Date(cert.date_received).toLocaleDateString()}
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button onclick="viewCertificate('${cert.file_path}')" style="flex: 1; padding: 0.5rem; border: 1px solid #dc2626; background: white; color: #dc2626; border-radius: 4px; cursor: pointer; font-size: 0.9rem;">
                                    View
                                </button>
                                <button onclick="deleteCertificate(${cert.id})" style="padding: 0.5rem; border: 1px solid #dc3545; background: #dc3545; color: white; border-radius: 4px; cursor: pointer; font-size: 0.9rem;">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            container.innerHTML = html;
        }

        function viewCertificate(filePath) {
            console.log('Viewing certificate:', filePath); // Debug log
            
            const fileExtension = filePath.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png'].includes(fileExtension);
            
            if (isImage) {
                // Show image in modal
                showImageModal(filePath);
            } else {
                // Open PDF in new tab
                window.open(filePath, '_blank');
            }
        }

        function showImageModal(imagePath) {
            // Create modal if it doesn't exist
            let modal = document.getElementById('imageViewModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'imageViewModal';
                modal.style.cssText = `
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.8);
                    z-index: 2000;
                    justify-content: center;
                    align-items: center;
                    padding: 2rem;
                `;
                
                modal.innerHTML = `
                    <div style="position: relative; max-width: 90%; max-height: 90%; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div style="position: absolute; top: 10px; right: 10px; z-index: 10;">
                            <button onclick="closeImageModal()" style="background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
                        </div>
                        <img id="modalImage" style="max-width: 100%; max-height: 90vh; object-fit: contain; display: block;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div style="display: none; padding: 2rem; text-align: center; color: #666;">
                            <p>Unable to load image</p>
                            <small>The certificate file may not exist or is not accessible</small>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                // Close modal when clicking outside
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeImageModal();
                    }
                });
            }
            
            // Set image source and show modal
            const modalImage = document.getElementById('modalImage');
            const errorDiv = modalImage.nextElementSibling;
            
            // Reset states
            modalImage.style.display = 'block';
            errorDiv.style.display = 'none';
            
            // Add loading state
            modalImage.style.opacity = '0.5';
            modalImage.onload = function() {
                this.style.opacity = '1';
                console.log('Image loaded successfully');
            };
            modalImage.onerror = function() {
                console.error('Failed to load image:', imagePath);
                this.style.display = 'none';
                errorDiv.style.display = 'block';
            };
            
            modalImage.src = imagePath;
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            const modal = document.getElementById('imageViewModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        function deleteCertificate(certificateId) {
            if (!confirm('Are you sure you want to delete this certificate?')) {
                return;
            }
            
            fetch('delete_certificate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ certificate_id: certificateId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Certificate deleted successfully!');
                    loadCertificates(); // Reload certificates
                } else {
                    alert('Error deleting certificate: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting certificate. Please try again.');
            });
        }

        function loadParticipatedEvents() {
            const container = document.getElementById('eventsContainer');
            const loading = document.getElementById('eventsLoading');
            
            loading.style.display = 'block';
            container.innerHTML = '';
            
            fetch('get_student_events.php')
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    displayParticipatedEvents(data.events || []);
                })
                .catch(error => {
                    console.error('Error:', error);
                    loading.style.display = 'none';
                    container.innerHTML = '<p style="color: #dc2626; text-align: center; padding: 2rem;">Error loading events</p>';
                });
        }

        function displayParticipatedEvents(events) {
            const container = document.getElementById('eventsContainer');
            
            if (events.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <p>No events participated</p>
                        <small>Your event participation history will appear here</small>
                    </div>
                `;
                return;
            }
            
            let html = `
                <table class="borrowed-costumes-table">
                    <thead>
                        <tr>
                            <th>EVENT NAME</th>
                            <th>STATUS</th>
                            <th>EVENT DATE</th>
                            <th>LOCATION</th>
                            <th>CATEGORY</th>
                            <th>JOINED</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            events.forEach(event => {
                const dateRange = event.is_multi_day 
                    ? `${event.formatted_start_date} - ${event.formatted_end_date}`
                    : event.formatted_start_date;
                
                const statusClass = event.date_status === 'completed' ? 'status-returned' : 
                                   event.date_status === 'ongoing' ? 'status-active' : 'status-pending';
                
                const joinedDate = new Date(event.joined_date).toLocaleDateString();
                
                // Determine if evaluate button should be shown
                let evaluateButtonHtml = '';
                if (event.date_status === 'completed') {
                    if (event.has_evaluation) {
                        evaluateButtonHtml = '<span class="item-status" style="background: #d1fae5; color: #065f46;">Evaluated</span>';
                    } else {
                        evaluateButtonHtml = `<button class="action-btn" style="background: #17a2b8; padding: 0.5rem 1rem; font-size: 0.8rem;" onclick="openEvaluationForm(${event.id}, '${event.title}')">Evaluate</button>`;
                    }
                } else {
                    evaluateButtonHtml = '<span style="color: #6c757d; font-size: 0.8rem;">Not Available</span>';
                }
                
                html += `
                    <tr>
                        <td class="item-name-cell">${event.title}</td>
                        <td><span class="item-status ${statusClass}">${event.date_status.toUpperCase()}</span></td>
                        <td class="date-cell">${dateRange}</td>
                        <td>${event.location}</td>
                        <td class="item-category-cell">${event.category}</td>
                        <td class="date-cell">${joinedDate}</td>
                        <td class="action-cell">${evaluateButtonHtml}</td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            container.innerHTML = html;
        }

        // Load performance record when section is activated
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const section = this.dataset.section;
                    if (section === 'performance-record') {
                        setTimeout(() => loadPerformanceRecord(), 100);
                    }
                });
            });
            
            // Load if initially on performance record page
            const urlParams = new URLSearchParams(window.location.search);
            const activeSection = urlParams.get('section');
            if (activeSection === 'performance-record') {
                setTimeout(() => loadPerformanceRecord(), 100);
            }
        });

        // Load dashboard statistics
        function loadDashboardStats() {
            fetch('get_dashboard_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateDashboardCards(data.stats);
                        loadDashboardAnnouncements();
                        loadDashboardEvents();
                    } else {
                        console.error('Dashboard stats error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading dashboard stats:', error);
                });
        }

        function updateDashboardCards(stats) {
            // Update card values
            const cards = document.querySelectorAll('.stat-card .stat-number');
            if (cards.length >= 4) {
                cards[0].textContent = stats.new_announcements;
                cards[1].textContent = stats.upcoming_events; 
                cards[2].textContent = stats.total_performances;
                cards[3].textContent = stats.borrowed_costumes;
            }
        }

        // Load recent announcements for dashboard
        function loadDashboardAnnouncements() {
            fetch('get_announcements.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.announcements && data.announcements.length > 0) {
                        const panel = document.querySelector('#dashboard .content-panel:nth-child(1) .panel-content');
                        const recentAnnouncements = data.announcements.slice(0, 3);
                        
                        let html = '<div style="display: flex; flex-direction: column; gap: 0.75rem;">';
                        recentAnnouncements.forEach(announcement => {
                            const content = announcement.content || announcement.message || 'No content';
                            const shortContent = content.length > 100 ? content.substring(0, 100) + '...' : content;
                            const date = new Date(announcement.created_at).toLocaleDateString();
                            
                            html += `
                                <div style="border-left: 3px solid #dc2626; padding-left: 0.75rem;">
                                    <div style="font-weight: 600; color: #333; margin-bottom: 0.25rem;">${announcement.title}</div>
                                    <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">${shortContent}</div>
                                    <div style="font-size: 0.75rem; color: #999;">${date}</div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        panel.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Error loading dashboard announcements:', error);
                });
        }

        // Load upcoming events for dashboard (only events student has JOINED and not yet ended)
        function loadDashboardEvents() {
            fetch('get_events.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.events && data.events.length > 0) {
                        const panel = document.querySelector('#dashboard .content-panel:nth-child(2) .panel-content');
                        // Filter for events that student HAS JOINED and haven't ended yet
                        const joinedEvents = data.events.filter(e => 
                            e.has_joined && e.event_status !== 'ended'
                        ).slice(0, 2);
                        
                        if (joinedEvents.length > 0) {
                            let html = '<div style="display: flex; flex-direction: column; gap: 0.75rem;">';
                            joinedEvents.forEach(event => {
                                const culturalGroups = Array.isArray(event.cultural_groups_array) 
                                    ? event.cultural_groups_array.join(', ') 
                                    : 'All groups';
                                html += `
                                    <div style="border-left: 3px solid #dc2626; padding-left: 0.75rem;">
                                        <div style="font-weight: 600; color: #333; margin-bottom: 0.25rem;">${event.title}</div>
                                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">
                                            📅 ${event.formatted_start_date}
                                        </div>
                                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">
                                            📍 ${event.location || 'TBA'}
                                        </div>
                                        <div style="font-size: 0.75rem; color: #999;">
                                            ${culturalGroups}
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                            panel.innerHTML = html;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading dashboard events:', error);
                });
        }

        // Load costume returns for dashboard (active borrowed items)
        function loadDashboardCostumeReturns() {
            fetch('get_borrowed_costumes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.requests && data.requests.length > 0) {
                        const panel = document.querySelector('#dashboard .content-panel:nth-child(3) .panel-content');
                        // Filter for active borrowed items
                        const activeBorrows = data.requests.filter(request => 
                            request.status === 'approved' && request.current_status === 'active'
                        ).slice(0, 3);
                        
                        if (activeBorrows.length > 0) {
                            let html = '<div style="display: flex; flex-direction: column; gap: 0.75rem;">';
                            activeBorrows.forEach(request => {
                                const dueDate = new Date(request.due_date);
                                const now = new Date();
                                const endOfDueDate = new Date(dueDate);
                                endOfDueDate.setHours(23, 59, 59, 999);
                                const isOverdue = endOfDueDate < now;
                                const dueDateText = formatDate(request.due_date);
                                
                                const itemName = request.approved_item_name || request.item_name || 'Item';
                                const quantity = request.approved_quantity || request.quantity || 1;
                                
                                html += `
                                    <div style="border-left: 3px solid ${isOverdue ? '#dc2626' : '#dc2626'}; padding-left: 0.75rem;">
                                        <div style="font-weight: 600; color: #333; margin-bottom: 0.25rem;">${itemName}</div>
                                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">
                                            Qty: ${quantity} | Due: ${dueDateText}
                                        </div>
                                        <div style="font-size: 0.75rem; color: ${isOverdue ? '#dc2626' : '#dc2626'};">
                                            ${isOverdue ? 'OVERDUE' : 'Active'}
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                            panel.innerHTML = html;
                        } else {
                            panel.innerHTML = `
                                <div class="empty-state">
                                    <p>No costume returns due</p>
                                    <small>Your borrowed costumes will appear here</small>
                                </div>
                            `;
                        }
                    } else {
                        const panel = document.querySelector('#dashboard .content-panel:nth-child(3) .panel-content');
                        panel.innerHTML = `
                            <div class="empty-state">
                                <p>No costume returns due</p>
                                <small>Your borrowed costumes will appear here</small>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading dashboard costume returns:', error);
                    const panel = document.querySelector('#dashboard .content-panel:nth-child(3) .panel-content');
                    panel.innerHTML = `
                        <div class="empty-state">
                            <p>Error loading costume returns</p>
                            <small>Please refresh the page</small>
                        </div>
                    `;
                });
        }

        // Load stats when dashboard is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Load stats after a short delay to ensure DOM is ready
            setTimeout(loadDashboardStats, 500);
            // Load dashboard content panels
            setTimeout(loadDashboardAnnouncements, 700);
            setTimeout(loadDashboardEvents, 800);
            setTimeout(loadDashboardCostumeReturns, 900);
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('certificateUploadModal');
            if (event.target === modal) {
                closeCertificateUploadModal();
            }
        });

        // Event evaluation function
        function openEvaluationForm(eventId, eventTitle) {
            // Set the event title and ID in the modal
            document.getElementById('evaluationEventTitle').textContent = eventTitle;
            document.getElementById('evaluationEventId').value = eventId;
            
            // Reset the form
            document.getElementById('eventEvaluationForm').reset();
            document.getElementById('evaluationEventId').value = eventId; // Set again after reset
            
            // Show the modal
            const modal = document.getElementById('eventEvaluationModal');
            modal.style.display = 'block';
            preventBackgroundScroll(); // Prevent background scrolling
        }

        // Close evaluation modal
        function closeEventEvaluationModal() {
            const modal = document.getElementById('eventEvaluationModal');
            modal.style.display = 'none';
            allowBackgroundScroll(); // Restore scrolling
        }

        // Submit event evaluation
        function submitEventEvaluation() {
            const form = document.getElementById('eventEvaluationForm');
            const formData = new FormData(form);
            
            // Convert FormData to JSON for easier handling
            const evaluationData = {
                event_id: formData.get('event_id'),
                q1_rating: formData.get('q1'),
                q2_rating: formData.get('q2'),
                q3_rating: formData.get('q3'),
                q4_rating: formData.get('q4'),
                q5_rating: formData.get('q5'),
                q6_rating: formData.get('q6'),
                q7_rating: formData.get('q7'),
                q8_rating: formData.get('q8'),
                q9_rating: formData.get('q9'),
                q10_rating: formData.get('q10'),
                q11_rating: formData.get('q11'),
                q12_rating: formData.get('q12'),
                q13_opinion: formData.get('q13_opinion'),
                q14_suggestions: formData.get('q14_suggestions'),
                q15_comments: formData.get('q15_comments')
            };

            // Validate that all Likert scale questions (1-12) are answered
            for (let i = 1; i <= 12; i++) {
                if (!evaluationData[`q${i}_rating`]) {
                    alert(`Please answer question ${i} before submitting.`);
                    return;
                }
            }
            
            // Validate open-ended questions
            if (!evaluationData.q13_opinion || evaluationData.q13_opinion.trim() === '') {
                alert('Please answer question 13 (Was the training helpful for you in the practice of your profession?).');
                return;
            }
            
            if (!evaluationData.q14_suggestions || evaluationData.q14_suggestions.trim() === '') {
                alert('Please answer question 14 (What aspect of the training has been helpful to you?).');
                return;
            }
            
            if (!evaluationData.q15_comments || evaluationData.q15_comments.trim() === '') {
                alert('Please provide your comments/recommendations/complaints in question 15.');
                return;
            }

            // Show loading state
            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;

            // Submit evaluation
            fetch('submit_event_evaluation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(evaluationData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Thank you! Your evaluation has been submitted successfully.');
                    closeEventEvaluationModal();
                    // Optionally reload events to show updated status
                    loadParticipatedEvents();
                } else {
                    alert('Error submitting evaluation: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error submitting evaluation. Please try again.');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }
    </script>
</body>
</html>

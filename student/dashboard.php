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
    // Count upcoming events
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'active' AND start_date >= CURDATE()");
    $stmt->execute();
    $upcoming_events = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Count borrowed costumes (placeholder - will implement table later)
    $borrowed_costumes = 0;
    
    // Count performances (placeholder - will implement table later)
    $total_performances = 0;
    
    // Count announcements (placeholder - will implement table later)
    $total_announcements = 0;
    
} catch (Exception $e) {
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
            background: linear-gradient(135deg, #4285F4, #357ae8);
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
                <span><?= htmlspecialchars($student_info['first_name'] ?? 'Student') ?> <?= htmlspecialchars($student_info['last_name'] ?? '') ?></span>
            </div>
            <button class="logout-btn" onclick="window.location.href='../index.php'">Logout</button>
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
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Announcements</div>
                            <div class="card-icon">📢</div>
                        </div>
                        <div class="card-number"><?= $total_announcements ?></div>
                        <div class="card-subtitle">New announcements</div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Upcoming Events</div>
                            <div class="card-icon">📅</div>
                        </div>
                        <div class="card-number"><?= $upcoming_events ?></div>
                        <div class="card-subtitle">Events this month</div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Performances</div>
                            <div class="card-icon">🎭</div>
                        </div>
                        <div class="card-number"><?= $total_performances ?></div>
                        <div class="card-subtitle">Total performances</div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Borrowed Costumes</div>
                            <div class="card-icon">👗</div>
                        </div>
                        <div class="card-number"><?= $borrowed_costumes ?></div>
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
                    <p class="page-subtitle">Track your performance history and achievements</p>
                </div>

                <div class="content-panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Performance History</h3>
                    </div>
                    <div class="panel-content">
                        <div class="empty-state">
                            <p>No performance records</p>
                            <small>Your performance history will be displayed here</small>
                        </div>
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
                    <div class="panel-content">
                        <div class="empty-state">
                            <p>No borrowed costumes</p>
                            <small>Your borrowed costumes will be listed here</small>
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
                        
                        <div style="margin-top: 2rem; text-align: center;">
                            <button class="action-btn secondary" onclick="editProfile()">Edit Profile</button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
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
                        
                        // Load data specific to section
                        if (sectionId === 'upcoming-events') {
                            loadUpcomingEvents();
                        }
                    }

                    // Update URL without page reload
                    const newUrl = `${window.location.pathname}?section=${sectionId}`;
                    window.history.pushState({}, '', newUrl);
                });
            });

            // Load initial data if needed
            if (activeSection === 'upcoming-events') {
                loadUpcomingEvents();
            }
        });

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../index.php';
            }
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
            
            // This would typically fetch from a backend endpoint
            // For now, we'll show placeholder content
            setTimeout(() => {
                eventsList.innerHTML = `
                    <div class="empty-state">
                        <p>No upcoming events</p>
                        <small>Events you're participating in will appear here</small>
                    </div>
                `;
            }, 1000);
        }

        // Edit profile function
        function editProfile() {
            alert('Edit profile functionality will be implemented soon!');
        }

        // Open costume borrowing form
        function openCostumeBorrowingForm() {
            window.location.href = 'costume-borrowing-form.php';
        }
    </script>
</body>
</html>

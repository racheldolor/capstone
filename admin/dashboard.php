<?php
session_start();
require_once '../config/database.php';

// Handle AJAX requests FIRST - before any other processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Prevent any output before JSON response
    ob_start();
    ob_clean();
    
    // Disable error display to prevent HTML in JSON response
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    header('Content-Type: application/json');
    
    // Log the request for debugging
    error_log("AJAX Request - Action: " . ($_POST['action'] ?? 'none'));
    
    $pdo = getDBConnection();
    
    switch ($_POST['action']) {
        case 'add_user':
            try {
                // Ensure clean output buffer
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                $first_name = trim($_POST['first_name']);
                $middle_name = !empty($_POST['middle_name']) ? trim($_POST['middle_name']) : null;
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $role = $_POST['role'];
                $raw_password = $_POST['password'];
                $sr_code = !empty($_POST['sr_code']) ? trim($_POST['sr_code']) : null;
                
                // Basic password validation (admin can set any password format)
                if (empty($raw_password)) {
                    echo json_encode(['success' => false, 'message' => 'Password is required']);
                    exit();
                }
                
                $password = password_hash($raw_password, PASSWORD_DEFAULT);
                
                // If role is student, SR code is required
                if ($role === 'student' && empty($sr_code)) {
                    echo json_encode(['success' => false, 'message' => 'SR Code is required for student accounts']);
                    exit();
                }
                
                // Insert into appropriate table based on role
                if ($role === 'student') {
                    // Debug: Log the attempt
                    error_log("Attempting to insert student: SR=$sr_code, Email=$email");
                    
                    // For students, insert into student_artists table with comprehensive data
                    $stmt = $pdo->prepare("INSERT INTO student_artists (sr_code, first_name, middle_name, last_name, email, password, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                    $result = $stmt->execute([$sr_code, $first_name, $middle_name, $last_name, $email, $password]);
                    
                    if (!$result) {
                        $errorInfo = $stmt->errorInfo();
                        error_log("SQL Error: " . print_r($errorInfo, true));
                        throw new Exception("Failed to insert into student_artists table. SQL Error: " . $errorInfo[2]);
                    }
                } else {
                    // For other roles, insert into users table (without sr_code)
                    $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                    if (!$stmt->execute([$first_name, $middle_name, $last_name, $email, $password, $role])) {
                        throw new Exception("Failed to insert into users table: " . implode(", ", $stmt->errorInfo()));
                    }
                }
                
                $user_id = $pdo->lastInsertId();
                
                // Log admin action
                try {
                    logAdminAction($pdo, $_SESSION['admin_id'], 'USER_ADD', $user_id, "Added new " . ($role === 'student' ? 'student artist' : 'user') . ": $email" . ($sr_code ? " (SR: $sr_code)" : ""));
                } catch (Exception $logError) {
                    // Log error but don't fail the user creation
                    error_log("Failed to log admin action: " . $logError->getMessage());
                }
                
                echo json_encode(['success' => true, 'message' => 'User added successfully']);
                exit();
            } catch (Exception $e) {
                // Ensure clean output
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                error_log("Add user error: " . $e->getMessage());
                
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    if (strpos($e->getMessage(), 'sr_code') !== false) {
                        echo json_encode(['success' => false, 'message' => 'SR Code already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Email already exists']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error adding user: ' . $e->getMessage()]);
                }
                exit();
            }
            
        case 'delete_user':
            try {
                // Ensure clean output
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                $user_id = intval($_POST['user_id']);
                $source_table = $_POST['source_table'] ?? 'users';
                
                if ($source_table === 'student_artists') {
                    // First, get the student's details before deletion
                    $stmt = $pdo->prepare("SELECT sr_code, email, first_name, last_name FROM student_artists WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($student) {
                        // Add to deleted_students tracking table
                        $stmt = $pdo->prepare("INSERT INTO deleted_students (sr_code, email, deleted_by, reason) VALUES (?, ?, ?, ?)");
                        $reason = "Student account deleted by admin";
                        $stmt->execute([$student['sr_code'], $student['email'], $_SESSION['admin_id'] ?? 1, $reason]);
                        
                        // Now delete from student_artists
                        $stmt = $pdo->prepare("DELETE FROM student_artists WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        // Log the action (suppress any output from logging)
                        try {
                            logAdminAction($pdo, $_SESSION['admin_id'] ?? 1, 'USER_DELETE', $user_id, "Student artist deleted: {$student['first_name']} {$student['last_name']} ({$student['sr_code']})");
                        } catch (Exception $logError) {
                            // Ignore logging errors to prevent JSON corruption
                        }
                    }
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    try {
                        logAdminAction($pdo, $_SESSION['admin_id'] ?? 1, 'USER_DELETE', $user_id, "User deleted from $source_table");
                    } catch (Exception $logError) {
                        // Ignore logging errors
                    }
                }
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()]);
            }
            exit();
            
        case 'suspend_user':
            try {
                $user_id = intval($_POST['user_id']);
                $source_table = $_POST['source_table'] ?? 'users';
                
                if ($source_table === 'student_artists') {
                    $stmt = $pdo->prepare("UPDATE student_artists SET status = 'suspended' WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
                }
                $stmt->execute([$user_id]);
                
                logAdminAction($pdo, $_SESSION['admin_id'], 'USER_SUSPEND', $user_id, "User suspended in $source_table");
                echo json_encode(['success' => true, 'message' => 'User suspended successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error suspending user']);
            }
            exit();
            
        case 'activate_user':
            try {
                $user_id = intval($_POST['user_id']);
                $source_table = $_POST['source_table'] ?? 'users';
                
                if ($source_table === 'student_artists') {
                    $stmt = $pdo->prepare("UPDATE student_artists SET status = 'active' WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                }
                $stmt->execute([$user_id]);
                
                logAdminAction($pdo, $_SESSION['admin_id'], 'USER_ACTIVATE', $user_id, "User activated in $source_table");
                echo json_encode(['success' => true, 'message' => 'User activated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error activating user']);
            }
            exit();
            
        case 'update_user':
            try {
                $user_id = intval($_POST['user_id']);
                $source_table = $_POST['source_table'] ?? 'users';
                $first_name = trim($_POST['first_name']);
                $middle_name = !empty($_POST['middle_name']) ? trim($_POST['middle_name']) : null;
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $role = $_POST['role'] ?? null;
                
                if ($source_table === 'student_artists') {
                    // Update student_artists table (no role field)
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE student_artists SET first_name = ?, middle_name = ?, last_name = ?, email = ?, password = ? WHERE id = ?");
                        $stmt->execute([$first_name, $middle_name, $last_name, $email, $password, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE student_artists SET first_name = ?, middle_name = ?, last_name = ?, email = ? WHERE id = ?");
                        $stmt->execute([$first_name, $middle_name, $last_name, $email, $user_id]);
                    }
                } else {
                    // Update users table (has role field)
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
                        $stmt->execute([$first_name, $middle_name, $last_name, $email, $role, $password, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->execute([$first_name, $middle_name, $last_name, $email, $role, $user_id]);
                    }
                }
                
                logAdminAction($pdo, $_SESSION['admin_id'], 'USER_UPDATE', $user_id, "Updated user: $email from table: $source_table");
                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo json_encode(['success' => false, 'message' => 'Email already exists']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()]);
                }
            }
            exit();
            
        case 'get_user':
            try {
                $user_id = intval($_POST['user_id']);
                $source_table = isset($_POST['source_table']) ? $_POST['source_table'] : 'users';
                
                // Validate table name
                if (!in_array($source_table, ['users', 'student_artists'])) {
                    throw new Exception('Invalid table specified');
                }
                
                $stmt = $pdo->prepare("SELECT * FROM `$source_table` WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    unset($user['password']); // Don't send password
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching user: ' . $e->getMessage()]);
            }
            exit();
            
        case 'allow_readd_student':
            try {
                $sr_code = $_POST['sr_code'] ?? '';
                $email = $_POST['email'] ?? '';
                
                if (empty($sr_code) && empty($email)) {
                    throw new Exception('SR code or email is required');
                }
                
                // Remove from deleted_students table to allow re-adding
                if (!empty($sr_code)) {
                    $stmt = $pdo->prepare("DELETE FROM deleted_students WHERE sr_code = ?");
                    $stmt->execute([$sr_code]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM deleted_students WHERE email = ?");
                    $stmt->execute([$email]);
                }
                
                logAdminAction($pdo, $_SESSION['admin_id'], 'STUDENT_UNDELETE', 0, "Allowed re-addition of student: $sr_code $email");
                echo json_encode(['success' => true, 'message' => 'Student can now be re-added from approved applications']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error allowing student re-addition: ' . $e->getMessage()]);
            }
            exit();
            
        case 'sync_deleted_students':
            try {
                // Find approved applications that don't have corresponding student accounts
                // and aren't already in the deleted_students table - these were likely deleted before tracking
                $stmt = $pdo->prepare("
                    SELECT DISTINCT a.sr_code, a.email 
                    FROM applications a 
                    WHERE a.application_status = 'approved' 
                    AND a.sr_code NOT IN (SELECT sr_code FROM student_artists WHERE sr_code IS NOT NULL)
                    AND a.email NOT IN (SELECT email FROM student_artists WHERE email IS NOT NULL)
                    AND a.sr_code NOT IN (SELECT sr_code FROM deleted_students WHERE sr_code IS NOT NULL)
                    AND a.email NOT IN (SELECT email FROM deleted_students WHERE email IS NOT NULL)
                ");
                $stmt->execute();
                $missingStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $addedCount = 0;
                foreach ($missingStudents as $student) {
                    $stmt = $pdo->prepare("INSERT INTO deleted_students (sr_code, email, deleted_by, reason) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$student['sr_code'], $student['email'], $_SESSION['admin_id'], 'Previously deleted by admin - added during sync']);
                    $addedCount++;
                }
                
                logAdminAction($pdo, $_SESSION['admin_id'], 'SYNC_DELETED', 0, "Synced $addedCount previously deleted students to tracking table");
                echo json_encode(['success' => true, 'message' => "Synced $addedCount previously deleted students to tracking table"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error syncing deleted students: ' . $e->getMessage()]);
            }
            exit();
            
        // ... other cases will be added here
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $_POST['action']]);
            exit();
    }
}

// Prevent any output before JSON response
ob_start();

// Simple authentication check - you can implement proper login later
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true; // For now, auto-login
    $_SESSION['admin_id'] = 1;
}

$pdo = getDBConnection();

// Get users from database
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Pagination variables
$users_per_page = 5;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$students_page = isset($_GET['students_page']) ? max(1, intval($_GET['students_page'])) : 1;

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all users - separate regular users and student artists
$regular_users = [];
$student_artists = [];

// Get regular users (excluding student role from regular users table)
$regular_where_conditions = [];
$regular_params = [];

if (!empty($search)) {
    $regular_where_conditions[] = "(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $regular_params[] = $search_param;
    $regular_params[] = $search_param;
    $regular_params[] = $search_param;
    $regular_params[] = $search_param;
}

if (!empty($role_filter) && $role_filter !== 'student') {
    $regular_where_conditions[] = "role = ?";
    $regular_params[] = $role_filter;
}

// Exclude student role from regular users table
$regular_where_conditions[] = "role != 'student'";

if (!empty($status_filter)) {
    $regular_where_conditions[] = "status = ?";
    $regular_params[] = $status_filter;
}

$regular_where_clause = 'WHERE ' . implode(' AND ', $regular_where_conditions);

// Get total count of regular users
$count_sql_users = "SELECT COUNT(*) FROM users $regular_where_clause";
$count_stmt = $pdo->prepare($count_sql_users);
$count_stmt->execute($regular_params);
$total_regular_users = $count_stmt->fetchColumn();

// Calculate pagination for regular users
$total_pages = ceil($total_regular_users / $users_per_page);
$offset = ($current_page - 1) * $users_per_page;

// Get paginated regular users
$sql_users = "SELECT id, first_name, middle_name, last_name, email, role, status, last_login, created_at, 'users' as source_table FROM users $regular_where_clause ORDER BY id ASC LIMIT $users_per_page OFFSET $offset";
$stmt = $pdo->prepare($sql_users);
$stmt->execute($regular_params);
$regular_users = $stmt->fetchAll();

// Get student artists
$student_where_conditions = [];
$student_params = [];

if (!empty($search)) {
    $student_where_conditions[] = "(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $student_params[] = $search_param;
    $student_params[] = $search_param;
    $student_params[] = $search_param;
    $student_params[] = $search_param;
}

if (!empty($status_filter)) {
    $student_where_conditions[] = "status = ?";
    $student_params[] = $status_filter;
}

$student_where_clause = !empty($student_where_conditions) ? 'WHERE ' . implode(' AND ', $student_where_conditions) : '';

// Get total count of student artists
$count_sql_students = "SELECT COUNT(*) FROM student_artists $student_where_clause";
$count_stmt = $pdo->prepare($count_sql_students);
$count_stmt->execute($student_params);
$total_student_artists = $count_stmt->fetchColumn();

// Calculate pagination for student artists
$total_students_pages = ceil($total_student_artists / $users_per_page);
$students_offset = ($students_page - 1) * $users_per_page;

// Get paginated student artists
$sql_students = "SELECT id, first_name, middle_name, last_name, email, 'student' as role, status, NULL as last_login, created_at, 'student_artists' as source_table FROM student_artists $student_where_clause ORDER BY id ASC LIMIT $users_per_page OFFSET $students_offset";
$stmt = $pdo->prepare($sql_students);
$stmt->execute($student_params);
$student_artists = $stmt->fetchAll();

// For backward compatibility, combine arrays for the main users variable
$all_users = array_merge($regular_users, $student_artists);

if (empty($all_users)) {
    $all_users = $regular_users;
}

$users = $regular_users; // Main table shows only regular users now

// Clean output buffer for HTML rendering
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Culture and Arts - BatStateU TNEU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        /* Header */
        .header {
            background: white;
            padding: 1rem 2rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-btn {
            background: #f8f9fa;
            color: #333;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
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

        /* Main Layout */
        .main-container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid #e0e0e0;
            padding: 2rem 0;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: block;
            padding: 1rem 2rem;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #f8f9fa;
            color: #e74c3c;
            border-right: 3px solid #e74c3c;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        /* User Management */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
        }

        .add-user-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        /* Search and Filters */
        .search-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .search-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
        }

        .filter-select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 1px solid #e0e0e0;
        }

        .users-table td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        /* Role Badges */
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .role-head {
            background: #007bff;
            color: white;
        }

        .role-central {
            background: #6f42c1;
            color: white;
        }

        .role-student {
            background: #28a745;
            color: white;
        }

        .role-staff {
            background: #ffc107;
            color: #333;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        /* Action Buttons */
        .action-btn {
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            margin: 0 0.2rem;
            font-weight: 500;
        }

        .btn-edit {
            background: #007bff;
            color: white;
        }

        .btn-suspend {
            background: #ffc107;
            color: #333;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-activate {
            background: #28a745;
            color: white;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .page-btn {
            display: inline-block;
            padding: 0.5rem 0.75rem;
            margin: 0 0.1rem;
            text-decoration: none;
            color: #007bff;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .page-btn:hover {
            background-color: #f8f9fa;
            text-decoration: none;
            color: #0056b3;
        }

        .page-btn.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
        }

        .pagination-info {
            color: #666;
            font-weight: 500;
        }

        /* Settings Form */
        .settings-form {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .save-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .search-filters {
                flex-direction: column;
                align-items: stretch;
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
            background-color: rgba(0,0,0,0.5);
            overflow: hidden; /* Prevent background scrolling */
        }

        .modal-content {
            background-color: white;
            margin: 2% auto 8% auto; /* Reduced top margin from 5% to 2%, increased bottom to 8% */
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh; /* Increased from 80vh to 90vh for more height */
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
            overflow-y: auto;
            position: relative;
            /* Hide scrollbar but keep functionality */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        .modal-content::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .modal-header {
            flex-shrink: 0; /* Header stays fixed */
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }

        #userForm,
        #editUserFormContent {
            padding: 2rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.3rem;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #e74c3c;
        }

        #userForm,
        #editUserFormContent {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #666;
            font-size: 0.85rem;
        }

        .show-password-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: -1.2rem;
            font-size: 0.9rem;
        }

        .show-password-container input[type="checkbox"] {
            cursor: pointer;
            accent-color: #dc2626;
        }

        .show-password-container label {
            cursor: pointer;
            color: #666;
            font-weight: normal;
        }

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .form-row .form-group {
            flex: 1;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Alert styles */
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 6px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Loading state */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Role badges for different roles */
        .role-staff {
            background: #ffc107;
            color: #333;
        }

        /* Status badges for suspended */
        .status-suspended {
            background: #f8d7da;
            color: #721c24;
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
            <button class="admin-btn">Admin</button>
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
                        <a href="#" class="nav-link active" data-section="users">User Management</a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="settings">System Settings</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- User Management Section -->
            <section class="content-section active" id="users">
                <div class="page-header">
                    <h1 class="page-title">User Management - System Users</h1>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <button class="notification-btn" id="notificationBtn" style="position: relative; background: #f0f0f0; border: 1px solid #ddd; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                            🔔
                            <span class="notification-badge" id="notificationBadge" style="position: absolute; top: -5px; right: -5px; background: #ff4757; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; font-weight: bold; display: none; align-items: center; justify-content: center;"></span>
                        </button>
                        <button class="add-user-btn">+ Add New User</button>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="search-filters">
                    <form method="GET" style="display: flex; gap: 1rem; align-items: center; width: 100%;">
                        <input type="text" name="search" placeholder="Search users..." class="search-input" value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="search-btn">🔍</button>
                        <select name="role" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <option value="head" <?= $role_filter === 'head' ? 'selected' : '' ?>>Head</option>
                            <option value="central" <?= $role_filter === 'central' ? 'selected' : '' ?>>Central</option>
                            <option value="staff" <?= $role_filter === 'staff' ? 'selected' : '' ?>>Staff</option>
                        </select>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </form>
                </div>

                <!-- Users Table -->
                <div class="table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem; color: #666;">
                                        <?php if (!empty($search) || !empty($role_filter) || !empty($status_filter)): ?>
                                            No users found matching your criteria.
                                        <?php else: ?>
                                            No users found. Click "Add New User" to create the first user.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr data-user-id="<?= $user['id'] ?>" data-source-table="<?= $user['source_table'] ?>">
                                        <td><?= str_pad($user['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                        <td><?= htmlspecialchars(trim($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name'])) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="role-badge role-<?= $user['role'] ?>">
                                                <?= strtoupper($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $user['status'] ?>">
                                                <?= strtoupper($user['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never' ?>
                                        </td>
                                        <td>
                                            <button class="action-btn btn-edit" onclick="editUser(<?= $user['id'] ?>, '<?= $user['source_table'] ?>')">Edit</button>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <button class="action-btn btn-suspend" onclick="suspendUser(<?= $user['id'] ?>, '<?= $user['source_table'] ?>')">Suspend</button>
                                            <?php else: ?>
                                                <button class="action-btn btn-activate" onclick="activateUser(<?= $user['id'] ?>, '<?= $user['source_table'] ?>')">Activate</button>
                                            <?php endif; ?>
                                            <button class="action-btn btn-delete" onclick="deleteUser(<?= $user['id'] ?>, '<?= $user['source_table'] ?>')">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- User Count and Pagination -->
                <div style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <div style="color: #666;">
                        Total Regular Users: <?= $total_regular_users ?> (Showing <?= count($users) ?> of <?= $total_regular_users ?>)
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php
                            $query_params = $_GET;
                            unset($query_params['page']);
                            $base_url = '?' . http_build_query($query_params);
                            if (empty($query_params)) $base_url = '?';
                            ?>
                            
                            <?php if ($current_page > 1): ?>
                                <a href="<?= $base_url ?><?= !empty($query_params) ? '&' : '' ?>page=<?= $current_page - 1 ?>" class="page-btn">← Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="page-btn active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="<?= $base_url ?><?= !empty($query_params) ? '&' : '' ?>page=<?= $i ?>" class="page-btn"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <a href="<?= $base_url ?><?= !empty($query_params) ? '&' : '' ?>page=<?= $current_page + 1 ?>" class="page-btn">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Student Artists Section -->
                <div style="margin-top: 3rem;">
                    <div class="page-header">
                        <h2 class="page-title" style="font-size: 1.5rem; margin-bottom: 1rem;">Student Artists</h2>
                    </div>

                    <!-- Student Artists Table -->
                    <div class="table-container">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Application Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($student_artists)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 2rem; color: #666;">
                                            <?php if (!empty($search) || !empty($status_filter)): ?>
                                                No student artists found matching your criteria.
                                            <?php else: ?>
                                                No student artists found yet.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($student_artists as $student): ?>
                                        <tr data-user-id="<?= $student['id'] ?>" data-source-table="<?= $student['source_table'] ?>">
                                            <td><?= str_pad($student['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                            <td><?= htmlspecialchars(trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'])) ?></td>
                                            <td><?= htmlspecialchars($student['email']) ?></td>
                                            <td>
                                                <span class="role-badge role-<?= $student['role'] ?>">
                                                    <?= strtoupper($student['role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $student['status'] ?>">
                                                    <?= strtoupper($student['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= $student['created_at'] ? date('Y-m-d H:i', strtotime($student['created_at'])) : 'N/A' ?>
                                            </td>
                                            <td>
                                                <button class="action-btn btn-edit" onclick="editUser(<?= $student['id'] ?>, '<?= $student['source_table'] ?>')">Edit</button>
                                                <?php if ($student['status'] === 'active'): ?>
                                                    <button class="action-btn btn-suspend" onclick="suspendUser(<?= $student['id'] ?>, '<?= $student['source_table'] ?>')">Suspend</button>
                                                <?php else: ?>
                                                    <button class="action-btn btn-activate" onclick="activateUser(<?= $student['id'] ?>, '<?= $student['source_table'] ?>')">Activate</button>
                                                <?php endif; ?>
                                                <button class="action-btn btn-delete" onclick="deleteUser(<?= $student['id'] ?>, '<?= $student['source_table'] ?>')">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Student Artists Count and Pagination -->
                    <div style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div style="color: #666;">
                            Total Student Artists: <?= $total_student_artists ?> (Showing <?= count($student_artists) ?> of <?= $total_student_artists ?>)
                        </div>
                        <?php if ($total_students_pages > 1): ?>
                            <div class="pagination">
                                <?php
                                $student_query_params = $_GET;
                                unset($student_query_params['students_page']);
                                $student_base_url = '?' . http_build_query($student_query_params);
                                if (empty($student_query_params)) $student_base_url = '?';
                                ?>
                                
                                <?php if ($students_page > 1): ?>
                                    <a href="<?= $student_base_url ?><?= !empty($student_query_params) ? '&' : '' ?>students_page=<?= $students_page - 1 ?>" class="page-btn">← Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_students_pages; $i++): ?>
                                    <?php if ($i == $students_page): ?>
                                        <span class="page-btn active"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="<?= $student_base_url ?><?= !empty($student_query_params) ? '&' : '' ?>students_page=<?= $i ?>" class="page-btn"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($students_page < $total_students_pages): ?>
                                    <a href="<?= $student_base_url ?><?= !empty($student_query_params) ? '&' : '' ?>students_page=<?= $students_page + 1 ?>" class="page-btn">Next →</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- System Settings Section -->
            <section class="content-section" id="settings">
                <div class="page-header">
                    <h1 class="page-title">System Settings</h1>
                </div>

                <form class="settings-form">
                    <div class="form-group">
                        <label class="form-label">System Name</label>
                        <input type="text" class="form-input" value="Culture and Arts - BatStateU TNEU">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Admin Email</label>
                        <input type="email" class="form-input" value="admin@g.batstate-u.edu.ph">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Session Timeout (minutes)</label>
                        <input type="number" class="form-input" value="30">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password Policy</label>
                        <select class="form-select">
                            <option>Basic (8 characters)</option>
                            <option selected>Medium (8 chars + mixed case)</option>
                            <option>Strong (8 chars + mixed case + numbers + symbols)</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="save-btn">Save Settings</button>
                </form>
            </section>
        </main>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="addUserForm" onsubmit="addUser(event)">
                <div id="userForm">
                    <div class="form-group">
                        <label for="firstName">First Name *</label>
                        <input type="text" id="firstName" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="middleName">Middle Name</label>
                        <input type="text" id="middleName" name="middle_name">
                    </div>
                    <div class="form-group">
                        <label for="lastName">Last Name *</label>
                        <input type="text" id="lastName" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required pattern=".*@g.batstate-u\.edu\.ph$" title="Must be a valid BatStateU email">
                        <small>Must be a valid @g.batstate-u.edu.ph email</small>
                    </div>
                    <div class="form-group" id="srCodeGroup" style="display: none;">
                        <label for="srCode">SR Code</label>
                        <input type="text" id="srCode" name="sr_code" pattern="^\d{2}-\d{5}$" title="Format: XX-XXXXX (e.g., 21-12345)">
                        <small>Format: XX-XXXXX (e.g., 21-12345)</small>
                    </div>
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required onchange="toggleSrCodeField()">
                            <option value="">Select Role</option>
                            <option value="head">Head</option>
                            <option value="central">Central</option>
                            <option value="student">Student</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required minlength="6" maxlength="12">
                        <small>Must be 6-12 characters with at least one letter and one number</small>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password *</label>
                        <input type="password" id="confirmPassword" name="confirm_password" required minlength="6" maxlength="12">
                    </div>
                    <div class="show-password-container">
                        <input type="checkbox" id="showAddPassword" onchange="toggleAddPasswords()">
                        <label for="showAddPassword">Show Password</label>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="save-btn">Add User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form id="editUserForm" onsubmit="updateUser(event)">
                <input type="hidden" id="editUserId" name="user_id">
                <input type="hidden" id="editSourceTable" name="source_table">
                <div id="editUserFormContent">
                    <div class="form-group">
                        <label for="editFirstName">First Name *</label>
                        <input type="text" id="editFirstName" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editMiddleName">Middle Name</label>
                        <input type="text" id="editMiddleName" name="middle_name">
                    </div>
                    <div class="form-group">
                        <label for="editLastName">Last Name *</label>
                        <input type="text" id="editLastName" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editEmail">Email *</label>
                        <input type="email" id="editEmail" name="email" required pattern=".*@g.batstate-u\.edu\.ph$" title="Must be a valid BatStateU email">
                        <small>Must be a valid @g.batstate-u.edu.ph email</small>
                    </div>
                    <div class="form-group">
                        <label for="editRole">Role *</label>
                        <select id="editRole" name="role" required>
                            <option value="">Select Role</option>
                            <option value="head">Head</option>
                            <option value="central">Central</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editPassword">New Password (optional)</label>
                        <input type="password" id="editPassword" name="password" minlength="6" maxlength="12">
                        <small>Leave blank to keep current password. Must be 6-12 characters with at least one letter and one number</small>
                    </div>
                    <div class="form-group">
                        <label for="editConfirmPassword">Confirm New Password</label>
                        <input type="password" id="editConfirmPassword" name="confirm_password" minlength="6" maxlength="12">
                    </div>
                    <div class="show-password-container">
                        <input type="checkbox" id="showEditPassword" onchange="toggleEditPasswords()">
                        <label for="showEditPassword">Show Password</label>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="save-btn">Update User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Notifications Modal -->
    <div id="notificationsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Approved Applications</h3>
                <span class="close" onclick="closeNotificationsModal()">&times;</span>
            </div>
            <div id="notificationsContent">
                <p>Loading approved applications...</p>
            </div>
        </div>
    </div>

    <script>
        // Navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            const contentSections = document.querySelectorAll('.content-section');

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
                });
            });
        });

        // Modal Functions
        function openModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('addUserModal').style.display = 'none';
            document.getElementById('addUserForm').reset();
        }

        function openEditModal() {
            document.getElementById('editUserModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editUserModal').style.display = 'none';
            document.getElementById('editUserForm').reset();
        }

        // Click outside modal to close
        window.onclick = function(event) {
            const addModal = document.getElementById('addUserModal');
            const editModal = document.getElementById('editUserModal');
            if (event.target === addModal) {
                closeModal();
            } else if (event.target === editModal) {
                closeEditModal();
            }
        }

        // Add User Function
        function addUser(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'add_user');
            
            // Validate password match
            if (formData.get('password') !== formData.get('confirm_password')) {
                alert('Passwords do not match!');
                return;
            }

            // Show loading state
            const submitBtn = event.target.querySelector('.save-btn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Adding...';
            submitBtn.disabled = true;

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User added successfully!');
                    closeModal();
                    location.reload(); // Refresh to show new user
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error adding user: ' + error.message);
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }

        // Edit User Function
        function editUser(userId, sourceTable = 'users') {
            // Fetch user data
            const formData = new FormData();
            formData.append('action', 'get_user');
            formData.append('user_id', userId);
            formData.append('source_table', sourceTable);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const user = data.user;
                    
                    // Populate edit form
                    document.getElementById('editUserId').value = user.id;
                    document.getElementById('editSourceTable').value = sourceTable;
                    document.getElementById('editFirstName').value = user.first_name;
                    document.getElementById('editMiddleName').value = user.middle_name || '';
                    document.getElementById('editLastName').value = user.last_name;
                    document.getElementById('editEmail').value = user.email;
                    document.getElementById('editRole').value = user.role;
                    document.getElementById('editPassword').value = '';
                    document.getElementById('editConfirmPassword').value = '';
                    
                    openEditModal();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error fetching user data: ' + error.message);
            });
        }

        // Update User Function
        function updateUser(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'update_user');
            
            // Validate password match if password is provided
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            if (password && password !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }

            // Show loading state
            const submitBtn = event.target.querySelector('.save-btn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Updating...';
            submitBtn.disabled = true;

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User updated successfully!');
                    closeEditModal();
                    location.reload(); // Refresh to show updated data
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating user: ' + error.message);
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }

        // User Management Functions
        function suspendUser(userId, sourceTable = 'users') {
            if (confirm('Are you sure you want to suspend this user?')) {
                updateUserStatus(userId, 'suspended', sourceTable);
            }
        }

        function activateUser(userId, sourceTable = 'users') {
            if (confirm('Are you sure you want to activate this user?')) {
                updateUserStatus(userId, 'active', sourceTable);
            }
        }

        function deleteUser(userId, sourceTable = 'users') {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('user_id', userId);
                formData.append('source_table', sourceTable);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('User deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting user: ' + error.message);
                });
            }
        }

        function updateUserStatus(userId, status, sourceTable = 'users') {
            const actionMap = {
                'suspended': 'suspend_user',
                'active': 'activate_user'
            };
            
            const formData = new FormData();
            formData.append('action', actionMap[status]);
            formData.append('user_id', userId);
            formData.append('source_table', sourceTable);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`User ${status} successfully!`);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating user: ' + error.message);
            });
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../index.php';
            }
        }

        // Settings form submission
        document.querySelector('.settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Settings saved successfully!');
        });

        // Add event listeners for action buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('add-user-btn')) {
                openModal();
            }
        });

        // Notification functions
        function loadNotifications() {
            console.log('Loading notifications...');
            fetch('get_approved_applications.php')
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        updateNotificationBadge(data.count);
                        displayNotifications(data.applications);
                    } else if (data.error) {
                        console.error('API Error:', data.error);
                        document.getElementById('notificationsContent').innerHTML = 
                            '<p style="color: red;">Error: ' + data.error + '</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    document.getElementById('notificationsContent').innerHTML = 
                        '<p style="color: red;">Error loading notifications: ' + error.message + '</p>';
                });
        }

        function updateNotificationBadge(count) {
            const badge = document.getElementById('notificationBadge');
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        function displayNotifications(applications) {
            const content = document.getElementById('notificationsContent');
            if (applications.length === 0) {
                content.innerHTML = '<p style="text-align: center; color: #666;">No approved applications pending user creation.</p>';
                return;
            }

            content.innerHTML = applications.map(app => `
                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; background: #f9f9f9;">
                    <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 0.5rem;">
                        <h4 style="margin: 0; color: #333;">${app.full_name}</h4>
                        <span style="font-size: 0.9em; color: #666;">Approved: ${app.approved_at}</span>
                    </div>
                    <p style="margin: 0.5rem 0; color: #666;"><strong>SR Code:</strong> ${app.sr_code}</p>
                    <p style="margin: 0.5rem 0; color: #666;"><strong>Email:</strong> ${app.email}</p>
                    <p style="margin: 0.5rem 0; color: #666;"><strong>Reviewed by:</strong> ${app.reviewer_name || 'Unknown'}</p>
                    <button onclick="createUserFromApplication('${app.sr_code}', '${app.first_name}', '${app.middle_name}', '${app.last_name}', '${app.email}')" 
                            style="background: #007bff; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; margin-top: 0.5rem;">
                        Create User Account
                    </button>
                </div>
            `).join('');
        }

        function createUserFromApplication(srCode, firstName, middleName, lastName, email) {
            // Close notifications modal
            closeNotificationsModal();
            
            // Pre-fill add user modal
            document.getElementById('firstName').value = firstName || '';
            document.getElementById('middleName').value = middleName || '';
            document.getElementById('lastName').value = lastName || '';
            document.getElementById('email').value = email;
            document.getElementById('srCode').value = srCode;
            document.getElementById('role').value = 'student';
            document.getElementById('password').value = srCode;
            document.getElementById('confirmPassword').value = srCode;
            
            // Show SR Code field
            document.getElementById('srCodeGroup').style.display = 'block';
            
            // Open add user modal
            openModal();
        }

        function toggleSrCodeField() {
            const role = document.getElementById('role').value;
            const srCodeGroup = document.getElementById('srCodeGroup');
            const srCodeInput = document.getElementById('srCode');
            
            if (role === 'student') {
                srCodeGroup.style.display = 'block';
                srCodeInput.required = true;
            } else {
                srCodeGroup.style.display = 'none';
                srCodeInput.required = false;
                srCodeInput.value = '';
            }
        }

        function openNotificationsModal() {
            document.getElementById('notificationsModal').style.display = 'block';
            loadNotifications();
        }

        function closeNotificationsModal() {
            document.getElementById('notificationsModal').style.display = 'none';
        }

        // Add notification button event listener
        document.getElementById('notificationBtn').addEventListener('click', openNotificationsModal);

        // Show/hide password functions
        function toggleAddPasswords() {
            const checkbox = document.getElementById('showAddPassword');
            const passwordFields = ['password', 'confirmPassword'];
            
            passwordFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.type = checkbox.checked ? 'text' : 'password';
                }
            });
        }

        function toggleEditPasswords() {
            const checkbox = document.getElementById('showEditPassword');
            const passwordFields = ['editPassword', 'editConfirmPassword'];
            
            passwordFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.type = checkbox.checked ? 'text' : 'password';
                }
            });
        }

        // Enhanced password validation (for user self-service, not admin)
        function validatePassword(password) {
            const hasLetter = /[A-Za-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const validLength = password.length >= 6 && password.length <= 12;
            
            return {
                valid: hasLetter && hasNumber && validLength,
                hasLetter,
                hasNumber,
                validLength
            };
        }

        // Simple admin password validation (no complexity requirements)
        function validateAdminPassword(password) {
            return {
                valid: password.length > 0,
                hasLetter: true,
                hasNumber: true,
                validLength: password.length > 0
            };
        }

        // Add password validation listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Add User Form validation (use simple validation for admin)
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirmPassword');
            
            if (passwordField) {
                passwordField.addEventListener('input', function() {
                    const password = this.value;
                    const validation = validateAdminPassword(password); // Use simple validation for admin
                    
                    if (password && !validation.validLength) {
                        this.setCustomValidity('Password is required');
                    } else {
                        this.setCustomValidity('');
                    }
                    
                    // Check password confirmation match
                    if (confirmPasswordField && confirmPasswordField.value && password !== confirmPasswordField.value) {
                        confirmPasswordField.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPasswordField.setCustomValidity('');
                    }
                });
            }
            
            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', function() {
                    const password = passwordField.value;
                    const confirmPassword = this.value;
                    
                    if (password !== confirmPassword) {
                        this.setCustomValidity('Passwords do not match');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            // Edit User Form validation
            const editPasswordField = document.getElementById('editPassword');
            const editConfirmPasswordField = document.getElementById('editConfirmPassword');
            
            if (editPasswordField) {
                editPasswordField.addEventListener('input', function() {
                    const password = this.value;
                    if (password) { // Only validate if password is provided
                        const validation = validatePassword(password);
                        
                        if (!validation.validLength) {
                            this.setCustomValidity('Password must be 6-12 characters long');
                        } else if (!validation.hasLetter) {
                            this.setCustomValidity('Password must contain at least one letter');
                        } else if (!validation.hasNumber) {
                            this.setCustomValidity('Password must contain at least one number');
                        } else {
                            this.setCustomValidity('');
                        }
                        
                        // Check password confirmation match
                        if (editConfirmPasswordField && editConfirmPasswordField.value && password !== editConfirmPasswordField.value) {
                            editConfirmPasswordField.setCustomValidity('Passwords do not match');
                        } else if (validation.valid) {
                            editConfirmPasswordField.setCustomValidity('');
                        }
                    } else {
                        this.setCustomValidity('');
                        editConfirmPasswordField.setCustomValidity('');
                    }
                });
            }
            
            if (editConfirmPasswordField) {
                editConfirmPasswordField.addEventListener('input', function() {
                    const password = editPasswordField.value;
                    const confirmPassword = this.value;
                    
                    if (password && password !== confirmPassword) {
                        this.setCustomValidity('Passwords do not match');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            loadNotifications();
            // Refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);
        });
    </script>
</body>
</html>

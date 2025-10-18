<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'capstone_culture_arts');
define('DB_USER', 'root');  // Change this to your MySQL username
define('DB_PASS', '');      // Change this to your MySQL password

// Create database connection
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Function to log admin actions
function logAdminAction($pdo, $admin_id, $action, $target_user_id = null, $details = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_user_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$admin_id, $action, $target_user_id, $details, $ip_address]);
}
?>
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
    try {
        // Create admin_logs table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT,
            action VARCHAR(100),
            target_user_id INT,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_user_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$admin_id, $action, $target_user_id, $details, $ip_address]);
    } catch (Exception $e) {
        // Log to error log if database logging fails
        error_log("Admin action logging failed: " . $e->getMessage());
    }
}
?>
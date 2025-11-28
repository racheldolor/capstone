<?php
/**
 * Database Configuration
 * 
 * IMPORTANT: Update these constants based on your hosting environment:
 * 
 * For localhost/XAMPP:
 *   DB_HOST = 'localhost'
 *   DB_USER = 'root'
 *   DB_PASS = ''
 * 
 * For shared hosting (e.g., cPanel, Hostinger, etc.):
 *   DB_HOST = 'localhost' or your hosting provider's database server
 *   DB_USER = your database username from hosting control panel
 *   DB_PASS = your database password from hosting control panel
 * 
 * For cloud hosting (e.g., AWS RDS, DigitalOcean):
 *   DB_HOST = database endpoint/IP address (e.g., 'mydb.abc123.us-east-1.rds.amazonaws.com')
 *   DB_USER = your database username
 *   DB_PASS = your database password
 * 
 * For remote database servers:
 *   DB_HOST = IP address or domain name of your database server
 *   DB_USER = your database username
 *   DB_PASS = your database password
 */

// Database connection constants
define('DB_HOST', 'localhost');  // Change to your database host
define('DB_NAME', 'capstone_culture_arts');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password

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
        // Check if we're in a transaction and save the state
        $inTransaction = $pdo->inTransaction();
        
        // If we're in a transaction, we'll defer the table creation
        // and just try to insert first
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        try {
            $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_user_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$admin_id, $action, $target_user_id, $details, $ip_address]);
        } catch (Exception $e) {
            // If insert fails and we're not in a transaction, try to create table
            if (!$inTransaction && strpos($e->getMessage(), "doesn't exist") !== false) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_id INT,
                    action VARCHAR(100),
                    target_user_id INT,
                    details TEXT,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                // Try insert again
                $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_user_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$admin_id, $action, $target_user_id, $details, $ip_address]);
            } else {
                throw $e;
            }
        }
    } catch (Exception $e) {
        // Log to error log if database logging fails
        error_log("Admin action logging failed: " . $e->getMessage());
    }
}
?>

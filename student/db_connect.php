<?php
// Use centralized database configuration
require_once __DIR__ . '/../config/database.php';

try {
    // Get PDO connection from centralized config
    $pdo = getDBConnection();
    
    // For backward compatibility, create MySQLi connection if needed
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Log error and show user-friendly message
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}
?>
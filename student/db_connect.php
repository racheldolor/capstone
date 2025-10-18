<?php
// Database connection configuration
$servername = "localhost";
$username = "root";
$password = ""; // Default XAMPP MySQL password is empty
$dbname = "capstone_culture_arts";

try {
    // Create connection using MySQLi
    $conn = new mysqli($servername, $username, $password, $dbname);
    
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
<?php
require_once 'config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "Checking existing student passwords...\n\n";
    
    $stmt = $pdo->query('SELECT id, sr_code, email, password FROM student_artists LIMIT 10');
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . "\n";
        echo "SR Code: " . $row['sr_code'] . "\n";
        echo "Email: " . $row['email'] . "\n";
        echo "Password starts with: " . substr($row['password'], 0, 7) . "...\n";
        
        // Test if password matches SR code
        if (password_verify($row['sr_code'], $row['password'])) {
            echo "✓ Password matches SR Code\n";
        } else {
            echo "✗ Password does NOT match SR Code\n";
        }
        echo "------------------------\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
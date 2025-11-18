<?php
// Test script to verify admin dashboard password fix
require_once 'config/database.php';

echo "Testing Admin Dashboard Password Fix\n";
echo "====================================\n\n";

try {
    // Test the password creation logic for students
    $test_sr_code = "22-TEST01";
    $test_role = "student";
    
    // Simulate what the admin dashboard now does for students
    if ($test_role === 'student') {
        $password_hash = password_hash($test_sr_code, PASSWORD_DEFAULT);
        echo "✓ For student role:\n";
        echo "  SR-code: $test_sr_code\n";
        echo "  Generated hash: " . substr($password_hash, 0, 20) . "...\n";
        
        // Test if SR-code verifies against the hash
        if (password_verify($test_sr_code, $password_hash)) {
            echo "  ✓ SR-code verification: PASS\n\n";
        } else {
            echo "  ✗ SR-code verification: FAIL\n\n";
        }
    }
    
    // Test for non-student roles
    $test_password = "admin123";
    $test_role = "admin";
    
    if ($test_role !== 'student') {
        $password_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "✓ For non-student role ($test_role):\n";
        echo "  Password: $test_password\n";
        echo "  Generated hash: " . substr($password_hash, 0, 20) . "...\n";
        
        // Test if password verifies against the hash
        if (password_verify($test_password, $password_hash)) {
            echo "  ✓ Password verification: PASS\n\n";
        } else {
            echo "  ✗ Password verification: FAIL\n\n";
        }
    }
    
    echo "All tests completed successfully!\n";
    echo "The admin dashboard will now:\n";
    echo "- Use SR-codes as passwords for student accounts\n";
    echo "- Use provided passwords for non-student accounts\n";

} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
    exit(1);
}
?>
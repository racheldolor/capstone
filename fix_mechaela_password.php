<?php
// fix_mechaela_password.php
// Sets Mechaela Maranan's password to her SR-code so she can login with SR-code

require_once 'config/database.php';

echo "Fixing Mechaela Maranan's password to match her SR-code...\n";

try {
    $pdo = getDBConnection();
    
    // Mechaela's details from database
    $email = '22-45416@g.batstate-u.edu.ph';
    $srCode = '22-45416';
    $name = 'Mechaela Allysa Maranan';
    
    // First, let's verify her current record
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, sr_code, email FROM student_artists WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "ERROR: User not found with email: $email\n";
        exit(1);
    }
    
    echo "Found user:\n";
    echo "  ID: {$user['id']}\n";
    echo "  Name: {$user['first_name']} {$user['last_name']}\n";
    echo "  SR-Code: {$user['sr_code']}\n";
    echo "  Email: {$user['email']}\n\n";
    
    // Hash the SR-code as the new password
    $newPasswordHash = password_hash($srCode, PASSWORD_DEFAULT);
    
    echo "Setting password to SR-code: $srCode\n";
    echo "New password hash: $newPasswordHash\n\n";
    
    // Update the password
    $stmt = $pdo->prepare("UPDATE student_artists SET password = ?, updated_at = NOW() WHERE email = ?");
    $result = $stmt->execute([$newPasswordHash, $email]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo "✅ SUCCESS: Password updated successfully!\n";
        echo "\nMechaela can now login with:\n";
        echo "  Email: $email\n";
        echo "  Password: $srCode\n\n";
        
        // Test the password verification
        echo "Testing password verification...\n";
        if (password_verify($srCode, $newPasswordHash)) {
            echo "✅ Password verification test PASSED\n";
        } else {
            echo "❌ Password verification test FAILED\n";
        }
        
    } else {
        echo "❌ ERROR: No rows were updated. Check the email or database connection.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Summary ===\n";
echo "If successful, Mechaela Maranan can now login with:\n";
echo "Email: 22-45416@g.batstate-u.edu.ph\n";
echo "Password: 22-45416 (her SR-code)\n";
?>
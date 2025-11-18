<?php
// fix_all_student_passwords.php
// Sets ALL student artists' passwords to their SR-codes

require_once 'config/database.php';

echo "Setting ALL student artists' passwords to their SR-codes...\n\n";

try {
    $pdo = getDBConnection();
    
    // Get all student artists
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, sr_code, email FROM student_artists WHERE status = 'active'");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
        echo "No active student artists found.\n";
        exit(0);
    }
    
    echo "Found " . count($students) . " active student artists:\n";
    echo "========================================\n";
    
    $updateCount = 0;
    $errorCount = 0;
    
    foreach ($students as $student) {
        $id = $student['id'];
        $name = trim($student['first_name'] . ' ' . $student['last_name']);
        $srCode = $student['sr_code'];
        $email = $student['email'];
        
        echo "Processing: $name (SR: $srCode)\n";
        
        if (empty($srCode)) {
            echo "  ❌ SKIP: No SR-code found\n\n";
            $errorCount++;
            continue;
        }
        
        try {
            // Hash the SR-code as the password
            $passwordHash = password_hash($srCode, PASSWORD_DEFAULT);
            
            // Update the password
            $updateStmt = $pdo->prepare("UPDATE student_artists SET password = ?, updated_at = NOW() WHERE id = ?");
            $result = $updateStmt->execute([$passwordHash, $id]);
            
            if ($result && $updateStmt->rowCount() > 0) {
                echo "  ✅ SUCCESS: Password set to SR-code ($srCode)\n";
                
                // Verify the password works
                if (password_verify($srCode, $passwordHash)) {
                    echo "  ✅ Password verification: PASSED\n";
                } else {
                    echo "  ❌ Password verification: FAILED\n";
                }
                
                $updateCount++;
            } else {
                echo "  ❌ ERROR: Failed to update password\n";
                $errorCount++;
            }
            
        } catch (Exception $e) {
            echo "  ❌ ERROR: " . $e->getMessage() . "\n";
            $errorCount++;
        }
        
        echo "\n";
    }
    
    echo "========================================\n";
    echo "SUMMARY:\n";
    echo "✅ Successfully updated: $updateCount students\n";
    echo "❌ Errors/Skipped: $errorCount students\n";
    echo "📊 Total processed: " . count($students) . " students\n\n";
    
    if ($updateCount > 0) {
        echo "🎉 All updated students can now login with:\n";
        echo "   Email: [their email address]\n";
        echo "   Password: [their SR-code]\n\n";
        
        echo "Example logins:\n";
        foreach ($students as $student) {
            if (!empty($student['sr_code'])) {
                echo "   Email: {$student['email']}\n";
                echo "   Password: {$student['sr_code']}\n";
                echo "   ---\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Default Password Policy ===\n";
echo "From now on, all student artists should login with:\n";
echo "- Email: [their email from student_artists table]\n";
echo "- Password: [their SR-code]\n";
echo "\nFor new student registrations, make sure to hash their SR-code as the default password.\n";
?>
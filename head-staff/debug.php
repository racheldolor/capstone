<?php
require_once '../config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>Database Debug Information</h2>";
    
    // Check if student_artists table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'student_artists'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p>✓ student_artists table exists</p>";
        
        // Check table structure
        echo "<h3>Table Structure:</h3>";
        $stmt = $pdo->prepare("DESCRIBE student_artists");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        foreach ($columns as $column) {
            echo $column['Field'] . " - " . $column['Type'] . "\n";
        }
        echo "</pre>";
        
        // Count total records
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM student_artists");
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<p>Total records in student_artists: " . $total . "</p>";
        
        // Count active records
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM student_artists WHERE status = 'active'");
        $stmt->execute();
        $active = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<p>Active records: " . $active . "</p>";
        
        // Show all records
        echo "<h3>All Records:</h3>";
        $stmt = $pdo->prepare("SELECT id, sr_code, first_name, last_name, email, status FROM student_artists");
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($records)) {
            echo "<p>No records found in student_artists table</p>";
        } else {
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>SR Code</th><th>Name</th><th>Email</th><th>Status</th></tr>";
            foreach ($records as $record) {
                echo "<tr>";
                echo "<td>" . $record['id'] . "</td>";
                echo "<td>" . $record['sr_code'] . "</td>";
                echo "<td>" . $record['first_name'] . " " . $record['last_name'] . "</td>";
                echo "<td>" . $record['email'] . "</td>";
                echo "<td>" . $record['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<p>❌ student_artists table does not exist</p>";
        
        // Check if applications table exists and has approved records
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'applications'");
        $stmt->execute();
        $appTableExists = $stmt->fetch();
        
        if ($appTableExists) {
            echo "<p>✓ applications table exists</p>";
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM applications WHERE application_status = 'approved'");
            $stmt->execute();
            $approved = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo "<p>Approved applications: " . $approved . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'central', 'admin', 'director'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

try {
    // Get cultural groups - prioritize database records, fallback to hardcoded list
    $pdo = getDBConnection();
    
    // Try to get unique cultural groups from student_artists table
    $stmt = $pdo->prepare("
        SELECT DISTINCT cultural_group 
        FROM student_artists 
        WHERE cultural_group IS NOT NULL AND cultural_group != '' 
        ORDER BY cultural_group ASC
    ");
    $stmt->execute();
    $dbGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Hardcoded cultural groups (complete list)
    $hardcodedGroups = [
        'Dulaang Batangan',
        'BatStateU Dance Company', 
        'Diwayanis Dance Theatre',
        'BatStateU Band',
        'Indak Yaman Dance Varsity',
        'Ritmo Voice',
        'Sandugo Dance Group',
        'Areglo Band',
        'Teatro Aliwana',
        'The Levites',
        'Melophiles',
        'Sindayog'
    ];
    
    // Merge and deduplicate (database records + hardcoded)
    $allGroups = array_unique(array_merge($dbGroups, $hardcodedGroups));
    sort($allGroups);
    
    echo json_encode([
        'success' => true,
        'cultural_groups' => array_values($allGroups)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching cultural groups: ' . $e->getMessage()
    ]);
}
?>

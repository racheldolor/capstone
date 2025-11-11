<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Database connection
    $host = 'localhost';
    $dbname = 'capstone_culture_arts';
    $username = 'root';
    $password = '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';

    // Build base query for available items (status = 'available')
    $costumeQuery = "SELECT 
                        id, 
                        name, 
                        condition_status as `condition`, 
                        status,
                        'costume' as type
                     FROM inventory 
                     WHERE status = 'available'";
    
    $equipmentQuery = "SELECT 
                         id, 
                         name, 
                         condition_status as `condition`, 
                         status,
                         'equipment' as type
                       FROM inventory 
                       WHERE status = 'available' AND category = 'equipment'";

    $costumeParams = [];
    $equipmentParams = [];

    // Add search filter if provided
    if (!empty($search)) {
        $costumeQuery .= " AND name LIKE :search";
        $equipmentQuery .= " AND name LIKE :search";
        $costumeParams['search'] = "%$search%";
        $equipmentParams['search'] = "%$search%";
    }

    // Add category filter for costumes (exclude equipment category)
    $costumeQuery .= " AND (category != 'equipment' OR category IS NULL)";

    // Order results
    $costumeQuery .= " ORDER BY name ASC";
    $equipmentQuery .= " ORDER BY name ASC";

    $costumes = [];
    $equipment = [];

    // Fetch costumes if no category filter or category is 'costume'
    if (empty($category) || $category === 'costume') {
        $stmt = $pdo->prepare($costumeQuery);
        $stmt->execute($costumeParams);
        $costumes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch equipment if no category filter or category is 'equipment'
    if (empty($category) || $category === 'equipment') {
        $stmt = $pdo->prepare($equipmentQuery);
        $stmt->execute($equipmentParams);
        $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Return the results
    echo json_encode([
        'success' => true,
        'costumes' => $costumes,
        'equipment' => $equipment,
        'total_costumes' => count($costumes),
        'total_equipment' => count($equipment)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
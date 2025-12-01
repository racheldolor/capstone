<?php
session_start();
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
        throw new Exception('Unauthorized access');
    }

    require_once __DIR__ . '/../config/database.php';
    $pdo = getDBConnection();

    $item_id = $_GET['item_id'] ?? null;
    
    if (!$item_id) {
        throw new Exception('Item ID is required');
    }

    // Get item details
    $stmt = $pdo->prepare("
        SELECT 
            id,
            item_name,
            quantity,
            available_quantity,
            category,
            status
        FROM inventory 
        WHERE id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        throw new Exception('Item not found');
    }

    // Get all borrowers for this item
    $borrowers = []; // Initialize empty array
    $stmt = $pdo->prepare("
        SELECT 
            br.id,
            br.student_id,
            br.student_name,
            br.student_email,
            br.dates_of_use as borrow_date,
            br.due_date,
            br.created_at,
            br.current_status,
            br.approved_items
        FROM borrowing_requests br
        WHERE br.status = 'approved'
        AND br.current_status IN ('active', 'pending_return')
        ORDER BY br.created_at DESC
    ");
    $stmt->execute();
    $all_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate available quantity and fix total quantity if needed
    $total_borrowed_qty = 0;
    
    // Filter requests that include this specific item
    foreach ($all_requests as $request) {
        if (!empty($request['approved_items'])) {
            $approved_items = json_decode($request['approved_items'], true);
            
            if (is_array($approved_items)) {
                foreach ($approved_items as $approved_item) {
                    if (isset($approved_item['id']) && $approved_item['id'] == $item_id) {
                        $quantity = $approved_item['quantity'] ?? 1;
                        $total_borrowed_qty += $quantity;
                        
                        $borrowers[] = [
                            'request_id' => $request['id'],
                            'student_id' => $request['student_id'],
                            'student_name' => $request['student_name'],
                            'student_email' => $request['student_email'],
                            'quantity' => $quantity,
                            'borrow_date' => $request['borrow_date'],
                            'due_date' => $request['due_date'],
                            'current_status' => $request['current_status']
                        ];
                    }
                }
            }
        }
    }
    
    // Use the correct quantities from database
    // quantity = total quantity (NEVER changes - this is the original amount)
    // available_quantity = what's currently available to borrow
    // borrowed = total - available
    $total_quantity = intval($item['quantity']); // This should NEVER be modified
    $available_quantity = intval($item['available_quantity']);
    $calculated_borrowed = $total_quantity - $available_quantity;
    
    $item['total_quantity'] = $total_quantity;
    $item['available_quantity'] = $available_quantity;
    $item['borrowed_quantity'] = $calculated_borrowed;
    
    echo json_encode([
        'success' => true,
        'item' => $item,
        'borrowers' => $borrowers,
        'total_borrowers' => count($borrowers)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

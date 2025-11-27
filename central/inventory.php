<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['central', 'admin'])) {
    header('Location: ../index.php');
    exit();
}

// RBAC: Get user's campus and determine access level
$user_role = $_SESSION['user_role'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_campus = $_SESSION['user_campus'] ?? null;

// Central Head emails (view-only access)
$centralHeadEmails = ['mark.central@g.batstate-u.edu.ph', 'centralhead@g.batstate-u.edu.ph'];
$isCentralHead = ($user_role === 'central' && in_array($user_email, $centralHeadEmails));
$isCentralStaff = ($user_role === 'central' && !$isCentralHead);
$canViewAll = in_array($user_role, ['central', 'admin']) || $isCentralHead || $isCentralStaff;
$canManage = !$isCentralHead; // Central Head is view-only

$pdo = getDBConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Culture and Arts - BatStateU TNEU</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }

        /* Header */
        .header {
            background: white;
            color: #dc2626;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #e0e0e0;
            width: 100%;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .header-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            color: #333;
        }
        
        .user-info span {
            white-space: nowrap;
        }

        .logout-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #b91c1c;
        }

        /* Main Container */
        .main-container {
            display: flex;
            min-height: calc(100vh - 70px);
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            max-width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            min-height: calc(100vh - 70px);
            position: fixed;
            top: 70px;
            left: 0;
            z-index: 50;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .nav-menu {
            list-style: none;
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.5rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #555;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background: #f8f9fa;
            color: #dc2626;
            border-left-color: #dc2626;
        }

        .nav-link.active {
            background: #fee2e2;
            color: #dc2626;
            border-left-color: #dc2626;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            max-width: calc(100vw - 280px);
            overflow-y: auto;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #333;
        }

        .add-btn {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .add-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Inventory Grid */
        .inventory-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            width: 100%;
        }

        .inventory-panel {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .panel-title-section {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
        }

        .panel-title {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .table-header {
            display: grid;
            grid-template-columns: 2fr 60px 90px 90px 1.5fr;
            font-size: 0.8rem;
            padding: 0.75rem 0.5rem;
            background: #dc2626;
            color: white;
            font-weight: 600;
        }

        .table-header > div {
            padding: 0 0.3rem;
        }

        .table-header > div:nth-child(2),
        .table-header > div:nth-child(3),
        .table-header > div:nth-child(4) {
            text-align: center;
        }

        .table-body {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 200px;
        }

        .table-row {
            display: grid;
            grid-template-columns: 2fr 60px 90px 90px 1.5fr;
            padding: 0.65rem 0.5rem;
            border-bottom: 1px solid #e0e0e0;
            align-items: center;
            font-size: 0.85rem;
        }

        .table-row > div {
            padding: 0 0.3rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.3;
        }

        .table-row > div:nth-child(1) {
            font-weight: 500;
        }

        .table-row > div:nth-child(2) {
            text-align: center;
            font-weight: 600;
        }

        .table-row > div:nth-child(3),
        .table-row > div:nth-child(4) {
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .empty-state {
            padding: 2rem;
            text-align: center;
            color: #666;
        }

        /* Badges */
        .badge {
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            white-space: nowrap;
            display: inline-block;
            font-weight: 600;
        }

        .badge-good {
            background: #28a745;
            color: white;
        }

        .badge-worn {
            background: #dc3545;
            color: white;
        }

        .badge-available {
            background: #28a745;
            color: white;
        }

        .badge-borrowed {
            background: #6c757d;
            color: white;
        }

        .badge-maintenance {
            background: #fd7e14;
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
            max-width: 600px;
            width: 90%;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.5rem;
        }

        .close {
            color: #666;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close:hover {
            color: #ff5a5a;
            background-color: #f0f0f0;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
            font-family: inherit;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
        }

        .btn-primary:hover {
            background: #b91c1c;
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <img src="../assets/OCA Logo.png" alt="BatStateU Logo" class="logo">
            <h1 class="header-title">Culture and Arts - Central Dashboard</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span>👤</span>
                <?php 
                $first_name = explode(' ', $_SESSION['user_name'])[0];
                $role_display = strtoupper($user_role);
                ?>
                <span><?= htmlspecialchars($first_name) ?></span>
                <span style="background: #6366f1; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; margin-left: 10px; font-weight: 600;"><?= $role_display ?></span>
                <?php if ($isCentralHead): ?>
                    <span style="background: #ff9800; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; margin-left: 10px; font-weight: 600;">VIEW ONLY</span>
                <?php endif; ?>
                <?php if ($canViewAll && !$isCentralHead): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; margin-left: 10px; font-weight: 600;">ALL CAMPUSES</span>
                <?php elseif (!$canViewAll && $user_campus): ?>
                    <span style="background: #2196f3; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; margin-left: 10px; font-weight: 600;"><?= htmlspecialchars($user_campus) ?></span>
                <?php endif; ?>
            </div>
            <button class="logout-btn" onclick="logout()">Logout</button>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php?section=student-profiles" class="nav-link">
                            Student Artist Profiles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php?section=events-trainings" class="nav-link">
                            Events & Trainings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php?section=reports-analytics" class="nav-link">
                            Reports & Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="inventory.php" class="nav-link active">
                            Inventory
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Inventory</h1>
                <div style="display: flex; gap: 1rem;">
                    <button class="add-btn" onclick="openAddItemModal()" <?= $isCentralHead ? 'disabled' : '' ?>>
                        <span>+</span>
                        Add Item
                    </button>
                    <button class="add-btn" onclick="openBorrowRequests()">
                        Borrow Requests
                    </button>
                    <button class="add-btn" onclick="openReturns()">
                        Returns
                    </button>
                </div>
            </div>

            <!-- Inventory Grid -->
            <div class="inventory-grid">
                <!-- Costumes Table -->
                <div>
                    <div class="inventory-panel">
                        <div class="panel-title-section">
                            <h3 class="panel-title">Costumes</h3>
                        </div>
                        <div class="table-header">
                            <div>NAME</div>
                            <div>QTY</div>
                            <div>CONDITION</div>
                            <div>STATUS</div>
                            <div>BORROWER INFO</div>
                        </div>
                        <div class="table-body" id="costumesTableBody">
                            <div class="empty-state">
                                <p>Loading costumes...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Equipment Table -->
                <div>
                    <div class="inventory-panel">
                        <div class="panel-title-section">
                            <h3 class="panel-title">Equipment</h3>
                        </div>
                        <div class="table-header">
                            <div>NAME</div>
                            <div>QTY</div>
                            <div>CONDITION</div>
                            <div>STATUS</div>
                            <div>BORROWER INFO</div>
                        </div>
                        <div class="table-body" id="equipmentTableBody">
                            <div class="empty-state">
                                <p>Loading equipment...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </main>
    </div>

    <!-- Add Item Modal -->
    <div id="addItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Costume/Equipment</h2>
                <span class="close" onclick="closeAddItemModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addItemForm">
                    <div class="form-group">
                        <label for="itemName">Name*</label>
                        <input type="text" id="itemName" name="name" placeholder="Enter item name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="itemCategory">Category*</label>
                        <select id="itemCategory" name="category" required>
                            <option value="">Select category</option>
                            <option value="costume">Costume</option>
                            <option value="equipment">Equipment</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="itemQuantity">Quantity*</label>
                        <input type="number" id="itemQuantity" name="quantity" placeholder="Enter quantity" required min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="itemCondition">Condition*</label>
                        <select id="itemCondition" name="condition" required>
                            <option value="">Select condition</option>
                            <option value="good">Good</option>
                            <option value="worn-out">Worn-out</option>
                            <option value="bad">Bad</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="itemDescription">Description (Optional)</label>
                        <textarea id="itemDescription" name="description" placeholder="Enter item description"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddItemModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" <?= $isCentralHead ? 'disabled' : '' ?>>Save Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const isCentralHead = <?= $isCentralHead ? 'true' : 'false' ?>;

        // Load inventory items
        function loadInventoryItems() {
            console.log('Loading inventory items...');
            
            fetch('get_inventory_items.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCostumes(data.costumes);
                        displayEquipment(data.equipment);
                    } else {
                        console.error('Error loading inventory:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading inventory:', error);
                });
        }

        function displayCostumes(costumes) {
            const tableBody = document.getElementById('costumesTableBody');
            if (!costumes || costumes.length === 0) {
                tableBody.innerHTML = '<div class="empty-state"><p>No costumes found.</p><small>Click "Add Item" to get started.</small></div>';
                return;
            }
            
            let html = '';
            costumes.forEach(costume => {
                html += '<div class="table-row">';
                html += '<div>' + (costume.item_name || costume.name || 'Unnamed Item') + '</div>';
                html += '<div>' + (costume.quantity || 0) + '</div>';
                html += '<div>' + getConditionBadge(costume.condition_status) + '</div>';
                html += '<div>' + getInventoryStatusBadge(costume.status) + '</div>';
                html += '<div style="font-size: 0.8rem;">' + getBorrowerInfo(costume) + '</div>';
                html += '</div>';
            });
            tableBody.innerHTML = html;
        }

        function displayEquipment(equipment) {
            const tableBody = document.getElementById('equipmentTableBody');
            if (!equipment || equipment.length === 0) {
                tableBody.innerHTML = '<div class="empty-state"><p>No equipment found.</p><small>Click "Add Item" to get started.</small></div>';
                return;
            }
            
            let html = '';
            equipment.forEach(item => {
                html += '<div class="table-row">';
                html += '<div>' + (item.item_name || item.name || 'Unnamed Item') + '</div>';
                html += '<div>' + (item.quantity || 0) + '</div>';
                html += '<div>' + getConditionBadge(item.condition_status) + '</div>';
                html += '<div>' + getInventoryStatusBadge(item.status) + '</div>';
                html += '<div style="font-size: 0.8rem;">' + getBorrowerInfo(item) + '</div>';
                html += '</div>';
            });
            tableBody.innerHTML = html;
        }

        function getBorrowerInfo(item) {
            if (item.status === 'borrowed') {
                if (item.borrowers && item.borrowers.length > 0) {
                    let html = '<div style="line-height: 1.3;">';
                    item.borrowers.forEach((borrower, index) => {
                        const borrowDate = new Date(borrower.borrow_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                        html += `<div style="margin-bottom: ${index < item.borrowers.length - 1 ? '0.4rem' : '0'}; padding-bottom: ${index < item.borrowers.length - 1 ? '0.4rem' : '0'}; border-bottom: ${index < item.borrowers.length - 1 ? '1px solid #eee' : 'none'};">`;
                        html += `<div style="font-weight: 600; color: #333; margin-bottom: 2px; font-size: 0.8rem; word-wrap: break-word;">${borrower.student_name}</div>`;
                        html += `<div style="color: #666; font-size: 0.7rem;">Since: ${borrowDate}</div>`;
                        html += `</div>`;
                    });
                    html += '</div>';
                    return html;
                } else if (item.borrower_name) {
                    const borrowDate = new Date(item.borrow_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    return `<div style="line-height: 1.3;">
                        <div style="font-weight: 600; color: #333; margin-bottom: 2px; font-size: 0.8rem; word-wrap: break-word;">${item.borrower_name}</div>
                        <div style="color: #666; font-size: 0.7rem;">Since: ${borrowDate}</div>
                    </div>`;
                } else {
                    return '<span style="color: #666; font-style: italic; font-size: 0.8rem;">Borrowed</span>';
                }
            }
            return '<span style="color: #aaa; text-align: center; display: block; font-size: 0.8rem;">-</span>';
        }

        function getConditionBadge(condition) {
            const badges = {
                'good': '<span class="badge badge-good">Good</span>',
                'worn-out': '<span class="badge badge-worn">Worn-out</span>',
                'bad': '<span class="badge badge-worn">Bad</span>',
                'excellent': '<span class="badge badge-good">Excellent</span>'
            };
            return badges[condition] || `<span style="color: #666; font-size: 0.7rem;">${condition || 'Unknown'}</span>`;
        }

        function getInventoryStatusBadge(status) {
            const badges = {
                'available': '<span class="badge badge-available">Available</span>',
                'borrowed': '<span class="badge badge-borrowed">Borrowed</span>',
                'maintenance': '<span class="badge badge-maintenance">Maintenance</span>'
            };
            return badges[status] || `<span style="color: #666; font-size: 0.7rem;">${status || 'Unknown'}</span>`;
        }

        // Modal functions
        function openAddItemModal() {
            if (isCentralHead) {
                alert('Central Head users have view-only access.');
                return;
            }
            document.getElementById('addItemModal').classList.add('show');
        }

        function closeAddItemModal() {
            document.getElementById('addItemModal').classList.remove('show');
            document.getElementById('addItemForm').reset();
        }

        function openBorrowRequests() {
            const modal = document.getElementById('borrowRequestsModal');
            modal.style.display = 'flex';
            loadBorrowRequests();
        }

        function closeBorrowRequestsModal() {
            const modal = document.getElementById('borrowRequestsModal');
            modal.style.display = 'none';
        }

        function openReturns() {
            const modal = document.getElementById('returnsModal');
            modal.style.display = 'flex';
            loadReturnRequests();
        }

        function closeReturnsModal() {
            const modal = document.getElementById('returnsModal');
            modal.style.display = 'none';
        }

        // Add item form submission
        document.getElementById('addItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (isCentralHead) {
                alert('Central Head users cannot add items.');
                return;
            }
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            fetch('save_inventory_item.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Item added successfully!');
                    closeAddItemModal();
                    loadInventoryItems();
                } else {
                    alert('Error adding item: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error adding item: ' + error.message);
            });
        });

        // Borrow Requests Functions
        function loadBorrowRequests(page = 1) {
            const loadingDiv = document.getElementById('borrowRequestsLoading');
            const contentDiv = document.getElementById('borrowRequestsContent');
            const tableBody = document.getElementById('borrowRequestsTableBody');
            
            loadingDiv.style.display = 'block';
            contentDiv.style.display = 'none';
            
            const status = document.getElementById('statusRequestFilter').value;
            const search = document.getElementById('requestSearchInput') ? document.getElementById('requestSearchInput').value : '';
            
            const params = new URLSearchParams({ page: page, limit: 10 });
            if (status) params.append('status', status);
            if (search) params.append('search', search);
            
            fetch(`get_borrowing_requests.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                    
                    if (data.success) {
                        displayBorrowRequests(data.data);
                        displayRequestsPagination(data.pagination);
                    } else {
                        tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #dc3545;">Error: ${data.error}</td></tr>`;
                    }
                })
                .catch(error => {
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                    tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #dc3545;">Error loading requests</td></tr>`;
                });
        }
        
        function displayBorrowRequests(requests) {
            const tableBody = document.getElementById('borrowRequestsTableBody');
            if (!requests || requests.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 2rem;">No requests found</td></tr>`;
                return;
            }
            
            let html = '';
            requests.forEach(request => {
                let equipmentList = request.item_name || 'Not specified';
                const statusBadge = getStatusBadge(request.status);
                const actions = getRequestActions(request.id, request.status);
                
                html += `
                    <tr>
                        <td><div style="font-weight: 600;">${request.student_name || 'Unknown'}</div>
                            <div style="font-size: 0.8rem; color: #666;">${request.student_email || ''}</div>
                            <div style="font-size: 0.8rem; color: #666;">${request.student_campus || ''}</div></td>
                        <td>${equipmentList}</td>
                        <td>${request.dates_of_use || 'Not specified'}</td>
                        <td>${statusBadge}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        }
        
        function getStatusBadge(status) {
            const badges = {
                'pending': '<span style="background: #fef3c7; color: #f59e0b; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500;">Pending</span>',
                'approved': '<span style="background: #d1fae5; color: #10b981; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500;">Approved</span>',
                'rejected': '<span style="background: #fee2e2; color: #ef4444; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500;">Rejected</span>'
            };
            return badges[status] || status;
        }
        
        function getRequestActions(requestId, status) {
            if (status === 'pending') {
                return `
                    <button class="action-btn small" onclick="approveRequest(${requestId})" style="background: #10b981; color: white; margin-right: 0.5rem;">Approve</button>
                    <button class="action-btn small" onclick="rejectRequest(${requestId})" style="background: #ef4444; color: white;">Reject</button>
                `;
            } else {
                return `<button class="action-btn small" onclick="viewRequestDetails(${requestId})">View</button>`;
            }
        }
        
        function approveRequest(requestId) {
            if (confirm('Are you sure you want to approve this borrow request?')) {
                updateRequestStatus(requestId, 'approved');
            }
        }
        
        function rejectRequest(requestId) {
            if (confirm('Are you sure you want to reject this borrow request?')) {
                updateRequestStatus(requestId, 'rejected');
            }
        }
        
        function updateRequestStatus(requestId, status) {
            fetch('update_borrowing_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Request updated successfully!');
                    loadBorrowRequests();
                    loadInventoryItems();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error updating request:', error);
                alert('Error updating request. Please try again.');
            });
        }
        
        function displayRequestsPagination(pagination) {
            const paginationDiv = document.getElementById('borrowRequestsPagination');
            if (!pagination || pagination.total_pages <= 1) {
                paginationDiv.innerHTML = '';
                return;
            }
            
            let html = '<div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1rem;">';
            if (pagination.current_page > 1) {
                html += `<button class="pagination-btn" onclick="loadBorrowRequests(${pagination.current_page - 1})">Previous</button>`;
            }
            for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
                html += `<button class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" onclick="loadBorrowRequests(${i})">${i}</button>`;
            }
            if (pagination.current_page < pagination.total_pages) {
                html += `<button class="pagination-btn" onclick="loadBorrowRequests(${pagination.current_page + 1})">Next</button>`;
            }
            html += '</div>';
            paginationDiv.innerHTML = html;
        }
        
        // Return Requests Functions
        function loadReturnRequests(page = 1) {
            const loadingDiv = document.getElementById('returnsLoading');
            const contentDiv = document.getElementById('returnsContent');
            const tableBody = document.getElementById('returnRequestsTableBody');
            
            loadingDiv.style.display = 'block';
            contentDiv.style.display = 'none';
            
            const status = document.getElementById('statusReturnFilter').value;
            const search = document.getElementById('searchReturnFilter').value;
            
            fetch('get_return_requests.php?' + new URLSearchParams({ page: page, status: status, search: search }))
                .then(response => response.json())
                .then(data => {
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                    if (data.success) {
                        displayReturnRequests(data.requests);
                        displayReturnsPagination(data.pagination);
                    } else {
                        tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 2rem;">Error: ${data.message}</td></tr>`;
                    }
                })
                .catch(error => {
                    loadingDiv.style.display = 'none';
                    contentDiv.style.display = 'block';
                    tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 2rem;">Error loading returns</td></tr>`;
                });
        }
        
        function displayReturnRequests(requests) {
            const tableBody = document.getElementById('returnRequestsTableBody');
            if (!requests || requests.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 2rem;">No return requests found</td></tr>`;
                return;
            }
            
            let html = '';
            requests.forEach(request => {
                const statusBadge = getReturnStatusBadge(request.status);
                const actions = getReturnRequestActions(request.id, request.status);
                
                html += `
                    <tr>
                        <td>${request.student_name}</td>
                        <td>${request.item_name}</td>
                        <td>${request.requested_at}</td>
                        <td>${request.condition_notes || '-'}</td>
                        <td>${statusBadge}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        }
        
        function getReturnStatusBadge(status) {
            const badges = {
                'pending': '<span style="background: #fef3c7; color: #f59e0b; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500;">Pending</span>',
                'completed': '<span style="background: #d1fae5; color: #10b981; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500;">Completed</span>',
                'confirmed': '<span style="background: #d1fae5; color: #10b981; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500;">Confirmed</span>'
            };
            return badges[status] || status;
        }
        
        function getReturnRequestActions(requestId, status) {
            if (status === 'pending') {
                return `
                    <button class="action-btn small" onclick="confirmReturn(${requestId})" style="background: #10b981; color: white; margin-right: 0.5rem;">Confirm Return</button>
                    <button class="action-btn small" onclick="viewReturnDetails(${requestId})">View</button>
                `;
            } else {
                return `<button class="action-btn small" onclick="viewReturnDetails(${requestId})">View</button>`;
            }
        }
        
        function confirmReturn(requestId) {
            if (confirm('Are you sure you want to confirm this return? This will mark the items as returned and available.')) {
                fetch('process_return_confirmation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        action: 'confirm_return'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Return confirmed successfully!');
                        loadReturnRequests();
                        loadInventoryItems();
                    } else {
                        alert('Error confirming return: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error confirming return: ' + error.message);
                });
            }
        }
        
        function displayReturnsPagination(pagination) {
            const paginationDiv = document.getElementById('returnsPagination');
            if (!pagination || pagination.total_pages <= 1) {
                paginationDiv.innerHTML = '';
                return;
            }
            
            let html = '<div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1rem;">';
            if (pagination.current_page > 1) {
                html += `<button class="pagination-btn" onclick="loadReturnRequests(${pagination.current_page - 1})">Previous</button>`;
            }
            for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
                html += `<button class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" onclick="loadReturnRequests(${i})">${i}</button>`;
            }
            if (pagination.current_page < pagination.total_pages) {
                html += `<button class="pagination-btn" onclick="loadReturnRequests(${pagination.current_page + 1})">Next</button>`;
            }
            html += '</div>';
            paginationDiv.innerHTML = html;
        }
        
        function viewRequestDetails(id) { alert('View borrow request: ' + id); }
        function viewReturnDetails(id) { alert('View return request: ' + id); }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../logout.php';
            }
        }

        // Load inventory on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadInventoryItems();
        });
    </script>

    <!-- Borrow Requests Modal -->
    <div id="borrowRequestsModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 1000px; width: 95%;">
            <div class="modal-header">
                <h2>Student Borrow Requests</h2>
                <span class="close" onclick="closeBorrowRequestsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem;">Status:</label>
                        <select id="statusRequestFilter" onchange="loadBorrowRequests()" style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px;">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="">All Status</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem;">Search:</label>
                        <input type="text" id="requestSearchInput" placeholder="Search..." style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; width: 250px;">
                    </div>
                    <button onclick="loadBorrowRequests()" style="padding: 0.75rem 1rem; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer; align-self: end;">Refresh</button>
                </div>
                <div id="borrowRequestsLoading" style="text-align: center; padding: 2rem;"><p>Loading...</p></div>
                <div id="borrowRequestsContent" style="display: none;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; text-align: left;">
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Student Info</th>
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Items</th>
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Dates</th>
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Status</th>
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="borrowRequestsTableBody"></tbody>
                    </table>
                </div>
                <div id="borrowRequestsPagination"></div>
            </div>
        </div>
    </div>

    <!-- Returns Modal -->
    <div id="returnsModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 1200px; width: 95%;">
            <div class="modal-header">
                <h2>Return Requests</h2>
                <span class="close" onclick="closeReturnsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem;">Status:</label>
                        <select id="statusReturnFilter" onchange="loadReturnRequests()" style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px;">
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="">All Status</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem;">Search:</label>
                        <input type="text" id="searchReturnFilter" placeholder="Search..." style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; width: 250px;">
                    </div>
                    <button onclick="loadReturnRequests()" style="padding: 0.75rem 1rem; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer; align-self: end;">Refresh</button>
                </div>
                <div id="returnsLoading" style="text-align: center; padding: 2rem;"><p>Loading...</p></div>
                <div id="returnsContent" style="display: none;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; text-align: left;">
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Student</th>
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Item</th>
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Request Date</th>
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Condition</th>
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Status</th>
                                <th style="padding: 1rem; border-bottom: 2px solid #ddd;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="returnRequestsTableBody"></tbody>
                    </table>
                </div>
                <div id="returnsPagination"></div>
            </div>
        </div>
    </div>

</body>
</html>

<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_table'] !== 'student_artists') {
    header("Location: ../index.php");
    exit();
}

// Database connection
require_once '../config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get student information
    $student_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM student_artists WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        header("Location: ../index.php");
        exit();
    }
    
} catch (Exception $e) {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Form - Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .header {
            background: #ff5a5a;
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
        }

        .form-content {
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
            border-bottom: 2px solid #ff5a5a;
            padding-bottom: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Montserrat', sans-serif;
            transition: border-color 0.3s ease;
        }

        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            outline: none;
            border-color: #ff5a5a;
            box-shadow: 0 0 0 3px rgba(255, 90, 90, 0.1);
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin: 1rem 0;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            border-color: #ff5a5a;
            background-color: rgba(255, 90, 90, 0.05);
        }

        .checkbox-item input[type="checkbox"] {
            display: none;
        }

        .checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid #333;
            background: #fff;
            position: relative;
            flex-shrink: 0;
            border-radius: 4px;
        }

        .checkbox-item input[type="checkbox"]:checked + .checkmark::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
            font-weight: bold;
            color: #ff5a5a;
        }

        .checkbox-label {
            font-size: 1rem;
            color: #333;
            line-height: 1.4;
        }

        .return-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #eee;
            margin-bottom: 1.5rem;
        }

        .return-section h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .received-by-section {
            background: #f8f9fa;
            border: 2px solid #ff5a5a;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 8px;
        }

        .received-by-header {
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            text-align: center;
            color: #ff5a5a;
        }

        .date-time-grid {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 1rem;
            align-items: center;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            min-width: 140px;
        }

        .btn-primary {
            background: #ff5a5a;
            color: white;
        }

        .btn-primary:hover {
            background: #ff3333;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header {
                padding: 1.5rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .form-content {
                padding: 1.5rem;
            }

            .form-grid,
            .date-time-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="header">
                <h1>Equipment Return Form</h1>
                <p>Office of Culture and Arts</p>
            </div>
            
            <div class="form-content">
                <form method="POST" action="process-return.php">
                    <!-- Return Details -->
                    <div class="form-section">
                        <h3>Return Details</h3>
                        <div class="input-group">
                            <label for="borrowed_items">Items Being Returned</label>
                            <textarea id="borrowed_items" name="borrowed_items" rows="3" 
                                      placeholder="List all items being returned..." required></textarea>
                        </div>
                    </div>

                    <!-- Return Condition -->
                    <div class="return-section">
                        <h4>Return</h4>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="good_condition" name="condition[]" value="good_condition">
                                <span class="checkmark"></span>
                                <label for="good_condition" class="checkbox-label">
                                    Properties was/were returned in good condition
                                </label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="with_damage" name="condition[]" value="with_damage">
                                <span class="checkmark"></span>
                                <label for="with_damage" class="checkbox-label">
                                    Properties was/were returned with damage
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Submit Return</button>
                        <a href="dashboard.php?section=costume-borrowing" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Handle checkbox clicking
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxItems = document.querySelectorAll('.checkbox-item');
            
            checkboxItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (e.target.type === 'checkbox') return;
                    
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'check_password') {
        $email = trim($_POST['email']);
        $test_password = $_POST['test_password'];
        
        try {
            $pdo = getDBConnection();
            
            // Check both tables
            $stmt = $pdo->prepare("SELECT id, sr_code, password, 'student_artists' as source FROM student_artists WHERE email = ? 
                                  UNION 
                                  SELECT id, '' as sr_code, password, 'users' as source FROM users WHERE email = ?");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $result = [
                    'found' => true,
                    'source_table' => $user['source'],
                    'sr_code' => $user['sr_code'],
                    'password_matches' => password_verify($test_password, $user['password'])
                ];
            } else {
                $result = ['found' => false];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
    
    if ($action === 'reset_password') {
        $email = trim($_POST['email']);
        
        try {
            $pdo = getDBConnection();
            
            // First check student_artists table
            $stmt = $pdo->prepare("SELECT id, sr_code FROM student_artists WHERE email = ?");
            $stmt->execute([$email]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                // Reset password to SR code
                $new_password = password_hash($student['sr_code'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE student_artists SET password = ? WHERE id = ?");
                $stmt->execute([$new_password, $student['id']]);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Password reset to SR code: ' . $student['sr_code']]);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Student not found']);
                exit;
            }
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Password Checker - Admin Tool</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 2rem;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #dc2626;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        input[type="email"], input[type="text"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        button {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
        }
        
        button:hover {
            background: #b91c1c;
        }
        
        .result {
            margin-top: 2rem;
            padding: 1rem;
            border-radius: 4px;
            display: none;
        }
        
        .result.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .result.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .result.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Student Password Checker</h1>
        
        <div class="form-group">
            <label for="email">Student Email:</label>
            <input type="email" id="email" placeholder="Enter student email address">
        </div>
        
        <div class="form-group">
            <label for="testPassword">Test Password:</label>
            <input type="text" id="testPassword" placeholder="Enter password to test (usually SR code)">
        </div>
        
        <button onclick="checkPassword()">Check Password</button>
        <button onclick="resetPassword()">Reset Password to SR Code</button>
        
        <div id="result" class="result"></div>
    </div>
    
    <script>
        function checkPassword() {
            const email = document.getElementById('email').value;
            const testPassword = document.getElementById('testPassword').value;
            
            if (!email || !testPassword) {
                showResult('Please enter both email and password to test', 'error');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_password&email=${encodeURIComponent(email)}&test_password=${encodeURIComponent(testPassword)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showResult('Error: ' + data.error, 'error');
                } else if (data.found) {
                    if (data.password_matches) {
                        showResult(`✓ Password matches! (Found in ${data.source_table}${data.sr_code ? ', SR Code: ' + data.sr_code : ''})`, 'success');
                    } else {
                        showResult(`✗ Password does NOT match (Found in ${data.source_table}${data.sr_code ? ', SR Code: ' + data.sr_code : ''})`, 'error');
                    }
                } else {
                    showResult('User not found with that email address', 'error');
                }
            })
            .catch(error => {
                showResult('Error: ' + error.message, 'error');
            });
        }
        
        function resetPassword() {
            const email = document.getElementById('email').value;
            
            if (!email) {
                showResult('Please enter an email address', 'error');
                return;
            }
            
            if (!confirm('Are you sure you want to reset this student\'s password to their SR code?')) {
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=reset_password&email=${encodeURIComponent(email)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showResult('Error: ' + data.error, 'error');
                } else if (data.success) {
                    showResult('✓ ' + data.message, 'success');
                } else {
                    showResult('✗ ' + data.message, 'error');
                }
            })
            .catch(error => {
                showResult('Error: ' + error.message, 'error');
            });
        }
        
        function showResult(message, type) {
            const result = document.getElementById('result');
            result.textContent = message;
            result.className = 'result ' + type;
            result.style.display = 'block';
        }
    </script>
</body>
</html>
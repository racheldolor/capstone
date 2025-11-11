<?php
session_start();
require_once 'config/database.php';

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (!empty($email) && !empty($password)) {
        try {
            $pdo = getDBConnection();
            
            // First, check if user exists in the users table
            $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, email, password, role, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            

            
            // If not found in users table, check student_artists table
            if (!$user) {
                $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, email, password, status FROM student_artists WHERE email = ?");
                $stmt->execute([$email]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($student) {
                    // Set role as 'student' for student_artists table users
                    $user = $student;
                    $user['role'] = 'student';
                }
            }
            
            if ($user) {
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $error_message = 'Your account is ' . $user['status'] . '. Please contact the administrator.';
                }
                // Verify password
                else if (password_verify($password, $user['password'])) {
                    // Update last login - determine which table to update
                    if (isset($student)) {
                        // For student_artists table, check if last_login column exists and add if needed
                        try {
                            $stmt = $pdo->prepare("SELECT last_login FROM student_artists LIMIT 1");
                            $stmt->execute();
                        } catch (PDOException $e) {
                            // Column doesn't exist, add it
                            $pdo->exec("ALTER TABLE student_artists ADD COLUMN last_login DATETIME DEFAULT NULL");
                        }
                        
                        // Update student_artists table
                        $stmt = $pdo->prepare("UPDATE student_artists SET last_login = NOW() WHERE id = ?");
                    } else {
                        // Update users table
                        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    }
                    $stmt->execute([$user['id']]);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = trim($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name']);
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    // Add table source for reference
                    $_SESSION['user_table'] = isset($student) ? 'student_artists' : 'users';
                    
                    // Redirect based on role
                    switch ($user['role']) {
                        case 'head':
                            header('Location: head-staff/dashboard.php');
                            break;
                        case 'central':
                            header('Location: central/dashboard.php');
                            break;
                        case 'student':
                            header('Location: student/dashboard.php');
                            break;
                        case 'staff':
                            header('Location: head-staff/dashboard.php');
                            break;
                        case 'admin':
                            header('Location: admin/dashboard.php');
                            break;
                        default:
                            // Unknown role, redirect to login
                            header('Location: index.php?error=invalid_role');
                    }
                    exit();
                } else {
                    error_log("Password verification failed for user: " . $user['email']);
                    $error_message = 'Invalid email or password.';
                }
            } else {
                $error_message = 'Account not found. Please check your email address.';
            }
        } catch (Exception $e) {
            $error_message = 'Login failed. Please try again later.';
        }
    } else {
        $error_message = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - Culture and Arts - BatStateU TNEU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 500px;
        }

        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #ff5a5a, #ff7a6b);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
        }

        .logo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
        }

        .title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .subtitle {
            font-size: 1rem;
            font-weight: 500;
            opacity: 0.9;
            margin: 0;
        }

        .login-body {
            padding: 2.5rem 2rem;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2a3550;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .form-description {
            color: #666;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .form-label {
            font-weight: 600;
            color: #2a3550;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-input {
            padding: 0.875rem 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus {
            outline: none;
            border-color: #ff5a5a;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 90, 90, 0.1);
        }

        .form-input:valid {
            border-color: #28a745;
        }

        .form-input.error {
            border-color: #dc3545;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            min-height: 1rem;
        }

        .error-message-box {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid #f5c6cb;
            font-size: 0.9rem;
        }

        .success-message-box {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid #c3e6cb;
            font-size: 0.9rem;
        }

        .show-password-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: -1.2rem;
            font-size: 0.9rem;
        }

        .show-password-container input[type="checkbox"] {
            cursor: pointer;
            accent-color: #ff5a5a;
        }

        .show-password-container label {
            cursor: pointer;
            color: #666;
            font-weight: normal;
        }

        .login-btn {
            background: linear-gradient(135deg, #ff5a5a, #ff7a6b);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 90, 90, 0.3);
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .signin-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e1e5e9;
        }

        .signin-link p {
            color: #666;
            font-size: 0.95rem;
        }

        .link {
            color: #ff5a5a;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .link:hover {
            color: #ff3333;
            text-decoration: underline;
        }

        /* Success Message */
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 500;
        }

        /* Loading State */
        .login-btn.loading {
            pointer-events: none;
        }

        .login-btn.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: button-loading-spinner 1s ease infinite;
        }

        @keyframes button-loading-spinner {
            from {
                transform: rotate(0turn);
            }
            to {
                transform: rotate(1turn);
            }
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .login-container {
                padding: 1rem;
            }
            
            .login-card {
                border-radius: 15px;
            }
            
            .login-header {
                padding: 2rem 1.5rem;
            }
            
            .login-body {
                padding: 2rem 1.5rem;
            }
            
            .title {
                font-size: 1.5rem;
            }
            
            .form-title {
                font-size: 1.7rem;
            }
        }

        @media (max-width: 400px) {
            body {
                padding: 1rem 0.5rem;
            }
            
            .login-header {
                padding: 1.5rem 1rem;
            }
            
            .login-body {
                padding: 1.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-section">
                    <img src="assets/OCA Logo.png" alt="BatstateU Logo" class="logo">
                    <h1 class="title">Culture and Arts</h1>
                    <p class="subtitle">BatStateU TNEU</p>
                </div>
            </div>
            
            <div class="login-body">
                <h2 class="form-title">Login</h2>
                <p class="form-description">Enter your email and password</p>
                
                <?php if (!empty($error_message)): ?>
                    <div class="error-message-box">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="success-message-box">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                
                <form class="login-form" id="loginForm" method="POST" action="">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        <span class="error-message" id="emailError"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-input" required>
                        <span class="error-message" id="passwordError"></span>
                    </div>

                    <div class="show-password-container">
                        <input type="checkbox" id="showPassword" onchange="toggleAllPasswords()">
                        <label for="showPassword">Show Password</label>
                    </div>
                    
                    <button type="submit" class="login-btn">Login</button>
                </form>
                
                <div class="signin-link">
                    <p>Change password? <a href="change-password.php" class="link">Update Here</a></p>
                    <br>
                    <p>Interested in becoming a Student Performer? <a href="student/performer-profile-form.php" class="link">Register Here</a></p>

                </div>
            </div>
        </div>
    </div>
    
    <script>
        class LoginForm {
            constructor() {
                this.form = document.getElementById('loginForm');
                this.inputs = {
                    email: document.getElementById('email'),
                    password: document.getElementById('password')
                };
                this.errors = {
                    email: document.getElementById('emailError'),
                    password: document.getElementById('passwordError')
                };
                this.submitBtn = this.form.querySelector('.login-btn');
                
                this.init();
            }

            init() {
                this.setupEventListeners();
            }

            setupEventListeners() {
                // Form submission
                this.form.addEventListener('submit', (e) => this.handleSubmit(e));
                
                // Real-time validation
                Object.keys(this.inputs).forEach(key => {
                    const input = this.inputs[key];
                    if (input) {
                        input.addEventListener('blur', () => this.validateField(key));
                        input.addEventListener('input', () => {
                            this.clearError(key);
                        });
                    }
                });
            }

            validateField(fieldName) {
                const input = this.inputs[fieldName];
                const value = input.value.trim();
                
                switch (fieldName) {
                    case 'email':
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!value) {
                            this.showError(fieldName, 'Email address is required');
                            return false;
                        } else if (!emailRegex.test(value)) {
                            this.showError(fieldName, 'Please enter a valid email address');
                            return false;
                        }
                        break;
                        
                    case 'password':
                        if (!value) {
                            this.showError(fieldName, 'Password is required');
                            return false;
                        }
                        break;
                }
                
                this.clearError(fieldName);
                return true;
            }

            showError(fieldName, message) {
                const errorElement = this.errors[fieldName];
                if (errorElement) {
                    errorElement.textContent = message;
                    this.inputs[fieldName].classList.add('error');
                }
            }

            clearError(fieldName) {
                const errorElement = this.errors[fieldName];
                if (errorElement) {
                    errorElement.textContent = '';
                    this.inputs[fieldName].classList.remove('error');
                }
            }

            validateForm() {
                let isValid = true;
                
                Object.keys(this.inputs).forEach(fieldName => {
                    if (!this.validateField(fieldName)) {
                        isValid = false;
                    }
                });
                
                return isValid;
            }

            async handleSubmit(e) {
                // Don't prevent default - let PHP handle the form submission
                // Just validate the form before submission
                if (!this.validateForm()) {
                    e.preventDefault();
                    return;
                }
                
                // Show loading state
                this.setLoading(true);
                
                // Form will submit naturally to PHP
            }

            setLoading(isLoading) {
                if (isLoading) {
                    this.submitBtn.textContent = 'Logging in...';
                    this.submitBtn.classList.add('loading');
                    this.submitBtn.disabled = true;
                } else {
                    this.submitBtn.textContent = 'Login';
                    this.submitBtn.classList.remove('loading');
                    this.submitBtn.disabled = false;
                }
            }

            clearAllErrors() {
                Object.keys(this.errors).forEach(fieldName => {
                    this.clearError(fieldName);
                });
            }
        }

        // Show/hide password function
        function toggleAllPasswords() {
            const checkbox = document.getElementById('showPassword');
            const passwordField = document.getElementById('password');
            
            if (passwordField) {
                passwordField.type = checkbox.checked ? 'text' : 'password';
            }
        }

        // Initialize the login form when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            new LoginForm();
        });

        // Export for potential use in other scripts
        window.LoginForm = LoginForm;
    </script>
</body>
</html>
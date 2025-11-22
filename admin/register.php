<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Culture and Arts - BatStateU TNEU</title>
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

        .signup-container {
            width: 100%;
            max-width: 600px;
        }

        .signup-card {
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

        .signup-header {
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
            padding: 0.5rem;
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

        .signup-body {
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

        .signup-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-row {
            display: flex;
            gap: 1rem;
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

        .form-input,
        .form-select {
            padding: 0.875rem 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #ff5a5a;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 90, 90, 0.1);
        }

        .form-input:valid {
            border-color: #28a745;
        }

        .form-input.error,
        .form-select.error {
            border-color: #dc3545;
        }

        .form-select {
            cursor: pointer;
        }

        .form-select option {
            padding: 0.5rem;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            min-height: 1rem;
        }

        .signup-btn {
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

        .signup-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .signup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 90, 90, 0.3);
        }

        .signup-btn:hover::before {
            left: 100%;
        }

        .signup-btn:active {
            transform: translateY(0);
        }

        .signup-btn:disabled {
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
        .signup-btn.loading {
            pointer-events: none;
        }

        .signup-btn.loading::after {
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

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            border-radius: 2px;
            background: #e1e5e9;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .password-strength.weak .password-strength-bar {
            width: 33%;
            background: #dc3545;
        }

        .password-strength.medium .password-strength-bar {
            width: 66%;
            background: #ffc107;
        }

        .password-strength.strong .password-strength-bar {
            width: 100%;
            background: #28a745;
        }

        .password-requirements {
            font-size: 0.75rem;
            color: #666;
            margin-top: 0.5rem;
            list-style: none;
        }

        .password-requirements li {
            margin: 0.25rem 0;
            position: relative;
            padding-left: 1.5rem;
        }

        .password-requirements li:before {
            content: '✗';
            position: absolute;
            left: 0;
            color: #dc3545;
        }

        .password-requirements li.valid:before {
            content: '✓';
            color: #28a745;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .signup-container {
                padding: 1rem;
            }
            
            .signup-card {
                border-radius: 15px;
            }
            
            .signup-header {
                padding: 2rem 1.5rem;
            }
            
            .signup-body {
                padding: 2rem 1.5rem;
            }

            .form-row {
                flex-direction: column;
                gap: 1.5rem;
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
            
            .signup-header {
                padding: 1.5rem 1rem;
            }
            
            .signup-body {
                padding: 1.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <div class="logo-section">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/2/2e/Batangas_State_University.png" alt="BatstateU Logo" class="logo">
                    <h1 class="title">Culture and Arts</h1>
                    <p class="subtitle">BatStateU TNEU</p>
                </div>
            </div>
            
            <div class="signup-body">
                <h2 class="form-title">Create Account</h2>
                <p class="form-description">Fill in your details to create an account</p>
                
                <form class="signup-form" id="signupForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" id="firstName" name="firstName" class="form-input" required>
                            <span class="error-message" id="firstNameError"></span>
                        </div>
                        
                        <div class="form-group">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" id="lastName" name="lastName" class="form-input" required>
                            <span class="error-message" id="lastNameError"></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">G-Suite Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="yourname@batstateu.edu.ph" required>
                        <span class="error-message" id="emailError"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">Role</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="">Select your role</option>
                            <option value="student">Student</option>
                            <option value="staff">Staff</option>
                            <option value="head">Head</option>
                            <option value="central">Central</option>
                        </select>
                        <span class="error-message" id="roleError"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="campus" class="form-label">Campus</label>
                        <select id="campus" name="campus" class="form-select" required>
                            <option value="">Select campus</option>
                            <option value="Pablo Borbon">Pablo Borbon</option>
                            <option value="Alangilan">Alangilan</option>
                            <option value="Lipa">Lipa</option>
                            <option value="Nasugbu">Nasugbu</option>
                            <option value="Malvar">Malvar</option>
                        </select>
                        <span class="error-message" id="campusError"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-input" required>
                        <div class="password-strength" id="passwordStrength">
                            <div class="password-strength-bar"></div>
                        </div>
                        <ul class="password-requirements" id="passwordRequirements">
                            <li id="req-length">At least 8 characters</li>
                            <li id="req-uppercase">At least one uppercase letter</li>
                            <li id="req-lowercase">At least one lowercase letter</li>
                            <li id="req-number">At least one number</li>
                            <li id="req-special">At least one special character</li>
                        </ul>
                        <span class="error-message" id="passwordError"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword" class="form-label">Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" required>
                        <span class="error-message" id="confirmPasswordError"></span>
                    </div>
                    
                    <button type="submit" class="signup-btn">Create Account</button>
                </form>
                
                <div class="signin-link">
                    <p>Already have an account? <a href="../index.php" class="link">Sign In Here</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        class SignupForm {
            constructor() {
                this.form = document.getElementById('signupForm');
                this.inputs = {
                    firstName: document.getElementById('firstName'),
                    lastName: document.getElementById('lastName'),
                    email: document.getElementById('email'),
                    role: document.getElementById('role'),
                    campus: document.getElementById('campus'),
                    password: document.getElementById('password'),
                    confirmPassword: document.getElementById('confirmPassword')
                };
                this.errors = {
                    firstName: document.getElementById('firstNameError'),
                    lastName: document.getElementById('lastNameError'),
                    email: document.getElementById('emailError'),
                    role: document.getElementById('roleError'),
                    campus: document.getElementById('campusError'),
                    password: document.getElementById('passwordError'),
                    confirmPassword: document.getElementById('confirmPasswordError')
                };
                this.submitBtn = this.form.querySelector('.signup-btn');
                this.passwordStrength = document.getElementById('passwordStrength');
                this.passwordRequirements = document.getElementById('passwordRequirements');
                
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
                            if (key === 'password') {
                                this.checkPasswordStrength();
                            }
                            if (key === 'confirmPassword' || key === 'password') {
                                this.validatePasswordMatch();
                            }
                        });
                    }
                });
            }

            validateField(fieldName) {
                const input = this.inputs[fieldName];
                const value = input.value.trim();
                
                switch (fieldName) {
                    case 'firstName':
                    case 'lastName':
                        if (!value) {
                            this.showError(fieldName, `${fieldName === 'firstName' ? 'First' : 'Last'} name is required`);
                            return false;
                        } else if (value.length < 2) {
                            this.showError(fieldName, `${fieldName === 'firstName' ? 'First' : 'Last'} name must be at least 2 characters`);
                            return false;
                        } else if (!/^[a-zA-Z\s]+$/.test(value)) {
                            this.showError(fieldName, 'Name can only contain letters and spaces');
                            return false;
                        }
                        break;
                        
                    case 'email':
                        const emailRegex = /^[^\s@]+@batstateu\.edu\.ph$/;
                        if (!value) {
                            this.showError(fieldName, 'Email address is required');
                            return false;
                        } else if (!emailRegex.test(value)) {
                            this.showError(fieldName, 'Please enter a valid BatStateU G-Suite email (@batstateu.edu.ph)');
                            return false;
                        }
                        break;
                        
                    case 'role':
                        if (!value) {
                            this.showError(fieldName, 'Please select your role');
                            return false;
                        }
                        break;
                    
                    case 'campus':
                        if (!value) {
                            this.showError(fieldName, 'Please select a campus');
                            return false;
                        }
                        break;
                        
                    case 'password':
                        if (!value) {
                            this.showError(fieldName, 'Password is required');
                            return false;
                        } else if (!this.isPasswordStrong(value)) {
                            this.showError(fieldName, 'Password does not meet all requirements');
                            return false;
                        }
                        break;
                        
                    case 'confirmPassword':
                        if (!value) {
                            this.showError(fieldName, 'Please confirm your password');
                            return false;
                        } else if (value !== this.inputs.password.value) {
                            this.showError(fieldName, 'Passwords do not match');
                            return false;
                        }
                        break;
                }
                
                this.clearError(fieldName);
                return true;
            }

            isPasswordStrong(password) {
                const requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /\d/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };
                
                return Object.values(requirements).every(req => req);
            }

            checkPasswordStrength() {
                const password = this.inputs.password.value;
                const requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /\d/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };

                // Update requirement indicators
                document.getElementById('req-length').classList.toggle('valid', requirements.length);
                document.getElementById('req-uppercase').classList.toggle('valid', requirements.uppercase);
                document.getElementById('req-lowercase').classList.toggle('valid', requirements.lowercase);
                document.getElementById('req-number').classList.toggle('valid', requirements.number);
                document.getElementById('req-special').classList.toggle('valid', requirements.special);

                // Calculate strength
                const validCount = Object.values(requirements).filter(req => req).length;
                const strength = validCount >= 5 ? 'strong' : validCount >= 3 ? 'medium' : 'weak';

                this.passwordStrength.className = `password-strength ${strength}`;
            }

            validatePasswordMatch() {
                const password = this.inputs.password.value;
                const confirmPassword = this.inputs.confirmPassword.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    this.showError('confirmPassword', 'Passwords do not match');
                } else if (confirmPassword && password === confirmPassword) {
                    this.clearError('confirmPassword');
                }
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
                e.preventDefault();
                
                // Clear any existing success messages
                const existingSuccess = this.form.parentElement.querySelector('.success-message');
                if (existingSuccess) {
                    existingSuccess.remove();
                }
                
                // Validate form
                if (!this.validateForm()) {
                    return;
                }
                
                // Show loading state
                this.setLoading(true);
                
                try {
                    // Simulate API call
                    const formData = {
                        firstName: this.inputs.firstName.value.trim(),
                        lastName: this.inputs.lastName.value.trim(),
                        email: this.inputs.email.value.trim(),
                        role: this.inputs.role.value,
                        campus: this.inputs.campus.value,
                        password: this.inputs.password.value
                    };
                    
                    // Simulate network delay
                    await this.simulateSignup(formData);
                    
                    // Show success message
                    this.showSuccess();
                    
                    // Reset form
                    this.form.reset();
                    this.clearAllErrors();
                    this.passwordStrength.className = 'password-strength';
                    
                } catch (error) {
                    this.showError('email', 'An error occurred. Please try again.');
                } finally {
                    this.setLoading(false);
                }
            }

            async simulateSignup(formData) {
                // Simulate API call delay
                return new Promise((resolve, reject) => {
                    setTimeout(() => {
                        // Simulate random success/failure for demo
                        if (Math.random() > 0.1) { // 90% success rate
                            console.log('Account created:', formData);
                            resolve(formData);
                        } else {
                            reject(new Error('Server error'));
                        }
                    }, 2000);
                });
            }

            setLoading(isLoading) {
                if (isLoading) {
                    this.submitBtn.textContent = 'Creating Account...';
                    this.submitBtn.classList.add('loading');
                    this.submitBtn.disabled = true;
                } else {
                    this.submitBtn.textContent = 'Create Account';
                    this.submitBtn.classList.remove('loading');
                    this.submitBtn.disabled = false;
                }
            }

            showSuccess() {
                const successDiv = document.createElement('div');
                successDiv.className = 'success-message';
                successDiv.textContent = 'Account created successfully! Please check your email to verify your account.';
                
                this.form.parentElement.insertBefore(successDiv, this.form);
                
                // Remove success message after 5 seconds
                setTimeout(() => {
                    successDiv.remove();
                }, 5000);
            }

            clearAllErrors() {
                Object.keys(this.errors).forEach(fieldName => {
                    this.clearError(fieldName);
                });
                
                // Reset password requirements
                const requirements = ['req-length', 'req-uppercase', 'req-lowercase', 'req-number', 'req-special'];
                requirements.forEach(req => {
                    document.getElementById(req).classList.remove('valid');
                });
            }
        }

        // Initialize the signup form when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            new SignupForm();
        });

        // Export for potential use in other scripts
        window.SignupForm = SignupForm;
    </script>
</body>
</html>

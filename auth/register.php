<?php
session_start();
require 'config.php';

// Handle OAuth callbacks
if (isset($_GET['provider'])) {
    $provider = $_GET['provider'];
    switch ($provider) {
        case 'google':
            header('Location: oauth_google.php');
            exit;
        case 'github':
            header('Location: oauth_github.php');
            exit;
        case 'microsoft':
            header('Location: oauth_microsoft.php');
            exit;
    }
}

// Handle traditional form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Basic validation
    if ($password !== $_POST['confirm-password']) {
        $error = "Passwords do not match!";
    } else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate database names
        $db_private = $username . '_private';
        $db_public = $username . '_public';
        
        try {
            // Insert user into database
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, db_private, db_public, auth_provider) VALUES (?, ?, ?, ?, ?, 'traditional')");
            $stmt->execute([$username, $email, $hashed_password, $db_private, $db_public]);
            
            // Create user databases
            createUserDatabases($username, $pdo);
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $username;
            header('Location: dashboard.php');
            exit;
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Username or email already exists!";
            } else {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}

function createUserDatabases($username, $pdo) {
    $private_db = $username . '_private';
    $public_db = $username . '_public';
    
    // Create private database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$private_db`");
    $pdo->exec("USE `$private_db`");
    
    // Create tables for private database
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS snippets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            code TEXT NOT NULL,
            language VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create public database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$public_db`");
    $pdo->exec("USE `$public_db`");
    
    // Create tables for public database (shared snippets)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shared_snippets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            code TEXT NOT NULL,
            language VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            views INT DEFAULT 0
        )
    ");
    
    // Switch back to main database
    $pdo->exec("USE codevault");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Code Vault</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Light Mode Colors */
            --primary-color: #6C63FF;
            --primary-dark: #554fd8;
            --secondary-color: #FF6584;
            --accent-color: #36D1DC;
            --text-color: #2D3748;
            --text-light: #718096;
            --bg-color: #FFFFFF;
            --bg-secondary: #F7FAFC;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.1);
            
            /* Typography */
            --font-family: 'Poppins', sans-serif;
            --font-light: 300;
            --font-regular: 400;
            --font-medium: 500;
            --font-semibold: 600;
            --font-bold: 700;
            
            /* Spacing */
            --section-padding: 5rem 1.5rem;
            --container-width: 1200px;
            
            /* Border Radius */
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 20px;
            --radius-xl: 30px;
            
            /* Transitions */
            --transition: all 0.3s ease;
            --transition-slow: all 0.5s ease;
        }

        /* Dark Mode Colors */
        .dark-mode {
            --primary-color: #6C63FF;
            --primary-dark: #554fd8;
            --secondary-color: #FF6584;
            --accent-color: #36D1DC;
            --text-color: #E2E8F0;
            --text-light: #A0AEC0;
            --bg-color: #1A202C;
            --bg-secondary: #2D3748;
            --bg-card: #2D3748;
            --border-color: #4A5568;
            --shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-family);
            font-weight: var(--font-regular);
            color: var(--text-color);
            background-color: var(--bg-color);
            line-height: 1.6;
            overflow-x: hidden;
            transition: var(--transition-slow);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-color) 100%);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        ul {
            list-style: none;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .container {
            max-width: var(--container-width);
            margin: 0 auto;
            padding: 0 1.5rem;
            width: 100%;
        }

        section {
            padding: var(--section-padding);
        }

        /* Sign Up Page Styles */
        .signup-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .form-container {
            background-color: var(--bg-card);
            padding: 3rem;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 2;
            animation: fadeInUp 0.8s ease;
            transition: var(--transition);
        }

        .form-container:hover {
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .form-header h1 {
            font-size: 2.5rem;
            font-weight: var(--font-bold);
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-header h2 {
            font-size: 1.5rem;
            font-weight: var(--font-medium);
            color: var(--text-light);
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: var(--font-medium);
            color: var(--text-color);
        }

        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: var(--transition);
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: var(--font-family);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
        }

        .form-group input:valid {
            border-color: var(--accent-color);
        }

        .password-strength {
            height: 4px;
            background-color: var(--border-color);
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-meter {
            height: 100%;
            width: 0;
            transition: var(--transition);
            border-radius: 2px;
        }

        .strength-weak {
            background-color: #FF6584;
            width: 33%;
        }

        .strength-medium {
            background-color: #FFB74D;
            width: 66%;
        }

        .strength-strong {
            background-color: #4CAF50;
            width: 100%;
        }

        .form-group button {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: var(--font-semibold);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .form-group button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .form-group button:hover::before {
            left: 100%;
        }

        .form-group button:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .form-group button:active {
            transform: translateY(0);
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-light);
        }

        .form-footer a {
            color: var(--primary-color);
            font-weight: var(--font-medium);
            transition: var(--transition);
        }

        .form-footer a:hover {
            color: var(--accent-color);
        }

        /* OAuth Buttons */
        .oauth-buttons {
            margin: 2rem 0;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .oauth-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.875rem 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-color);
            color: var(--text-color);
            font-weight: var(--font-medium);
            transition: var(--transition);
            gap: 0.75rem;
            text-decoration: none;
        }

        .oauth-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            border-color: var(--primary-color);
        }

        .oauth-google:hover {
            color: #DB4437;
            border-color: #DB4437;
        }

        .oauth-github:hover {
            color: #333;
            border-color: #333;
        }

        .oauth-microsoft:hover {
            color: #0078D4;
            border-color: #0078D4;
        }

        .divider {
            text-align: center;
            margin: 2rem 0;
            position: relative;
            color: var(--text-light);
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-color);
        }

        .divider span {
            background: var(--bg-card);
            padding: 0 1rem;
            position: relative;
            font-size: 0.875rem;
        }

        /* Error Message */
        .error-message {
            background: linear-gradient(135deg, #FF6584, #FF3366);
            color: white;
            padding: 0.875rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: var(--font-medium);
            box-shadow: var(--shadow);
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Theme Toggle */
        .theme-toggle {
            position: absolute;
            top: 2rem;
            right: 2rem;
            z-index: 10;
        }

        .toggle-checkbox {
            display: none;
        }

        .toggle-label {
            position: relative;
            display: flex;
            align-items: center;
            width: 60px;
            height: 30px;
            background-color: var(--bg-secondary);
            border-radius: 50px;
            padding: 5px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        .toggle-label i {
            position: absolute;
            font-size: 14px;
            transition: var(--transition);
            z-index: 2;
        }

        .toggle-label .fa-sun {
            left: 8px;
            color: #f39c12;
        }

        .toggle-label .fa-moon {
            right: 8px;
            color: #f1c40f;
        }

        .toggle-ball {
            position: absolute;
            width: 22px;
            height: 22px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-radius: 50%;
            transition: transform 0.3s ease;
            z-index: 1;
        }

        .toggle-checkbox:checked + .toggle-label .toggle-ball {
            transform: translateX(30px);
        }

        /* Background Decorations */
        .bg-decoration {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            z-index: 1;
        }

        .decoration-1 {
            width: 300px;
            height: 300px;
            background: var(--primary-color);
            top: -150px;
            left: -150px;
            animation: pulse 8s ease-in-out infinite;
        }

        .decoration-2 {
            width: 200px;
            height: 200px;
            background: var(--accent-color);
            bottom: -100px;
            right: 10%;
            animation: pulse 6s ease-in-out infinite reverse;
        }

        .decoration-3 {
            width: 150px;
            height: 150px;
            background: var(--secondary-color);
            top: 20%;
            right: -75px;
            animation: pulse 7s ease-in-out infinite 2s;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 0.2;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.4;
            }
            100% {
                transform: scale(1);
                opacity: 0.2;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .form-container {
                padding: 2rem;
                margin: 1rem;
            }

            .form-header h1 {
                font-size: 2rem;
            }

            .form-header h2 {
                font-size: 1.25rem;
            }

            .theme-toggle {
                top: 1rem;
                right: 1rem;
            }

            .bg-decoration {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .form-container {
                padding: 1.5rem;
            }

            .form-header h1 {
                font-size: 1.75rem;
            }

            .form-header h2 {
                font-size: 1.1rem;
            }

            .oauth-btn {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body><br>
    <div class="signup-container">
        <!-- Background Decorations -->
        <div class="bg-decoration decoration-1"></div>
        <div class="bg-decoration decoration-2"></div>
        <div class="bg-decoration decoration-3"></div>
        
        <!-- Theme Toggle -->
        <div class="theme-toggle">
            <input type="checkbox" id="theme-toggle" class="toggle-checkbox">
            <label for="theme-toggle" class="toggle-label">
                <i class="fas fa-sun"></i>
                <i class="fas fa-moon"></i>
                <span class="toggle-ball"></span>
            </label>
        </div>

        <div class="form-container" style ="margin-top: 1cm;margin-bottom: 1cm;">
            <div class="form-header">
                <h1>Welcome to Code Vault</h1>
                <h2>Create Your Account</h2>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- OAuth Buttons -->
            <div class="oauth-buttons">
                <a href="?provider=google" class="oauth-btn oauth-google">
                    <i class="fab fa-google"></i>
                    Continue with Google
                </a>
            </div>

            <div class="divider">
                <span>or sign up with email</span>
            </div>
            
            <form action="" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-strength">
                        <div class="strength-meter" id="password-strength-meter"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input type="password" id="confirm-password" name="confirm-password" required>
                </div>

                <div class="form-group">
                    <button type="submit">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </div>

                <div class="form-footer">
                    Already have an account? <a href="login.php">Sign in here</a><br>
                    Want to go back home? <a href="../index.php">Return Home</a>
                </div>
            </form>
        </div><br>
    </div>

    <script>
        // Theme Toggle Functionality
        const themeToggle = document.getElementById('theme-toggle');
        const body = document.body;

        // Check for saved theme preference or default to light mode
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            themeToggle.checked = true;
        }

        themeToggle.addEventListener('change', function() {
            if (this.checked) {
                body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            } else {
                body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            }
        });

        // Password Strength Indicator
        const passwordInput = document.getElementById('password');
        const strengthMeter = document.getElementById('password-strength-meter');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Check password length
            if (password.length >= 8) strength += 1;
            
            // Check for lowercase letters
            if (/[a-z]/.test(password)) strength += 1;
            
            // Check for uppercase letters
            if (/[A-Z]/.test(password)) strength += 1;
            
            // Check for numbers
            if (/[0-9]/.test(password)) strength += 1;
            
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update strength meter
            strengthMeter.className = 'strength-meter';
            if (password.length === 0) {
                strengthMeter.style.width = '0';
            } else if (strength <= 2) {
                strengthMeter.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthMeter.classList.add('strength-medium');
            } else {
                strengthMeter.classList.add('strength-strong');
            }
        });

        // Form validation
        const form = document.querySelector('form');
        const confirmPasswordInput = document.getElementById('confirm-password');

        form.addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                confirmPasswordInput.focus();
            }
        });

        // Add input validation styling
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value) {
                    this.classList.add('filled');
                } else {
                    this.classList.remove('filled');
                }
            });
        });
    </script>
</body>
</html>
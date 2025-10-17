<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Database configuration
$host = 'localhost';
$db   = 'codevault';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Initialize variables
$userData = [];
$success_message = '';
$error_message = '';

// Default values for new columns
$themePreference = 'light';
$emailNotifications = 1;

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if new columns exist, if not create them
    $checkColumns = $pdo->prepare("
        SELECT COUNT(*) as column_count 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'users' 
        AND COLUMN_NAME IN ('theme_preference', 'email_notifications')
    ");
    $checkColumns->execute([$db]);
    $columnCount = $checkColumns->fetch()['column_count'];
    
    if ($columnCount < 2) {
        // Add missing columns
        try {
            $pdo->exec("
                ALTER TABLE users 
                ADD COLUMN theme_preference ENUM('light', 'dark') DEFAULT 'light',
                ADD COLUMN email_notifications TINYINT(1) DEFAULT 1
            ");
            $success_message = "Database updated successfully!";
        } catch (PDOException $e) {
            error_log("Failed to add columns: " . $e->getMessage());
            // Continue without the new columns
        }
    }
    
    // Get user data - handle both old and new schema
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, auth_provider, created_at, theme_preference, email_notifications FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
    } catch (PDOException $e) {
        // Fallback to basic user data if new columns don't exist
        $stmt = $pdo->prepare("SELECT id, username, email, auth_provider, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
        
        // Set default values
        $userData['theme_preference'] = 'light';
        $userData['email_notifications'] = 1;
    }
    
    if (!$userData) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_profile'])) {
            // Update profile information
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            
            // Validate inputs
            if (empty($username) || empty($email)) {
                $error_message = "Username and email are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Please enter a valid email address.";
            } else {
                // Check if username or email already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $_SESSION['user_id']]);
                $existingUser = $stmt->fetch();
                
                if ($existingUser) {
                    $error_message = "Username or email already exists.";
                } else {
                    // Update user profile
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $_SESSION['user_id']]);
                    
                    // Update session username if changed
                    if ($username !== $_SESSION['username']) {
                        $_SESSION['username'] = $username;
                    }
                    
                    $success_message = "Profile updated successfully!";
                    
                    // Refresh user data
                    try {
                        $stmt = $pdo->prepare("SELECT id, username, email, auth_provider, created_at, theme_preference, email_notifications FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $userData = $stmt->fetch();
                    } catch (PDOException $e) {
                        $stmt = $pdo->prepare("SELECT id, username, email, auth_provider, created_at FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $userData = $stmt->fetch();
                        $userData['theme_preference'] = $themePreference;
                        $userData['email_notifications'] = $emailNotifications;
                    }
                }
            }
        }
        
        elseif (isset($_POST['change_password'])) {
            // Change password
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validate passwords
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = "All password fields are required.";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match.";
            } elseif (strlen($new_password) < 6) {
                $error_message = "New password must be at least 6 characters long.";
            } else {
                // Verify current password (for non-OAuth users)
                if ($userData['auth_provider'] === 'local') {
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $dbPassword = $stmt->fetchColumn();
                    
                    if (!password_verify($current_password, $dbPassword)) {
                        $error_message = "Current password is incorrect.";
                    } else {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                        $success_message = "Password changed successfully!";
                    }
                } else {
                    $error_message = "Password change is not available for OAuth accounts.";
                }
            }
        }
        
        elseif (isset($_POST['update_preferences'])) {
            // Update preferences
            $theme_preference = $_POST['theme_preference'];
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET theme_preference = ?, email_notifications = ? WHERE id = ?");
                $stmt->execute([$theme_preference, $email_notifications, $_SESSION['user_id']]);
                $success_message = "Preferences updated successfully!";
            } catch (PDOException $e) {
                // If columns don't exist yet, try to create them
                try {
                    $pdo->exec("
                        ALTER TABLE users 
                        ADD COLUMN theme_preference ENUM('light', 'dark') DEFAULT 'light',
                        ADD COLUMN email_notifications TINYINT(1) DEFAULT 1
                    ");
                    // Retry the update
                    $stmt = $pdo->prepare("UPDATE users SET theme_preference = ?, email_notifications = ? WHERE id = ?");
                    $stmt->execute([$theme_preference, $email_notifications, $_SESSION['user_id']]);
                    $success_message = "Preferences updated successfully!";
                } catch (PDOException $e2) {
                    $error_message = "Failed to update preferences. Please try again.";
                }
            }
            
            // Refresh user data
            try {
                $stmt = $pdo->prepare("SELECT id, username, email, auth_provider, created_at, theme_preference, email_notifications FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $userData = $stmt->fetch();
            } catch (PDOException $e) {
                $stmt = $pdo->prepare("SELECT id, username, email, auth_provider, created_at FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $userData = $stmt->fetch();
                $userData['theme_preference'] = $theme_preference;
                $userData['email_notifications'] = $email_notifications;
            }
        }
        
        elseif (isset($_POST['delete_account'])) {
            // Delete account confirmation
            $confirm_delete = $_POST['confirm_delete'] ?? '';
            
            if ($confirm_delete === 'DELETE') {
                // Begin transaction
                $pdo->beginTransaction();
                
                try {
                    // Get user's repositories
                    $stmt = $pdo->prepare("SELECT repo_id, db_name FROM repositories WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $userRepos = $stmt->fetchAll();
                    
                    // Drop repository databases
                    foreach ($userRepos as $repo) {
                        try {
                            $pdo->exec("DROP DATABASE IF EXISTS `" . $repo['db_name'] . "`");
                        } catch (PDOException $e) {
                            error_log("Failed to drop database {$repo['db_name']}: " . $e->getMessage());
                        }
                    }
                    
                    // Remove user from collaborators
                    $stmt = $pdo->prepare("DELETE FROM collaborators WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    // Delete user's repositories
                    $stmt = $pdo->prepare("DELETE FROM repositories WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    // Delete user account
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    // Logout and redirect
                    session_destroy();
                    header('Location: login.php?message=account_deleted');
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "Failed to delete account: " . $e->getMessage();
                }
            } else {
                $error_message = "Please type 'DELETE' to confirm account deletion.";
            }
        }
    }
    
} catch (PDOException $e) {
    error_log("Settings error: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
}

// Safe display variables
$displayUsername = htmlspecialchars($userData['username'] ?? '');
$displayEmail = htmlspecialchars($userData['email'] ?? '');
$userInitial = strtoupper(substr($userData['username'] ?? 'U', 0, 1));
$authProvider = $userData['auth_provider'] ?? 'local';
$createdAt = $userData['created_at'] ?? '';
$themePreference = $userData['theme_preference'] ?? 'light';
$emailNotifications = $userData['email_notifications'] ?? 1;
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $themePreference; ?>">
<head>
    <meta charset="UTF-8">
    <title>Settings | CodeVault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ... (keep all the CSS styles from the previous version) ... */
        /* The CSS remains exactly the same as in the previous version */
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border-radius: 12px;
            --shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        [data-theme="dark"] {
            --light: #1a1d23;
            --dark: #f8f9fa;
            --gray-light: #2d3239;
            --gray: #adb5bd;
            --shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
            transition: var(--transition);
        }

        .settings-container {
            display: flex;
            min-height: 100vh;
        }

        /* ... (rest of the CSS remains identical) ... */
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 25px 0;
            box-shadow: var(--shadow);
            z-index: 100;
            transition: var(--transition);
        }

        .logo {
            display: flex;
            align-items: center;
            padding: 0 25px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 25px;
        }

        .logo i {
            font-size: 28px;
            margin-right: 12px;
        }

        .logo h1 {
            font-size: 22px;
            font-weight: 600;
        }

        .nav-links {
            list-style: none;
            padding: 0 15px;
        }

        .nav-links li {
            margin-bottom: 8px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .nav-links a:hover, .nav-links a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-links i {
            margin-right: 12px;
            font-size: 18px;
            width: 24px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            transition: var(--transition);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h2 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .theme-toggle {
            background: var(--light);
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .theme-toggle:hover {
            background: var(--primary);
            color: white;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--light);
            padding: 8px 15px;
            border-radius: 50px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .logout-btn {
            background: var(--light);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .logout-btn:hover {
            background: var(--danger);
            color: white;
        }

        /* Settings Content */
        .settings-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .settings-section {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-light);
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin-right: 15px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
        }

        .section-description {
            color: var(--gray);
            font-size: 14px;
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            background: var(--light);
            color: var(--dark);
            font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-control:disabled {
            background: var(--gray-light);
            color: var(--gray);
            cursor: not-allowed;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .radio-option input {
            margin: 0;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c1121f;
        }

        .btn:disabled {
            background: var(--gray-light);
            color: var(--gray);
            cursor: not-allowed;
        }

        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(76, 201, 240, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(230, 57, 70, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .alert-warning {
            background: rgba(248, 150, 30, 0.1);
            border: 1px solid var(--warning);
            color: var(--warning);
        }

        /* Account Info */
        .account-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .info-item {
            background: var(--gray-light);
            padding: 15px;
            border-radius: var(--border-radius);
        }

        .info-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .info-value {
            font-weight: 500;
            color: var(--dark);
        }

        .auth-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .auth-local {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .auth-oauth {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        /* Danger Zone */
        .danger-zone {
            border: 2px solid var(--danger);
            background: rgba(230, 57, 70, 0.05);
        }

        .danger-zone .section-icon {
            background: var(--danger);
        }

        .danger-note {
            color: var(--danger);
            font-size: 14px;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(230, 57, 70, 0.1);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--danger);
        }

        .confirm-input {
            font-family: monospace;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .settings-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 15px 0;
            }
            
            .nav-links {
                display: flex;
                overflow-x: auto;
                padding: 0 10px;
            }
            
            .nav-links li {
                margin-bottom: 0;
                margin-right: 10px;
            }
            
            .nav-links a {
                white-space: nowrap;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .user-menu {
                width: 100%;
                justify-content: space-between;
            }
            
            .settings-section {
                padding: 20px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .section-icon {
                margin-right: 0;
            }
            
            .account-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-code"></i>
                <h1>CodeVault</h1>
            </div>
            <ul class="nav-links">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="my_rep.php"><i class="fas fa-code-branch"></i> My Repositories</a></li>
                <li><a href="#" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../index.php#contact"><i class="fas fa-question-circle"></i> Help & Support</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2>Account Settings</h2>
                <div class="header-actions">
                    <button class="theme-toggle" id="themeToggle">
                        <i class="fas fa-moon"></i>
                    </button>
                    <div class="user-menu">
                        <div class="user-info">
                            <div class="user-avatar">
                                <?php echo $userInitial; ?>
                            </div>
                            <span><?php echo $displayUsername; ?></span>
                        </div>
                        <button class="logout-btn" onclick="logout()">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="settings-content">
                <!-- Alerts -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Settings -->
                <div class="settings-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div class="section-title">Profile Information</div>
                            <div class="section-description">Update your account's profile information and email address</div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo $displayUsername; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $displayEmail; ?>" required>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>

                    <!-- Account Information -->
                    <div class="account-info">
                        <div class="info-item">
                            <div class="info-label">Account Created</div>
                            <div class="info-value"><?php echo date('M j, Y', strtotime($createdAt)); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Authentication</div>
                            <div class="info-value">
                                <span class="auth-badge <?php echo $authProvider === 'local' ? 'auth-local' : 'auth-oauth'; ?>">
                                    <i class="fas fa-<?php echo $authProvider === 'local' ? 'user' : 'key'; ?>"></i>
                                    <?php echo ucfirst($authProvider); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">User ID</div>
                            <div class="info-value">#<?php echo $_SESSION['user_id']; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Password Change -->
                <?php if ($authProvider === 'local'): ?>
                <div class="settings-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div>
                            <div class="section-title">Change Password</div>
                            <div class="section-description">Ensure your account is using a long, random password to stay secure</div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required 
                                   minlength="6" placeholder="At least 6 characters">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Preferences -->
                <div class="settings-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-palette"></i>
                        </div>
                        <div>
                            <div class="section-title">Preferences</div>
                            <div class="section-description">Customize your application experience</div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label>Theme Preference</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="theme_preference" value="light" <?php echo $themePreference === 'light' ? 'checked' : ''; ?>>
                                    <i class="fas fa-sun"></i> Light
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="theme_preference" value="dark" <?php echo $themePreference === 'dark' ? 'checked' : ''; ?>>
                                    <i class="fas fa-moon"></i> Dark
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="email_notifications" name="email_notifications" 
                                       <?php echo $emailNotifications ? 'checked' : ''; ?>>
                                <label for="email_notifications">Enable email notifications</label>
                            </div>
                        </div>

                        <button type="submit" name="update_preferences" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </form>
                </div>

                <!-- Danger Zone -->
                <div class="settings-section danger-zone">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="section-title">Danger Zone</div>
                            <div class="section-description">Permanently delete your account and all associated data</div>
                        </div>
                    </div>

                    <div class="danger-note">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Warning:</strong> This action cannot be undone. This will permanently delete your account, 
                        all your repositories, and remove all your data from our servers.
                    </div>

                    <form method="POST" onsubmit="return confirmAccountDeletion()">
                        <div class="form-group">
                            <label for="confirm_delete">
                                To confirm deletion, type <strong>DELETE</strong> in the box below
                            </label>
                            <input type="text" class="form-control confirm-input" id="confirm_delete" name="confirm_delete" 
                                   placeholder="DELETE" pattern="DELETE" required>
                        </div>

                        <button type="submit" name="delete_account" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Account Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = themeToggle.querySelector('i');
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            themeIcon.className = newTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            
            // Save to localStorage for immediate effect
            localStorage.setItem('theme', newTheme);
        });
        
        // Load saved theme from localStorage
        const savedTheme = localStorage.getItem('theme') || '<?php echo $themePreference; ?>';
        document.documentElement.setAttribute('data-theme', savedTheme);
        themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        function confirmAccountDeletion() {
            const confirmText = document.getElementById('confirm_delete').value;
            if (confirmText !== 'DELETE') {
                alert('Please type DELETE to confirm account deletion.');
                return false;
            }
            return confirm('ARE YOU ABSOLUTELY SURE?\n\nThis action CANNOT be undone. This will permanently delete your account and all your repositories.');
        }

        // Password confirmation validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePassword() {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        if (newPassword && confirmPassword) {
            newPassword.addEventListener('change', validatePassword);
            confirmPassword.addEventListener('keyup', validatePassword);
        }

        // Real-time theme preview
        const themeRadios = document.querySelectorAll('input[name="theme_preference"]');
        themeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                document.documentElement.setAttribute('data-theme', this.value);
                localStorage.setItem('theme', this.value);
            });
        });
    </script>
</body>
</html>
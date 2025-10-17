<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
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

// Google OAuth configuration
$clientId = '69324994611-qfheni1vpg623f538de5shmsrtbebu0o.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://localhost/WEBPAGES/CodeVault/auth/login.php';

// Handle Google OAuth callback
if (isset($_GET['code']) && isset($_GET['state'])) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Validate state parameter
        if (!isset($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
            throw new Exception('Invalid state parameter');
        }
        
        unset($_SESSION['oauth2state']);

        // Exchange authorization code for access token
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $tokenParams = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $_GET['code'],
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $tokenResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Token exchange failed');
        }

        $tokenData = json_decode($tokenResponse, true);
        
        if (!isset($tokenData['access_token'])) {
            throw new Exception('No access token received');
        }

        $accessToken = $tokenData['access_token'];

        // Get user info from Google API
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $userResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('User info fetch failed');
        }

        $userData = json_decode($userResponse, true);
        
        if (!$userData || !isset($userData['id'])) {
            throw new Exception('Invalid user data received');
        }

        // Extract user information
        $googleId = $userData['id'];
        $email = $userData['email'];
        $name = $userData['name'] ?? $userData['email'];

        // Check if user exists in database
        $stmt = $pdo->prepare("SELECT id, username, email, auth_provider, db_private, db_public FROM users WHERE provider_id = ? AND auth_provider = 'google'");
        $stmt->execute([$googleId]);
        $user = $stmt->fetch();

        if (!$user) {
            // User doesn't exist, redirect to login with error
            $_SESSION['login_error'] = "No account found with this Google account. Please sign up first.";
            header('Location: login.php');
            exit;
        }

        // User exists - set session and redirect to dashboard
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['auth_provider'] = $user['auth_provider'];
        $_SESSION['db_private'] = $user['db_private'];
        $_SESSION['db_public'] = $user['db_public'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        // Update last login time
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Redirect to dashboard
        header('Location: dashboard.php');
        exit;

    } catch (Exception $e) {
        error_log("Google OAuth error: " . $e->getMessage());
        $_SESSION['login_error'] = "Google login failed. Please try again.";
        header('Location: login.php');
        exit;
    }
}

// Handle traditional form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Get and validate input
        $email_or_username = trim($_POST['email']);
        $password = $_POST['password'];

        // Basic validation
        if (empty($email_or_username) || empty($password)) {
            $error = "Please enter both email/username and password.";
        } else {
            // Check if user exists by email or username
            $stmt = $pdo->prepare("
                SELECT id, username, email, password, auth_provider, db_private, db_public 
                FROM users 
                WHERE (email = ? OR username = ?)
            ");
            $stmt->execute([$email_or_username, $email_or_username]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "Invalid email/username or password.";
            } elseif ($user['auth_provider'] !== 'traditional') {
                $error = "This account uses " . ucfirst($user['auth_provider']) . " login. Please use that method.";
            } elseif (!password_verify($password, $user['password'])) {
                $error = "Invalid email/username or password.";
            } else {
                // Check if password needs rehashing
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$newHash, $user['id']]);
                }

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['auth_provider'] = $user['auth_provider'];
                $_SESSION['db_private'] = $user['db_private'];
                $_SESSION['db_public'] = $user['db_public'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();

                // Update last login time
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);

                // Regenerate session ID for security
                session_regenerate_id(true);

                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "An error occurred during login. Please try again.";
    }
}

// Handle session errors and messages
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle Google OAuth initiation
if (isset($_GET['google_login'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth2state'] = $state;
    
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'consent'
    ];
    
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    header('Location: ' . $authUrl);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | CodeVault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Your existing CSS styles remain the same */
        :root {
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
            --font-family: 'Poppins', sans-serif;
        }

        .dark-mode {
            --text-color: #E2E8F0;
            --text-light: #A0AEC0;
            --bg-color: #1A202C;
            --bg-secondary: #2D3748;
            --bg-card: #2D3748;
            --border-color: #4A5568;
            --shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
        }

        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            width: 100%;
            position: relative;
        }

        .form-container {
            background-color: var(--bg-card);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 480px;
            margin: 1rem;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .form-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: var(--font-family);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
        }

        .form-group button {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.3s ease;
        }

        .form-group button:hover {
            transform: translateY(-2px);
        }

        .error-message {
            background: linear-gradient(135deg, #FF6584, #FF3366);
            color: white;
            padding: 0.875rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }

        .success-message {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 0.875rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }

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
            border-radius: 12px;
            background: var(--bg-color);
            color: var(--text-color);
            font-weight: 500;
            transition: all 0.3s ease;
            gap: 0.75rem;
            text-decoration: none;
        }

        .oauth-btn:hover {
            transform: translateY(-2px);
            border-color: var(--primary-color);
        }

        .oauth-google:hover {
            color: #DB4437;
            border-color: #DB4437;
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-light);
        }

        .form-footer a {
            color: var(--primary-color);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="form-container">
            <div class="form-header">
                <h1>Welcome Back</h1>
                <h2>Sign in to your CodeVault</h2>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success-message"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email or Username</label>
                    <input type="text" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <button type="submit">
                        <i class="fas fa-sign-in-alt"></i> Log In<br>
                    </button>
                </div>
            </form>

            <div style="text-align: center; margin: 2rem 0; position: relative;">
                <span style="background: var(--bg-card); padding: 0 1rem; position: relative; color: var(--text-light);">or</span>
                <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: var(--border-color); z-index: -1;"></div>
            </div>

            <!-- OAuth Buttons -->
            <div class="oauth-buttons">
                <a href="?google_login=1" class="oauth-btn oauth-google">
                    <i class="fab fa-google"></i> Continue with Google
                </a>
            </div>

            <div class="form-footer">
                Don't have an account?
                <a href="register.php" style="text-decoration: none;">Sign Up</a><br>
                    Want to go back home? <a href="../index.php" style="text-decoration: none;">Return Home</a>
            </div>
        </div>
    </div>

    <script>
        // Simple theme toggle
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
        }
    </script>
    <script>
        // Prevent back button after logout
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };

        // Clear form on page load
        window.onload = function() {
            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
        };
    </script>
</body>
</html>
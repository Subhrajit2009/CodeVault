<?php
session_start();

// Database connection
$host = 'localhost';
$db   = 'codevault';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit('Database connection failed: ' . $e->getMessage());
}

// Google OAuth configuration - MAKE SURE THIS MATCHES GOOGLE CLOUD CONSOLE
$clientId = '69324994611-qfheni1vpg623f538de5shmsrtbebu0o.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://localhost/WEBPAGES/CodeVault/auth/oauth_google.php'; // Changed to this file

// Step 1: Redirect to Google OAuth if no code
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth2state'] = $state;
    
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'consent' // Force consent screen
    ];
    
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    header('Location: ' . $authUrl);
    exit;
}

// Step 2: Validate state parameter
if (empty($_GET['state']) || !isset($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
    unset($_SESSION['oauth2state']);
    header('Location: login.php?error=invalid_state');
    exit;
}

// Clear the state
unset($_SESSION['oauth2state']);

// Step 3: Exchange authorization code for access token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenParams = [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => $_GET['code'],
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirectUri
];

// Use cURL to get access token
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
    error_log("Token exchange failed: " . $tokenResponse);
    header('Location: login.php?error=token_exchange_failed');
    exit;
}

$tokenData = json_decode($tokenResponse, true);

if (!isset($tokenData['access_token'])) {
    header('Location: login.php?error=no_access_token');
    exit;
}

$accessToken = $tokenData['access_token'];

// Step 4: Get user info from Google API
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
    error_log("User info fetch failed: " . $userResponse);
    header('Location: login.php?error=user_info_failed');
    exit;
}

$userData = json_decode($userResponse, true);

// Process user data
$googleId = $userData['id'];
$email = $userData['email'];
$name = $userData['name'] ?? $userData['email'];
$givenName = $userData['given_name'] ?? '';
$familyName = $userData['family_name'] ?? '';

// Generate safe username
$baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($name));
$username = $baseUsername ?: 'user_' . substr(md5($googleId), 0, 8);

// Ensure username is unique
$counter = 1;
$originalUsername = $username;
while (true) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if (!$stmt->fetch()) {
        break;
    }
    $username = $originalUsername . '_' . $counter;
    $counter++;
    if ($counter > 100) {
        $username = 'user_' . substr(md5($googleId . time()), 0, 12);
        break;
    }
}

// Generate database names
$db_private = $username . '_private';
$db_public = $username . '_public';

try {
    // Check if user exists with this Google ID
    $stmt = $pdo->prepare("SELECT id, username, db_private, db_public FROM users WHERE provider_id = ? AND auth_provider = 'google'");
    $stmt->execute([$googleId]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingUser) {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $emailUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($emailUser) {
            header('Location: login.php?error=email_exists&provider=google');
            exit;
        }

        // Begin transaction
        $pdo->beginTransaction();

        try {
            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO users (username, email, auth_provider, provider_id, db_private, db_public, created_at) 
                                   VALUES (?, ?, 'google', ?, ?, ?, NOW())");
            $stmt->execute([$username, $email, $googleId, $db_private, $db_public]);

            $userId = $pdo->lastInsertId();

            // Create user's private database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_private`");
            $pdo->exec("USE `$db_private`");
            
           
            
            
            // Create user's public database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_public`");
            $pdo->exec("USE `$db_public`");
            
            

            // Switch back to main database
            $pdo->exec("USE `$db`");
            $pdo->commit();

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } else {
        // Existing user
        $userId = $existingUser['id'];
        $username = $existingUser['username'];
        $db_private = $existingUser['db_private'];
        $db_public = $existingUser['db_public'];
        
        // Update last login time
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }

    // Set session variables
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['auth_provider'] = 'google';
    $_SESSION['db_private'] = $db_private;
    $_SESSION['db_public'] = $db_public;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    // Regenerate session ID for security
    session_regenerate_id(true);

    // Redirect to dashboard
    header('Location: ../dashboard.php');
    exit;

} catch (Exception $e) {
    error_log("Google OAuth error: " . $e->getMessage());
    header('Location: login.php?error=oauth_failed');
    exit;
}
?>
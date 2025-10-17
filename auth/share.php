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
$repoData = [];
$collaborators = [];
$pendingInvites = [];
$error = '';
$success = '';
$username = 'User';

// Get repository ID from URL
$repoId = $_GET['id'] ?? '';

if (!$repoId) {
    header('Location: my_rep.php');
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Create necessary tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS collaborator_invites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        repo_id VARCHAR(50) NOT NULL,
        target_email VARCHAR(255) NOT NULL,
        permission_level ENUM('read', 'write', 'admin') DEFAULT 'read',
        invited_by INT NOT NULL,
        invite_token VARCHAR(32) NOT NULL,
        status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        responded_at TIMESTAMP NULL
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS share_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        repo_id VARCHAR(50) NOT NULL,
        share_token VARCHAR(64) NOT NULL UNIQUE,
        created_by INT NOT NULL,
        expires_at DATETIME NOT NULL,
        max_uses INT DEFAULT 100,
        use_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create collaborators table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS collaborators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        repo_id VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        permission_level ENUM('read', 'write', 'admin') DEFAULT 'read',
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_collaborator (repo_id, user_id)
    )");
    
    // Get user data
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    $username = $userData['username'] ?? 'User';
    $userEmail = $userData['email'] ?? '';
    
    // Get repository data and verify ownership
    $stmt = $pdo->prepare("SELECT r.*, u.username, u.email as owner_email 
                          FROM repositories r 
                          JOIN users u ON r.user_id = u.id 
                          WHERE r.repo_id = ? AND r.user_id = ?");
    $stmt->execute([$repoId, $_SESSION['user_id']]);
    $repoData = $stmt->fetch();
    
    if (!$repoData) {
        throw new Exception("Repository not found or access denied");
    }
    
    // Get existing collaborators
    $collabStmt = $pdo->prepare("
        SELECT c.*, u.username, u.email 
        FROM collaborators c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.repo_id = ? 
        ORDER BY c.added_at DESC
    ");
    $collabStmt->execute([$repoId]);
    $collaborators = $collabStmt->fetchAll();
    
    // Get pending invites
    $inviteStmt = $pdo->prepare("
        SELECT ci.*, u.username as invited_by_username
        FROM collaborator_invites ci
        JOIN users u ON ci.invited_by = u.id
        WHERE ci.repo_id = ? AND ci.status = 'pending'
        ORDER BY ci.created_at DESC
    ");
    $inviteStmt->execute([$repoId]);
    $pendingInvites = $inviteStmt->fetchAll();
    
    // Handle POST operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle collaborator invitation
        if (isset($_POST['invite_collaborator'])) {
            $collabEmail = trim($_POST['collab_email']);
            $permission = $_POST['permission_level'] ?? 'read';
            
            if (empty($collabEmail)) {
                $error = "Email address is required";
            } elseif (!filter_var($collabEmail, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address";
            } else {
                // Check if user exists
                $userStmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
                $userStmt->execute([$collabEmail]);
                $targetUser = $userStmt->fetch();
                
                if ($targetUser) {
                    $targetUserId = $targetUser['id'];
                    
                    // Check if already collaborator
                    $existingStmt = $pdo->prepare("SELECT id FROM collaborators WHERE repo_id = ? AND user_id = ?");
                    $existingStmt->execute([$repoId, $targetUserId]);
                    
                    if ($existingStmt->fetch()) {
                        $error = "User is already a collaborator";
                    } else {
                        // Check for pending invite
                        $pendingStmt = $pdo->prepare("SELECT id FROM collaborator_invites WHERE repo_id = ? AND target_email = ? AND status = 'pending'");
                        $pendingStmt->execute([$repoId, $collabEmail]);
                        
                        if ($pendingStmt->fetch()) {
                            $error = "Invitation already sent to this email";
                        } else {
                            // Add collaborator directly if user exists
                            $insertStmt = $pdo->prepare("INSERT INTO collaborators (repo_id, user_id, permission_level) VALUES (?, ?, ?)");
                            $insertStmt->execute([$repoId, $targetUserId, $permission]);
                            
                            $success = "Collaborator {$targetUser['username']} ($collabEmail) added successfully with $permission access!";
                        }
                    }
                } else {
                    // User doesn't exist, create pending invitation
                    $inviteStmt = $pdo->prepare("
                        INSERT INTO collaborator_invites (repo_id, target_email, permission_level, invited_by, invite_token) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $inviteToken = bin2hex(random_bytes(16));
                    $inviteStmt->execute([$repoId, $collabEmail, $permission, $_SESSION['user_id'], $inviteToken]);
                    
                    $success = "Invitation sent to $collabEmail! They will be able to join once they create an account.";
                }
            }
        }
        
        // Handle remove collaborator
        if (isset($_POST['remove_collaborator'])) {
            $collabId = $_POST['collab_id'];
            $deleteStmt = $pdo->prepare("DELETE FROM collaborators WHERE id = ? AND repo_id = ?");
            $deleteStmt->execute([$collabId, $repoId]);
            
            $success = "Collaborator removed successfully!";
        }
        
        // Handle cancel invitation
        if (isset($_POST['cancel_invite'])) {
            $inviteId = $_POST['invite_id'];
            $deleteStmt = $pdo->prepare("DELETE FROM collaborator_invites WHERE id = ? AND repo_id = ?");
            $deleteStmt->execute([$inviteId, $repoId]);
            
            $success = "Invitation cancelled successfully!";
        }
        
        // Handle permission update
        if (isset($_POST['update_permission'])) {
            $collabId = $_POST['collab_id'];
            $newPermission = $_POST['new_permission'];
            
            $updateStmt = $pdo->prepare("UPDATE collaborators SET permission_level = ? WHERE id = ? AND repo_id = ?");
            $updateStmt->execute([$newPermission, $collabId, $repoId]);
            
            $success = "Permission updated successfully!";
        }
        
        // Handle repository visibility change
        if (isset($_POST['change_visibility'])) {
            $newVisibility = $_POST['visibility'];
            
            $updateStmt = $pdo->prepare("UPDATE repositories SET type = ? WHERE repo_id = ? AND user_id = ?");
            $updateStmt->execute([$newVisibility, $repoId, $_SESSION['user_id']]);
            
            $success = "Repository visibility changed to $newVisibility!";
        }
        
        // Generate shareable link
        if (isset($_POST['generate_share_link'])) {
            $shareToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // Delete existing share links
            $deleteStmt = $pdo->prepare("DELETE FROM share_links WHERE repo_id = ?");
            $deleteStmt->execute([$repoId]);
            
            // Create new share link
            $insertStmt = $pdo->prepare("
                INSERT INTO share_links (repo_id, share_token, created_by, expires_at, max_uses) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$repoId, $shareToken, $_SESSION['user_id'], $expiresAt, 100]);
            
            $success = "Shareable link generated successfully!";
        }
    }
    
    // Get share links
    $shareLinkStmt = $pdo->prepare("SELECT * FROM share_links WHERE repo_id = ? ORDER BY created_at DESC");
    $shareLinkStmt->execute([$repoId]);
    $shareLinks = $shareLinkStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Share Repository error: " . $e->getMessage());
    $error = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    error_log("Repository error: " . $e->getMessage());
    $error = $e->getMessage();
}

// Safe display variables
$displayUsername = htmlspecialchars($username);
$repoName = htmlspecialchars($repoData['name'] ?? '');
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Share <?php echo $repoName; ?> | CodeVault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- EmailJS SDK -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --success: #48bb78;
            --warning: #ed8936;
            --danger: #f56565;
            --light: #f7fafc;
            --dark: #2d3748;
            --gray: #718096;
            --gray-light: #e2e8f0;
            --border-radius: 16px;
            --shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.12);
        }

        [data-theme="dark"] {
            --light: #1a202c;
            --dark: #f7fafc;
            --gray-light: #2d3748;
            --gray: #a0aec0;
            --shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--dark);
            line-height: 1.6;
            transition: var(--transition);
            min-height: 100vh;
            padding: 20px;
        }

        .share-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .share-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .share-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .repo-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .repo-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .repo-details h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            color: white;
            letter-spacing: -0.5px;
        }

        .repo-details p {
            opacity: 0.9;
            font-size: 16px;
            font-weight: 400;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .back-btn, .theme-toggle {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            backdrop-filter: blur(10px);
            font-size: 18px;
        }

        .back-btn:hover, .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        /* Main Grid */
        .share-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 25px;
            margin-bottom: 25px;
        }

        /* Cards */
        .share-card {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            position: relative;
            overflow: hidden;
        }

        .share-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            opacity: 0;
            transition: var(--transition);
        }

        .share-card:hover::before {
            opacity: 1;
        }

        .share-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--gray-light);
        }

        .card-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title i {
            color: var(--primary);
            font-size: 24px;
        }

        /* Invite Form */
        .invite-form {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 12px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            background: var(--light);
            color: var(--dark);
            font-size: 15px;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .select-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23718096'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 18px;
            padding-right: 50px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #38a169);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(72, 187, 120, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #e53e3e);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245, 101, 101, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #dd6b20);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(237, 137, 54, 0.3);
        }

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        .btn-sm {
            padding: 10px 18px;
            font-size: 13px;
        }

        /* Lists */
        .collaborator-list, .invite-list {
            space-y: 12px;
        }

        .collaborator-item, .invite-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: var(--gray-light);
            border-radius: 14px;
            margin-bottom: 12px;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .collaborator-item:hover, .invite-item:hover {
            background: var(--light);
            border-color: var(--primary);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .collaborator-info, .invite-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--dark);
        }

        .user-email {
            font-size: 13px;
            opacity: 0.8;
            color: var(--gray);
        }

        .permission-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .permission-admin { 
            background: linear-gradient(135deg, var(--success), #38a169);
            color: white;
        }
        .permission-write { 
            background: linear-gradient(135deg, var(--warning), #dd6b20);
            color: white;
        }
        .permission-read { 
            background: linear-gradient(135deg, var(--gray), #4a5568);
            color: white;
        }

        .collaborator-actions, .invite-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* Share Links */
        .share-link-item {
            background: var(--gray-light);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 16px;
            border: 1px solid transparent;
            transition: var(--transition);
        }

        .share-link-item:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .share-link-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .share-link-url {
            background: var(--light);
            border: 1px solid var(--gray-light);
            border-radius: 10px;
            padding: 14px 16px;
            font-family: 'Fira Code', monospace;
            font-size: 13px;
            margin-bottom: 12px;
            word-break: break-all;
            border: 2px dashed var(--gray-light);
        }

        .share-link-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: var(--gray);
        }

        /* Visibility Settings */
        .visibility-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .visibility-option {
            border: 2px solid var(--gray-light);
            border-radius: 14px;
            padding: 24px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: var(--light);
        }

        .visibility-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .visibility-option.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        }

        .visibility-icon {
            font-size: 36px;
            margin-bottom: 12px;
            color: var(--primary);
        }

        .visibility-option.public .visibility-icon {
            color: var(--success);
        }

        .visibility-option.private .visibility-icon {
            color: var(--secondary);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 50px 30px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 72px;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--primary);
        }

        .empty-state h3 {
            margin-bottom: 12px;
            color: var(--dark);
            font-size: 20px;
            font-weight: 600;
        }

        .empty-state p {
            font-size: 15px;
            line-height: 1.5;
        }

        /* Alert Styles */
        .alert {
            padding: 18px 22px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .alert-success {
            background: rgba(72, 187, 120, 0.1);
            border-left: 4px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(245, 101, 101, 0.1);
            border-left: 4px solid var(--danger);
            color: var(--danger);
        }

        /* Email Notification */
        .email-notification {
            background: rgba(237, 137, 54, 0.1);
            border-left: 4px solid var(--warning);
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .email-notification i {
            color: var(--warning);
            font-size: 20px;
        }

        /* Email Status Styles */
        .email-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .email-status.success {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }
        
        .email-status.error {
            background: linear-gradient(135deg, #f56565, #e53e3e);
        }
        
        .email-status.sending {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
        }
        
        .email-loading {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .email-loading .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .share-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .invite-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .share-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                padding: 25px;
            }
            
            .repo-info {
                flex-direction: column;
            }
            
            .visibility-options {
                grid-template-columns: 1fr;
            }
            
            .collaborator-item, .invite-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .collaborator-actions, .invite-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .card-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .share-header h1 {
                font-size: 24px;
            }
            
            .share-card {
                padding: 20px;
            }
            
            .btn {
                padding: 12px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="share-container">
        <!-- Header -->
        <div class="share-header">
            <div class="repo-info">
                <div class="repo-icon">
                    <i class="fas fa-share-alt"></i>
                </div>
                <div class="repo-details">
                    <h1><?php echo $repoName; ?></h1>
                    <p>Manage sharing and collaboration settings</p>
                </div>
            </div>
            <div class="header-actions">
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                <button class="back-btn" onclick="window.location.href='edit_rep.php?id=<?php echo $repoId; ?>'">
                    <i class="fas fa-arrow-left"></i>
                </button>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Email Notification -->
        <div class="email-notification">
            <i class="fas fa-envelope"></i>
            <div>
                <strong>Email Notifications Active</strong>
                <p>Invitations will be sent via EmailJS. Make sure to configure your EmailJS credentials.</p>
            </div>
        </div>

        <div class="share-grid">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Invite Collaborators -->
                <div class="share-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-user-plus"></i>
                            Invite Collaborators
                        </h2>
                    </div>
                    
                    <form method="POST" class="invite-form" id="inviteForm">
                        <input type="email" class="form-control" name="collab_email" 
                               placeholder="Enter email address" required id="collabEmail">
                        <select class="form-control select-control" name="permission_level" id="permissionLevel">
                            <option value="read">Read Only</option>
                            <option value="write">Read & Write</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button type="submit" name="invite_collaborator" class="btn btn-primary" id="inviteButton">
                            <i class="fas fa-paper-plane"></i> Send Invite
                        </button>
                    </form>

                    <!-- Current Collaborators -->
                    <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px; font-weight: 600;">Current Collaborators</h3>
                    <div class="collaborator-list">
                        <?php if (empty($collaborators)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h3>No Collaborators</h3>
                                <p>Invite someone to collaborate on this repository</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($collaborators as $collab): ?>
                                <div class="collaborator-item">
                                    <div class="collaborator-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($collab['username'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($collab['username']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($collab['email']); ?></div>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <span class="permission-badge permission-<?php echo $collab['permission_level']; ?>">
                                            <?php echo ucfirst($collab['permission_level']); ?>
                                        </span>
                                        <div class="collaborator-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="collab_id" value="<?php echo $collab['id']; ?>">
                                                <select name="new_permission" onchange="this.form.submit()" class="form-control select-control btn-sm">
                                                    <option value="read" <?php echo $collab['permission_level'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                                    <option value="write" <?php echo $collab['permission_level'] === 'write' ? 'selected' : ''; ?>>Write</option>
                                                    <option value="admin" <?php echo $collab['permission_level'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                                <input type="hidden" name="update_permission" value="1">
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="collab_id" value="<?php echo $collab['id']; ?>">
                                                <button type="submit" name="remove_collaborator" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to remove this collaborator?')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pending Invites -->
                <?php if (!empty($pendingInvites)): ?>
                <div class="share-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-clock"></i>
                            Pending Invites
                        </h2>
                    </div>
                    <div class="invite-list">
                        <?php foreach ($pendingInvites as $invite): ?>
                            <div class="invite-item">
                                <div class="invite-info">
                                    <div class="user-avatar">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($invite['target_email']); ?></div>
                                        <div class="user-email">Invited by <?php echo htmlspecialchars($invite['invited_by_username']); ?></div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <span class="permission-badge permission-<?php echo $invite['permission_level']; ?>">
                                        <?php echo ucfirst($invite['permission_level']); ?>
                                    </span>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="invite_id" value="<?php echo $invite['id']; ?>">
                                        <button type="submit" name="cancel_invite" class="btn btn-danger btn-sm">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Shareable Links -->
                <div class="share-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-link"></i>
                            Shareable Links
                        </h2>
                    </div>
                    
                    <form method="POST" style="margin-bottom: 20px;">
                        <button type="submit" name="generate_share_link" class="btn btn-success btn-block">
                            <i class="fas fa-plus"></i> Generate Share Link
                        </button>
                    </form>

                    <?php if (!empty($shareLinks)): ?>
                        <?php foreach ($shareLinks as $link): ?>
                            <div class="share-link-item">
                                <div class="share-link-header">
                                    <strong>Share Link</strong>
                                    <span class="permission-badge permission-read">
                                        <?php echo $link['use_count']; ?> uses
                                    </span>
                                </div>
                                <div class="share-link-url">
                                    <?php 
                                    $shareUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/join_repo.php?token=" . $link['share_token'];
                                    echo htmlspecialchars($shareUrl);
                                    ?>
                                </div>
                                <div class="share-link-meta">
                                    <span><i class="fas fa-clock"></i> Expires: <?php echo date('M j, Y', strtotime($link['expires_at'])); ?></span>
                                    <span><i class="fas fa-chart-bar"></i> Max uses: <?php echo $link['max_uses']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-link"></i>
                            <h3>No Share Links</h3>
                            <p>Generate a shareable link to allow others to join</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Repository Visibility -->
                <div class="share-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-eye"></i>
                            Visibility Settings
                        </h2>
                    </div>
                    
                    <form method="POST">
                        <div class="visibility-options">
                            <label class="visibility-option public <?php echo $repoData['type'] === 'public' ? 'selected' : ''; ?>">
                                <input type="radio" name="visibility" value="public" <?php echo $repoData['type'] === 'public' ? 'checked' : ''; ?> hidden>
                                <div class="visibility-icon">
                                    <i class="fas fa-globe"></i>
                                </div>
                                <h4>Public</h4>
                                <p>Anyone can view</p>
                            </label>
                            
                            <label class="visibility-option private <?php echo $repoData['type'] === 'private' ? 'selected' : ''; ?>">
                                <input type="radio" name="visibility" value="private" <?php echo $repoData['type'] === 'private' ? 'checked' : ''; ?> hidden>
                                <div class="visibility-icon">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <h4>Private</h4>
                                <p>Only collaborators</p>
                            </label>
                        </div>
                        
                        <button type="submit" name="change_visibility" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Update Visibility
                        </button>
                    </form>
                </div>

                <!-- Quick Actions -->
                <div class="share-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h2>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <button class="btn btn-warning btn-block" onclick="copyAllEmails()">
                            <i class="fas fa-copy"></i> Copy All Emails
                        </button>
                        <button class="btn btn-success btn-block" onclick="exportCollaborators()">
                            <i class="fas fa-download"></i> Export List
                        </button>
                        <button class="btn btn-primary btn-block" onclick="refreshPage()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize EmailJS with your Public Key
        (function() {
            emailjs.init("QXjRAHGt9spD3ODkZ");
            console.log("EmailJS initialized successfully");
        })();

        // Function to send email via EmailJS
        async function sendEmail(templateData, serviceID, templateID) {
            try {
                const response = await emailjs.send(serviceID, templateID, templateData);
                return { success: true, response };
            } catch (error) {
                console.error('EmailJS error:', error);
                return { success: false, error };
            }
        }

        // Show email status notification
        function showEmailStatus(message, type = 'success') {
            const status = document.createElement('div');
            status.className = `email-status ${type}`;
            status.innerHTML = message;
            document.body.appendChild(status);

            // Animate in
            setTimeout(() => {
                status.style.transform = 'translateX(0)';
            }, 100);

            // Animate out and remove
            setTimeout(() => {
                status.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (status.parentNode) {
                        document.body.removeChild(status);
                    }
                }, 300);
            }, 5000);
        }

        // Send invitation email when form is submitted
        document.getElementById('inviteForm')?.addEventListener('submit', function(e) {
            const button = document.getElementById('inviteButton');
            const email = document.getElementById('collabEmail').value;
            const permission = document.getElementById('permissionLevel').value;
            
            // Show loading state
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            button.disabled = true;

            // Prepare email data
            const templateParams = {
                to_email: email,
                inviter_name: '<?php echo $username; ?>',
                repo_name: '<?php echo $repoName; ?>',
                permission_level: permission,
                invite_url: '<?php echo "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/signup.php"; ?>',
                reply_to: '<?php echo $userEmail; ?>'
            };

            // Send email via EmailJS
            sendEmail(templateParams, "service_lyh8h99", "template_mx12hnx")
                .then(result => {
                    if (result.success) {
                        showEmailStatus('✅ Invitation email sent successfully!', 'success');
                    } else {
                        showEmailStatus('❌ Failed to send invitation email. The invitation was still saved.', 'error');
                    }
                })
                .finally(() => {
                    // Let the form submit normally after email attempt
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 1000);
                });
        });

        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = themeToggle.querySelector('i');
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            themeIcon.className = newTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            
            localStorage.setItem('theme', newTheme);
        });
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        
        // Visibility selection
        document.querySelectorAll('.visibility-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.visibility-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                this.querySelector('input').checked = true;
            });
        });
        
        // Quick action functions
        function copyAllEmails() {
            const emails = Array.from(document.querySelectorAll('.user-email'))
                .map(el => el.textContent)
                .filter(email => email && !email.includes('Invited by'))
                .join(', ');
            
            if (emails) {
                navigator.clipboard.writeText(emails).then(() => {
                    showEmailStatus('All emails copied to clipboard!', 'success');
                });
            } else {
                showEmailStatus('No emails found to copy.', 'error');
            }
        }
        
        function exportCollaborators() {
            const collaborators = Array.from(document.querySelectorAll('.collaborator-item')).map(item => {
                const name = item.querySelector('.user-name').textContent;
                const email = item.querySelector('.user-email').textContent;
                const permission = item.querySelector('.permission-badge').textContent;
                return { name, email, permission };
            });
            
            if (collaborators.length === 0) {
                showEmailStatus('No collaborators to export.', 'error');
                return;
            }
            
            const csv = collaborators.map(c => `"${c.name}","${c.email}","${c.permission}"`).join('\n');
            const csvContent = 'data:text/csv;charset=utf-8,' + 'Name,Email,Permission\n' + csv;
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'collaborators.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showEmailStatus('Collaborators exported successfully!', 'success');
        }
        
        function refreshPage() {
            window.location.reload();
        }

        // Auto-submit permission changes
        document.addEventListener('change', function(e) {
            if (e.target.name === 'new_permission') {
                e.target.form.submit();
            }
        });

        // Test EmailJS connection on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Testing EmailJS connection...");
            
            // Simple test to verify EmailJS is working
            const testParams = {
                to_email: "test@example.com",
                message: "EmailJS is working correctly!"
            };

            emailjs.send("service_lyh8h99", "template_mx12hnx", testParams)
                .then(response => {
                    console.log("✅ EmailJS is properly configured");
                })
                .catch(error => {
                    console.log("ℹ️ EmailJS test failed (this is normal if templates don't match)");
                });
        });
    </script>
</body>
</html>
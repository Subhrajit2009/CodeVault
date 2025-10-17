<?php
session_start();

// Handle AJAX search suggestions request
if (isset($_GET['ajax_suggestions']) && isset($_GET['q'])) {
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

    $searchTerm = trim($_GET['q']);
    
    if (!empty($searchTerm)) {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            
            $stmt = $pdo->prepare("
                SELECT DISTINCT r.name, u.username 
                FROM repositories r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.type = 'public' AND 
                      (r.name LIKE ? OR r.description LIKE ? OR u.username LIKE ?)
                ORDER BY 
                    CASE 
                        WHEN r.name LIKE ? THEN 1
                        WHEN u.username LIKE ? THEN 2
                        ELSE 3
                    END,
                    r.created_at DESC 
                LIMIT 8
            ");
            
            $likeTerm = '%' . $searchTerm . '%';
            $startTerm = $searchTerm . '%';
            $stmt->execute([$likeTerm, $likeTerm, $likeTerm, $startTerm, $startTerm]);
            $results = $stmt->fetchAll();
            
            header('Content-Type: text/html; charset=utf-8');
            
            if (!empty($results)) {
                foreach ($results as $result) {
                    echo '<div class="suggestion-item" data-repo-name="' . htmlspecialchars($result['name']) . '">';
                    echo '<i class="fas fa-repository"></i>';
                    echo '<div class="suggestion-text">';
                    echo '<strong>' . htmlspecialchars($result['name']) . '</strong>';
                    echo '<small>by ' . htmlspecialchars($result['username']) . '</small>';
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<div class="suggestion-item no-results">';
                echo '<i class="fas fa-search"></i>';
                echo '<span>No repositories found</span>';
                echo '</div>';
            }
            
        } catch (PDOException $e) {
            echo '<div class="suggestion-item error">';
            echo '<i class="fas fa-exclamation-triangle"></i>';
            echo '<span>Search error</span>';
            echo '</div>';
        }
    }
    exit;
}

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

// Function to format file size
function formatSize($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Function to generate unique repository ID
function generateRepoId($type, $pdo) {
    $prefix = $type === 'public' ? 'PUB_' : 'PRIV_';
    
    do {
        $random = bin2hex(random_bytes(4));
        $repoId = $prefix . strtoupper($random);
        
        // Check if ID already exists
        $stmt = $pdo->prepare("SELECT id FROM repositories WHERE repo_id = ?");
        $stmt->execute([$repoId]);
    } while ($stmt->fetch());
    
    return $repoId;
}

// Function to create database with fallback
function createRepositoryDatabase($dbName, $pdo) {
    try {
        // Try to create the database
        $createDBSQL = "CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $pdo->exec($createDBSQL);
        return true;
    } catch (PDOException $e) {
        error_log("Database creation failed: " . $e->getMessage());
        
        // If permission denied, fall back to table creation in main database
        if (strpos($e->getMessage(), 'denied') !== false) {
            throw new Exception("Database creation permission denied. Please contact administrator to grant CREATE privileges to your MySQL user.");
        }
        throw $e;
    }
}

// Function to check user permissions for a repository
function getUserRepoPermissions($userId, $repoId, $pdo) {
    try {
        // Check if user is owner
        $stmt = $pdo->prepare("SELECT user_id FROM repositories WHERE repo_id = ? AND user_id = ?");
        $stmt->execute([$repoId, $userId]);
        if ($stmt->fetch()) {
            return 'admin'; // Owner has admin permissions
        }
        
        // Check if user is collaborator
        $stmt = $pdo->prepare("SELECT permission_level FROM collaborators WHERE repo_id = ? AND user_id = ?");
        $stmt->execute([$repoId, $userId]);
        $collab = $stmt->fetch();
        if ($collab) {
            return $collab['permission_level']; // Return collaborator permission level
        }
        
        return 'read'; // Default to read-only for public repositories
    } catch (PDOException $e) {
        error_log("Error checking permissions: " . $e->getMessage());
        return 'read'; // Default to read-only on error
    }
}

// Function to search public repositories across all users
// Function to search public repositories across all users
function searchPublicRepositories($searchTerm, $currentUserId, $pdo) {
    $searchResults = [];
    
    try {
        // Search in repositories table for matching PUBLIC repository names or descriptions
        $stmt = $pdo->prepare("
            SELECT r.id, r.name, r.type, r.repo_id, r.db_name, r.description, r.created_at,
                   u.username, u.email, u.id as user_id,
                   (r.user_id = ?) as is_owner,
                   CASE 
                       WHEN r.user_id = ? THEN 'admin'
                       WHEN EXISTS (
                           SELECT 1 FROM collaborators c 
                           WHERE c.repo_id = r.repo_id AND c.user_id = ? AND c.permission_level = 'admin'
                       ) THEN 'admin'
                       WHEN EXISTS (
                           SELECT 1 FROM collaborators c 
                           WHERE c.repo_id = r.repo_id AND c.user_id = ? AND c.permission_level = 'write'
                       ) THEN 'write'
                       WHEN EXISTS (
                           SELECT 1 FROM collaborators c 
                           WHERE c.repo_id = r.repo_id AND c.user_id = ?
                       ) THEN 'read'
                       ELSE 'read'
                   END as user_permission,
                   EXISTS(
                       SELECT 1 FROM collaborators c 
                       WHERE c.repo_id = r.repo_id AND c.user_id = ?
                   ) as is_collaborator
            FROM repositories r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.type = 'public' AND (r.name LIKE ? OR r.description LIKE ? OR u.username LIKE ?)
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([
            $currentUserId, // For is_owner check
            $currentUserId, // For user_permission admin check
            $currentUserId, // For collaborator admin check
            $currentUserId, // For collaborator write check
            $currentUserId, // For collaborator read check
            $currentUserId, // For is_collaborator check
            '%' . $searchTerm . '%', 
            '%' . $searchTerm . '%', 
            '%' . $searchTerm . '%'
        ]);
        $searchResults = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error searching public repositories: " . $e->getMessage());
    }
    
    return $searchResults;
}

// Initialize variables with default values
$userData = [];
$totalRepositories = 0;
$publicCount = 0;
$privateCount = 0;
$recentRepos = [];
$username = 'User';
$searchResults = [];
$searchTerm = '';

// Handle search
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    if (!empty($searchTerm)) {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            $searchResults = searchPublicRepositories($searchTerm, $_SESSION['user_id'], $pdo);
        } catch (PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            $_SESSION['error'] = "Search failed: " . $e->getMessage();
        }
    }
}

// Handle repository creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_repository'])) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Get user data
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
        
        if (!$userData) {
            throw new Exception("User not found");
        }
        
        $repoName = preg_replace('/[^a-zA-Z0-9_]/', '_', $_POST['repo_name']);
        $repoType = $_POST['repo_type'];
        $description = $_POST['description'];
        $username = $userData['username'];
        
        // Generate unique repository ID
        $repoId = generateRepoId($repoType, $pdo);
        
        // Create database name
        $dbName = $username . '_' . $repoName;
        
        // Create new database for the repository
        createRepositoryDatabase($dbName, $pdo);
        
        // Add to repositories table for tracking
        $stmt = $pdo->prepare("INSERT INTO repositories (user_id, name, type, repo_id, db_name, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $repoName, $repoType, $repoId, $dbName, $description]);
        
        $_SESSION['success'] = "Repository '$repoName' created successfully with database '$dbName'!";
        header('Location: dashboard.php');
        exit;
        
    } catch (PDOException $e) {
        error_log("Repository creation error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to create repository: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("User error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get user data
    $stmt = $pdo->prepare("SELECT username, email, auth_provider, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    
    if (!$userData || !isset($userData['username'])) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    $username = $userData['username'];
    
    // Check if repositories table exists, if not create it
    $checkTableSQL = "CREATE TABLE IF NOT EXISTS repositories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        type ENUM('public', 'private') NOT NULL,
        repo_id VARCHAR(50) NOT NULL UNIQUE,
        db_name VARCHAR(500) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($checkTableSQL);
    
    // Create collaborators table
    $checkCollabTableSQL = "CREATE TABLE IF NOT EXISTS collaborators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        repo_id VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        permission_level ENUM('read', 'write', 'admin') DEFAULT 'read',
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_collaborator (repo_id, user_id)
    )";
    $pdo->exec($checkCollabTableSQL);
    
    // Get repository statistics
    $stmt = $pdo->prepare("SELECT type, COUNT(*) as count FROM repositories WHERE user_id = ? GROUP BY type");
    $stmt->execute([$_SESSION['user_id']]);
    $repoStats = $stmt->fetchAll();
    
    $publicCount = 0;
    $privateCount = 0;
    foreach ($repoStats as $stat) {
        if ($stat['type'] === 'public') $publicCount = $stat['count'];
        if ($stat['type'] === 'private') $privateCount = $stat['count'];
    }
    $totalRepositories = $publicCount + $privateCount;
    
    // Get recent repositories
    $stmt = $pdo->prepare("SELECT name, type, repo_id, description, created_at FROM repositories WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $recentRepos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    // More specific error message
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        $_SESSION['error'] = "Database access denied. Please check your MySQL credentials.";
    } else if (strpos($e->getMessage(), 'Unknown database') !== false) {
        $_SESSION['error'] = "Database 'codevault' does not exist. Please create it first.";
    } else {
        $_SESSION['error'] = "Database connection error: " . $e->getMessage();
    }
}

// Safe username display
$displayUsername = htmlspecialchars($username);
$userInitial = strtoupper(substr($username, 0, 1));
$displaySearchTerm = htmlspecialchars($searchTerm);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | CodeVault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

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

        /* Search Styles */
        .search-container {
            position: relative;
            margin-bottom: 25px;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            max-width: 600px;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            background: var(--light);
            color: var(--dark);
            font-size: 14px;
            transition: var(--transition);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--light);
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            z-index: 1000;
            display: none;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .suggestion-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }
        
        .suggestion-item:last-child {
            border-bottom: none;
        }
        
        .suggestion-item:hover,
        .suggestion-item.active {
            background: var(--primary);
            color: white;
        }
        
        .suggestion-item i {
            width: 16px;
            text-align: center;
        }
        
        .suggestion-text {
            display: flex;
            flex-direction: column;
        }
        
        .suggestion-text strong {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .suggestion-text small {
            font-size: 11px;
            opacity: 0.8;
        }
        
        .suggestion-item.loading,
        .suggestion-item.no-results,
        .suggestion-item.error {
            cursor: default;
            color: var(--gray);
        }
        
        .suggestion-item.loading:hover,
        .suggestion-item.no-results:hover,
        .suggestion-item.error:hover {
            background: var(--light);
            color: var(--gray);
        }

        /* Search Results */
        .search-results {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }
        
        .result-count {
            color: var(--gray);
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .result-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--gray-light);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .result-item:hover {
            background: var(--gray-light);
            transform: translateX(5px);
        }
        
        .result-item:last-child {
            border-bottom: none;
        }
        
        .file-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            color: white;
            font-size: 20px;
        }
        
        .file-icon.public {
            background: var(--success);
        }
        
        .result-details {
            flex: 1;
        }
        
        .result-title {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .result-desc {
            color: var(--gray);
            margin-bottom: 10px;
            line-height: 1.4;
        }
        
        .result-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: var(--gray);
        }
        
        .result-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .user-info {
            background: rgba(67, 97, 238, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
            color: var(--primary);
            font-weight: 500;
        }
        
        .result-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            color: var(--gray);
            font-size: 18px;
            transition: var(--transition);
        }
        
        .result-item:hover .result-action {
            color: var(--primary);
            transform: translateX(5px);
        }
        
        .repo-id {
            font-size: 12px;
            background: var(--gray-light);
            padding: 4px 8px;
            border-radius: 6px;
            font-family: monospace;
            color: var(--gray);
        }
        
        .permission-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .permission-admin {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .permission-write {
            background: rgba(247, 183, 49, 0.1);
            color: #f7b731;
            border: 1px solid #f7b731;
        }
        
        .permission-read {
            background: rgba(108, 117, 125, 0.1);
            color: var(--gray);
            border: 1px solid var(--gray);
        }
        
        .action-text {
            font-size: 12px;
            font-weight: 500;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 40px;
            color: var(--gray);
        }
        
        .no-results i {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--gray-light);
        }
        
        .no-results h3 {
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .no-results p {
            margin-bottom: 25px;
            font-size: 16px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }

        .stat-card.total::before {
            background: linear-gradient(to bottom, var(--primary), var(--secondary));
        }

        .stat-card.public::before {
            background: var(--success);
        }

        .stat-card.private::before {
            background: var(--secondary);
        }

        .stat-card.create::before {
            background: linear-gradient(to bottom, var(--warning), var(--danger));
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
        }

        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-card.public .stat-icon {
            background: var(--success);
        }

        .stat-card.private .stat-icon {
            background: var(--secondary);
        }

        .stat-card.create .stat-icon {
            background: linear-gradient(135deg, var(--warning), var(--danger));
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--light);
            margin: 5% auto;
            padding: 0;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .close-modal:hover {
            color: var(--danger);
        }

        .modal-body {
            padding: 25px;
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

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        /* Recent Activity */
        .recent-activity {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
        }

        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
        }

        .activity-icon.public {
            background: var(--success);
        }

        .activity-icon.private {
            background: var(--secondary);
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            margin-bottom: 3px;
        }

        .activity-desc {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 12px;
            color: var(--gray);
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

        /* Responsive Design */
        @media (max-width: 992px) {
            .dashboard-container {
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
            
            .stats-grid {
                grid-template-columns: 1fr;
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
            
            .search-box {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <i >C</i>
                <h1>CodeVault</h1>
            </div>
            <ul class="nav-links">
                <li><a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="#" id="createRepoBtn"><i class="fas fa-plus"></i> Create Repository</a></li>
                <li><a href="my_rep.php"><i class="fas fa-code-branch"></i> My Repositories</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../index.php#contact"><i class="fas fa-question-circle"></i> Help & Support</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2>Welcome back, <?php echo $displayUsername; ?>!</h2>
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

            <!-- Search Bar -->
            <div class="search-container">
                <form method="GET" class="search-box" id="searchForm">
                    <input type="text" name="search" class="search-input" id="searchInput"
                           placeholder="Search public repositories across all users..." 
                           value="<?php echo $displaySearchTerm; ?>"
                           autocomplete="off">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
                
                <!-- Real-time search suggestions -->
                <div class="search-suggestions" id="searchSuggestions"></div>
            </div>

            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

           <!-- Search Results -->
<?php if (!empty($searchTerm)): ?>
    <div class="search-results">
        <h3 class="section-title">Public Repositories</h3>
        <div class="result-count">
            Found <?php echo count($searchResults); ?> public repository(s) matching "<?php echo $displaySearchTerm; ?>"
        </div>
        
        <?php if (!empty($searchResults)): ?>
            <?php foreach ($searchResults as $repo): ?>
                <?php
                // Check if current user is the owner of this repository
                $isOwner = ($repo['user_id'] == $_SESSION['user_id']);
                $isCollaborator = (bool)$repo['is_collaborator'];
                $userPermission = $repo['user_permission'];
                
                // Determine action URL and text based on permissions
                if ($isOwner || $userPermission === 'admin' || $userPermission === 'write') {
                    $actionUrl = 'edit_rep.php?id=' . $repo['repo_id'];
                    $actionIcon = 'fa-edit';
                    $actionText = 'Edit Repository';
                } else {
                    $actionUrl = 'view_repo.php?id=' . $repo['repo_id'];
                    $actionIcon = 'fa-eye';
                    $actionText = 'View Repository';
                }
                
                $permissionClass = 'permission-' . $userPermission;
                $permissionText = ucfirst($userPermission);
                ?>
                <div class="result-item" onclick="window.location.href='<?php echo $actionUrl; ?>'">
                    <div class="file-icon public">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="result-details">
                        <div class="result-title">
                            <?php echo htmlspecialchars($repo['name']); ?>
                            <span class="repo-id"><?php echo htmlspecialchars($repo['repo_id']); ?></span>
                            <span class="permission-badge <?php echo $permissionClass; ?>">
                                <?php echo $permissionText; ?>
                            </span>
                            <?php if ($isOwner): ?>
                                <span class="permission-badge permission-admin">Owner</span>
                            <?php endif; ?>
                        </div>
                        <div class="result-desc"><?php echo htmlspecialchars($repo['description'] ?: 'No description'); ?></div>
                        <div class="result-meta">
                            <span class="user-info">
                                <i class="fas fa-user"></i> 
                                <?php echo htmlspecialchars($repo['username']); ?>
                            </span>
                            <span><i class="fas fa-database"></i> <?php echo htmlspecialchars($repo['db_name']); ?></span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($repo['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="result-action">
                        <i class="fas <?php echo $actionIcon; ?>"></i>
                        <span class="action-text"><?php echo $actionText; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No public repositories found</h3>
                <p>No public repositories matching "<?php echo $displaySearchTerm; ?>" were found.</p>
                <p class="suggestion">Try different keywords or check the spelling</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $totalRepositories; ?></div>
                            <div class="stat-label">Total Repositories</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-code-branch"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card public">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $publicCount; ?></div>
                            <div class="stat-label">Public Repositories</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card private">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $privateCount; ?></div>
                            <div class="stat-label">Private Repositories</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card create">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">+</div>
                            <div class="stat-label">Create New Repository</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                    </div>
                    <a href="#" id="createRepoCardBtn" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="section-header">
                    <h3 class="section-title">Recent Repositories</h3>
                    <a href="my_rep.php" class="view-all">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <ul class="activity-list">
                    <?php if (empty($recentRepos)): ?>
                        <li class="activity-item">
                            <div class="activity-details">
                                <div class="activity-title">No repositories yet</div>
                                <div class="activity-desc">Create your first repository to get started</div>
                            </div>
                        </li>
                    <?php else: ?>
                        <?php foreach ($recentRepos as $repo): ?>
                            <li class="activity-item">
                                <div class="activity-icon <?php echo $repo['type']; ?>">
                                    <i class="fas fa-<?php echo $repo['type'] === 'public' ? 'globe' : 'lock'; ?>"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title">
                                        <?php echo htmlspecialchars($repo['name']); ?>
                                        <span class="repo-id"><?php echo htmlspecialchars($repo['repo_id']); ?></span>
                                    </div>
                                    <div class="activity-desc"><?php echo htmlspecialchars($repo['description'] ?: 'No description'); ?></div>
                                    <div class="activity-time">
                                        Created <?php echo date('M j, Y', strtotime($repo['created_at'])); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Create Repository Modal -->
    <div class="modal" id="createRepoModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Repository</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="createRepoForm">
                    <div class="form-group">
                        <label for="repo_name">Repository Name</label>
                        <input type="text" class="form-control" id="repo_name" name="repo_name" required 
                               placeholder="my-awesome-project" pattern="[a-zA-Z0-9_-]+" 
                               title="Only letters, numbers, hyphens, and underscores allowed">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" 
                                  placeholder="A brief description of your repository" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Repository Type</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="repo_type" value="public" checked>
                                <i class="fas fa-globe"></i> Public
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="repo_type" value="private">
                                <i class="fas fa-lock"></i> Private
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" name="create_repository" class="btn btn-primary btn-block">
                        <i class="fas fa-plus"></i> Create Repository
                    </button>
                </form>
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
            
            // Save theme preference
            localStorage.setItem('theme', newTheme);
        });
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        
        // Modal functionality
        const modal = document.getElementById('createRepoModal');
        const createRepoBtn = document.getElementById('createRepoBtn');
        const createRepoCardBtn = document.getElementById('createRepoCardBtn');
        const closeModal = document.querySelector('.close-modal');
        
        function openModal() {
            modal.style.display = 'block';
            setTimeout(() => modal.classList.add('show'), 10);
        }
        
        function closeModalFunc() {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }
        
        createRepoBtn.addEventListener('click', openModal);
        createRepoCardBtn.addEventListener('click', openModal);
        closeModal.addEventListener('click', closeModalFunc);
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModalFunc();
            }
        });
        
        // Form validation
        document.getElementById('createRepoForm').addEventListener('submit', (e) => {
            const repoName = document.getElementById('repo_name').value;
            if (!/^[a-zA-Z0-9_-]+$/.test(repoName)) {
                e.preventDefault();
                alert('Repository name can only contain letters, numbers, hyphens, and underscores.');
            }
        });
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Real-time search functionality
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        const searchForm = document.getElementById('searchForm');

        let searchTimeout;

        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.trim();
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            // Hide suggestions if search term is empty
            if (searchTerm.length === 0) {
                searchSuggestions.innerHTML = '';
                searchSuggestions.style.display = 'none';
                return;
            }
            
            // Show loading state
            searchSuggestions.innerHTML = '<div class="suggestion-item loading"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
            searchSuggestions.style.display = 'block';
            
            // Debounce search requests
            searchTimeout = setTimeout(() => {
                fetchSearchSuggestions(searchTerm);
            }, 300);
        });

        // Fetch search suggestions via AJAX
        function fetchSearchSuggestions(searchTerm) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `?ajax_suggestions=1&q=${encodeURIComponent(searchTerm)}`, true);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    searchSuggestions.innerHTML = xhr.responseText;
                    if (xhr.responseText.trim() !== '') {
                        searchSuggestions.style.display = 'block';
                    } else {
                        searchSuggestions.style.display = 'none';
                    }
                }
            };
            
            xhr.onerror = function() {
                searchSuggestions.style.display = 'none';
            };
            
            xhr.send();
        }

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.style.display = 'none';
            }
        });

        // Handle suggestion clicks
        searchSuggestions.addEventListener('click', function(e) {
            const suggestionItem = e.target.closest('.suggestion-item');
            if (suggestionItem && !suggestionItem.classList.contains('loading')) {
                const repoName = suggestionItem.getAttribute('data-repo-name');
                if (repoName) {
                    searchInput.value = repoName;
                    searchForm.submit();
                }
            }
        });

        // Keyboard navigation for suggestions
        searchInput.addEventListener('keydown', function(e) {
            const suggestions = searchSuggestions.querySelectorAll('.suggestion-item:not(.loading)');
            const activeSuggestion = searchSuggestions.querySelector('.suggestion-item.active');
            
            if (e.key === 'ArrowDown' && suggestions.length > 0) {
                e.preventDefault();
                if (!activeSuggestion) {
                    suggestions[0].classList.add('active');
                } else {
                    const next = activeSuggestion.nextElementSibling;
                    if (next) {
                        activeSuggestion.classList.remove('active');
                        next.classList.add('active');
                    }
                }
            } else if (e.key === 'ArrowUp' && suggestions.length > 0) {
                e.preventDefault();
                if (activeSuggestion) {
                    const prev = activeSuggestion.previousElementSibling;
                    if (prev) {
                        activeSuggestion.classList.remove('active');
                        prev.classList.add('active');
                    }
                }
            } else if (e.key === 'Enter' && activeSuggestion) {
                e.preventDefault();
                const repoName = activeSuggestion.getAttribute('data-repo-name');
                if (repoName) {
                    searchInput.value = repoName;
                    searchForm.submit();
                }
            } else if (e.key === 'Escape') {
                searchSuggestions.style.display = 'none';
            }
        });

        // Focus on search input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            searchInput.focus();
        });
    </script>
</body>
</html>
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
$files = [];
$username = 'User';
$error = '';
$currentFileContent = '';
$currentFileName = '';
$currentFileLanguage = '';
$permission = 'read';
$isOwner = false;

// Get repository ID from URL
$repoId = $_GET['id'] ?? '';

if (!$repoId) {
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get repository data
    $stmt = $pdo->prepare("
        SELECT r.*, u.username 
        FROM repositories r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.repo_id = ?
    ");
    $stmt->execute([$repoId]);
    $repoData = $stmt->fetch();
    
    if (!$repoData) {
        throw new Exception("Repository not found");
    }
    
    // Check if user is owner
    $isOwner = ($repoData['user_id'] == $_SESSION['user_id']);
    
    // If user is owner, redirect to edit mode
    if ($isOwner) {
        header('Location: edit_rep.php?id=' . $repoId);
        exit;
    }
    
    // Check if user is collaborator
    $collabStmt = $pdo->prepare("SELECT permission_level FROM collaborators WHERE repo_id = ? AND user_id = ?");
    $collabStmt->execute([$repoId, $_SESSION['user_id']]);
    $collab = $collabStmt->fetch();
    
    // If user has write/admin permissions, redirect to edit mode
    if ($collab && in_array($collab['permission_level'], ['write', 'admin'])) {
        header('Location: edit_rep.php?id=' . $repoId);
        exit;
    }
    
    // Only allow access if repository is public or user has read permissions
    if ($repoData['type'] !== 'public' && (!$collab || $collab['permission_level'] !== 'read')) {
        throw new Exception("You don't have permission to access this repository");
    }
    
    $username = $_SESSION['username'] ?? 'User';
    $dbName = $repoData['db_name'];
    
    // Connect to repository database
    $repoDsn = "mysql:host=$host;dbname=$dbName;charset=$charset";
    $repoPdo = new PDO($repoDsn, $user, $pass, $options);
    
    // Get current version
    $versionStmt = $repoPdo->query("SHOW TABLES LIKE '" . $repoData['name'] . "_%'");
    $tables = $versionStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $currentVersion = 1;
    if (!empty($tables)) {
        // Extract version numbers and find the latest
        $versions = [];
        foreach ($tables as $table) {
            if (preg_match('/^' . preg_quote($repoData['name']) . '_(\d+)$/', $table, $matches)) {
                $versions[] = (int)$matches[1];
            }
        }
        $currentVersion = !empty($versions) ? max($versions) : 1;
    }
    
    $currentTable = $repoData['name'] . '_' . $currentVersion;
    
    // Check if table exists, if not create it
    $tableCheck = $repoPdo->query("SHOW TABLES LIKE '$currentTable'")->fetch();
    if (!$tableCheck) {
        // Create table structure
        $createTableSQL = "CREATE TABLE IF NOT EXISTS `$currentTable` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) DEFAULT '/',
            code LONGTEXT,
            language VARCHAR(50),
            version INT DEFAULT $currentVersion,
            last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            file_size INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            UNIQUE KEY unique_file_path (file_path, file_name)
        )";
        $repoPdo->exec($createTableSQL);
    }
    
    // Get files from current version
    $filesStmt = $repoPdo->prepare("SELECT * FROM `$currentTable` WHERE is_active = TRUE ORDER BY file_path, file_name");
    $filesStmt->execute();
    $files = $filesStmt->fetchAll();

    // Handle file content loading
    if (isset($_GET['file_id'])) {
        $fileId = $_GET['file_id'];
        $fileStmt = $repoPdo->prepare("SELECT * FROM `$currentTable` WHERE id = ? AND is_active = TRUE");
        $fileStmt->execute([$fileId]);
        $fileData = $fileStmt->fetch();
        
        if ($fileData) {
            $currentFileContent = $fileData['code'];
            $currentFileName = $fileData['file_name'];
            $currentFileLanguage = $fileData['language'];
        }
    }
    
} catch (PDOException $e) {
    error_log("View Repository error: " . $e->getMessage());
    $error = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    error_log("Repository error: " . $e->getMessage());
    $error = $e->getMessage();
}

// Safe display variables
$displayUsername = htmlspecialchars($username);
$userInitial = strtoupper(substr($username, 0, 1));
$repoName = htmlspecialchars($repoData['name'] ?? '');
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?php echo $repoName; ?> | CodeVault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/monokai.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
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

    .editor-container {
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

    .repo-info {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-top: 10px;
        font-size: 14px;
    }

    .repo-type {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .repo-type.public {
        background: rgba(76, 201, 240, 0.1);
        color: var(--success);
        border: 1px solid var(--success);
    }

    .version-badge {
        background: rgba(108, 117, 125, 0.1);
        color: var(--gray);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .permission-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .permission-read {
        background: rgba(108, 117, 125, 0.1);
        color: var(--gray);
        border: 1px solid var(--gray);
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

    .alert-info {
        background: rgba(76, 201, 240, 0.1);
        border: 1px solid var(--success);
        color: var(--success);
    }

    .alert-error {
        background: rgba(230, 57, 70, 0.1);
        border: 1px solid var(--danger);
        color: var(--danger);
    }

    /* Editor Layout */
    .editor-layout {
        display: flex;
        gap: 25px;
        height: calc(100vh - 200px);
    }

    /* File Explorer */
    .file-explorer {
        width: 300px;
        background: var(--light);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        display: flex;
        flex-direction: column;
    }

    .explorer-header {
        padding: 20px;
        border-bottom: 1px solid var(--gray-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .explorer-title {
        font-weight: 600;
        font-size: 18px;
    }

    .file-list {
        list-style: none;
        overflow-y: auto;
        flex: 1;
        padding: 10px 0;
    }

    .file-item {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        cursor: pointer;
        transition: var(--transition);
        border-left: 3px solid transparent;
    }

    .file-item:hover {
        background: var(--gray-light);
    }

    .file-item.active {
        background: var(--primary);
        color: white;
        border-left-color: var(--primary);
    }

    .file-icon {
        margin-right: 12px;
        width: 20px;
        text-align: center;
    }

    .file-name {
        flex: 1;
        font-weight: 500;
    }

    .file-meta {
        font-size: 11px;
        background: var(--gray-light);
        padding: 2px 6px;
        border-radius: 4px;
        color: var(--gray);
    }

    .file-item.active .file-meta {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    /* Editor Panel */
    .editor-panel {
        flex: 1;
        background: var(--light);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        display: flex;
        flex-direction: column;
    }

    .editor-tabs {
        display: flex;
        border-bottom: 1px solid var(--gray-light);
        background: var(--light);
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .editor-tab {
        padding: 15px 25px;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        color: var(--gray);
        cursor: pointer;
        transition: var(--transition);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .editor-tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
        background: rgba(67, 97, 238, 0.05);
    }

    .editor-content {
        flex: 1;
        padding: 0;
        overflow: hidden;
    }

    .tab-content {
        display: none;
        height: 100%;
        flex-direction: column;
    }

    .tab-content.active {
        display: flex;
    }

    /* Code Editor */
    .code-editor {
        flex: 1;
        border: none;
        outline: none;
        padding: 20px;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-size: 14px;
        line-height: 1.5;
        background: var(--light);
        color: var(--dark);
        resize: none;
    }

    .CodeMirror {
        height: 100%;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-size: 14px;
        line-height: 1.5;
    }

    .action-buttons {
        padding: 20px;
        border-top: 1px solid var(--gray-light);
        display: flex;
        gap: 15px;
        justify-content: flex-end;
    }

    .btn {
        padding: 10px 20px;
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

    .btn-success {
        background: var(--success);
        color: white;
    }

    .btn-success:hover {
        background: #3ab0d9;
    }

    .btn:disabled {
        background: var(--gray-light);
        color: var(--gray);
        cursor: not-allowed;
    }

    /* READ-ONLY SPECIFIC STYLES */
    .read-only-indicator {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(108, 117, 125, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        z-index: 1001;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .editor-container-readonly {
        position: relative;
        height: 100%;
    }
    
    /* Enable text selection and scrolling */
    .CodeMirror {
        opacity: 1;
    }
    
    .CodeMirror-cursors {
        display: none !important;
    }
    
    .CodeMirror-cursor {
        display: none !important;
    }
    
    /* Ensure scrollbars are visible and functional */
    .CodeMirror-vscrollbar, 
    .CodeMirror-hscrollbar,
    .CodeMirror-scrollbar-filler,
    .CodeMirror-gutter-filler {
        pointer-events: auto !important;
    }
    
    .CodeMirror-scroll {
        pointer-events: auto !important;
        cursor: default !important;
    }
    
    /* Enable text selection for copying */
    .CodeMirror-line > span {
        -webkit-user-select: text;
        -moz-user-select: text;
        -ms-user-select: text;
        user-select: text;
        cursor: text !important;
    }
    
    .CodeMirror-line {
        cursor: text !important;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
        .editor-container {
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
        
        .editor-layout {
            flex-direction: column;
            height: auto;
        }
        
        .file-explorer {
            width: 100%;
            height: 300px;
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
    }
</style>
</head>
<body>
    <div class="editor-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-code"></i>
                <h1>CodeVault</h1>
            </div>
            <ul class="nav-links">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="my_rep.php"><i class="fas fa-code-branch"></i> My Repositories</a></li>
                <li><a href="view_repo.php?id=<?php echo $repoId; ?>" class="active"><i class="fas fa-eye"></i> View Repository</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h2><?php echo $repoName; ?></h2>
                    <div class="repo-info">
                        <span class="repo-type <?php echo $repoData['type']; ?>">
                            <i class="fas fa-<?php echo $repoData['type'] === 'public' ? 'globe' : 'lock'; ?>"></i>
                            <?php echo $repoData['type']; ?>
                        </span>
                        <span class="version-badge">Version <?php echo $currentVersion; ?></span>
                        <span class="permission-badge permission-read">
                            <i class="fas fa-eye"></i> Read Only
                        </span>
                        <span>By: <?php echo htmlspecialchars($repoData['username']); ?></span>
                    </div>
                </div>
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

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                You are viewing this repository in read-only mode. You can view files and copy code but cannot make changes.
                <?php if ($isOwner): ?>
                    <br><strong>Note:</strong> You are the owner of this repository. <a href="edit_rep.php?id=<?php echo $repoId; ?>" style="color: var(--primary); text-decoration: underline;">Click here to edit</a>.
                <?php endif; ?>
            </div>

            <div class="editor-layout">
                <!-- File Explorer -->
                <div class="file-explorer">
                    <div class="explorer-header">
                        <div class="explorer-title">Files</div>
                    </div>
                    
                    <ul class="file-list">
                        <?php if (empty($files)): ?>
                            <li class="file-item">
                                <i class="fas fa-folder-open file-icon"></i>
                                <span class="file-name">No files yet</span>
                            </li>
                        <?php else: ?>
                            <?php foreach ($files as $file): ?>
                                <li class="file-item <?php echo $file['id'] == ($_GET['file_id'] ?? '') ? 'active' : ''; ?>" 
                                    onclick="loadFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name']); ?>', '<?php echo $file['language']; ?>')">
                                    <i class="fas fa-file-code file-icon"></i>
                                    <span class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></span>
                                    <span class="file-meta"><?php echo strtoupper($file['language']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Editor Panel -->
                <div class="editor-panel">
                    <div class="editor-tabs">
                        <button class="editor-tab <?php echo isset($_GET['file_id']) ? 'active' : ''; ?>" onclick="showTab('editor-tab')">
                            <i class="fas fa-code"></i> Code Viewer
                        </button>
                        <button class="editor-tab" onclick="showTab('info-tab')">
                            <i class="fas fa-info-circle"></i> Repository Info
                        </button>
                    </div>

                    <div class="editor-content">
                       <!-- Code Viewer Tab -->
<div id="editor-tab" class="tab-content <?php echo isset($_GET['file_id']) ? 'active' : ''; ?>">
    <?php if (isset($_GET['file_id'])): ?>
        <div class="editor-container-readonly" style="position: relative; height: 100%;">
            <div class="read-only-indicator">
                <i class="fas fa-lock"></i> Read Only - Copy & Scroll Allowed
            </div>
            <textarea id="code-editor" class="code-editor" readonly><?php echo htmlspecialchars($currentFileContent); ?></textarea>
        </div>
    <?php else: ?>
        <div style="padding: 40px; text-align: center; color: var(--gray);">
            <i class="fas fa-file-code" style="font-size: 64px; margin-bottom: 20px;"></i>
            <h3>No File Selected</h3>
            <p>Select a file from the explorer to view its content.</p>
        </div>
    <?php endif; ?>
</div>

                        <!-- Repository Info Tab -->
                        <div id="info-tab" class="tab-content">
                            <div style="padding: 30px;">
                                <h3 style="margin-bottom: 20px;">Repository Information</h3>
                                
                                <div style="display: grid; gap: 20px;">
                                    <div>
                                        <strong>Repository Name:</strong>
                                        <p><?php echo htmlspecialchars($repoData['name']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <strong>Description:</strong>
                                        <p><?php echo htmlspecialchars($repoData['description'] ?: 'No description provided'); ?></p>
                                    </div>
                                    
                                    <div>
                                        <strong>Owner:</strong>
                                        <p><?php echo htmlspecialchars($repoData['username']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <strong>Repository ID:</strong>
                                        <p style="font-family: monospace;"><?php echo htmlspecialchars($repoData['repo_id']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <strong>Database Name:</strong>
                                        <p style="font-family: monospace;"><?php echo htmlspecialchars($repoData['db_name']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <strong>Type:</strong>
                                        <p>
                                            <span class="repo-type <?php echo $repoData['type']; ?>" style="display: inline-flex; align-items: center; gap: 5px;">
                                                <i class="fas fa-<?php echo $repoData['type'] === 'public' ? 'globe' : 'lock'; ?>"></i>
                                                <?php echo ucfirst($repoData['type']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    
                                    <div>
                                        <strong>Current Version:</strong>
                                        <p><?php echo $currentVersion; ?></p>
                                    </div>
                                    
                                    <div>
                                        <strong>Created:</strong>
                                        <p><?php echo date('F j, Y \a\t g:i A', strtotime($repoData['created_at'])); ?></p>
                                    </div>
                                    
                                    <div>
                                        <strong>Total Files:</strong>
                                        <p><?php echo count($files); ?> file(s)</p>
                                    </div>
                                    
                                    <div>
                                        <strong>Your Access Level:</strong>
                                        <p>
                                            <span class="permission-badge permission-read" style="display: inline-flex; align-items: center; gap: 5px;">
                                                <i class="fas fa-eye"></i> Read Only
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
            
            localStorage.setItem('theme', newTheme);
            
            // Refresh CodeMirror theme
            if (editor) {
                editor.setOption('theme', newTheme === 'dark' ? 'monokai' : 'default');
            }
        });
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        
        // Tab management
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.editor-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
        
        // CodeMirror Editor (Read-only but allows copying and scrolling)
        let editor;
        <?php if (isset($_GET['file_id'])): ?>
            editor = CodeMirror.fromTextArea(document.getElementById('code-editor'), {
                lineNumbers: true,
                mode: '<?php echo getCodeMirrorMode($currentFileLanguage); ?>',
                theme: savedTheme === 'dark' ? 'monokai' : 'default',
                readOnly: true,
                indentUnit: 4,
                smartIndent: true,
                electricChars: true,
                matchBrackets: true,
                styleActiveLine: false,
                lineWrapping: true,
                cursorBlinkRate: -1, // Disable cursor blink
                viewportMargin: Infinity,
                // Enable selection and copying
                selectOnActive: true,
                allowDropFileTypes: false // Disable file drops
            });
            
            // Set read-only mode
            editor.setOption('readOnly', true);
            
            // Hide cursor but allow text selection
            editor.setCursor(0, 0, {scroll: false});
            editor.display.input.blur();
            
            // Enable text selection and scrolling
            const editorElement = editor.getWrapperElement();
            editorElement.style.pointerEvents = 'auto';
            
            // Make sure scrollbars are fully functional
            const scrollbars = editorElement.querySelectorAll('.CodeMirror-vscrollbar, .CodeMirror-hscrollbar');
            scrollbars.forEach(scrollbar => {
                scrollbar.style.pointerEvents = 'auto';
            });
            
        <?php endif; ?>
        
        // File management
        function loadFile(fileId, fileName, language) {
            window.location.href = 'view_repo.php?id=<?php echo $repoId; ?>&file_id=' + fileId;
        }
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Allow copy functionality but prevent editing
        document.addEventListener('keydown', function(e) {
            const isInEditor = e.target.closest('.CodeMirror') || 
                              e.target.closest('.editor-container-readonly');
            
            if (isInEditor) {
                // Allow all navigation and selection keys
                const allowedKeys = [
                    'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 
                    'PageUp', 'PageDown', 'Home', 'End', 'Tab',
                    'Shift', 'Control', 'Alt', 'Meta',
                    'Escape', 'Insert', 'Delete', 'Backspace'
                ];
                
                // Allow copy (Ctrl+C / Cmd+C) and select all (Ctrl+A / Cmd+A)
                if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'a' || e.key === 'x')) {
                    if (e.key === 'x') {
                        e.preventDefault(); // Block cut
                        return false;
                    }
                    return true; // Allow copy and select all
                }
                
                // Block typing and editing keys
                if (!allowedKeys.includes(e.key) && e.key.length === 1) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Enhanced protection against editing attempts while allowing selection
        document.addEventListener('DOMContentLoaded', function() {
            // Allow context menu for copy options
            // Block drag and drop in editor area to prevent content manipulation
            document.addEventListener('dragstart', function(e) {
                if (e.target.closest('.editor-container-readonly')) {
                    e.preventDefault();
                    return false;
                }
            });
            
            document.addEventListener('drop', function(e) {
                if (e.target.closest('.editor-container-readonly')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>

<?php
function getCodeMirrorMode($language) {
    $modeMap = [
        'PHP' => 'text/x-php',
        'JavaScript' => 'text/javascript',
        'HTML' => 'text/html',
        'CSS' => 'text/css',
        'Python' => 'text/x-python',
        'Java' => 'text/x-java',
        'C++' => 'text/x-c++src',
        'C' => 'text/x-csrc',
        'SQL' => 'text/x-sql',
        'Text' => 'text/plain'
    ];
    return $modeMap[$language] ?? 'text/plain';
}
?>
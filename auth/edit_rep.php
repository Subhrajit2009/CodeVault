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
$currentVersion = 1;
$files = [];
$username = 'User';
$error = '';
$success = '';
$currentFileContent = '';
$currentFileId = null;
$currentFileName = '';
$currentFileLanguage = 'php';
$currentFilePath = '/';
$selectedVersion = 1;
$allVersions = [];
$hasReadme = false;
$readmeContent = '';
$readmeData = null;

// Get repository ID from URL
$repoId = $_GET['id'] ?? '';

if (!$repoId) {
    header('Location: my_rep.php');
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get repository data
    $stmt = $pdo->prepare("SELECT r.*, u.username 
                          FROM repositories r 
                          JOIN users u ON r.user_id = u.id 
                          WHERE r.repo_id = ? AND r.user_id = ?");
    $stmt->execute([$repoId, $_SESSION['user_id']]);
    $repoData = $stmt->fetch();
    
    if (!$repoData) {
        throw new Exception("Repository not found or access denied");
    }
    
    $username = $repoData['username'];
    $dbName = $repoData['db_name'];
    
    // Connect to repository database
    $repoDsn = "mysql:host=$host;dbname=$dbName;charset=$charset";
    $repoPdo = new PDO($repoDsn, $user, $pass, $options);
    
    // Get all version tables
    $versionStmt = $repoPdo->query("SHOW TABLES LIKE '" . $repoData['name'] . "_%'");
    $tables = $versionStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Extract version numbers
    $versions = [];
    foreach ($tables as $table) {
        if (preg_match('/_(\d+)$/', $table, $matches)) {
            $versions[] = (int)$matches[1];
        }
    }
    
    if (!empty($versions)) {
        $currentVersion = max($versions);
        $allVersions = $versions;
    }
    
    // Get selected version from URL or use current version
    $selectedVersion = $_GET['version'] ?? $currentVersion;
    $selectedTable = $repoData['name'] . '_' . $selectedVersion;
    
    // Check if version table exists, if not create it
    $tableExists = $repoPdo->query("SHOW TABLES LIKE '$selectedTable'")->fetch();
    if (!$tableExists) {
        // Create fresh table structure WITH is_folder column
        $repoPdo->exec("CREATE TABLE `$selectedTable` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(1000) DEFAULT '/',
            code LONGTEXT,
            language VARCHAR(50) DEFAULT 'text',
            file_size INT DEFAULT 0,
            is_folder BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_path (file_path(255)),
            INDEX idx_active (is_active),
            INDEX idx_folder (is_folder)
        )");
    } else {
        // Check if is_folder column exists, if not add it
        $checkColumnStmt = $repoPdo->query("SHOW COLUMNS FROM `$selectedTable` LIKE 'is_folder'");
        $columnExists = $checkColumnStmt->fetch();
        
        if (!$columnExists) {
            $repoPdo->exec("ALTER TABLE `$selectedTable` ADD COLUMN is_folder BOOLEAN DEFAULT FALSE");
            $success = "Database structure updated successfully!";
        }
    }
    
    // Get files from selected version
    $filesStmt = $repoPdo->prepare("SELECT * FROM `$selectedTable` WHERE is_active = TRUE ORDER BY file_path, file_name");
    $filesStmt->execute();
    $files = $filesStmt->fetchAll();

    // Check if README.md exists and get its content
    $readmeStmt = $repoPdo->prepare("SELECT * FROM `$selectedTable` WHERE file_name = 'README.md' AND is_active = TRUE LIMIT 1");
    $readmeStmt->execute();
    $readmeData = $readmeStmt->fetch();
    $readmeContent = $readmeData ? $readmeData['code'] : '';
    $hasReadme = !empty($readmeContent);

    // Handle file content loading
    if (isset($_GET['file_id'])) {
        $fileId = $_GET['file_id'];
        $fileStmt = $repoPdo->prepare("SELECT * FROM `$selectedTable` WHERE id = ? AND is_active = TRUE");
        $fileStmt->execute([$fileId]);
        $fileData = $fileStmt->fetch();
        
        if ($fileData) {
            $currentFileContent = $fileData['code'];
            $currentFileId = $fileData['id'];
            $currentFileName = $fileData['file_name'];
            $currentFileLanguage = $fileData['language'];
            $currentFilePath = $fileData['file_path'];
        }
    }
    
    // Handle POST operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle create file
        if (isset($_POST['create_file'])) {
            $fileName = trim($_POST['file_name']);
            $filePath = trim($_POST['file_path']);
            $code = $_POST['code'];
            $language = $_POST['language'];
            
            if (empty($fileName)) {
                $error = "File name is required";
            } else {
                // Auto-detect language from filename if not specified
                if ($language === 'auto') {
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $language = detectLanguageFromExtension($extension);
                }
                
                // Add extension if missing based on language
                if (!preg_match('/\.[a-z]+$/', $fileName)) {
                    $extensions = [
                        'php' => '.php', 'javascript' => '.js', 'html' => '.html', 
                        'css' => '.css', 'python' => '.py', 'java' => '.java',
                        'cpp' => '.cpp', 'c' => '.c', 'sql' => '.sql'
                    ];
                    $fileName .= $extensions[strtolower($language)] ?? '.txt';
                }
                
                // Check if file already exists
                $checkStmt = $repoPdo->prepare("SELECT id FROM `$selectedTable` WHERE file_path = ? AND file_name = ? AND is_active = TRUE");
                $checkStmt->execute([$filePath, $fileName]);
                
                if ($checkStmt->fetch()) {
                    $error = "File '$fileName' already exists at path '$filePath'";
                } else {
                    $insertStmt = $repoPdo->prepare("INSERT INTO `$selectedTable` (file_name, file_path, code, language, file_size, is_folder) VALUES (?, ?, ?, ?, ?, FALSE)");
                    $fileSize = strlen($code);
                    $insertStmt->execute([$fileName, $filePath, $code, $language, $fileSize]);
                    
                    $newFileId = $repoPdo->lastInsertId();
                    $success = "File '$fileName' created successfully!";
                    header("Location: edit_rep.php?id=" . $repoId . "&file_id=" . $newFileId . "&version=" . $selectedVersion);
                    exit;
                }
            }
        }
        
        // Handle folder creation
        if (isset($_POST['create_folder'])) {
            $folderName = trim($_POST['folder_name']);
            $folderPath = trim($_POST['folder_path']);
            
            if (empty($folderName)) {
                $error = "Folder name is required";
            } else {
                // Ensure folder name doesn't have extension
                $folderName = preg_replace('/\.[^\.]+$/', '', $folderName);
                
                // Check if folder already exists
                $checkStmt = $repoPdo->prepare("SELECT id FROM `$selectedTable` WHERE file_path = ? AND file_name = ? AND is_folder = TRUE AND is_active = TRUE");
                $checkStmt->execute([$folderPath, $folderName]);
                
                if ($checkStmt->fetch()) {
                    $error = "Folder '$folderName' already exists at path '$folderPath'";
                } else {
                    $insertStmt = $repoPdo->prepare("INSERT INTO `$selectedTable` (file_name, file_path, code, language, file_size, is_folder) VALUES (?, ?, '', 'folder', 0, TRUE)");
                    $insertStmt->execute([$folderName, $folderPath]);
                    
                    $success = "Folder '$folderName' created successfully!";
                    header("Location: edit_rep.php?id=" . $repoId . "&version=" . $selectedVersion);
                    exit;
                }
            }
        }
        
        // Handle file upload
        if (isset($_FILES['code_file']) && $_FILES['code_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['code_file'];
            $fileName = $uploadedFile['name'];
            $filePath = $_POST['upload_path'] ?? '/';
            $fileContent = file_get_contents($uploadedFile['tmp_name']);
            
            // Detect language from extension
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $language = detectLanguageFromExtension($extension);
            
            // Check if file already exists
            $checkStmt = $repoPdo->prepare("SELECT id FROM `$selectedTable` WHERE file_path = ? AND file_name = ? AND is_active = TRUE");
            $checkStmt->execute([$filePath, $fileName]);
            
            if ($checkStmt->fetch()) {
                $error = "File '$fileName' already exists at path '$filePath'";
            } else {
                $insertStmt = $repoPdo->prepare("INSERT INTO `$selectedTable` (file_name, file_path, code, language, file_size, is_folder) VALUES (?, ?, ?, ?, ?, FALSE)");
                $fileSize = strlen($fileContent);
                $insertStmt->execute([$fileName, $filePath, $fileContent, $language, $fileSize]);
                
                $newFileId = $repoPdo->lastInsertId();
                $success = "File '$fileName' uploaded successfully!";
                header("Location: edit_rep.php?id=" . $repoId . "&file_id=" . $newFileId . "&version=" . $selectedVersion);
                exit;
            }
        }
        
        // Handle folder upload
        if (isset($_FILES['folder_upload']) && is_array($_FILES['folder_upload']['name'])) {
            $uploadPath = $_POST['upload_folder_path'] ?? '/';
            $uploadedFiles = $_FILES['folder_upload'];
            
            $fileCount = count($uploadedFiles['name']);
            $uploadedCount = 0;
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
                    $fileName = $uploadedFiles['name'][$i];
                    $fileContent = file_get_contents($uploadedFiles['tmp_name'][$i]);
                    
                    // Detect language from extension
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $language = detectLanguageFromExtension($extension);
                    
                    // Check if file already exists
                    $checkStmt = $repoPdo->prepare("SELECT id FROM `$selectedTable` WHERE file_path = ? AND file_name = ? AND is_active = TRUE");
                    $checkStmt->execute([$uploadPath, $fileName]);
                    
                    if (!$checkStmt->fetch()) {
                        $insertStmt = $repoPdo->prepare("INSERT INTO `$selectedTable` (file_name, file_path, code, language, file_size, is_folder) VALUES (?, ?, ?, ?, ?, FALSE)");
                        $fileSize = strlen($fileContent);
                        $insertStmt->execute([$fileName, $uploadPath, $fileContent, $language, $fileSize]);
                        $uploadedCount++;
                    }
                }
            }
            
            if ($uploadedCount > 0) {
                $success = "Successfully uploaded $uploadedCount files!";
                header("Location: edit_rep.php?id=" . $repoId . "&version=" . $selectedVersion);
                exit;
            } else {
                $error = "No files were uploaded or all files already exist";
            }
        }
        
        // Handle save file changes
        if (isset($_POST['save_file'])) {
            $fileId = $_POST['file_id'];
            $codeContent = $_POST['code_content'];
            
            $updateStmt = $repoPdo->prepare("UPDATE `$selectedTable` SET code = ?, last_modified = CURRENT_TIMESTAMP, file_size = ? WHERE id = ?");
            $fileSize = strlen($codeContent);
            $updateStmt->execute([$codeContent, $fileSize, $fileId]);
            
            $success = "File saved successfully!";
            header("Location: edit_rep.php?id=" . $repoId . "&file_id=" . $fileId . "&version=" . $selectedVersion);
            exit;
        }
        
        // Handle create new version
        if (isset($_POST['create_version'])) {
            $newVersion = $currentVersion + 1;
            $newTable = $repoData['name'] . '_' . $newVersion;
            
            // Create new table with same structure (including is_folder column)
            $repoPdo->exec("CREATE TABLE `$newTable` LIKE `$selectedTable`");
            
            // Copy all active files to new version
            $repoPdo->exec("INSERT INTO `$newTable` SELECT * FROM `$selectedTable` WHERE is_active = TRUE");
            
            $success = "New version $newVersion created successfully!";
            header("Location: edit_rep.php?id=" . $repoId . "&version=" . $newVersion);
            exit;
        }
        
        // Handle delete file/folder
        if (isset($_POST['delete_file'])) {
            $fileId = $_POST['file_id'];
            $updateStmt = $repoPdo->prepare("UPDATE `$selectedTable` SET is_active = FALSE WHERE id = ?");
            $updateStmt->execute([$fileId]);
            
            $success = "Item deleted successfully!";
            header("Location: edit_rep.php?id=" . $repoId . "&version=" . $selectedVersion);
            exit;
        }
    }
    
} catch (PDOException $e) {
    error_log("Edit Repository error: " . $e->getMessage());
    $error = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    error_log("Repository error: " . $e->getMessage());
    $error = $e->getMessage();
}

// Function to detect language from file extension
function detectLanguageFromExtension($extension) {
    $languageMap = [
        'php' => 'php', 'js' => 'javascript', 'html' => 'html', 'htm' => 'html',
        'css' => 'css', 'py' => 'python', 'java' => 'java', 'cpp' => 'cpp', 
        'c' => 'c', 'sql' => 'sql', 'json' => 'json', 'xml' => 'xml',
        'txt' => 'text', 'md' => 'markdown', 'rb' => 'ruby', 'go' => 'go',
        'rs' => 'rust', 'ts' => 'typescript'
    ];
    return $languageMap[strtolower($extension)] ?? 'text';
}

// Function to get CodeMirror mode
function getCodeMirrorMode($language) {
    $modeMap = [
        'php' => 'text/x-php',
        'javascript' => 'text/javascript',
        'html' => 'text/html',
        'css' => 'text/css',
        'python' => 'text/x-python',
        'java' => 'text/x-java',
        'cpp' => 'text/x-c++src',
        'c' => 'text/x-csrc',
        'sql' => 'text/x-sql',
        'json' => 'application/json',
        'xml' => 'text/xml',
        'text' => 'text/plain',
        'markdown' => 'text/x-markdown',
        'ruby' => 'text/x-ruby',
        'go' => 'text/x-go',
        'rust' => 'text/x-rust',
        'typescript' => 'text/typescript'
    ];
    return $modeMap[strtolower($language)] ?? 'text/plain';
}

// Function to organize files into a hierarchical structure
function organizeFilesHierarchically($files) {
    $hierarchy = [];
    
    foreach ($files as $file) {
        $isFolder = isset($file['is_folder']) ? (bool)$file['is_folder'] : false;
        $path = $file['file_path'] ?? '/';
        $name = $file['file_name'] ?? '';
        
        // Split path into parts
        $pathParts = array_filter(explode('/', $path));
        
        // Start from root
        $currentLevel = &$hierarchy;
        
        // Navigate to the correct level in hierarchy
        foreach ($pathParts as $part) {
            if (!isset($currentLevel[$part])) {
                $currentLevel[$part] = [
                    'type' => 'folder',
                    'name' => $part,
                    'children' => []
                ];
            }
            $currentLevel = &$currentLevel[$part]['children'];
        }
        
        // Add file to current level
        $currentLevel[$name] = [
            'type' => $isFolder ? 'folder' : 'file',
            'name' => $name,
            'id' => $file['id'] ?? null,
            'language' => $file['language'] ?? 'text',
            'file_path' => $file['file_path'] ?? '/'
        ];
        
        if (!$isFolder) {
            $currentLevel[$name]['size'] = $file['file_size'] ?? 0;
        }
    }
    
    return $hierarchy;
}

// Organize files for display
$fileHierarchy = organizeFilesHierarchically($files);

// Safe display variables
$displayUsername = htmlspecialchars($username);
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closetag.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
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
            overflow: hidden;
        }

        .editor-container {
            display: flex;
            min-height: 100vh;
            max-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 25px 0;
            box-shadow: var(--shadow);
            z-index: 100;
            transition: var(--transition);
            overflow-y: auto;
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

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            max-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-shrink: 0;
        }

        .header h2 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }

        .repo-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--light);
            padding: 10px 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .repo-type {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .repo-type.public {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .repo-type.private {
            background: rgba(114, 9, 183, 0.1);
            color: var(--secondary);
        }

        .version-badge {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .version-selector {
            background: var(--light);
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            padding: 6px 12px;
            color: var(--dark);
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .theme-toggle, .logout-btn {
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

        .theme-toggle:hover, .logout-btn:hover {
            background: var(--primary);
            color: white;
        }

        .logout-btn:hover {
            background: var(--danger);
        }

        /* Editor Layout */
        .editor-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            flex: 1;
            min-height: 0;
            max-height: calc(100vh - 120px);
        }

        /* File Explorer */
        .file-explorer {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .explorer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-light);
            flex-shrink: 0;
        }

        .explorer-title {
            font-size: 18px;
            font-weight: 600;
        }

        .file-list {
            list-style: none;
            overflow-y: auto;
            flex: 1;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .file-item:hover {
            background: var(--gray-light);
            border-color: var(--primary);
        }

        .file-item.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary-dark);
        }

        .file-icon {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .file-name {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
        }

        .file-meta {
            font-size: 11px;
            opacity: 0.8;
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 4px;
        }

        .file-item.active .file-meta {
            background: rgba(255,255,255,0.2);
        }

        /* Tree view for folders */
        .tree-view {
            list-style: none;
            padding-left: 20px;
        }

        .tree-item {
            padding: 8px 0;
        }

        .tree-folder {
            cursor: pointer;
            user-select: none;
        }

        .tree-folder > div {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .tree-folder > div:hover {
            background: var(--gray-light);
        }

        .tree-folder i {
            margin-right: 8px;
            transition: var(--transition);
        }

        .tree-folder.expanded i.fa-folder {
            color: var(--warning);
        }

        .tree-folder.expanded i.fa-chevron-right {
            transform: rotate(90deg);
        }

        .tree-children {
            margin-left: 20px;
            display: none;
        }

        .tree-folder.expanded .tree-children {
            display: block;
        }

        /* README Styles */
        .readme-section {
            border-top: 1px solid var(--gray-light);
            margin-top: 20px;
            padding-top: 20px;
        }

        .readme-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .readme-content {
            background: var(--gray-light);
            padding: 15px;
            border-radius: var(--border-radius);
            max-height: 200px;
            overflow-y: auto;
            font-size: 13px;
            line-height: 1.4;
            border: 1px solid var(--gray-light);
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .readme-content::-webkit-scrollbar {
            width: 6px;
        }

        .readme-content::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 3px;
        }

        .readme-content::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: 3px;
        }

        .readme-content::-webkit-scrollbar-thumb:hover {
            background: var(--dark);
        }

        /* Editor Panel */
        .editor-panel {
            background: var(--light);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .editor-tabs {
            display: flex;
            background: var(--gray-light);
            padding: 0 15px;
            border-bottom: 1px solid var(--gray-light);
            flex-shrink: 0;
        }

        .editor-tab {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 3px solid transparent;
            font-weight: 500;
            color: var(--gray);
        }

        .editor-tab.active {
            background: var(--light);
            border-bottom-color: var(--primary);
            color: var(--dark);
        }

        .editor-content {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .tab-content {
            display: none;
            flex: 1;
            overflow: hidden;
            flex-direction: column;
        }

        .tab-content.active {
            display: flex;
        }

        /* Code Editor */
        .code-editor-container {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        .code-editor {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: none;
            font-family: 'Fira Code', 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
            line-height: 1.5;
        }

        .CodeMirror {
            height: 100% !important;
            font-family: 'Fira Code', 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
        }

        .editor-header {
            padding: 15px 20px;
            background: var(--gray-light);
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-path {
            font-size: 14px;
            color: var(--gray);
            font-family: monospace;
        }

        .language-badge {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }

        /* Form Styles */
        .form-container {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #3ab0d9;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c1121f;
        }

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            padding: 20px;
            border-top: 1px solid var(--gray-light);
            flex-shrink: 0;
        }

        /* File Upload */
        .file-upload {
            border: 2px dashed var(--gray-light);
            border-radius: var(--border-radius);
            padding: 40px;
            text-align: center;
            margin-bottom: 20px;
            transition: var(--transition);
            cursor: pointer;
        }

        .file-upload:hover {
            border-color: var(--primary);
            background: rgba(67, 97, 238, 0.05);
        }

        .file-upload input {
            display: none;
        }

        .file-upload-label {
            cursor: pointer;
            color: var(--gray);
        }

        .file-upload-label:hover {
            color: var(--primary);
        }

        .hidden {
            display: none;
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
                <li><a href="edit_rep.php?id=<?php echo $repoId; ?>" class="active"><i class="fas fa-edit"></i> Editor</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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
                        <span class="version-badge">Version <?php echo $selectedVersion; ?></span>
                        <select class="version-selector" onchange="changeVersion(this.value)">
                            <?php for ($v = 1; $v <= $currentVersion; $v++): ?>
                                <option value="<?php echo $v; ?>" <?php echo $v == $selectedVersion ? 'selected' : ''; ?>>
                                    Version <?php echo $v; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="theme-toggle" id="themeToggle">
                        <i class="fas fa-moon"></i>
                    </button>
                    <button class="logout-btn" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i>
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

            <div class="editor-layout">
                <!-- File Explorer -->
                <div class="file-explorer">
                    <div class="explorer-header">
                        <div class="explorer-title">Project Files</div>
                        <div>
                            <button class="btn btn-primary btn-sm" onclick="showTab('create-tab')">
                                <i class="fas fa-file"></i> File
                            </button><br><br>
                            <button class="btn btn-success btn-sm" onclick="showTab('folder-tab')">
                                <i class="fas fa-folder"></i> Folder
                            </button>
                        </div>
                    </div>
                    
                    <div class="file-list">
                        <?php if (empty($files)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--gray);">
                                <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                <p>No files yet. Create your first file!</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            function renderFileTree($tree, $path = '') {
                                $html = '';
                                foreach ($tree as $name => $item) {
                                    if ($item['type'] === 'folder') {
                                        $html .= '<li class="tree-item">';
                                        $html .= '<div class="tree-folder" onclick="toggleFolder(this)">';
                                        $html .= '<div>';
                                        $html .= '<i class="fas fa-chevron-right"></i>';
                                        $html .= '<i class="fas fa-folder"></i>';
                                        $html .= '<span>' . htmlspecialchars($name) . '</span>';
                                        $html .= '</div>';
                                        $html .= '</div>';
                                        $html .= '<ul class="tree-children">';
                                        $html .= renderFileTree($item['children'] ?? [], $path . $name . '/');
                                        $html .= '</ul>';
                                        $html .= '</li>';
                                    } else {
                                        $activeClass = isset($item['id']) && $item['id'] == $GLOBALS['currentFileId'] ? 'active' : '';
                                        $html .= '<li class="file-item ' . $activeClass . '" onclick="loadFile(' . ($item['id'] ?? '0') . ')">';
                                        $html .= '<i class="fas fa-file-code file-icon"></i>';
                                        $html .= '<span class="file-name">' . htmlspecialchars($name) . '</span>';
                                        $html .= '<span class="file-meta">' . strtoupper($item['language'] ?? 'text') . '</span>';
                                        $html .= '</li>';
                                    }
                                }
                                return $html;
                            }
                            
                            echo '<ul class="tree-view">';
                            echo renderFileTree($fileHierarchy);
                            echo '</ul>';
                            ?>
                        <?php endif; ?>
                    </div>

                    <!-- README.md Display -->
                    <?php if ($hasReadme): ?>
                    <div class="readme-section">
                        <div class="readme-header">
                            <h4 style="font-size: 16px; font-weight: 600; color: var(--dark);">
                                <i class="fas fa-book" style="margin-right: 8px; color: var(--primary);"></i>
                                README.md
                            </h4>
                            <button class="btn btn-sm" onclick="loadFile(<?php echo $readmeData['id']; ?>)" 
                                    style="background: var(--primary); color: white; padding: 4px 8px; font-size: 11px;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                        <div class="readme-content">
                            <?php echo htmlspecialchars($readmeContent); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Editor Panel -->
                <div class="editor-panel">
                    <div class="editor-tabs">
                        <button class="editor-tab <?php echo $currentFileId ? 'active' : ''; ?>" onclick="showTab('editor-tab')">
                            <i class="fas fa-code"></i> Editor
                        </button>
                        <button class="editor-tab <?php echo !$currentFileId ? 'active' : ''; ?>" onclick="showTab('create-tab')">
                            <i class="fas fa-file"></i> Create File
                        </button>
                        <button class="editor-tab" onclick="showTab('folder-tab')">
                            <i class="fas fa-folder"></i> Create Folder
                        </button>
                        <button class="editor-tab" onclick="showTab('upload-tab')">
                            <i class="fas fa-upload"></i> Upload File
                        </button>
                        <button class="editor-tab" onclick="showTab('folder-upload-tab')">
                            <i class="fas fa-folder-open"></i> Upload Folder
                        </button>
                        <button class="editor-tab" onclick="showTab('version-tab')">
                            <i class="fas fa-code-branch"></i> Versions
                        </button>
                    </div>

                    <div class="editor-content">
                        <!-- Code Editor Tab -->
                        <div id="editor-tab" class="tab-content <?php echo $currentFileId ? 'active' : ''; ?>">
                            <?php if ($currentFileId): ?>
                                <div class="editor-header">
                                    <div class="file-info">
                                        <span class="file-path"><?php echo $currentFilePath . $currentFileName; ?></span>
                                        <span class="language-badge"><?php echo strtoupper($currentFileLanguage); ?></span>
                                    </div>
                                    <div class="file-actions">
                                        <button class="btn btn-danger btn-sm" onclick="deleteFile()">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                                <div class="code-editor-container">
                                    <textarea id="code-editor" class="code-editor hidden"><?php echo htmlspecialchars($currentFileContent); ?></textarea>
                                </div>
                                <form method="POST" id="save-form">
                                    <input type="hidden" name="file_id" value="<?php echo $currentFileId; ?>">
                                    <input type="hidden" name="code_content" id="code-content">
                                    <input type="hidden" name="save_file" value="1">
                                </form>
                                <form method="POST" id="delete-form">
                                    <input type="hidden" name="file_id" value="<?php echo $currentFileId; ?>">
                                    <input type="hidden" name="delete_file" value="1">
                                </form>
                                <div class="action-buttons">
                                    <button class="btn btn-primary" onclick="saveFile()">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button class="btn btn-success" onclick="createNewVersion()">
                                        <i class="fas fa-code-branch"></i> Create New Version
                                    </button>
                                </div>
                            <?php else: ?>
                                <div style="padding: 40px; text-align: center; color: var(--gray); flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                    <i class="fas fa-file-code" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                                    <h3>No File Selected</h3>
                                    <p>Select a file from the explorer or create a new one to start editing.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Create File Tab -->
                        <div id="create-tab" class="tab-content <?php echo !$currentFileId ? 'active' : ''; ?>">
                            <div class="form-container">
                                <form method="POST" id="create-file-form">
                                    <div class="form-group">
                                        <label for="file_name">File Name</label>
                                        <input type="text" class="form-control" id="file_name" name="file_name" 
                                               placeholder="example.php" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="file_path">File Path</label>
                                        <input type="text" class="form-control" id="file_path" name="file_path" 
                                               placeholder="/src/controllers" value="/">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="language">Language</label>
                                        <select class="form-control" id="language" name="language">
                                            <option value="auto">Auto-detect from filename</option>
                                            <option value="php">PHP</option>
                                            <option value="javascript">JavaScript</option>
                                            <option value="html">HTML</option>
                                            <option value="css">CSS</option>
                                            <option value="python">Python</option>
                                            <option value="java">Java</option>
                                            <option value="cpp">C++</option>
                                            <option value="sql">SQL</option>
                                            <option value="text">Text</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="code">Code Content</label>
                                        <textarea class="form-control" id="code" name="code" rows="15" 
                                                  placeholder="Enter your code here..." style="font-family: 'Fira Code', monospace;"></textarea>
                                    </div>
                                    
                                    <button type="submit" name="create_file" class="btn btn-primary btn-block">
                                        <i class="fas fa-plus"></i> Create File
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Create Folder Tab -->
                        <div id="folder-tab" class="tab-content">
                            <div class="form-container">
                                <form method="POST" id="create-folder-form">
                                    <div class="form-group">
                                        <label for="folder_name">Folder Name</label>
                                        <input type="text" class="form-control" id="folder_name" name="folder_name" 
                                               placeholder="controllers" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="folder_path">Folder Path</label>
                                        <input type="text" class="form-control" id="folder_path" name="folder_path" 
                                               placeholder="/src" value="/">
                                    </div>
                                    
                                    <button type="submit" name="create_folder" class="btn btn-primary btn-block">
                                        <i class="fas fa-folder-plus"></i> Create Folder
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Upload File Tab -->
                        <div id="upload-tab" class="tab-content">
                            <div class="form-container">
                                <form method="POST" enctype="multipart/form-data" id="upload-form">
                                    <div class="file-upload" onclick="document.getElementById('code_file').click()">
                                        <input type="file" id="code_file" name="code_file" accept=".php,.js,.html,.css,.py,.java,.cpp,.c,.sql,.json,.xml,.txt,.md,.rb,.go,.rs,.ts">
                                        <label for="code_file" class="file-upload-label">
                                            <i class="fas fa-cloud-upload-alt" style="font-size: 48px; margin-bottom: 15px;"></i>
                                            <h3 id="upload-text">Click to upload code files</h3>
                                            <p>Supported: PHP, JS, HTML, CSS, Python, Java, C++, C, SQL, JSON, XML, TXT, MD, Ruby, Go, Rust, TypeScript</p>
                                        </label>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="upload_path">Upload Path</label>
                                        <input type="text" class="form-control" id="upload_path" name="upload_path" 
                                               placeholder="/src" value="/">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-upload"></i> Upload File
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Upload Folder Tab -->
                        <div id="folder-upload-tab" class="tab-content">
                            <div class="form-container">
                                <form method="POST" enctype="multipart/form-data" id="folder-upload-form">
                                    <div class="file-upload" onclick="document.getElementById('folder_upload').click()">
                                        <input type="file" id="folder_upload" name="folder_upload[]" multiple accept=".php,.js,.html,.css,.py,.java,.cpp,.c,.sql,.json,.xml,.txt,.md,.rb,.go,.rs,.ts">
                                        <label for="folder_upload" class="file-upload-label">
                                            <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px;"></i>
                                            <h3 id="folder-upload-text">Click to upload multiple files</h3>
                                            <p>Select multiple files to upload them all at once to the same directory</p>
                                        </label>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="upload_folder_path">Upload Path</label>
                                        <input type="text" class="form-control" id="upload_folder_path" name="upload_folder_path" 
                                               placeholder="/src" value="/">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-upload"></i> Upload Files
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Version Control Tab -->
                        <div id="version-tab" class="tab-content">
                            <div class="form-container">
                                <h3>Version Control</h3>
                                <p>Current Version: <strong><?php echo $currentVersion; ?></strong></p>
                                <p>Selected Version: <strong><?php echo $selectedVersion; ?></strong></p>
                                <p>Create a new version to save the current state of your project.</p>
                                
                                <form method="POST">
                                    <button type="submit" name="create_version" class="btn btn-success btn-block">
                                        <i class="fas fa-code-branch"></i> Create New Version (v<?php echo $currentVersion + 1; ?>)
                                    </button>
                                </form>
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
            
            // Update CodeMirror theme
            if (window.editor) {
                window.editor.setOption('theme', newTheme === 'dark' ? 'monokai' : 'eclipse');
            }
        });
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        
        // Tab management
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.editor-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
        
        // CodeMirror Editor
        let editor;
        <?php if ($currentFileId): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const editorTextarea = document.getElementById('code-editor');
            const mode = '<?php echo getCodeMirrorMode($currentFileLanguage); ?>';
            const theme = savedTheme === 'dark' ? 'monokai' : 'eclipse';
            
            editor = CodeMirror.fromTextArea(editorTextarea, {
                mode: mode,
                theme: theme,
                lineNumbers: true,
                lineWrapping: true,
                matchBrackets: true,
                autoCloseBrackets: true,
                indentUnit: 4,
                indentWithTabs: false
            });
            
            window.editor = editor;
        });
        <?php endif; ?>
        
        // File management
        function loadFile(fileId) {
            window.location.href = 'edit_rep.php?id=<?php echo $repoId; ?>&file_id=' + fileId + '&version=<?php echo $selectedVersion; ?>';
        }
        
        function saveFile() {
            if (!<?php echo $currentFileId ? 'true' : 'false'; ?>) {
                alert('Please select a file to edit');
                return;
            }
            
            // Get content from CodeMirror editor if available, otherwise from textarea
            let content;
            if (window.editor) {
                content = editor.getValue();
            } else {
                content = document.getElementById('code-editor').value;
            }
            
            document.getElementById('code-content').value = content;
            document.getElementById('save-form').submit();
        }
        
        function deleteFile() {
            if (confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
                document.getElementById('delete-form').submit();
            }
        }
        
        function createNewVersion() {
            if (confirm('Create a new version? This will save the current state and create version <?php echo $currentVersion + 1; ?>.')) {
                document.querySelector('[name="create_version"]').click();
            }
        }
        
        function changeVersion(version) {
            window.location.href = 'edit_rep.php?id=<?php echo $repoId; ?>&version=' + version;
        }
        
        function toggleFolder(element) {
            element.classList.toggle('expanded');
        }
        
        // File upload preview
        document.getElementById('code_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                document.getElementById('upload-text').textContent = 'Selected: ' + fileName;
            }
        });
        
        // Folder upload preview
        document.getElementById('folder_upload').addEventListener('change', function(e) {
            const fileCount = e.target.files.length;
            if (fileCount > 0) {
                document.getElementById('folder-upload-text').textContent = 'Selected: ' + fileCount + ' files';
            }
        });
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveFile();
            }
        });
    </script>
</body>
</html>
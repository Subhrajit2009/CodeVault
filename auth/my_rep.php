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
$repositories = [];
$username = 'User';
$totalRepos = 0;
$publicCount = 0;
$privateCount = 0;

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get user data
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    
    if (!$userData) {
        throw new Exception("User not found");
    }
    
    $username = $userData['username'];
    
    // Get all repositories for the user
    $stmt = $pdo->prepare("SELECT * FROM repositories WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $repositories = $stmt->fetchAll();
    
    $totalRepos = count($repositories);
    
    // Count public and private repositories
    foreach ($repositories as $repo) {
        if ($repo['type'] === 'public') $publicCount++;
        if ($repo['type'] === 'private') $privateCount++;
    }
    
} catch (PDOException $e) {
    error_log("My Repositories error: " . $e->getMessage());
    $error = "Database connection error. Please try again.";
} catch (Exception $e) {
    error_log("User error: " . $e->getMessage());
    $error = $e->getMessage();
}

// Safe username display
$displayUsername = htmlspecialchars($username);
$userInitial = strtoupper(substr($username, 0, 1));
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>My Repositories | CodeVault</title>
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

        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-item {
            background: var(--light);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        /* Repository Grid */
        .repos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .repo-card {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary);
            position: relative;
            cursor: pointer;
        }

        .repo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .repo-card.public {
            border-left-color: var(--success);
        }

        .repo-card.private {
            border-left-color: var(--secondary);
        }

        .repo-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .repo-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
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

        .repo-id {
            font-size: 11px;
            color: var(--gray);
            font-family: monospace;
            background: var(--gray-light);
            padding: 2px 8px;
            border-radius: 4px;
            margin-bottom: 10px;
            display: inline-block;
        }

        .repo-description {
            color: var(--gray);
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .repo-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--gray-light);
        }

        .repo-date {
            font-size: 12px;
            color: var(--gray);
        }

        .repo-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-light);
            color: var(--gray);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--gray-light);
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .empty-state p {
            margin-bottom: 20px;
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
            
            .repos-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
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
        }

        @media (max-width: 480px) {
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .repo-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .repo-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-code"></i>
                <h1>CodeVault</h1>
            </div>
            <ul class="nav-links">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="dashboard.php#createRepoBtn"><i class="fas fa-plus"></i> Create Repository</a></li>
                <li><a href="my_rep.php" class="active"><i class="fas fa-code-branch"></i> My Repositories</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../index.php#contact"><i class="fas fa-question-circle"></i> Help & Support</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2>My Repositories</h2>
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
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $totalRepos; ?></div>
                    <div class="stat-label">Total Repositories</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $publicCount; ?></div>
                    <div class="stat-label">Public</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $privateCount; ?></div>
                    <div class="stat-label">Private</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo date('M Y'); ?></div>
                    <div class="stat-label">Current Month</div>
                </div>
            </div>

            <!-- Repositories Grid -->
            <div class="repos-grid">
                <?php if (empty($repositories)): ?>
                    <div class="empty-state">
                        <i class="fas fa-code-branch"></i>
                        <h3>No repositories yet</h3>
                        <p>Create your first repository to start organizing your code</p>
                        <a href="dashboard.php#createRepoBtn" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Repository
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($repositories as $repo): ?>
                        <div class="repo-card <?php echo $repo['type']; ?>" onclick="openRepository('<?php echo $repo['repo_id']; ?>')">
                            <div class="repo-header">
                                <div>
                                    <div class="repo-name"><?php echo htmlspecialchars($repo['name']); ?></div>
                                    <div class="repo-id"><?php echo htmlspecialchars($repo['repo_id']); ?></div>
                                </div>
                                <span class="repo-type <?php echo $repo['type']; ?>">
                                    <i class="fas fa-<?php echo $repo['type'] === 'public' ? 'globe' : 'lock'; ?>"></i>
                                    <?php echo $repo['type']; ?>
                                </span>
                            </div>
                            
                            <div class="repo-description">
                                <?php echo htmlspecialchars($repo['description'] ?: 'No description provided'); ?>
                            </div>
                            
                            <div class="repo-meta">
                                <div class="repo-date">
                                    Created <?php echo date('M j, Y', strtotime($repo['created_at'])); ?>
                                </div>
                                <div class="repo-actions">
                                    <div class="repo-actions">
                                    <button class="btn btn-outline" onclick="event.stopPropagation(); shareRepository('<?php echo $repo['repo_id']; ?>')">
                                        <i class="fas fa-share"></i> Share
                                    </button>
                                    <a href="edit_rep.php?id=<?php echo $repo['repo_id']; ?>" class="btn btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-folder-open"></i> Open
                                    </a>
                                </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
        
        // Repository functions
        function openRepository(repoId) {
            // For now, just show an alert. Later we'll redirect to repository details page.
            alert('Opening repository: ' + repoId + '\n\nThis will redirect you to a page where you can edit the repository code.');
            // window.location.href = 'repository.php?id=' + repoId;
        }
        
        function shareRepository(repoId) {
            alert('Share repository: ' + repoId + '\n\nShare functionality will be added later.');
        }
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Add click effect to repo cards
        document.querySelectorAll('.repo-card').forEach(card => {
            card.addEventListener('mousedown', function() {
                this.style.transform = 'scale(0.98)';
            });
            
            card.addEventListener('mouseup', function() {
                this.style.transform = '';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = '';
            });
        });
    </script>
</body>
</html>
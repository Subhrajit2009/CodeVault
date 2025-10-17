<?php
// Handle AJAX search suggestions request
if (isset($_GET['ajax_suggestions']) && isset($_GET['q'])) {
    session_start();
    
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
?>
<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme'])) {
    $theme = $_POST['theme'];
    
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
    
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $stmt = $pdo->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
        $stmt->execute([$theme, $_SESSION['user_id']]);
        
        echo "Theme updated successfully";
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Error updating theme";
    }
}
?>
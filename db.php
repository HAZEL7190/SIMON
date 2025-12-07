<?php
// Database connection helper (PDO)
// Uses XAMPP defaults: host=localhost, user=root, password=(empty), database=eligibility
function get_db_pdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = '127.0.0.1';
    $db   = 'eligibility';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $opts);
        return $pdo;
    } catch (PDOException $e) {
        // In production do not expose details
        http_response_code(500);
        echo "Database connection failed";
        exit;
    }
}

?>

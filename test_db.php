<?php
// Quick DB connection test — open this in your browser to see the result.
require_once __DIR__ . '/db.php';
try {
    $pdo = get_db_pdo();
    echo "Connected to database successfully.<br>";
    // show basic info
    $stmt = $pdo->query("SELECT DATABASE() as db");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Current database: " . htmlspecialchars($row['db']);
} catch (Exception $e) {
    // show the exception message for debugging (remove in production)
    echo "Connection failed: " . htmlspecialchars($e->getMessage());
}

?>

<?php
// login.php
// Accepts form POST (login.html) with `email` and `password` and starts a session.
session_start();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    $msg = urlencode('Please enter email and password.');
    header('Location: login.html?error=' . $msg);
    exit;
}

try {
    $pdo = get_db_pdo();
    $stmt = $pdo->prepare('SELECT id, name, password FROM students WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $msg = urlencode('Invalid email or password.');
        header('Location: login.html?error=' . $msg);
        exit;
    }

    // success: store session and redirect to home
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    header('Location: home.php');
    exit;

} catch (Exception $e) {
    $msg = urlencode('Server error. Try again later.');
    header('Location: login.html?error=' . $msg);
    exit;
}

?>

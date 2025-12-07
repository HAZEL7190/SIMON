<?php
// signup.php
// Accepts JSON POST from `index.html` signup form and creates a new student record.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

// read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$student_id = trim($data['student_id'] ?? '');
$password = $data['password'] ?? '';

// Basic validation
if (!$name || !$email || !$student_id || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill out all fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

try {
    $pdo = get_db_pdo();

    // check duplicates by email or student_id
    $stmt = $pdo->prepare('SELECT id FROM students WHERE email = :email OR student_id = :sid LIMIT 1');
    $stmt->execute([':email' => $email, ':sid' => $student_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'An account with that email or student ID already exists.']);
        exit;
    }

    // store hashed password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $ins = $pdo->prepare('INSERT INTO students (name, email, student_id, password) VALUES (:name, :email, :sid, :pw)');
    $ins->execute([':name' => $name, ':email' => $email, ':sid' => $student_id, ':pw' => $hash]);

    // success
    echo json_encode(['success' => true, 'message' => 'Account created', 'name' => $name]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Try again later.']);
    exit;
}

?>

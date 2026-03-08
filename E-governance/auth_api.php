<?php
// Prevent HTML error output - force JSON errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Database connection - simplified
$servername = "localhost";
$username = "root";
$password = "";

// Connect directly to the database (create if doesn't exist)
$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database server connection failed']);
    exit;
}

// Create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS e_governance");

// Connect to the specific database
$conn = new mysqli($servername, $username, $password, "e_governance");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Ensure Users table exists
$createTable = "CREATE TABLE IF NOT EXISTS Users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    national_id VARCHAR(50) UNIQUE NOT NULL,
    role ENUM('Citizen', 'Admin') NOT NULL DEFAULT 'Citizen',
    district VARCHAR(100),
    province VARCHAR(100),
    profile_photo VARCHAR(255),
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    account_status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_national_id (national_id),
    INDEX idx_role (role),
    INDEX idx_account_status (account_status)
)";

if (!$conn->query($createTable)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create database table']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Invalid request data - could not parse JSON']);
            exit;
        }

        $action = $data['action'] ?? '';

        switch ($action) {
            case 'login':
                handleLogin($conn, $data);
                break;
            case 'register':
                handleRegister($conn, $data);
                break;
            case 'check_user':
                checkUserExists($conn, $data);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
} else {
    // Handle GET requests for testing
    $dbStatus = ['connected' => false, 'database_exists' => false, 'users_table_exists' => false];

    // Test database connection
    if ($conn) {
        $dbStatus['connected'] = true;

        // Check if database exists
        $dbCheck = $conn->query("SELECT DATABASE()");
        if ($dbCheck && $dbCheck->fetch_row()[0] === 'e_governance') {
            $dbStatus['database_exists'] = true;

            // Check if Users table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'Users'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $dbStatus['users_table_exists'] = true;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'E-Governance Auth API is working!',
        'timestamp' => date('Y-m-d H:i:s'),
        'database_status' => $dbStatus,
        'methods' => ['POST'],
        'endpoints' => [
            'login' => ['email', 'password'],
            'register' => ['firstName', 'lastName', 'email', 'password', 'phone', 'nationalId', 'district', 'province'],
            'check_user' => ['email']
        ]
    ]);
}

function handleLogin($conn, $data) {
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        return;
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password_hash, role FROM Users WHERE email = ? AND account_status = 'Active'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found. Please register first.', 'needs_registration' => true]);
        return;
    }

    $user = $result->fetch_assoc();

    // Debug: Check if password verification is working
    $passwordValid = password_verify($password, $user['password_hash']);

    if (!$passwordValid) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid password',
            'debug' => [
                'password_provided' => $password,
                'hash_stored' => $user['password_hash'],
                'verification_result' => $passwordValid
            ]
        ]);
        return;
    }

    // Update last login
    $updateStmt = $conn->prepare("UPDATE Users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
    $updateStmt->bind_param("i", $user['user_id']);
    $updateStmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['user_id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
}

function handleRegister($conn, $data) {
    $firstName = $data['firstName'] ?? '';
    $lastName = $data['lastName'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $phone = $data['phone'] ?? '';
    $nationalId = $data['nationalId'] ?? '';
    $district = $data['district'] ?? '';
    $province = $data['province'] ?? '';

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($phone) || empty($nationalId)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        return;
    }

    // Check if national ID already exists
    $stmt = $conn->prepare("SELECT user_id FROM Users WHERE national_id = ?");
    $stmt->bind_param("s", $nationalId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'National ID already registered']);
        return;
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO Users (first_name, last_name, email, password_hash, phone, national_id, district, province, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Citizen')");
    $stmt->bind_param("ssssssss", $firstName, $lastName, $email, $passwordHash, $phone, $nationalId, $district, $province);

    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'userId' => $userId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed: ' . $stmt->error
        ]);
    }
}

function checkUserExists($conn, $data) {
    $email = $data['email'] ?? '';

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        return;
    }

    $stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    echo json_encode(['exists' => $result->num_rows > 0]);
}

$conn->close();
?>
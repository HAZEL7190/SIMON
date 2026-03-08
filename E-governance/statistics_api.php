<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
error_reporting(E_ALL);

function jsonErrorHandler($errno, $errstr, $errfile, $errline) {
    echo json_encode([
        'success' => false,
        'message' => 'PHP Error: ' . $errstr
    ]);
    exit;
}
set_error_handler('jsonErrorHandler');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$servername = "localhost";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database server connection failed']));
}

$conn->query("CREATE DATABASE IF NOT EXISTS e_governance");
$conn = new mysqli($servername, $username, $password, "e_governance");

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'get_statistics') {
        getApplicationStatistics($conn, $data);
    } elseif ($action === 'get_recent_applications') {
        getRecentApplications($conn, $data);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function getApplicationStatistics($conn, $data) {
    $user_id = $data['user_id'] ?? null;

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }

    // Check if Applications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'Applications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        // Table doesn't exist, return default statistics
        echo json_encode([
            'success' => true,
            'total_applications' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'message' => 'Applications table not yet created'
        ]);
        return;
    }

    // Get total applications (excluding drafts)
    $totalResult = $conn->query("
        SELECT COUNT(*) as count FROM Applications 
        WHERE user_id = $user_id AND status != 'Draft'
    ");
    $total = $totalResult->fetch_assoc()['count'] ?? 0;

    // Get pending applications (Submitted or Under Review)
    $pendingResult = $conn->query("
        SELECT COUNT(*) as count FROM Applications 
        WHERE user_id = $user_id AND status IN ('Submitted', 'Under Review')
    ");
    $pending = $pendingResult->fetch_assoc()['count'] ?? 0;

    // Get approved applications
    $approvedResult = $conn->query("
        SELECT COUNT(*) as count FROM Applications 
        WHERE user_id = $user_id AND status = 'Approved'
    ");
    $approved = $approvedResult->fetch_assoc()['count'] ?? 0;

    // Get rejected applications
    $rejectedResult = $conn->query("
        SELECT COUNT(*) as count FROM Applications 
        WHERE user_id = $user_id AND status = 'Rejected'
    ");
    $rejected = $rejectedResult->fetch_assoc()['count'] ?? 0;

    echo json_encode([
        'success' => true,
        'total_applications' => (int)$total,
        'pending' => (int)$pending,
        'approved' => (int)$approved,
        'rejected' => (int)$rejected
    ]);
}

function getRecentApplications($conn, $data) {
    $user_id = $data['user_id'] ?? null;
    $limit = $data['limit'] ?? 5;

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }

    // Check if Applications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'Applications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(['success' => true, 'applications' => []]);
        return;
    }

    $query = "
        SELECT 
            a.app_id,
            a.app_reference,
            s.service_name,
            a.status,
            a.submission_date,
            a.status as application_status
        FROM Applications a
        LEFT JOIN ServiceTypes s ON a.service_id = s.service_id
        WHERE a.user_id = $user_id
        ORDER BY a.created_at DESC
        LIMIT $limit
    ";

    $result = $conn->query($query);

    if (!$result) {
        echo json_encode([
            'success' => true,
            'applications' => [],
            'message' => 'ServiceTypes table may not exist yet'
        ]);
        return;
    }

    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }

    echo json_encode([
        'success' => true,
        'applications' => $applications
    ]);
}

$conn->close();
?>
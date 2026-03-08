<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

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

// Database connection
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

    switch ($action) {
        case 'get_admin_statistics':
            getAdminStatistics($conn);
            break;
        case 'get_pending_applications':
            getPendingApplications($conn, $data);
            break;
        case 'approve_application':
            updateApplicationStatus($conn, $data, 'Approved');
            break;
        case 'reject_application':
            updateApplicationStatus($conn, $data, 'Rejected');
            break;
        case 'get_all_applications':
            getAllApplications($conn, $data);
            break;
        case 'get_users_overview':
            getUsersOverview($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

function getAdminStatistics($conn) {
    // Total Applications
    $totalApps = $conn->query("SELECT COUNT(*) as count FROM Applications WHERE status != 'Draft'");
    $totalApplications = $totalApps ? $totalApps->fetch_assoc()['count'] : 0;

    // Pending Applications
    $pendingApps = $conn->query("SELECT COUNT(*) as count FROM Applications WHERE status IN ('Submitted', 'Under Review')");
    $pendingApplications = $pendingApps ? $pendingApps->fetch_assoc()['count'] : 0;

    // Approved Today
    $approvedToday = $conn->query("SELECT COUNT(*) as count FROM Applications WHERE status = 'Approved' AND DATE(approval_date) = CURDATE()");
    $approvedTodayCount = $approvedToday ? $approvedToday->fetch_assoc()['count'] : 0;

    // Total Users
    $totalUsers = $conn->query("SELECT COUNT(*) as count FROM Users WHERE account_status = 'Active'");
    $totalUsersCount = $totalUsers ? $totalUsers->fetch_assoc()['count'] : 0;

    // Applications reviewed today
    $reviewedToday = $conn->query("SELECT COUNT(*) as count FROM Applications WHERE (status = 'Approved' OR status = 'Rejected') AND DATE(updated_at) = CURDATE()");
    $reviewedTodayCount = $reviewedToday ? $reviewedToday->fetch_assoc()['count'] : 0;

    // New users this week
    $newUsersWeek = $conn->query("SELECT COUNT(*) as count FROM Users WHERE account_status = 'Active' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $newUsersWeekCount = $newUsersWeek ? $newUsersWeek->fetch_assoc()['count'] : 0;

    // New applications this week
    $newAppsWeek = $conn->query("SELECT COUNT(*) as count FROM Applications WHERE status != 'Draft' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $newAppsWeekCount = $newAppsWeek ? $newAppsWeek->fetch_assoc()['count'] : 0;

    // Success rate calculation
    $approvedTotal = $conn->query("SELECT COUNT(*) as count FROM Applications WHERE status = 'Approved'");
    $approvedTotalCount = $approvedTotal ? $approvedTotal->fetch_assoc()['count'] : 0;

    $successRate = $totalApplications > 0 ? round(($approvedTotalCount / $totalApplications) * 100, 1) : 0;

    // Average processing time (in days)
    $avgProcessingTime = $conn->query("
        SELECT AVG(DATEDIFF(COALESCE(approval_date, rejection_date), submission_date)) as avg_days
        FROM Applications
        WHERE status IN ('Approved', 'Rejected') AND submission_date IS NOT NULL
    ");
    $avgDays = $avgProcessingTime ? $avgProcessingTime->fetch_assoc()['avg_days'] : 0;
    $avgProcessingDays = $avgDays ? round($avgDays, 1) : 0;

    echo json_encode([
        'success' => true,
        'statistics' => [
            'total_applications' => (int)$totalApplications,
            'new_applications_week' => (int)$newAppsWeekCount,
            'pending_applications' => (int)$pendingApplications,
            'approved_today' => (int)$approvedTodayCount,
            'reviewed_today' => (int)$reviewedTodayCount,
            'total_users' => (int)$totalUsersCount,
            'new_users_week' => (int)$newUsersWeekCount,
            'success_rate' => $successRate,
            'avg_processing_days' => $avgProcessingDays
        ]
    ]);
}

function getPendingApplications($conn, $data) {
    $limit = $data['limit'] ?? 50;
    $offset = $data['offset'] ?? 0;
    $service_filter = $data['service_filter'] ?? '';
    $status_filter = $data['status_filter'] ?? '';

    $whereClause = "WHERE a.status IN ('Submitted', 'Under Review')";

    if (!empty($service_filter)) {
        $whereClause .= " AND s.service_name = '" . $conn->real_escape_string($service_filter) . "'";
    }

    if (!empty($status_filter)) {
        $whereClause .= " AND a.status = '" . $conn->real_escape_string($status_filter) . "'";
    }

    $query = "
        SELECT
            a.app_id,
            a.app_reference,
            a.user_id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            s.service_name,
            a.status,
            a.submission_date,
            a.application_data
        FROM Applications a
        LEFT JOIN Users u ON a.user_id = u.user_id
        LEFT JOIN ServiceTypes s ON a.service_id = s.service_id
        $whereClause
        ORDER BY a.submission_date ASC
        LIMIT $limit OFFSET $offset
    ";

    $result = $conn->query($query);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
        return;
    }

    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $appData = json_decode($row['application_data'], true);

        $applications[] = [
            'app_id' => $row['app_id'],
            'app_reference' => $row['app_reference'],
            'applicant_name' => $row['first_name'] . ' ' . $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'service_name' => $row['service_name'] ?? 'Unknown Service',
            'status' => $row['status'],
            'submission_date' => $row['submission_date'],
            'documents_count' => isset($appData['documents']) ? count($appData['documents']) : 0
        ];
    }

    echo json_encode([
        'success' => true,
        'applications' => $applications,
        'count' => count($applications)
    ]);
}

function updateApplicationStatus($conn, $data, $newStatus) {
    $app_id = intval($data['app_id'] ?? 0);
    $admin_reason = trim($data['reason'] ?? '');

    if (!$app_id) {
        echo json_encode(['success' => false, 'message' => 'Application ID is required']);
        return;
    }

    // Get current application status
    $checkStmt = $conn->prepare("SELECT status FROM Applications WHERE app_id = ?");
    $checkStmt->bind_param('i', $app_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        return;
    }

    $currentStatus = $result->fetch_assoc()['status'];
    $checkStmt->close();

    // Prevent updating already processed applications
    if (in_array($currentStatus, ['Approved', 'Rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Application has already been processed']);
        return;
    }

    // Update application status
    $updateFields = "status = ?, updated_at = NOW()";
    $params = [$newStatus];
    $types = 's';

    if ($newStatus === 'Approved') {
        $updateFields .= ", approval_date = NOW()";
    } elseif ($newStatus === 'Rejected') {
        $updateFields .= ", rejection_date = NOW(), rejection_reason = ?";
        $params[] = $admin_reason;
        $types .= 's';
    }

    $stmt = $conn->prepare("UPDATE Applications SET $updateFields WHERE app_id = ?");
    $params[] = $app_id;
    $types .= 'i';

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => "Application $newStatus successfully",
            'app_id' => $app_id,
            'new_status' => $newStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update application: ' . $stmt->error]);
    }

    $stmt->close();
}

function getAllApplications($conn, $data) {
    $limit = $data['limit'] ?? 100;
    $offset = $data['offset'] ?? 0;
    $search = trim($data['search'] ?? '');
    $service_filter = $data['service_filter'] ?? '';
    $status_filter = $data['status_filter'] ?? '';

    $whereClause = "WHERE 1=1";

    if (!empty($search)) {
        $searchTerm = $conn->real_escape_string($search);
        $whereClause .= " AND (a.app_reference LIKE '%$searchTerm%' OR u.first_name LIKE '%$searchTerm%' OR u.last_name LIKE '%$searchTerm%' OR u.email LIKE '%$searchTerm%')";
    }

    if (!empty($service_filter)) {
        $whereClause .= " AND s.service_name = '" . $conn->real_escape_string($service_filter) . "'";
    }

    if (!empty($status_filter)) {
        $whereClause .= " AND a.status = '" . $conn->real_escape_string($status_filter) . "'";
    }

    $query = "
        SELECT
            a.app_id,
            a.app_reference,
            a.user_id,
            u.first_name,
            u.last_name,
            u.email,
            s.service_name,
            a.status,
            a.submission_date,
            a.approval_date,
            a.rejection_date,
            a.application_data
        FROM Applications a
        LEFT JOIN Users u ON a.user_id = u.user_id
        LEFT JOIN ServiceTypes s ON a.service_id = s.service_id
        $whereClause
        ORDER BY a.submission_date DESC
        LIMIT $limit OFFSET $offset
    ";

    $result = $conn->query($query);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
        return;
    }

    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $appData = json_decode($row['application_data'], true);

        $applications[] = [
            'app_id' => $row['app_id'],
            'app_reference' => $row['app_reference'],
            'applicant_name' => $row['first_name'] . ' ' . $row['last_name'],
            'email' => $row['email'],
            'service_name' => $row['service_name'] ?? 'Unknown Service',
            'status' => $row['status'],
            'submission_date' => $row['submission_date'],
            'approval_date' => $row['approval_date'],
            'rejection_date' => $row['rejection_date'],
            'documents_count' => isset($appData['documents']) ? count($appData['documents']) : 0
        ];
    }

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM Applications a LEFT JOIN Users u ON a.user_id = u.user_id LEFT JOIN ServiceTypes s ON a.service_id = s.service_id $whereClause";
    $countResult = $conn->query($countQuery);
    $totalCount = $countResult ? $countResult->fetch_assoc()['total'] : 0;

    echo json_encode([
        'success' => true,
        'applications' => $applications,
        'total_count' => (int)$totalCount,
        'count' => count($applications)
    ]);
}

function getUsersOverview($conn) {
    // Total users
    $totalUsers = $conn->query("SELECT COUNT(*) as count FROM Users");
    $totalUsersCount = $totalUsers ? $totalUsers->fetch_assoc()['count'] : 0;

    // Active users
    $activeUsers = $conn->query("SELECT COUNT(*) as count FROM Users WHERE account_status = 'Active'");
    $activeUsersCount = $activeUsers ? $activeUsers->fetch_assoc()['count'] : 0;

    // Admin users
    $adminUsers = $conn->query("SELECT COUNT(*) as count FROM Users WHERE role = 'Admin'");
    $adminUsersCount = $adminUsers ? $adminUsers->fetch_assoc()['count'] : 0;

    // Citizen users
    $citizenUsers = $conn->query("SELECT COUNT(*) as count FROM Users WHERE role = 'Citizen'");
    $citizenUsersCount = $citizenUsers ? $citizenUsers->fetch_assoc()['count'] : 0;

    // Recent registrations (last 30 days)
    $recentUsers = $conn->query("SELECT COUNT(*) as count FROM Users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $recentUsersCount = $recentUsers ? $recentUsers->fetch_assoc()['count'] : 0;

    echo json_encode([
        'success' => true,
        'users' => [
            'total_users' => (int)$totalUsersCount,
            'active_users' => (int)$activeUsersCount,
            'admin_users' => (int)$adminUsersCount,
            'citizen_users' => (int)$citizenUsersCount,
            'recent_registrations' => (int)$recentUsersCount
        ]
    ]);
}

$conn->close();
?>
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

    if ($action === 'get_notifications') {
        getNotifications($conn, $data);
    } elseif ($action === 'mark_as_read') {
        markNotificationAsRead($conn, $data);
    } elseif ($action === 'setup') {
        setupSampleNotifications($conn);
    } elseif ($action === 'add_notification') {
        addNotification($conn, $data);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function getNotifications($conn, $data) {
    $user_id = $data['user_id'] ?? null;
    $limit = $data['limit'] ?? 10;

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }

    // Check if Notifications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'Notifications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Notifications table does not exist'
        ]);
        return;
    }

    // Get notifications with application details
    $stmt = $conn->prepare("
        SELECT
            n.notif_id,
            n.notification_type,
            n.subject,
            n.message,
            n.is_read,
            n.created_at,
            a.app_reference,
            st.service_name
        FROM Notifications n
        LEFT JOIN Applications a ON n.app_id = a.app_id
        LEFT JOIN ServiceTypes st ON a.service_id = st.service_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ?
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }

    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'notif_id' => $row['notif_id'],
            'type' => $row['notification_type'],
            'subject' => $row['subject'],
            'message' => $row['message'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
            'app_reference' => $row['app_reference'],
            'service_name' => $row['service_name'],
            'icon' => getNotificationIcon($row['notification_type'])
        ];
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);

    $stmt->close();
}

function markNotificationAsRead($conn, $data) {
    $notif_id = $data['notif_id'] ?? null;

    if (!$notif_id) {
        echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE Notifications
        SET is_read = TRUE, read_at = NOW()
        WHERE notif_id = ?
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }

    $stmt->bind_param("i", $notif_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating notification: ' . $stmt->error]);
    }

    $stmt->close();
}

function setupSampleNotifications($conn) {
    // Check if Notifications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'Notifications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Notifications table does not exist. Run database setup first.'
        ]);
        return;
    }

    // Get first user ID (assuming at least one user exists)
    $userResult = $conn->query("SELECT user_id FROM Users LIMIT 1");
    if (!$userResult || $userResult->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No users found. Please register a user first.'
        ]);
        return;
    }

    $userId = $userResult->fetch_assoc()['user_id'];

    // Get first application ID for linking (optional)
    $appResult = $conn->query("SELECT app_id FROM Applications LIMIT 1");
    $appId = $appResult && $appResult->num_rows > 0 ? $appResult->fetch_assoc()['app_id'] : null;

    // Insert sample notifications
    $notifications = [
        [
            'user_id' => $userId,
            'app_id' => $appId,
            'type' => 'Application Status',
            'subject' => 'Application Received',
            'message' => 'Your Trading Permit application has been received and is under review.',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'user_id' => $userId,
            'app_id' => $appId,
            'type' => 'Success',
            'subject' => 'Application Approved',
            'message' => 'Congratulations! Your Building Permit has been approved.',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ],
        [
            'user_id' => $userId,
            'app_id' => $appId,
            'type' => 'Info',
            'subject' => 'Document Requested',
            'message' => 'We need additional documents for your Marriage Certificate application.',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ]
    ];

    $addedCount = 0;
    foreach ($notifications as $notif) {
        $query = sprintf(
            "INSERT INTO Notifications (user_id, app_id, notification_type, subject, message, created_at) VALUES (%d, %s, '%s', '%s', '%s', '%s')",
            $notif['user_id'],
            $notif['app_id'] ? $notif['app_id'] : 'NULL',
            $conn->real_escape_string($notif['type']),
            $conn->real_escape_string($notif['subject']),
            $conn->real_escape_string($notif['message']),
            $notif['timestamp']
        );

        if ($conn->query($query)) {
            $addedCount++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Sample notifications added',
        'count' => $addedCount,
        'user_id' => $userId
    ]);
}

function addNotification($conn, $data) {
    $user_id = $data['user_id'] ?? null;
    $app_id = $data['app_id'] ?? null;
    $type = $data['notification_type'] ?? 'Info';
    $subject = $data['subject'] ?? '';
    $message = $data['message'] ?? '';

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }

    if (!$subject || !$message) {
        echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
        return;
    }

    $query = sprintf(
        "INSERT INTO Notifications (user_id, app_id, notification_type, subject, message, created_at) VALUES (%d, %s, '%s', '%s', '%s', NOW())",
        $user_id,
        $app_id ? $app_id : 'NULL',
        $conn->real_escape_string($type),
        $conn->real_escape_string($subject),
        $conn->real_escape_string($message)
    );

    if ($conn->query($query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification added successfully',
            'notif_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error adding notification: ' . $conn->error
        ]);
    }
}

function getNotificationIcon($type) {
    $icons = [
        'Info' => 'ℹ️',
        'Success' => '✅',
        'Warning' => '⚠️',
        'Error' => '❌',
        'Application Status' => '📋'
    ];

    return $icons[$type] ?? '🔔';
}

$conn->close();
?>
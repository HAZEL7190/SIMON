<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Upload directory configuration
$uploadDir = dirname(__FILE__) . '/uploads';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

// Create uploads directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
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

// Handle file downloads for documents
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download_document') {
    downloadDocument($conn, $_GET, $uploadDir);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine action: support both JSON payloads and multipart form data
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    $action = $json['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'submit_application':
            submitApplication($conn, $_POST, $_FILES, $uploadDir, $maxFileSize, $allowedTypes, $allowedExtensions);
            break;
        case 'get_applications':
            // use JSON or POST parameters
            $data = $json ?? $_POST;
            getApplications($conn, $data);
            break;
        case 'download_documents':
            downloadDocuments($conn, $json, $uploadDir);
            break;
        case 'send_feedback':
            sendFeedback($conn, $json);
            break;
        case 'setup_sample_applications':
            setupSampleApplications($conn);
            break;
        case 'clear_applications':
            clearApplications($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

function submitApplication($conn, $data, $files, $uploadDir, $maxFileSize, $allowedTypes, $allowedExtensions) {
    // Validate required fields
    $user_id = $data['user_id'] ?? null;
    $service_id = $data['service_id'] ?? null;
    $fullName = $data['fullName'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';
    $nrc = $data['nrc'] ?? '';
    $description = $data['description'] ?? '';
    $location = $data['location'] ?? '';
    $requestedDate = $data['requestedDate'] ?? '';

    if (!$user_id || !$service_id || !$fullName || !$email || !$phone || !$nrc) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    // Validate files are present
    if (empty($files['documents']) || empty($files['documents']['name'][0])) {
        echo json_encode(['success' => false, 'message' => 'At least one document is required']);
        return;
    }

    // Check if Applications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'Applications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Applications table does not exist. Please run setup.']);
        return;
    }

    // Generate application reference
    $app_reference = 'APP-' . date('Y') . '-' . substr(uniqid(), -6);
    $uploadedFiles = [];
    $fileErrors = [];

    // Process uploaded files
    if (isset($files['documents'])) {
        $fileCount = count($files['documents']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $files['documents']['name'][$i];
            $fileTmp = $files['documents']['tmp_name'][$i];
            $fileSize = $files['documents']['size'][$i];
            $fileError = $files['documents']['error'][$i];

            // Skip empty files
            if ($fileError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            // Check for upload errors
            if ($fileError !== UPLOAD_ERR_OK) {
                $fileErrors[] = "Error uploading $fileName";
                continue;
            }

            // Validate file size
            if ($fileSize > $maxFileSize) {
                $fileErrors[] = "$fileName exceeds 5MB size limit";
                continue;
            }

            // Get file extension
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Validate file extension
            if (!in_array($fileExt, $allowedExtensions)) {
                $fileErrors[] = "$fileName has unsupported format";
                continue;
            }

            // Generate unique filename to prevent conflicts
            $uniqueFileName = $app_reference . '_' . time() . '_' . basename($fileName);
            $uploadPath = $uploadDir . '/' . $uniqueFileName;

            // Move uploaded file
            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $uploadedFiles[] = [
                    'original_name' => $fileName,
                    'stored_name' => $uniqueFileName,
                    'size' => $fileSize,
                    'type' => $fileExt,
                    'upload_date' => date('Y-m-d H:i:s')
                ];
            } else {
                $fileErrors[] = "Failed to save $fileName";
            }
        }
    }

    // Check if at least one file was uploaded successfully
    if (empty($uploadedFiles)) {
        echo json_encode([
            'success' => false,
            'message' => 'No valid files were uploaded. ' . implode('; ', $fileErrors)
        ]);
        return;
    }

    // Prepare application data as JSON (including file references)
    $application_data = json_encode([
        'fullName' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'nrc' => $nrc,
        'description' => $description,
        'location' => $location,
        'requestedDate' => $requestedDate,
        'submittedDate' => date('Y-m-d H:i:s'),
        'documents' => $uploadedFiles
    ]);

    // Insert into database
    $stmt = $conn->prepare("
        INSERT INTO Applications 
        (app_reference, user_id, service_id, status, application_data, submission_date) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }

    $status = 'Submitted';
    $stmt->bind_param("siiss", $app_reference, $user_id, $service_id, $status, $application_data);

    if ($stmt->execute()) {
        $successMessage = 'Application submitted successfully with ' . count($uploadedFiles) . ' document(s)';
        if (!empty($fileErrors)) {
            $successMessage .= '. Note: ' . implode('; ', $fileErrors);
        }

        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'app_reference' => $app_reference,
            'app_id' => $stmt->insert_id,
            'files_uploaded' => count($uploadedFiles),
            'file_errors' => $fileErrors
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error submitting application: ' . $stmt->error]);
    }

    $stmt->close();
}

// -----------------------------------------------------------------------------
// Additional utility endpoints previously located in applications.php
// -----------------------------------------------------------------------------

function getApplications($conn, $data) {
    $user_id = intval($data['user_id'] ?? 0);
    $limit = intval($data['limit'] ?? 10);

    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }

    $query = "
        SELECT
            a.app_id,
            a.app_reference,
            s.service_name,
            a.status,
            a.submission_date,
            a.approval_date,
            a.rejection_date
        FROM Applications a
        LEFT JOIN ServiceTypes s ON a.service_id = s.service_id
        WHERE a.user_id = ?
        ORDER BY a.submission_date DESC
        LIMIT ?
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $applications = [];

    while ($row = $result->fetch_assoc()) {
        $submissionDate = date('Y-m-d', strtotime($row['submission_date']));
        $action_label = 'View';
        if ($row['status'] === 'Approved') {
            $action_label = 'Download';
        } elseif ($row['status'] === 'Rejected') {
            $action_label = 'View';
        }

        $applications[] = [
            'app_id' => $row['app_id'],
            'app_reference' => $row['app_reference'],
            'service_name' => $row['service_name'] ?? 'Unknown Service',
            'status' => $row['status'],
            'submission_date' => $submissionDate,
            'action_label' => $action_label
        ];
    }

    echo json_encode([
        'success' => true,
        'applications' => $applications,
        'count' => count($applications)
    ]);
    $stmt->close();
}

function setupSampleApplications($conn) {
    // Get first user for testing
    $userQuery = "SELECT user_id FROM Users LIMIT 1";
    $userResult = $conn->query($userQuery);

    if (!$userResult || $userResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No users found. Please register a user first.']);
        exit;
    }

    $user = $userResult->fetch_assoc();
    $userId = $user['user_id'];

    // Get service types
    $serviceQuery = "SELECT service_id, service_name FROM ServiceTypes LIMIT 5";
    $serviceResult = $conn->query($serviceQuery);

    if (!$serviceResult || $serviceResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No service types found. Please set up services first.']);
        exit;
    }

    $services = [];
    while ($service = $serviceResult->fetch_assoc()) {
        $services[] = $service;
    }

    // Sample applications data
    $applications = [
        [
            'service_id' => $services[0]['service_id'] ?? 1,
            'status' => 'Pending',
            'submission_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'application_data' => '{"purpose": "Business expansion", "location": "Downtown"}'
        ],
        [
            'service_id' => $services[1]['service_id'] ?? 1,
            'status' => 'Approved',
            'submission_date' => date('Y-m-d H:i:s', strtotime('-7 days')),
            'approval_date' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'application_data' => '{"property_address": "123 Main St", "property_type": "Residential"}'
        ],
        [
            'service_id' => $services[2]['service_id'] ?? 1,
            'status' => 'Pending',
            'submission_date' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'application_data' => '{"event_name": "Community Festival", "expected_attendees": 500}'
        ],
        [
            'service_id' => $services[3]['service_id'] ?? 1,
            'status' => 'Approved',
            'submission_date' => date('Y-m-d H:i:s', strtotime('-15 days')),
            'approval_date' => date('Y-m-d H:i:s', strtotime('-12 days')),
            'application_data' => '{"tax_year": "2025", "property_value": 250000}'
        ],
        [
            'service_id' => $services[4]['service_id'] ?? 1,
            'status' => 'Rejected',
            'submission_date' => date('Y-m-d H:i:s', strtotime('-20 days')),
            'rejection_date' => date('Y-m-d H:i:s', strtotime('-18 days')),
            'rejection_reason' => 'Incomplete documentation',
            'application_data' => '{"certificate_type": "Birth Certificate", "urgency": "Normal"}'
        ]
    ];

    $inserted = 0;
    foreach ($applications as $app) {
        // Generate unique reference
        $reference = 'APP-' . date('Y') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

        $query = "INSERT INTO Applications (app_reference, user_id, service_id, status, application_data, submission_date, approval_date, rejection_date, rejection_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);

        if ($stmt) {
            $stmt->bind_param('siissssss',
                $reference,
                $userId,
                $app['service_id'],
                $app['status'],
                $app['application_data'],
                $app['submission_date'],
                $app['approval_date'] ?? null,
                $app['rejection_date'] ?? null,
                $app['rejection_reason'] ?? null
            );

            if ($stmt->execute()) {
                $inserted++;
            }
            $stmt->close();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Successfully inserted $inserted sample applications for user ID $userId",
        'user_id' => $userId
    ]);
}

function clearApplications($conn) {
    $query = "DELETE FROM Applications WHERE app_reference LIKE 'APP-%'";
    if ($conn->query($query)) {
        echo json_encode(['success' => true, 'message' => 'Sample applications cleared']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to clear applications']);
    }
}

// Download a single document file
function downloadDocument($conn, $params, $uploadDir) {
    $user_id = intval($params['user_id'] ?? 0);
    $app_id = intval($params['app_id'] ?? 0);
    $filename = $params['filename'] ?? '';

    if (!$user_id || !$app_id || !$filename) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }

    // Verify user owns this application
    $stmt = $conn->prepare("SELECT application_data FROM Applications WHERE app_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $app_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $row = $result->fetch_assoc();
    $appData = json_decode($row['application_data'], true);
    
    // Prevent directory traversal attacks
    $filename = basename($filename);
    $filePath = $uploadDir . '/' . $filename;

    // Verify file exists and is in the uploads directory
    if (!file_exists($filePath) || strpos(realpath($filePath), realpath($uploadDir)) !== 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }

    // Send file for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// Get all documents for an application
function downloadDocuments($conn, $data, $uploadDir) {
    $user_id = intval($data['user_id'] ?? 0);
    $app_id = intval($data['app_id'] ?? 0);

    if (!$user_id || !$app_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing user_id or app_id']);
        exit;
    }

    // Verify user owns this application
    $stmt = $conn->prepare("SELECT app_reference, application_data FROM Applications WHERE app_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $app_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized or application not found']);
        exit;
    }

    $row = $result->fetch_assoc();
    $appData = json_decode($row['application_data'], true);
    
    // Get documents from application data
    $documents = $appData['documents'] ?? [];

    if (empty($documents)) {
        echo json_encode([
            'success' => false,
            'message' => 'No documents found for this application'
        ]);
        exit;
    }

    // Return list of documents with download URLs
    $documentList = [];
    foreach ($documents as $doc) {
        $documentList[] = [
            'original_name' => $doc['original_name'] ?? 'Unknown',
            'stored_name' => $doc['stored_name'] ?? '',
            'size' => $doc['size'] ?? 0,
            'type' => $doc['type'] ?? '',
            'upload_date' => $doc['upload_date'] ?? '',
            'download_url' => 'application_api.php?action=download_document&user_id=' . $user_id . '&app_id=' . $app_id . '&filename=' . urlencode($doc['stored_name'] ?? '')
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Documents retrieved successfully',
        'app_reference' => $row['app_reference'],
        'documents' => $documentList,
        'count' => count($documentList)
    ]);
}

// Send user feedback
function sendFeedback($conn, $data) {
    // Validate required fields
    $user_id = intval($data['user_id'] ?? 0);
    $feedback_text = trim($data['feedback'] ?? '');
    $rating = intval($data['rating'] ?? 0);
    $app_id = intval($data['app_id'] ?? 0);

    if (!$user_id || empty($feedback_text)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: user_id and feedback']);
        exit;
    }

    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
        exit;
    }

    // Create Feedback table if it doesn't exist
    $createTableQuery = "CREATE TABLE IF NOT EXISTS Feedback (
        feedback_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        app_id INT,
        feedback_text TEXT NOT NULL,
        rating INT NOT NULL,
        feedback_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('New', 'Read', 'Responded') DEFAULT 'New',
        response TEXT,
        response_date TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (app_id) REFERENCES Applications(app_id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_feedback_date (feedback_date)
    )";

    if (!$conn->query($createTableQuery)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create feedback table']);
        exit;
    }

    // Insert feedback
    $stmt = $conn->prepare("INSERT INTO Feedback (user_id, app_id, feedback_text, rating, feedback_date) VALUES (?, ?, ?, ?, NOW())");
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    // Use NULL for app_id if not provided
    $app_id_param = ($app_id > 0) ? $app_id : NULL;
    
    $stmt->bind_param('iisi', $user_id, $app_id_param, $feedback_text, $rating);

    if ($stmt->execute()) {
        $feedback_id = $stmt->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Feedback submitted successfully. Thank you for your input!',
            'feedback_id' => $feedback_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to submit feedback: ' . $stmt->error]);
    }

    $stmt->close();
}

$conn->close();
?>
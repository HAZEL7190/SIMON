<?php
// Complete database setup with all tables

echo "<h1>E-Governance Database Setup</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; }</style>";

$servername = "localhost";
$username = "root";
$password = "";

// Create connection
$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die("<p class='error'>Connection failed: " . $conn->connect_error . "</p>");
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS e_governance";
if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Database created or already exists</p>";
} else {
    die("<p class='error'>Error creating database: " . $conn->error . "</p>");
}

// Select database
$conn->select_db("e_governance");

// Create Users table
echo "<h2>Creating Tables</h2>";

$usersTable = "CREATE TABLE IF NOT EXISTS Users (
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

if ($conn->query($usersTable) === TRUE) {
    echo "<p class='success'>✅ Users table created</p>";
} else {
    echo "<p class='error'>❌ Error creating Users table: " . $conn->error . "</p>";
}

// Create ServiceTypes table
$serviceTypesTable = "CREATE TABLE IF NOT EXISTS ServiceTypes (
    service_id INT PRIMARY KEY AUTO_INCREMENT,
    service_name VARCHAR(255) NOT NULL,
    service_code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    requirements TEXT NOT NULL,
    processing_days INT DEFAULT 5,
    fee DECIMAL(10, 2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_service_name (service_name),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (created_by) REFERENCES Users(user_id) ON DELETE SET NULL
)";

if ($conn->query($serviceTypesTable) === TRUE) {
    echo "<p class='success'>✅ ServiceTypes table created</p>";
} else {
    echo "<p class='error'>❌ Error creating ServiceTypes table: " . $conn->error . "</p>";
}

// Create Applications table
$applicationsTable = "CREATE TABLE IF NOT EXISTS Applications (
    app_id INT PRIMARY KEY AUTO_INCREMENT,
    app_reference VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    status ENUM('Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Withdrawn') DEFAULT 'Draft',
    application_data JSON,
    submission_date TIMESTAMP,
    approval_date TIMESTAMP NULL,
    rejection_date TIMESTAMP NULL,
    rejection_reason TEXT,
    notes TEXT,
    assigned_to INT,
    priority ENUM('Low', 'Normal', 'High', 'Urgent') DEFAULT 'Normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES ServiceTypes(service_id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_to) REFERENCES Users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_service_id (service_id),
    INDEX idx_status (status),
    INDEX idx_submission_date (submission_date),
    INDEX idx_app_reference (app_reference)
)";

if ($conn->query($applicationsTable) === TRUE) {
    echo "<p class='success'>✅ Applications table created</p>";
} else {
    echo "<p class='error'>❌ Error creating Applications table: " . $conn->error . "</p>";
}

// Insert sample services if they don't exist
echo "<h2>Inserting Sample Data</h2>";

$services = [
    ['Trading Permit', 'TRADE-001', 'Permit for trading activities', 250],
    ['Building Permit', 'BUILD-001', 'Permit for building construction', 500],
    ['Event Permit', 'EVENT-001', 'Permit for organizing events', 150],
    ['Marriage Certificate', 'MARR-001', 'Certificate for marriage registration', 50],
    ['Business Registration', 'BUS-001', 'Registration of new business', 100]
];

foreach ($services as $service) {
    $stmt = $conn->prepare("INSERT IGNORE INTO ServiceTypes (service_name, service_code, description, fee) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $service[0], $service[1], $service[2], $service[3]);
    if ($stmt->execute()) {
        echo "<p class='success'>✅ Service '{$service[0]}' created</p>";
    } else {
        echo "<p class='error'>❌ Error creating service: " . $stmt->error . "</p>";
    }
}

// Create or update admin user
$adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
$adminEmail = 'admin@e-governance.gov';

// Check if admin exists
$checkAdmin = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
$checkAdmin->bind_param("s", $adminEmail);
$checkAdmin->execute();
$result = $checkAdmin->get_result();

if ($result->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO Users (first_name, last_name, email, password_hash, phone, national_id, role, district, province, email_verified, account_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $firstName = 'Admin';
    $lastName = 'User';
    $phone = '+260123456789';
    $nationalId = 'ADM001';
    $role = 'Admin';
    $district = 'Lusaka';
    $province = 'Lusaka';
    $emailVerified = 1;
    $status = 'Active';
    
    $stmt->bind_param("sssssssssii", $firstName, $lastName, $adminEmail, $adminPassword, $phone, $nationalId, $role, $district, $province, $emailVerified, $status);
    
    if ($stmt->execute()) {
        echo "<p class='success'>✅ Admin user created (admin@e-governance.gov / admin123)</p>";
    } else {
        echo "<p class='error'>❌ Error creating admin: " . $stmt->error . "</p>";
    }
}

$conn->close();

echo "<br><hr>";
echo "<h2>Setup Complete!</h2>";
echo "<p>Your database is now ready to use.</p>";
echo "<br><a href='register.html'>Register New User</a> | <a href='index.html'>Login</a>";
?>
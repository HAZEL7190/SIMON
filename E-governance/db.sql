-- TABLE: Users
-- Description: Stores citizen and council officer accounts
CREATE TABLE Users (
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
);

-- TABLE: ServiceTypes
-- Description: Categories of services offered by council

CREATE TABLE ServiceTypes (
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
);

-- TABLE: Applications
-- Description: Core table for service applications submitted by users

CREATE TABLE Applications (
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
);


-- TABLE: ApplicationHistory
-- Description: Tracks all status changes and updates to applications

CREATE TABLE ApplicationHistory (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    app_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    changed_by INT,
    change_reason TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES Applications(app_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES Users(user_id) ON DELETE SET NULL,
    INDEX idx_app_id (app_id),
    INDEX idx_changed_at (changed_at)
);

-- TABLE: Documents
-- Description: Stores files and certificates associated with applications

CREATE TABLE Documents (
    doc_id INT PRIMARY KEY AUTO_INCREMENT,
    app_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(50),
    is_approved BOOLEAN DEFAULT FALSE,
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE NULL,
    INDEX idx_app_id (app_id),
    INDEX idx_document_type (document_type),
    FOREIGN KEY (app_id) REFERENCES Applications(app_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- TABLE: Notifications
-- Description: Stores system notifications for users

CREATE TABLE Notifications (
    notif_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    app_id INT,
    notification_type ENUM('Info', 'Success', 'Warning', 'Error', 'Application Status') DEFAULT 'Info',
    subject VARCHAR(255),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    delivery_method ENUM('Email', 'SMS', 'In-App') DEFAULT 'In-App',
    delivery_status ENUM('Pending', 'Sent', 'Failed') DEFAULT 'Pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES Applications(app_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_delivery_status (delivery_status)
);

-- TABLE: PasswordResets
-- Description: Manages password reset tokens and requests

CREATE TABLE PasswordResets (
    reset_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reset_token VARCHAR(255) UNIQUE NOT NULL,
    token_expiry TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    INDEX idx_reset_token (reset_token),
    INDEX idx_user_id (user_id)
);


-- TABLE: UserSessions
-- Description: Tracks active user sessions

CREATE TABLE UserSessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_is_active (is_active)
);

-- TABLE: AuditLog
-- Description: Logs all admin actions for security and compliance

CREATE TABLE AuditLog (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
);

-- TABLE: SystemSettings
-- Description: Configuration settings for the system

CREATE TABLE SystemSettings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50),
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES Users(user_id) ON DELETE SET NULL,
    INDEX idx_setting_key (setting_key)
);

-- VIEWS
-- View: User Application Summary

CREATE OR REPLACE VIEW user_application_summary AS
SELECT 
    u.user_id,
    CONCAT(u.first_name, ' ', u.last_name) AS full_name,
    u.email,
    st.service_name,
    a.app_id,
    a.app_reference,
    a.status,
    a.submission_date,
    DATEDIFF(NOW(), a.submission_date) AS days_pending
FROM Users u
LEFT JOIN Applications a ON u.user_id = a.user_id
LEFT JOIN ServiceTypes st ON a.service_id = st.service_id
ORDER BY a.submission_date DESC;

-- View: Pending Applications
CREATE OR REPLACE VIEW pending_applications AS
SELECT 
    a.app_id,
    a.app_reference,
    u.user_id,
    CONCAT(u.first_name, ' ', u.last_name) AS full_name,
    u.email,
    st.service_name,
    a.submission_date,
    DATEDIFF(NOW(), a.submission_date) AS days_pending,
    a.priority
FROM Applications a
JOIN Users u ON a.user_id = u.user_id
JOIN ServiceTypes st ON a.service_id = st.service_id
WHERE a.status IN ('Submitted', 'Under Review')
ORDER BY a.priority DESC, a.submission_date ASC;

-- View: Application Statistics
CREATE OR REPLACE VIEW application_statistics AS
SELECT 
    st.service_id,
    st.service_name,
    COUNT(a.app_id) AS total_applications,
    SUM(CASE WHEN a.status = 'Approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN a.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected,
    SUM(CASE WHEN a.status IN ('Submitted', 'Under Review') THEN 1 ELSE 0 END) AS pending,
    ROUND(AVG(DATEDIFF(IFNULL(a.approval_date, NOW()), a.submission_date)), 2) AS avg_processing_days
FROM ServiceTypes st
LEFT JOIN Applications a ON st.service_id = a.service_id
GROUP BY st.service_id, st.service_name;

-- SAMPLE DATA
-- Insert Sample Service Types

INSERT INTO ServiceTypes (service_name, service_code, description, requirements, processing_days, fee) VALUES
('Trading Permit', 'TRADE-001', 'License to conduct business activities in the council jurisdiction', 'Business registration certificate, identification, shop location proof', 5, 250.00),
('Building Permit', 'BUILD-001', 'Permission to construct residential or commercial buildings', 'Plot document, architectural plan, environmental clearance', 7, 500.00),
('Event Permit', 'EVENT-001', 'Authorization for organizing public events or gatherings', 'Event details, venue information, security plan', 3, 150.00),
('Marriage Certificate', 'MARR-001', 'Official certificate of marriage from council records', 'Birth certificate, passport, identification documents', 2, 50.00),
('Business Registration', 'REG-001', 'Registration of new business entity with the council', 'Completed application form, identification, business plan', 4, 100.00);

-- Insert Sample Users (Demo Accounts)
INSERT INTO Users (first_name, last_name, email, password_hash, phone, national_id, role, district, province, email_verified, phone_verified) VALUES
('John', 'Doe', 'citizen@example.com', 'hash_1234567890', '+260123456789', '111222333444', 'Citizen', 'Lusaka', 'Lusaka', TRUE, TRUE),
('Mary', 'Smith', 'citizen2@example.com', 'hash_0987654321', '+260987654321', '555666777888', 'Citizen', 'Livingstone', 'Southern', TRUE, TRUE),
('Admin', 'Officer', 'admin@council.gov', 'hash_admin12345', '+260755555555', '999888777666', 'Admin', 'Lusaka', 'Lusaka', TRUE, TRUE);

-- Insert Sample Applications
INSERT INTO Applications (app_reference, user_id, service_id, status, submission_date, priority, assigned_to) VALUES
('APP-2026-001', 1, 1, 'Submitted', NOW() - INTERVAL 3 DAY, 'Normal', 3),
('APP-2026-002', 1, 3, 'Under Review', NOW() - INTERVAL 1 DAY, 'High', 3),
('APP-2026-003', 2, 2, 'Approved', NOW() - INTERVAL 5 DAY, 'Normal', 3),
('APP-2026-004', 2, 4, 'Under Review', NOW() - INTERVAL 2 DAY, 'Normal', 3);

-- Insert Sample Notifications
INSERT INTO Notifications (user_id, app_id, notification_type, subject, message, is_read) VALUES
(1, 1, 'Application Status', 'Application Received', 'Your Trading Permit application (APP-2026-001) has been received and is under review.', FALSE),
(1, 2, 'Application Status', 'Application Status Update', 'Your Event Permit application (APP-2026-002) is currently being reviewed.', TRUE),
(2, 3, 'Success', 'Application Approved', 'Congratulations! Your Building Permit application has been approved.', TRUE);

-- Insert System Settings
INSERT INTO SystemSettings (setting_key, setting_value, setting_type, description) VALUES
('app_name', 'E-Governance Council System', 'string', 'Application name'),
('app_version', '1.0.0', 'string', 'Application version'),
('max_file_size', '5242880', 'integer', 'Maximum file upload size in bytes (5MB)'),
('session_timeout', '3600', 'integer', 'Session timeout in seconds'),
('password_expiry_days', '90', 'integer', 'Days before password expiry'),
('email_notifications_enabled', 'true', 'boolean', 'Enable email notifications');

-- INDEXES FOR PERFORMANCE
-- Additional indexes for frequently queried columns

CREATE INDEX idx_applications_user_service ON Applications(user_id, service_id);
CREATE INDEX idx_notifications_user_read ON Notifications(user_id, is_read);
CREATE INDEX idx_documents_app_type ON Documents(app_id, document_type);

-- END OF DATABASE

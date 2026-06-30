-- =============================================
-- DATABASE: ict_asset_db
-- =============================================

CREATE DATABASE IF NOT EXISTS ict_asset_db;
USE ict_asset_db;

-- =============================================
-- TABLE: users
-- =============================================

CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('System Administrator', 'ICT Technician', 'Staff') NOT NULL,
    department VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- SAMPLE DATA (with first_name and last_name)
-- =============================================

-- Password: Admin@123 (hashed: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
INSERT INTO users (first_name, last_name, email, phone, password, role, department) VALUES
('John', 'Mushi', 'admin@ict.ifm.ac.tz', '0712345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'ICT Department'),
('David', 'Kato', 'tech1@ict.ifm.ac.tz', '0723456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ICT Technician', 'ICT Department'),
('Sarah', 'Mrema', 'staff1@ifm.ac.tz', '0734567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Finance Department');

-- =============================================
-- TABLE: assets
-- =============================================
CREATE TABLE assets (
    asset_id INT PRIMARY KEY AUTO_INCREMENT,
    asset_tag VARCHAR(50) UNIQUE NOT NULL,
    qr_code VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    model VARCHAR(50),
    serial_number VARCHAR(50),
    location VARCHAR(100),
    purchase_date DATE,
    warranty_expiry DATE,
    status ENUM('Available', 'In Use', 'Under Maintenance', 'Retired') DEFAULT 'Available',
    assigned_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL
);

-- =============================================
-- TABLE: maintenance_requests
-- =============================================
CREATE TABLE maintenance_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    asset_id INT NOT NULL,
    reported_by INT NOT NULL,
    assigned_to INT NULL,
    issue_description TEXT NOT NULL,
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    status ENUM('Pending', 'Assigned', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Pending',
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    resolution_notes TEXT,
    FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL
);

-- =============================================
-- TABLE: maintenance_tasks
-- =============================================
CREATE TABLE maintenance_tasks (
    task_id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    technician_id INT NOT NULL,
    task_description TEXT NOT NULL,
    status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- =============================================
-- TABLE: notifications
-- =============================================
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('Info', 'Warning', 'Success', 'Error') DEFAULT 'Info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- =============================================
-- TABLE: maintenance_analytics
-- =============================================
CREATE TABLE maintenance_analytics (
    analytics_id INT PRIMARY KEY AUTO_INCREMENT,
    asset_id INT NOT NULL,
    total_repairs INT DEFAULT 0,
    avg_repair_time DECIMAL(5,2) DEFAULT 0,
    total_cost DECIMAL(10,2) DEFAULT 0,
    last_maintenance DATE NULL,
    next_maintenance DATE NULL,
    reliability_score DECIMAL(3,2) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE CASCADE
);



INSERT INTO assets (asset_tag, qr_code, name, category, model, serial_number, location, status) VALUES
('ASSET-001', 'QR-001', 'Dell Latitude 7420', 'Laptop', 'Latitude 7420', 'SN-001', 'ICT Office', 'Available'),
('ASSET-002', 'QR-002', 'HP LaserJet Pro', 'Printer', 'MFP M428fdw', 'SN-002', 'Admin Office', 'In Use'),
('ASSET-003', 'QR-003', 'Cisco Switch 2960', 'Network', '2960-24TT', 'SN-003', 'Server Room', 'Available');

-- Add status column to users table
ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active';

-- Add profile_picture column to users table
ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL;

-- =============================================
-- TABLE: user_settings (for storing user preferences)
-- =============================================
CREATE TABLE IF NOT EXISTS user_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    header_color VARCHAR(20) DEFAULT '#0d47a1',
    sidebar_color VARCHAR(20) DEFAULT '#0d47a1',
    background_color VARCHAR(20) DEFAULT '#f8f9fa',
    font_size VARCHAR(10) DEFAULT '14px',
    sidebar_collapsed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id)
);

-- Insert default settings for existing users
INSERT INTO user_settings (user_id, header_color, sidebar_color, background_color, font_size, sidebar_collapsed)
SELECT user_id, '#0d47a1', '#0d47a1', '#f8f9fa', '14px', 0 FROM users
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
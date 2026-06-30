-- =============================================
-- DATABASE: ict_asset_db
-- Smart ICT Asset Maintenance and Fault Detection System
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- =============================================
-- CREATE DATABASE
-- =============================================
CREATE DATABASE IF NOT EXISTS `ict_asset_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `ict_asset_db`;

-- =============================================
-- TABLE: users
-- =============================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('System Administrator','ICT Technician','Staff') NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
-- Password: Admin@123 (hashed)
--
INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `role`, `department`, `profile_picture`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Tzone', 'Mushi', 'admin@ict', '0712345678', '$2y$10$nss2PAKVMktnlFNj0wm94exVgdlEGHVnEzcHIJcN/5V0KYTvXPc2y', 'System Administrator', 'ICT Department', 'user_1_1782807552.jpg', 'active', '2026-06-30 05:54:30', '2026-06-30 08:26:32'),
(2, 'David', 'Kato', 'tech1@ict', '0723456789', '$2y$10$hmIPLO8FW4/ZrBMQnwTaV.5m/ccV.hfW05W/6qbDoVV0wMwoxuzc.', 'ICT Technician', 'ICT Department', NULL, 'active', '2026-06-30 05:54:30', '2026-06-30 08:28:52'),
(3, 'Sarah', 'Mrema', 'staff1@ict', '0734567890', '$2y$10$/dNQMHf/AQB5NjDjQCFLsuc25j5xEchp.VGmCd03rCGSEXB0uts4e', 'Staff', 'Finance Department', NULL, 'active', '2026-06-30 05:54:30', '2026-06-30 08:40:22'),
(4, 'Thazan', 'Jumanne', 'tz@gmail.com', '0712345679', '$2y$10$GEdF5r6XTo/Wl2Q3By2/zOh22ZeuYlGCE8UW8QiBs0aSO44Srfuo6', 'Staff', 'ICT Department', NULL, 'active', '2026-06-30 06:12:36', '2026-06-30 07:10:41');

-- =============================================
-- TABLE: user_settings
-- =============================================
DROP TABLE IF EXISTS `user_settings`;
CREATE TABLE `user_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `header_color` varchar(20) DEFAULT '#0d47a1',
  `sidebar_color` varchar(20) DEFAULT '#0d47a1',
  `background_color` varchar(20) DEFAULT '#f8f9fa',
  `font_size` varchar(10) DEFAULT '14px',
  `sidebar_collapsed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `uk_user_settings_user_id` (`user_id`),
  KEY `fk_user_settings_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--
INSERT INTO `user_settings` (`setting_id`, `user_id`, `header_color`, `sidebar_color`, `background_color`, `font_size`, `sidebar_collapsed`, `created_at`, `updated_at`) VALUES
(1, 1, '#0d47a1', '#0d47a1', '#f8f9fa', '14px', 0, '2026-06-30 06:28:50', '2026-06-30 11:09:22'),
(2, 2, '#0d47a1', '#0d47a1', '#f8f9fa', '14px', 0, '2026-06-30 06:28:50', '2026-06-30 06:28:50'),
(3, 3, '#0d47a1', '#0d47a1', '#f8f9fa', '14px', 0, '2026-06-30 06:28:50', '2026-06-30 08:41:53'),
(4, 4, '#0d47a1', '#0d47a1', '#f8f9fa', '14px', 0, '2026-06-30 06:28:50', '2026-06-30 06:28:50');

-- =============================================
-- TABLE: assets
-- =============================================
DROP TABLE IF EXISTS `assets`;
CREATE TABLE `assets` (
  `asset_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_tag` varchar(50) NOT NULL,
  `qr_code` varchar(100) NOT NULL,
  `qr_image` varchar(255) DEFAULT NULL COMMENT 'Path to QR code image',
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `model` varchar(50) DEFAULT NULL,
  `serial_number` varchar(50) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `status` enum('Available','In Use','Under Maintenance','Retired') DEFAULT 'Available',
  `assigned_to` int(11) DEFAULT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`asset_id`),
  UNIQUE KEY `uk_assets_asset_tag` (`asset_tag`),
  UNIQUE KEY `uk_assets_qr_code` (`qr_code`),
  KEY `fk_assets_assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assets`
--
INSERT INTO `assets` (`asset_id`, `asset_tag`, `qr_code`, `qr_image`, `name`, `category`, `model`, `serial_number`, `location`, `purchase_date`, `warranty_expiry`, `status`, `assigned_to`, `last_maintenance_date`, `next_maintenance_date`, `created_at`, `updated_at`) VALUES
(1, 'ICT-0001', 'QR-ICT-0001', 'qr_codes/ICT-0001.png', 'Dell Latitude 7420', 'Laptop', 'Latitude 7420', 'SN-001', 'ICT Office', '2025-01-15', '2027-01-15', 'Available', NULL, '2026-01-15', '2026-07-15', '2026-06-30 05:54:30', '2026-06-30 05:54:30'),
(2, 'ICT-0002', 'QR-ICT-0002', 'qr_codes/ICT-0002.png', 'HP LaserJet Pro MFP M428fdw', 'Printer', 'MFP M428fdw', 'SN-002', 'Admin Office', '2025-03-10', '2027-03-10', 'In Use', 2, '2026-03-10', '2026-09-10', '2026-06-30 05:54:30', '2026-06-30 05:54:30'),
(3, 'ICT-0003', 'QR-ICT-0003', 'qr_codes/ICT-0003.png', 'Cisco Switch 2960-24TT', 'Network', '2960-24TT', 'SN-003', 'Server Room', '2025-06-20', '2027-06-20', 'Available', NULL, '2026-01-20', '2026-07-20', '2026-06-30 05:54:30', '2026-06-30 05:54:30'),
(4, 'ICT-0004', 'QR-ICT-0004', 'qr_codes/ICT-0004.png', 'Dell OptiPlex 7080', 'Desktop', 'OptiPlex 7080', 'SN-004', 'Finance Office', '2025-02-01', '2027-02-01', 'In Use', 3, '2026-02-01', '2026-08-01', '2026-06-30 06:15:00', '2026-06-30 06:15:00'),
(5, 'ICT-0005', 'QR-ICT-0005', 'qr_codes/ICT-0005.png', 'Epson EB-2250U Projector', 'Projector', 'EB-2250U', 'SN-005', 'Conference Room', '2025-07-15', '2027-07-15', 'Available', NULL, '2026-01-15', '2026-07-15', '2026-06-30 06:20:00', '2026-06-30 06:20:00');

-- =============================================
-- TABLE: maintenance_requests
-- =============================================
DROP TABLE IF EXISTS `maintenance_requests`;
CREATE TABLE `maintenance_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `issue_description` text NOT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `status` enum('Pending','Assigned','In Progress','Resolved','Closed') DEFAULT 'Pending',
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `qr_scanned` tinyint(1) DEFAULT 0 COMMENT 'Reported via QR scan',
  PRIMARY KEY (`request_id`),
  KEY `fk_requests_asset_id` (`asset_id`),
  KEY `fk_requests_reported_by` (`reported_by`),
  KEY `fk_requests_assigned_to` (`assigned_to`),
  KEY `idx_requests_status` (`status`),
  KEY `idx_requests_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- TABLE: maintenance_tasks
-- =============================================
DROP TABLE IF EXISTS `maintenance_tasks`;
CREATE TABLE `maintenance_tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `task_description` text NOT NULL,
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `qr_scanned` tinyint(1) DEFAULT 0 COMMENT 'Started via QR scan',
  PRIMARY KEY (`task_id`),
  KEY `fk_tasks_request_id` (`request_id`),
  KEY `fk_tasks_technician_id` (`technician_id`),
  KEY `idx_tasks_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- TABLE: maintenance_analytics
-- =============================================
DROP TABLE IF EXISTS `maintenance_analytics`;
CREATE TABLE `maintenance_analytics` (
  `analytics_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `total_repairs` int(11) DEFAULT 0,
  `avg_repair_time` decimal(5,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `reliability_score` decimal(3,2) DEFAULT 0.00,
  `fault_count` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`analytics_id`),
  KEY `fk_analytics_asset_id` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_analytics`
--
INSERT INTO `maintenance_analytics` (`analytics_id`, `asset_id`, `total_repairs`, `avg_repair_time`, `total_cost`, `last_maintenance`, `next_maintenance`, `reliability_score`, `fault_count`, `updated_at`) VALUES
(1, 1, 0, 0.00, 0.00, '2026-01-15', '2026-07-15', 9.50, 0, '2026-06-30 06:00:00'),
(2, 2, 0, 0.00, 0.00, '2026-03-10', '2026-09-10', 8.80, 0, '2026-06-30 06:00:00'),
(3, 3, 0, 0.00, 0.00, '2026-01-20', '2026-07-20', 9.20, 0, '2026-06-30 06:00:00'),
(4, 4, 0, 0.00, 0.00, '2026-02-01', '2026-08-01', 8.50, 0, '2026-06-30 06:00:00'),
(5, 5, 0, 0.00, 0.00, '2026-01-15', '2026-07-15', 9.00, 0, '2026-06-30 06:00:00');

-- =============================================
-- TABLE: notifications
-- =============================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `fk_notifications_user_id` (`user_id`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  KEY `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--
INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`, `updated_at`) VALUES
(1, 1, 'Welcome to ICT-AMS', 'System initialized successfully. QR Code feature is now active!', 'success', NULL, 1, '2026-06-30 07:44:53', '2026-06-30 08:12:21'),
(2, 1, 'QR Code Feature', 'You can now generate QR codes for all ICT assets.', 'info', NULL, 1, '2026-06-30 08:14:02', '2026-06-30 08:18:21'),
(3, 2, 'QR Code Feature', 'You can now scan QR codes to view asset details.', 'info', NULL, 1, '2026-06-30 08:14:02', '2026-06-30 12:46:04'),
(4, 3, 'QR Code Feature', 'You can now scan QR codes to report faults quickly.', 'info', NULL, 1, '2026-06-30 08:14:02', '2026-06-30 08:44:54');

-- =============================================
-- TABLE: qr_scan_logs
-- =============================================
DROP TABLE IF EXISTS `qr_scan_logs`;
CREATE TABLE `qr_scan_logs` (
  `scan_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `scan_type` enum('view','report_fault','repair','check_status') DEFAULT 'view',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`scan_id`),
  KEY `fk_qr_scan_asset_id` (`asset_id`),
  KEY `fk_qr_scan_user_id` (`user_id`),
  KEY `idx_qr_scan_scanned` (`scanned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- TABLE: qr_codes
-- =============================================
DROP TABLE IF EXISTS `qr_codes`;
CREATE TABLE `qr_codes` (
  `qr_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `qr_code` varchar(100) NOT NULL,
  `qr_image_path` varchar(255) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`qr_id`),
  UNIQUE KEY `uk_qr_codes_code` (`qr_code`),
  KEY `fk_qr_codes_asset_id` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_codes`
--
INSERT INTO `qr_codes` (`qr_id`, `asset_id`, `qr_code`, `qr_image_path`, `generated_at`, `expires_at`, `is_active`) VALUES
(1, 1, 'QR-ICT-0001', 'qr_codes/ICT-0001.png', '2026-06-30 06:00:00', NULL, 1),
(2, 2, 'QR-ICT-0002', 'qr_codes/ICT-0002.png', '2026-06-30 06:00:00', NULL, 1),
(3, 3, 'QR-ICT-0003', 'qr_codes/ICT-0003.png', '2026-06-30 06:00:00', NULL, 1),
(4, 4, 'QR-ICT-0004', 'qr_codes/ICT-0004.png', '2026-06-30 06:00:00', NULL, 1),
(5, 5, 'QR-ICT-0005', 'qr_codes/ICT-0005.png', '2026-06-30 06:00:00', NULL, 1);

-- =============================================
-- TABLE: asset_categories
-- =============================================
DROP TABLE IF EXISTS `asset_categories`;
CREATE TABLE `asset_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(50) NOT NULL,
  `category_icon` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uk_categories_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asset_categories`
--
INSERT INTO `asset_categories` (`category_id`, `category_name`, `category_icon`, `description`, `created_at`) VALUES
(1, 'Laptop', 'fa-laptop', 'Laptop computers and notebooks', '2026-06-30 06:00:00'),
(2, 'Desktop', 'fa-desktop', 'Desktop computers and workstations', '2026-06-30 06:00:00'),
(3, 'Printer', 'fa-print', 'Printers, scanners, and multifunction devices', '2026-06-30 06:00:00'),
(4, 'Network', 'fa-network-wired', 'Network switches, routers, and access points', '2026-06-30 06:00:00'),
(5, 'Projector', 'fa-projector', 'Projectors and display devices', '2026-06-30 06:00:00'),
(6, 'Server', 'fa-server', 'Servers and storage devices', '2026-06-30 06:00:00'),
(7, 'Tablet', 'fa-tablet-alt', 'Tablets and mobile devices', '2026-06-30 06:00:00'),
(8, 'Phone', 'fa-phone', 'Office phones and VoIP devices', '2026-06-30 06:00:00');

-- =============================================
-- TABLE: system_logs
-- =============================================
DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `fk_logs_user_id` (`user_id`),
  KEY `idx_logs_action` (`action`),
  KEY `idx_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- FOREIGN KEY CONSTRAINTS
-- =============================================

-- user_settings
ALTER TABLE `user_settings` ADD CONSTRAINT `fk_user_settings_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- assets
ALTER TABLE `assets` ADD CONSTRAINT `fk_assets_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

-- maintenance_requests
ALTER TABLE `maintenance_requests` ADD CONSTRAINT `fk_requests_asset_id` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE;
ALTER TABLE `maintenance_requests` ADD CONSTRAINT `fk_requests_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
ALTER TABLE `maintenance_requests` ADD CONSTRAINT `fk_requests_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

-- maintenance_tasks
ALTER TABLE `maintenance_tasks` ADD CONSTRAINT `fk_tasks_request_id` FOREIGN KEY (`request_id`) REFERENCES `maintenance_requests` (`request_id`) ON DELETE CASCADE;
ALTER TABLE `maintenance_tasks` ADD CONSTRAINT `fk_tasks_technician_id` FOREIGN KEY (`technician_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- maintenance_analytics
ALTER TABLE `maintenance_analytics` ADD CONSTRAINT `fk_analytics_asset_id` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE;

-- notifications
ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- qr_scan_logs
ALTER TABLE `qr_scan_logs` ADD CONSTRAINT `fk_qr_scan_asset_id` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE;
ALTER TABLE `qr_scan_logs` ADD CONSTRAINT `fk_qr_scan_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- qr_codes
ALTER TABLE `qr_codes` ADD CONSTRAINT `fk_qr_codes_asset_id` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE;

-- system_logs
ALTER TABLE `system_logs` ADD CONSTRAINT `fk_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

-- =============================================
-- TRIGGERS
-- =============================================

-- Trigger: Update analytics when request is resolved
DROP TRIGGER IF EXISTS update_analytics_on_resolve;
DELIMITER //
CREATE TRIGGER update_analytics_on_resolve
AFTER UPDATE ON maintenance_requests
FOR EACH ROW
BEGIN
    IF NEW.status = 'Resolved' AND OLD.status != 'Resolved' THEN
        UPDATE maintenance_analytics 
        SET 
            total_repairs = total_repairs + 1,
            fault_count = fault_count + 1,
            last_maintenance = CURDATE(),
            next_maintenance = DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
            avg_repair_time = (
                SELECT AVG(TIMESTAMPDIFF(HOUR, reported_at, resolved_at)) 
                FROM maintenance_requests 
                WHERE asset_id = NEW.asset_id AND status = 'Resolved'
            )
        WHERE asset_id = NEW.asset_id;
    END IF;
END//
DELIMITER ;

-- Trigger: Log QR scans
DROP TRIGGER IF EXISTS log_qr_scan;
DELIMITER //
CREATE TRIGGER log_qr_scan
AFTER INSERT ON qr_scan_logs
FOR EACH ROW
BEGIN
    UPDATE assets SET updated_at = CURRENT_TIMESTAMP WHERE asset_id = NEW.asset_id;
END//
DELIMITER ;

-- =============================================
-- STORED PROCEDURES
-- =============================================

-- Get asset by QR code
DROP PROCEDURE IF EXISTS get_asset_by_qr;
DELIMITER //
CREATE PROCEDURE get_asset_by_qr(IN qr_code_param VARCHAR(100))
BEGIN
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,
           (SELECT COUNT(*) FROM maintenance_requests WHERE asset_id = a.asset_id AND status != 'Resolved') as active_requests
    FROM assets a
    LEFT JOIN users u ON a.assigned_to = u.user_id
    WHERE a.qr_code = qr_code_param;
END//
DELIMITER ;

-- Get asset maintenance history
DROP PROCEDURE IF EXISTS get_asset_maintenance_history;
DELIMITER //
CREATE PROCEDURE get_asset_maintenance_history(IN asset_id_param INT)
BEGIN
    SELECT r.*, 
           CONCAT(reporter.first_name, ' ', reporter.last_name) as reported_by_name,
           CONCAT(tech.first_name, ' ', tech.last_name) as assigned_to_name
    FROM maintenance_requests r
    LEFT JOIN users reporter ON r.reported_by = reporter.user_id
    LEFT JOIN users tech ON r.assigned_to = tech.user_id
    WHERE r.asset_id = asset_id_param
    ORDER BY r.reported_at DESC;
END//
DELIMITER ;

-- Generate QR code for asset
DROP PROCEDURE IF EXISTS generate_qr_for_asset;
DELIMITER //
CREATE PROCEDURE generate_qr_for_asset(IN asset_id_param INT, IN qr_code_param VARCHAR(100), IN qr_image_path_param VARCHAR(255))
BEGIN
    UPDATE assets 
    SET qr_code = qr_code_param, qr_image = qr_image_path_param 
    WHERE asset_id = asset_id_param;
    
    INSERT INTO qr_codes (asset_id, qr_code, qr_image_path) 
    VALUES (asset_id_param, qr_code_param, qr_image_path_param);
END//
DELIMITER ;

-- =============================================
-- VIEWS
-- =============================================

-- View: Asset summary with QR
DROP VIEW IF EXISTS vw_asset_summary;
CREATE VIEW vw_asset_summary AS
SELECT 
    a.asset_id,
    a.asset_tag,
    a.qr_code,
    a.name,
    a.category,
    a.status,
    a.location,
    CONCAT(u.first_name, ' ', u.last_name) as assigned_to,
    (SELECT COUNT(*) FROM maintenance_requests WHERE asset_id = a.asset_id AND status != 'Resolved') as active_requests,
    (SELECT COUNT(*) FROM maintenance_requests WHERE asset_id = a.asset_id) as total_requests,
    a.last_maintenance_date,
    a.next_maintenance_date
FROM assets a
LEFT JOIN users u ON a.assigned_to = u.user_id;

-- View: QR scan statistics
DROP VIEW IF EXISTS vw_qr_statistics;
CREATE VIEW vw_qr_statistics AS
SELECT 
    a.asset_id,
    a.asset_tag,
    a.name,
    COUNT(l.scan_id) as total_scans,
    COUNT(DISTINCT l.user_id) as unique_scanners,
    MAX(l.scanned_at) as last_scanned,
    SUM(CASE WHEN l.scan_type = 'view' THEN 1 ELSE 0 END) as view_scans,
    SUM(CASE WHEN l.scan_type = 'report_fault' THEN 1 ELSE 0 END) as fault_scans,
    SUM(CASE WHEN l.scan_type = 'repair' THEN 1 ELSE 0 END) as repair_scans
FROM assets a
LEFT JOIN qr_scan_logs l ON a.asset_id = l.asset_id
GROUP BY a.asset_id;

-- =============================================
-- COMMIT
-- =============================================
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
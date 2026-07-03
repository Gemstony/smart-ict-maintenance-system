-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 03, 2026 at 11:26 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ict_asset_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_qr_for_asset` (IN `asset_id_param` INT, IN `qr_code_param` VARCHAR(100), IN `qr_image_path_param` VARCHAR(255))   BEGIN
    UPDATE assets 
    SET qr_code = qr_code_param, qr_image = qr_image_path_param 
    WHERE asset_id = asset_id_param;
    
    INSERT INTO qr_codes (asset_id, qr_code, qr_image_path) 
    VALUES (asset_id_param, qr_code_param, qr_image_path_param);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_asset_by_qr` (IN `qr_code_param` VARCHAR(100))   BEGIN
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,
           (SELECT COUNT(*) FROM maintenance_requests WHERE asset_id = a.asset_id AND status != 'Resolved') as active_requests
    FROM assets a
    LEFT JOIN users u ON a.assigned_to = u.user_id
    WHERE a.qr_code = qr_code_param;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_asset_maintenance_history` (IN `asset_id_param` INT)   BEGIN
    SELECT r.*, 
           CONCAT(reporter.first_name, ' ', reporter.last_name) as reported_by_name,
           CONCAT(tech.first_name, ' ', tech.last_name) as assigned_to_name
    FROM maintenance_requests r
    LEFT JOIN users reporter ON r.reported_by = reporter.user_id
    LEFT JOIN users tech ON r.assigned_to = tech.user_id
    WHERE r.asset_id = asset_id_param
    ORDER BY r.reported_at DESC;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `asset_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`asset_id`, `asset_tag`, `qr_code`, `qr_image`, `name`, `category`, `model`, `serial_number`, `location`, `purchase_date`, `warranty_expiry`, `status`, `assigned_to`, `last_maintenance_date`, `next_maintenance_date`, `created_at`, `updated_at`) VALUES
(1, 'ICT-0001', 'QR-ICT-0001', 'assets/qr_codes/ICT-0001.png', 'Dell Latitude 7420', 'Laptop', 'Latitude 7420', 'SN-001', 'ICT Department', '2025-01-15', '2027-01-15', 'Available', NULL, '2026-01-15', '2026-07-15', '2026-06-30 05:54:30', '2026-07-03 17:28:08'),
(3, 'ICT-0003', 'QR-ICT-0003', 'assets/qr_codes/ICT-0003.png', 'Cisco Switch 2960-24TT', 'Network', '2960-24TT', 'SN-003', 'Finance Department', '2025-06-20', '2027-06-20', 'Available', NULL, '2026-01-20', '2026-07-20', '2026-06-30 05:54:30', '2026-07-03 17:28:26'),
(4, 'ICT-0004', 'QR-ICT-0004', 'assets/qr_codes/ICT-0004.png', 'Dell OptiPlex 7080', 'Desktop', 'OptiPlex 7080', 'SN-004', 'Finance Department', '2025-02-01', '2027-02-01', 'Under Maintenance', 3, '2026-02-01', '2026-08-01', '2026-06-30 06:15:00', '2026-07-03 17:28:16'),
(5, 'ICT-0005', 'QR-ICT-0005', 'assets/qr_codes/ICT-0005.png', 'Epson EB-2250U Projector 22', 'Projector', 'EB-2250U', 'SN-005', 'Mathematics Department', '2025-07-15', '2027-07-15', 'In Use', 2, '2026-01-15', '2026-07-15', '2026-06-30 06:20:00', '2026-07-03 17:27:53'),
(6, 'ICT-0006', 'QR-ICT-0006', 'assets/qr_codes/ICT-0006.png', 'CCTV Camera', 'Network', 'C001', '', 'Finance Department', '2026-07-03', '2027-04-15', 'Available', 5, '2026-07-03', '2026-08-02', '2026-07-03 14:09:34', '2026-07-03 17:27:45'),
(7, 'ICT-0007', 'QR-ICT-0007', 'assets/qr_codes/ICT-0007.png', 'LONG TABLE', 'Other', '', '', 'Finance Department', NULL, NULL, 'Available', 3, NULL, NULL, '2026-07-03 15:20:54', '2026-07-03 17:27:37'),
(8, 'ICT-0008', 'QR-ICT-0008', 'assets/qr_codes/ICT-0008.png', 'Printer ESP', 'Printer', '', '', 'ICT Department', NULL, NULL, 'Available', NULL, NULL, NULL, '2026-07-03 17:24:46', '2026-07-03 17:24:46');

-- --------------------------------------------------------

--
-- Table structure for table `asset_categories`
--

CREATE TABLE `asset_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_icon` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
(8, 'Phone', 'fa-phone', 'Office phones and VoIP devices', '2026-06-30 06:00:00'),
(9, 'Other', 'fa-gear', 'Other assets', '2026-07-03 14:14:01');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_analytics`
--

CREATE TABLE `maintenance_analytics` (
  `analytics_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `total_repairs` int(11) DEFAULT 0,
  `avg_repair_time` decimal(5,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `reliability_score` decimal(3,2) DEFAULT 0.00,
  `fault_count` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_analytics`
--

INSERT INTO `maintenance_analytics` (`analytics_id`, `asset_id`, `total_repairs`, `avg_repair_time`, `total_cost`, `last_maintenance`, `next_maintenance`, `reliability_score`, `fault_count`, `updated_at`) VALUES
(1, 1, 0, 0.00, 0.00, '2026-01-15', '2026-07-15', 9.50, 0, '2026-06-30 06:00:00'),
(3, 3, 0, 0.00, 0.00, '2026-01-20', '2026-07-20', 9.20, 0, '2026-06-30 06:00:00'),
(4, 4, 1, 0.00, 0.00, '2026-07-03', '2027-01-03', 8.50, 1, '2026-07-03 19:43:38'),
(5, 5, 0, 0.00, 0.00, '2026-01-15', '2026-07-15', 9.00, 0, '2026-06-30 06:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `request_id` int(11) NOT NULL,
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
  `qr_scanned` tinyint(1) DEFAULT 0 COMMENT 'Reported via QR scan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_requests`
--

INSERT INTO `maintenance_requests` (`request_id`, `asset_id`, `reported_by`, `assigned_to`, `issue_description`, `priority`, `status`, `reported_at`, `assigned_at`, `resolved_at`, `closed_at`, `resolution_notes`, `qr_scanned`) VALUES
(1, 6, 3, NULL, 'the CCTV is not recording', 'High', 'Pending', '2026-07-03 17:49:26', NULL, NULL, NULL, NULL, 0),
(2, 7, 3, 2, 'Broken', 'Critical', 'In Progress', '2026-07-03 18:31:31', '2026-07-03 19:24:45', NULL, NULL, NULL, 1),
(3, 4, 3, 2, 'very broken', 'Low', 'Closed', '2026-07-03 19:36:21', '2026-07-03 19:37:19', '2026-07-03 19:43:38', '2026-07-03 19:45:03', NULL, 0),
(4, 3, 3, NULL, 'Not connecting', 'Medium', 'Pending', '2026-07-03 19:36:37', NULL, NULL, NULL, NULL, 0);

--
-- Triggers `maintenance_requests`
--
DELIMITER $$
CREATE TRIGGER `update_analytics_on_resolve` AFTER UPDATE ON `maintenance_requests` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_tasks`
--

CREATE TABLE `maintenance_tasks` (
  `task_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `task_description` text NOT NULL,
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `qr_scanned` tinyint(1) DEFAULT 0 COMMENT 'Started via QR scan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`, `updated_at`) VALUES
(1, 1, 'Welcome to ICT-AMS', 'System initialized successfully. QR Code feature is now active!', 'success', NULL, 1, '2026-06-30 07:44:53', '2026-06-30 08:12:21'),
(2, 1, 'QR Code Feature', 'You can now generate QR codes for all ICT assets.', 'info', NULL, 1, '2026-06-30 08:14:02', '2026-06-30 08:18:21'),
(3, 2, 'QR Code Feature', 'You can now scan QR codes to view asset details.', 'info', NULL, 1, '2026-06-30 08:14:02', '2026-07-03 14:41:21'),
(4, 3, 'QR Code Feature', 'You can now scan QR codes to report faults quickly.', 'info', NULL, 1, '2026-06-30 08:14:02', '2026-06-30 08:44:54'),
(5, 2, 'Testing', 'hellow devid', 'danger', NULL, 1, '2026-07-03 14:40:35', '2026-07-03 14:41:16'),
(6, 2, '🔧 New Assignment', 'You have been assigned to request #3 for asset \'Dell OptiPlex 7080\'. Please check the details.', 'info', NULL, 1, '2026-07-03 19:37:19', '2026-07-03 19:37:43'),
(7, 3, '⚙️ Work Started on Your Request', 'Technician has started working on request #3 for asset \'Dell OptiPlex 7080\'.', 'warning', NULL, 0, '2026-07-03 19:43:22', '2026-07-03 19:43:22'),
(8, 3, '✅ Request Resolved', 'Your request #3 for asset \'Dell OptiPlex 7080\' has been resolved. ', 'success', NULL, 0, '2026-07-03 19:43:38', '2026-07-03 19:43:38'),
(9, 3, '📋 Request Closed', 'Your request #3 for asset \'Dell OptiPlex 7080\' has been closed.', 'info', NULL, 1, '2026-07-03 19:45:03', '2026-07-03 21:11:59'),
(10, 3, '⚙️ Work Started on Your Request', 'Technician has started working on request #2 for asset \'LONG TABLE\'.', 'warning', NULL, 1, '2026-07-03 19:49:22', '2026-07-03 20:07:02');

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `qr_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `qr_code` varchar(100) NOT NULL,
  `qr_image_path` varchar(255) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_codes`
--

INSERT INTO `qr_codes` (`qr_id`, `asset_id`, `qr_code`, `qr_image_path`, `generated_at`, `expires_at`, `is_active`) VALUES
(1, 1, 'QR-ICT-0001', 'qr_codes/ICT-0001.png', '2026-06-30 06:00:00', NULL, 1),
(3, 3, 'QR-ICT-0003', 'qr_codes/ICT-0003.png', '2026-06-30 06:00:00', NULL, 1),
(4, 4, 'QR-ICT-0004', 'qr_codes/ICT-0004.png', '2026-06-30 06:00:00', NULL, 1),
(5, 5, 'QR-ICT-0005', 'qr_codes/ICT-0005.png', '2026-06-30 06:00:00', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `qr_scan_logs`
--

CREATE TABLE `qr_scan_logs` (
  `scan_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `scan_type` enum('view','report_fault','repair','check_status') DEFAULT 'view',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `qr_scan_logs`
--
DELIMITER $$
CREATE TRIGGER `log_qr_scan` AFTER INSERT ON `qr_scan_logs` FOR EACH ROW BEGIN
    UPDATE assets SET updated_at = CURRENT_TIMESTAMP WHERE asset_id = NEW.asset_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `role`, `department`, `profile_picture`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Tzone', 'Mushi', 'admin@ict', '0712345678', '$2y$10$nss2PAKVMktnlFNj0wm94exVgdlEGHVnEzcHIJcN/5V0KYTvXPc2y', 'System Administrator', 'ICT Department', 'user_1_1782807552.jpg', 'active', '2026-06-30 05:54:30', '2026-06-30 08:26:32'),
(2, 'David', 'Kato', 'tech1@ict', '0723456789', '$2y$10$hmIPLO8FW4/ZrBMQnwTaV.5m/ccV.hfW05W/6qbDoVV0wMwoxuzc.', 'ICT Technician', 'ICT Department', NULL, 'active', '2026-06-30 05:54:30', '2026-06-30 08:28:52'),
(3, 'Sarah', 'Mrema', 'staff1@ict', '0734567890', '$2y$10$8qQw..Fx3S6Ea7/tQKX/jO977dP6QiyYw6stQoHbVMc9FBWuOvxhm', 'Staff', 'Finance Department', NULL, 'active', '2026-06-30 05:54:30', '2026-07-01 10:44:36'),
(4, 'Thazan', 'Jumanne', 'tz@gmail.com', '0712345679', '$2y$10$GEdF5r6XTo/Wl2Q3By2/zOh22ZeuYlGCE8UW8QiBs0aSO44Srfuo6', 'Staff', 'ICT Department', NULL, 'active', '2026-06-30 06:12:36', '2026-07-03 07:14:15'),
(5, 'scar', 'scar', 'scar@gmail.com', '0611111111', '$2y$10$XtI8ZB9hQrQDwHO/y2nxpe4zczNpekuMhvbOCm9zdiw3YITK4S/wq', 'System Administrator', 'ICT Department', 'user_5_1783062189.png', 'active', '2026-07-03 07:01:21', '2026-07-03 16:21:51'),
(6, 'mama', 'amina', 'amina@gmail.com', '0713000007', '$2y$10$ClHcVpRDQSozYTitYahs3OpNpKxItTKJpRggTq8aKhe8.Zw7LILme', 'Staff', 'Mathematics Department', NULL, 'active', '2026-07-03 16:19:43', '2026-07-03 16:19:43');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `setting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `header_color` varchar(20) DEFAULT '#0d47a1',
  `sidebar_color` varchar(20) DEFAULT '#0d47a1',
  `background_color` varchar(20) DEFAULT '#f8f9fa',
  `font_size` varchar(10) DEFAULT '14px',
  `sidebar_collapsed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`setting_id`, `user_id`, `header_color`, `sidebar_color`, `background_color`, `font_size`, `sidebar_collapsed`, `created_at`, `updated_at`) VALUES
(1, 1, '#0d47a1', '#0d47a1', '#f8f9fa', '14px', 0, '2026-06-30 06:28:50', '2026-07-01 10:48:17'),
(2, 2, '#2d3436', '#1e1e1e', '#f8f9fa', '14px', 0, '2026-06-30 06:28:50', '2026-07-03 19:49:47'),
(3, 3, '#2d3436', '#1e1e1e', '#f8f9fa', '14px', 0, '2026-06-30 06:28:50', '2026-07-03 18:32:57'),
(4, 4, '#0d47a1', '#0d47a1', '#f8f9fa', '14px', 0, '2026-06-30 06:28:50', '2026-06-30 06:28:50'),
(5, 5, '#2d3436', '#1e1e1e', '#f5f5f5', '12px', 0, '2026-07-03 07:02:03', '2026-07-03 14:28:51');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_asset_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_asset_summary` (
`asset_id` int(11)
,`asset_tag` varchar(50)
,`qr_code` varchar(100)
,`name` varchar(100)
,`category` varchar(50)
,`status` enum('Available','In Use','Under Maintenance','Retired')
,`location` varchar(100)
,`assigned_to` varchar(101)
,`active_requests` bigint(21)
,`total_requests` bigint(21)
,`last_maintenance_date` date
,`next_maintenance_date` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_qr_statistics`
-- (See below for the actual view)
--
CREATE TABLE `vw_qr_statistics` (
`asset_id` int(11)
,`asset_tag` varchar(50)
,`name` varchar(100)
,`total_scans` bigint(21)
,`unique_scanners` bigint(21)
,`last_scanned` timestamp
,`view_scans` decimal(22,0)
,`fault_scans` decimal(22,0)
,`repair_scans` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_asset_summary`
--
DROP TABLE IF EXISTS `vw_asset_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_asset_summary`  AS SELECT `a`.`asset_id` AS `asset_id`, `a`.`asset_tag` AS `asset_tag`, `a`.`qr_code` AS `qr_code`, `a`.`name` AS `name`, `a`.`category` AS `category`, `a`.`status` AS `status`, `a`.`location` AS `location`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `assigned_to`, (select count(0) from `maintenance_requests` where `maintenance_requests`.`asset_id` = `a`.`asset_id` and `maintenance_requests`.`status` <> 'Resolved') AS `active_requests`, (select count(0) from `maintenance_requests` where `maintenance_requests`.`asset_id` = `a`.`asset_id`) AS `total_requests`, `a`.`last_maintenance_date` AS `last_maintenance_date`, `a`.`next_maintenance_date` AS `next_maintenance_date` FROM (`assets` `a` left join `users` `u` on(`a`.`assigned_to` = `u`.`user_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_qr_statistics`
--
DROP TABLE IF EXISTS `vw_qr_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_qr_statistics`  AS SELECT `a`.`asset_id` AS `asset_id`, `a`.`asset_tag` AS `asset_tag`, `a`.`name` AS `name`, count(`l`.`scan_id`) AS `total_scans`, count(distinct `l`.`user_id`) AS `unique_scanners`, max(`l`.`scanned_at`) AS `last_scanned`, sum(case when `l`.`scan_type` = 'view' then 1 else 0 end) AS `view_scans`, sum(case when `l`.`scan_type` = 'report_fault' then 1 else 0 end) AS `fault_scans`, sum(case when `l`.`scan_type` = 'repair' then 1 else 0 end) AS `repair_scans` FROM (`assets` `a` left join `qr_scan_logs` `l` on(`a`.`asset_id` = `l`.`asset_id`)) GROUP BY `a`.`asset_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`asset_id`),
  ADD UNIQUE KEY `uk_assets_asset_tag` (`asset_tag`),
  ADD UNIQUE KEY `uk_assets_qr_code` (`qr_code`),
  ADD KEY `fk_assets_assigned_to` (`assigned_to`);

--
-- Indexes for table `asset_categories`
--
ALTER TABLE `asset_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uk_categories_name` (`category_name`);

--
-- Indexes for table `maintenance_analytics`
--
ALTER TABLE `maintenance_analytics`
  ADD PRIMARY KEY (`analytics_id`),
  ADD KEY `fk_analytics_asset_id` (`asset_id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_requests_asset_id` (`asset_id`),
  ADD KEY `fk_requests_reported_by` (`reported_by`),
  ADD KEY `fk_requests_assigned_to` (`assigned_to`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `idx_requests_priority` (`priority`);

--
-- Indexes for table `maintenance_tasks`
--
ALTER TABLE `maintenance_tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `fk_tasks_request_id` (`request_id`),
  ADD KEY `fk_tasks_technician_id` (`technician_id`),
  ADD KEY `idx_tasks_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notifications_user_id` (`user_id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_created` (`created_at`);

--
-- Indexes for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`qr_id`),
  ADD UNIQUE KEY `uk_qr_codes_code` (`qr_code`),
  ADD KEY `fk_qr_codes_asset_id` (`asset_id`);

--
-- Indexes for table `qr_scan_logs`
--
ALTER TABLE `qr_scan_logs`
  ADD PRIMARY KEY (`scan_id`),
  ADD KEY `fk_qr_scan_asset_id` (`asset_id`),
  ADD KEY `fk_qr_scan_user_id` (`user_id`),
  ADD KEY `idx_qr_scan_scanned` (`scanned_at`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_logs_user_id` (`user_id`),
  ADD KEY `idx_logs_action` (`action`),
  ADD KEY `idx_logs_created` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uk_users_email` (`email`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `uk_user_settings_user_id` (`user_id`),
  ADD KEY `fk_user_settings_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `asset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `asset_categories`
--
ALTER TABLE `asset_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `maintenance_analytics`
--
ALTER TABLE `maintenance_analytics`
  MODIFY `analytics_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `maintenance_tasks`
--
ALTER TABLE `maintenance_tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `qr_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `qr_scan_logs`
--
ALTER TABLE `qr_scan_logs`
  MODIFY `scan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `fk_assets_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance_analytics`
--
ALTER TABLE `maintenance_analytics`
  ADD CONSTRAINT `fk_analytics_asset_id` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `fk_requests_asset_id` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_requests_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_requests_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_tasks`
--
ALTER TABLE `maintenance_tasks`
  ADD CONSTRAINT `fk_tasks_request_id` FOREIGN KEY (`request_id`) REFERENCES `maintenance_requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tasks_technician_id` FOREIGN KEY (`technician_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD CONSTRAINT `fk_qr_codes_asset_id` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE;

--
-- Constraints for table `qr_scan_logs`
--
ALTER TABLE `qr_scan_logs`
  ADD CONSTRAINT `fk_qr_scan_asset_id` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qr_scan_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `fk_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_user_settings_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

<?php
// config.php - Database configuration

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'ict_asset_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('APP_NAME', 'ICT Asset Management System');
define('APP_SHORT', 'ICT-AMS');
define('BASE_URL', 'http://localhost/smart-ict-maintenance-system/');
define('TIMEZONE', 'Africa/Dar_es_Salaam');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Function to get PDO connection
function getDB() {
    global $pdo;
    return $pdo;
}
?>
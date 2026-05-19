<?php
/**
 * SpendWise — Database Configuration
 * Uses PDO for secure, prepared-statement queries.
 *
 * Copy this file to config/database.php and update credentials.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'spendwise');
define('DB_USER', 'root');       // ← change in production
define('DB_PASS', '');           // ← change in production

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // In production, log this — never expose DB errors to users
    error_log("DB Connection failed: " . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed.']));
}
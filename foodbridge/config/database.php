<?php
/**
 * FoodBridge - Database Connection (PDO / MySQL)
 * =================================================
 * This is the ONLY file in the project that opens a database connection.
 * Every other PHP page includes this file to get a ready-to-use $pdo
 * object instead of writing its own connection code.
 *
 * Architecture:
 *
 *     PHP Page  --->  config/database.php  --->  MySQL Database
 *
 *   1. A page (e.g. login.php, admin/dashboard.php) does:
 *          require_once __DIR__ . '/config/database.php';
 *      or, from a subfolder:
 *          require_once __DIR__ . '/../config/database.php';
 *
 *   2. This file connects once using PDO and exposes a single
 *      variable, $pdo, that holds the live database connection.
 *
 *   3. The page then uses $pdo to run prepared statements against
 *      MySQL (SELECT / INSERT / UPDATE / DELETE), e.g.:
 *          $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
 *          $stmt->execute([':email' => $email]);
 *          $user = $stmt->fetch();
 *
 * Why PDO (not MySQLi)?
 *   - Works with named placeholders (:email) which is easier to read
 *     than MySQLi's positional "?" placeholders.
 *   - Throws exceptions on error instead of requiring manual checks
 *     after every call, so errors can't silently pass unnoticed.
 *   - If the project ever needed a different database engine, PDO's
 *     API stays the same (only the DSN line changes).
 *
 * Credentials live ONLY here (single source of truth). No other file
 * should contain a hostname, username, password, or `new PDO(...)`.
 */

// ---- Database credentials (XAMPP defaults) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'foodbridge');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Data Source Name: tells PDO which driver, host, database, and charset to use.
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

// Connection options.
$options = [
    // Throw a PDOException on any DB error instead of failing silently.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Return rows as associative arrays ($row['column']) by default.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Use real prepared statements (sent to MySQL as-is) instead of
    // PHP emulating them - safer and lets MySQL type-check bound values.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // The single, reusable connection object every page will use.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Log the real error for the developer, but never show raw DB
    // details (host, credentials, schema) to the end user.
    error_log('FoodBridge DB connection failed: ' . $e->getMessage());
    die('Database connection failed. Please try again later.');
}

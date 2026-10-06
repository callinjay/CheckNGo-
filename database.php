<?php
/**
 * config/database.php
 * Central PDO connection for CheckNGo.
 * Adjust credentials for your local XAMPP setup, or better, load them
 * from environment variables in a real deployment.
 */

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'checkngo_db';
const DB_USER = 'root';
const DB_PASS = '';        // default XAMPP root password is empty
const DB_CHARSET = 'utf8mb4';

/**
 * Returns a shared PDO instance (singleton per request).
 */
function get_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Never leak DB credentials or raw exception details to the browser.
        error_log('[CheckNGo] DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('A system error occurred. Please try again later.');
    }

    return $pdo;
}

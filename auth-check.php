<?php
/**
 * includes/auth-check.php
 * Include this at the very top of every protected page.
 * Ensures a valid, authenticated session exists before any output.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        // 'cookie_secure' => true, // enable once served over HTTPS
    ]);
}

require_once __DIR__ . '/csrf.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /checkngo/login.php');
    exit;
}

/** Convenience accessor used throughout controllers/views. */
function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

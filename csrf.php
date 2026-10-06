<?php
/**
 * includes/csrf.php
 * Minimal CSRF token generation/validation helpers.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function csrf_verify(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    if ($submitted === '' || $expected === '') {
        return false;
    }
    return hash_equals($expected, $submitted);
}

/**
 * Call at the top of any POST handler. Dies with a friendly message on failure.
 */
function csrf_require(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(419);
        die('Your session expired or the request could not be verified. Please refresh and try again.');
    }
}

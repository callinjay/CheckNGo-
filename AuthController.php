<?php
/**
 * controllers/AuthController.php
 * Handles registration, login, and logout. Views (register.php, login.php)
 * include this and call the static methods below.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/csrf.php';

class AuthController
{
    /**
     * @return array{success:bool, errors:array<string,string>}
     */
    public static function register(array $post): array
    {
        $errors = [];

        $fullName = trim((string) ($post['full_name'] ?? ''));
        $email    = trim((string) ($post['email'] ?? ''));
        $password = (string) ($post['password'] ?? '');
        $confirm  = (string) ($post['confirm_password'] ?? '');
        $agreed   = isset($post['terms']);

        if ($fullName === '' || mb_strlen($fullName) > 150) {
            $errors['full_name'] = 'Please enter your full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (User::emailExists($email)) {
            $errors['email'] = 'An account with this email already exists.';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        if (!$agreed) {
            $errors['terms'] = 'You must acknowledge the terms to continue.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $userId = User::create($fullName, $email, $password);

        session_regenerate_id(true);
        $_SESSION['user_id']   = $userId;
        $_SESSION['user_name'] = $fullName;

        return ['success' => true, 'errors' => []];
    }

    /**
     * @return array{success:bool, errors:array<string,string>}
     */
    public static function login(array $post): array
    {
        $email    = trim((string) ($post['email'] ?? ''));
        $password = (string) ($post['password'] ?? '');

        $genericError = ['email' => 'Incorrect email or password.'];

        if ($email === '' || $password === '') {
            return ['success' => false, 'errors' => $genericError];
        }

        $user = User::findByEmail($email);

        // Always run password_verify (even against a dummy hash) to avoid
        // leaking which emails exist via timing differences.
        $hashToCheck = $user['password_hash'] ?? '$2y$10$invalidsaltinvalidsaltinvalidsaltinvalidsal';
        $valid = User::verifyPassword($password, $hashToCheck);

        if (!$user || !$valid) {
            return ['success' => false, 'errors' => $genericError];
        }

        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_name'] = $user['full_name'];

        return ['success' => true, 'errors' => []];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}

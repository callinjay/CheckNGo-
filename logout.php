<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/controllers/AuthController.php';
AuthController::logout();
header('Location: /checkngo/login.php');
exit;

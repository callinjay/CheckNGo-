<?php
declare(strict_types=1);
session_start();
header('Location: ' . (!empty($_SESSION['user_id']) ? '/checkngo/dashboard.php' : '/checkngo/login.php'));
exit;

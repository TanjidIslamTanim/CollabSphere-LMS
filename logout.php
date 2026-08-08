<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/app/Helpers/auth.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookieParameters = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookieParameters['path'],
        $cookieParameters['domain'],
        $cookieParameters['secure'],
        $cookieParameters['httponly']
    );
}

session_destroy();

session_start();

setFlash('success', 'You have logged out successfully.');

redirect('login.php');
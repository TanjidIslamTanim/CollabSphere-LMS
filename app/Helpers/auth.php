<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireGuest(): void
{
    if (isLoggedIn()) {
        redirectToDashboard();
    }
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function requireRole(string $role): void
{
    requireLogin();

    if (($_SESSION['user']['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function redirectToDashboard(): never
{
    $role = $_SESSION['user']['role'] ?? '';

    if ($role === 'student') {
        redirect('student/dashboard.php');
    }

    if ($role === 'instructor') {
        redirect('instructor/dashboard.php');
    }

    redirect('login.php');
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}
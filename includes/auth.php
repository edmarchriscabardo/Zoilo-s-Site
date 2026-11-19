<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/* ---------- security headers ---------- */
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'self'");

/* ---------- session ---------- */
if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('ZBG_SESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Old form input survives exactly one redirect.
$GLOBALS['__old'] = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

const SESSION_IDLE_SECONDS = 1800; // 30 minutes

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(mixed $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Every POST handler calls this first. Redirects to $backTo when invalid. */
function require_post_csrf(string $backTo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        redirect($backTo);
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your form expired or was invalid. Please try again.');
        redirect($backTo);
    }
}

/* ---------- authentication ---------- */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int)$user['id'],
        'username' => (string)$user['username'],
        'role'     => (string)$user['role'],
    ];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

// Idle timeout
if (is_logged_in()) {
    $last = (int)($_SESSION['last_activity'] ?? 0);
    if ($last > 0 && time() - $last > SESSION_IDLE_SECONDS) {
        logout_user();
        flash('error', 'You were logged out after 30 minutes of inactivity.');
    } else {
        $_SESSION['last_activity'] = time();
    }
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('public/login.php');
    }
}

function require_guest(): void
{
    if (is_logged_in()) {
        redirect('public/dashboard.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', 'Only an administrator can do that.');
        redirect('public/dashboard.php');
    }
}

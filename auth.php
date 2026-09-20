<?php
declare(strict_types=1);

/**
 * Session bootstrap, authentication, and the small helpers every page needs.
 * Every entry point includes this first.
 */

/* Local overrides load first so their define() calls win over the defaults. */
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
require_once __DIR__ . '/config.php';

/* ---------- Session ---------- */

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,   // never send the cookie over plain HTTP
        'httponly' => true,     // out of reach of JavaScript
        'samesite' => 'Lax',    // blocks cross-site POSTs carrying the cookie
    ]);
    session_start();
}

/* Expire an idle session rather than letting it live indefinitely. */
if (isset($_SESSION['last_seen']) && time() - (int) $_SESSION['last_seen'] > SESSION_LIFETIME) {
    logout();
    header('Location: login.php?timeout=1', true, 303);
    exit;
}
$_SESSION['last_seen'] = time();

/* ---------- Output helpers ---------- */

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_ok(): bool
{
    return !empty($_POST['csrf_token'])
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

/* ---------- Flash messages ---------- */

/** Store a message and redirect. Never returns. */
function back(string $type, string $message, string $url = 'index.php')
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . $url, true, 303);
    exit;
}

/** Read and clear the pending message. */
function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/* ---------- Authentication ---------- */

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function current_user(): string
{
    return (string) ($_SESSION['user'] ?? '');
}

/**
 * Gate a page. Sends anyone not signed in to the login form, remembering
 * where they were headed so they land there afterwards.
 */
function require_login()
{
    if (is_logged_in()) {
        return;
    }

    $target = $_SERVER['REQUEST_URI'] ?? '/index.php';
    $_SESSION['after_login'] = is_string($target) ? $target : '/index.php';

    header('Location: login.php', true, 303);
    exit;
}

/**
 * Check a username and password against the configured accounts.
 * Always runs a hash comparison, even for an unknown username, so the
 * response time does not reveal which usernames exist.
 */
function attempt_login(string $username, string $password): bool
{
    $users = USERS;
    $dummy = '$2y$12$usesomesillystringfoeequ0TJcYMiFxEnKm3HHmvBpJcD5JdM8C.';
    $hash  = $users[$username] ?? $dummy;

    if (!password_verify($password, $hash) || !isset($users[$username])) {
        return false;
    }

    /* New session id on privilege change, so a fixed cookie is worthless. */
    session_regenerate_id(true);

    $_SESSION['user']      = $username;
    $_SESSION['last_seen'] = time();
    unset($_SESSION['login_attempts'], $_SESSION['locked_until']);

    /* A fresh CSRF token for the new session. */
    unset($_SESSION['csrf_token']);
    csrf_token();

    return true;
}

function logout()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

/* ---------- Login throttling ---------- */

/** Seconds remaining on a lockout, or 0 if not locked out. */
function lockout_remaining(): int
{
    $until = (int) ($_SESSION['locked_until'] ?? 0);
    return $until > time() ? $until - time() : 0;
}

function record_failed_login()
{
    $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_attempts'] = $attempts;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['locked_until']   = time() + LOCKOUT_SECONDS;
        $_SESSION['login_attempts'] = 0;
    }
}

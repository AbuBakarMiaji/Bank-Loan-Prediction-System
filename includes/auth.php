<?php
/**
 * Session bootstrap + authentication guards.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Returns the logged-in user's array from session, or null. */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function is_admin(): bool {
    return is_logged_in() && $_SESSION['user']['role'] === 'admin';
}

/** Redirect helper */
function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

/** Call at the top of any customer-only page. */
function require_login(): void {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

/** Call at the top of any admin-only page. */
function require_admin(): void {
    if (!is_logged_in()) {
        redirect('../login.php');
    }
    if (!is_admin()) {
        redirect('../dashboard.php');
    }
}

/** Simple CSRF token helpers */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

<?php
/**
 * ShopSphere - Authentication & Session Security Engine
 * Phase 3: Backend & API Foundation
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/response.php';

/**
 * Initialize a hardened PHP session if not already active.
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            // Set standard session name
            session_name('SHOPSPHERE_SESSID');

            // Apply session cookie settings
            session_set_cookie_params([
                'lifetime' => 0, // Session cookie (expires when browser closes)
                'path'     => '/',
                'domain'   => '',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        @session_start();
    }
}

/**
 * Generate or retrieve the existing CSRF token from session.
 */
function getCsrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from header X-CSRF-Token or request payload.
 */
function verifyCsrfToken(?string $token): bool {
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token on state-changing requests (POST, PUT, DELETE, PATCH).
 */
function requireCsrf(): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    
    // Only verify for state-changing HTTP verbs
    if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        // First check HTTP header X-CSRF-Token
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        // If not in header, check request body
        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? null);
        }

        if (!verifyCsrfToken($token)) {
            sendForbidden('CSRF verification failed or token missing. Please refresh and try again.');
        }
    }
}

/**
 * Establish authenticated session for a validated user.
 */
function loginUser(array $user): void {
    startSecureSession();

    // Prevent session fixation by generating a new session ID
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }

    // Populate session data (never store password hash in session)
    $_SESSION['auth_logged_in'] = true;
    $_SESSION['auth_user_id']   = (int) $user['id'];
    $_SESSION['auth_username']  = $user['username'];
    $_SESSION['auth_email']     = $user['email'];
    $_SESSION['auth_full_name'] = $user['full_name'];
    $_SESSION['auth_role']      = $user['role'];
    $_SESSION['auth_status']    = $user['status'];
    $_SESSION['auth_login_at']  = time();

    // Initialize fresh CSRF token
    $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
}

/**
 * Securely terminate and destroy the active user session.
 */
function logoutUser(): void {
    startSecureSession();

    // Wipe all session variables
    $_SESSION = [];

    // Expire the session cookie in client browser if headers not yet sent
    if (!headers_sent() && ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_destroy();
    }
}

/**
 * Check if the current client is authenticated.
 */
function isAuthenticated(): bool {
    startSecureSession();
    return !empty($_SESSION['auth_logged_in']) && !empty($_SESSION['auth_user_id']);
}

/**
 * Retrieve the currently authenticated user's session payload.
 */
function getCurrentUser(): ?array {
    if (!isAuthenticated()) {
        return null;
    }

    return [
        'id'        => (int) $_SESSION['auth_user_id'],
        'username'  => $_SESSION['auth_username'],
        'email'     => $_SESSION['auth_email'],
        'full_name' => $_SESSION['auth_full_name'],
        'role'      => $_SESSION['auth_role'],
        'status'    => $_SESSION['auth_status'],
        'login_at'  => $_SESSION['auth_login_at'] ?? time()
    ];
}

/**
 * Require active authentication or abort with 401 Unauthorized.
 */
function requireAuth(): array {
    if (!isAuthenticated()) {
        sendUnauthorized('Authentication required. You must be signed in to access this administrative resource.');
    }

    return getCurrentUser();
}

/**
 * Require specific user role (e.g. administrator).
 */
function requireRole(string|array $roles): array {
    $user = requireAuth();
    $allowed = is_array($roles) ? $roles : [$roles];

    if (!in_array($user['role'], $allowed, true)) {
        sendForbidden('Insufficient permissions to access this endpoint.');
    }

    return $user;
}

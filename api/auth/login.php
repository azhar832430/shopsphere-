<?php
/**
 * ShopSphere - Admin Authentication Login Endpoint
 * Phase 3: Backend & API Foundation
 * 
 * Route: POST /api/auth/login.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only allow POST requests
requireMethod('POST');

// Parse request payload
$input = getJsonInput();

$loginIdentifier = sanitizeString($input['username_or_email'] ?? ($input['email'] ?? ($input['username'] ?? '')));
$password = (string) ($input['password'] ?? '');

// Validation
$validator = new Validator([
    'identifier' => $loginIdentifier,
    'password'   => $password
]);

$validator->required([
    'identifier' => 'Username or email address',
    'password'   => 'Password'
]);

if ($validator->fails()) {
    sendError('Validation failed.', $validator->getErrors(), 400);
}

try {
    $pdo = Database::getConnection();

    // Query for user by either username or email
    $stmt = $pdo->prepare('
        SELECT id, username, email, password, full_name, role, status 
        FROM users 
        WHERE username = :identifier_user OR email = :identifier_email 
        LIMIT 1
    ');

    $stmt->execute([
        'identifier_user'  => $loginIdentifier,
        'identifier_email' => $loginIdentifier
    ]);

    $user = $stmt->fetch();

    // Constant-time comparison dummy check to prevent timing attacks if user doesn't exist
    $dummyHash = '$2y$10$Fav8jb7jkvI4QV/dVJWcNO3B6mmgMizBKyhsyI1Ir2VsvdAUwIjHK';
    $hashToVerify = $user ? $user['password'] : $dummyHash;

    $passwordMatches = password_verify($password, $hashToVerify);

    // If user not found OR password mismatch: return generic message
    if (!$user || !$passwordMatches) {
        sendError('Invalid username/email or password.', [
            'auth' => 'Invalid credentials'
        ], 401);
    }

    // Check account status
    if (strtolower($user['status']) !== 'active') {
        sendForbidden('Your administrative account has been deactivated or suspended. Please contact support.');
    }

    // Initialize authenticated session
    loginUser($user);

    // Retrieve active CSRF token for the frontend client
    $csrfToken = getCsrfToken();

    sendSuccess('Authentication successful. Welcome to ShopSphere.', [
        'user' => [
            'id'        => (int) $user['id'],
            'username'  => $user['username'],
            'email'     => $user['email'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
            'status'    => $user['status']
        ],
        'csrf_token' => $csrfToken
    ]);

} catch (PDOException $e) {
    logSystemError('Login database query failure: ' . $e->getMessage());
    sendServerError('An error occurred during authentication. Please try again.');
} catch (Throwable $e) {
    logSystemError('Unexpected login exception: ' . $e->getMessage());
    sendServerError('An unexpected authentication error occurred.');
}


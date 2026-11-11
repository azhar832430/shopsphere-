<?php
/**
 * ShopSphere - Authenticated User Profile Endpoint
 * Phase 3: Backend & API Foundation
 * 
 * Route: GET /api/auth/me.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only allow GET requests
requireMethod('GET');

// Ensure client is authenticated (aborts with 401 if unauthenticated)
$currentUser = requireAuth();

try {
    $pdo = Database::getConnection();

    // Verify user record still exists and status is active
    $stmt = $pdo->prepare('SELECT id, username, email, full_name, role, status FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $currentUser['id']]);
    $user = $stmt->fetch();

    if (!$user) {
        logoutUser();
        sendUnauthorized('Session invalidated. User profile no longer exists.');
    }

    if (strtolower($user['status']) !== 'active') {
        logoutUser();
        sendForbidden('Your account has been deactivated.');
    }

    sendSuccess('Authenticated user profile retrieved.', [
        'user' => [
            'id'        => (int) $user['id'],
            'username'  => $user['username'],
            'email'     => $user['email'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
            'status'    => $user['status']
        ],
        'csrf_token' => getCsrfToken()
    ]);

} catch (Throwable $e) {
    logSystemError('Failed to fetch authenticated profile: ' . $e->getMessage());
    sendServerError('An error occurred while retrieving user details.');
}


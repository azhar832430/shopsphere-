<?php
/**
 * ShopSphere - Admin Authentication Logout Endpoint
 * Phase 3: Backend & API Foundation
 * 
 * Route: POST /api/auth/logout.php
 */

require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only allow POST requests
requireMethod('POST');

// Verify CSRF token if client is authenticated
if (isAuthenticated()) {
    requireCsrf();
}

// Destroy session and expire cookie
logoutUser();

sendSuccess('You have been securely signed out of ShopSphere.');


<?php
/**
 * ShopSphere - System Health Check Endpoint
 * Phase 3: Backend & API Foundation
 * 
 * Route: GET /api/health.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/helpers.php';

// Only allow GET requests
requireMethod('GET');

try {
    [$dbConnected, $dbMessage] = Database::testConnection();

    if (!$dbConnected) {
        sendJsonResponse(false, 'Service degraded: Database connection unavailable.', [], [
            'database' => 'disconnected'
        ], 503);
    }

    sendSuccess('ShopSphere backend is operational and database connection is healthy.', [
        'status'       => 'healthy',
        'php_version'  => PHP_VERSION,
        'database'     => 'connected',
        'server_time'  => date('Y-m-d H:i:s T')
    ]);

} catch (Throwable $e) {
    logSystemError('Health check failure: ' . $e->getMessage());
    sendJsonResponse(false, 'Backend service check failed.', [], [
        'service' => 'degraded'
    ], 503);
}


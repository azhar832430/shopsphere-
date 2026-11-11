<?php
/**
 * ShopSphere - Application Configuration
 * Phase 3: Backend & API Foundation
 * Phase 8: Production Readiness & Environment Configuration
 */

// Load .env configuration file if present in project root
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }
}

// Application Environment: 'development' or 'production'
defined('APP_ENV') or define('APP_ENV', getenv('APP_ENV') ?: 'development');

// Base Application Settings
defined('APP_NAME') or define('APP_NAME', 'ShopSphere');
defined('APP_VERSION') or define('APP_VERSION', '1.0.0');

// Default Timezone
date_default_timezone_set('UTC');

// Error Reporting Policy
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0'); // Do not expose PHP errors in HTTP response body
    ini_set('log_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Session Security Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    
    // If HTTPS is active, require secure cookies
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
}


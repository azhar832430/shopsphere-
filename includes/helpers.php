<?php
/**
 * ShopSphere - Global Helper Utilities
 * Phase 3: Backend & API Foundation
 */

require_once __DIR__ . '/response.php';

/**
 * Get normalized HTTP request method.
 */
function getRequestMethod(): string {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Ensure the incoming request matches the expected HTTP method(s).
 *
 * @param string|array $allowedMethods e.g. 'POST' or ['GET', 'POST']
 */
function requireMethod($allowedMethods): void {
    $current = getRequestMethod();
    $methods = is_array($allowedMethods) ? array_map('strtoupper', $allowedMethods) : [strtoupper($allowedMethods)];

    if (!in_array($current, $methods, true)) {
        sendMethodNotAllowed(implode(', ', $methods));
    }
}

/**
 * Safely parse JSON input from php://input or form-encoded POST.
 *
 * @return array Decoded payload
 */
function getJsonInput(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'multipart/form-data') !== false || stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
        return !empty($_POST) ? $_POST : [];
    }

    $raw = file_get_contents('php://input');

    if (empty($raw)) {
        // Fallback to standard $_POST if request was multipart or urlencoded
        return !empty($_POST) ? $_POST : [];
    }

    $decoded = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Malformed JSON payload received.', ['json' => json_last_error_msg()], 400);
    }

    return is_array($decoded) ? $decoded : [];
}

/**
 * Sanitize a string against null bytes and excessive whitespace.
 */
function sanitizeString(?string $str): string {
    if ($str === null) return '';
    $clean = str_replace(chr(0), '', $str);
    return trim($clean);
}

/**
 * Sanitize an associative array recursively.
 */
function sanitizeInput(array $data): array {
    $clean = [];
    foreach ($data as $key => $val) {
        if (is_array($val)) {
            $clean[$key] = sanitizeInput($val);
        } elseif (is_string($val)) {
            $clean[$key] = sanitizeString($val);
        } else {
            $clean[$key] = $val;
        }
    }
    return $clean;
}

/**
 * Safe error logger that writes to PHP's error_log with context.
 */
function logSystemError(string $message, array $context = []): void {
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    error_log("[ShopSphere Error] {$message}{$contextStr}");
}


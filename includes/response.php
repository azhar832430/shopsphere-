<?php
/**
 * ShopSphere - Standardized API Response Engine
 * Phase 3: Backend & API Foundation
 */

/**
 * Send a standardized JSON response and terminate script execution.
 *
 * @param bool $success
 * @param string $message
 * @param array|object $data
 * @param array $errors
 * @param int $statusCode
 * @return void
 */
function sendJsonResponse(bool $success, string $message = '', $data = [], array $errors = [], int $statusCode = 200): void {
    // If output buffer has any stray characters, clean it
    if (ob_get_length()) {
        ob_clean();
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
    }

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($success) {
        $response['data'] = (object) $data;
    } else {
        $response['errors'] = (object) $errors;
    }

    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send standard 200/201 Success Response.
 * Polymorphic: supports both (string $message, array $data = [], int $statusCode = 200)
 * and (array $data, string $message = '...', int $statusCode = 200).
 *
 * @param string|array|object $message
 * @param array|object|string $data
 * @param int $statusCode
 * @return void
 */
function sendSuccess($message = 'Operation completed successfully.', $data = [], int $statusCode = 200): void {
    if (is_array($message) || is_object($message)) {
        // Swapped arguments: sendSuccess($data, $message, $status)
        $actualData = $message;
        $actualMessage = (is_string($data) && $data !== '') ? $data : 'Operation completed successfully.';
        $actualStatusCode = is_int($data) ? $data : $statusCode;
        sendJsonResponse(true, $actualMessage, $actualData, [], $actualStatusCode);
        return;
    }

    $msg = is_string($message) ? $message : 'Operation completed successfully.';
    $payload = (is_array($data) || is_object($data)) ? $data : [];
    sendJsonResponse(true, $msg, $payload, [], $statusCode);
}

/**
 * Send standard 400 Client Error Response.
 */
function sendError(string $message = 'Invalid request parameters.', array $errors = [], int $statusCode = 400): void {
    sendJsonResponse(false, $message, [], $errors, $statusCode);
}

/**
 * Send standard 401 Unauthorized Response.
 */
function sendUnauthorized(string $message = 'Authentication required. Please sign in.'): void {
    sendJsonResponse(false, $message, [], ['auth' => 'Unauthenticated'], 401);
}

/**
 * Send standard 403 Forbidden Response.
 */
function sendForbidden(string $message = 'You do not have permission to perform this action.'): void {
    sendJsonResponse(false, $message, [], ['permission' => 'Access denied'], 403);
}

/**
 * Send standard 404 Not Found Response.
 */
function sendNotFound(string $message = 'The requested resource was not found.'): void {
    sendJsonResponse(false, $message, [], ['resource' => 'Not found'], 404);
}

/**
 * Send standard 405 Method Not Allowed Response.
 */
function sendMethodNotAllowed(string $allowed = 'GET'): void {
    header("Allow: {$allowed}");
    sendJsonResponse(false, "Method not allowed. Allowed methods: {$allowed}", [], ['method' => 'Method not allowed'], 405);
}

/**
 * Send standard 500 Internal Server Error Response (without sensitive leaks).
 * Logs internal error context safely and sends sanitized client response.
 *
 * @param string $message User-safe error description
 * @param array $errors Internal error details (logged server-side, sanitized for client)
 * @param int $statusCode HTTP status code (default 500)
 * @return void
 */
function sendServerError(string $message = 'An unexpected server error occurred. Please try again later.', array $errors = [], int $statusCode = 500): void {
    if (!empty($errors)) {
        if (function_exists('logSystemError')) {
            logSystemError($message, $errors);
        } else {
            error_log("[ShopSphere Internal Error] {$message} " . json_encode($errors));
        }
    }
    // Never expose raw SQL exceptions or traces to the client response
    sendJsonResponse(false, $message, [], ['server' => 'Internal server error occurred.'], $statusCode);
}


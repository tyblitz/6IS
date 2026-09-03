<?php
// backend/helpers/csrf.php
// Centralized CSRF Protection Middleware & Helpers for 6IS Platform

require_once __DIR__ . '/auth.php';
ensureSessionStarted();

/**
 * Generates a cryptographically secure CSRF token and stores it in the active session.
 * 
 * @return string The generated CSRF token
 */
function generateCsrfToken(): string {
    ensureSessionStarted();
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Retrieves the current session CSRF token, generating one if it does not yet exist.
 * 
 * @return string The session CSRF token
 */
function getCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        return generateCsrfToken();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates CSRF token for state-changing HTTP methods (POST, PUT, PATCH, DELETE).
 * Header-first verification via 'X-CSRF-Token', with input body fallback.
 * Uses constant-time hash_equals() to prevent timing attacks.
 * Rejects with HTTP 403 Forbidden on missing or invalid token.
 * 
 * @param array|null $parsedInput Optional already-parsed request body array
 */
function requireCsrf(?array $parsedInput = null): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    // Safe read methods are exempt
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or missing CSRF token.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 1. Primary: X-CSRF-Token header
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    // 2. Fallback: Request body or GET parameter if not in headers
    $bodyToken = '';
    if (empty($headerToken)) {
        if ($parsedInput !== null && isset($parsedInput['csrf_token'])) {
            $bodyToken = (string)$parsedInput['csrf_token'];
        } elseif (isset($_POST['csrf_token'])) {
            $bodyToken = (string)$_POST['csrf_token'];
        }
    }

    $providedToken = !empty($headerToken) ? $headerToken : $bodyToken;

    if (empty($providedToken) || !hash_equals($sessionToken, $providedToken)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or missing CSRF token.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

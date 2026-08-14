<?php
// backend/helpers/auth.php
// Reusable Backend Authorization Middleware & Helpers for 6IS

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensures a valid authenticated PHP session exists.
 * Rejects with HTTP 401 Unauthorized if unauthenticated.
 */
function requireAuth() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access. Login required.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

/**
 * Ensures the authenticated user has the specified session role.
 * Rejects with HTTP 403 Forbidden if the user lacks the required role.
 * 
 * @param string $requiredRole Role name ('Administrator', 'User', etc.)
 */
function requireRole($requiredRole = 'Administrator') {
    requireAuth();

    $currentRole = $_SESSION['role'] ?? '';
    if ($currentRole !== $requiredRole) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => "Forbidden. {$requiredRole} privileges required."
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

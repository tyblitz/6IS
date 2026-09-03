<?php
// backend/helpers/cors.php
// Centralized CORS Handler for 6IS Platform

/**
 * Applies strict CORS headers based exclusively on a server-side allowlist.
 * Never reflects arbitrary origins.
 * Never uses wildcard with credentials.
 */
function handleCors(): void {
    $configPath = __DIR__ . '/../config/cors.php';
    $allowedOrigins = file_exists($configPath) ? require $configPath : [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost',
        'http://127.0.0.1'
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Exact case-insensitive match against server-side allowlist only
    $isAllowed = false;
    $matchedOrigin = '';
    if (!empty($origin)) {
        foreach ($allowedOrigins as $allowed) {
            if (strcasecmp(rtrim($origin, '/'), rtrim($allowed, '/')) === 0) {
                $isAllowed = true;
                $matchedOrigin = $origin;
                break;
            }
        }
    }

    if ($isAllowed) {
        header("Access-Control-Allow-Origin: {$matchedOrigin}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-CSRF-Token');
    }

    // Handle preflight OPTIONS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if ($isAllowed || empty($origin)) {
            http_response_code(200);
        } else {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'CORS origin not allowed.'
            ], JSON_UNESCAPED_UNICODE);
        }
        exit();
    }
}

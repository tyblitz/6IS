<?php
// backend/config/cors.php
// Server-Side Configured CORS Allowed Origins for 6IS Platform

$defaultOrigins = [
    'http://localhost:8100',
    'http://127.0.0.1:8100',
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:4173',
    'http://127.0.0.1:4173',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost',
    'http://127.0.0.1'
];

// Production / Server environment defined origins
// Sourced strictly from server-side environment (e.g. system env), never from incoming request headers
$envOrigins = getenv('ALLOWED_ORIGINS');
if ($envOrigins !== false && trim($envOrigins) !== '') {
    $parsed = array_filter(array_map('trim', explode(',', $envOrigins)));
    $defaultOrigins = array_unique(array_merge($defaultOrigins, $parsed));
}

return $defaultOrigins;

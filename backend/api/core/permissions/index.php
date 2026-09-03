<?php
// backend/api/core/permissions/index.php
// REST API Endpoint for 6IS Core Permissions Catalog (Phase 2)

$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/permissions.php';

requireAuth();

function sendJsonResponse(bool $success, string $message, $data = null, $errors = null, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => $errors
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$configPath = __DIR__ . '/../../../config/database.php';
if (!file_exists($configPath)) {
    sendJsonResponse(false, 'Database configuration file missing.', null, null, 500);
}
$dbConfig = require $configPath;

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, 'Database connection failed.', null, ['db' => $e->getMessage()], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Requires 'roles.view' permission
    requirePermission('roles', 'view', $pdo);

    try {
        $stmt = $pdo->query("
            SELECT p.id, p.module_key, p.permission_key, p.name, p.description, p.is_active,
                   CONCAT(p.module_key, '.', p.permission_key) AS code,
                   COALESCE(m.name, p.module_key) AS module_name,
                   COALESCE(m.is_active, 1) AS module_is_active
            FROM tbl_permissions p
            LEFT JOIN tbl_modules m ON LOWER(p.module_key) = LOWER(m.module_key)
            WHERE p.is_active = 1
            ORDER BY 
                CASE p.module_key
                    WHEN 'inventory' THEN 1
                    WHEN 'communications' THEN 2
                    WHEN 'calendar' THEN 3
                    WHEN 'accomplishments' THEN 4
                    WHEN 'organization' THEN 5
                    WHEN 'offices' THEN 6
                    WHEN 'users' THEN 7
                    WHEN 'roles' THEN 8
                    WHEN 'modules' THEN 9
                    ELSE 10
                END,
                CASE p.permission_key
                    WHEN 'view' THEN 1
                    WHEN 'create' THEN 2
                    WHEN 'edit' THEN 3
                    WHEN 'delete' THEN 4
                    WHEN 'configure' THEN 5
                    ELSE 6
                END
        ");
        $permissions = $stmt->fetchAll();

        foreach ($permissions as &$p) {
            $p['id'] = (int)$p['id'];
            $p['is_active'] = (bool)$p['is_active'];
            $p['module_is_active'] = (bool)$p['module_is_active'];
        }

        // Group by module for convenient matrix consumption
        $grouped = [];
        foreach ($permissions as $p) {
            $modKey = $p['module_key'];
            if (!isset($grouped[$modKey])) {
                $grouped[$modKey] = [
                    'module_key' => $modKey,
                    'module_name' => $p['module_name'],
                    'module_is_active' => $p['module_is_active'],
                    'permissions' => []
                ];
            }
            $grouped[$modKey]['permissions'][] = $p;
        }

        sendJsonResponse(true, 'Permissions catalog retrieved successfully.', [
            'list' => $permissions,
            'grouped' => array_values($grouped)
        ]);
    } catch (Throwable $e) {
        sendJsonResponse(false, 'Failed to fetch permissions catalog.', null, ['db' => $e->getMessage()], 500);
    }
}

sendJsonResponse(false, 'Method Not Allowed.', null, null, 405);

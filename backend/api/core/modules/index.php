<?php
// backend/api/core/modules/index.php
// REST API Endpoint for 6IS Core Module Registry & Activation Management (Phase 4 Hardened)

require_once __DIR__ . '/../../../helpers/cors.php';
handleCors();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/audit.php';
require_once __DIR__ . '/../../../helpers/permissions.php';

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

// Load Database Config
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
    sendJsonResponse(false, 'Database connection failed: ' . $e->getMessage(), null, null, 500);
}

$method = $_SERVER['REQUEST_METHOD'];

// =========================================================================
// GET: Retrieve All Registered Modules (Authenticated Users)
// =========================================================================
if ($method === 'GET') {
    requireAuth();

    try {
        $stmt = $pdo->query("
            SELECT id, module_key, name, description, icon, route, is_core, is_active, sort_order, version
            FROM tbl_modules
            ORDER BY sort_order ASC, id ASC
        ");
        $rawModules = $stmt->fetchAll();

        $modules = array_map(function ($m) {
            return [
                'id' => (int)$m['id'],
                'module_key' => $m['module_key'],
                'name' => $m['name'],
                'description' => $m['description'],
                'icon' => $m['icon'],
                'route' => $m['route'],
                'is_core' => (bool)$m['is_core'],
                'is_active' => (bool)$m['is_active'],
                'sort_order' => (int)$m['sort_order'],
                'version' => $m['version']
            ];
        }, $rawModules);

        sendJsonResponse(true, 'Modules retrieved successfully.', $modules);
    } catch (PDOException $e) {
        sendJsonResponse(false, 'Failed to fetch module registry.', null, ['error' => $e->getMessage()], 500);
    }
}

// =========================================================================
// PATCH: Toggle Module Activation (Administrator Only)
// =========================================================================
if ($method === 'PATCH' || ($method === 'POST' && isset($_GET['_method']) && strtoupper($_GET['_method']) === 'PATCH')) {
    requireAuth();
    requirePermission('modules', 'configure', $pdo);
    requireRole('Administrator');

    $rawInput = file_get_contents('php://input');
    if (empty($rawInput) && !empty($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $rawInput = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $input = json_decode($rawInput, true) ?? [];

    requireCsrf($input);

    if (!is_array($input)) {
        sendJsonResponse(false, 'Invalid JSON body provided.', null, null, 400);
    }

    $moduleId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($input['id']) ? (int)$input['id'] : null);
    $moduleKey = isset($_GET['module_key']) ? trim($_GET['module_key']) : (isset($input['module_key']) ? trim($input['module_key']) : null);

    if (!$moduleId && !$moduleKey) {
        sendJsonResponse(false, 'Module identifier (id or module_key) is required.', null, null, 400);
    }

    if (!isset($input['is_active'])) {
        sendJsonResponse(false, "'is_active' field is required in request body.", null, null, 400);
    }

    $newActive = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($newActive === null) {
        sendJsonResponse(false, "'is_active' must be a valid boolean value.", null, null, 400);
    }

    if ($moduleId) {
        $stmt = $pdo->prepare("SELECT * FROM tbl_modules WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $moduleId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tbl_modules WHERE LOWER(module_key) = LOWER(:key) LIMIT 1");
        $stmt->execute([':key' => $moduleKey]);
    }

    $module = $stmt->fetch();
    if (!$module) {
        sendJsonResponse(false, 'Module not found in registry.', null, null, 404);
    }

    // Server-Side Invariant: Core modules cannot be disabled
    if ((int)$module['is_core'] === 1 && !$newActive) {
        sendJsonResponse(false, 'Core modules cannot be disabled.', null, null, 400);
    }

    try {
        $pdo->beginTransaction();

        $updateStmt = $pdo->prepare("
            UPDATE tbl_modules
            SET is_active = :is_active, updated_at = NOW()
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':is_active' => $newActive ? 1 : 0,
            ':id' => $module['id']
        ]);

        $actionType = $newActive ? 'ACTIVATE' : 'DEACTIVATE';
        auditLog([
            'action' => $actionType,
            'module_key' => 'modules',
            'entity_type' => 'module',
            'entity_id' => $module['module_key'],
            'description' => "Module '{$module['name']}' " . strtolower($actionType) . "d.",
            'old_values' => ['module_key' => $module['module_key'], 'is_active' => (bool)$module['is_active']],
            'new_values' => ['module_key' => $module['module_key'], 'is_active' => (bool)$newActive]
        ], $pdo);

        $pdo->commit();

        $stmt->execute($moduleId ? [':id' => $moduleId] : [':key' => $moduleKey]);
        $updated = $stmt->fetch();

        sendJsonResponse(true, "Module '{$updated['name']}' " . ($newActive ? 'activated' : 'deactivated') . " successfully.", [
            'id' => (int)$updated['id'],
            'module_key' => $updated['module_key'],
            'name' => $updated['name'],
            'description' => $updated['description'],
            'icon' => $updated['icon'],
            'route' => $updated['route'],
            'is_core' => (bool)$updated['is_core'],
            'is_active' => (bool)$updated['is_active'],
            'sort_order' => (int)$updated['sort_order'],
            'version' => $updated['version']
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[modules] Failed to update module state: ' . $e->getMessage());
        sendJsonResponse(false, 'Failed to update module state.', null, ['error' => $e->getMessage()], 500);
    }
}

sendJsonResponse(false, 'Invalid request method.', null, null, 405);

<?php
// backend/helpers/permissions.php
// Reusable Core Roles & Permissions Helper and Middleware for 6IS

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/modules.php';

/**
 * Returns a valid PDO connection.
 * 
 * @param PDO|null $pdo Existing database connection
 * @return PDO
 * @throws RuntimeException If configuration missing or connection fails
 */
function getPermissionDbConnection(?PDO $pdo = null): PDO {
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configPath = __DIR__ . '/../config/database.php';
    if (!file_exists($configPath)) {
        throw new RuntimeException('Database configuration file missing.');
    }

    $dbConfig = require $configPath;
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
    return new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

/**
 * Retrieves all effective permission keys ('module.action') for a user ID.
 * Resolves permissions through the user's assigned active role.
 * 
 * @param int $userId Target user ID
 * @param PDO|null $pdo Optional existing PDO connection
 * @return array List of permission strings, e.g. ['inventory.view', 'inventory.create']
 */
function getUserPermissions(int $userId, ?PDO $pdo = null): array {
    if ($userId <= 0) {
        return [];
    }

    try {
        $db = getPermissionDbConnection($pdo);

        $stmt = $db->prepare("
            SELECT DISTINCT CONCAT(p.module_key, '.', p.permission_key) AS perm_code
            FROM tbl_users u
            JOIN tbl_roles r ON u.role_id = r.id
            JOIN tbl_role_permissions rp ON r.id = rp.role_id
            JOIN tbl_permissions p ON rp.permission_id = p.id
            WHERE u.id = :user_id 
              AND u.deleted_at IS NULL 
              AND u.is_active = 1
              AND r.is_active = 1
              AND p.is_active = 1
            ORDER BY p.module_key ASC, p.permission_key ASC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Checks whether the currently authenticated session user possesses a specific permission.
 * 
 * @param string $moduleKey Module identifier ('inventory', 'communications', 'users', etc.)
 * @param string $permissionKey Action identifier ('view', 'create', 'edit', 'delete', 'configure')
 * @param PDO|null $pdo Optional existing PDO connection
 * @return bool True if authorized, false otherwise
 */
function hasPermission(string $moduleKey, string $permissionKey, ?PDO $pdo = null): bool {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    try {
        $db = getPermissionDbConnection($pdo);

        $stmt = $db->prepare("
            SELECT 1
            FROM tbl_users u
            JOIN tbl_roles r ON u.role_id = r.id
            JOIN tbl_role_permissions rp ON r.id = rp.role_id
            JOIN tbl_permissions p ON rp.permission_id = p.id
            WHERE u.id = :user_id
              AND u.deleted_at IS NULL
              AND u.is_active = 1
              AND r.is_active = 1
              AND LOWER(p.module_key) = LOWER(:module_key)
              AND LOWER(p.permission_key) = LOWER(:permission_key)
              AND p.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':module_key' => trim($moduleKey),
            ':permission_key' => trim($permissionKey)
        ]);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Enforces that:
 * 1. User has an active authenticated session (HTTP 401 if not).
 * 2. If the module is registered in tbl_modules, the module is currently active (HTTP 403 if disabled).
 * 3. User possesses the specific module permission (HTTP 403 if denied).
 * 
 * @param string $moduleKey Module identifier ('inventory', 'communications', 'users', etc.)
 * @param string $permissionKey Action identifier ('view', 'create', 'edit', 'delete', 'configure')
 * @param PDO|null $pdo Optional existing PDO connection
 */
function requirePermission(string $moduleKey, string $permissionKey, ?PDO $pdo = null): void {
    // 1. Enforce Authentication
    requireAuth();

    $db = getPermissionDbConnection($pdo);

    // 2. Enforce Module Activation if moduleKey corresponds to a registered business module
    $registeredBusinessModules = ['inventory', 'communications', 'calendar', 'accomplishments', 'performance', 'finances'];
    if (in_array(strtolower($moduleKey), $registeredBusinessModules)) {
        requireModuleActive($moduleKey, $db);
    }

    // 3. Enforce Role Permission
    if (!hasPermission($moduleKey, $permissionKey, $db)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to perform this action.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

<?php
// backend/api/core/roles/index.php
// REST API Endpoint for 6IS Core Roles Management (Phase 4 Hardened)

require_once __DIR__ . '/../../../helpers/cors.php';
handleCors();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/audit.php';
require_once __DIR__ . '/../../../helpers/permissions.php';

// Enforce authenticated session
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
$action = $_GET['action'] ?? '';

// Support CLI/Subprocess inputs when php://input is empty
$rawInput = file_get_contents('php://input');
if (empty($rawInput) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
    $rawInput = $GLOBALS['HTTP_RAW_POST_DATA'];
}
$input = json_decode($rawInput, true) ?? [];

// Enforce CSRF validation on mutating operations
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    requireCsrf($input);
}

// =========================================================================
// GET: List all roles or fetch single role with permissions
// =========================================================================
if ($method === 'GET') {
    requirePermission('roles', 'view', $pdo);

    $roleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($roleId > 0) {
        $stmt = $pdo->prepare("
            SELECT r.id, r.name, r.description, r.is_system, r.is_active, r.created_at, r.updated_at,
                   COUNT(DISTINCT u.id) AS user_count,
                   COUNT(DISTINCT rp.permission_id) AS permission_count
            FROM tbl_roles r
            LEFT JOIN tbl_users u ON r.id = u.role_id AND u.deleted_at IS NULL
            LEFT JOIN tbl_role_permissions rp ON r.id = rp.role_id
            WHERE r.id = :id
            GROUP BY r.id
            LIMIT 1
        ");
        $stmt->execute([':id' => $roleId]);
        $role = $stmt->fetch();

        if (!$role) {
            sendJsonResponse(false, 'Role not found.', null, null, 404);
        }

        $role['id'] = (int)$role['id'];
        $role['is_system'] = (bool)$role['is_system'];
        $role['is_active'] = (bool)$role['is_active'];
        $role['user_count'] = (int)$role['user_count'];
        $role['permission_count'] = (int)$role['permission_count'];

        // Fetch assigned permission IDs and permission codes
        $permStmt = $pdo->prepare("
            SELECT p.id, p.module_key, p.permission_key, CONCAT(p.module_key, '.', p.permission_key) AS code
            FROM tbl_role_permissions rp
            JOIN tbl_permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = :id AND p.is_active = 1
            ORDER BY p.module_key ASC, p.permission_key ASC
        ");
        $permStmt->execute([':id' => $roleId]);
        $assignedPerms = $permStmt->fetchAll();

        $role['permission_ids'] = array_map(function($p) { return (int)$p['id']; }, $assignedPerms);
        $role['permissions'] = array_map(function($p) { return $p['code']; }, $assignedPerms);

        sendJsonResponse(true, 'Role details retrieved.', $role);
    }

    $stmt = $pdo->query("
        SELECT r.id, r.name, r.description, r.is_system, r.is_active, r.created_at, r.updated_at,
               COUNT(DISTINCT u.id) AS user_count,
               COUNT(DISTINCT rp.permission_id) AS permission_count
        FROM tbl_roles r
        LEFT JOIN tbl_users u ON r.id = u.role_id AND u.deleted_at IS NULL
        LEFT JOIN tbl_role_permissions rp ON r.id = rp.role_id
        GROUP BY r.id
        ORDER BY r.is_system DESC, r.name ASC
    ");
    $roles = $stmt->fetchAll();

    foreach ($roles as &$r) {
        $r['id'] = (int)$r['id'];
        $r['is_system'] = (bool)$r['is_system'];
        $r['is_active'] = (bool)$r['is_active'];
        $r['user_count'] = (int)$r['user_count'];
        $r['permission_count'] = (int)$r['permission_count'];
    }

    sendJsonResponse(true, 'Roles retrieved successfully.', $roles);
}

// =========================================================================
// POST: Create custom role
// =========================================================================
if ($method === 'POST') {
    requirePermission('roles', 'create', $pdo);

    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '');
    $isActive = isset($input['is_active']) ? ((bool)$input['is_active'] ? 1 : 0) : 1;

    if (empty($name)) {
        sendJsonResponse(false, 'Role name is required.', null, null, 400);
    }

    // Check duplicate name
    $checkStmt = $pdo->prepare("SELECT id FROM tbl_roles WHERE LOWER(name) = LOWER(:name) LIMIT 1");
    $checkStmt->execute([':name' => $name]);
    if ($checkStmt->fetch()) {
        sendJsonResponse(false, "A role named '{$name}' already exists.", null, null, 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO tbl_roles (name, description, is_system, is_active, created_at, updated_at)
            VALUES (:name, :description, 0, :is_active, NOW(), NOW())
        ");
        $stmt->execute([
            ':name' => $name,
            ':description' => $description ?: null,
            ':is_active' => $isActive
        ]);

        $newId = (int)$pdo->lastInsertId();

        auditLog([
            'action' => 'CREATE',
            'module_key' => 'roles',
            'entity_type' => 'role',
            'entity_id' => (string)$newId,
            'description' => "Created custom role '{$name}'.",
            'old_values' => null,
            'new_values' => [
                'id' => $newId,
                'name' => $name,
                'description' => $description,
                'is_system' => false,
                'is_active' => (bool)$isActive
            ]
        ], $pdo);

        $pdo->commit();

        sendJsonResponse(true, "Role '{$name}' created successfully.", [
            'id' => $newId,
            'name' => $name,
            'description' => $description,
            'is_system' => false,
            'is_active' => (bool)$isActive,
            'user_count' => 0,
            'permission_count' => 0
        ], null, 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[roles] Failed to create role: ' . $e->getMessage());
        sendJsonResponse(false, 'Database failure creating role.', null, null, 500);
    }
}

// =========================================================================
// PATCH: Update role details or assigned permissions
// =========================================================================
if ($method === 'PATCH') {
    $roleId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($input['id'] ?? 0);
    if ($roleId <= 0) {
        sendJsonResponse(false, 'Valid role ID is required.', null, null, 400);
    }

    $stmt = $pdo->prepare("SELECT id, name, description, is_system, is_active FROM tbl_roles WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $roleId]);
    $existingRole = $stmt->fetch();

    if (!$existingRole) {
        sendJsonResponse(false, 'Role not found.', null, null, 404);
    }

    $isSystemRole = (int)$existingRole['is_system'] === 1;

    // A. Permission assignment replacement
    if ($action === 'permissions' || isset($input['permission_ids'])) {
        requirePermission('roles', 'configure', $pdo);

        $permissionIds = $input['permission_ids'] ?? [];
        if (!is_array($permissionIds)) {
            sendJsonResponse(false, 'permission_ids must be an array of integers.', null, null, 400);
        }

        // Validate all permission IDs exist and are active
        if (!empty($permissionIds)) {
            $inClause = implode(',', array_fill(0, count($permissionIds), '?'));
            $valStmt = $pdo->prepare("SELECT id FROM tbl_permissions WHERE id IN ({$inClause}) AND is_active = 1");
            $valStmt->execute(array_values($permissionIds));
            $foundIds = $valStmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($foundIds) !== count(array_unique($permissionIds))) {
                sendJsonResponse(false, 'One or more permission IDs are invalid or inactive.', null, null, 400);
            }
        }

        // Prevent stripping essential administration permissions from Administrator role
        if ($isSystemRole && strtolower($existingRole['name']) === 'administrator') {
            // Administrator must retain roles.configure
            $corePermCheck = $pdo->query("SELECT id FROM tbl_permissions WHERE module_key = 'roles' AND permission_key = 'configure' LIMIT 1")->fetchColumn();
            if ($corePermCheck && !in_array((int)$corePermCheck, array_map('intval', $permissionIds))) {
                sendJsonResponse(false, 'The Administrator role must retain roles.configure permission to avoid lock-out.', null, null, 400);
            }
        }

        // Fetch previous assigned permissions for audit
        $oldPermStmt = $pdo->prepare("SELECT permission_id FROM tbl_role_permissions WHERE role_id = :role_id");
        $oldPermStmt->execute([':role_id' => $roleId]);
        $oldPermIds = array_map('intval', $oldPermStmt->fetchAll(PDO::FETCH_COLUMN));

        try {
            $pdo->beginTransaction();

            $delStmt = $pdo->prepare("DELETE FROM tbl_role_permissions WHERE role_id = :role_id");
            $delStmt->execute([':role_id' => $roleId]);

            if (!empty($permissionIds)) {
                $insStmt = $pdo->prepare("
                    INSERT INTO tbl_role_permissions (role_id, permission_id, created_at)
                    VALUES (:role_id, :perm_id, NOW())
                ");
                foreach (array_unique($permissionIds) as $pid) {
                    $insStmt->execute([
                        ':role_id' => $roleId,
                        ':perm_id' => (int)$pid
                    ]);
                }
            }

            auditLog([
                'action' => 'ASSIGN',
                'module_key' => 'roles',
                'entity_type' => 'role_permissions',
                'entity_id' => (string)$roleId,
                'description' => "Updated permissions assignment for role '{$existingRole['name']}'.",
                'old_values' => ['role_id' => $roleId, 'permission_ids' => $oldPermIds],
                'new_values' => ['role_id' => $roleId, 'permission_ids' => array_values(array_map('intval', array_unique($permissionIds)))]
            ], $pdo);

            $pdo->commit();
            sendJsonResponse(true, "Permissions for role '{$existingRole['name']}' updated successfully.", [
                'role_id' => $roleId,
                'permission_count' => count(array_unique($permissionIds))
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[roles] Failed to update role permissions: ' . $e->getMessage());
            sendJsonResponse(false, 'Failed to update role permissions.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // B. Role Metadata Update (name, description, is_active)
    requirePermission('roles', 'edit', $pdo);

    $newName = isset($input['name']) ? trim($input['name']) : $existingRole['name'];
    $newDesc = isset($input['description']) ? trim($input['description']) : $existingRole['description'];
    $newActive = isset($input['is_active']) ? ((bool)$input['is_active'] ? 1 : 0) : (int)$existingRole['is_active'];

    // System role protection rules:
    if ($isSystemRole) {
        if (strcasecmp($newName, $existingRole['name']) !== 0) {
            sendJsonResponse(false, 'System roles cannot be renamed.', null, null, 400);
        }
        if ($newActive === 0) {
            sendJsonResponse(false, 'System roles cannot be deactivated.', null, null, 400);
        }
    }

    // Custom role name uniqueness
    if (strcasecmp($newName, $existingRole['name']) !== 0) {
        $chkName = $pdo->prepare("SELECT id FROM tbl_roles WHERE LOWER(name) = LOWER(:name) AND id != :id LIMIT 1");
        $chkName->execute([':name' => $newName, ':id' => $roleId]);
        if ($chkName->fetch()) {
            sendJsonResponse(false, "A role named '{$newName}' already exists.", null, null, 400);
        }
    }

    try {
        $pdo->beginTransaction();

        $upStmt = $pdo->prepare("
            UPDATE tbl_roles
            SET name = :name, description = :description, is_active = :is_active, updated_at = NOW()
            WHERE id = :id
        ");
        $upStmt->execute([
            ':name' => $newName,
            ':description' => $newDesc ?: null,
            ':is_active' => $newActive,
            ':id' => $roleId
        ]);

        // Synchronize legacy role column in tbl_users if role name changed
        if (strcasecmp($newName, $existingRole['name']) !== 0) {
            $syncUsers = $pdo->prepare("UPDATE tbl_users SET role = :name WHERE role_id = :role_id");
            $syncUsers->execute([':name' => $newName, ':role_id' => $roleId]);
        }

        auditLog([
            'action' => 'UPDATE',
            'module_key' => 'roles',
            'entity_type' => 'role',
            'entity_id' => (string)$roleId,
            'description' => "Updated role '{$newName}'.",
            'old_values' => [
                'name' => $existingRole['name'],
                'description' => $existingRole['description'],
                'is_active' => (int)$existingRole['is_active']
            ],
            'new_values' => [
                'name' => $newName,
                'description' => $newDesc,
                'is_active' => $newActive
            ]
        ], $pdo);

        $pdo->commit();

        sendJsonResponse(true, "Role '{$newName}' updated successfully.", [
            'id' => $roleId,
            'name' => $newName,
            'description' => $newDesc,
            'is_system' => $isSystemRole,
            'is_active' => (bool)$newActive
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[roles] Failed to update role: ' . $e->getMessage());
        sendJsonResponse(false, 'Database failure updating role.', null, null, 500);
    }
}

// =========================================================================
// DELETE: Delete unassigned custom role
// =========================================================================
if ($method === 'DELETE') {
    requirePermission('roles', 'delete', $pdo);

    $roleId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($input['id'] ?? 0);
    if ($roleId <= 0) {
        sendJsonResponse(false, 'Valid role ID is required.', null, null, 400);
    }

    $stmt = $pdo->prepare("SELECT id, name, is_system FROM tbl_roles WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $roleId]);
    $role = $stmt->fetch();

    if (!$role) {
        sendJsonResponse(false, 'Role not found.', null, null, 404);
    }

    // 1. Rejection: Protected System Role
    if ((int)$role['is_system'] === 1) {
        sendJsonResponse(false, 'System roles cannot be deleted.', null, null, 400);
    }

    // 2. Rejection: Role currently assigned to users
    $userCountStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE role_id = :id AND deleted_at IS NULL");
    $userCountStmt->execute([':id' => $roleId]);
    $assignedUserCount = (int)$userCountStmt->fetchColumn();

    if ($assignedUserCount > 0) {
        sendJsonResponse(false, "Cannot delete role because it is currently assigned to {$assignedUserCount} user(s).", null, null, 400);
    }

    // 3. Delete unassigned custom role in transaction
    try {
        $pdo->beginTransaction();

        $delPerms = $pdo->prepare("DELETE FROM tbl_role_permissions WHERE role_id = :id");
        $delPerms->execute([':id' => $roleId]);

        $delRole = $pdo->prepare("DELETE FROM tbl_roles WHERE id = :id");
        $delRole->execute([':id' => $roleId]);

        auditLog([
            'action' => 'DELETE',
            'module_key' => 'roles',
            'entity_type' => 'role',
            'entity_id' => (string)$roleId,
            'description' => "Deleted custom role '{$role['name']}'.",
            'old_values' => ['id' => $roleId, 'name' => $role['name'], 'is_system' => false],
            'new_values' => null
        ], $pdo);

        $pdo->commit();
        sendJsonResponse(true, "Role '{$role['name']}' deleted successfully.");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[roles] Failed to delete role: ' . $e->getMessage());
        sendJsonResponse(false, 'Failed to delete role.', null, ['db' => $e->getMessage()], 500);
    }
}

sendJsonResponse(false, 'Invalid request method.', null, null, 405);

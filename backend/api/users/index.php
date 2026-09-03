<?php
// backend/api/users/index.php
// REST API Endpoint for 6IS User Management (Phase 4 Hardened)

require_once __DIR__ . '/../../helpers/cors.php';
handleCors();

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/audit.php';
require_once __DIR__ . '/../../helpers/permissions.php';

header('Content-Type: application/json; charset=utf-8');

// Enforce both Authentication AND User View Authorization for GET requests
requireAuth();

function sendJsonResponse($success, $message, $data = null, $errors = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => $errors
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Load DB Config
$configPath = __DIR__ . '/../../config/database.php';
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

// GET Handler - List all user accounts
if ($method === 'GET') {
    requirePermission('users', 'view', $pdo);

    $stmt = $pdo->query("
        SELECT u.id, u.username, u.full_name, u.role_id, COALESCE(r.name, u.role) AS role,
               r.name AS role_name, r.is_active AS role_is_active,
               u.office_id, COALESCE(NULLIF(o.name, ''), o.office_name, o.code, o.office_code) AS office_name,
               COALESCE(NULLIF(o.code, ''), o.office_code, o.office_abbv) AS office_code,
               o.is_active AS office_is_active,
               u.is_active, u.created_at, u.updated_at
        FROM tbl_users u
        LEFT JOIN tbl_roles r ON u.role_id = r.id
        LEFT JOIN tbl_offices o ON u.office_id = o.id
        WHERE u.deleted_at IS NULL
        ORDER BY u.role_id ASC, u.username ASC
    ");
    $users = $stmt->fetchAll();
    
    // Map is_active and IDs
    foreach ($users as &$user) {
        $user['id'] = (int)$user['id'];
        $user['role_id'] = $user['role_id'] ? (int)$user['role_id'] : null;
        $user['office_id'] = $user['office_id'] ? (int)$user['office_id'] : null;
        $user['office_name'] = $user['office_name'] ?: null;
        $user['office_code'] = $user['office_code'] ?: null;
        $user['is_active'] = (int)$user['is_active'];
        $user['role'] = $user['role_name'] ?: $user['role'];
    }

    sendJsonResponse(true, 'User accounts retrieved.', $users);
}

// POST Handler - Administrative User Operations
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $rawInput = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $input = json_decode($rawInput, true) ?? [];

    // Enforce CSRF protection on all state-changing operations
    requireCsrf($input);

    // Helper: Resolve Administrator Role ID
    $getAdminRoleId = function() use ($pdo): int {
        $stmt = $pdo->query("SELECT id FROM tbl_roles WHERE LOWER(name) = 'administrator' LIMIT 1");
        return (int)($stmt->fetchColumn() ?: 1);
    };

    // Helper: Count other active Administrators in the system
    $countOtherActiveAdmins = function(int $excludeUserId) use ($pdo, $getAdminRoleId): int {
        $adminRoleId = $getAdminRoleId();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tbl_users
            WHERE (role_id = :role_id OR (role_id IS NULL AND LOWER(role) = 'administrator'))
              AND is_active = 1
              AND deleted_at IS NULL
              AND id != :exclude_id
        ");
        $stmt->execute([':role_id' => $adminRoleId, ':exclude_id' => $excludeUserId]);
        return (int)$stmt->fetchColumn();
    };

    // Helper: Resolve role_id and validate against tbl_roles
    $resolveRole = function(?int $roleId, ?string $roleName) use ($pdo): ?array {
        if ($roleId && $roleId > 0) {
            $stmt = $pdo->prepare("SELECT id, name, is_active FROM tbl_roles WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $roleId]);
            $role = $stmt->fetch();
            if ($role) return $role;
        }
        if (!empty($roleName)) {
            $stmt = $pdo->prepare("SELECT id, name, is_active FROM tbl_roles WHERE LOWER(name) = LOWER(:name) LIMIT 1");
            $stmt->execute([':name' => trim($roleName)]);
            $role = $stmt->fetch();
            if ($role) return $role;
        }
        return null;
    };

    // Helper: Validate office_id against tbl_offices
    $validateOffice = function($officeIdInput) use ($pdo): ?int {
        if ($officeIdInput === null || $officeIdInput === '' || (int)$officeIdInput <= 0) {
            return null;
        }
        $officeId = (int)$officeIdInput;
        $stmt = $pdo->prepare("
            SELECT id, is_active FROM tbl_offices 
            WHERE id = :id AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            LIMIT 1
        ");
        $stmt->execute([':id' => $officeId]);
        $office = $stmt->fetch();
        if (!$office) {
            sendJsonResponse(false, 'Selected office does not exist.', null, null, 400);
        }
        if ((int)$office['is_active'] !== 1) {
            sendJsonResponse(false, 'Cannot assign an inactive office to a user.', null, null, 400);
        }
        return $officeId;
    };

    // 1. Create New User Account
    if ($action === 'create') {
        requirePermission('users', 'create', $pdo);

        $username = trim($input['username'] ?? '');
        $fullName = trim($input['full_name'] ?? '');
        $password = trim($input['password'] ?? '');
        $roleIdInput = isset($input['role_id']) ? (int)$input['role_id'] : null;
        $roleNameInput = trim($input['role'] ?? 'User');
        $officeId = $validateOffice($input['office_id'] ?? null);

        if (empty($username) || empty($password)) {
            sendJsonResponse(false, 'Username and password are required.', null, null, 400);
        }

        $resolvedRole = $resolveRole($roleIdInput, $roleNameInput);
        if (!$resolvedRole || (int)$resolvedRole['is_active'] !== 1) {
            sendJsonResponse(false, 'Invalid or inactive role specified.', null, null, 400);
        }

        // Check for duplicate username
        $checkStmt = $pdo->prepare("SELECT id FROM tbl_users WHERE LOWER(username) = LOWER(:username) AND deleted_at IS NULL LIMIT 1");
        $checkStmt->execute([':username' => $username]);
        if ($checkStmt->fetch()) {
            sendJsonResponse(false, "Username '{$username}' already exists.", null, null, 400);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO tbl_users (username, full_name, password, role, role_id, office_id, is_active, created_at, updated_at)
                VALUES (:username, :full_name, :password, :role, :role_id, :office_id, 1, NOW(), NOW())
            ");
            $stmt->execute([
                ':username' => $username,
                ':full_name' => $fullName ?: $username,
                ':password' => $passwordHash,
                ':role' => $resolvedRole['name'],
                ':role_id' => (int)$resolvedRole['id'],
                ':office_id' => $officeId
            ]);
            $newUserId = (int)$pdo->lastInsertId();

            auditLog([
                'action' => 'CREATE',
                'module_key' => 'users',
                'entity_type' => 'user',
                'entity_id' => (string)$newUserId,
                'description' => "Created user account '{$username}' with role '{$resolvedRole['name']}'.",
                'old_values' => null,
                'new_values' => [
                    'id' => $newUserId,
                    'username' => $username,
                    'full_name' => $fullName ?: $username,
                    'role' => $resolvedRole['name'],
                    'role_id' => (int)$resolvedRole['id'],
                    'office_id' => $officeId,
                    'is_active' => 1
                ]
            ], $pdo);

            $pdo->commit();

            sendJsonResponse(true, "User account '{$username}' created successfully.", [
                'id' => $newUserId,
                'username' => $username,
                'role' => $resolvedRole['name'],
                'role_id' => (int)$resolvedRole['id'],
                'office_id' => $officeId
            ], null, 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[users] Failed to create user: ' . $e->getMessage());
            sendJsonResponse(false, 'Database failure creating user account.', null, null, 500);
        }
    }

    // 2. Update User Account (Full Name, Role, Office, Password)
    if ($action === 'update') {
        requirePermission('users', 'edit', $pdo);

        $userId = (int)($input['id'] ?? 0);
        $fullName = trim($input['full_name'] ?? '');
        $roleIdInput = isset($input['role_id']) ? (int)$input['role_id'] : null;
        $roleNameInput = trim($input['role'] ?? '');
        $password = trim($input['password'] ?? '');
        $hasOfficeKey = array_key_exists('office_id', $input);
        $officeId = $hasOfficeKey ? $validateOffice($input['office_id']) : null;

        if ($userId <= 0) {
            sendJsonResponse(false, 'Valid user ID is required.', null, null, 400);
        }

        $userStmt = $pdo->prepare("SELECT id, username, full_name, role, role_id, office_id, is_active FROM tbl_users WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $userStmt->execute([':id' => $userId]);
        $existingUser = $userStmt->fetch();

        if (!$existingUser) {
            sendJsonResponse(false, 'User not found.', null, null, 404);
        }

        $adminRoleId = $getAdminRoleId();
        $isExistingAdmin = ((int)$existingUser['role_id'] === $adminRoleId || strcasecmp($existingUser['role'], 'Administrator') === 0);

        $resolvedRole = null;
        if ($roleIdInput || $roleNameInput) {
            $resolvedRole = $resolveRole($roleIdInput, $roleNameInput);
            if (!$resolvedRole || (int)$resolvedRole['is_active'] !== 1) {
                sendJsonResponse(false, 'Invalid or inactive role specified.', null, null, 400);
            }
        }

        $targetRoleId = $resolvedRole ? (int)$resolvedRole['id'] : (int)$existingUser['role_id'];
        $targetRoleName = $resolvedRole ? $resolvedRole['name'] : $existingUser['role'];
        $targetOfficeId = $hasOfficeKey ? $officeId : ($existingUser['office_id'] ? (int)$existingUser['office_id'] : null);

        // Final Administrator Invariant Check:
        // If target is an active Administrator, but new role is not Administrator:
        if ($isExistingAdmin && (int)$existingUser['is_active'] === 1 && $targetRoleId !== $adminRoleId) {
            if ($countOtherActiveAdmins($userId) < 1) {
                sendJsonResponse(false, 'Cannot modify or deactivate the final active Administrator account.', null, null, 400);
            }
        }

        try {
            $pdo->beginTransaction();

            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    UPDATE tbl_users
                    SET full_name = :full_name, role = :role, role_id = :role_id, office_id = :office_id, password = :password, updated_at = NOW()
                    WHERE id = :id AND deleted_at IS NULL
                ");
                $stmt->execute([
                    ':full_name' => $fullName ?: $existingUser['username'],
                    ':role' => $targetRoleName,
                    ':role_id' => $targetRoleId,
                    ':office_id' => $targetOfficeId,
                    ':password' => $passwordHash,
                    ':id' => $userId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE tbl_users
                    SET full_name = :full_name, role = :role, role_id = :role_id, office_id = :office_id, updated_at = NOW()
                    WHERE id = :id AND deleted_at IS NULL
                ");
                $stmt->execute([
                    ':full_name' => $fullName ?: $existingUser['username'],
                    ':role' => $targetRoleName,
                    ':role_id' => $targetRoleId,
                    ':office_id' => $targetOfficeId,
                    ':id' => $userId
                ]);
            }

            // Write transactional audit log (passwords strictly excluded)
            auditLog([
                'action' => 'UPDATE',
                'module_key' => 'users',
                'entity_type' => 'user',
                'entity_id' => (string)$userId,
                'description' => "Updated user account '{$existingUser['username']}'.",
                'old_values' => [
                    'full_name' => $existingUser['full_name'],
                    'role' => $existingUser['role'],
                    'role_id' => (int)$existingUser['role_id'],
                    'office_id' => $existingUser['office_id'] ? (int)$existingUser['office_id'] : null
                ],
                'new_values' => [
                    'full_name' => $fullName ?: $existingUser['username'],
                    'role' => $targetRoleName,
                    'role_id' => $targetRoleId,
                    'office_id' => $targetOfficeId,
                    'password_changed' => !empty($password)
                ]
            ], $pdo);

            $pdo->commit();
            sendJsonResponse(true, "User account updated successfully.");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[users] Failed to update user: ' . $e->getMessage());
            sendJsonResponse(false, 'Database failure updating user account.', null, null, 500);
        }
    }

    // 3. Toggle Active State (Activate / Deactivate - NO Physical Delete)
    if ($action === 'toggle_active') {
        requirePermission('users', 'edit', $pdo);

        $userId = (int)($input['id'] ?? 0);
        $isActive = isset($input['is_active']) ? ((int)$input['is_active'] === 1 ? 1 : 0) : 0;

        if ($userId <= 0) {
            sendJsonResponse(false, 'Valid user ID is required.', null, null, 400);
        }

        $userStmt = $pdo->prepare("SELECT id, username, full_name, role, role_id, is_active FROM tbl_users WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $userStmt->execute([':id' => $userId]);
        $existingUser = $userStmt->fetch();

        if (!$existingUser) {
            sendJsonResponse(false, 'User not found.', null, null, 404);
        }

        $adminRoleId = $getAdminRoleId();
        $isExistingAdmin = ((int)$existingUser['role_id'] === $adminRoleId || strcasecmp($existingUser['role'], 'Administrator') === 0);

        // Deactivation checks
        if ($isActive === 0) {
            // Self-deactivation protection
            if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
                sendJsonResponse(false, 'You cannot deactivate your own account.', null, null, 400);
            }

            // Final Administrator Invariant Check
            if ($isExistingAdmin) {
                if ($countOtherActiveAdmins($userId) < 1) {
                    sendJsonResponse(false, 'Cannot modify or deactivate the final active Administrator account.', null, null, 400);
                }
            }
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE tbl_users
                SET is_active = :is_active, updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':is_active' => $isActive,
                ':id' => $userId
            ]);

            $actionType = $isActive === 1 ? 'ACTIVATE' : 'DEACTIVATE';
            auditLog([
                'action' => $actionType,
                'module_key' => 'users',
                'entity_type' => 'user',
                'entity_id' => (string)$userId,
                'description' => "User account '{$existingUser['username']}' " . strtolower($actionType) . "d.",
                'old_values' => ['is_active' => (int)$existingUser['is_active']],
                'new_values' => ['is_active' => $isActive]
            ], $pdo);

            $pdo->commit();

            $statusText = $isActive === 1 ? 'activated' : 'deactivated';
            sendJsonResponse(true, "User account has been {$statusText}.");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[users] Failed to toggle user active status: ' . $e->getMessage());
            sendJsonResponse(false, 'Database failure updating user status.', null, null, 500);
        }
    }

    // 4. Soft Delete User Account (if requested)
    if ($action === 'delete') {
        requirePermission('users', 'delete', $pdo);

        $userId = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($userId <= 0) {
            sendJsonResponse(false, 'Valid user ID is required.', null, null, 400);
        }

        $userStmt = $pdo->prepare("SELECT id, username, full_name, role, role_id, is_active FROM tbl_users WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $userStmt->execute([':id' => $userId]);
        $existingUser = $userStmt->fetch();

        if (!$existingUser) {
            sendJsonResponse(false, 'User not found.', null, null, 404);
        }

        $adminRoleId = $getAdminRoleId();
        $isExistingAdmin = ((int)$existingUser['role_id'] === $adminRoleId || strcasecmp($existingUser['role'], 'Administrator') === 0);

        if ($isExistingAdmin && (int)$existingUser['is_active'] === 1) {
            if ($countOtherActiveAdmins($userId) < 1) {
                sendJsonResponse(false, 'Cannot modify or deactivate the final active Administrator account.', null, null, 400);
            }
        }

        if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
            sendJsonResponse(false, 'You cannot delete your own currently active account.', null, null, 400);
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE tbl_users SET deleted_at = NOW(), is_active = 0 WHERE id = :id");
            $stmt->execute([':id' => $userId]);

            auditLog([
                'action' => 'DELETE',
                'module_key' => 'users',
                'entity_type' => 'user',
                'entity_id' => (string)$userId,
                'description' => "Soft-deleted user account '{$existingUser['username']}'.",
                'old_values' => ['is_active' => (int)$existingUser['is_active'], 'deleted_at' => null],
                'new_values' => ['is_active' => 0, 'deleted_at' => date('Y-m-d H:i:s')]
            ], $pdo);

            $pdo->commit();
            sendJsonResponse(true, "User account '{$existingUser['username']}' deleted successfully.");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[users] Failed to delete user: ' . $e->getMessage());
            sendJsonResponse(false, 'Database failure deleting user account.', null, null, 500);
        }
    }
}

sendJsonResponse(false, 'Invalid request method or endpoint.', null, null, 405);

<?php
// backend/api/users/index.php
// REST API Endpoint for 6IS User Management (Administrator Only)

$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enforce both Authentication AND Administrator Authorization for ALL methods (GET & POST)
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/permissions.php';
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

        sendJsonResponse(true, "User account '{$username}' created successfully.", [
            'id' => (int)$pdo->lastInsertId(),
            'username' => $username,
            'role' => $resolvedRole['name'],
            'role_id' => (int)$resolvedRole['id'],
            'office_id' => $officeId
        ], null, 201);
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

        $userStmt = $pdo->prepare("SELECT id, username, role, role_id, office_id FROM tbl_users WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $userStmt->execute([':id' => $userId]);
        $existingUser = $userStmt->fetch();

        if (!$existingUser) {
            sendJsonResponse(false, 'User not found.', null, null, 404);
        }

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

        sendJsonResponse(true, "User account updated successfully.");
    }

    // 3. Toggle Active State (Activate / Deactivate - NO Physical Delete)
    if ($action === 'toggle_active') {
        requirePermission('users', 'edit', $pdo);

        $userId = (int)($input['id'] ?? 0);
        $isActive = isset($input['is_active']) ? ((int)$input['is_active'] === 1 ? 1 : 0) : 0;

        if ($userId <= 0) {
            sendJsonResponse(false, 'Valid user ID is required.', null, null, 400);
        }

        // Prevent self-deactivation if updating current session user
        if ($userId === (int)($_SESSION['user_id'] ?? 0) && $isActive === 0) {
            sendJsonResponse(false, 'You cannot deactivate your own currently active administrator account.', null, null, 400);
        }

        $stmt = $pdo->prepare("
            UPDATE tbl_users
            SET is_active = :is_active, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':is_active' => $isActive,
            ':id' => $userId
        ]);

        $statusText = $isActive === 1 ? 'activated' : 'deactivated';
        sendJsonResponse(true, "User account has been {$statusText}.");
    }
}

sendJsonResponse(false, 'Invalid request method or endpoint.', null, null, 405);

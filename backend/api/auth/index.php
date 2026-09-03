<?php
// backend/api/auth/index.php
// REST API Endpoint for 6IS Authentication & Session Management (Phase 4 Hardened)

require_once __DIR__ . '/../../helpers/cors.php';
handleCors();

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/audit.php';
require_once __DIR__ . '/../../helpers/permissions.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Standardized JSON Response Helper
 */
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

// Load Database Config
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

// GET Method - Current User Check & CSRF Token Bootstrap
if ($method === 'GET') {
    $csrfToken = getCsrfToken();

    if (isset($_SESSION['user_id'])) {
        try {
            // Verify user is still active in database and resolve active role & office
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.role, u.role_id, u.office_id,
                       COALESCE(NULLIF(o.name, ''), o.office_name, o.code, o.office_code) AS office_name,
                       COALESCE(NULLIF(o.code, ''), o.office_code, o.office_abbv) AS office_code,
                       u.is_active, u.deleted_at, r.name AS role_name
                FROM tbl_users u
                LEFT JOIN tbl_roles r ON u.role_id = r.id
                LEFT JOIN tbl_offices o ON u.office_id = o.id
                WHERE u.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();

            if ($user && (int)$user['is_active'] === 1 && empty($user['deleted_at'])) {
                $effectiveRole = !empty($user['role_name']) ? $user['role_name'] : $user['role'];
                $permissions = getUserPermissions((int)$user['id'], $pdo);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'authenticated' => true,
                    'csrf_token' => $csrfToken,
                    'user' => [
                        'id' => (int)$user['id'],
                        'username' => $user['username'],
                        'role' => $effectiveRole,
                        'role_id' => $user['role_id'] ? (int)$user['role_id'] : null,
                        'office_id' => $user['office_id'] ? (int)$user['office_id'] : null,
                        'office_name' => $user['office_name'] ?: null,
                        'office_code' => $user['office_code'] ?: null,
                        'permissions' => $permissions
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit();
            } else {
                // Account disabled or soft deleted - destroy session
                session_unset();
                session_destroy();
            }
        } catch (Throwable $e) {
            sendJsonResponse(false, 'Database error retrieving user session.', null, null, 500);
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'csrf_token' => $csrfToken,
        'user' => null
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// POST Method - Login or Logout
if ($method === 'POST') {
    // Handle Logout
    if ($action === 'logout') {
        $logoutUserId = $_SESSION['user_id'] ?? null;
        $logoutUsername = $_SESSION['username'] ?? 'unknown';

        if ($logoutUserId) {
            try {
                auditLog([
                    'action' => 'LOGOUT',
                    'module_key' => 'core',
                    'entity_type' => 'authentication',
                    'entity_id' => (string)$logoutUserId,
                    'user_id' => (int)$logoutUserId,
                    'description' => "User '{$logoutUsername}' logged out.",
                    'old_values' => ['user_id' => (int)$logoutUserId, 'username' => $logoutUsername],
                    'new_values' => null
                ], $pdo);
            } catch (Throwable $e) {
                error_log('[auth] Audit log failure during logout: ' . $e->getMessage());
            }
        }

        session_unset();
        session_destroy();
        sendJsonResponse(true, 'Logged out successfully.');
    }

    // Handle Login
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $rawInput = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    $input = json_decode($rawInput, true);

    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        sendJsonResponse(false, 'Username and password are required.', null, null, 400);
    }

    // Query active user from tbl_users and join tbl_roles and tbl_offices
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.password, u.role, u.role_id, u.office_id,
               COALESCE(NULLIF(o.name, ''), o.office_name, o.code, o.office_code) AS office_name,
               COALESCE(NULLIF(o.code, ''), o.office_code, o.office_abbv) AS office_code,
               u.is_active, u.deleted_at, r.name AS role_name
        FROM tbl_users u
        LEFT JOIN tbl_roles r ON u.role_id = r.id
        LEFT JOIN tbl_offices o ON u.office_id = o.id
        WHERE LOWER(u.username) = LOWER(:username) AND u.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    // Check user existence, active status, and password hash verification
    if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, $user['password'])) {
        // Audit failed login without logging passwords, tokens, or hashes
        try {
            auditLog([
                'action' => 'LOGIN_FAILED',
                'module_key' => 'core',
                'entity_type' => 'authentication',
                'entity_id' => $user ? (string)$user['id'] : null,
                'user_id' => $user ? (int)$user['id'] : null,
                'description' => "Failed login attempt for username '{$username}'.",
                'old_values' => null,
                'new_values' => ['attempted_username' => $username]
            ], $pdo);
        } catch (Throwable $e) {
            error_log('[auth] Audit log failure during LOGIN_FAILED: ' . $e->getMessage());
        }

        sendJsonResponse(false, 'Invalid username or password.', null, null, 401);
    }

    // 1. Session Fixation Protection: Regenerate session ID upon successful credential verification
    session_regenerate_id(true);

    // 2. Generate fresh CSRF token for authenticated session
    $csrfToken = generateCsrfToken();

    $effectiveRole = !empty($user['role_name']) ? $user['role_name'] : $user['role'];

    // 3. Set authenticated session variables
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $effectiveRole;
    if (!empty($user['role_id'])) {
        $_SESSION['role_id'] = (int)$user['role_id'];
    }
    if (!empty($user['office_id'])) {
        $_SESSION['office_id'] = (int)$user['office_id'];
    }

    // 4. Audit successful LOGIN
    try {
        auditLog([
            'action' => 'LOGIN',
            'module_key' => 'core',
            'entity_type' => 'authentication',
            'entity_id' => (string)$user['id'],
            'user_id' => (int)$user['id'],
            'description' => "User '{$user['username']}' logged in successfully.",
            'old_values' => null,
            'new_values' => [
                'user_id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $effectiveRole
            ]
        ], $pdo);
    } catch (Throwable $e) {
        error_log('[auth] Audit log failure during LOGIN: ' . $e->getMessage());
        // Do not abort login if already authenticated, but report error log
    }

    $permissions = getUserPermissions((int)$user['id'], $pdo);

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'csrf_token' => $csrfToken,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'role' => $effectiveRole,
            'role_id' => $user['role_id'] ? (int)$user['role_id'] : null,
            'office_id' => $user['office_id'] ? (int)$user['office_id'] : null,
            'office_name' => $user['office_name'] ?: null,
            'office_code' => $user['office_code'] ?: null,
            'permissions' => $permissions
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

sendJsonResponse(false, 'Invalid request method.', null, null, 405);

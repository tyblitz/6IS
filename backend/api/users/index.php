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
requireAuth();
requireRole('Administrator'); // Returns 403 Forbidden for non-admins

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
    $stmt = $pdo->query("
        SELECT id, username, full_name, role, is_active, created_at, updated_at
        FROM tbl_users
        WHERE deleted_at IS NULL
        ORDER BY role ASC, username ASC
    ");
    $users = $stmt->fetchAll();
    
    // Map is_active to integer boolean
    foreach ($users as &$user) {
        $user['id'] = (int)$user['id'];
        $user['is_active'] = (int)$user['is_active'];
    }

    sendJsonResponse(true, 'User accounts retrieved.', $users);
}

// POST Handler - Administrative User Operations
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    // 1. Create New User Account
    if ($action === 'create') {
        $username = trim($input['username'] ?? '');
        $fullName = trim($input['full_name'] ?? '');
        $password = trim($input['password'] ?? '');
        $role = trim($input['role'] ?? 'User');

        if (empty($username) || empty($password)) {
            sendJsonResponse(false, 'Username and password are required.', null, null, 400);
        }

        if (!in_array($role, ['Administrator', 'User'])) {
            sendJsonResponse(false, 'Invalid role. Role must be Administrator or User.', null, null, 400);
        }

        // Check for duplicate username
        $checkStmt = $pdo->prepare("SELECT id FROM tbl_users WHERE LOWER(username) = LOWER(:username) AND deleted_at IS NULL LIMIT 1");
        $checkStmt->execute([':username' => $username]);
        if ($checkStmt->fetch()) {
            sendJsonResponse(false, "Username '{$username}' already exists.", null, null, 400);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO tbl_users (username, full_name, password, role, is_active, created_at, updated_at)
            VALUES (:username, :full_name, :password, :role, 1, NOW(), NOW())
        ");
        $stmt->execute([
            ':username' => $username,
            ':full_name' => $fullName ?: $username,
            ':password' => $passwordHash,
            ':role' => $role
        ]);

        sendJsonResponse(true, "User account '{$username}' created successfully.");
    }

    // 2. Update User Account (Full Name, Role, Password)
    if ($action === 'update') {
        $userId = (int)($input['id'] ?? 0);
        $fullName = trim($input['full_name'] ?? '');
        $role = trim($input['role'] ?? '');
        $password = trim($input['password'] ?? '');

        if ($userId <= 0) {
            sendJsonResponse(false, 'Valid user ID is required.', null, null, 400);
        }

        if (!in_array($role, ['Administrator', 'User'])) {
            sendJsonResponse(false, 'Invalid role specified.', null, null, 400);
        }

        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                UPDATE tbl_users
                SET full_name = :full_name, role = :role, password = :password, updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':full_name' => $fullName,
                ':role' => $role,
                ':password' => $passwordHash,
                ':id' => $userId
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE tbl_users
                SET full_name = :full_name, role = :role, updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':full_name' => $fullName,
                ':role' => $role,
                ':id' => $userId
            ]);
        }

        sendJsonResponse(true, "User account updated successfully.");
    }

    // 3. Toggle Active State (Activate / Deactivate - NO Physical Delete)
    if ($action === 'toggle_active') {
        $userId = (int)($input['id'] ?? 0);
        $isActive = isset($input['is_active']) ? ((int)$input['is_active'] === 1 ? 1 : 0) : 0;

        if ($userId <= 0) {
            sendJsonResponse(false, 'Valid user ID is required.', null, null, 400);
        }

        // Prevent self-deactivation if updating current session user
        if ($userId === (int)$_SESSION['user_id'] && $isActive === 0) {
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

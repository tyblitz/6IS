<?php
// backend/api/auth/index.php
// REST API Endpoint for 6IS Authentication & Session Management

// CORS & Credential Headers
$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start PHP Session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// GET Method - Current User Check
if ($method === 'GET') {
    if (isset($_SESSION['user_id'])) {
        // Verify user is still active in database
        $stmt = $pdo->prepare("SELECT id, username, role, is_active, deleted_at FROM tbl_users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && (int)$user['is_active'] === 1 && empty($user['deleted_at'])) {
            echo json_encode([
                'authenticated' => true,
                'user' => [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit();
        } else {
            // Account disabled or soft deleted - destroy session
            session_unset();
            session_destroy();
        }
    }

    echo json_encode([
        'authenticated' => false,
        'user' => null
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// POST Method - Login or Logout
if ($method === 'POST') {
    // Handle Logout
    if ($action === 'logout') {
        session_unset();
        session_destroy();
        sendJsonResponse(true, 'Logged out successfully.');
    }

    // Handle Login
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        sendJsonResponse(false, 'Username and password are required.', null, null, 400);
    }

    // Query active user from tbl_users
    $stmt = $pdo->prepare("
        SELECT id, username, password, role, is_active, deleted_at
        FROM tbl_users
        WHERE LOWER(username) = LOWER(:username) AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    // Check user existence, active status, and password hash verification
    if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, $user['password'])) {
        sendJsonResponse(false, 'Invalid username or password.', null, null, 401);
    }

    // Successful Login - Set Session Data
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

sendJsonResponse(false, 'Invalid request method.', null, null, 405);

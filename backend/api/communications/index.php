<?php
// backend/api/communications/index.php
// REST API Endpoint for 6IS Communications Module Management

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

// Require authenticated session
require_once __DIR__ . '/../../helpers/auth.php';
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
$view = $_GET['view'] ?? '';

// GET Handler
if ($method === 'GET') {
    // 1. List Communications
    if ($view === '' || $view === 'communications') {
        $stmt = $pdo->query("
            SELECT c.id, c.communication_type, c.communication_date, c.subject, c.office_id, o.office_abbv as originating_office,
                   c.category_id, cat.name as category_name, cat.code as category_code, c.purpose_id, p.name as purpose_name, c.status, c.created_at
            FROM tbl_communications c
            LEFT JOIN tbl_offices o ON c.office_id = o.id
            LEFT JOIN tbl_communication_categories cat ON c.category_id = cat.id
            LEFT JOIN tbl_communication_purposes p ON c.purpose_id = p.id
            WHERE c.deleted_at IS NULL
            ORDER BY c.communication_date DESC, c.id DESC
        ");
        $comms = $stmt->fetchAll();
        sendJsonResponse(true, 'Communications retrieved.', $comms);
    }

    // 2. List Categories
    if ($view === 'categories') {
        $stmt = $pdo->query("SELECT id, name as category_name, code, is_active FROM tbl_communication_categories WHERE deleted_at IS NULL ORDER BY name ASC");
        $categories = $stmt->fetchAll();
        sendJsonResponse(true, 'Communication categories retrieved.', $categories);
    }

    // 3. List Purposes
    if ($view === 'purposes') {
        $stmt = $pdo->query("SELECT id, name as purpose_name, is_active FROM tbl_communication_purposes WHERE deleted_at IS NULL ORDER BY name ASC");
        $purposes = $stmt->fetchAll();
        sendJsonResponse(true, 'Communication purposes retrieved.', $purposes);
    }

    // 4. List Statuses
    if ($view === 'statuses') {
        $stmt = $pdo->query("SELECT id, name as status_name, is_active FROM tbl_communication_statuses WHERE deleted_at IS NULL ORDER BY id ASC");
        $statuses = $stmt->fetchAll();
        sendJsonResponse(true, 'Communication statuses retrieved.', $statuses);
    }
}

// POST Handler (Requires Administrator role for management actions)
if ($method === 'POST') {
    requireRole('Administrator'); // Enforce Administrator authorization

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    // 1. Create or Update Communication
    if ($action === 'create_communication' || $action === 'update_communication') {
        $id = (int)($input['id'] ?? 0);
        $commType = trim($input['communication_type'] ?? 'Incoming');
        $commDate = trim($input['communication_date'] ?? date('Y-m-d'));
        $subject = trim($input['subject'] ?? '');
        $officeId = (int)($input['office_id'] ?? 1);
        $categoryId = (int)($input['category_id'] ?? 1);
        $purposeId = (int)($input['purpose_id'] ?? 1);
        $status = trim($input['status'] ?? ($commType === 'Incoming' ? 'Pending' : 'Released'));

        if (empty($subject)) {
            sendJsonResponse(false, 'Subject is required.', null, null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE tbl_communications
                SET communication_type = :type, office_id = :office_id, category_id = :cat_id, purpose_id = :purpose_id,
                    subject = :subject, communication_date = :date, status = :status, updated_at = NOW(), modified_by = :modified_by
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':type' => $commType,
                ':office_id' => $officeId,
                ':cat_id' => $categoryId,
                ':purpose_id' => $purposeId,
                ':subject' => $subject,
                ':date' => $commDate,
                ':status' => $status,
                ':modified_by' => $_SESSION['user_id'],
                ':id' => $id
            ]);
            sendJsonResponse(true, "Communication record updated successfully.");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_communications (communication_type, office_id, category_id, purpose_id, subject, communication_date, status, created_at, updated_at, created_by, modified_by)
                VALUES (:type, :office_id, :cat_id, :purpose_id, :subject, :date, :status, NOW(), NOW(), :created_by, :modified_by)
            ");
            $stmt->execute([
                ':type' => $commType,
                ':office_id' => $officeId,
                ':cat_id' => $categoryId,
                ':purpose_id' => $purposeId,
                ':subject' => $subject,
                ':date' => $commDate,
                ':status' => $status,
                ':created_by' => $_SESSION['user_id'],
                ':modified_by' => $_SESSION['user_id']
            ]);
            sendJsonResponse(true, "Communication record created successfully.");
        }
    }

    // 2. Soft Delete Communication
    if ($action === 'delete_communication') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            sendJsonResponse(false, 'Valid communication ID is required.', null, null, 400);
        }

        $stmt = $pdo->prepare("UPDATE tbl_communications SET deleted_at = NOW(), modified_by = :modified_by WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute([':modified_by' => $_SESSION['user_id'], ':id' => $id]);

        sendJsonResponse(true, "Communication record soft-deleted successfully.");
    }

    // 3. Save Category (Add / Update)
    if ($action === 'save_category') {
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['category_name'] ?? '');
        $code = trim($input['code'] ?? '');
        $isActive = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;

        if (empty($name)) {
            sendJsonResponse(false, 'Category name is required.', null, null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE tbl_communication_categories SET name = :name, code = :code, is_active = :active, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':name' => $name, ':code' => $code ?: null, ':active' => $isActive, ':id' => $id]);
            sendJsonResponse(true, "Communication category updated successfully.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO tbl_communication_categories (name, code, is_active, created_at, updated_at) VALUES (:name, :code, :active, NOW(), NOW())");
            $stmt->execute([':name' => $name, ':code' => $code ?: null, ':active' => $isActive]);
            sendJsonResponse(true, "Communication category created successfully.");
        }
    }

    // 4. Delete Category
    if ($action === 'delete_category') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Valid ID is required.', null, null, 400);
        $stmt = $pdo->prepare("UPDATE tbl_communication_categories SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        sendJsonResponse(true, "Communication category deleted successfully.");
    }

    // 5. Save Purpose (Add / Update)
    if ($action === 'save_purpose') {
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['purpose_name'] ?? '');
        $isActive = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;

        if (empty($name)) {
            sendJsonResponse(false, 'Purpose name is required.', null, null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE tbl_communication_purposes SET name = :name, is_active = :active, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':name' => $name, ':active' => $isActive, ':id' => $id]);
            sendJsonResponse(true, "Communication purpose updated successfully.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO tbl_communication_purposes (name, is_active, created_at, updated_at) VALUES (:name, :active, NOW(), NOW())");
            $stmt->execute([':name' => $name, ':active' => $isActive]);
            sendJsonResponse(true, "Communication purpose created successfully.");
        }
    }

    // 6. Delete Purpose
    if ($action === 'delete_purpose') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Valid ID is required.', null, null, 400);
        $stmt = $pdo->prepare("UPDATE tbl_communication_purposes SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        sendJsonResponse(true, "Communication purpose deleted successfully.");
    }

    // 7. Save Status (Add / Update)
    if ($action === 'save_status') {
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['status_name'] ?? '');
        $isActive = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;

        if (empty($name)) {
            sendJsonResponse(false, 'Status name is required.', null, null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE tbl_communication_statuses SET name = :name, is_active = :active, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':name' => $name, ':active' => $isActive, ':id' => $id]);
            sendJsonResponse(true, "Communication status updated successfully.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO tbl_communication_statuses (name, is_active, created_at, updated_at) VALUES (:name, :active, NOW(), NOW())");
            $stmt->execute([':name' => $name, ':active' => $isActive]);
            sendJsonResponse(true, "Communication status created successfully.");
        }
    }

    // 8. Delete Status
    if ($action === 'delete_status') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Valid ID is required.', null, null, 400);
        $stmt = $pdo->prepare("UPDATE tbl_communication_statuses SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        sendJsonResponse(true, "Communication status deleted successfully.");
    }
}

sendJsonResponse(false, 'Invalid request method or endpoint.', null, null, 405);

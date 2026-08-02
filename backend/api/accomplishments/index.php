<?php
// backend/api/accomplishments/index.php
// REST API Endpoint for 6IS Accomplishment Module

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight CORS OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Standardized Response Helper
 */
function sendResponse($success, $message, $data = null, $errors = null, $statusCode = 200) {
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
    sendResponse(false, 'Database configuration file missing.', null, null, 500);
}

$dbConfig = require $configPath;

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    sendResponse(false, 'Database connection failed: ' . $e->getMessage(), null, ['db' => $e->getMessage()], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo);
        break;
    case 'PUT':
        handlePut($pdo);
        break;
    case 'DELETE':
        handleDelete($pdo);
        break;
    default:
        sendResponse(false, 'HTTP Method Not Allowed', null, null, 405);
}

/**
 * GET Handler
 */
function handleGet(PDO $pdo) {
    $view = isset($_GET['view']) ? trim($_GET['view']) : '';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // View: Return Reference Options for Form Select Dropdowns
    if ($view === 'options') {
        try {
            $offices = $pdo->query("SELECT id, office_name, office_code FROM tbl_offices WHERE deleted_at IS NULL ORDER BY office_name ASC")->fetchAll();
            $categories = $pdo->query("SELECT id, category_name FROM tbl_categories WHERE deleted_at IS NULL ORDER BY category_name ASC")->fetchAll();
            $users = $pdo->query("SELECT id, full_name, username, role FROM tbl_users WHERE deleted_at IS NULL ORDER BY full_name ASC")->fetchAll();

            sendResponse(true, 'Options fetched successfully.', [
                'offices' => $offices,
                'categories' => $categories,
                'users' => $users
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch dropdown options.', null, ['error' => $e->getMessage()], 500);
        }
    }

    // View: Single Record by ID
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                a.*,
                o.office_name,
                o.office_code,
                c.category_name,
                u.full_name AS assigned_employee_name
            FROM tbl_accomplishments a
            LEFT JOIN tbl_offices o ON a.office_id = o.id
            LEFT JOIN tbl_categories c ON a.category_id = c.id
            LEFT JOIN tbl_users u ON a.assigned_employee_id = u.id
            WHERE a.id = :id AND a.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $record = $stmt->fetch();

        if (!$record) {
            sendResponse(false, 'Accomplishment record not found.', null, null, 404);
        }

        sendResponse(true, 'Accomplishment details fetched successfully.', $record);
    }

    // View: Overview Today (Compact 4-column current-day table)
    if ($view === 'overview_today') {
        $sql = "
            SELECT 
                a.id,
                a.title,
                u.full_name AS assigned_employee_name,
                o.office_name,
                o.office_code,
                c.category_name
            FROM tbl_accomplishments a
            LEFT JOIN tbl_offices o ON a.office_id = o.id
            LEFT JOIN tbl_categories c ON a.category_id = c.id
            LEFT JOIN tbl_users u ON a.assigned_employee_id = u.id
            WHERE a.date_started = CURDATE() AND a.deleted_at IS NULL
            ORDER BY a.created_at DESC
        ";
        $records = $pdo->query($sql)->fetchAll();
        sendResponse(true, 'Today accomplishments fetched successfully.', $records);
    }

    // View: General List / Detailed Reports View
    $where = ["a.deleted_at IS NULL"];
    $params = [];

    if ($view === 'daily') {
        $where[] = "a.date_started = CURDATE()";
    } elseif ($view === 'monthly') {
        $where[] = "MONTH(a.date_started) = MONTH(CURDATE()) AND YEAR(a.date_started) = YEAR(CURDATE())";
    } elseif ($view === 'annual') {
        $where[] = "YEAR(a.date_started) = YEAR(CURDATE())";
    }

    if (!empty($_GET['search'])) {
        $where[] = "(a.title LIKE :search OR a.description LIKE :search OR u.full_name LIKE :search)";
        $params[':search'] = '%' . trim($_GET['search']) . '%';
    }

    if (!empty($_GET['status'])) {
        $where[] = "a.status = :status";
        $params[':status'] = trim($_GET['status']);
    }

    if (!empty($_GET['priority'])) {
        $where[] = "a.priority = :priority";
        $params[':priority'] = trim($_GET['priority']);
    }

    if (!empty($_GET['office_id'])) {
        $where[] = "a.office_id = :office_id";
        $params[':office_id'] = (int)$_GET['office_id'];
    }

    if (!empty($_GET['category_id'])) {
        $where[] = "a.category_id = :category_id";
        $params[':category_id'] = (int)$_GET['category_id'];
    }

    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT 
            a.*,
            o.office_name,
            o.office_code,
            c.category_name,
            u.full_name AS assigned_employee_name
        FROM tbl_accomplishments a
        LEFT JOIN tbl_offices o ON a.office_id = o.id
        LEFT JOIN tbl_categories c ON a.category_id = c.id
        LEFT JOIN tbl_users u ON a.assigned_employee_id = u.id
        WHERE {$whereSql}
        ORDER BY a.date_started DESC, a.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    sendResponse(true, 'Accomplishments list fetched successfully.', $records);
}

/**
 * POST Handler (Create)
 */
function handlePost(PDO $pdo) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data) {
        sendResponse(false, 'Invalid JSON payload.', null, null, 400);
    }

    $errors = validatePayload($pdo, $data);
    if (!empty($errors)) {
        sendResponse(false, 'Validation failed.', null, $errors, 400);
    }

    $title = trim($data['title']);
    $description = isset($data['description']) ? trim($data['description']) : null;
    $officeId = (int)$data['office_id'];
    $categoryId = (int)$data['category_id'];
    $assignedEmployeeId = (int)$data['assigned_employee_id'];
    $dateStarted = trim($data['date_started']);
    $dateCompleted = !empty($data['date_completed']) ? trim($data['date_completed']) : null;
    $status = trim($data['status']);
    $priority = trim($data['priority']);
    $remarks = isset($data['remarks']) ? trim($data['remarks']) : null;

    // Auto completion date setting if status completed
    if ($status === 'Completed' && empty($dateCompleted)) {
        $dateCompleted = date('Y-m-d');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tbl_accomplishments 
            (title, description, office_id, category_id, assigned_employee_id, date_started, date_completed, status, priority, remarks, created_at, updated_at, created_by, modified_by)
            VALUES 
            (:title, :description, :office_id, :category_id, :assigned_employee_id, :date_started, :date_completed, :status, :priority, :remarks, NOW(), NOW(), 1, 1)
        ");
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':office_id' => $officeId,
            ':category_id' => $categoryId,
            ':assigned_employee_id' => $assignedEmployeeId,
            ':date_started' => $dateStarted,
            ':date_completed' => $dateCompleted,
            ':status' => $status,
            ':priority' => $priority,
            ':remarks' => $remarks
        ]);

        $newId = $pdo->lastInsertId();
        sendResponse(true, 'Accomplishment created successfully.', ['id' => $newId], null, 201);
    } catch (Exception $e) {
        sendResponse(false, 'Failed to create accomplishment record.', null, ['db' => $e->getMessage()], 500);
    }
}

/**
 * PUT Handler (Update)
 */
function handlePut(PDO $pdo) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if ($id <= 0 && isset($data['id'])) {
        $id = (int)$data['id'];
    }

    if ($id <= 0) {
        sendResponse(false, 'Record ID is required for update.', null, null, 400);
    }

    // Verify existing active record
    $checkStmt = $pdo->prepare("SELECT id FROM tbl_accomplishments WHERE id = :id AND deleted_at IS NULL");
    $checkStmt->execute([':id' => $id]);
    if (!$checkStmt->fetch()) {
        sendResponse(false, 'Accomplishment record not found.', null, null, 404);
    }

    $errors = validatePayload($pdo, $data, true);
    if (!empty($errors)) {
        sendResponse(false, 'Validation failed.', null, $errors, 400);
    }

    $title = trim($data['title']);
    $description = isset($data['description']) ? trim($data['description']) : null;
    $officeId = (int)$data['office_id'];
    $categoryId = (int)$data['category_id'];
    $assignedEmployeeId = (int)$data['assigned_employee_id'];
    $dateStarted = trim($data['date_started']);
    $dateCompleted = !empty($data['date_completed']) ? trim($data['date_completed']) : null;
    $status = trim($data['status']);
    $priority = trim($data['priority']);
    $remarks = isset($data['remarks']) ? trim($data['remarks']) : null;

    if ($status === 'Completed' && empty($dateCompleted)) {
        $dateCompleted = date('Y-m-d');
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE tbl_accomplishments SET
                title = :title,
                description = :description,
                office_id = :office_id,
                category_id = :category_id,
                assigned_employee_id = :assigned_employee_id,
                date_started = :date_started,
                date_completed = :date_completed,
                status = :status,
                priority = :priority,
                remarks = :remarks,
                updated_at = NOW(),
                modified_by = 1
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $id,
            ':title' => $title,
            ':description' => $description,
            ':office_id' => $officeId,
            ':category_id' => $categoryId,
            ':assigned_employee_id' => $assignedEmployeeId,
            ':date_started' => $dateStarted,
            ':date_completed' => $dateCompleted,
            ':status' => $status,
            ':priority' => $priority,
            ':remarks' => $remarks
        ]);

        sendResponse(true, 'Accomplishment updated successfully.', ['id' => $id]);
    } catch (Exception $e) {
        sendResponse(false, 'Failed to update accomplishment record.', null, ['db' => $e->getMessage()], 500);
    }
}

/**
 * DELETE Handler (Soft Delete)
 */
function handleDelete(PDO $pdo) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        sendResponse(false, 'Record ID is required for deletion.', null, null, 400);
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE tbl_accomplishments 
            SET deleted_at = NOW(), modified_by = 1 
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            sendResponse(false, 'Record not found or already deleted.', null, null, 404);
        }

        sendResponse(true, 'Accomplishment deleted successfully.', ['id' => $id]);
    } catch (Exception $e) {
        sendResponse(false, 'Failed to delete accomplishment record.', null, ['db' => $e->getMessage()], 500);
    }
}

/**
 * Payload Validation Helper
 */
function validatePayload(PDO $pdo, $data, $isUpdate = false) {
    $errors = [];

    if (empty($data['title']) || strlen(trim($data['title'])) === 0) {
        $errors['title'] = 'Title is required.';
    } elseif (strlen(trim($data['title'])) > 255) {
        $errors['title'] = 'Title cannot exceed 255 characters.';
    }

    if (empty($data['office_id']) || (int)$data['office_id'] <= 0) {
        $errors['office_id'] = 'Office is required.';
    } else {
        $check = $pdo->prepare("SELECT id FROM tbl_offices WHERE id = :id AND deleted_at IS NULL");
        $check->execute([':id' => (int)$data['office_id']]);
        if (!$check->fetch()) {
            $errors['office_id'] = 'Selected office does not exist.';
        }
    }

    if (empty($data['category_id']) || (int)$data['category_id'] <= 0) {
        $errors['category_id'] = 'Category is required.';
    } else {
        $check = $pdo->prepare("SELECT id FROM tbl_categories WHERE id = :id AND deleted_at IS NULL");
        $check->execute([':id' => (int)$data['category_id']]);
        if (!$check->fetch()) {
            $errors['category_id'] = 'Selected category does not exist.';
        }
    }

    if (empty($data['assigned_employee_id']) || (int)$data['assigned_employee_id'] <= 0) {
        $errors['assigned_employee_id'] = 'Assigned employee is required.';
    } else {
        $check = $pdo->prepare("SELECT id FROM tbl_users WHERE id = :id AND deleted_at IS NULL");
        $check->execute([':id' => (int)$data['assigned_employee_id']]);
        if (!$check->fetch()) {
            $errors['assigned_employee_id'] = 'Selected employee does not exist.';
        }
    }

    if (empty($data['date_started'])) {
        $errors['date_started'] = 'Date started is required.';
    }

    if (!empty($data['date_started']) && !empty($data['date_completed'])) {
        if (strtotime($data['date_completed']) < strtotime($data['date_started'])) {
            $errors['date_completed'] = 'Date completed cannot be earlier than date started.';
        }
    }

    $validStatuses = ['Pending', 'Ongoing', 'Completed', 'Cancelled'];
    if (empty($data['status']) || !in_array($data['status'], $validStatuses)) {
        $errors['status'] = 'Valid status is required.';
    }

    $validPriorities = ['Low', 'Medium', 'High', 'Critical'];
    if (empty($data['priority']) || !in_array($data['priority'], $validPriorities)) {
        $errors['priority'] = 'Valid priority level is required.';
    }

    return $errors;
}

<?php
// backend/api/accomplishments/index.php
// REST API Endpoint for 6IS Accomplishment Module & Admin Category Management

$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight CORS OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../helpers/auth.php';
requireAuth();

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

    // View: Return Reference Categories
    if ($view === 'categories') {
        $categories = $pdo->query("
            SELECT id, category_name, category_code, created_at, updated_at
            FROM tbl_accomplishment_categories
            WHERE deleted_at IS NULL
            ORDER BY id ASC
        ")->fetchAll();
        sendResponse(true, 'Categories fetched successfully.', $categories);
    }

    // View: Return Reference Options (Offices + Categories)
    if ($view === 'options') {
        try {
            $offices = $pdo->query("SELECT id, office_name, office_code, office_abbv FROM tbl_offices WHERE deleted_at IS NULL ORDER BY office_abbv ASC")->fetchAll();
            $categories = $pdo->query("SELECT id, category_name, category_code FROM tbl_accomplishment_categories WHERE deleted_at IS NULL ORDER BY id ASC")->fetchAll();
            sendResponse(true, 'Options fetched successfully.', [
                'offices' => $offices,
                'categories' => $categories
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
                o.office_abbv
            FROM tbl_accomplishments a
            LEFT JOIN tbl_offices o ON a.office_id = o.id
            WHERE a.id = :id AND a.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $record = $stmt->fetch();

        if (!$record) {
            sendResponse(false, 'Accomplishment record not found.', null, null, 404);
        }

        sendResponse(true, 'Accomplishment details fetched successfully.', $record);
    }

    // View: Overview Dashboard
    if ($view === 'overview') {
        try {
            $todayCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments WHERE `date` = CURDATE() AND deleted_at IS NULL")->fetchColumn();
            $monthlyCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments WHERE MONTH(`date`) = MONTH(CURDATE()) AND YEAR(`date`) = YEAR(CURDATE()) AND deleted_at IS NULL")->fetchColumn();
            $quarterlyCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments WHERE QUARTER(`date`) = QUARTER(CURDATE()) AND YEAR(`date`) = YEAR(CURDATE()) AND deleted_at IS NULL")->fetchColumn();
            $annualCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments WHERE YEAR(`date`) = YEAR(CURDATE()) AND deleted_at IS NULL")->fetchColumn();

            $todayRecordsStmt = $pdo->query("
                SELECT 
                    a.id,
                    a.office_id,
                    a.date,
                    a.description,
                    a.remarks,
                    o.office_name,
                    o.office_code,
                    o.office_abbv
                FROM tbl_accomplishments a
                LEFT JOIN tbl_offices o ON a.office_id = o.id
                WHERE a.date = CURDATE() AND a.deleted_at IS NULL
                ORDER BY a.created_at DESC
            ");
            $todayRecords = $todayRecordsStmt->fetchAll();

            sendResponse(true, 'Overview summary fetched successfully.', [
                'counts' => [
                    'today' => $todayCount,
                    'monthly' => $monthlyCount,
                    'quarterly' => $quarterlyCount,
                    'annual' => $annualCount,
                    'incoming_comms' => 0,
                    'outgoing_comms' => 0
                ],
                'today_records' => $todayRecords
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch overview summary.', null, ['error' => $e->getMessage()], 500);
        }
    }

    // Dynamic Report Queries
    $where = ["a.deleted_at IS NULL"];
    $params = [];

    $commWhere = ["c.deleted_at IS NULL"];
    $commParams = [];

    if ($view === 'daily') {
        if (!empty($_GET['date'])) {
            $targetDate = trim($_GET['date']);
            $where[] = "a.date = :target_date";
            $params[':target_date'] = $targetDate;
            $commWhere[] = "c.communication_date = :target_date";
            $commParams[':target_date'] = $targetDate;
        }
    } elseif ($view === 'monthly') {
        $year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = !empty($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
        $where[] = "YEAR(a.date) = :year AND MONTH(a.date) = :month";
        $params[':year'] = $year;
        $params[':month'] = $month;
        $commWhere[] = "YEAR(c.communication_date) = :year AND MONTH(c.communication_date) = :month";
        $commParams[':year'] = $year;
        $commParams[':month'] = $month;
    } elseif ($view === 'quarterly') {
        $year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $quarter = !empty($_GET['quarter']) ? (int)$_GET['quarter'] : (int)ceil((int)date('m') / 3);
        $where[] = "YEAR(a.date) = :year AND QUARTER(a.date) = :quarter";
        $params[':year'] = $year;
        $params[':quarter'] = $quarter;
        $commWhere[] = "YEAR(c.communication_date) = :year AND QUARTER(c.communication_date) = :quarter";
        $commParams[':year'] = $year;
        $commParams[':quarter'] = $quarter;
    } elseif ($view === 'annual') {
        $year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $where[] = "YEAR(a.date) = :year";
        $params[':year'] = $year;
        $commWhere[] = "YEAR(c.communication_date) = :year";
        $commParams[':year'] = $year;
    } elseif ($view === 'custom') {
        $startDate = !empty($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
        $endDate = !empty($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
        $where[] = "a.date >= :start_date AND a.date <= :end_date";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
        $commWhere[] = "c.communication_date >= :start_date AND c.communication_date <= :end_date";
        $commParams[':start_date'] = $startDate;
        $commParams[':end_date'] = $endDate;
    }

    if (!empty($_GET['office_id'])) {
        $where[] = "a.office_id = :office_id";
        $params[':office_id'] = (int)$_GET['office_id'];
    }

    if (!empty($_GET['category_id'])) {
        $where[] = "a.category_id = :category_id";
        $params[':category_id'] = (int)$_GET['category_id'];
    }

    if (!empty($_GET['search'])) {
        $where[] = "(a.description LIKE :search OR a.remarks LIKE :search OR o.office_name LIKE :search OR o.office_abbv LIKE :search OR ac.category_name LIKE :search OR ac.category_code LIKE :search)";
        $params[':search'] = '%' . trim($_GET['search']) . '%';
    }

    $whereSql = implode(' AND ', $where);
    $commWhereSql = implode(' AND ', $commWhere);

    $sql = "
        SELECT 
            a.id,
            a.office_id,
            a.category_id,
            a.date,
            a.description,
            a.remarks,
            a.created_at,
            a.updated_at,
            o.office_name,
            o.office_code,
            o.office_abbv,
            ac.category_name,
            ac.category_code
        FROM tbl_accomplishments a
        LEFT JOIN tbl_offices o ON a.office_id = o.id
        LEFT JOIN tbl_accomplishment_categories ac ON a.category_id = ac.id
        WHERE {$whereSql}
        ORDER BY a.date DESC, a.created_at DESC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        // 1. Aggregate Accomplishments By Category
        $accByCatStmt = $pdo->prepare("
            SELECT 
                ac.id as category_id,
                ac.category_name,
                ac.category_code,
                COUNT(a.id) as count
            FROM tbl_accomplishment_categories ac
            LEFT JOIN tbl_accomplishments a ON a.category_id = ac.id AND {$whereSql}
            WHERE ac.deleted_at IS NULL
            GROUP BY ac.id, ac.category_name, ac.category_code
            ORDER BY ac.id ASC
        ");
        $accByCatStmt->execute($params);
        $accByCat = $accByCatStmt->fetchAll();

        // 2. Aggregate Outgoing Communications By Category
        $outCommsStmt = $pdo->prepare("
            SELECT 
                cc.id as category_id,
                cc.name as category_name,
                cc.code as category_code,
                COUNT(c.id) as count
            FROM tbl_communication_categories cc
            LEFT JOIN tbl_communications c ON c.category_id = cc.id AND c.communication_type = 'Outgoing' AND {$commWhereSql}
            WHERE cc.deleted_at IS NULL
            GROUP BY cc.id, cc.name, cc.code
            ORDER BY cc.id ASC
        ");
        $outCommsStmt->execute($commParams);
        $outComms = $outCommsStmt->fetchAll();

        // 3. Aggregate Clearances By Purpose
        $clearancesStmt = $pdo->prepare("
            SELECT 
                cp.id as purpose_id,
                cp.name as purpose_name,
                COUNT(c.id) as count
            FROM tbl_communication_purposes cp
            LEFT JOIN tbl_communications c ON c.purpose_id = cp.id AND {$commWhereSql}
            WHERE cp.deleted_at IS NULL
            GROUP BY cp.id, cp.name
            ORDER BY cp.id ASC
        ");
        $clearancesStmt->execute($commParams);
        $clearances = $clearancesStmt->fetchAll();

        sendResponse(true, 'Accomplishments list fetched successfully.', [
            'records' => $records,
            'accomplishments_by_category' => $accByCat,
            'outgoing_comms_by_category' => $outComms,
            'clearances_by_purpose' => $clearances
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Failed to fetch accomplishment records.', null, ['error' => $e->getMessage()], 500);
    }
}

/**
 * POST Handler (Create & Admin Category Actions)
 */
function handlePost(PDO $pdo) {
    $action = $_GET['action'] ?? '';
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data) {
        sendResponse(false, 'Invalid JSON payload.', null, null, 400);
    }

    // Admin Action: Create Category
    if ($action === 'create_category') {
        requireRole('Administrator');
        $catName = trim($data['category_name'] ?? '');
        $catCode = trim($data['category_code'] ?? '');

        if (empty($catName)) {
            sendResponse(false, 'Category name is required.', null, null, 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO tbl_accomplishment_categories (category_name, category_code, created_at, updated_at, created_by, modified_by)
            VALUES (:name, :code, NOW(), NOW(), :uid, :uid)
        ");
        $stmt->execute([
            ':name' => $catName,
            ':code' => $catCode ?: null,
            ':uid' => $_SESSION['user_id']
        ]);

        sendResponse(true, "Accomplishment category '{$catName}' created successfully.");
    }

    // Admin Action: Update Category
    if ($action === 'update_category') {
        requireRole('Administrator');
        $id = (int)($data['id'] ?? 0);
        $catName = trim($data['category_name'] ?? '');
        $catCode = trim($data['category_code'] ?? '');

        if ($id <= 0 || empty($catName)) {
            sendResponse(false, 'Category ID and name are required.', null, null, 400);
        }

        $stmt = $pdo->prepare("
            UPDATE tbl_accomplishment_categories
            SET category_name = :name, category_code = :code, updated_at = NOW(), modified_by = :uid
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':name' => $catName,
            ':code' => $catCode ?: null,
            ':uid' => $_SESSION['user_id'],
            ':id' => $id
        ]);

        sendResponse(true, "Accomplishment category updated successfully.");
    }

    // Admin Action: Delete Category
    if ($action === 'delete_category') {
        requireRole('Administrator');
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            sendResponse(false, 'Valid category ID is required.', null, null, 400);
        }

        $stmt = $pdo->prepare("
            UPDATE tbl_accomplishment_categories
            SET deleted_at = NOW(), modified_by = :uid
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':uid' => $_SESSION['user_id'], ':id' => $id]);

        sendResponse(true, "Accomplishment category deleted successfully.");
    }

    // Standard Create Accomplishment Entry
    $errors = validatePayload($pdo, $data);
    if (!empty($errors)) {
        sendResponse(false, 'Validation failed.', null, $errors, 400);
    }

    $officeId = (int)$data['office_id'];
    $categoryId = (int)($data['category_id'] ?? 1);
    $date = trim($data['date']);
    $description = trim($data['description']);
    $remarks = isset($data['remarks']) ? trim($data['remarks']) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tbl_accomplishments 
            (office_id, category_id, date, description, remarks, created_at, updated_at, created_by, modified_by)
            VALUES 
            (:office_id, :category_id, :date, :description, :remarks, NOW(), NOW(), :uid, :uid)
        ");
        $stmt->execute([
            ':office_id' => $officeId,
            ':category_id' => $categoryId,
            ':date' => $date,
            ':description' => $description,
            ':remarks' => $remarks,
            ':uid' => $_SESSION['user_id']
        ]);

        $newId = $pdo->lastInsertId();
        sendResponse(true, 'Accomplishment recorded successfully.', ['id' => $newId], null, 201);
    } catch (Exception $e) {
        sendResponse(false, 'Failed to record accomplishment.', null, ['db' => $e->getMessage()], 500);
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

    $checkStmt = $pdo->prepare("SELECT id FROM tbl_accomplishments WHERE id = :id AND deleted_at IS NULL");
    $checkStmt->execute([':id' => $id]);
    if (!$checkStmt->fetch()) {
        sendResponse(false, 'Accomplishment record not found.', null, null, 404);
    }

    $errors = validatePayload($pdo, $data, true);
    if (!empty($errors)) {
        sendResponse(false, 'Validation failed.', null, $errors, 400);
    }

    $officeId = (int)$data['office_id'];
    $categoryId = (int)($data['category_id'] ?? 1);
    $date = trim($data['date']);
    $description = trim($data['description']);
    $remarks = isset($data['remarks']) ? trim($data['remarks']) : null;

    try {
        $stmt = $pdo->prepare("
            UPDATE tbl_accomplishments SET
                office_id = :office_id,
                category_id = :category_id,
                date = :date,
                description = :description,
                remarks = :remarks,
                updated_at = NOW(),
                modified_by = :uid
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $id,
            ':office_id' => $officeId,
            ':category_id' => $categoryId,
            ':date' => $date,
            ':description' => $description,
            ':remarks' => $remarks,
            ':uid' => $_SESSION['user_id']
        ]);

        sendResponse(true, 'Accomplishment updated successfully.', ['id' => $id]);
    } catch (Exception $e) {
        sendResponse(false, 'Failed to update accomplishment record.', null, ['db' => $e->getMessage()], 500);
    }
}

/**
 * DELETE Handler
 */
function handleDelete(PDO $pdo) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        sendResponse(false, 'Record ID is required for deletion.', null, null, 400);
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE tbl_accomplishments 
            SET deleted_at = NOW(), modified_by = :uid 
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':uid' => $_SESSION['user_id'], ':id' => $id]);

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

    if (empty($data['office_id']) || (int)$data['office_id'] <= 0) {
        $errors['office_id'] = 'Office is required.';
    } else {
        $check = $pdo->prepare("SELECT id FROM tbl_offices WHERE id = :id AND deleted_at IS NULL");
        $check->execute([':id' => (int)$data['office_id']]);
        if (!$check->fetch()) {
            $errors['office_id'] = 'Selected office does not exist.';
        }
    }

    if (empty($data['date']) || strlen(trim($data['date'])) === 0) {
        $errors['date'] = 'Date is required.';
    }

    if (empty($data['description']) || strlen(trim($data['description'])) === 0) {
        $errors['description'] = 'Accomplishment description is required.';
    }

    return $errors;
}

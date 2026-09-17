<?php
// backend/api/edfs/index.php
// REST API Endpoint for 6IS EDFS Account Monitoring Module (Soft Delete & Canonical Office Code Enabled)

require_once __DIR__ . '/../../helpers/cors.php';
handleCors();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/audit.php';
require_once __DIR__ . '/../../helpers/modules.php';
require_once __DIR__ . '/../../helpers/permissions.php';

requireAuth();
requireModuleActive('edfs');

function sendResponse($success, $message, $data = null, $errors = null, int $statusCode = 200) {
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
    case 'PATCH':
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
    requirePermission('edfs', 'view', $pdo);

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // Single item fetch
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                e.*,
                o.office_code,
                o.office_abbv,
                COALESCE(NULLIF(o.office_code, ''), o.office_name, e.office_name) AS office_short_name,
                o.office_name AS full_office_name
            FROM tbl_edfs_accounts e
            LEFT JOIN tbl_offices o ON e.office_id = o.id
            WHERE e.id = ? AND e.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record) {
            sendResponse(false, 'EDFS account record not found.', null, null, 404);
        }
        sendResponse(true, 'EDFS account retrieved successfully.', $record);
    }

    // Summary / Stats View
    $isSummary = isset($_GET['view']) && $_GET['view'] === 'summary';

    // Filters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $office = isset($_GET['office']) ? trim($_GET['office']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $hasPersonnel = isset($_GET['has_personnel']) ? trim($_GET['has_personnel']) : '';

    $where = ["e.deleted_at IS NULL"];
    $params = [];

    if ($search !== '') {
        $where[] = "(e.personnel_name LIKE :search OR e.current_title LIKE :search OR e.account_name LIKE :search OR e.username LIKE :search OR e.office_name LIKE :search OR o.office_code LIKE :search OR e.remarks LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    if ($office !== '') {
        if (is_numeric($office)) {
            $where[] = "e.office_id = :office_id";
            $params[':office_id'] = (int)$office;
        } else {
            $where[] = "(e.office_name = :office_name OR o.office_code = :office_name)";
            $params[':office_name'] = $office;
        }
    }

    if ($status !== '') {
        $where[] = "e.status = :status";
        $params[':status'] = $status;
    }

    if ($hasPersonnel === '1' || $hasPersonnel === 'true') {
        $where[] = "(e.personnel_name IS NOT NULL AND e.personnel_name != '')";
    } elseif ($hasPersonnel === '0' || $hasPersonnel === 'false') {
        $where[] = "(e.personnel_name IS NULL OR e.personnel_name = '')";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Summary query
    $totalCount = $pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE deleted_at IS NULL")->fetchColumn();
    $activeCount = $pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE deleted_at IS NULL AND status = 'Active'")->fetchColumn();
    $inactiveCount = $pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE deleted_at IS NULL AND status = 'Inactive'")->fetchColumn();
    $renewalCount = $pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE deleted_at IS NULL AND status = 'For Renewal'")->fetchColumn();
    $assignedCount = $pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE deleted_at IS NULL AND personnel_name IS NOT NULL AND personnel_name != ''")->fetchColumn();
    $genericCount = $pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE deleted_at IS NULL AND (personnel_name IS NULL OR personnel_name = '')")->fetchColumn();

    // Distinct master offices list from tbl_offices (for strict dropdown selection)
    $allOfficesList = $pdo->query("
        SELECT 
            o.id AS office_id,
            COALESCE(NULLIF(o.office_code, ''), o.office_name) AS short_name,
            o.office_name AS full_name,
            COUNT(e.id) AS account_count
        FROM tbl_offices o
        LEFT JOIN tbl_edfs_accounts e ON o.id = e.office_id AND e.deleted_at IS NULL
        WHERE o.is_active = 1
        GROUP BY o.id, o.office_code, o.office_name
        ORDER BY short_name ASC
    ")->fetchAll();

    if ($isSummary) {
        sendResponse(true, 'EDFS summary retrieved.', [
            'metrics' => [
                'total' => (int)$totalCount,
                'active' => (int)$activeCount,
                'inactive' => (int)$inactiveCount,
                'for_renewal' => (int)$renewalCount,
                'assigned' => (int)$assignedCount,
                'generic_desk' => (int)$genericCount,
                'offices_count' => count($allOfficesList)
            ],
            'offices' => $allOfficesList
        ]);
    }

    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
    if ($perPage <= 0 || $perPage > 500) {
        $perPage = 50;
    }
    $offset = ($page - 1) * $perPage;

    // Filtered count
    $countSql = "
        SELECT COUNT(*) 
        FROM tbl_edfs_accounts e 
        LEFT JOIN tbl_offices o ON e.office_id = o.id 
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $filteredCount = (int)$countStmt->fetchColumn();

    // Data query (canonical short name selected)
    $sql = "
        SELECT 
            e.*,
            o.office_code,
            o.office_abbv,
            COALESCE(NULLIF(o.office_code, ''), o.office_name, e.office_name) AS office_short_name,
            o.office_name AS full_office_name
        FROM tbl_edfs_accounts e
        LEFT JOIN tbl_offices o ON e.office_id = o.id
        $whereClause
        ORDER BY office_short_name ASC, e.current_title ASC, e.id ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll();

    sendResponse(true, 'EDFS accounts retrieved successfully.', [
        'items' => $records,
        'pagination' => [
            'total' => $filteredCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($filteredCount / $perPage)
        ],
        'metrics' => [
            'total' => (int)$totalCount,
            'active' => (int)$activeCount,
            'inactive' => (int)$inactiveCount,
            'for_renewal' => (int)$renewalCount,
            'assigned' => (int)$assignedCount,
            'generic_desk' => (int)$genericCount
        ],
        'offices' => $allOfficesList
    ]);
}

/**
 * POST Handler (Create Account)
 */
function handlePost(PDO $pdo) {
    requirePermission('edfs', 'create', $pdo);
    validateCsrfToken();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $errors = [];
    $officeId = !empty($input['office_id']) ? (int)$input['office_id'] : 0;
    $currentTitle = isset($input['current_title']) ? trim($input['current_title']) : '';

    if ($officeId <= 0) {
        $errors['office_id'] = 'Please select a valid office.';
    }

    if ($currentTitle === '') {
        $errors['current_title'] = 'Current Title / Role is required.';
    }

    // Resolve short name strictly from tbl_offices (cannot be arbitrarily edited)
    $officeStmt = $pdo->prepare("SELECT id, office_name, office_code FROM tbl_offices WHERE id = ?");
    $officeStmt->execute([$officeId]);
    $officeRow = $officeStmt->fetch();

    if (!$officeRow) {
        $errors['office_id'] = 'Selected office does not exist in tbl_offices.';
    }

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed.', null, $errors, 400);
    }

    // Derive official short name
    $officeShortName = !empty($officeRow['office_code']) ? $officeRow['office_code'] : $officeRow['office_name'];

    // Unified login credential: account_name & username are the exact same login name
    $loginName = !empty($input['username']) ? trim($input['username']) : (!empty($input['account_name']) ? trim($input['account_name']) : null);
    $personnelName = !empty($input['personnel_name']) ? trim($input['personnel_name']) : null;
    $password = !empty($input['password']) ? trim($input['password']) : null;
    $status = in_array($input['status'] ?? 'Active', ['Active', 'Inactive', 'For Renewal']) ? $input['status'] : 'Active';
    $remarks = !empty($input['remarks']) ? trim($input['remarks']) : null;

    $stmt = $pdo->prepare("
        INSERT INTO tbl_edfs_accounts (
            office_id, office_name, account_name, current_title,
            personnel_name, username, password, status, remarks
        ) VALUES (
            :office_id, :office_name, :account_name, :current_title,
            :personnel_name, :username, :password, :status, :remarks
        )
    ");

    $stmt->execute([
        ':office_id' => $officeId,
        ':office_name' => $officeShortName,
        ':account_name' => $loginName,
        ':current_title' => $currentTitle,
        ':personnel_name' => $personnelName,
        ':username' => $loginName,
        ':password' => $password,
        ':status' => $status,
        ':remarks' => $remarks
    ]);

    $newId = (int)$pdo->lastInsertId();

    logAudit($pdo, 'edfs_account_create', [
        'account_id' => $newId,
        'office_id' => $officeId,
        'office' => $officeShortName,
        'title' => $currentTitle,
        'login_username' => $loginName,
        'personnel' => $personnelName
    ]);

    sendResponse(true, 'EDFS account created successfully.', ['id' => $newId], null, 201);
}

/**
 * PUT / PATCH Handler (Update Account)
 */
function handlePut(PDO $pdo) {
    requirePermission('edfs', 'edit', $pdo);
    validateCsrfToken();

    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0 && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
    }

    if ($id <= 0) {
        sendResponse(false, 'Missing or invalid account ID.', null, null, 400);
    }

    $checkStmt = $pdo->prepare("SELECT * FROM tbl_edfs_accounts WHERE id = ? AND deleted_at IS NULL");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch();

    if (!$existing) {
        sendResponse(false, 'EDFS account not found.', null, null, 404);
    }

    // Office ID: resolve short name from tbl_offices
    $officeId = array_key_exists('office_id', $input) && !empty($input['office_id']) ? (int)$input['office_id'] : $existing['office_id'];
    $officeShortName = $existing['office_name'];

    if ($officeId > 0) {
        $offStmt = $pdo->prepare("SELECT id, office_name, office_code FROM tbl_offices WHERE id = ?");
        $offStmt->execute([$officeId]);
        $offRow = $offStmt->fetch();
        if ($offRow) {
            $officeShortName = !empty($offRow['office_code']) ? $offRow['office_code'] : $offRow['office_name'];
        }
    }

    $currentTitle = isset($input['current_title']) && trim($input['current_title']) !== '' ? trim($input['current_title']) : $existing['current_title'];

    // Unified login name (username == account_name)
    $loginName = $existing['username'];
    if (array_key_exists('username', $input) || array_key_exists('account_name', $input)) {
        $val = array_key_exists('username', $input) ? trim($input['username']) : trim($input['account_name']);
        $loginName = $val !== '' ? $val : null;
    }

    $personnelName = array_key_exists('personnel_name', $input) ? (empty($input['personnel_name']) ? null : trim($input['personnel_name'])) : $existing['personnel_name'];
    $password = array_key_exists('password', $input) ? (empty($input['password']) ? null : trim($input['password'])) : $existing['password'];
    $status = isset($input['status']) && in_array($input['status'], ['Active', 'Inactive', 'For Renewal']) ? $input['status'] : $existing['status'];
    $remarks = array_key_exists('remarks', $input) ? (empty($input['remarks']) ? null : trim($input['remarks'])) : $existing['remarks'];

    $stmt = $pdo->prepare("
        UPDATE tbl_edfs_accounts
        SET office_id = :office_id,
            office_name = :office_name,
            account_name = :account_name,
            current_title = :current_title,
            personnel_name = :personnel_name,
            username = :username,
            password = :password,
            status = :status,
            remarks = :remarks
        WHERE id = :id AND deleted_at IS NULL
    ");

    $stmt->execute([
        ':office_id' => $officeId,
        ':office_name' => $officeShortName,
        ':account_name' => $loginName,
        ':current_title' => $currentTitle,
        ':personnel_name' => $personnelName,
        ':username' => $loginName,
        ':password' => $password,
        ':status' => $status,
        ':remarks' => $remarks,
        ':id' => $id
    ]);

    logAudit($pdo, 'edfs_account_update', [
        'account_id' => $id,
        'office_id' => $officeId,
        'office' => $officeShortName,
        'title' => $currentTitle,
        'changes' => [
            'personnel' => $personnelName,
            'status' => $status,
            'login_name' => $loginName
        ]
    ]);

    sendResponse(true, 'EDFS account updated successfully.');
}

/**
 * DELETE Handler (Soft Delete Account)
 */
function handleDelete(PDO $pdo) {
    requirePermission('edfs', 'delete', $pdo);
    validateCsrfToken();

    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

    if ($id <= 0) {
        sendResponse(false, 'Missing or invalid account ID.', null, null, 400);
    }

    $checkStmt = $pdo->prepare("SELECT * FROM tbl_edfs_accounts WHERE id = ? AND deleted_at IS NULL");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch();

    if (!$existing) {
        sendResponse(false, 'EDFS account not found or already deleted.', null, null, 404);
    }

    // Soft delete: set deleted_at to current timestamp
    $delStmt = $pdo->prepare("UPDATE tbl_edfs_accounts SET deleted_at = NOW() WHERE id = ?");
    $delStmt->execute([$id]);

    logAudit($pdo, 'edfs_account_soft_delete', [
        'account_id' => $id,
        'office' => $existing['office_name'],
        'title' => $existing['current_title'],
        'personnel' => $existing['personnel_name']
    ]);

    sendResponse(true, 'EDFS account successfully archived.');
}

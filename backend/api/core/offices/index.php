<?php
// backend/api/core/offices/index.php
// REST API Endpoint for 6IS Core Offices Management (Phase 3)

$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/permissions.php';

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

$rawInput = file_get_contents('php://input');
if (empty($rawInput) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
    $rawInput = $GLOBALS['HTTP_RAW_POST_DATA'];
}
$input = json_decode($rawInput, true) ?? [];

// =========================================================================
// GET: Retrieve Offices List or Single Office
// =========================================================================
if ($method === 'GET') {
    requirePermission('offices', 'view', $pdo);

    $idParam = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $activeOnly = isset($_GET['active_only']) && ((string)$_GET['active_only'] === '1' || strtolower((string)$_GET['active_only']) === 'true');
    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

    try {
        if ($idParam && $idParam > 0) {
            $stmt = $pdo->prepare("
                SELECT o.id, o.organization_id,
                       COALESCE(NULLIF(o.name, ''), o.office_name, o.code, o.office_code) AS name,
                       COALESCE(NULLIF(o.code, ''), o.office_code, o.office_abbv) AS code,
                       o.description, o.address, o.contact_number, o.email, o.is_active,
                       o.created_at, o.updated_at,
                       COUNT(u.id) AS user_count
                FROM tbl_offices o
                LEFT JOIN tbl_users u ON u.office_id = o.id AND u.deleted_at IS NULL
                WHERE o.id = :id
                GROUP BY o.id
                LIMIT 1
            ");
            $stmt->execute([':id' => $idParam]);
            $office = $stmt->fetch();

            if (!$office) {
                sendJsonResponse(false, 'Office not found.', null, null, 404);
            }

            $office['id'] = (int)$office['id'];
            $office['organization_id'] = (int)$office['organization_id'];
            $office['is_active'] = (int)$office['is_active'];
            $office['user_count'] = (int)$office['user_count'];
            $office['office_name'] = $office['name'];
            $office['office_code'] = $office['code'];
            $office['office_abbv'] = $office['code'];

            sendJsonResponse(true, 'Office details retrieved.', $office);
        }

        // List query
        $whereClauses = ["(o.deleted_at IS NULL OR o.deleted_at = '0000-00-00 00:00:00')"];
        $params = [];

        if ($activeOnly) {
            $whereClauses[] = "o.is_active = 1";
        }

        if ($search !== '') {
            $whereClauses[] = "(LOWER(o.name) LIKE :search OR LOWER(o.code) LIKE :search OR LOWER(o.office_name) LIKE :search OR LOWER(o.office_code) LIKE :search)";
            $params[':search'] = '%' . strtolower($search) . '%';
        }

        $whereSql = implode(' AND ', $whereClauses);

        $stmt = $pdo->prepare("
            SELECT o.id, o.organization_id,
                   COALESCE(NULLIF(o.name, ''), o.office_name, o.code, o.office_code) AS name,
                   COALESCE(NULLIF(o.code, ''), o.office_code, o.office_abbv) AS code,
                   o.description, o.address, o.contact_number, o.email, o.is_active,
                   o.created_at, o.updated_at,
                   COUNT(u.id) AS user_count
            FROM tbl_offices o
            LEFT JOIN tbl_users u ON u.office_id = o.id AND u.deleted_at IS NULL
            WHERE {$whereSql}
            GROUP BY o.id
            ORDER BY o.is_active DESC, o.code ASC, o.name ASC
        ");
        $stmt->execute($params);
        $offices = $stmt->fetchAll();

        foreach ($offices as &$off) {
            $off['id'] = (int)$off['id'];
            $off['organization_id'] = (int)$off['organization_id'];
            $off['is_active'] = (int)$off['is_active'];
            $off['user_count'] = (int)$off['user_count'];
            $off['office_name'] = $off['name'];
            $off['office_code'] = $off['code'];
            $off['office_abbv'] = $off['code'];
        }

        sendJsonResponse(true, 'Offices directory retrieved.', $offices);
    } catch (Throwable $e) {
        error_log('[offices] Database error: ' . $e->getMessage());
        sendJsonResponse(false, 'Database failure retrieving offices.', null, null, 500);
    }
}

// =========================================================================
// POST: Create New Office
// =========================================================================
if ($method === 'POST') {
    requirePermission('offices', 'create', $pdo);

    $name = trim((string)($input['name'] ?? $input['office_name'] ?? ''));
    $code = strtoupper(trim((string)($input['code'] ?? $input['office_code'] ?? '')));
    $orgId = isset($input['organization_id']) ? (int)$input['organization_id'] : 1;
    $description = isset($input['description']) ? trim((string)$input['description']) : null;
    $address = isset($input['address']) ? trim((string)$input['address']) : null;
    $contactNumber = isset($input['contact_number']) ? trim((string)$input['contact_number']) : null;
    $email = isset($input['email']) ? trim((string)$input['email']) : null;
    $isActive = isset($input['is_active']) ? ((int)$input['is_active'] ? 1 : 0) : 1;

    if (empty($name)) {
        sendJsonResponse(false, 'Office name is required.', null, null, 400);
    }
    if (empty($code)) {
        sendJsonResponse(false, 'Office code is required.', null, null, 400);
    }
    if (mb_strlen($name) > 150) {
        sendJsonResponse(false, 'Office name must not exceed 150 characters.', null, null, 400);
    }
    if (mb_strlen($code) > 50) {
        sendJsonResponse(false, 'Office code must not exceed 50 characters.', null, null, 400);
    }
    if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Invalid office email format.', null, null, 400);
    }

    try {
        // Validate Organization exists and is active
        $orgCheck = $pdo->prepare("SELECT id FROM tbl_organization WHERE id = :org_id AND is_active = 1 LIMIT 1");
        $orgCheck->execute([':org_id' => $orgId]);
        if (!$orgCheck->fetch()) {
            sendJsonResponse(false, 'Invalid organization specified.', null, null, 400);
        }

        // Check duplicate code within organization
        $codeCheck = $pdo->prepare("
            SELECT id FROM tbl_offices 
            WHERE organization_id = :org_id AND LOWER(COALESCE(NULLIF(code, ''), office_code)) = LOWER(:code)
            LIMIT 1
        ");
        $codeCheck->execute([':org_id' => $orgId, ':code' => $code]);
        if ($codeCheck->fetch()) {
            sendJsonResponse(false, "Office code '{$code}' already exists in this organization.", null, null, 409);
        }

        // Check duplicate name within organization
        $nameCheck = $pdo->prepare("
            SELECT id FROM tbl_offices 
            WHERE organization_id = :org_id AND LOWER(COALESCE(NULLIF(name, ''), office_name)) = LOWER(:name)
            LIMIT 1
        ");
        $nameCheck->execute([':org_id' => $orgId, ':name' => $name]);
        if ($nameCheck->fetch()) {
            sendJsonResponse(false, "Office name '{$name}' already exists in this organization.", null, null, 409);
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO tbl_offices (
                organization_id, name, code, description, address, contact_number, email,
                office_name, office_code, office_abbv, is_active, created_at, updated_at
            ) VALUES (
                :organization_id, :name, :code, :description, :address, :contact_number, :email,
                :office_name, :office_code, :office_abbv, :is_active, NOW(), NOW()
            )
        ");
        $insertStmt->execute([
            ':organization_id' => $orgId,
            ':name' => $name,
            ':code' => $code,
            ':description' => $description,
            ':address' => $address,
            ':contact_number' => $contactNumber,
            ':email' => $email,
            ':office_name' => $name,
            ':office_code' => $code,
            ':office_abbv' => $code,
            ':is_active' => $isActive
        ]);

        $createdId = (int)$pdo->lastInsertId();

        $fetchStmt = $pdo->prepare("
            SELECT id, organization_id, name, code, description, address, contact_number, email, is_active, created_at, updated_at
            FROM tbl_offices WHERE id = :id
        ");
        $fetchStmt->execute([':id' => $createdId]);
        $newOffice = $fetchStmt->fetch();
        $newOffice['id'] = (int)$newOffice['id'];
        $newOffice['organization_id'] = (int)$newOffice['organization_id'];
        $newOffice['is_active'] = (int)$newOffice['is_active'];
        $newOffice['user_count'] = 0;
        $newOffice['office_name'] = $newOffice['name'];
        $newOffice['office_code'] = $newOffice['code'];
        $newOffice['office_abbv'] = $newOffice['code'];

        sendJsonResponse(true, "Office '{$code}' created successfully.", $newOffice, null, 201);
    } catch (Throwable $e) {
        error_log('[offices] Database error: ' . $e->getMessage());
        sendJsonResponse(false, 'Database failure creating office.', null, null, 500);
    }
}

// =========================================================================
// PATCH: Update Existing Office
// =========================================================================
if ($method === 'PATCH') {
    requirePermission('offices', 'edit', $pdo);

    $officeId = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if ($officeId <= 0) {
        sendJsonResponse(false, 'Valid office ID is required.', null, null, 400);
    }

    try {
        $existingStmt = $pdo->prepare("SELECT id, organization_id, name, code, office_name, office_code, is_active FROM tbl_offices WHERE id = :id LIMIT 1");
        $existingStmt->execute([':id' => $officeId]);
        $existing = $existingStmt->fetch();

        if (!$existing) {
            sendJsonResponse(false, 'Office not found.', null, null, 404);
        }

        $orgId = (int)$existing['organization_id'];
        $name = isset($input['name']) ? trim((string)$input['name']) : (isset($input['office_name']) ? trim((string)$input['office_name']) : null);
        $code = isset($input['code']) ? strtoupper(trim((string)$input['code'])) : (isset($input['office_code']) ? strtoupper(trim((string)$input['office_code'])) : null);
        $description = isset($input['description']) ? trim((string)$input['description']) : null;
        $address = isset($input['address']) ? trim((string)$input['address']) : null;
        $contactNumber = isset($input['contact_number']) ? trim((string)$input['contact_number']) : null;
        $email = isset($input['email']) ? trim((string)$input['email']) : null;
        $isActive = isset($input['is_active']) ? ((int)$input['is_active'] ? 1 : 0) : null;

        if ($name !== null) {
            if ($name === '') {
                sendJsonResponse(false, 'Office name cannot be empty.', null, null, 400);
            }
            if (mb_strlen($name) > 150) {
                sendJsonResponse(false, 'Office name must not exceed 150 characters.', null, null, 400);
            }
            // Check uniqueness if changed
            $nameCheck = $pdo->prepare("
                SELECT id FROM tbl_offices 
                WHERE organization_id = :org_id AND LOWER(COALESCE(NULLIF(name, ''), office_name)) = LOWER(:name) AND id != :id
                LIMIT 1
            ");
            $nameCheck->execute([':org_id' => $orgId, ':name' => $name, ':id' => $officeId]);
            if ($nameCheck->fetch()) {
                sendJsonResponse(false, "Office name '{$name}' already exists in this organization.", null, null, 409);
            }
        }

        if ($code !== null) {
            if ($code === '') {
                sendJsonResponse(false, 'Office code cannot be empty.', null, null, 400);
            }
            if (mb_strlen($code) > 50) {
                sendJsonResponse(false, 'Office code must not exceed 50 characters.', null, null, 400);
            }
            // Check uniqueness if changed
            $codeCheck = $pdo->prepare("
                SELECT id FROM tbl_offices 
                WHERE organization_id = :org_id AND LOWER(COALESCE(NULLIF(code, ''), office_code)) = LOWER(:code) AND id != :id
                LIMIT 1
            ");
            $codeCheck->execute([':org_id' => $orgId, ':code' => $code, ':id' => $officeId]);
            if ($codeCheck->fetch()) {
                sendJsonResponse(false, "Office code '{$code}' already exists in this organization.", null, null, 409);
            }
        }

        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(false, 'Invalid office email format.', null, null, 400);
        }

        $updates = [];
        $params = [':id' => $officeId];

        if ($name !== null) {
            $updates[] = '`name` = :name';
            $updates[] = '`office_name` = :name_compat';
            $params[':name'] = $name;
            $params[':name_compat'] = $name;
        }
        if ($code !== null) {
            $updates[] = '`code` = :code';
            $updates[] = '`office_code` = :code_compat';
            $updates[] = '`office_abbv` = :code_abbv';
            $params[':code'] = $code;
            $params[':code_compat'] = $code;
            $params[':code_abbv'] = $code;
        }
        if ($description !== null) {
            $updates[] = '`description` = :description';
            $params[':description'] = $description;
        }
        if ($address !== null) {
            $updates[] = '`address` = :address';
            $params[':address'] = $address;
        }
        if ($contactNumber !== null) {
            $updates[] = '`contact_number` = :contact_number';
            $params[':contact_number'] = $contactNumber;
        }
        if ($email !== null) {
            $updates[] = '`email` = :email';
            $params[':email'] = $email;
        }
        if ($isActive !== null) {
            $updates[] = '`is_active` = :is_active';
            $params[':is_active'] = $isActive;
        }

        if (!empty($updates)) {
            $updates[] = '`updated_at` = NOW()';
            $sql = "UPDATE tbl_offices SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        $refetch = $pdo->prepare("
            SELECT o.id, o.organization_id,
                   COALESCE(NULLIF(o.name, ''), o.office_name, o.code, o.office_code) AS name,
                   COALESCE(NULLIF(o.code, ''), o.office_code, o.office_abbv) AS code,
                   o.description, o.address, o.contact_number, o.email, o.is_active,
                   o.created_at, o.updated_at,
                   COUNT(u.id) AS user_count
            FROM tbl_offices o
            LEFT JOIN tbl_users u ON u.office_id = o.id AND u.deleted_at IS NULL
            WHERE o.id = :id
            GROUP BY o.id
        ");
        $refetch->execute([':id' => $officeId]);
        $updatedOffice = $refetch->fetch();
        $updatedOffice['id'] = (int)$updatedOffice['id'];
        $updatedOffice['organization_id'] = (int)$updatedOffice['organization_id'];
        $updatedOffice['is_active'] = (int)$updatedOffice['is_active'];
        $updatedOffice['user_count'] = (int)$updatedOffice['user_count'];
        $updatedOffice['office_name'] = $updatedOffice['name'];
        $updatedOffice['office_code'] = $updatedOffice['code'];
        $updatedOffice['office_abbv'] = $updatedOffice['code'];

        sendJsonResponse(true, "Office '{$updatedOffice['code']}' updated successfully.", $updatedOffice);
    } catch (Throwable $e) {
        error_log('[offices] Database error: ' . $e->getMessage());
        sendJsonResponse(false, 'Database failure updating office.', null, null, 500);
    }
}

// =========================================================================
// DELETE: Delete Office (Safety Protected)
// =========================================================================
if ($method === 'DELETE') {
    requirePermission('offices', 'delete', $pdo);

    $officeId = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if ($officeId <= 0) {
        sendJsonResponse(false, 'Valid office ID is required.', null, null, 400);
    }

    try {
        $checkStmt = $pdo->prepare("SELECT id, name, code, office_name, office_code FROM tbl_offices WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $officeId]);
        $office = $checkStmt->fetch();

        if (!$office) {
            sendJsonResponse(false, 'Office not found.', null, null, 404);
        }

        // 1. Cannot delete office if active users are assigned
        $userCountStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE office_id = :id AND deleted_at IS NULL");
        $userCountStmt->execute([':id' => $officeId]);
        $userCount = (int)$userCountStmt->fetchColumn();

        if ($userCount > 0) {
            sendJsonResponse(
                false, 
                "Cannot delete office with {$userCount} assigned user account(s). Please reassign the users or deactivate the office instead.", 
                null, 
                ['user_count' => $userCount], 
                400
            );
        }

        // 2. Cannot delete office if referenced by historical activity records
        $historicalCount = 0;
        try {
            $accStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_accomplishments WHERE office_id = :id");
            $accStmt->execute([':id' => $officeId]);
            $historicalCount += (int)$accStmt->fetchColumn();
        } catch (Throwable $e) {}

        try {
            $commStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_communications WHERE office_id = :id");
            $commStmt->execute([':id' => $officeId]);
            $historicalCount += (int)$commStmt->fetchColumn();
        } catch (Throwable $e) {}

        try {
            $invStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE office_id = :id");
            $invStmt->execute([':id' => $officeId]);
            $historicalCount += (int)$invStmt->fetchColumn();
        } catch (Throwable $e) {}

        if ($historicalCount > 0) {
            sendJsonResponse(
                false,
                "Cannot delete office with {$historicalCount} associated historical record(s). Please deactivate the office instead.",
                null,
                ['historical_count' => $historicalCount],
                400
            );
        }

        // Clean deletion when zero dependencies
        $delStmt = $pdo->prepare("DELETE FROM tbl_offices WHERE id = :id");
        $delStmt->execute([':id' => $officeId]);

        $code = $office['code'] ?: $office['office_code'];
        sendJsonResponse(true, "Office '{$code}' deleted successfully.", ['id' => $officeId]);
    } catch (Throwable $e) {
        error_log('[offices] Database error: ' . $e->getMessage());
        sendJsonResponse(false, 'Database failure deleting office.', null, null, 500);
    }
}

sendJsonResponse(false, 'Method not allowed.', null, null, 405);

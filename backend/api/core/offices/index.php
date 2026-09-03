<?php
// backend/api/core/offices/index.php
// REST API Endpoint for 6IS Core Offices Management (Phase 3)

require_once __DIR__ . '/../../../helpers/cors.php';
handleCors();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/audit.php';
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

if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
    requireCsrf($input);
}

// =========================================================================
// CONFIGURE: Office Policies & Metadata Endpoint
// =========================================================================
if ($action === 'configure') {
    requirePermission('offices', 'configure', $pdo);

    if ($method === 'GET') {
        sendJsonResponse(true, 'Offices module policies and metadata retrieved.', [
            'allow_registration' => true,
            'code_unique_per_org' => true,
            'soft_deactivation_preferred' => true,
            'max_code_length' => 50,
            'max_name_length' => 150,
            'is_configurable' => false,
            'policy_type' => 'system_defined'
        ]);
    }

    sendJsonResponse(false, 'Office policies are system-defined and immutable at runtime.', null, null, 400);
}

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

        $pdo->beginTransaction();

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

        auditLog([
            'action' => 'CREATE',
            'module_key' => 'offices',
            'entity_type' => 'office',
            'entity_id' => (string)$createdId,
            'description' => "Created office '{$code}' ({$name}).",
            'old_values' => null,
            'new_values' => [
                'id' => $createdId,
                'name' => $name,
                'code' => $code,
                'organization_id' => $orgId,
                'is_active' => $isActive
            ]
        ], $pdo);

        $pdo->commit();

        sendJsonResponse(true, "Office '{$code}' created successfully.", $newOffice, null, 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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

        $pdo->beginTransaction();

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

        $actionType = 'UPDATE';
        if ($isActive !== null && $isActive !== (int)$existing['is_active']) {
            $actionType = $isActive === 1 ? 'ACTIVATE' : 'DEACTIVATE';
        }

        auditLog([
            'action' => $actionType,
            'module_key' => 'offices',
            'entity_type' => 'office',
            'entity_id' => (string)$officeId,
            'description' => "Office '{$updatedOffice['code']}' updated.",
            'old_values' => [
                'name' => $existing['name'],
                'code' => $existing['code'],
                'is_active' => (int)$existing['is_active']
            ],
            'new_values' => [
                'name' => $updatedOffice['name'],
                'code' => $updatedOffice['code'],
                'is_active' => (int)$updatedOffice['is_active']
            ]
        ], $pdo);

        $pdo->commit();

        sendJsonResponse(true, "Office '{$updatedOffice['code']}' updated successfully.", $updatedOffice);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[offices] Database error: ' . $e->getMessage());
        sendJsonResponse(false, 'Database failure updating office.', null, null, 500);
    }
}

// =========================================================================
// DELETE: Delete Office (Safety Protected & Atomic)
// =========================================================================
if ($method === 'DELETE') {
    requirePermission('offices', 'delete', $pdo);

    $officeId = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if ($officeId <= 0) {
        sendJsonResponse(false, 'Valid office ID is required.', null, null, 400);
    }

    try {
        $pdo->beginTransaction();

        // 1. Lock office row for atomic operation
        $checkStmt = $pdo->prepare("SELECT id, name, code, office_name, office_code FROM tbl_offices WHERE id = :id FOR UPDATE");
        $checkStmt->execute([':id' => $officeId]);
        $office = $checkStmt->fetch();

        if (!$office) {
            $pdo->rollBack();
            sendJsonResponse(false, 'Office not found.', null, null, 404);
        }

        // 2. Check for assigned active user accounts
        $userCountStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE office_id = :id AND deleted_at IS NULL");
        $userCountStmt->execute([':id' => $officeId]);
        $userCount = (int)$userCountStmt->fetchColumn();

        if ($userCount > 0) {
            $pdo->rollBack();
            sendJsonResponse(
                false, 
                "This office cannot be deleted because it has {$userCount} assigned user account(s). Please reassign the users or deactivate the office instead.", 
                null, 
                ['user_count' => $userCount, 'can_deactivate' => true], 
                409
            );
        }

        // 3. Check ALL operational and historical dependencies atomically
        $dependencyDetails = [
            'accomplishments' => 0,
            'communications' => 0,
            'inventory_equipment' => 0,
            'inventory_history' => 0,
            'calendar_events' => 0
        ];

        // tbl_accomplishments
        $accStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_accomplishments WHERE office_id = :id");
        $accStmt->execute([':id' => $officeId]);
        $dependencyDetails['accomplishments'] = (int)$accStmt->fetchColumn();

        // tbl_communications
        $commStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_communications WHERE office_id = :id");
        $commStmt->execute([':id' => $officeId]);
        $dependencyDetails['communications'] = (int)$commStmt->fetchColumn();

        // tbl_inventory_equipment (current equipment records)
        $invStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE office_id = :id");
        $invStmt->execute([':id' => $officeId]);
        $dependencyDetails['inventory_equipment'] = (int)$invStmt->fetchColumn();

        // tbl_inventory_history (historical snapshot records)
        $invHistStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE office_id = :id");
        $invHistStmt->execute([':id' => $officeId]);
        $dependencyDetails['inventory_history'] = (int)$invHistStmt->fetchColumn();

        // tbl_calendar_events (calendar events assigned to office)
        $calStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_calendar_events WHERE office_id = :id");
        $calStmt->execute([':id' => $officeId]);
        $dependencyDetails['calendar_events'] = (int)$calStmt->fetchColumn();

        $totalHistoricalCount = array_sum($dependencyDetails);

        if ($totalHistoricalCount > 0) {
            $pdo->rollBack();
            sendJsonResponse(
                false,
                "This office cannot be deleted because it has historical or operational records ({$totalHistoricalCount} record(s) found). Deactivate the office instead to preserve historical data.",
                null,
                [
                    'historical_count' => $totalHistoricalCount,
                    'dependencies' => $dependencyDetails,
                    'can_deactivate' => true
                ],
                409
            );
        }

        // Clean deletion when zero dependencies
        auditLog([
            'action' => 'DELETE',
            'module_key' => 'offices',
            'entity_type' => 'office',
            'entity_id' => (string)$officeId,
            'description' => "Deleted office '{$office['code']}'.",
            'old_values' => [
                'id' => $officeId,
                'name' => $office['name'],
                'code' => $office['code']
            ],
            'new_values' => null
        ], $pdo);

        $delStmt = $pdo->prepare("DELETE FROM tbl_offices WHERE id = :id");
        $delStmt->execute([':id' => $officeId]);

        $pdo->commit();

        $code = $office['code'] ?: $office['office_code'];
        sendJsonResponse(true, "Office '{$code}' deleted successfully.", ['id' => $officeId]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[offices] Database error: ' . $e->getMessage());
        sendJsonResponse(false, 'Database failure deleting office.', null, null, 500);
    }
}

sendJsonResponse(false, 'Method not allowed.', null, null, 405);

<?php
// backend/api/core/organization/index.php
// REST API Endpoint for 6IS Core Organization Management (Phase 3)

$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
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

// Support CLI/Subprocess inputs when php://input is empty
$rawInput = file_get_contents('php://input');
if (empty($rawInput) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
    $rawInput = $GLOBALS['HTTP_RAW_POST_DATA'];
}
$input = json_decode($rawInput, true) ?? [];

// =========================================================================
// GET: Retrieve the Primary Organization Record
// =========================================================================
if ($method === 'GET') {
    requirePermission('organization', 'view', $pdo);

    try {
        $stmt = $pdo->query("
            SELECT id, name, short_name, description, address, contact_number, email, logo_path, is_active, created_at, updated_at
            FROM tbl_organization
            ORDER BY is_active DESC, id ASC
            LIMIT 1
        ");
        $org = $stmt->fetch();

        if (!$org) {
            // Seed baseline record if missing
            $pdo->exec("
                INSERT INTO tbl_organization (id, name, short_name, description, is_active)
                VALUES (1, '6th Infantry Division', '6ID', 'Primary command organization for 6IS deployment', 1)
            ");
            $stmt = $pdo->query("SELECT id, name, short_name, description, address, contact_number, email, logo_path, is_active, created_at, updated_at FROM tbl_organization WHERE id = 1");
            $org = $stmt->fetch();
        }

        $org['id'] = (int)$org['id'];
        $org['is_active'] = (int)$org['is_active'];

        sendJsonResponse(true, 'Organization profile retrieved.', $org);
    } catch (Throwable $e) {
        error_log('[organization] Database error: ' . $e->getMessage());
        sendJsonResponse(false, 'Database failure retrieving organization.', null, null, 500);
    }
}

// =========================================================================
// POST / PATCH: Update Primary Organization Profile
// =========================================================================
if ($method === 'POST' || $method === 'PATCH') {
    requirePermission('organization', 'configure', $pdo);

    // If POST with action=create but an organization already exists, enforce single-deployment invariant
    if ($method === 'POST' && $action === 'create') {
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM tbl_organization WHERE is_active = 1");
            if ((int)$countStmt->fetchColumn() >= 1) {
                sendJsonResponse(false, 'Only one active organization is supported per deployment.', null, null, 400);
            }
        } catch (Throwable $e) {
            error_log('[organization] Error checking org count: ' . $e->getMessage());
            sendJsonResponse(false, 'Database failure verifying organization count.', null, null, 500);
        }
    }

    $name = isset($input['name']) ? trim((string)$input['name']) : null;
    $shortName = isset($input['short_name']) ? trim((string)$input['short_name']) : null;
    $description = isset($input['description']) ? trim((string)$input['description']) : null;
    $address = isset($input['address']) ? trim((string)$input['address']) : null;
    $contactNumber = isset($input['contact_number']) ? trim((string)$input['contact_number']) : null;
    $email = isset($input['email']) ? trim((string)$input['email']) : null;
    $logoPath = isset($input['logo_path']) ? trim((string)$input['logo_path']) : null;
    $isActive = isset($input['is_active']) ? ((int)$input['is_active'] ? 1 : 0) : null;

    if ($name !== null && $name === '') {
        sendJsonResponse(false, 'Organization name cannot be empty.', null, null, 400);
    }

    if ($name !== null && mb_strlen($name) > 255) {
        sendJsonResponse(false, 'Organization name must not exceed 255 characters.', null, null, 400);
    }

    if ($shortName !== null && mb_strlen($shortName) > 50) {
        sendJsonResponse(false, 'Short name must not exceed 50 characters.', null, null, 400);
    }

    if ($contactNumber !== null && mb_strlen($contactNumber) > 50) {
        sendJsonResponse(false, 'Contact number must not exceed 50 characters.', null, null, 400);
    }

    if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Invalid organization email format.', null, null, 400);
    }

    try {
        // Resolve current organization
        $existing = $pdo->query("SELECT id FROM tbl_organization ORDER BY is_active DESC, id ASC LIMIT 1")->fetch();

        if ($existing) {
            $orgId = (int)$existing['id'];
            $updates = [];
            $params = [':id' => $orgId];

            if ($name !== null) {
                $updates[] = '`name` = :name';
                $params[':name'] = $name;
            }
            if ($shortName !== null) {
                $updates[] = '`short_name` = :short_name';
                $params[':short_name'] = $shortName;
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
            if ($logoPath !== null) {
                $updates[] = '`logo_path` = :logo_path';
                $params[':logo_path'] = $logoPath;
            }
            if ($isActive !== null) {
                $updates[] = '`is_active` = :is_active';
                $params[':is_active'] = $isActive;
            }

            if (!empty($updates)) {
                $updates[] = '`updated_at` = NOW()';
                $sql = "UPDATE tbl_organization SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            $updatedStmt = $pdo->prepare("SELECT id, name, short_name, description, address, contact_number, email, logo_path, is_active, created_at, updated_at FROM tbl_organization WHERE id = :id");
            $updatedStmt->execute([':id' => $orgId]);
            $updatedOrg = $updatedStmt->fetch();
            $updatedOrg['id'] = (int)$updatedOrg['id'];
            $updatedOrg['is_active'] = (int)$updatedOrg['is_active'];

            sendJsonResponse(true, 'Organization profile updated successfully.', $updatedOrg);
        } else {
            // Insert initial organization
            if (empty($name)) {
                sendJsonResponse(false, 'Organization name is required.', null, null, 400);
            }
            $stmt = $pdo->prepare("
                INSERT INTO tbl_organization (name, short_name, description, address, contact_number, email, logo_path, is_active, created_at, updated_at)
                VALUES (:name, :short_name, :description, :address, :contact_number, :email, :logo_path, :is_active, NOW(), NOW())
            ");
            $stmt->execute([
                ':name' => $name,
                ':short_name' => $shortName,
                ':description' => $description,
                ':address' => $address,
                ':contact_number' => $contactNumber,
                ':email' => $email,
                ':logo_path' => $logoPath,
                ':is_active' => $isActive !== null ? $isActive : 1
            ]);
            $newId = (int)$pdo->lastInsertId();

            $fetchStmt = $pdo->prepare("SELECT id, name, short_name, description, address, contact_number, email, logo_path, is_active, created_at, updated_at FROM tbl_organization WHERE id = :id");
            $fetchStmt->execute([':id' => $newId]);
            $newOrg = $fetchStmt->fetch();
            $newOrg['id'] = (int)$newOrg['id'];
            $newOrg['is_active'] = (int)$newOrg['is_active'];

            sendJsonResponse(true, 'Organization profile created successfully.', $newOrg, null, 201);
        }
    } catch (Throwable $e) {
        error_log('[organization] Database error: ' . $e->getMessage());
        sendJsonResponse(false, 'Database failure updating organization.', null, null, 500);
    }
}

sendJsonResponse(false, 'Method not allowed.', null, null, 405);

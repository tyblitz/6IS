<?php
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
}
if (function_exists('opcache_reset')) {
    @opcache_reset();
}
// backend/api/inventory/index.php
// REST API Endpoint for 6IS Extensible Inventory Module & Readiness Calculations

require_once __DIR__ . '/../../helpers/cors.php';
handleCors();
header('Content-Type: application/json; charset=utf-8');

// Require authenticated session and active module for all inventory requests
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/modules.php';
require_once __DIR__ . '/../../helpers/permissions.php';
requireAuth();
requireModuleActive('inventory');

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

function formatPeriodLabel($ym) {
    if (empty($ym)) return '';
    $dt = DateTime::createFromFormat('Y-m', $ym);
    return $dt ? $dt->format('F Y') : $ym;
}

function formatAttributeValue($row) {
    $type = $row['data_type'] ?? 'text';
    switch ($type) {
        case 'number':
            return $row['value_number'] !== null ? (int)$row['value_number'] : null;
        case 'decimal':
            return $row['value_decimal'] !== null ? (float)$row['value_decimal'] : null;
        case 'date':
            return $row['value_date'] ?? null;
        case 'boolean':
            return $row['value_boolean'] !== null ? (bool)$row['value_boolean'] : null;
        case 'select':
        case 'text':
        default:
            return $row['value_text'] ?? null;
    }
}

// ==========================================
// POST ACTIONS (Create, Update, Delete, Snapshot)
// ==========================================

if ($method === 'POST') {

    // 1. Update JRRS Target Quantity (Admin)
    if ($action === 'update_jrrs') {
        requirePermission('inventory', 'configure', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $subtypeId = (int)($input['equipment_subtype_id'] ?? 0);
        $targetQty = (int)($input['target_quantity'] ?? 0);

        if ($subtypeId <= 0 || $targetQty < 0) {
            sendJsonResponse(false, 'Invalid target parameters.', null, null, 400);
        }

        try {
            $stmt = $pdo->prepare("UPDATE tbl_inventory_jrrs SET target_quantity = :qty, updated_at = NOW() WHERE equipment_subtype_id = :st_id AND deleted_at IS NULL");
            $stmt->execute([':qty' => $targetQty, ':st_id' => $subtypeId]);
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("UPDATE tbl_inventory_jrrs SET target_quantity = :qty, updated_at = NOW() WHERE id = :st_id AND deleted_at IS NULL");
                $stmt->execute([':qty' => $targetQty, ':st_id' => $subtypeId]);
            }
            sendJsonResponse(true, 'JRRS target quantity updated.');
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to update JRRS target quantity.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 2. Create Equipment Record
    if ($action === 'create_equipment') {
        requirePermission('inventory', 'create', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $officeId = (int)($input['office_id'] ?? 0);
        $typeId = (int)($input['equipment_type_id'] ?? 0);
        $subtypeId = (int)($input['equipment_subtype_id'] ?? 0);
        $statusId = (int)($input['status_id'] ?? 0);
        $serialNumber = trim($input['serial_number'] ?? '');
        $description = trim($input['description'] ?? '');
        $dateAcquired = trim($input['date_acquired'] ?? date('Y-m-d'));
        $attributes = $input['attributes'] ?? [];

        if ($officeId <= 0 || $subtypeId <= 0 || empty($serialNumber)) {
            sendJsonResponse(false, 'Office assignment, equipment subtype, and serial number are required.', null, null, 400);
        }

        $typeStmt = $pdo->prepare("SELECT name, equipment_type_id FROM tbl_inventory_equipment_subtypes WHERE id = :id");
        $typeStmt->execute([':id' => $subtypeId]);
        $stRow = $typeStmt->fetch();
        $subtypeName = $stRow['name'] ?? 'Equipment';
        if ($typeId <= 0 && $stRow) {
            $typeId = (int)$stRow['equipment_type_id'];
        }

        $sNameStmt = $pdo->prepare("SELECT name FROM tbl_inventory_equipment_statuses WHERE id = :id");
        $sNameStmt->execute([':id' => $statusId]);
        $statusName = $sNameStmt->fetchColumn() ?: 'Serviceable';

        $userId = $_SESSION['user_id'] ?? 1;
        $propertyNumber = isset($input['property_number']) ? trim($input['property_number']) : null;
        if ($propertyNumber === '') $propertyNumber = null;

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO tbl_inventory_equipment 
                (office_id, equipment_type_id, equipment_subtype_id, status_id, equipment_type, description, serial_number, property_number, date_acquired, status, created_by, modified_by, created_at, updated_at) 
                VALUES (:office_id, :type_id, :subtype_id, :status_id, :equipment_type, :description, :serial_number, :property_number, :date_acquired, :status, :created_by, :modified_by, NOW(), NOW())
            ");
            $stmt->execute([
                ':office_id' => $officeId,
                ':type_id' => $typeId,
                ':subtype_id' => $subtypeId,
                ':status_id' => $statusId,
                ':equipment_type' => $subtypeName,
                ':description' => $description,
                ':serial_number' => $serialNumber,
                ':property_number' => $propertyNumber,
                ':date_acquired' => $dateAcquired,
                ':status' => $statusName,
                ':created_by' => $userId,
                ':modified_by' => $userId
            ]);
            $newEquipmentId = (int)$pdo->lastInsertId();

            if (!empty($attributes) && is_array($attributes)) {
                foreach ($attributes as $attrDefId => $val) {
                    if ($val === null || $val === '') continue;
                    $defStmt = $pdo->prepare("SELECT data_type FROM tbl_inventory_attribute_definitions WHERE id = :def_id");
                    $defStmt->execute([':def_id' => (int)$attrDefId]);
                    $dataType = $defStmt->fetchColumn() ?: 'text';

                    $valText = null; $valNum = null; $valDec = null; $valDate = null; $valBool = null;
                    switch ($dataType) {
                        case 'number': $valNum = (int)$val; break;
                        case 'decimal': $valDec = (float)$val; break;
                        case 'date': $valDate = (string)$val; break;
                        case 'boolean': $valBool = $val ? 1 : 0; break;
                        case 'text':
                        default: $valText = (string)$val; break;
                    }

                    $attrIns = $pdo->prepare("
                        INSERT INTO tbl_inventory_equipment_attribute_values 
                        (equipment_id, attribute_definition_id, value_text, value_number, value_decimal, value_date, value_boolean, created_at, updated_at) 
                        VALUES (:eq_id, :def_id, :v_text, :v_num, :v_dec, :v_date, :v_bool, NOW(), NOW())
                    ");
                    $attrIns->execute([
                        ':eq_id' => $newEquipmentId,
                        ':def_id' => (int)$attrDefId,
                        ':v_text' => $valText,
                        ':v_num' => $valNum,
                        ':v_dec' => $valDec,
                        ':v_date' => $valDate,
                        ':v_bool' => $valBool
                    ]);
                }
            }
            $pdo->commit();
            sendJsonResponse(true, 'Equipment record created successfully.', ['id' => $newEquipmentId]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            sendJsonResponse(false, 'Failed to create equipment record.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 3. Update Equipment Record
    if ($action === 'update_equipment') {
        requirePermission('inventory', 'edit', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $eqId = (int)($input['id'] ?? 0);
        $officeId = (int)($input['office_id'] ?? 0);
        $typeId = (int)($input['equipment_type_id'] ?? 0);
        $subtypeId = (int)($input['equipment_subtype_id'] ?? 0);
        $statusId = (int)($input['status_id'] ?? 0);
        $serialNumber = trim($input['serial_number'] ?? '');
        $description = trim($input['description'] ?? '');
        $dateAcquired = trim($input['date_acquired'] ?? date('Y-m-d'));
        $attributes = $input['attributes'] ?? [];

        if ($eqId <= 0 || $officeId <= 0 || $subtypeId <= 0 || empty($serialNumber)) {
            sendJsonResponse(false, 'Equipment ID, office assignment, equipment subtype, and serial number are required.', null, null, 400);
        }

        $typeStmt = $pdo->prepare("SELECT name, equipment_type_id FROM tbl_inventory_equipment_subtypes WHERE id = :id");
        $typeStmt->execute([':id' => $subtypeId]);
        $stRow = $typeStmt->fetch();
        $subtypeName = $stRow['name'] ?? 'Equipment';
        if ($typeId <= 0 && $stRow) {
            $typeId = (int)$stRow['equipment_type_id'];
        }

        $sNameStmt = $pdo->prepare("SELECT name FROM tbl_inventory_equipment_statuses WHERE id = :id");
        $sNameStmt->execute([':id' => $statusId]);
        $statusName = $sNameStmt->fetchColumn() ?: 'Serviceable';

        $userId = $_SESSION['user_id'] ?? 1;
        $propertyNumber = isset($input['property_number']) ? trim($input['property_number']) : null;
        if ($propertyNumber === '') $propertyNumber = null;

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                UPDATE tbl_inventory_equipment 
                SET office_id = :office_id, equipment_type_id = :type_id, equipment_subtype_id = :subtype_id, status_id = :status_id,
                    equipment_type = :equipment_type, description = :description, serial_number = :serial_number, property_number = :property_number,
                    date_acquired = :date_acquired, status = :status, modified_by = :modified_by, updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':office_id' => $officeId,
                ':type_id' => $typeId,
                ':subtype_id' => $subtypeId,
                ':status_id' => $statusId,
                ':equipment_type' => $subtypeName,
                ':description' => $description,
                ':serial_number' => $serialNumber,
                ':property_number' => $propertyNumber,
                ':date_acquired' => $dateAcquired,
                ':status' => $statusName,
                ':modified_by' => $userId,
                ':id' => $eqId
            ]);

            if (!empty($attributes) && is_array($attributes)) {
                $delAttr = $pdo->prepare("DELETE FROM tbl_inventory_equipment_attribute_values WHERE equipment_id = :eq_id");
                $delAttr->execute([':eq_id' => $eqId]);

                foreach ($attributes as $attrDefId => $val) {
                    if ($val === null || $val === '') continue;
                    $defStmt = $pdo->prepare("SELECT data_type FROM tbl_inventory_attribute_definitions WHERE id = :def_id");
                    $defStmt->execute([':def_id' => (int)$attrDefId]);
                    $dataType = $defStmt->fetchColumn() ?: 'text';

                    $valText = null; $valNum = null; $valDec = null; $valDate = null; $valBool = null;
                    switch ($dataType) {
                        case 'number': $valNum = (int)$val; break;
                        case 'decimal': $valDec = (float)$val; break;
                        case 'date': $valDate = (string)$val; break;
                        case 'boolean': $valBool = $val ? 1 : 0; break;
                        case 'text':
                        default: $valText = (string)$val; break;
                    }

                    $attrIns = $pdo->prepare("
                        INSERT INTO tbl_inventory_equipment_attribute_values 
                        (equipment_id, attribute_definition_id, value_text, value_number, value_decimal, value_date, value_boolean, created_at, updated_at) 
                        VALUES (:eq_id, :def_id, :v_text, :v_num, :v_dec, :v_date, :v_bool, NOW(), NOW())
                    ");
                    $attrIns->execute([
                        ':eq_id' => $eqId,
                        ':def_id' => (int)$attrDefId,
                        ':v_text' => $valText,
                        ':v_num' => $valNum,
                        ':v_dec' => $valDec,
                        ':v_date' => $valDate,
                        ':v_bool' => $valBool
                    ]);
                }
            }
            $pdo->commit();
            sendJsonResponse(true, 'Equipment record updated successfully.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            sendJsonResponse(false, 'Failed to update equipment record.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 4. Delete Equipment Record
    if ($action === 'delete_equipment') {
        requirePermission('inventory', 'delete', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $eqId = (int)($input['id'] ?? 0);
        if ($eqId <= 0) {
            sendJsonResponse(false, 'Invalid equipment ID.', null, null, 400);
        }
        try {
            $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment SET deleted_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $eqId]);
            sendJsonResponse(true, 'Equipment record deleted successfully.');
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to delete equipment record.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 5. Generate Historical Snapshot (Admin)
    if ($action === 'generate_snapshot') {
        requirePermission('inventory', 'configure', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $ym = trim($input['year_month'] ?? date('Y-m'));

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) {
            sendJsonResponse(false, 'Invalid reporting period format. Expected YYYY-MM.', null, null, 400);
        }

        // Safety Guard 1: Protect locked official historical baselines
        if (in_array($ym, ['2026-06', '2026-07'], true)) {
            sendJsonResponse(false, "Historical snapshot for period {$ym} is a locked official baseline and cannot be overwritten.", null, null, 400);
        }

        // Safety Guard 2: Protect against silent duplicate snapshot overwrite
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :ym");
        $checkStmt->execute([':ym' => $ym]);
        $existingCount = (int)$checkStmt->fetchColumn();

        $allowOverwrite = !empty($input['overwrite']);
        if ($existingCount > 0 && !$allowOverwrite) {
            sendJsonResponse(false, "A historical snapshot for period {$ym} already exists ({$existingCount} records). Pass overwrite=true to replace it.", null, null, 409);
        }

        try {
            $pdo->beginTransaction();
            if ($existingCount > 0) {
                $del = $pdo->prepare("DELETE FROM tbl_inventory_history WHERE `year_month` = :ym");
                $del->execute([':ym' => $ym]);
            }

            $ins = $pdo->prepare("
                INSERT INTO tbl_inventory_history 
                (`year_month`, equipment_id, office_id, equipment_type_id, equipment_subtype_id, status_id, equipment_type, description, serial_number, property_number, date_acquired, status, snapshot_date, created_at, updated_at)
                SELECT :ym, id, office_id, equipment_type_id, equipment_subtype_id, status_id, equipment_type, description, serial_number, property_number, date_acquired, status, NOW(), NOW(), NOW()
                FROM tbl_inventory_equipment
                WHERE deleted_at IS NULL
            ");
            $ins->execute([':ym' => $ym]);
            $insertedCount = $ins->rowCount();
            $pdo->commit();
            sendJsonResponse(true, "Historical snapshot for period {$ym} generated successfully.", ['year_month' => $ym, 'records_captured' => $insertedCount]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            sendJsonResponse(false, 'Failed to generate historical snapshot.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 6. Save (Create/Update) Equipment Type (Admin)
    if ($action === 'save_equipment_type') {
        requirePermission('inventory', 'configure', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $code = trim($input['code'] ?? strtoupper($name));

        if (empty($name)) {
            sendJsonResponse(false, 'Equipment type name is required.', null, null, 400);
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_types SET name = :name, code = :code, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':name' => $name, ':code' => $code, ':id' => $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO tbl_inventory_equipment_types (name, code, is_active, created_at, updated_at) VALUES (:name, :code, 1, NOW(), NOW())");
                $stmt->execute([':name' => $name, ':code' => $code]);
            }
            sendJsonResponse(true, 'Equipment type saved successfully.');
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to save equipment type.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 7. Delete Equipment Type (Admin)
    if ($action === 'delete_equipment_type') {
        requirePermission('inventory', 'configure', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Invalid equipment type ID.', null, null, 400);
        try {
            $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_types SET deleted_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $id]);
            sendJsonResponse(true, 'Equipment type deleted successfully.');
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to delete equipment type.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 8. Save (Create/Update) Equipment Subtype (Admin)
    if ($action === 'save_equipment_subtype') {
        requirePermission('inventory', 'configure', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $typeId = (int)($input['equipment_type_id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $code = trim($input['code'] ?? strtoupper($name));

        if ($typeId <= 0 || empty($name)) {
            sendJsonResponse(false, 'Equipment type ID and subtype name are required.', null, null, 400);
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_subtypes SET equipment_type_id = :type_id, name = :name, code = :code, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':type_id' => $typeId, ':name' => $name, ':code' => $code, ':id' => $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO tbl_inventory_equipment_subtypes (equipment_type_id, name, code, is_active, created_at, updated_at) VALUES (:type_id, :name, :code, 1, NOW(), NOW())");
                $stmt->execute([':type_id' => $typeId, ':name' => $name, ':code' => $code]);
            }
            sendJsonResponse(true, 'Equipment subtype saved successfully.');
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to save equipment subtype.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 9. Delete Equipment Subtype (Admin)
    if ($action === 'delete_equipment_subtype') {
        requirePermission('inventory', 'configure', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Invalid equipment subtype ID.', null, null, 400);
        try {
            $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_subtypes SET deleted_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $id]);
            sendJsonResponse(true, 'Equipment subtype deleted successfully.');
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to delete equipment subtype.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 10. Save (Create/Update) Equipment Status (Admin)
    if ($action === 'save_equipment_status') {
        requirePermission('inventory', 'configure', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $code = trim($input['code'] ?? strtoupper($name));

        if (empty($name)) sendJsonResponse(false, 'Status name is required.', null, null, 400);

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_statuses SET name = :name, code = :code, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':name' => $name, ':code' => $code, ':id' => $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO tbl_inventory_equipment_statuses (name, code, is_active, created_at, updated_at) VALUES (:name, :code, 1, NOW(), NOW())");
                $stmt->execute([':name' => $name, ':code' => $code]);
            }
            sendJsonResponse(true, 'Equipment status saved successfully.');
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to save equipment status.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 11. Delete Equipment Status (Admin)
    if ($action === 'delete_equipment_status') {
        requirePermission('inventory', 'configure', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Invalid status ID.', null, null, 400);
        try {
            $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_statuses SET deleted_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $id]);
            sendJsonResponse(true, 'Equipment status deleted successfully.');
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to delete equipment status.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 12. Save (Create/Update) Attribute Definition (Admin)
    if ($action === 'save_attribute_definition') {
        requirePermission('inventory', 'configure', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $subtypeId = (int)($input['equipment_subtype_id'] ?? 0);
        $name = trim($input['attribute_name'] ?? '');
        $code = trim($input['attribute_code'] ?? strtoupper($name));
        $dataType = trim($input['data_type'] ?? 'text');
        $isRequired = !empty($input['is_required']) ? 1 : 0;
        $sortOrder = (int)($input['sort_order'] ?? 0);

        if ($subtypeId <= 0 || empty($name)) {
            sendJsonResponse(false, 'Equipment subtype ID and attribute name are required.', null, null, 400);
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE tbl_inventory_attribute_definitions SET equipment_subtype_id = :st_id, attribute_name = :name, attribute_code = :code, data_type = :dt, is_required = :req, sort_order = :sort, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':st_id' => $subtypeId, ':name' => $name, ':code' => $code, ':dt' => $dataType, ':req' => $isRequired, ':sort' => $sortOrder, ':id' => $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO tbl_inventory_attribute_definitions (equipment_subtype_id, attribute_name, attribute_code, data_type, is_required, sort_order, is_active, created_at, updated_at) VALUES (:st_id, :name, :code, :dt, :req, :sort, 1, NOW(), NOW())");
                $stmt->execute([':st_id' => $subtypeId, ':name' => $name, ':code' => $code, ':dt' => $dataType, ':req' => $isRequired, ':sort' => $sortOrder]);
            }
            sendJsonResponse(true, 'Attribute definition saved successfully.');
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to save attribute definition.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 13. Delete Attribute Definition (Admin)
    if ($action === 'delete_attribute_definition') {
        requirePermission('inventory', 'configure', $pdo);
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Invalid attribute definition ID.', null, null, 400);
        try {
            $stmt = $pdo->prepare("UPDATE tbl_inventory_attribute_definitions SET deleted_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $id]);
            sendJsonResponse(true, 'Attribute definition deleted successfully.');
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to delete attribute definition.', null, ['db' => $e->getMessage()], 500);
        }
    }
}

// ==========================================
// GET ACTIONS & VIEWS
// ==========================================

if ($method === 'GET') {
    requirePermission('inventory', 'view', $pdo);

    // 1. Available Equipment Types
    if ($view === 'equipment_types') {
        try {
            $stmt = $pdo->query("SELECT id, name, code, is_active FROM tbl_inventory_equipment_types WHERE deleted_at IS NULL ORDER BY id ASC");
            sendJsonResponse(true, 'Equipment types retrieved.', $stmt->fetchAll());
        } catch (Exception $e) {
            sendJsonResponse(true, 'Equipment types retrieved.', []);
        }
    }

    // 2. Equipment Subtypes
    if ($view === 'equipment_subtypes') {
        $typeId = isset($_GET['type_id']) ? (int)$_GET['type_id'] : (isset($_GET['equipment_type_id']) ? (int)$_GET['equipment_type_id'] : 0);
        try {
            if ($typeId > 0) {
                $stmt = $pdo->prepare("SELECT st.id, st.equipment_type_id, t.name as equipment_type_name, st.name, st.code, st.is_active FROM tbl_inventory_equipment_subtypes st LEFT JOIN tbl_inventory_equipment_types t ON st.equipment_type_id = t.id WHERE st.equipment_type_id = :type_id AND st.deleted_at IS NULL ORDER BY st.name ASC");
                $stmt->execute([':type_id' => $typeId]);
            } else {
                $stmt = $pdo->query("SELECT st.id, st.equipment_type_id, t.name as equipment_type_name, st.name, st.code, st.is_active FROM tbl_inventory_equipment_subtypes st LEFT JOIN tbl_inventory_equipment_types t ON st.equipment_type_id = t.id WHERE st.deleted_at IS NULL ORDER BY st.equipment_type_id ASC, st.name ASC");
            }
            sendJsonResponse(true, 'Equipment subtypes retrieved.', $stmt->fetchAll());
        } catch (Exception $e) {
            sendJsonResponse(true, 'Equipment subtypes retrieved.', []);
        }
    }

    // 3. Equipment Statuses
    if ($view === 'equipment_statuses' || $view === 'statuses') {
        try {
            $stmt = $pdo->query("SELECT id, name, code, is_active FROM tbl_inventory_equipment_statuses WHERE deleted_at IS NULL ORDER BY id ASC");
            sendJsonResponse(true, 'Equipment statuses retrieved.', $stmt->fetchAll());
        } catch (Exception $e) {
            sendJsonResponse(true, 'Equipment statuses retrieved.', []);
        }
    }

    // 4. Offices List
    if ($view === 'offices') {
        try {
            $stmt = $pdo->query("SELECT id, office_name, office_abbv FROM tbl_offices WHERE deleted_at IS NULL ORDER BY office_abbv ASC");
            sendJsonResponse(true, 'Offices retrieved.', $stmt->fetchAll());
        } catch (Exception $e) {
            sendJsonResponse(true, 'Offices retrieved.', []);
        }
    }

    // 5. Attribute Definitions
    if ($view === 'attribute_definitions') {
        $subtypeId = isset($_GET['subtype_id']) ? (int)$_GET['subtype_id'] : (isset($_GET['equipment_subtype_id']) ? (int)$_GET['equipment_subtype_id'] : 0);
        try {
            if ($subtypeId > 0) {
                $stmt = $pdo->prepare("SELECT id, equipment_subtype_id, attribute_name, attribute_code, data_type, is_required, sort_order, is_active FROM tbl_inventory_attribute_definitions WHERE equipment_subtype_id = :st_id AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC");
                $stmt->execute([':st_id' => $subtypeId]);
            } else {
                $stmt = $pdo->query("SELECT id, equipment_subtype_id, attribute_name, attribute_code, data_type, is_required, sort_order, is_active FROM tbl_inventory_attribute_definitions WHERE deleted_at IS NULL ORDER BY equipment_subtype_id ASC, sort_order ASC");
            }
            sendJsonResponse(true, 'Attribute definitions retrieved.', $stmt->fetchAll());
        } catch (Exception $e) {
            sendJsonResponse(true, 'Attribute definitions retrieved.', []);
        }
    }

    // 6. Reference Options (Types, Subtypes, Statuses, Offices)
    if ($view === 'reference_options') {
        try {
            $types = $pdo->query("SELECT id, name, code FROM tbl_inventory_equipment_types WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id ASC")->fetchAll();
            $subtypes = $pdo->query("SELECT id, equipment_type_id, name, code FROM tbl_inventory_equipment_subtypes WHERE is_active = 1 AND deleted_at IS NULL ORDER BY equipment_type_id ASC, name ASC")->fetchAll();
            $statuses = $pdo->query("SELECT id, name, code FROM tbl_inventory_equipment_statuses WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id ASC")->fetchAll();
            $offices = $pdo->query("SELECT id, office_name, office_abbv FROM tbl_offices WHERE deleted_at IS NULL ORDER BY office_abbv ASC")->fetchAll();

            sendJsonResponse(true, 'Reference options retrieved.', [
                'equipment_types' => $types,
                'equipment_subtypes' => $subtypes,
                'types' => $types,
                'subtypes' => $subtypes,
                'statuses' => $statuses,
                'offices' => $offices
            ]);
        } catch (Exception $e) {
            sendJsonResponse(true, 'Reference options retrieved.', [
                'equipment_types' => [], 'equipment_subtypes' => [],
                'types' => [], 'subtypes' => [], 'statuses' => [], 'offices' => []
            ]);
        }
    }

    // 7. Reporting Periods
    $currentYearMonth = date('Y-m');
    if ($view === 'periods') {
        try {
            $historyStmt = $pdo->query("SELECT DISTINCT `year_month` FROM tbl_inventory_history ORDER BY `year_month` DESC");
            $allPeriods = $historyStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) {
            $allPeriods = [];
        }

        if (!in_array($currentYearMonth, $allPeriods)) {
            array_unshift($allPeriods, $currentYearMonth);
        }

        $periodObjects = array_map(function($ym) use ($currentYearMonth) {
            return [
                'year_month' => $ym,
                'label' => formatPeriodLabel($ym),
                'is_current' => ($ym === $currentYearMonth)
            ];
        }, $allPeriods);

        sendJsonResponse(true, 'Reporting periods retrieved.', $periodObjects);
    }

    $selectedPeriod = $_GET['period'] ?? $currentYearMonth;
    $isCurrentMonth = ($selectedPeriod === $currentYearMonth);

    // 8. Overview Metrics & Readiness Calculations
    if ($view === 'overview') {
        if ($isCurrentMonth) {
            $totalStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE deleted_at IS NULL");
            $totalEquipment = (int)$totalStmt->fetchColumn();

            $serviceableStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE (status_id = 1 OR status = 'Serviceable' OR status_id IS NULL) AND deleted_at IS NULL");
            $serviceableCount = (int)$serviceableStmt->fetchColumn();

            $repairStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE (status_id = 2 OR status = 'For Repair') AND deleted_at IS NULL");
            $forRepairCount = (int)$repairStmt->fetchColumn();

            $turnInStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE (status_id = 3 OR status LIKE 'For Turn%' OR status = 'Unserviceable') AND deleted_at IS NULL");
            $unserviceableCount = (int)$turnInStmt->fetchColumn();
        } else {
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period");
            $totalStmt->execute([':period' => $selectedPeriod]);
            $totalEquipment = (int)$totalStmt->fetchColumn();

            $serviceableStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period AND (status_id = 1 OR status = 'Serviceable')");
            $serviceableStmt->execute([':period' => $selectedPeriod]);
            $serviceableCount = (int)$serviceableStmt->fetchColumn();

            $repairStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period AND (status_id = 2 OR status = 'For Repair')");
            $repairStmt->execute([':period' => $selectedPeriod]);
            $forRepairCount = (int)$repairStmt->fetchColumn();

            $turnInStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period AND (status_id = 3 OR status LIKE 'For Turn%')");
            $turnInStmt->execute([':period' => $selectedPeriod]);
            $unserviceableCount = (int)$turnInStmt->fetchColumn();
        }

        $maintenanceReadinessPct = $totalEquipment > 0
            ? round(($serviceableCount / $totalEquipment) * 100, 1)
            : 0.0;

        try {
            $jrrsStmt = $pdo->query("
                SELECT j.id, j.equipment_subtype_id, st.name AS equipment_subtype_name, t.name AS equipment_type_name, j.target_quantity
                FROM tbl_inventory_jrrs j
                JOIN tbl_inventory_equipment_subtypes st ON j.equipment_subtype_id = st.id
                JOIN tbl_inventory_equipment_types t ON st.equipment_type_id = t.id
                WHERE j.deleted_at IS NULL AND st.deleted_at IS NULL
                ORDER BY t.name ASC, st.name ASC
            ");
            $jrrsTargets = $jrrsStmt->fetchAll();
        } catch (Exception $e) {
            $jrrsTargets = [];
        }

        $totalTargetQty = 0;
        $totalCurrentQty = 0;
        $typeBreakdown = [];

        foreach ($jrrsTargets as $jrrs) {
            $subtypeId = (int)$jrrs['equipment_subtype_id'];
            $subtypeName = $jrrs['equipment_subtype_name'];
            $typeName = $jrrs['equipment_type_name'];
            $target = (int)$jrrs['target_quantity'];
            $totalTargetQty += $target;

            if ($isCurrentMonth) {
                $cStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM tbl_inventory_equipment e
                    LEFT JOIN tbl_inventory_equipment_subtypes st ON (
                        e.equipment_subtype_id = st.id OR LOWER(e.equipment_type) = LOWER(st.name)
                        OR (e.equipment_type LIKE '%Desktop%' AND st.name = 'Desktop')
                        OR (e.equipment_type LIKE '%PA%' AND st.name = 'Public Address System')
                        OR (e.equipment_type LIKE '%Public Address%' AND st.name = 'Public Address System')
                    )
                    WHERE st.id = :st_id AND e.deleted_at IS NULL
                ");
                $cStmt->execute([':st_id' => $subtypeId]);
                $currentQty = (int)$cStmt->fetchColumn();
            } else {
                $cStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM tbl_inventory_history h
                    LEFT JOIN tbl_inventory_equipment_subtypes st ON (
                        h.equipment_subtype_id = st.id OR LOWER(h.equipment_type) = LOWER(st.name)
                        OR (h.equipment_type LIKE '%Desktop%' AND st.name = 'Desktop')
                        OR (h.equipment_type LIKE '%PA%' AND st.name = 'Public Address System')
                        OR (h.equipment_type LIKE '%Public Address%' AND st.name = 'Public Address System')
                    )
                    WHERE h.`year_month` = :period AND st.id = :st_id
                ");
                $cStmt->execute([':period' => $selectedPeriod, ':st_id' => $subtypeId]);
                $currentQty = (int)$cStmt->fetchColumn();
            }
            $totalCurrentQty += $currentQty;

            $shortage = max(0, $target - $currentQty);
            $typeReadinessPct = $target > 0 ? round(($currentQty / $target) * 100, 1) : 0.0;

            $typeBreakdown[] = [
                'equipment_subtype_id' => $subtypeId,
                'equipment_subtype' => $subtypeName,
                'equipment_type' => $typeName,
                'target_quantity' => $target,
                'current_quantity' => $currentQty,
                'shortage' => $shortage,
                'readiness_pct' => $typeReadinessPct
            ];
        }

        $equipmentReadinessPct = $totalTargetQty > 0
            ? round(($totalCurrentQty / $totalTargetQty) * 100, 1)
            : 0.0;

        $overviewData = [
            'period' => $selectedPeriod,
            'period_label' => formatPeriodLabel($selectedPeriod),
            'is_current' => $isCurrentMonth,
            'maintenance_readiness_pct' => $maintenanceReadinessPct,
            'equipment_readiness_pct' => $equipmentReadinessPct,
            'total_equipment' => $totalEquipment,
            'serviceable_count' => $serviceableCount,
            'for_repair_count' => $forRepairCount,
            'unserviceable_count' => $unserviceableCount,
            'type_breakdown' => $typeBreakdown
        ];

        sendJsonResponse(true, 'Overview summary calculated.', $overviewData);
    }

    // 9. JRRS Target List Endpoint
    if ($view === 'jrrs') {
        try {
            $jrrsStmt = $pdo->query("
                SELECT j.id, j.equipment_subtype_id, st.name AS equipment_subtype, t.name AS equipment_type, j.target_quantity
                FROM tbl_inventory_jrrs j
                JOIN tbl_inventory_equipment_subtypes st ON j.equipment_subtype_id = st.id
                JOIN tbl_inventory_equipment_types t ON st.equipment_type_id = t.id
                WHERE j.deleted_at IS NULL AND st.deleted_at IS NULL
                ORDER BY t.name ASC, st.name ASC
            ");
            $jrrsTargets = $jrrsStmt->fetchAll();
        } catch (Exception $e) {
            $jrrsTargets = [];
        }

        $items = [];
        foreach ($jrrsTargets as $jrrs) {
            $subtypeId = (int)$jrrs['equipment_subtype_id'];
            $subtypeName = $jrrs['equipment_subtype'];
            $typeName = $jrrs['equipment_type'];
            $target = (int)$jrrs['target_quantity'];

            if ($isCurrentMonth) {
                $cStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM tbl_inventory_equipment e
                    LEFT JOIN tbl_inventory_equipment_subtypes st ON (
                        e.equipment_subtype_id = st.id OR LOWER(e.equipment_type) = LOWER(st.name)
                        OR (e.equipment_type LIKE '%Desktop%' AND st.name = 'Desktop')
                        OR (e.equipment_type LIKE '%PA%' AND st.name = 'Public Address System')
                        OR (e.equipment_type LIKE '%Public Address%' AND st.name = 'Public Address System')
                    )
                    WHERE st.id = :st_id AND e.deleted_at IS NULL
                ");
                $cStmt->execute([':st_id' => $subtypeId]);
                $currentQty = (int)$cStmt->fetchColumn();
            } else {
                $cStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM tbl_inventory_history h
                    LEFT JOIN tbl_inventory_equipment_subtypes st ON (
                        h.equipment_subtype_id = st.id OR LOWER(h.equipment_type) = LOWER(st.name)
                        OR (h.equipment_type LIKE '%Desktop%' AND st.name = 'Desktop')
                        OR (h.equipment_type LIKE '%PA%' AND st.name = 'Public Address System')
                        OR (h.equipment_type LIKE '%Public Address%' AND st.name = 'Public Address System')
                    )
                    WHERE h.`year_month` = :period AND st.id = :st_id
                ");
                $cStmt->execute([':period' => $selectedPeriod, ':st_id' => $subtypeId]);
                $currentQty = (int)$cStmt->fetchColumn();
            }

            $shortage = max(0, $target - $currentQty);
            $readinessPct = $target > 0 ? round(($currentQty / $target) * 100, 1) : 0.0;

            $items[] = [
                'id' => (int)$jrrs['id'],
                'equipment_subtype_id' => $subtypeId,
                'equipment_subtype' => $subtypeName,
                'equipment_type' => $typeName,
                'target_quantity' => $target,
                'current_quantity' => $currentQty,
                'shortage' => $shortage,
                'readiness_pct' => $readinessPct
            ];
        }

        sendJsonResponse(true, 'JRRS list retrieved.', [
            'period' => $selectedPeriod,
            'period_label' => formatPeriodLabel($selectedPeriod),
            'is_current' => $isCurrentMonth,
            'items' => $items
        ]);
    }

    // 9B. G6 Equipment Readiness Reporting Engine
    if ($view === 'g6_readiness') {
        requirePermission('inventory', 'view', $pdo);
        require_once __DIR__ . '/../../services/G6ReadinessService.php';

        $periodParam = isset($_GET['period']) ? trim($_GET['period']) : null;
        try {
            $reportData = G6ReadinessService::calculate($pdo, $periodParam);
            sendJsonResponse(true, 'G6 Equipment Readiness Report retrieved.', $reportData);
        } catch (Exception $e) {
            sendJsonResponse(false, 'Failed to calculate G6 Equipment Readiness Report.', null, ['error' => $e->getMessage()], 500);
        }
    }

    // 10. Equipment Retrieval (Single Item with Dynamic Attributes OR List)
    if ($view === 'equipment') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id > 0) {
            // Single Item Detail
            try {
                $stmt = $pdo->prepare("
                    SELECT e.id, e.office_id, o.office_abbv, o.office_name,
                           COALESCE(e.equipment_type_id, st.equipment_type_id, 1) AS equipment_type_id,
                           COALESCE(t.name, 'ICT') AS equipment_type_name,
                           COALESCE(e.equipment_subtype_id, st.id, 1) AS equipment_subtype_id,
                           COALESCE(st.name, e.equipment_type) AS equipment_subtype_name,
                           COALESCE(e.status_id, s.id, 1) AS status_id,
                           COALESCE(s.name, e.status) AS status_name,
                           e.description, e.serial_number, e.property_number, e.date_acquired,
                           COALESCE(t.name, 'ICT') AS equipment_type,
                           COALESCE(st.name, e.equipment_type) AS equipment_subtype,
                           COALESCE(s.name, e.status) AS status
                    FROM tbl_inventory_equipment e
                    JOIN tbl_offices o ON e.office_id = o.id
                    LEFT JOIN tbl_inventory_equipment_subtypes st ON (
                        e.equipment_subtype_id = st.id OR LOWER(e.equipment_type) = LOWER(st.name) 
                        OR (e.equipment_type LIKE '%Desktop%' AND st.name = 'Desktop')
                        OR (e.equipment_type LIKE '%PA%' AND st.name = 'Public Address System')
                        OR (e.equipment_type LIKE '%Public Address%' AND st.name = 'Public Address System')
                    )
                    LEFT JOIN tbl_inventory_equipment_types t ON (e.equipment_type_id = t.id OR st.equipment_type_id = t.id)
                    LEFT JOIN tbl_inventory_equipment_statuses s ON (e.status_id = s.id OR LOWER(e.status) = LOWER(s.name))
                    WHERE e.id = :id AND e.deleted_at IS NULL
                ");
                $stmt->execute([':id' => $id]);
                $item = $stmt->fetch();

                if (!$item) {
                    sendJsonResponse(false, 'Equipment record not found.', null, null, 404);
                }

                // Dynamic attributes
                $attrStmt = $pdo->prepare("
                    SELECT v.attribute_definition_id, d.attribute_name, d.data_type,
                           v.value_text, v.value_number, v.value_decimal, v.value_date, v.value_boolean
                    FROM tbl_inventory_equipment_attribute_values v
                    JOIN tbl_inventory_attribute_definitions d ON v.attribute_definition_id = d.id
                    WHERE v.equipment_id = :id AND d.deleted_at IS NULL
                    ORDER BY d.sort_order ASC
                ");
                $attrStmt->execute([':id' => $id]);
                $attrRows = $attrStmt->fetchAll();

                $attributes = [];
                foreach ($attrRows as $arow) {
                    $val = formatAttributeValue($arow);
                    $attributes[] = [
                        'attribute_definition_id' => (int)$arow['attribute_definition_id'],
                        'attribute_name' => $arow['attribute_name'],
                        'data_type' => $arow['data_type'],
                        'value' => $val,
                        'display_value' => is_bool($val) ? ($val ? 'Yes' : 'No') : (string)($val ?? 'N/A')
                    ];
                }
                $item['attributes'] = $attributes;
                sendJsonResponse(true, 'Equipment detail retrieved.', $item);
            } catch (Exception $e) {
                sendJsonResponse(false, 'Failed to fetch equipment details.', null, ['db' => $e->getMessage()], 500);
            }
        } else {
            // Equipment List
            if ($isCurrentMonth) {
                try {
                    $stmt = $pdo->query("
                        SELECT e.id, e.office_id, o.office_abbv, o.office_name,
                               COALESCE(e.equipment_type_id, st.equipment_type_id, 1) AS equipment_type_id,
                               COALESCE(t.name, 'ICT') AS equipment_type_name,
                               COALESCE(e.equipment_subtype_id, st.id, 1) AS equipment_subtype_id,
                               COALESCE(st.name, e.equipment_type) AS equipment_subtype_name,
                               COALESCE(e.status_id, s.id, 1) AS status_id,
                               COALESCE(s.name, e.status) AS status_name,
                               e.description, e.serial_number, e.property_number, e.date_acquired,
                               COALESCE(t.name, 'ICT') AS equipment_type,
                               COALESCE(st.name, e.equipment_type) AS equipment_subtype,
                               COALESCE(s.name, e.status) AS status
                        FROM tbl_inventory_equipment e
                        JOIN tbl_offices o ON e.office_id = o.id
                        LEFT JOIN tbl_inventory_equipment_subtypes st ON (
                            e.equipment_subtype_id = st.id OR LOWER(e.equipment_type) = LOWER(st.name) 
                            OR (e.equipment_type LIKE '%Desktop%' AND st.name = 'Desktop')
                            OR (e.equipment_type LIKE '%PA%' AND st.name = 'Public Address System')
                            OR (e.equipment_type LIKE '%Public Address%' AND st.name = 'Public Address System')
                        )
                        LEFT JOIN tbl_inventory_equipment_types t ON (e.equipment_type_id = t.id OR st.equipment_type_id = t.id)
                        LEFT JOIN tbl_inventory_equipment_statuses s ON (e.status_id = s.id OR LOWER(e.status) = LOWER(s.name))
                        WHERE e.deleted_at IS NULL
                        ORDER BY o.office_abbv ASC, e.id ASC
                    ");
                    $equipment = $stmt->fetchAll();
                } catch (Exception $e) {
                    $equipment = [];
                }
            } else {
                try {
                    $stmt = $pdo->prepare("
                        SELECT h.id, h.office_id, o.office_abbv, o.office_name,
                               COALESCE(h.equipment_type_id, st.equipment_type_id, 1) AS equipment_type_id,
                               COALESCE(t.name, 'ICT') AS equipment_type_name,
                               COALESCE(h.equipment_subtype_id, st.id, 1) AS equipment_subtype_id,
                               COALESCE(st.name, h.equipment_type) AS equipment_subtype_name,
                               COALESCE(h.status_id, s.id, 1) AS status_id,
                               COALESCE(s.name, h.status) AS status_name,
                               h.description, h.serial_number, h.property_number, h.date_acquired,
                               COALESCE(t.name, 'ICT') AS equipment_type,
                               COALESCE(st.name, h.equipment_type) AS equipment_subtype,
                               COALESCE(s.name, h.status) AS status
                        FROM tbl_inventory_history h
                        JOIN tbl_offices o ON h.office_id = o.id
                        LEFT JOIN tbl_inventory_equipment_subtypes st ON (
                            h.equipment_subtype_id = st.id OR LOWER(h.equipment_type) = LOWER(st.name) 
                            OR (h.equipment_type LIKE '%Desktop%' AND st.name = 'Desktop')
                            OR (h.equipment_type LIKE '%PA%' AND st.name = 'Public Address System')
                            OR (h.equipment_type LIKE '%Public Address%' AND st.name = 'Public Address System')
                        )
                        LEFT JOIN tbl_inventory_equipment_types t ON (h.equipment_type_id = t.id OR st.equipment_type_id = t.id)
                        LEFT JOIN tbl_inventory_equipment_statuses s ON (h.status_id = s.id OR LOWER(h.status) = LOWER(s.name))
                        WHERE h.`year_month` = :period
                        ORDER BY o.office_abbv ASC, h.id ASC
                    ");
                    $stmt->execute([':period' => $selectedPeriod]);
                    $equipment = $stmt->fetchAll();
                } catch (Exception $e) {
                    $equipment = [];
                }
            }

            sendJsonResponse(true, 'Equipment registry list retrieved.', [
                'period' => $selectedPeriod,
                'period_label' => formatPeriodLabel($selectedPeriod),
                'is_current' => $isCurrentMonth,
                'items' => $equipment
            ]);
        }
    }
}

sendJsonResponse(false, 'Invalid inventory endpoint request.', null, null, 400);

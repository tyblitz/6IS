<?php
// backend/api/inventory/index.php
// REST API Endpoint for 6IS Extensible Inventory Module & Readiness Calculations

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

// Require authenticated session for all inventory requests
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

$currentYearMonth = date('Y-m');

function formatPeriodLabel($ym) {
    $date = DateTime::createFromFormat('Y-m-d', $ym . '-01');
    return $date ? $date->format('F Y') : $ym;
}

// Helper to format attribute value for API output
function formatAttributeValue($attrRow) {
    $dataType = $attrRow['data_type'] ?? 'text';
    switch ($dataType) {
        case 'number':
            return isset($attrRow['value_number']) && $attrRow['value_number'] !== null ? (int)$attrRow['value_number'] : null;
        case 'decimal':
            return isset($attrRow['value_decimal']) && $attrRow['value_decimal'] !== null ? (float)$attrRow['value_decimal'] : null;
        case 'date':
            return $attrRow['value_date'] ?? null;
        case 'boolean':
            return isset($attrRow['value_boolean']) && $attrRow['value_boolean'] !== null ? (bool)$attrRow['value_boolean'] : null;
        case 'text':
        case 'select':
        default:
            return $attrRow['value_text'] ?? null;
    }
}

// GET Handler
if ($method === 'GET') {

    // 1. Reference Data: Equipment Types
    if ($view === 'equipment_types') {
        $stmt = $pdo->query("SELECT id, name, code, is_active FROM tbl_inventory_equipment_types WHERE deleted_at IS NULL ORDER BY id ASC");
        sendJsonResponse(true, 'Equipment types retrieved.', $stmt->fetchAll());
    }

    // 2. Reference Data: Equipment Subtypes
    if ($view === 'equipment_subtypes') {
        $typeId = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
        if ($typeId > 0) {
            $stmt = $pdo->prepare("SELECT st.id, st.equipment_type_id, t.name as equipment_type_name, st.name, st.code, st.is_active FROM tbl_inventory_equipment_subtypes st LEFT JOIN tbl_inventory_equipment_types t ON st.equipment_type_id = t.id WHERE st.equipment_type_id = :type_id AND st.deleted_at IS NULL ORDER BY st.name ASC");
            $stmt->execute([':type_id' => $typeId]);
        } else {
            $stmt = $pdo->query("SELECT st.id, st.equipment_type_id, t.name as equipment_type_name, st.name, st.code, st.is_active FROM tbl_inventory_equipment_subtypes st LEFT JOIN tbl_inventory_equipment_types t ON st.equipment_type_id = t.id WHERE st.deleted_at IS NULL ORDER BY st.equipment_type_id ASC, st.name ASC");
        }
        sendJsonResponse(true, 'Equipment subtypes retrieved.', $stmt->fetchAll());
    }

    // 3. Reference Data: Equipment Statuses
    if ($view === 'statuses') {
        $stmt = $pdo->query("SELECT id, name, code, is_active FROM tbl_inventory_equipment_statuses WHERE deleted_at IS NULL ORDER BY id ASC");
        sendJsonResponse(true, 'Equipment statuses retrieved.', $stmt->fetchAll());
    }

    // 4. Reference Data: Attribute Definitions
    if ($view === 'attribute_definitions') {
        $subtypeId = isset($_GET['subtype_id']) ? (int)$_GET['subtype_id'] : 0;
        if ($subtypeId > 0) {
            $stmt = $pdo->prepare("SELECT d.id, d.equipment_subtype_id, st.name as equipment_subtype_name, d.attribute_name, d.attribute_code, d.data_type, d.is_required, d.sort_order, d.is_active FROM tbl_inventory_attribute_definitions d LEFT JOIN tbl_inventory_equipment_subtypes st ON d.equipment_subtype_id = st.id WHERE d.equipment_subtype_id = :subtype_id AND d.deleted_at IS NULL ORDER BY d.sort_order ASC, d.attribute_name ASC");
            $stmt->execute([':subtype_id' => $subtypeId]);
        } else {
            $stmt = $pdo->query("SELECT d.id, d.equipment_subtype_id, st.name as equipment_subtype_name, d.attribute_name, d.attribute_code, d.data_type, d.is_required, d.sort_order, d.is_active FROM tbl_inventory_attribute_definitions d LEFT JOIN tbl_inventory_equipment_subtypes st ON d.equipment_subtype_id = st.id WHERE d.deleted_at IS NULL ORDER BY d.equipment_subtype_id ASC, d.sort_order ASC, d.attribute_name ASC");
        }
        sendJsonResponse(true, 'Attribute definitions retrieved.', $stmt->fetchAll());
    }

    // 5. Consolidated Reference Options
    if ($view === 'reference_options') {
        $types = $pdo->query("SELECT id, name, code FROM tbl_inventory_equipment_types WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id ASC")->fetchAll();
        $subtypes = $pdo->query("SELECT id, equipment_type_id, name, code FROM tbl_inventory_equipment_subtypes WHERE is_active = 1 AND deleted_at IS NULL ORDER BY equipment_type_id ASC, name ASC")->fetchAll();
        $statuses = $pdo->query("SELECT id, name, code FROM tbl_inventory_equipment_statuses WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id ASC")->fetchAll();
        
        sendJsonResponse(true, 'Reference options retrieved.', [
            'equipment_types' => $types,
            'equipment_subtypes' => $subtypes,
            'statuses' => $statuses
        ]);
    }

    // 6. Available Reporting Periods
    if ($view === 'periods') {
        $stmt = $pdo->query("SELECT DISTINCT `year_month` FROM tbl_inventory_history WHERE `year_month` != '' ORDER BY `year_month` DESC");
        $historyPeriods = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $allPeriods = array_values(array_unique(array_merge([$currentYearMonth], $historyPeriods)));
        rsort($allPeriods);

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

    // 7. Overview Metrics & Readiness Calculations
    if ($view === 'overview') {
        if ($isCurrentMonth) {
            $totalStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE deleted_at IS NULL");
            $totalEquipment = (int)$totalStmt->fetchColumn();

            $serviceableStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE status_id = 1 AND deleted_at IS NULL");
            $serviceableCount = (int)$serviceableStmt->fetchColumn();

            $repairStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE status_id = 2 AND deleted_at IS NULL");
            $forRepairCount = (int)$repairStmt->fetchColumn();

            $turnInStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE status_id = 3 AND deleted_at IS NULL");
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

        $jrrsStmt = $pdo->query("
            SELECT j.id, j.equipment_subtype_id, st.name AS equipment_subtype_name, t.name AS equipment_type_name, j.target_quantity
            FROM tbl_inventory_jrrs j
            JOIN tbl_inventory_equipment_subtypes st ON j.equipment_subtype_id = st.id
            JOIN tbl_inventory_equipment_types t ON st.equipment_type_id = t.id
            WHERE j.deleted_at IS NULL AND st.deleted_at IS NULL
            ORDER BY t.name ASC, st.name ASC
        ");
        $jrrsTargets = $jrrsStmt->fetchAll();

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
                $cStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE equipment_subtype_id = :st_id AND deleted_at IS NULL");
                $cStmt->execute([':st_id' => $subtypeId]);
            } else {
                $cStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period AND equipment_subtype_id = :st_id");
                $cStmt->execute([':period' => $selectedPeriod, ':st_id' => $subtypeId]);
            }
            $currentQty = (int)$cStmt->fetchColumn();
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

    // 8. Equipment Retrieval (Single Item with Dynamic Attributes OR List)
    if ($view === 'equipment') {

        // Single Equipment Item Detail Retrieval
        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("
                SELECT e.id, e.office_id, o.office_abbv, o.office_name,
                       e.equipment_type_id, t.name AS equipment_type_name, t.code AS equipment_type_code,
                       e.equipment_subtype_id, st.name AS equipment_subtype_name, st.code AS equipment_subtype_code,
                       e.status_id, s.name AS status_name, s.code AS status_code,
                       e.description, e.serial_number, e.date_acquired
                FROM tbl_inventory_equipment e
                JOIN tbl_offices o ON e.office_id = o.id
                JOIN tbl_inventory_equipment_types t ON e.equipment_type_id = t.id
                JOIN tbl_inventory_equipment_subtypes st ON e.equipment_subtype_id = st.id
                JOIN tbl_inventory_equipment_statuses s ON e.status_id = s.id
                WHERE e.id = :id AND e.deleted_at IS NULL
            ");
            $stmt->execute([':id' => $id]);
            $item = $stmt->fetch();

            if (!$item) {
                sendJsonResponse(false, 'Equipment record not found.', null, null, 404);
            }

            // Fetch dynamic attributes and values for this equipment item
            $attrStmt = $pdo->prepare("
                SELECT d.id AS attribute_definition_id, d.attribute_name, d.attribute_code, d.data_type, d.is_required, d.sort_order,
                       v.id AS value_id, v.value_text, v.value_number, v.value_decimal, v.value_date, v.value_boolean
                FROM tbl_inventory_attribute_definitions d
                LEFT JOIN tbl_inventory_equipment_attribute_values v ON d.id = v.attribute_definition_id AND v.equipment_id = :eq_id
                WHERE d.equipment_subtype_id = :subtype_id AND d.is_active = 1 AND d.deleted_at IS NULL
                ORDER BY d.sort_order ASC, d.attribute_name ASC
            ");
            $attrStmt->execute([
                ':eq_id' => $id,
                ':subtype_id' => $item['equipment_subtype_id']
            ]);
            $attrRows = $attrStmt->fetchAll();

            $attributesList = [];
            $attributesMap = [];

            foreach ($attrRows as $r) {
                $val = formatAttributeValue($r);
                
                // Formatted display string
                $dispVal = $val;
                if ($r['data_type'] === 'boolean') {
                    $dispVal = $val === true ? 'Yes' : ($val === false ? 'No' : '-');
                } else if ($dispVal === null || $dispVal === '') {
                    $dispVal = '-';
                }

                $attrItem = [
                    'attribute_definition_id' => (int)$r['attribute_definition_id'],
                    'attribute_name' => $r['attribute_name'],
                    'attribute_code' => $r['attribute_code'],
                    'data_type' => $r['data_type'],
                    'is_required' => (bool)$r['is_required'],
                    'sort_order' => (int)$r['sort_order'],
                    'value' => $val,
                    'display_value' => (string)$dispVal
                ];

                $attributesList[] = $attrItem;
                $attributesMap[$r['attribute_definition_id']] = $val;
            }

            $item['attributes'] = $attributesList;
            $item['attributes_map'] = $attributesMap;

            // Maintain legacy aliases for backwards compatibility
            $item['equipment_type'] = $item['equipment_type_name'];
            $item['equipment_subtype'] = $item['equipment_subtype_name'];
            $item['status'] = $item['status_name'];

            sendJsonResponse(true, 'Equipment record retrieved.', $item);
        }

        // Equipment Registry List Retrieval
        if ($isCurrentMonth) {
            $stmt = $pdo->query("
                SELECT e.id, e.office_id, o.office_abbv, o.office_name,
                       e.equipment_type_id, t.name AS equipment_type_name, t.code AS equipment_type_code,
                       e.equipment_subtype_id, st.name AS equipment_subtype_name, st.code AS equipment_subtype_code,
                       e.status_id, s.name AS status_name, s.code AS status_code,
                       e.description, e.serial_number, e.date_acquired,
                       t.name AS equipment_type, st.name AS equipment_subtype, s.name AS status
                FROM tbl_inventory_equipment e
                JOIN tbl_offices o ON e.office_id = o.id
                JOIN tbl_inventory_equipment_types t ON e.equipment_type_id = t.id
                JOIN tbl_inventory_equipment_subtypes st ON e.equipment_subtype_id = st.id
                JOIN tbl_inventory_equipment_statuses s ON e.status_id = s.id
                WHERE e.deleted_at IS NULL
                ORDER BY t.name ASC, st.name ASC, o.office_abbv ASC
            ");
            $equipment = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("
                SELECT h.id, h.office_id, o.office_abbv, o.office_name,
                       COALESCE(h.equipment_type_id, 1) AS equipment_type_id,
                       COALESCE(t.name, h.equipment_type) AS equipment_type_name,
                       COALESCE(h.equipment_subtype_id, 1) AS equipment_subtype_id,
                       COALESCE(st.name, h.equipment_type) AS equipment_subtype_name,
                       COALESCE(h.status_id, 1) AS status_id,
                       COALESCE(s.name, h.status) AS status_name,
                       h.description, h.serial_number, h.date_acquired,
                       COALESCE(t.name, h.equipment_type) AS equipment_type,
                       COALESCE(st.name, h.equipment_type) AS equipment_subtype,
                       COALESCE(s.name, h.status) AS status
                FROM tbl_inventory_history h
                JOIN tbl_offices o ON h.office_id = o.id
                LEFT JOIN tbl_inventory_equipment_types t ON h.equipment_type_id = t.id
                LEFT JOIN tbl_inventory_equipment_subtypes st ON h.equipment_subtype_id = st.id
                LEFT JOIN tbl_inventory_equipment_statuses s ON h.status_id = s.id
                WHERE h.`year_month` = :period
                ORDER BY h.equipment_type ASC, o.office_abbv ASC
            ");
            $stmt->execute([':period' => $selectedPeriod]);
            $equipment = $stmt->fetchAll();
        }

        sendJsonResponse(true, 'Equipment records retrieved.', [
            'period' => $selectedPeriod,
            'period_label' => formatPeriodLabel($selectedPeriod),
            'is_current' => $isCurrentMonth,
            'items' => $equipment
        ]);
    }

    // 9. JRRS Target Comparison List
    if ($view === 'jrrs') {
        $jrrsStmt = $pdo->query("
            SELECT j.id, j.equipment_subtype_id, st.name AS equipment_subtype_name,
                   st.equipment_type_id, t.name AS equipment_type_name, j.target_quantity
            FROM tbl_inventory_jrrs j
            JOIN tbl_inventory_equipment_subtypes st ON j.equipment_subtype_id = st.id
            JOIN tbl_inventory_equipment_types t ON st.equipment_type_id = t.id
            WHERE j.deleted_at IS NULL AND st.deleted_at IS NULL
            ORDER BY t.name ASC, st.name ASC
        ");
        $jrrsItems = $jrrsStmt->fetchAll();

        $result = [];
        foreach ($jrrsItems as $item) {
            $subtypeId = (int)$item['equipment_subtype_id'];
            $subtypeName = $item['equipment_subtype_name'];
            $typeName = $item['equipment_type_name'];
            $target = (int)$item['target_quantity'];

            if ($isCurrentMonth) {
                $cStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE equipment_subtype_id = :st_id AND deleted_at IS NULL");
                $cStmt->execute([':st_id' => $subtypeId]);
            } else {
                $cStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period AND equipment_subtype_id = :st_id");
                $cStmt->execute([':period' => $selectedPeriod, ':st_id' => $subtypeId]);
            }
            $currentQty = (int)$cStmt->fetchColumn();
            $shortage = max(0, $target - $currentQty);
            $readinessPct = $target > 0 ? round(($currentQty / $target) * 100, 1) : 0.0;

            $result[] = [
                'id' => (int)$item['id'],
                'equipment_subtype_id' => $subtypeId,
                'equipment_subtype' => $subtypeName,
                'equipment_type_id' => (int)$item['equipment_type_id'],
                'equipment_type' => $typeName,
                'target_quantity' => $target,
                'current_quantity' => $currentQty,
                'shortage' => $shortage,
                'readiness_pct' => $readinessPct
            ];
        }

        sendJsonResponse(true, 'JRRS comparison data retrieved.', [
            'period' => $selectedPeriod,
            'period_label' => formatPeriodLabel($selectedPeriod),
            'is_current' => $isCurrentMonth,
            'items' => $result
        ]);
    }

    // 10. Offices Reference List
    if ($view === 'offices') {
        $stmt = $pdo->query("SELECT id, office_name, office_code, office_abbv FROM tbl_offices WHERE is_active = 1 AND deleted_at IS NULL ORDER BY office_abbv ASC");
        sendJsonResponse(true, 'Offices retrieved.', $stmt->fetchAll());
    }
}

// POST Handler
if ($method === 'POST') {

    // 1. Action: update_jrrs (Modify Target Quantity - Administrator only)
    if ($action === 'update_jrrs') {
        requireRole('Administrator');

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        $subtypeId = isset($input['equipment_subtype_id']) ? (int)$input['equipment_subtype_id'] : 0;
        $targetQuantity = isset($input['target_quantity']) ? (int)$input['target_quantity'] : -1;

        if ($subtypeId <= 0 || $targetQuantity < 0) {
            sendJsonResponse(false, 'Valid equipment subtype and target quantity are required.', null, null, 400);
        }

        // Verify subtype exists
        $stCheck = $pdo->prepare("SELECT name FROM tbl_inventory_equipment_subtypes WHERE id = :id AND deleted_at IS NULL");
        $stCheck->execute([':id' => $subtypeId]);
        $stName = $stCheck->fetchColumn();

        if (!$stName) {
            sendJsonResponse(false, 'Selected equipment subtype does not exist.', null, null, 404);
        }

        $stmt = $pdo->prepare("
            INSERT INTO tbl_inventory_jrrs (equipment_subtype_id, equipment_type, target_quantity, created_at, updated_at)
            VALUES (:st_id, :st_name, :target, NOW(), NOW())
            ON DUPLICATE KEY UPDATE target_quantity = VALUES(target_quantity), updated_at = NOW()
        ");
        $stmt->execute([
            ':st_id' => $subtypeId,
            ':st_name' => $stName,
            ':target' => $targetQuantity
        ]);

        sendJsonResponse(true, "JRRS target quantity for '{$stName}' updated successfully.");
    }

    // 2. Action: create_equipment (Add new equipment with dynamic attributes - Authenticated users)
    if ($action === 'create_equipment') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        $officeId = (int)($input['office_id'] ?? 0);
        $equipmentTypeId = (int)($input['equipment_type_id'] ?? 0);
        $equipmentSubtypeId = (int)($input['equipment_subtype_id'] ?? 0);
        $statusId = (int)($input['status_id'] ?? 0);
        $description = trim($input['description'] ?? '');
        $serialNumber = trim($input['serial_number'] ?? '');
        $dateAcquired = trim($input['date_acquired'] ?? '');
        $attributesPayload = $input['attributes'] ?? [];

        // Required field validations
        if ($officeId <= 0 || $equipmentTypeId <= 0 || $equipmentSubtypeId <= 0 || $statusId <= 0 || empty($serialNumber) || empty($dateAcquired)) {
            sendJsonResponse(false, 'Office, equipment type, subtype, status, serial number, and date acquired are required.', null, null, 400);
        }

        // STRICT RELATIONSHIP VERIFICATION: Subtype must belong to Equipment Type
        $relStmt = $pdo->prepare("SELECT id, equipment_type_id, name FROM tbl_inventory_equipment_subtypes WHERE id = :st_id AND deleted_at IS NULL");
        $relStmt->execute([':st_id' => $equipmentSubtypeId]);
        $subtypeRow = $relStmt->fetch();

        if (!$subtypeRow || (int)$subtypeRow['equipment_type_id'] !== $equipmentTypeId) {
            sendJsonResponse(false, 'Invalid equipment subtype for the selected equipment type.', null, ['subtype' => 'Mismatch between subtype and parent equipment type.'], 400);
        }

        // Fetch type and status names for legacy compatibility columns
        $tName = $pdo->query("SELECT name FROM tbl_inventory_equipment_types WHERE id = {$equipmentTypeId}")->fetchColumn() ?: 'ICT';
        $sName = $pdo->query("SELECT name FROM tbl_inventory_equipment_statuses WHERE id = {$statusId}")->fetchColumn() ?: 'Serviceable';

        // STRICT ATTRIBUTE VERIFICATION: Fetch attribute definitions belonging to selected subtype
        $defStmt = $pdo->prepare("SELECT id, attribute_name, data_type, is_required FROM tbl_inventory_attribute_definitions WHERE equipment_subtype_id = :st_id AND is_active = 1 AND deleted_at IS NULL");
        $defStmt->execute([':st_id' => $equipmentSubtypeId]);
        $validDefs = $defStmt->fetchAll();

        $validDefMap = [];
        foreach ($validDefs as $def) {
            $validDefMap[$def['id']] = $def;
        }

        // Verify payload attributes strictly belong to this equipment subtype
        foreach ($attributesPayload as $defId => $val) {
            $defId = (int)$defId;
            if (!isset($validDefMap[$defId])) {
                sendJsonResponse(false, "Attribute ID {$defId} does not belong to equipment subtype '{$subtypeRow['name']}'.", null, null, 400);
            }
        }

        // Check required attribute definitions
        foreach ($validDefs as $def) {
            if ($def['is_required']) {
                $val = $attributesPayload[$def['id']] ?? null;
                if ($val === null || trim((string)$val) === '') {
                    sendJsonResponse(false, "Field '{$def['attribute_name']}' is required for {$subtypeRow['name']}.", null, null, 400);
                }
            }
        }

        // Save Equipment Record
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_inventory_equipment (office_id, equipment_type_id, equipment_subtype_id, status_id, equipment_type, description, serial_number, date_acquired, status, created_at, updated_at, created_by, modified_by)
                VALUES (:office_id, :type_id, :subtype_id, :status_id, :type_name, :desc, :sn, :date_acq, :status_name, NOW(), NOW(), :created_by, :modified_by)
            ");
            $stmt->execute([
                ':office_id' => $officeId,
                ':type_id' => $equipmentTypeId,
                ':subtype_id' => $equipmentSubtypeId,
                ':status_id' => $statusId,
                ':type_name' => $tName,
                ':desc' => $description,
                ':sn' => $serialNumber,
                ':date_acq' => $dateAcquired,
                ':status_name' => $sName,
                ':created_by' => $_SESSION['user_id'],
                ':modified_by' => $_SESSION['user_id']
            ]);
            $equipmentId = (int)$pdo->lastInsertId();

            // Insert Attribute Values
            $valStmt = $pdo->prepare("
                INSERT INTO tbl_inventory_equipment_attribute_values
                (equipment_id, attribute_definition_id, value_text, value_number, value_decimal, value_date, value_boolean, created_at, updated_at, created_by, modified_by)
                VALUES (:eq_id, :def_id, :val_text, :val_num, :val_dec, :val_date, :val_bool, NOW(), NOW(), :created_by, :modified_by)
            ");

            foreach ($attributesPayload as $defId => $val) {
                $defId = (int)$defId;
                if (!isset($validDefMap[$defId])) continue;

                $dataType = $validDefMap[$defId]['data_type'];
                $valText = null; $valNum = null; $valDec = null; $valDate = null; $valBool = null;

                if ($val !== null && $val !== '') {
                    switch ($dataType) {
                        case 'number': $valNum = (int)$val; break;
                        case 'decimal': $valDec = (float)$val; break;
                        case 'date': $valDate = (string)$val; break;
                        case 'boolean': $valBool = filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 1 : 0; break;
                        case 'text':
                        case 'select':
                        default: $valText = (string)$val; break;
                    }
                }

                $valStmt->execute([
                    ':eq_id' => $equipmentId,
                    ':def_id' => $defId,
                    ':val_text' => $valText,
                    ':val_num' => $valNum,
                    ':val_dec' => $valDec,
                    ':val_date' => $valDate,
                    ':val_bool' => $valBool,
                    ':created_by' => $_SESSION['user_id'],
                    ':modified_by' => $_SESSION['user_id']
                ]);
            }

            $pdo->commit();
            sendJsonResponse(true, "Equipment record created successfully.");
        } catch (Exception $e) {
            $pdo->rollBack();
            sendJsonResponse(false, 'Failed to create equipment record.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 3. Action: update_equipment (Update equipment & dynamic attributes - Authenticated users)
    if ($action === 'update_equipment') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        $id = (int)($input['id'] ?? 0);
        $officeId = (int)($input['office_id'] ?? 0);
        $equipmentTypeId = (int)($input['equipment_type_id'] ?? 0);
        $equipmentSubtypeId = (int)($input['equipment_subtype_id'] ?? 0);
        $statusId = (int)($input['status_id'] ?? 0);
        $description = trim($input['description'] ?? '');
        $serialNumber = trim($input['serial_number'] ?? '');
        $dateAcquired = trim($input['date_acquired'] ?? '');
        $attributesPayload = $input['attributes'] ?? [];

        if ($id <= 0 || $officeId <= 0 || $equipmentTypeId <= 0 || $equipmentSubtypeId <= 0 || $statusId <= 0 || empty($serialNumber) || empty($dateAcquired)) {
            sendJsonResponse(false, 'Equipment ID, office, type, subtype, status, serial number, and date acquired are required.', null, null, 400);
        }

        // Check existing equipment item
        $exStmt = $pdo->prepare("SELECT id, equipment_subtype_id FROM tbl_inventory_equipment WHERE id = :id AND deleted_at IS NULL");
        $exStmt->execute([':id' => $id]);
        $existingEquipment = $exStmt->fetch();

        if (!$existingEquipment) {
            sendJsonResponse(false, 'Equipment record not found.', null, null, 404);
        }

        // STRICT RELATIONSHIP VERIFICATION: Subtype must belong to Equipment Type
        $relStmt = $pdo->prepare("SELECT id, equipment_type_id, name FROM tbl_inventory_equipment_subtypes WHERE id = :st_id AND deleted_at IS NULL");
        $relStmt->execute([':st_id' => $equipmentSubtypeId]);
        $subtypeRow = $relStmt->fetch();

        if (!$subtypeRow || (int)$subtypeRow['equipment_type_id'] !== $equipmentTypeId) {
            sendJsonResponse(false, 'Invalid equipment subtype for the selected equipment type.', null, ['subtype' => 'Mismatch between subtype and parent equipment type.'], 400);
        }

        $tName = $pdo->query("SELECT name FROM tbl_inventory_equipment_types WHERE id = {$equipmentTypeId}")->fetchColumn() ?: 'ICT';
        $sName = $pdo->query("SELECT name FROM tbl_inventory_equipment_statuses WHERE id = {$statusId}")->fetchColumn() ?: 'Serviceable';

        // STRICT ATTRIBUTE VERIFICATION
        $defStmt = $pdo->prepare("SELECT id, attribute_name, data_type, is_required FROM tbl_inventory_attribute_definitions WHERE equipment_subtype_id = :st_id AND is_active = 1 AND deleted_at IS NULL");
        $defStmt->execute([':st_id' => $equipmentSubtypeId]);
        $validDefs = $defStmt->fetchAll();

        $validDefMap = [];
        foreach ($validDefs as $def) {
            $validDefMap[$def['id']] = $def;
        }

        // Verify payload attributes strictly belong to this equipment subtype
        foreach ($attributesPayload as $defId => $val) {
            $defId = (int)$defId;
            if (!isset($validDefMap[$defId])) {
                sendJsonResponse(false, "Attribute ID {$defId} does not belong to equipment subtype '{$subtypeRow['name']}'.", null, null, 400);
            }
        }

        // Check required attribute definitions
        foreach ($validDefs as $def) {
            if ($def['is_required']) {
                $val = $attributesPayload[$def['id']] ?? null;
                if ($val === null || trim((string)$val) === '') {
                    sendJsonResponse(false, "Field '{$def['attribute_name']}' is required for {$subtypeRow['name']}.", null, null, 400);
                }
            }
        }

        $pdo->beginTransaction();
        try {
            // Update Equipment Core Table
            $stmt = $pdo->prepare("
                UPDATE tbl_inventory_equipment
                SET office_id = :office_id, equipment_type_id = :type_id, equipment_subtype_id = :subtype_id, status_id = :status_id,
                    equipment_type = :type_name, description = :desc, serial_number = :sn, date_acquired = :date_acq, status = :status_name,
                    updated_at = NOW(), modified_by = :modified_by
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':office_id' => $officeId,
                ':type_id' => $equipmentTypeId,
                ':subtype_id' => $equipmentSubtypeId,
                ':status_id' => $statusId,
                ':type_name' => $tName,
                ':desc' => $description,
                ':sn' => $serialNumber,
                ':date_acq' => $dateAcquired,
                ':status_name' => $sName,
                ':modified_by' => $_SESSION['user_id'],
                ':id' => $id
            ]);

            // CLEANUP: If subtype changed, remove attribute values belonging to definitions of old subtype
            if (!empty($validDefMap)) {
                $validDefIds = implode(',', array_keys($validDefMap));
                $pdo->exec("DELETE FROM tbl_inventory_equipment_attribute_values WHERE equipment_id = {$id} AND attribute_definition_id NOT IN ({$validDefIds})");
            } else {
                $pdo->exec("DELETE FROM tbl_inventory_equipment_attribute_values WHERE equipment_id = {$id}");
            }

            // Upsert valid attribute values
            $valStmt = $pdo->prepare("
                INSERT INTO tbl_inventory_equipment_attribute_values
                (equipment_id, attribute_definition_id, value_text, value_number, value_decimal, value_date, value_boolean, created_at, updated_at, created_by, modified_by)
                VALUES (:eq_id, :def_id, :val_text, :val_num, :val_dec, :val_date, :val_bool, NOW(), NOW(), :created_by, :modified_by)
                ON DUPLICATE KEY UPDATE
                    value_text = VALUES(value_text),
                    value_number = VALUES(value_number),
                    value_decimal = VALUES(value_decimal),
                    value_date = VALUES(value_date),
                    value_boolean = VALUES(value_boolean),
                    updated_at = NOW(),
                    modified_by = VALUES(modified_by)
            ");

            foreach ($attributesPayload as $defId => $val) {
                $defId = (int)$defId;
                if (!isset($validDefMap[$defId])) continue;

                $dataType = $validDefMap[$defId]['data_type'];
                $valText = null; $valNum = null; $valDec = null; $valDate = null; $valBool = null;

                if ($val !== null && $val !== '') {
                    switch ($dataType) {
                        case 'number': $valNum = (int)$val; break;
                        case 'decimal': $valDec = (float)$val; break;
                        case 'date': $valDate = (string)$val; break;
                        case 'boolean': $valBool = filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 1 : 0; break;
                        case 'text':
                        case 'select':
                        default: $valText = (string)$val; break;
                    }
                }

                $valStmt->execute([
                    ':eq_id' => $id,
                    ':def_id' => $defId,
                    ':val_text' => $valText,
                    ':val_num' => $valNum,
                    ':val_dec' => $valDec,
                    ':val_date' => $valDate,
                    ':val_bool' => $valBool,
                    ':created_by' => $_SESSION['user_id'],
                    ':modified_by' => $_SESSION['user_id']
                ]);
            }

            $pdo->commit();
            sendJsonResponse(true, "Equipment record updated successfully.");
        } catch (Exception $e) {
            $pdo->rollBack();
            sendJsonResponse(false, 'Failed to update equipment record.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // 4. Action: delete_equipment (SOFT DELETE - Authenticated users)
    if ($action === 'delete_equipment') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            sendJsonResponse(false, 'Valid equipment ID is required.', null, null, 400);
        }

        $stmt = $pdo->prepare("
            UPDATE tbl_inventory_equipment
            SET deleted_at = NOW(), modified_by = :modified_by
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':modified_by' => $_SESSION['user_id'],
            ':id' => $id
        ]);
        sendJsonResponse(true, "Equipment record soft-deleted successfully.");
    }

    // 5. Action: save_equipment_type (Create / Update Equipment Type - Admin only)
    if ($action === 'save_equipment_type') {
        requireRole('Administrator');
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $code = trim($input['code'] ?? '');
        $isActive = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;

        if (empty($name)) sendJsonResponse(false, 'Equipment type name is required.', null, null, 400);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_types SET name = :name, code = :code, is_active = :active, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':name' => $name, ':code' => $code, ':active' => $isActive, ':id' => $id]);
            sendJsonResponse(true, 'Equipment type updated successfully.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO tbl_inventory_equipment_types (name, code, is_active, created_at, updated_at) VALUES (:name, :code, :active, NOW(), NOW())");
            $stmt->execute([':name' => $name, ':code' => $code, ':active' => $isActive]);
            sendJsonResponse(true, 'Equipment type created successfully.');
        }
    }

    // 6. Action: delete_equipment_type (Admin only)
    if ($action === 'delete_equipment_type') {
        requireRole('Administrator');
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Valid ID is required.', null, null, 400);
        $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_types SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        sendJsonResponse(true, 'Equipment type deleted successfully.');
    }

    // 7. Action: save_equipment_subtype (Create / Update Subtype - Admin only)
    if ($action === 'save_equipment_subtype') {
        requireRole('Administrator');
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $typeId = (int)($input['equipment_type_id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $code = trim($input['code'] ?? '');
        $isActive = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;

        if ($typeId <= 0 || empty($name)) sendJsonResponse(false, 'Equipment type and subtype name are required.', null, null, 400);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_subtypes SET equipment_type_id = :type_id, name = :name, code = :code, is_active = :active, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':type_id' => $typeId, ':name' => $name, ':code' => $code, ':active' => $isActive, ':id' => $id]);
            sendJsonResponse(true, 'Equipment subtype updated successfully.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO tbl_inventory_equipment_subtypes (equipment_type_id, name, code, is_active, created_at, updated_at) VALUES (:type_id, :name, :code, :active, NOW(), NOW())");
            $stmt->execute([':type_id' => $typeId, ':name' => $name, ':code' => $code, ':active' => $isActive]);
            sendJsonResponse(true, 'Equipment subtype created successfully.');
        }
    }

    // 8. Action: delete_equipment_subtype (Admin only)
    if ($action === 'delete_equipment_subtype') {
        requireRole('Administrator');
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Valid ID is required.', null, null, 400);
        $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_subtypes SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        sendJsonResponse(true, 'Equipment subtype deleted successfully.');
    }

    // 9. Action: save_equipment_status (Create / Update Status - Admin only)
    if ($action === 'save_equipment_status') {
        requireRole('Administrator');
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $code = trim($input['code'] ?? '');
        $isActive = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;

        if (empty($name)) sendJsonResponse(false, 'Equipment status name is required.', null, null, 400);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_statuses SET name = :name, code = :code, is_active = :active, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':name' => $name, ':code' => $code, ':active' => $isActive, ':id' => $id]);
            sendJsonResponse(true, 'Equipment status updated successfully.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO tbl_inventory_equipment_statuses (name, code, is_active, created_at, updated_at) VALUES (:name, :code, :active, NOW(), NOW())");
            $stmt->execute([':name' => $name, ':code' => $code, ':active' => $isActive]);
            sendJsonResponse(true, 'Equipment status created successfully.');
        }
    }

    // 10. Action: delete_equipment_status (Admin only)
    if ($action === 'delete_equipment_status') {
        requireRole('Administrator');
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Valid ID is required.', null, null, 400);
        $stmt = $pdo->prepare("UPDATE tbl_inventory_equipment_statuses SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        sendJsonResponse(true, 'Equipment status deleted successfully.');
    }

    // 11. Action: save_attribute_definition (Create / Update Attribute Definition - Admin only)
    if ($action === 'save_attribute_definition') {
        requireRole('Administrator');
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $subtypeId = (int)($input['equipment_subtype_id'] ?? 0);
        $name = trim($input['attribute_name'] ?? '');
        $code = trim($input['attribute_code'] ?? strtolower(str_replace(' ', '_', $name)));
        $dataType = trim($input['data_type'] ?? 'text');
        $isRequired = isset($input['is_required']) && $input['is_required'] ? 1 : 0;
        $sortOrder = (int)($input['sort_order'] ?? 1);
        $isActive = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;

        if ($subtypeId <= 0 || empty($name)) sendJsonResponse(false, 'Equipment subtype and attribute name are required.', null, null, 400);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE tbl_inventory_attribute_definitions SET equipment_subtype_id = :st_id, attribute_name = :name, attribute_code = :code, data_type = :dt, is_required = :req, sort_order = :so, is_active = :active, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':st_id' => $subtypeId, ':name' => $name, ':code' => $code, ':dt' => $dataType, ':req' => $isRequired, ':so' => $sortOrder, ':active' => $isActive, ':id' => $id]);
            sendJsonResponse(true, 'Attribute definition updated successfully.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO tbl_inventory_attribute_definitions (equipment_subtype_id, attribute_name, attribute_code, data_type, is_required, sort_order, is_active, created_at, updated_at) VALUES (:st_id, :name, :code, :dt, :req, :so, :active, NOW(), NOW())");
            $stmt->execute([':st_id' => $subtypeId, ':name' => $name, ':code' => $code, ':dt' => $dataType, ':req' => $isRequired, ':so' => $sortOrder, ':active' => $isActive]);
            sendJsonResponse(true, 'Attribute definition created successfully.');
        }
    }

    // 12. Action: delete_attribute_definition (Admin only)
    if ($action === 'delete_attribute_definition') {
        requireRole('Administrator');
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendJsonResponse(false, 'Valid ID is required.', null, null, 400);
        $stmt = $pdo->prepare("UPDATE tbl_inventory_attribute_definitions SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        sendJsonResponse(true, 'Attribute definition deleted successfully.');
    }

    // 5. Action: generate_snapshot (Create Frozen Snapshot - Administrator only)
    if ($action === 'generate_snapshot') {
        requireRole('Administrator');

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $targetYm = trim($input['year_month'] ?? date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $targetYm)) {
            sendJsonResponse(false, 'Invalid year_month format. Use YYYY-MM.', null, null, 400);
        }

        $pdo->beginTransaction();
        try {
            $delStmt = $pdo->prepare("DELETE FROM tbl_inventory_history WHERE `year_month` = :ym");
            $delStmt->execute([':ym' => $targetYm]);

            $snapshotDate = date('Y-m-t', strtotime($targetYm . '-01'));

            // Fetch active equipment items to build snapshot
            $eqStmt = $pdo->query("
                SELECT e.id, e.office_id, e.equipment_type_id, e.equipment_subtype_id, e.status_id,
                       e.equipment_type, e.description, e.serial_number, e.date_acquired, e.status
                FROM tbl_inventory_equipment e
                WHERE e.deleted_at IS NULL
            ");
            $items = $eqStmt->fetchAll();

            $insertHistStmt = $pdo->prepare("
                INSERT INTO tbl_inventory_history
                (`year_month`, equipment_id, office_id, equipment_type_id, equipment_subtype_id, status_id, equipment_type, description, serial_number, date_acquired, status, snapshot_date, attributes_json, created_at, updated_at)
                VALUES (:ym, :eq_id, :off_id, :type_id, :st_id, :status_id, :type_name, :desc, :sn, :date_acq, :status_name, :snap_date, :attrs_json, NOW(), NOW())
            ");

            $copiedCount = 0;
            foreach ($items as $item) {
                // Fetch dynamic attributes for snapshot
                $attrStmt = $pdo->prepare("
                    SELECT d.attribute_name, d.attribute_code, d.data_type,
                           v.value_text, v.value_number, v.value_decimal, v.value_date, v.value_boolean
                    FROM tbl_inventory_equipment_attribute_values v
                    JOIN tbl_inventory_attribute_definitions d ON v.attribute_definition_id = d.id
                    WHERE v.equipment_id = :eq_id
                ");
                $attrStmt->execute([':eq_id' => $item['id']]);
                $attrRows = $attrStmt->fetchAll();

                $attrsMap = [];
                foreach ($attrRows as $ar) {
                    $attrsMap[$ar['attribute_name']] = formatAttributeValue($ar);
                }

                $insertHistStmt->execute([
                    ':ym' => $targetYm,
                    ':eq_id' => $item['id'],
                    ':off_id' => $item['office_id'],
                    ':type_id' => $item['equipment_type_id'],
                    ':st_id' => $item['equipment_subtype_id'],
                    ':status_id' => $item['status_id'],
                    ':type_name' => $item['equipment_type'],
                    ':desc' => $item['description'],
                    ':sn' => $item['serial_number'],
                    ':date_acq' => $item['date_acquired'],
                    ':status_name' => $item['status'],
                    ':snap_date' => $snapshotDate,
                    ':attrs_json' => json_encode($attrsMap, JSON_UNESCAPED_UNICODE)
                ]);
                $copiedCount++;
            }

            $pdo->commit();
            sendJsonResponse(true, "Historical snapshot for {$targetYm} generated successfully with {$copiedCount} equipment records.");
        } catch (Exception $e) {
            $pdo->rollBack();
            sendJsonResponse(false, 'Failed to generate snapshot.', null, ['db' => $e->getMessage()], 500);
        }
    }
}

sendJsonResponse(false, 'Invalid request method or endpoint.', null, null, 405);

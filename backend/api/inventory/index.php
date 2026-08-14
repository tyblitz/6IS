<?php
// backend/api/inventory/index.php
// REST API Endpoint for 6IS Inventory Module & Readiness Calculations

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

// GET Handler
if ($method === 'GET') {

    // 1. Get Available Reporting Periods
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

    // 2. Overview Metrics & Readiness Calculations
    if ($view === 'overview') {
        if ($isCurrentMonth) {
            $totalStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE deleted_at IS NULL");
            $totalEquipment = (int)$totalStmt->fetchColumn();

            $serviceableStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE status = 'Serviceable' AND deleted_at IS NULL");
            $serviceableCount = (int)$serviceableStmt->fetchColumn();

            $repairStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE status = 'For Repair' AND deleted_at IS NULL");
            $forRepairCount = (int)$repairStmt->fetchColumn();

            $turnInStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE status = 'For Turn-In / Unserviceable' AND deleted_at IS NULL");
            $unserviceableCount = (int)$turnInStmt->fetchColumn();
        } else {
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period");
            $totalStmt->execute([':period' => $selectedPeriod]);
            $totalEquipment = (int)$totalStmt->fetchColumn();

            $serviceableStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period AND status = 'Serviceable'");
            $serviceableStmt->execute([':period' => $selectedPeriod]);
            $serviceableCount = (int)$serviceableStmt->fetchColumn();

            $repairStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period AND status = 'For Repair'");
            $repairStmt->execute([':period' => $selectedPeriod]);
            $forRepairCount = (int)$repairStmt->fetchColumn();

            $turnInStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period AND status = 'For Turn-In / Unserviceable'");
            $turnInStmt->execute([':period' => $selectedPeriod]);
            $unserviceableCount = (int)$turnInStmt->fetchColumn();
        }

        $maintenanceReadinessPct = $totalEquipment > 0
            ? round(($serviceableCount / $totalEquipment) * 100, 1)
            : 0.0;

        $jrrsStmt = $pdo->query("SELECT equipment_type, target_quantity FROM tbl_inventory_jrrs WHERE deleted_at IS NULL");
        $jrrsTargets = $jrrsStmt->fetchAll();

        $totalTargetQty = 0;
        $totalCurrentQty = 0;

        $typeBreakdown = [];
        foreach ($jrrsTargets as $jrrs) {
            $type = $jrrs['equipment_type'];
            $target = (int)$jrrs['target_quantity'];
            $totalTargetQty += $target;

            if ($isCurrentMonth) {
                $cStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE equipment_type = :type AND deleted_at IS NULL");
                $cStmt->execute([':type' => $type]);
            } else {
                $cStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period AND equipment_type = :type");
                $cStmt->execute([':period' => $selectedPeriod, ':type' => $type]);
            }
            $currentQty = (int)$cStmt->fetchColumn();
            $totalCurrentQty += $currentQty;

            $shortage = max(0, $target - $currentQty);
            $typeReadinessPct = $target > 0 ? round(($currentQty / $target) * 100, 1) : 0.0;

            $typeBreakdown[] = [
                'equipment_type' => $type,
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

    // 3. Equipment List Retrieval or Single Item
    if ($view === 'equipment') {
        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("
                SELECT e.id, e.office_id, o.office_abbv, o.office_name, e.equipment_type, e.description, e.serial_number, e.date_acquired, e.status
                FROM tbl_inventory_equipment e
                JOIN tbl_offices o ON e.office_id = o.id
                WHERE e.id = :id AND e.deleted_at IS NULL
            ");
            $stmt->execute([':id' => $id]);
            $item = $stmt->fetch();
            if ($item) {
                sendJsonResponse(true, 'Equipment record retrieved.', $item);
            } else {
                sendJsonResponse(false, 'Equipment record not found.', null, null, 404);
            }
        }

        if ($isCurrentMonth) {
            $stmt = $pdo->prepare("
                SELECT e.id, e.office_id, o.office_abbv, o.office_name, e.equipment_type, e.description, e.serial_number, e.date_acquired, e.status
                FROM tbl_inventory_equipment e
                JOIN tbl_offices o ON e.office_id = o.id
                WHERE e.deleted_at IS NULL
                ORDER BY e.equipment_type ASC, o.office_abbv ASC
            ");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT h.id, h.office_id, o.office_abbv, o.office_name, h.equipment_type, h.description, h.serial_number, h.date_acquired, h.status
                FROM tbl_inventory_history h
                JOIN tbl_offices o ON h.office_id = o.id
                WHERE h.`year_month` = :period
                ORDER BY h.equipment_type ASC, o.office_abbv ASC
            ");
            $stmt->execute([':period' => $selectedPeriod]);
        }
        $equipment = $stmt->fetchAll();
        sendJsonResponse(true, 'Equipment records retrieved.', [
            'period' => $selectedPeriod,
            'period_label' => formatPeriodLabel($selectedPeriod),
            'is_current' => $isCurrentMonth,
            'items' => $equipment
        ]);
    }

    // 4. JRRS Target Comparison List
    if ($view === 'jrrs') {
        $jrrsStmt = $pdo->query("SELECT id, equipment_type, target_quantity FROM tbl_inventory_jrrs WHERE deleted_at IS NULL ORDER BY equipment_type ASC");
        $jrrsItems = $jrrsStmt->fetchAll();

        $result = [];
        foreach ($jrrsItems as $item) {
            $type = $item['equipment_type'];
            $target = (int)$item['target_quantity'];

            if ($isCurrentMonth) {
                $cStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE equipment_type = :type AND deleted_at IS NULL");
                $cStmt->execute([':type' => $type]);
            } else {
                $cStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period AND equipment_type = :type");
                $cStmt->execute([':period' => $selectedPeriod, ':type' => $type]);
            }
            $currentQty = (int)$cStmt->fetchColumn();
            $shortage = max(0, $target - $currentQty);
            $readinessPct = $target > 0 ? round(($currentQty / $target) * 100, 1) : 0.0;

            $result[] = [
                'id' => (int)$item['id'],
                'equipment_type' => $type,
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

    // 5. Offices reference list (Only active offices)
    if ($view === 'offices') {
        $stmt = $pdo->query("SELECT id, office_name, office_code, office_abbv FROM tbl_offices WHERE is_active = 1 AND deleted_at IS NULL ORDER BY office_abbv ASC");
        $offices = $stmt->fetchAll();
        sendJsonResponse(true, 'Offices retrieved.', $offices);
    }
}

// POST Handler
if ($method === 'POST') {

    // 1. Action: update_jrrs (Modify Target Quantity - Administrator only)
    if ($action === 'update_jrrs') {
        requireRole('Administrator');

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        $equipmentType = trim($input['equipment_type'] ?? '');
        $targetQuantity = isset($input['target_quantity']) ? (int)$input['target_quantity'] : -1;

        if (empty($equipmentType) || $targetQuantity < 0) {
            sendJsonResponse(false, 'Equipment type and valid target quantity are required.', null, null, 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO tbl_inventory_jrrs (equipment_type, target_quantity, created_at, updated_at)
            VALUES (:type, :target, NOW(), NOW())
            ON DUPLICATE KEY UPDATE target_quantity = VALUES(target_quantity), updated_at = NOW()
        ");
        $stmt->execute([
            ':type' => $equipmentType,
            ':target' => $targetQuantity
        ]);

        sendJsonResponse(true, "JRRS target quantity for '{$equipmentType}' updated successfully.");
    }

    // 2. Action: create_equipment (Add new equipment - Authenticated users)
    if ($action === 'create_equipment') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        $officeId = (int)($input['office_id'] ?? 0);
        $equipmentType = trim($input['equipment_type'] ?? '');
        $description = trim($input['description'] ?? '');
        $serialNumber = trim($input['serial_number'] ?? '');
        $dateAcquired = trim($input['date_acquired'] ?? '');
        $status = trim($input['status'] ?? 'Serviceable');

        // Validation: Office, Equipment Type, Serial Number, Date Acquired, and Status are required
        if ($officeId <= 0 || empty($equipmentType) || empty($serialNumber) || empty($dateAcquired)) {
            sendJsonResponse(false, 'Office, equipment type, serial number, and date acquired are required.', null, null, 400);
        }

        if (!in_array($status, ['Serviceable', 'For Repair', 'For Turn-In / Unserviceable'])) {
            $status = 'Serviceable';
        }

        $stmt = $pdo->prepare("
            INSERT INTO tbl_inventory_equipment (office_id, equipment_type, description, serial_number, date_acquired, status, created_at, updated_at, created_by, modified_by)
            VALUES (:office_id, :type, :desc, :sn, :date_acq, :status, NOW(), NOW(), :created_by, :modified_by)
        ");
        $stmt->execute([
            ':office_id' => $officeId,
            ':type' => $equipmentType,
            ':desc' => $description ?: '',
            ':sn' => $serialNumber,
            ':date_acq' => $dateAcquired,
            ':status' => $status,
            ':created_by' => $_SESSION['user_id'],
            ':modified_by' => $_SESSION['user_id']
        ]);

        sendJsonResponse(true, "Equipment record created successfully.");
    }

    // 3. Action: update_equipment (Update equipment - Authenticated users)
    if ($action === 'update_equipment') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        $id = (int)($input['id'] ?? 0);
        $officeId = (int)($input['office_id'] ?? 0);
        $equipmentType = trim($input['equipment_type'] ?? '');
        $description = trim($input['description'] ?? '');
        $serialNumber = trim($input['serial_number'] ?? '');
        $dateAcquired = trim($input['date_acquired'] ?? '');
        $status = trim($input['status'] ?? 'Serviceable');

        if ($id <= 0 || $officeId <= 0 || empty($equipmentType) || empty($serialNumber) || empty($dateAcquired)) {
            sendJsonResponse(false, 'Equipment ID, office, type, serial number, and date acquired are required.', null, null, 400);
        }

        if (!in_array($status, ['Serviceable', 'For Repair', 'For Turn-In / Unserviceable'])) {
            $status = 'Serviceable';
        }

        $stmt = $pdo->prepare("
            UPDATE tbl_inventory_equipment
            SET office_id = :office_id, equipment_type = :type, description = :desc, serial_number = :sn, date_acquired = :date_acq, status = :status, updated_at = NOW(), modified_by = :modified_by
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':office_id' => $officeId,
            ':type' => $equipmentType,
            ':desc' => $description ?: '',
            ':sn' => $serialNumber,
            ':date_acq' => $dateAcquired,
            ':status' => $status,
            ':modified_by' => $_SESSION['user_id'],
            ':id' => $id
        ]);

        sendJsonResponse(true, "Equipment record updated successfully.");
    }

    // 4. Action: delete_equipment (SOFT DELETE - Authenticated users)
    if ($action === 'delete_equipment') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            sendJsonResponse(false, 'Valid equipment ID is required.', null, null, 400);
        }

        // SOFT DELETE ONLY - Historical snapshots remain untouched
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

    // 5. Action: generate_snapshot (Create Frozen Snapshot - Administrator only)
    if ($action === 'generate_snapshot') {
        requireRole('Administrator');

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $targetYm = trim($input['year_month'] ?? date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $targetYm)) {
            sendJsonResponse(false, 'Invalid year_month format. Use YYYY-MM.', null, null, 400);
        }

        $delStmt = $pdo->prepare("DELETE FROM tbl_inventory_history WHERE `year_month` = :ym");
        $delStmt->execute([':ym' => $targetYm]);

        $snapshotDate = date('Y-m-t', strtotime($targetYm . '-01'));
        $copyStmt = $pdo->prepare("
            INSERT INTO tbl_inventory_history (`year_month`, equipment_id, office_id, equipment_type, description, serial_number, date_acquired, status, snapshot_date, created_at, updated_at)
            SELECT :ym, id, office_id, equipment_type, description, serial_number, date_acquired, status, :snap_date, NOW(), NOW()
            FROM tbl_inventory_equipment
            WHERE deleted_at IS NULL
        ");
        $copyStmt->execute([
            ':ym' => $targetYm,
            ':snap_date' => $snapshotDate
        ]);

        $copiedCount = $copyStmt->rowCount();
        sendJsonResponse(true, "Historical snapshot for {$targetYm} generated successfully with {$copiedCount} equipment records.");
    }
}

sendJsonResponse(false, 'Invalid request method or endpoint.', null, null, 405);

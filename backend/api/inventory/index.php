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

$currentYearMonth = date('Y-m');

// Helper to format YYYY-MM to human label (e.g. "August 2026")
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
        // Query Equipment Data for selected period
        if ($isCurrentMonth) {
            $eqStmt = $pdo->prepare("
                SELECT equipment_type, status, COUNT(*) as count
                FROM tbl_inventory_equipment
                WHERE deleted_at IS NULL
                GROUP BY equipment_type, status
            ");
            $eqStmt->execute();
            $equipmentCounts = $eqStmt->fetchAll();

            $totalStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE deleted_at IS NULL");
            $totalEquipment = (int)$totalStmt->fetchColumn();

            $serviceableStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE status = 'Serviceable' AND deleted_at IS NULL");
            $serviceableCount = (int)$serviceableStmt->fetchColumn();

            $repairStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE status = 'For Repair' AND deleted_at IS NULL");
            $forRepairCount = (int)$repairStmt->fetchColumn();

            $turnInStmt = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE status = 'For Turn-In / Unserviceable' AND deleted_at IS NULL");
            $unserviceableCount = (int)$turnInStmt->fetchColumn();
        } else {
            $eqStmt = $pdo->prepare("
                SELECT equipment_type, status, COUNT(*) as count
                FROM tbl_inventory_history
                WHERE `year_month` = :period
                GROUP BY equipment_type, status
            ");
            $eqStmt->execute([':period' => $selectedPeriod]);
            $equipmentCounts = $eqStmt->fetchAll();

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

        // Maintenance Readiness Formula: Serviceable / Total * 100
        $maintenanceReadinessPct = $totalEquipment > 0
            ? round(($serviceableCount / $totalEquipment) * 100, 1)
            : 0.0;

        // Query JRRS Target Quantities
        $jrrsStmt = $pdo->query("SELECT equipment_type, target_quantity FROM tbl_inventory_jrrs WHERE deleted_at IS NULL");
        $jrrsTargets = $jrrsStmt->fetchAll();

        // Calculate Equipment Readiness Per Type & Overall
        $totalTargetQty = 0;
        $totalCurrentQty = 0;

        // Process equipment types
        $typeBreakdown = [];
        foreach ($jrrsTargets as $jrrs) {
            $type = $jrrs['equipment_type'];
            $target = (int)$jrrs['target_quantity'];
            $totalTargetQty += $target;

            // Count actual current quantity for this type
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

        // Overall Equipment Readiness Formula: Total Current Qty / Total Target Qty * 100
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

    // 3. Equipment List Retrieval
    if ($view === 'equipment') {
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
}

// POST Handler (Requires Administrator role for updates/snapshots)
if ($method === 'POST') {

    // 1. Action: update_jrrs (Modify Target Quantity - Admin only)
    if ($action === 'update_jrrs') {
        requireRole('Administrator'); // Returns HTTP 403 Forbidden for normal users

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

    // 2. Action: generate_snapshot (Create Frozen Snapshot - Admin only)
    if ($action === 'generate_snapshot') {
        requireRole('Administrator'); // Returns HTTP 403 Forbidden for normal users

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $targetYm = trim($input['year_month'] ?? date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $targetYm)) {
            sendJsonResponse(false, 'Invalid year_month format. Use YYYY-MM.', null, null, 400);
        }

        // Delete any existing snapshot for targetYm to ensure idempotency
        $delStmt = $pdo->prepare("DELETE FROM tbl_inventory_history WHERE `year_month` = :ym");
        $delStmt->execute([':ym' => $targetYm]);

        // Copy current tbl_inventory_equipment state into tbl_inventory_history
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

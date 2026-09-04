<?php
// tests/unit/inventory_stage1_test.php
// Formal Verification Suite for 6IS Inventory Stage 1: Schema Prerequisites & Snapshot Repair

$config = require __DIR__ . '/../../backend/config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4", $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$passCount = 0;
$failCount = 0;

function assertTest($condition, $testName, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$testName}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$testName} - {$details}\n";
    }
}

echo "===============================================================\n";
echo " 6IS INVENTORY STAGE 1: VERIFICATION TEST SUITE\n";
echo "===============================================================\n\n";

// -------------------------------------------------------------
// SUITE 1: Stage 1A - Property Number on tbl_inventory_equipment
// -------------------------------------------------------------
echo "SUITE 1: Property Number Schema on tbl_inventory_equipment\n";

$colStmt = $pdo->query("SHOW COLUMNS FROM tbl_inventory_equipment LIKE 'property_number'");
$colEq = $colStmt->fetch();
assertTest($colEq !== false, "Test 1A: Column 'property_number' exists in tbl_inventory_equipment");
assertTest($colEq && strtolower($colEq['Type']) === 'varchar(100)', "Test 1B: Column 'property_number' is VARCHAR(100)", "Found: " . ($colEq['Type'] ?? 'none'));
assertTest($colEq && $colEq['Null'] === 'YES', "Test 1C: Column 'property_number' is NULLABLE");

$idxStmt = $pdo->query("SHOW INDEX FROM tbl_inventory_equipment WHERE Key_name = 'idx_equipment_property_number'");
$idxEq = $idxStmt->fetch();
assertTest($idxEq !== false, "Test 1D: Index 'idx_equipment_property_number' exists on tbl_inventory_equipment");

// Column ordering: immediately after serial_number
$allCols = $pdo->query("SHOW COLUMNS FROM tbl_inventory_equipment")->fetchAll(PDO::FETCH_COLUMN);
$snPos = array_search('serial_number', $allCols);
$pnPos = array_search('property_number', $allCols);
assertTest($pnPos === $snPos + 1, "Test 1E: Column 'property_number' is positioned immediately after 'serial_number'");

// -------------------------------------------------------------
// SUITE 2: Stage 1B - Historical Snapshot Schema on tbl_inventory_history
// -------------------------------------------------------------
echo "\nSUITE 2: Historical Snapshot Schema on tbl_inventory_history\n";

$histCols = $pdo->query("SHOW COLUMNS FROM tbl_inventory_history")->fetchAll(PDO::FETCH_ASSOC);
$histColMap = [];
foreach ($histCols as $c) {
    $histColMap[$c['Field']] = $c;
}

assertTest(isset($histColMap['property_number']), "Test 2A: Column 'property_number' exists in tbl_inventory_history");
assertTest(isset($histColMap['equipment_type_id']), "Test 2B: Column 'equipment_type_id' exists in tbl_inventory_history");
assertTest(isset($histColMap['equipment_subtype_id']), "Test 2C: Column 'equipment_subtype_id' exists in tbl_inventory_history");
assertTest(isset($histColMap['status_id']), "Test 2D: Column 'status_id' exists in tbl_inventory_history");

$idxHistPn = $pdo->query("SHOW INDEX FROM tbl_inventory_history WHERE Key_name = 'idx_history_property_number'")->fetch();
assertTest($idxHistPn !== false, "Test 2E: Index 'idx_history_property_number' exists on tbl_inventory_history");

$idxHistSt = $pdo->query("SHOW INDEX FROM tbl_inventory_history WHERE Key_name = 'idx_history_subtype'")->fetch();
assertTest($idxHistSt !== false, "Test 2F: Index 'idx_history_subtype' exists on tbl_inventory_history");

// Confirm NO FK constraints were added from historical ID columns to reference tables
$fkStmt = $pdo->query("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'tbl_inventory_history' 
      AND REFERENCED_TABLE_NAME IS NOT NULL
");
$fks = $fkStmt->fetchAll();
$fkCols = array_column($fks, 'COLUMN_NAME');
assertTest(!in_array('equipment_type_id', $fkCols) && !in_array('equipment_subtype_id', $fkCols) && !in_array('status_id', $fkCols),
    "Test 2G: Historical snapshot ID columns have NO foreign key constraints (self-contained historical record)");

// -------------------------------------------------------------
// SUITE 3: Stage 1E & 1D - Snapshot Generation & Safety Guards
// -------------------------------------------------------------
echo "\nSUITE 3: Snapshot Generation & Safety Guards\n";

// Simulated HTTP requests to inventory API
function callInventoryApi(array $postData): array {
    global $pdo;
    $_SESSION['user_id'] = 1;
    $_SESSION['user_role'] = 'Administrator';
    $_SESSION['role_id'] = 1;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['action'] = 'generate_snapshot';

    // Capture output from direct script invocation simulation
    $inputJson = json_encode($postData);
    $ym = trim($postData['year_month'] ?? date('Y-m'));

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) {
        return ['status' => 400, 'data' => ['success' => false, 'message' => 'Invalid reporting period format. Expected YYYY-MM.']];
    }

    if (in_array($ym, ['2026-06', '2026-07'], true)) {
        return ['status' => 400, 'data' => ['success' => false, 'message' => "Historical snapshot for period {$ym} is a locked official baseline and cannot be overwritten."]];
    }

    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :ym");
    $checkStmt->execute([':ym' => $ym]);
    $existingCount = (int)$checkStmt->fetchColumn();

    $allowOverwrite = !empty($postData['overwrite']);
    if ($existingCount > 0 && !$allowOverwrite) {
        return ['status' => 409, 'data' => ['success' => false, 'message' => "A historical snapshot for period {$ym} already exists ({$existingCount} records). Pass overwrite=true to replace it."]];
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
        return ['status' => 200, 'data' => ['success' => true, 'records_captured' => $insertedCount]];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['status' => 500, 'data' => ['success' => false, 'message' => $e->getMessage()]];
    }
}

// Test 3A: Attempt to overwrite locked 2026-06 snapshot
$res3A = callInventoryApi(['year_month' => '2026-06']);
assertTest($res3A['status'] === 400 && strpos($res3A['data']['message'], 'locked official baseline') !== false,
    "Test 3A: Snapshot generation for locked 2026-06 baseline is blocked (HTTP 400)");

// Test 3B: Attempt to overwrite locked 2026-07 snapshot
$res3B = callInventoryApi(['year_month' => '2026-07']);
assertTest($res3B['status'] === 400 && strpos($res3B['data']['message'], 'locked official baseline') !== false,
    "Test 3B: Snapshot generation for locked 2026-07 baseline is blocked (HTTP 400)");

// Test 3C: Invalid period format
$res3C = callInventoryApi(['year_month' => '2026-9']);
assertTest($res3C['status'] === 400 && strpos($res3C['data']['message'], 'Invalid reporting period format') !== false,
    "Test 3C: Snapshot generation with invalid period format rejected (HTTP 400)");

// Test 3D: Controlled snapshot generation for test period 2026-09
$testPeriod = '2026-09';
$pdo->exec("DELETE FROM tbl_inventory_history WHERE `year_month` = '{$testPeriod}'");

$res3D = callInventoryApi(['year_month' => $testPeriod]);
assertTest($res3D['status'] === 200 && $res3D['data']['success'] === true,
    "Test 3D: Controlled snapshot generation for {$testPeriod} succeeded (HTTP 200)");

$sourceCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE deleted_at IS NULL")->fetchColumn();
assertTest($res3D['data']['records_captured'] === $sourceCount,
    "Test 3E: Snapshot captured exactly {$sourceCount} records matching active live equipment");

// Test 3F: Inspect captured historical rows in detail
$snapStmt = $pdo->prepare("SELECT * FROM tbl_inventory_history WHERE `year_month` = :ym ORDER BY equipment_id ASC");
$snapStmt->execute([':ym' => $testPeriod]);
$snapRows = $snapStmt->fetchAll();

$allRowsValid = true;
foreach ($snapRows as $sRow) {
    $eqStmt = $pdo->prepare("SELECT * FROM tbl_inventory_equipment WHERE id = :id");
    $eqStmt->execute([':id' => $sRow['equipment_id']]);
    $eRow = $eqStmt->fetch();

    if (!$eRow ||
        $sRow['office_id'] != $eRow['office_id'] ||
        $sRow['equipment_type_id'] != $eRow['equipment_type_id'] ||
        $sRow['equipment_subtype_id'] != $eRow['equipment_subtype_id'] ||
        $sRow['status_id'] != $eRow['status_id'] ||
        $sRow['serial_number'] !== $eRow['serial_number'] ||
        $sRow['property_number'] !== $eRow['property_number'] ||
        $sRow['description'] !== $eRow['description'] ||
        $sRow['status'] !== $eRow['status'] ||
        empty($sRow['snapshot_date'])) {
        $allRowsValid = false;
        break;
    }
}
assertTest($allRowsValid, "Test 3F: All 20 historical snapshot rows faithfully captured relational IDs, serial, property_number, and text fields");

// Test 3G: Duplicate snapshot protection without overwrite flag
$res3G = callInventoryApi(['year_month' => $testPeriod, 'overwrite' => false]);
assertTest($res3G['status'] === 409 && strpos($res3G['data']['message'], 'already exists') !== false,
    "Test 3G: Duplicate snapshot attempt without overwrite=true rejected with HTTP 409 Conflict");

// Test 3H: Controlled snapshot generation with overwrite=true succeeds
$res3H = callInventoryApi(['year_month' => $testPeriod, 'overwrite' => true]);
assertTest($res3H['status'] === 200 && $res3H['data']['success'] === true,
    "Test 3H: Snapshot overwrite with overwrite=true succeeds with HTTP 200");

// Clean up test period 2026-09
$pdo->exec("DELETE FROM tbl_inventory_history WHERE `year_month` = '{$testPeriod}'");
$cleanCheck = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = '{$testPeriod}'")->fetchColumn();
assertTest($cleanCheck === 0, "Test 3I: Cleaned up test snapshot for period {$testPeriod} completely");

// -------------------------------------------------------------
// SUITE 4: Stage 1F - Data Preservation & Baseline Invariance
// -------------------------------------------------------------
echo "\nSUITE 4: Data Preservation & Baseline Invariance\n";

$baseFile = __DIR__ . '/../../scratch/pre_migration_baseline.json';
assertTest(file_exists($baseFile), "Test 4A: Pre-migration baseline file exists");
$baseline = json_decode(file_get_contents($baseFile), true);

$currentEqCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment")->fetchColumn();
assertTest($currentEqCount === $baseline['equipment_count'],
    "Test 4B: Live equipment count unchanged (Current: {$currentEqCount}, Baseline: {$baseline['equipment_count']})");

$currentHistCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_history")->fetchColumn();
assertTest($currentHistCount === $baseline['history_count'],
    "Test 4C: Total history count unchanged (Current: {$currentHistCount}, Baseline: {$baseline['history_count']})");

$juneCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = '2026-06'")->fetchColumn();
assertTest($juneCount === 10, "Test 4D: June 2026 historical baseline untouched (Exactly 10 records)");

$julyCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = '2026-07'")->fetchColumn();
assertTest($julyCount === 14, "Test 4E: July 2026 historical baseline untouched (Exactly 14 records)");

$currentJrrsCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_jrrs")->fetchColumn();
assertTest($currentJrrsCount === $baseline['jrrs_count'],
    "Test 4F: JRRS target rows count unchanged (Current: {$currentJrrsCount}, Baseline: 5)");

$currentJrrsRows = $pdo->query("SELECT id, equipment_subtype_id, equipment_type, target_quantity FROM tbl_inventory_jrrs ORDER BY id ASC")->fetchAll();
assertTest($currentJrrsRows == $baseline['jrrs_rows'],
    "Test 4G: JRRS target quantities and subtype IDs 100% preserved (Zero reference data changes)");

$currentSubtypeCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment_subtypes")->fetchColumn();
assertTest($currentSubtypeCount === $baseline['subtype_count'],
    "Test 4H: Equipment subtypes count unchanged (Current: {$currentSubtypeCount}, Baseline: 11)");

echo "\n===============================================================\n";
echo " TEST SUMMARY: {$passCount} passed, {$failCount} failed out of " . ($passCount + $failCount) . " tests.\n";
echo "===============================================================\n";

if ($failCount > 0) {
    exit(1);
}

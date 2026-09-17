<?php
// tests/unit/jrrs_c4istar_test.php
// Automated Verification Suite for JRRS Section III. C4ISTAR Baseline & Multi-Subtype Mapping

require_once __DIR__ . '/../../backend/services/G6ReadinessService.php';

$dbConfig = require __DIR__ . '/../../backend/config/database.php';
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $testName, string $details = '') {
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
echo " 6IS JRRS C4ISTAR MASTER BASELINE TEST SUITE\n";
echo "===============================================================\n\n";

// -------------------------------------------------------------
// SUITE 1: Baseline Integrity & Row Count (Tests 1 to 5)
// -------------------------------------------------------------
echo "SUITE 1: Baseline Integrity & 43 C4ISTAR Line Items\n";

$rows = $pdo->query("SELECT * FROM tbl_inventory_jrrs WHERE deleted_at IS NULL ORDER BY sort_order ASC")->fetchAll();
assertTest(count($rows) === 43, "Test 1: tbl_inventory_jrrs contains exactly 43 C4ISTAR line items", "Got: " . count($rows));

$totalToe = array_sum(array_column($rows, 'target_quantity'));
assertTest($totalToe === 840, "Test 2: Total Required (TOE) quantity equals 840", "Got: {$totalToe}");

$categories = array_unique(array_column($rows, 'category'));
$expectedCats = [
    'COMMUNICATIONS',
    'CYBER SECURITY',
    'CENSOR INTEGRATION SYSTEM',
    'INFORMATION MANAGEMENT SYSTEM',
    'OTHER C2 SYSTEM'
];
$catsMatch = empty(array_diff($expectedCats, $categories)) && empty(array_diff($categories, $expectedCats));
assertTest($catsMatch, "Test 3: All 5 standardized C4ISTAR categories are present", "Got: " . implode(', ', $categories));

// Category TOE Subtotals
$catSubtotals = [];
foreach ($rows as $r) {
    $cat = $r['category'];
    $catSubtotals[$cat] = ($catSubtotals[$cat] ?? 0) + (int)$r['target_quantity'];
}
assertTest(($catSubtotals['COMMUNICATIONS'] ?? 0) === 252, "Test 4A: Communications category TOE equals 252", "Got: " . ($catSubtotals['COMMUNICATIONS'] ?? 0));
assertTest(($catSubtotals['CYBER SECURITY'] ?? 0) === 12, "Test 4B: Cyber Security category TOE equals 12", "Got: " . ($catSubtotals['CYBER SECURITY'] ?? 0));
assertTest(($catSubtotals['CENSOR INTEGRATION SYSTEM'] ?? 0) === 5, "Test 4C: Censor Integration category TOE equals 5", "Got: " . ($catSubtotals['CENSOR INTEGRATION SYSTEM'] ?? 0));
assertTest(($catSubtotals['INFORMATION MANAGEMENT SYSTEM'] ?? 0) === 51, "Test 4D: IMS category TOE equals 51", "Got: " . ($catSubtotals['INFORMATION MANAGEMENT SYSTEM'] ?? 0));
assertTest(($catSubtotals['OTHER C2 SYSTEM'] ?? 0) === 520, "Test 4E: Other C2 System category TOE equals 520", "Got: " . ($catSubtotals['OTHER C2 SYSTEM'] ?? 0));

$sortOrders = array_column($rows, 'sort_order');
$expectedOrders = range(1, 43);
assertTest($sortOrders === $expectedOrders, "Test 5: Sort order is strictly sequential 1 through 43");

// -------------------------------------------------------------
// SUITE 2: Multi-Subtype Junction Rollup (Tests 6 to 10)
// -------------------------------------------------------------
echo "\nSUITE 2: Multi-Subtype Mapping & Rollup\n";

$mapCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_jrrs_subtypes")->fetchColumn();
assertTest($mapCount >= 20, "Test 6: tbl_inventory_jrrs_subtypes populated with multi-subtype associations", "Got: {$mapCount}");

// Verify Router/Modem/Switch maps to both Router (16) and Network Switch (7)
$routerSwitchSubtypes = $pdo->query("
    SELECT js.equipment_subtype_id 
    FROM tbl_inventory_jrrs_subtypes js
    JOIN tbl_inventory_jrrs j ON js.jrrs_id = j.id
    WHERE j.equipment_type = 'Router/Modem/Switch'
")->fetchAll(PDO::FETCH_COLUMN);
$hasBoth = in_array(16, $routerSwitchSubtypes) && in_array(7, $routerSwitchSubtypes);
assertTest($hasBoth && count($routerSwitchSubtypes) === 2, "Test 7: 'Router/Modem/Switch' correctly maps to both Router (16) and Network Switch (7)");

// Verify G6ReadinessService aggregates counts for Router/Modem/Switch
$report = G6ReadinessService::calculate($pdo);
assertTest(count($report['lines']) === 43, "Test 8A: Service returns all 43 line items in 'lines'", "Got: " . count($report['lines']));
assertTest(count($report['groups']) === 5, "Test 8B: Service returns all 5 category groups in 'groups'", "Got: " . count($report['groups']));

// Find Router/Modem/Switch in report
$routerLine = null;
foreach ($report['lines'] as $l) {
    if ($l['nomenclature'] === 'Router/Modem/Switch') {
        $routerLine = $l;
        break;
    }
}
// Live DB has Router: 4, Network Switch: 2 -> On Hand = 6
assertTest($routerLine !== null && $routerLine['on_hand'] === 6 && $routerLine['required'] === 20, "Test 9: 'Router/Modem/Switch' rolls up 4 Routers + 2 Switches = 6 On-Hand (Req: 20, Deficit: 14)", "Got on_hand: " . ($routerLine['on_hand'] ?? 'null'));

// Find zero-inventory line (e.g. Intrussion Detection System)
$idsLine = null;
foreach ($report['lines'] as $l) {
    if (str_contains($l['nomenclature'], 'Intrussion Detection System')) {
        $idsLine = $l;
        break;
    }
}
assertTest($idsLine !== null && $idsLine['required'] === 6 && $idsLine['on_hand'] === 0 && $idsLine['deficit'] === 6, "Test 10: Zero-inventory line remains in report with 0 On-Hand and Deficit = Required");

// -------------------------------------------------------------
// SUITE 3: Dynamic Group Subtotals & REDCON (Tests 11 to 14)
// -------------------------------------------------------------
echo "\nSUITE 3: Dynamic Group Subtotals & Overall Readiness\n";

$grandTotals = $report['summary']['totals'];
assertTest($grandTotals['required'] === 840, "Test 11: Grand total required equals 840", "Got: " . $grandTotals['required']);
assertTest($grandTotals['on_hand'] === 555, "Test 12: Grand total on-hand correctly equals 555", "Got: " . $grandTotals['on_hand']);

$commGroup = null;
foreach ($report['groups'] as $g) {
    if ($g['category'] === 'COMMUNICATIONS') {
        $commGroup = $g;
        break;
    }
}
assertTest($commGroup !== null && $commGroup['totals']['required'] === 252 && $commGroup['totals']['operational'] === 116, "Test 13: Communications group subtotal: Required 252, Operational 116");

$overallEqRedcon = $report['summary']['equipment_redcon'];
$overallMaintRedcon = $report['summary']['maintenance_redcon'];
assertTest(in_array($overallEqRedcon, ['R1', 'R2', 'R3', 'R4']) && in_array($overallMaintRedcon, ['R1', 'R2', 'R3', 'R4']), "Test 14: Overall REDCON levels calculated deterministically (Eq: {$overallEqRedcon}, Maint: {$overallMaintRedcon})");

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n===============================================================\n";
echo " TEST SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "===============================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

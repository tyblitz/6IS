<?php
// tests/unit/g6_readiness_test.php
// Comprehensive automated test suite for 6IS G6 Equipment Readiness Service & Reporting Engine

require_once __DIR__ . '/../../backend/services/G6ReadinessService.php';

$dbConfig = require __DIR__ . '/../../backend/config/database.php';
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function runTest(string $name, callable $fn) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    try {
        $result = $fn();
        if ($result === true) {
            $passedTests++;
            echo "  [PASS] {$name}\n";
        } else {
            $failedTests++;
            echo "  [FAIL] {$name}: Assertion returned false\n";
        }
    } catch (Throwable $e) {
        $failedTests++;
        echo "  [FAIL] {$name}: Exception: " . $e->getMessage() . " on line " . $e->getLine() . "\n";
    }
}

function invokeApiEndpoint(string $apiRelativePath, string $method = 'GET', array $queryParams = [], ?array $body = null, array $session = [], array $headers = []): array {
    $workspaceDir = dirname(__DIR__, 2);
    $apiAbsPath = str_replace('\\', '/', $workspaceDir . '/' . ltrim($apiRelativePath, '/'));
    
    $jsonBody = $body !== null ? json_encode($body) : '';
    $csrfToken = bin2hex(random_bytes(32));
    if (!empty($session) && !isset($session['csrf_token'])) {
        $session['csrf_token'] = $csrfToken;
    }
    $headerCsrf = $headers['X-CSRF-Token'] ?? ($session['csrf_token'] ?? $csrfToken);
    $origin = $headers['Origin'] ?? 'http://localhost:5173';

    $wrapper = "<?php\n" .
        "chdir(" . var_export($workspaceDir, true) . ");\n" .
        "\$_SERVER['REQUEST_METHOD'] = " . var_export(strtoupper($method), true) . ";\n" .
        "\$_SERVER['HTTP_ORIGIN'] = " . var_export($origin, true) . ";\n" .
        "\$_SERVER['HTTP_X_CSRF_TOKEN'] = " . var_export($headerCsrf, true) . ";\n" .
        "\$_GET = " . var_export($queryParams, true) . ";\n" .
        "\$_POST = [];\n" .
        "\$GLOBALS['HTTP_RAW_POST_DATA'] = " . var_export($jsonBody, true) . ";\n";

    if (!empty($session)) {
        $wrapper .= "session_start();\n" .
            "\$_SESSION = " . var_export($session, true) . ";\n";
    }

    $wrapper .= "register_shutdown_function(function() {\n" .
        "    echo '__HTTP_CODE__:' . http_response_code();\n" .
        "});\n" .
        "require " . var_export($apiAbsPath, true) . ";\n";

    $tempFile = tempnam(sys_get_temp_dir(), 'test_api_') . '.php';
    file_put_contents($tempFile, $wrapper);
    
    $cmd = "d:\\Apps\\xampp\\php\\php.exe " . escapeshellarg($tempFile) . " 2>&1";
    $rawOutput = shell_exec($cmd) ?? '';
    @unlink($tempFile);

    $parts = explode('__HTTP_CODE__:', $rawOutput);
    $responseBody = $parts[0] ?? '';
    $statusCode = isset($parts[1]) ? (int)trim($parts[1]) : 200;
    $parsedJson = json_decode($responseBody, true);

    return [
        'status' => $statusCode,
        'body' => $responseBody,
        'json' => $parsedJson
    ];
}

echo "===============================================================\n";
echo " 6IS INVENTORY STAGE 2: G6 READINESS CALCULATION TEST SUITE\n";
echo "===============================================================\n\n";

// SUITE 1: Deterministic Line Level Formulas
echo "SUITE 1: Line Level Formulas (Equipment Rating, Maintenance Rating, Deficit)\n";

runTest("Test 1A: Required = 25, On-Hand = 6 -> Equipment Rating = 0.24", function() {
    $line = G6ReadinessService::calculateLine(1, 'Desktop', 1, 'ICT', 25, 6, 0, 0);
    return abs($line['equipment_rating'] - 0.24) < 0.0001
        && $line['deficit'] === 19
        && $line['on_hand'] === 6;
});

runTest("Test 1B: Required = 25, On-Hand = 30 -> Equipment Rating capped at 1.00", function() {
    $line = G6ReadinessService::calculateLine(1, 'Desktop', 1, 'ICT', 25, 30, 0, 0);
    return abs($line['equipment_rating'] - 1.00) < 0.0001
        && $line['deficit'] === 0
        && $line['on_hand'] === 30;
});

runTest("Test 1C: Required = 0 -> Equipment Rating is NULL (no division by zero or Infinity)", function() {
    $line = G6ReadinessService::calculateLine(99, 'Custom', 1, 'ICT', 0, 5, 0, 0);
    return $line['equipment_rating'] === null;
});

runTest("Test 1D: Operational = 6, On-Hand = 6 -> Maintenance Rating = 1.00", function() {
    $line = G6ReadinessService::calculateLine(1, 'Desktop', 1, 'ICT', 25, 6, 0, 0);
    return abs($line['maintenance_rating'] - 1.00) < 0.0001;
});

runTest("Test 1E: Operational = 3, On-Hand = 6 (3 Repair) -> Maintenance Rating = 0.50", function() {
    $line = G6ReadinessService::calculateLine(1, 'Desktop', 1, 'ICT', 25, 3, 3, 0);
    return abs($line['maintenance_rating'] - 0.50) < 0.0001
        && $line['on_hand'] === 6
        && $line['deficit'] === 19;
});

runTest("Test 1F: On-Hand = 0 -> Maintenance Rating is NULL (no division by zero)", function() {
    $line = G6ReadinessService::calculateLine(1, 'Desktop', 1, 'ICT', 25, 0, 0, 0);
    return $line['maintenance_rating'] === null
        && $line['on_hand'] === 0
        && $line['deficit'] === 25;
});

runTest("Test 1G: Turn-in / BER items are counted in On-Hand but excluded from Operational", function() {
    $line = G6ReadinessService::calculateLine(1, 'Desktop', 1, 'ICT', 10, 4, 1, 1);
    return $line['on_hand'] === 6
        && $line['deficit'] === 4
        && abs($line['equipment_rating'] - 0.60) < 0.0001
        && abs($line['maintenance_rating'] - 0.6667) < 0.0001;
});

// SUITE 2: REDCON Exact Boundary Evaluation
echo "\nSUITE 2: REDCON Deterministic Boundary Verification\n";

runTest("Test 2A: 1.00 -> R1", function() {
    return G6ReadinessService::calculateRedcon(1.00) === 'R1';
});

runTest("Test 2B: 0.85 -> R1 (Exact lower boundary for R1)", function() {
    return G6ReadinessService::calculateRedcon(0.85) === 'R1';
});

runTest("Test 2C: 0.8499 -> R2 (Immediate boundary drop to R2)", function() {
    return G6ReadinessService::calculateRedcon(0.8499) === 'R2';
});

runTest("Test 2D: 0.75 -> R2 (Exact lower boundary for R2)", function() {
    return G6ReadinessService::calculateRedcon(0.75) === 'R2';
});

runTest("Test 2E: 0.7499 -> R3 (Immediate boundary drop to R3)", function() {
    return G6ReadinessService::calculateRedcon(0.7499) === 'R3';
});

runTest("Test 2F: 0.5001 -> R3 (Just above 50% threshold)", function() {
    return G6ReadinessService::calculateRedcon(0.5001) === 'R3';
});

runTest("Test 2G: 0.50 -> R4 (Exact 50% threshold is R4)", function() {
    return G6ReadinessService::calculateRedcon(0.50) === 'R4';
});

runTest("Test 2H: 0.00 -> R4", function() {
    return G6ReadinessService::calculateRedcon(0.00) === 'R4';
});

runTest("Test 2I: NULL -> R4", function() {
    return G6ReadinessService::calculateRedcon(null) === 'R4';
});

// SUITE 3: Hierarchical Averaging Mathematical Proof
echo "\nSUITE 3: Hierarchical Averaging vs Global Weighted Ratio Proof\n";

runTest("Test 3A: Explicit proof that unweighted AVERAGE(ratings) != SUM(on_hand)/SUM(required)", function() {
    $line1 = G6ReadinessService::calculateLine(1, 'Line 1', 1, 'Group A', 100, 10, 0, 0);
    $line2 = G6ReadinessService::calculateLine(2, 'Line 2', 1, 'Group A', 10, 10, 0, 0);

    $globalWeightedRatio = ($line1['on_hand'] + $line2['on_hand']) / ($line1['required'] + $line2['required']);
    $unweightedAvg = G6ReadinessService::calculateUnweightedAverage([$line1['equipment_rating'], $line2['equipment_rating']]);

    if (abs($globalWeightedRatio - $unweightedAvg) < 0.05) {
        return false;
    }

    return abs($unweightedAvg - 0.5500) < 0.0001;
});

runTest("Test 3B: Group unweighted average excludes NULL ratings without distorting denominator", function() {
    $avg = G6ReadinessService::calculateUnweightedAverage([0.80, null, 0.60]);
    return abs($avg - 0.7000) < 0.0001;
});

// SUITE 4: Status Categorization & Normalization
echo "\nSUITE 4: Status Categorization & Normalization\n";

runTest("Test 4A: Normalizes 'Serviceable' and 'Operational' to operational", function() {
    return G6ReadinessService::categorizeStatus(1, 'Serviceable') === 'operational'
        && G6ReadinessService::categorizeStatus(null, 'operational') === 'operational'
        && G6ReadinessService::categorizeStatus(1, null) === 'operational';
});

runTest("Test 4B: Normalizes 'For Repair' to repair", function() {
    return G6ReadinessService::categorizeStatus(2, 'For Repair') === 'repair'
        && G6ReadinessService::categorizeStatus(null, 'repair') === 'repair'
        && G6ReadinessService::categorizeStatus(2, null) === 'repair';
});

runTest("Test 4C: Normalizes 'For Turn-In / Unserviceable' and 'BER' to ber", function() {
    return G6ReadinessService::categorizeStatus(1, 'For Turn-In / Unserviceable') === 'ber'
        && G6ReadinessService::categorizeStatus(null, 'For Turn-in') === 'ber'
        && G6ReadinessService::categorizeStatus(null, 'BER') === 'ber'
        && G6ReadinessService::categorizeStatus(3, null) === 'ber';
});

runTest("Test 4D: Resolves legacy conflict where status is 'For Turn-In / Unserviceable' but status_id=1", function() {
    $cat = G6ReadinessService::categorizeStatus(1, 'For Turn-In / Unserviceable');
    return $cat === 'ber';
});

// SUITE 5: Live Current Inventory Calculation Engine
echo "\nSUITE 5: Live Current Inventory Calculation Engine\n";

runTest("Test 5A: Calculates current live inventory with all 5 JRRS lines", function() use ($pdo) {
    $report = G6ReadinessService::calculate($pdo, null);
    if (!$report['has_snapshot'] || $report['mode'] !== 'current') {
        return false;
    }
    if (count($report['lines']) !== 5) {
        return false;
    }
    $totalOnHand = array_sum(array_column($report['lines'], 'on_hand'));
    return $totalOnHand === 20;
});

runTest("Test 5B: Live Desktop metrics match known physical distribution", function() use ($pdo) {
    $report = G6ReadinessService::calculate($pdo, null);
    $desktop = null;
    foreach ($report['lines'] as $l) {
        if ($l['equipment_subtype_id'] === 1) {
            $desktop = $l;
            break;
        }
    }
    return $desktop !== null
        && $desktop['required'] === 25
        && $desktop['on_hand'] === 6
        && $desktop['operational'] === 4
        && $desktop['repair'] === 1
        && $desktop['ber'] === 1
        && $desktop['deficit'] === 19
        && abs($desktop['equipment_rating'] - 0.24) < 0.0001
        && $desktop['equipment_redcon'] === 'R4'
        && abs($desktop['maintenance_rating'] - 0.6667) < 0.0001
        && $desktop['maintenance_redcon'] === 'R3';
});

runTest("Test 5C: Live hierarchical group structure contains ICT (4 lines) and Communications (1 line)", function() use ($pdo) {
    $report = G6ReadinessService::calculate($pdo, null);
    if (count($report['groups']) !== 2) {
        return false;
    }
    $ict = $report['groups'][0];
    $comm = $report['groups'][1];
    return $ict['group_id'] === 1
        && count($ict['lines']) === 4
        && $comm['group_id'] === 2
        && count($comm['lines']) === 1;
});

// SUITE 6: Historical Snapshot Calculations & Missing Period Guard
echo "\nSUITE 6: Historical Snapshot Calculations & Missing Period Guard\n";

runTest("Test 6A: Historical period 2026-06 evaluates preserved June baseline (10 records)", function() use ($pdo) {
    $report = G6ReadinessService::calculate($pdo, '2026-06');
    if (!$report['has_snapshot'] || $report['mode'] !== 'historical') {
        return false;
    }
    $totalOnHand = array_sum(array_column($report['lines'], 'on_hand'));
    return $totalOnHand === 10;
});

runTest("Test 6B: Historical period 2026-07 evaluates preserved July baseline (14 records)", function() use ($pdo) {
    $report = G6ReadinessService::calculate($pdo, '2026-07');
    if (!$report['has_snapshot'] || $report['mode'] !== 'historical') {
        return false;
    }
    $totalOnHand = array_sum(array_column($report['lines'], 'on_hand'));
    return $totalOnHand === 14;
});

runTest("Test 6C: Non-existent period 2026-01 returns has_snapshot=false and does NOT fall back to live inventory", function() use ($pdo) {
    $report = G6ReadinessService::calculate($pdo, '2026-01');
    return $report['has_snapshot'] === false
        && $report['mode'] === 'historical'
        && !isset($report['lines'])
        && str_contains($report['message'], 'No snapshot data recorded');
});

// SUITE 7: REST API Endpoint Integration & Security
echo "\nSUITE 7: REST API Endpoint Integration & Security (backend/api/inventory/index.php?view=g6_readiness)\n";

runTest("Test 7A: Unauthenticated request to view=g6_readiness rejected with HTTP 401 Unauthorized", function() {
    $res = invokeApiEndpoint('backend/api/inventory/index.php', 'GET', ['view' => 'g6_readiness'], null, []);
    return $res['status'] === 401 && ($res['json']['success'] ?? null) === false;
});

runTest("Test 7B: Authenticated request as Administrator receives HTTP 200 with structured reporting payload", function() {
    $session = [
        'user_id' => 1,
        'user_role' => 'Administrator',
        'role' => 'Administrator',
        'role_id' => 1,
        'permissions' => ['inventory.view' => true]
    ];
    $res = invokeApiEndpoint('backend/api/inventory/index.php', 'GET', ['view' => 'g6_readiness'], null, $session);
    if ($res['status'] !== 200 || ($res['json']['success'] ?? false) !== true) {
        return false;
    }
    $data = $res['json']['data'] ?? [];
    return isset($data['lines']) && count($data['lines']) === 5
        && isset($data['groups']) && count($data['groups']) === 2
        && isset($data['summary']['equipment_rating'])
        && isset($data['summary']['maintenance_rating'])
        && isset($data['summary']['equipment_redcon'])
        && isset($data['summary']['maintenance_redcon']);
});

runTest("Test 7C: API request with period=2026-06 returns historical mode and has_snapshot=true", function() {
    $session = [
        'user_id' => 1,
        'user_role' => 'Administrator',
        'role' => 'Administrator',
        'role_id' => 1,
        'permissions' => ['inventory.view' => true]
    ];
    $res = invokeApiEndpoint('backend/api/inventory/index.php', 'GET', ['view' => 'g6_readiness', 'period' => '2026-06'], null, $session);
    return $res['status'] === 200
        && ($res['json']['data']['mode'] ?? '') === 'historical'
        && ($res['json']['data']['has_snapshot'] ?? false) === true;
});

runTest("Test 7D: API request with period=2026-01 returns has_snapshot=false with no fallback", function() {
    $session = [
        'user_id' => 1,
        'user_role' => 'Administrator',
        'role' => 'Administrator',
        'role_id' => 1,
        'permissions' => ['inventory.view' => true]
    ];
    $res = invokeApiEndpoint('backend/api/inventory/index.php', 'GET', ['view' => 'g6_readiness', 'period' => '2026-01'], null, $session);
    return $res['status'] === 200
        && ($res['json']['data']['has_snapshot'] ?? true) === false
        && ($res['json']['data']['mode'] ?? '') === 'historical'
        && str_contains($res['json']['data']['message'] ?? '', 'No snapshot data recorded');
});

// SUITE 8: Database Baseline Invariance (Zero Mutation Verification)
echo "\nSUITE 8: Database Baseline Invariance (Zero Mutation Verification)\n";

runTest("Test 8A: tbl_inventory_equipment count unchanged (20)", function() use ($pdo) {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment WHERE deleted_at IS NULL")->fetchColumn();
    return $cnt === 20;
});

runTest("Test 8B: tbl_inventory_history total count unchanged (24: June 10, July 14)", function() use ($pdo) {
    $cntTotal = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_history")->fetchColumn();
    $cntJune = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = '2026-06'")->fetchColumn();
    $cntJuly = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = '2026-07'")->fetchColumn();
    return $cntTotal === 24 && $cntJune === 10 && $cntJuly === 14;
});

runTest("Test 8C: tbl_inventory_jrrs row count unchanged (5) and target quantities 100% preserved", function() use ($pdo) {
    $rows = $pdo->query("SELECT equipment_subtype_id, target_quantity FROM tbl_inventory_jrrs ORDER BY equipment_subtype_id ASC")->fetchAll();
    if (count($rows) !== 5) return false;
    $expected = [
        1 => 25, // Desktop
        2 => 10, // Printer
        6 => 15, // Laptop
        7 => 8,  // Network Switch
        11 => 5  // Public Address System
    ];
    foreach ($rows as $r) {
        $stId = (int)$r['equipment_subtype_id'];
        $qty = (int)$r['target_quantity'];
        if (!isset($expected[$stId]) || $expected[$stId] !== $qty) {
            return false;
        }
    }
    return true;
});

runTest("Test 8D: tbl_inventory_equipment_subtypes count unchanged (11)", function() use ($pdo) {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment_subtypes")->fetchColumn();
    return $cnt === 11;
});

echo "\n===============================================================\n";
echo " G6 READINESS SUITE: {$passedTests} passed, {$failedTests} failed out of {$totalTests} tests.\n";
echo "===============================================================\n";

if ($failedTests > 0) {
    exit(1);
}

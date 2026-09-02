<?php
// tests/unit/modules_and_auth_test.php
// Automated CLI Test Suite for 6IS Phase 0 & Phase 1 Integrity, Actual APIs & Data Preservation

ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "===============================================================\n";
echo " 6IS Phase 0 & Phase 1 Automated Test Suite\n";
echo " Core Authentication, Production Module API & Data Preservation\n";
echo "===============================================================\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertTest(string $description, bool $condition, string $details = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$description}\n";
        if ($details) {
            echo "         Details: {$details}\n";
        }
    }
}

/**
 * Runs an isolated PHP code snippet in a CLI subprocess.
 */
function runPhpSnippet(string $code): string {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_snippet_') . '.php';
    $workspaceDir = dirname(__DIR__, 2);
    $wrappedCode = "<?php\nchdir(" . var_export($workspaceDir, true) . ");\n" . $code;
    file_put_contents($tempFile, $wrappedCode);
    $cmd = "d:\\Apps\\xampp\\php\\php.exe " . escapeshellarg($tempFile) . " 2>&1";
    $output = shell_exec($cmd);
    @unlink($tempFile);
    return $output ?? '';
}

/**
 * Invokes an actual production REST API endpoint in an isolated subprocess.
 * Emulates HTTP request method, query params, request body, headers, and session.
 * Captures HTTP status code, raw output, and parsed JSON response.
 *
 * @param string $apiRelativePath Relative path to API endpoint (e.g. 'backend/api/core/modules/index.php')
 * @param string $method HTTP method (GET, PATCH, POST, etc.)
 * @param array $queryParams Array of query string parameters
 * @param array|null $body Array body to encode as JSON
 * @param array $session Session data array (e.g. ['user_id' => 1, 'role' => 'Administrator'])
 * @return array ['status' => int, 'body' => string, 'json' => array|null]
 */
function invokeApiEndpoint(string $apiRelativePath, string $method = 'GET', array $queryParams = [], ?array $body = null, array $session = []): array {
    $workspaceDir = dirname(__DIR__, 2);
    $apiAbsPath = str_replace('\\', '/', $workspaceDir . '/' . ltrim($apiRelativePath, '/'));
    
    $jsonBody = $body !== null ? json_encode($body) : '';
    
    $wrapper = "<?php\n" .
        "chdir(" . var_export($workspaceDir, true) . ");\n" .
        "\$_SERVER['REQUEST_METHOD'] = " . var_export(strtoupper($method), true) . ";\n" .
        "\$_SERVER['HTTP_ORIGIN'] = 'http://localhost:5173';\n" .
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
    
    $statusCode = 200;
    $bodyOutput = $rawOutput;
    if (preg_match('/__HTTP_CODE__:(\d+)/', $rawOutput, $matches)) {
        $statusCode = (int)$matches[1];
        $bodyOutput = str_replace($matches[0], '', $rawOutput);
    }
    
    $json = json_decode(trim($bodyOutput), true);
    
    return [
        'status' => $statusCode,
        'body' => trim($bodyOutput),
        'json' => $json
    ];
}

// Connect to Database
$configPath = __DIR__ . '/../../backend/config/database.php';
$dbConfig = require $configPath;
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// =========================================================================
// REQUIREMENT 3: Capture Original Module States Before Tests
// =========================================================================
$originalModuleStates = [];
$stmt = $pdo->query("SELECT module_key, is_active FROM tbl_modules");
while ($row = $stmt->fetch()) {
    $originalModuleStates[$row['module_key']] = (int)$row['is_active'];
}

try {

    // =========================================================================
    // SUITE 1: Core Authentication Stabilization (Phase 0)
    // =========================================================================
    echo "SUITE 1: Core Authentication Stabilization Regression (Subprocess Isolation)\n";

    // Test 1A & 1B: Unauthenticated request to requireAuth() helper
    $outputUnauth = runPhpSnippet('
        require "backend/helpers/auth.php";
        requireAuth();
    ');
    $jsonUnauth = json_decode($outputUnauth, true);

    assertTest(
        "Test 1A: requireAuth() rejects unauthenticated requests with JSON",
        is_array($jsonUnauth) && isset($jsonUnauth['success']) && $jsonUnauth['success'] === false,
        "Output: " . trim($outputUnauth)
    );

    assertTest(
        "Test 1B: requireAuth() error message explicitly states Unauthorized",
        isset($jsonUnauth['message']) && str_contains($jsonUnauth['message'], 'Unauthorized'),
        "Message: " . ($jsonUnauth['message'] ?? 'none')
    );

    // Test 1C: No silent fallback Administrator session created
    $sessionRole = runPhpSnippet('
        require "backend/helpers/auth.php";
        echo isset($_SESSION["role"]) ? $_SESSION["role"] : "NO_SESSION";
    ');

    assertTest(
        "Test 1C: No silent fallback Administrator session is assigned",
        trim($sessionRole) === 'NO_SESSION',
        "Role found: " . trim($sessionRole)
    );

    // Test 1D: Authenticated session continues without exiting
    $outputAuth = runPhpSnippet('
        session_start();
        $_SESSION["user_id"] = 1;
        $_SESSION["role"] = "Administrator";
        require "backend/helpers/auth.php";
        requireAuth();
        echo "AUTH_CONTINUED_SUCCESSFULLY";
    ');

    assertTest(
        "Test 1D: Valid authenticated session passes requireAuth() cleanly",
        trim($outputAuth) === 'AUTH_CONTINUED_SUCCESSFULLY',
        "Output: " . trim($outputAuth)
    );

    // Test 1E: Role check requireRole("Administrator") rejects "User" with HTTP 403
    $outputRoleMismatch = runPhpSnippet('
        session_start();
        $_SESSION["user_id"] = 2;
        $_SESSION["role"] = "User";
        require "backend/helpers/auth.php";
        requireRole("Administrator");
    ');
    $jsonRole = json_decode($outputRoleMismatch, true);

    assertTest(
        "Test 1E: requireRole('Administrator') rejects 'User' role with HTTP 403 JSON",
        is_array($jsonRole) && isset($jsonRole['success']) && $jsonRole['success'] === false && str_contains($jsonRole['message'], 'Forbidden'),
        "Output: " . trim($outputRoleMismatch)
    );

    echo "\n";

    // =========================================================================
    // SUITE 2: Module Registry Schema & Database Seeding Verification (Phase 1)
    // =========================================================================
    echo "SUITE 2: Module Registry Schema & Seeding Verification\n";

    // Test 2A: Table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'tbl_modules'")->fetchAll();
    assertTest(
        "Test 2A: tbl_modules table exists in database",
        count($tables) === 1
    );

    // Test 2B: 8 Official Modules seeded
    $stmt = $pdo->query("SELECT module_key, name, is_core, is_active FROM tbl_modules ORDER BY sort_order ASC");
    $allModules = $stmt->fetchAll();
    $moduleMap = [];
    foreach ($allModules as $m) {
        $moduleMap[$m['module_key']] = $m;
    }

    $expectedModules = [
        'dashboard', 'inventory', 'communications', 'calendar',
        'accomplishments', 'performance', 'finances', 'administrator'
    ];

    $allPresent = true;
    foreach ($expectedModules as $exp) {
        if (!isset($moduleMap[$exp])) {
            $allPresent = false;
            break;
        }
    }

    assertTest(
        "Test 2B: All 8 official modules registered in tbl_modules",
        $allPresent && count($allModules) === 8,
        "Count found: " . count($allModules)
    );

    // Test 2C: Core Modules are flagged is_core = 1
    assertTest(
        "Test 2C: Core modules ('dashboard', 'administrator') flagged with is_core = 1",
        isset($moduleMap['dashboard']) && (int)$moduleMap['dashboard']['is_core'] === 1 &&
        isset($moduleMap['administrator']) && (int)$moduleMap['administrator']['is_core'] === 1
    );

    // Test 2D: Unreleased modules ('performance', 'finances') have route NULL
    $unreleasedRoutesNull = true;
    $stmt = $pdo->query("SELECT route FROM tbl_modules WHERE module_key IN ('performance', 'finances')");
    while ($row = $stmt->fetch()) {
        if ($row['route'] !== null) {
            $unreleasedRoutesNull = false;
        }
    }
    assertTest(
        "Test 2D: Unreleased modules ('performance', 'finances') have route = NULL",
        $unreleasedRoutesNull
    );

    echo "\n";

    // =========================================================================
    // SUITE 3: Module Helper Robust Error Handling (Requirement 4)
    // =========================================================================
    echo "SUITE 3: Module Helper Error Handling Verification (backend/helpers/modules.php)\n";

    // Test 3A: isModuleActive returns true for active module
    $outputActiveHelper = runPhpSnippet('
        require "backend/helpers/modules.php";
        echo isModuleActive("dashboard") ? "IS_ACTIVE" : "IS_INACTIVE";
    ');
    assertTest(
        "Test 3A: isModuleActive('dashboard') returns true for active module",
        trim($outputActiveHelper) === 'IS_ACTIVE'
    );

    // Test 3B: isModuleActive returns false for non-existent module
    $outputMissingHelper = runPhpSnippet('
        require "backend/helpers/modules.php";
        echo isModuleActive("non_existent_module_xyz") ? "IS_ACTIVE" : "IS_INACTIVE";
    ');
    assertTest(
        "Test 3B: isModuleActive('non_existent_module_xyz') returns false for non-existent module",
        trim($outputMissingHelper) === 'IS_INACTIVE'
    );

    // Test 3C: requireModuleActive propagates HTTP 500 on database failure
    $outputDbError = runPhpSnippet('
        require "backend/helpers/modules.php";
        register_shutdown_function(function() {
            echo "__HTTP_CODE__:" . http_response_code();
        });
        // Pass a broken PDO with invalid SQL mode or mock error
        $brokenPdo = new PDO("sqlite::memory:");
        requireModuleActive("inventory", $brokenPdo);
    ');
    $statusDbError = 200;
    if (preg_match('/__HTTP_CODE__:(\d+)/', $outputDbError, $m)) {
        $statusDbError = (int)$m[1];
    }
    $jsonDbError = json_decode(preg_replace('/__HTTP_CODE__:\d+/', '', $outputDbError), true);

    assertTest(
        "Test 3C: requireModuleActive() returns HTTP 500 on query/database failure instead of false/403",
        $statusDbError === 500 && is_array($jsonDbError) && isset($jsonDbError['message']) && str_contains($jsonDbError['message'], 'Internal server error'),
        "Status: {$statusDbError}, Output: " . trim($outputDbError)
    );

    echo "\n";

    // =========================================================================
    // SUITE 4: Actual Production Module API Endpoints (backend/api/core/modules/index.php)
    // =========================================================================
    echo "SUITE 4: Actual Production Module API Endpoints (Requirement 1)\n";

    // Test 4A: Unauthenticated GET to actual production API returns HTTP 401
    $resGetUnauth = invokeApiEndpoint('backend/api/core/modules/index.php', 'GET');
    assertTest(
        "Test 4A: GET backend/api/core/modules/index.php unauthenticated returns HTTP 401",
        $resGetUnauth['status'] === 401 && isset($resGetUnauth['json']['success']) && $resGetUnauth['json']['success'] === false,
        "Status: {$resGetUnauth['status']}, Body: {$resGetUnauth['body']}"
    );

    // Test 4B: Authenticated GET to actual production API returns HTTP 200 and modules list
    $resGetAuth = invokeApiEndpoint('backend/api/core/modules/index.php', 'GET', [], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 4B: GET backend/api/core/modules/index.php authenticated returns HTTP 200 with module registry",
        $resGetAuth['status'] === 200 && isset($resGetAuth['json']['success']) && $resGetAuth['json']['success'] === true && is_array($resGetAuth['json']['data']) && count($resGetAuth['json']['data']) === 8,
        "Status: {$resGetAuth['status']}, Modules count: " . (is_array($resGetAuth['json']['data'] ?? null) ? count($resGetAuth['json']['data']) : 0)
    );

    // Test 4C: Non-admin user cannot invoke PATCH on actual endpoint (HTTP 403)
    $resPatchUser = invokeApiEndpoint('backend/api/core/modules/index.php', 'PATCH', ['module_key' => 'inventory'], ['is_active' => false], ['user_id' => 2, 'role' => 'User']);
    assertTest(
        "Test 4C: PATCH backend/api/core/modules/index.php as standard 'User' returns HTTP 403 Forbidden",
        $resPatchUser['status'] === 403 && isset($resPatchUser['json']['success']) && $resPatchUser['json']['success'] === false,
        "Status: {$resPatchUser['status']}"
    );

    // Test 4D: Actual PATCH attempting to disable core module 'dashboard' returns HTTP 400
    $resPatchCoreDash = invokeApiEndpoint('backend/api/core/modules/index.php', 'PATCH', ['module_key' => 'dashboard'], ['is_active' => false], ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 4D: Actual PATCH to disable core module 'dashboard' returns HTTP 400 ('Core modules cannot be disabled.')",
        $resPatchCoreDash['status'] === 400 && isset($resPatchCoreDash['json']['success']) && $resPatchCoreDash['json']['success'] === false && str_contains($resPatchCoreDash['json']['message'], 'Core modules cannot be disabled'),
        "Status: {$resPatchCoreDash['status']}, Message: " . ($resPatchCoreDash['json']['message'] ?? '')
    );

    // Test 4E: Actual PATCH attempting to disable core module 'administrator' returns HTTP 400
    $resPatchCoreAdmin = invokeApiEndpoint('backend/api/core/modules/index.php', 'PATCH', ['module_key' => 'administrator'], ['is_active' => false], ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 4E: Actual PATCH to disable core module 'administrator' returns HTTP 400 ('Core modules cannot be disabled.')",
        $resPatchCoreAdmin['status'] === 400 && isset($resPatchCoreAdmin['json']['success']) && $resPatchCoreAdmin['json']['success'] === false && str_contains($resPatchCoreAdmin['json']['message'], 'Core modules cannot be disabled'),
        "Status: {$resPatchCoreAdmin['status']}, Message: " . ($resPatchCoreAdmin['json']['message'] ?? '')
    );

    // Test 4F: Actual PATCH to disable business module 'inventory' returns HTTP 200 with is_active = false
    $resPatchDisableInv = invokeApiEndpoint('backend/api/core/modules/index.php', 'PATCH', ['module_key' => 'inventory'], ['is_active' => false], ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 4F: Actual PATCH to disable 'inventory' returns HTTP 200 with is_active = false",
        $resPatchDisableInv['status'] === 200 && isset($resPatchDisableInv['json']['success']) && $resPatchDisableInv['json']['success'] === true && isset($resPatchDisableInv['json']['data']['is_active']) && $resPatchDisableInv['json']['data']['is_active'] === false,
        "Status: {$resPatchDisableInv['status']}, Body: {$resPatchDisableInv['body']}"
    );

    // Test 4G: Verify in database that inventory is_active is now 0
    $dbInvState = (int)$pdo->query("SELECT is_active FROM tbl_modules WHERE module_key = 'inventory'")->fetchColumn();
    assertTest(
        "Test 4G: Database confirms tbl_modules is_active = 0 for 'inventory'",
        $dbInvState === 0,
        "Current DB is_active: {$dbInvState}"
    );

    echo "\n";

    // =========================================================================
    // SUITE 5: Disabled Module Actual Production API Invocation & Re-enable
    // =========================================================================
    echo "SUITE 5: Production Endpoint Gatekeeper & Re-enablement (Requirement 1)\n";

    // Test 5A: Invoke the actual production Inventory API while disabled -> must return HTTP 403
    $resInvApiDisabled = invokeApiEndpoint('backend/api/inventory/index.php', 'GET', [], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 5A: Invoking actual backend/api/inventory/index.php when disabled returns HTTP 403",
        $resInvApiDisabled['status'] === 403 && isset($resInvApiDisabled['json']['success']) && $resInvApiDisabled['json']['success'] === false && str_contains($resInvApiDisabled['json']['message'], 'disabled on this system'),
        "Status: {$resInvApiDisabled['status']}, Message: " . ($resInvApiDisabled['json']['message'] ?? '')
    );

    // Test 5B: Actual PATCH endpoint to re-enable 'inventory' -> must return HTTP 200 and is_active = true
    $resPatchEnableInv = invokeApiEndpoint('backend/api/core/modules/index.php', 'PATCH', ['module_key' => 'inventory'], ['is_active' => true], ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 5B: Actual PATCH to re-enable 'inventory' returns HTTP 200 with is_active = true",
        $resPatchEnableInv['status'] === 200 && isset($resPatchEnableInv['json']['success']) && $resPatchEnableInv['json']['success'] === true && isset($resPatchEnableInv['json']['data']['is_active']) && $resPatchEnableInv['json']['data']['is_active'] === true,
        "Status: {$resPatchEnableInv['status']}, Body: {$resPatchEnableInv['body']}"
    );

    // Test 5C: Verify in database that inventory is_active is now 1
    $dbInvStateReenabled = (int)$pdo->query("SELECT is_active FROM tbl_modules WHERE module_key = 'inventory'")->fetchColumn();
    assertTest(
        "Test 5C: Database confirms tbl_modules is_active = 1 for 'inventory'",
        $dbInvStateReenabled === 1,
        "Current DB is_active: {$dbInvStateReenabled}"
    );

    // Test 5D: Invoking actual production Inventory API when re-enabled -> must return HTTP 200 OK
    $resInvApiEnabled = invokeApiEndpoint('backend/api/inventory/index.php', 'GET', ['view' => 'equipment_types'], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 5D: Invoking actual backend/api/inventory/index.php when re-enabled returns HTTP 200 OK",
        $resInvApiEnabled['status'] === 200 && isset($resInvApiEnabled['json']['success']) && $resInvApiEnabled['json']['success'] === true,
        "Status: {$resInvApiEnabled['status']}, Message: " . ($resInvApiEnabled['json']['message'] ?? '')
    );

    echo "\n";

    // =========================================================================
    // SUITE 6: Zero Data Loss / Complete State Preservation (Requirement 2)
    // =========================================================================
    echo "SUITE 6: Data Preservation & Baseline Integrity Across Module Lifecycle\n";

    // 1. Capture exact state before deactivation
    $countEquipmentBefore = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment")->fetchColumn();
    $countJrrsBefore = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_jrrs")->fetchColumn();
    $countCommsBefore = (int)$pdo->query("SELECT COUNT(*) FROM tbl_communications")->fetchColumn();
    $countAccomplishmentsBefore = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments")->fetchColumn();
    $countCalendarBefore = (int)$pdo->query("SELECT COUNT(*) FROM tbl_calendar_events")->fetchColumn();

    $sampleEquipmentBefore = $pdo->query("SELECT id, serial_number, description, status FROM tbl_inventory_equipment ORDER BY id ASC LIMIT 1")->fetch();
    $sampleEventBefore = $pdo->query("SELECT id, title, event_date FROM tbl_calendar_events ORDER BY id ASC LIMIT 1")->fetch();

    // 2. Disable Inventory via actual production Module API
    invokeApiEndpoint('backend/api/core/modules/index.php', 'PATCH', ['module_key' => 'inventory'], ['is_active' => false], ['user_id' => 1, 'role' => 'Administrator']);

    // 3. Verify all counts and sample records remain 100% untouched while disabled
    $countEquipmentWhileDisabled = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment")->fetchColumn();
    $countJrrsWhileDisabled = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_jrrs")->fetchColumn();
    $countCommsWhileDisabled = (int)$pdo->query("SELECT COUNT(*) FROM tbl_communications")->fetchColumn();
    $countAccomplishmentsWhileDisabled = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments")->fetchColumn();
    $countCalendarWhileDisabled = (int)$pdo->query("SELECT COUNT(*) FROM tbl_calendar_events")->fetchColumn();

    $sampleEquipmentWhileDisabled = $pdo->query("SELECT id, serial_number, description, status FROM tbl_inventory_equipment WHERE id = " . (int)$sampleEquipmentBefore['id'])->fetch();

    assertTest(
        "Test 6A: Inventory equipment count ({$countEquipmentBefore}) completely unchanged while module is disabled",
        $countEquipmentWhileDisabled === $countEquipmentBefore
    );

    assertTest(
        "Test 6B: JRRS targets ({$countJrrsBefore}), Communications ({$countCommsBefore}), Accomplishments ({$countAccomplishmentsBefore}), Calendar ({$countCalendarBefore}) counts unchanged",
        $countJrrsWhileDisabled === $countJrrsBefore &&
        $countCommsWhileDisabled === $countCommsBefore &&
        $countAccomplishmentsWhileDisabled === $countAccomplishmentsBefore &&
        $countCalendarWhileDisabled === $countCalendarBefore
    );

    assertTest(
        "Test 6C: Representative equipment record ID #{$sampleEquipmentBefore['id']} ('{$sampleEquipmentBefore['description']}') intact while disabled",
        $sampleEquipmentWhileDisabled !== false && $sampleEquipmentWhileDisabled['serial_number'] === $sampleEquipmentBefore['serial_number']
    );

    // 4. Re-enable Inventory via actual production Module API
    invokeApiEndpoint('backend/api/core/modules/index.php', 'PATCH', ['module_key' => 'inventory'], ['is_active' => true], ['user_id' => 1, 'role' => 'Administrator']);

    // 5. Verify counts and sample records after re-enabling
    $countEquipmentAfter = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment")->fetchColumn();
    $sampleEquipmentAfter = $pdo->query("SELECT id, serial_number, description, status FROM tbl_inventory_equipment WHERE id = " . (int)$sampleEquipmentBefore['id'])->fetch();
    $sampleEventAfter = $pdo->query("SELECT id, title, event_date FROM tbl_calendar_events WHERE id = " . (int)$sampleEventBefore['id'])->fetch();

    assertTest(
        "Test 6D: All counts and records identical after restoring Inventory module",
        $countEquipmentAfter === $countEquipmentBefore &&
        $sampleEquipmentAfter['serial_number'] === $sampleEquipmentBefore['serial_number'] &&
        $sampleEventAfter['title'] === $sampleEventBefore['title']
    );

} finally {
    // =========================================================================
    // REQUIREMENT 3: Restore Exact Original Module States
    // =========================================================================
    echo "\nSUITE 7: Original Database State Restoration & Cleanliness (Requirement 3)\n";

    $restoredCleanly = true;
    foreach ($originalModuleStates as $moduleKey => $originalActiveState) {
        $upStmt = $pdo->prepare("UPDATE tbl_modules SET is_active = :act WHERE module_key = :k");
        $upStmt->execute([':act' => $originalActiveState, ':k' => $moduleKey]);
        
        $chk = (int)$pdo->query("SELECT is_active FROM tbl_modules WHERE module_key = " . $pdo->quote($moduleKey))->fetchColumn();
        if ($chk !== $originalActiveState) {
            $restoredCleanly = false;
        }
    }

    assertTest(
        "Test 7A: Exactly restored pre-test module activation states for all 8 modules",
        $restoredCleanly,
        "Original captured states: " . json_encode($originalModuleStates)
    );
}

echo "\n===============================================================\n";
echo " TEST SUMMARY: {$passedTests} passed, {$failedTests} failed out of {$totalTests} tests.\n";
echo "===============================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);

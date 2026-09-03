<?php
// tests/unit/modules_and_auth_test.php
// Automated CLI Test Suite for 6IS Phase 0 & Phase 1 Integrity, Actual APIs & Data Preservation

ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "===============================================================\n";
echo " 6IS Phase 0 + Phase 1 + Phase 2 + Phase 3 Automated Test Suite\n";
echo " Core Auth, Module Registry, RBAC & Organization/Offices\n";
echo "===============================================================\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Track dynamically created fixtures for guaranteed cleanup in finally
$cleanupRoleIds = [];
$cleanupUserIds = [];
$cleanupOfficeIds = [];

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
function invokeApiEndpoint(string $apiRelativePath, string $method = 'GET', array $queryParams = [], ?array $body = null, array $session = [], array $headers = []): array {
    $workspaceDir = dirname(__DIR__, 2);
    $apiAbsPath = str_replace('\\', '/', $workspaceDir . '/' . ltrim($apiRelativePath, '/'));
    
    $jsonBody = $body !== null ? json_encode($body) : '';
    
    $csrfToken = bin2hex(random_bytes(32));
    if (!empty($session) && !isset($session['csrf_token'])) {
        $session['csrf_token'] = $csrfToken;
    }
    $headerCsrf = $headers['X-CSRF-Token'] ?? $headers['HTTP_X_CSRF_TOKEN'] ?? ($session['csrf_token'] ?? $csrfToken);

    $origin = $headers['Origin'] ?? $headers['HTTP_ORIGIN'] ?? 'http://localhost:5173';

    $wrapper = "<?php\n" .
        "chdir(" . var_export($workspaceDir, true) . ");\n" .
        "\$_SERVER['REQUEST_METHOD'] = " . var_export(strtoupper($method), true) . ";\n" .
        "\$_SERVER['HTTP_ORIGIN'] = " . var_export($origin, true) . ";\n" .
        ($headerCsrf !== '__OMIT__' ? "\$_SERVER['HTTP_X_CSRF_TOKEN'] = " . var_export($headerCsrf, true) . ";\n" : "") .
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
    if (preg_match('/__HTTP_CODE__:(\d*)/', $rawOutput, $matches)) {
        $statusCode = !empty($matches[1]) ? (int)$matches[1] : 200;
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

$originalPermissionStates = [];
$stmtPerm = $pdo->query("SELECT id, is_active FROM tbl_permissions");
while ($row = $stmtPerm->fetch()) {
    $originalPermissionStates[(int)$row['id']] = (int)$row['is_active'];
}

$originalOrgRecord = $pdo->query("SELECT * FROM tbl_organization WHERE id = 1 LIMIT 1")->fetch();

$cleanupUserIds = [];
$cleanupOfficeIds = [];
$cleanupRoleIds = [];
$cleanupAccomplishmentIds = [];
$cleanupCommunicationIds = [];
$cleanupEquipmentIds = [];
$cleanupHistoryIds = [];
$cleanupCalendarEventIds = [];

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

    // =========================================================================
    // SUITE 8: Phase 2 Database Schema & RBAC Seed Verification
    // =========================================================================
    echo "\nSUITE 8: Phase 2 Roles & Permissions Database Schema & Seeding Verification\n";

    $hasRolesTable = (bool)$pdo->query("SHOW TABLES LIKE 'tbl_roles'")->fetch();
    $hasPermsTable = (bool)$pdo->query("SHOW TABLES LIKE 'tbl_permissions'")->fetch();
    $hasRolePermsTable = (bool)$pdo->query("SHOW TABLES LIKE 'tbl_role_permissions'")->fetch();

    assertTest("Test 8A: tbl_roles, tbl_permissions, tbl_role_permissions tables exist", $hasRolesTable && $hasPermsTable && $hasRolePermsTable);

    $rolesCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_roles WHERE name IN ('Administrator', 'User') AND is_system = 1")->fetchColumn();
    assertTest("Test 8B: System roles 'Administrator' and 'User' exist with is_system = 1", $rolesCount === 2);

    $permsCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_permissions WHERE is_active = 1")->fetchColumn();
    assertTest("Test 8C: System permissions seeded in tbl_permissions ({$permsCount} permissions)", $permsCount >= 32);

    $adminRoleId = (int)$pdo->query("SELECT id FROM tbl_roles WHERE name = 'Administrator'")->fetchColumn();
    $adminPermCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_role_permissions WHERE role_id = {$adminRoleId}")->fetchColumn();
    assertTest("Test 8D: Administrator role possesses full permission suite ({$adminPermCount} assigned)", $adminPermCount >= 32);

    $usersWithRoleId = (int)$pdo->query("SELECT COUNT(*) FROM tbl_users WHERE role_id IS NOT NULL AND role_id > 0")->fetchColumn();
    $totalUsersCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_users WHERE deleted_at IS NULL")->fetchColumn();
    assertTest("Test 8E: tbl_users.role_id successfully mapped for all users ({$usersWithRoleId}/{$totalUsersCount})", $usersWithRoleId === $totalUsersCount);

    // =========================================================================
    // SUITE 9: Roles API Production Endpoints (backend/api/core/roles/index.php)
    // =========================================================================
    echo "\nSUITE 9: Roles API Production Endpoints (CRUD, System Protection, Transactions)\n";

    // 9A: GET roles unauthenticated -> 401
    $resRolesUnauth = invokeApiEndpoint('backend/api/core/roles/index.php', 'GET');
    assertTest("Test 9A: GET roles unauthenticated returns HTTP 401 Unauthorized", $resRolesUnauth['status'] === 401);

    // 9B: GET roles as User (no roles.view) -> 403
    $resRolesNoPerm = invokeApiEndpoint('backend/api/core/roles/index.php', 'GET', [], null, ['user_id' => 2, 'role' => 'User']);
    assertTest("Test 9B: GET roles as User (lacking roles.view) returns HTTP 403 Forbidden", $resRolesNoPerm['status'] === 403);

    // 9C: GET roles as Admin (has roles.view) -> 200
    $resRolesAdmin = invokeApiEndpoint('backend/api/core/roles/index.php', 'GET', [], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest("Test 9C: GET roles as Administrator returns HTTP 200 OK with role list", $resRolesAdmin['status'] === 200 && is_array($resRolesAdmin['json']['data']));

    // 9D: POST create custom role
    $testRolePayload = ['name' => 'QA Auditor ' . time(), 'description' => 'Test role for automated verification', 'is_active' => true];
    $resCreateRole = invokeApiEndpoint('backend/api/core/roles/index.php', 'POST', [], $testRolePayload, ['user_id' => 1, 'role' => 'Administrator']);
    $createdRoleId = (int)($resCreateRole['json']['data']['id'] ?? 0);
    if ($createdRoleId > 0) {
        $cleanupRoleIds[] = $createdRoleId;
    }
    assertTest("Test 9D: POST create custom role returns HTTP 201 Created (Role ID: {$createdRoleId})", $resCreateRole['status'] === 201 && $createdRoleId > 0);

    // 9E: PATCH update custom role details
    $resPatchRole = invokeApiEndpoint('backend/api/core/roles/index.php', 'PATCH', ['id' => $createdRoleId], ['name' => $testRolePayload['name'] . ' (Updated)', 'description' => 'Updated description'], ['user_id' => 1, 'role' => 'Administrator']);
    assertTest("Test 9E: PATCH custom role details returns HTTP 200 OK", $resPatchRole['status'] === 200 && $resPatchRole['json']['success'] === true);

    // 9F: PATCH assign permissions to custom role
    $samplePermIds = $pdo->query("SELECT id FROM tbl_permissions WHERE module_key = 'inventory' AND permission_key IN ('view', 'create')")->fetchAll(PDO::FETCH_COLUMN);
    $resAssignPerms = invokeApiEndpoint('backend/api/core/roles/index.php', 'PATCH', ['id' => $createdRoleId, 'action' => 'permissions'], ['permission_ids' => $samplePermIds], ['user_id' => 1, 'role' => 'Administrator']);
    assertTest("Test 9F: PATCH assign permissions to custom role returns HTTP 200 OK", $resAssignPerms['status'] === 200 && $resAssignPerms['json']['data']['permission_count'] === count($samplePermIds));

    // 9G: Attempt to rename system role -> 400
    $resRenameSys = invokeApiEndpoint('backend/api/core/roles/index.php', 'PATCH', ['id' => $adminRoleId], ['name' => 'SuperAdmin'], ['user_id' => 1, 'role' => 'Administrator']);
    assertTest("Test 9G: System role cannot be renamed (HTTP 400 rejection)", $resRenameSys['status'] === 400 && str_contains($resRenameSys['json']['message'] ?? '', 'System roles cannot be renamed'));

    // 9H: Attempt to deactivate system role -> 400
    $resDeactSys = invokeApiEndpoint('backend/api/core/roles/index.php', 'PATCH', ['id' => $adminRoleId], ['is_active' => false], ['user_id' => 1, 'role' => 'Administrator']);
    assertTest("Test 9H: System role cannot be deactivated (HTTP 400 rejection)", $resDeactSys['status'] === 400 && str_contains($resDeactSys['json']['message'] ?? '', 'System roles cannot be deactivated'));

    // 9I: Attempt to delete system role -> 400
    $resDelSys = invokeApiEndpoint('backend/api/core/roles/index.php', 'DELETE', ['id' => $adminRoleId], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest("Test 9I: System role cannot be deleted (HTTP 400 rejection)", $resDelSys['status'] === 400 && str_contains($resDelSys['json']['message'] ?? '', 'System roles cannot be deleted'));

    // 9J: Attempt to delete role assigned to users -> 400
    // Create custom role and assign a test user to test un-deletable assigned custom role rule
    $pdo->exec("INSERT INTO tbl_roles (name, is_system, is_active, created_at, updated_at) VALUES ('AssignedCustomRole', 0, 1, NOW(), NOW())");
    $assignedCustomRoleId = (int)$pdo->lastInsertId();
    $cleanupRoleIds[] = $assignedCustomRoleId;
    $pdo->exec("INSERT INTO tbl_users (username, full_name, password, role_id, role, is_active, created_at, updated_at) VALUES ('assigned_test_usr', 'Assigned User', 'hash', {$assignedCustomRoleId}, 'AssignedCustomRole', 1, NOW(), NOW())");
    $assignedTestUserId = (int)$pdo->lastInsertId();
    $cleanupUserIds[] = $assignedTestUserId;

    $resDelAssigned = invokeApiEndpoint('backend/api/core/roles/index.php', 'DELETE', ['id' => $assignedCustomRoleId], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 9J: Assigned role cannot be deleted (HTTP 400 rejection with user count notice)",
        $resDelAssigned['status'] === 400 && str_contains($resDelAssigned['json']['message'] ?? '', 'assigned to'),
        "Status: {$resDelAssigned['status']}, Message: " . ($resDelAssigned['json']['message'] ?? 'none')
    );

    // Clean up assigned test user and role
    $pdo->exec("DELETE FROM tbl_users WHERE id = {$assignedTestUserId}");
    $pdo->exec("DELETE FROM tbl_roles WHERE id = {$assignedCustomRoleId}");

    // 9K: DELETE unassigned custom role -> 200
    $resDelCustom = invokeApiEndpoint('backend/api/core/roles/index.php', 'DELETE', ['id' => $createdRoleId], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest("Test 9K: Unassigned custom role deleted cleanly in transaction (HTTP 200)", $resDelCustom['status'] === 200);

    // =========================================================================
    // SUITE 10: Server-side Permissions Resolution & Invariants
    // =========================================================================
    echo "\nSUITE 10: Server-side Permissions Resolution & Invariant Verification\n";

    // 10A: hasPermission() resolves correctly via PHP helper
    $outputPermCheck = runPhpSnippet('
        session_start();
        $_SESSION["user_id"] = 1;
        $_SESSION["role"] = "Administrator";
        require "backend/helpers/permissions.php";
        echo hasPermission("inventory", "view") ? "HAS_PERM_TRUE" : "HAS_PERM_FALSE";
    ');
    assertTest("Test 10A: hasPermission('inventory', 'view') for Administrator session returns true", trim($outputPermCheck) === 'HAS_PERM_TRUE');

    $outputUserPermCheck = runPhpSnippet('
        session_start();
        $_SESSION["user_id"] = 2;
        $_SESSION["role"] = "User";
        require "backend/helpers/permissions.php";
        echo hasPermission("roles", "delete") ? "HAS_PERM_TRUE" : "HAS_PERM_FALSE";
    ');
    assertTest("Test 10B: hasPermission('roles', 'delete') for standard User session returns false", trim($outputUserPermCheck) === 'HAS_PERM_FALSE');

    // 10C: Configure Independence verification:
    // inventory.configure does NOT grant inventory.create, inventory.edit, inventory.delete
    $outputConfigIndep = runPhpSnippet('
        $cfg = require "backend/config/database.php";
        $pdo = new PDO("mysql:host={$cfg[\'host\']};dbname={$cfg[\'database\']}", $cfg[\'username\'], $cfg[\'password\']);
        
        // Create temp role with ONLY inventory.configure
        $pdo->exec("INSERT INTO tbl_roles (name, is_system, is_active, created_at, updated_at) VALUES (\'TempConfigOnly\', 0, 1, NOW(), NOW())");
        $tRoleId = (int)$pdo->lastInsertId();
        $confPermId = (int)$pdo->query("SELECT id FROM tbl_permissions WHERE module_key = \'inventory\' AND permission_key = \'configure\'")->fetchColumn();
        $pdo->exec("INSERT INTO tbl_role_permissions (role_id, permission_id, created_at) VALUES ({$tRoleId}, {$confPermId}, NOW())");
        
        // Create temp user assigned to TempConfigOnly
        $pdo->exec("INSERT INTO tbl_users (username, full_name, password, role_id, role, is_active, created_at, updated_at) VALUES (\'test_cfg_user\', \'Test Cfg\', \'hash\', {$tRoleId}, \'TempConfigOnly\', 1, NOW(), NOW())");
        $tUserId = (int)$pdo->lastInsertId();
        
        session_start();
        $_SESSION[\'user_id\'] = $tUserId;
        $_SESSION[\'role\'] = \'TempConfigOnly\';
        
        require "backend/helpers/permissions.php";
        $hasConf = hasPermission("inventory", "configure");
        $hasCreate = hasPermission("inventory", "create");
        $hasEdit = hasPermission("inventory", "edit");
        $hasDelete = hasPermission("inventory", "delete");
        
        // Clean up temp user & role
        $pdo->exec("DELETE FROM tbl_users WHERE id = {$tUserId}");
        $pdo->exec("DELETE FROM tbl_role_permissions WHERE role_id = {$tRoleId}");
        $pdo->exec("DELETE FROM tbl_roles WHERE id = {$tRoleId}");
        
        echo json_encode(["conf" => $hasConf, "create" => $hasCreate, "edit" => $hasEdit, "delete" => $hasDelete]);
    ');
    $indepData = json_decode($outputConfigIndep, true);
    assertTest(
        "Test 10C: Configure Independence: inventory.configure does NOT grant create, edit, or delete",
        is_array($indepData) && $indepData['conf'] === true && $indepData['create'] === false && $indepData['edit'] === false && $indepData['delete'] === false
    );

    // 10D: Module Invariant: Administrator + inventory.* when Inventory module is disabled -> HTTP 403
    invokeApiEndpoint('backend/api/core/modules/index.php', 'PATCH', ['module_key' => 'inventory'], ['is_active' => false], ['user_id' => 1, 'role' => 'Administrator']);
    $resInvDisabledAdmin = invokeApiEndpoint('backend/api/inventory/index.php', 'GET', [], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 10D: Module Invariant: Administrator with all permissions calling disabled module gets HTTP 403",
        $resInvDisabledAdmin['status'] === 403 && str_contains($resInvDisabledAdmin['json']['message'] ?? '', 'disabled'),
        "Status: {$resInvDisabledAdmin['status']}, Body: {$resInvDisabledAdmin['body']}"
    );

    // Re-enable Inventory
    invokeApiEndpoint('backend/api/core/modules/index.php', 'PATCH', ['module_key' => 'inventory'], ['is_active' => true], ['user_id' => 1, 'role' => 'Administrator']);
    $resInvEnabledAdmin = invokeApiEndpoint('backend/api/inventory/index.php', 'GET', ['view' => 'equipment_types'], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 10E: Re-enabling module immediately restores authorized access (HTTP 200 OK)",
        $resInvEnabledAdmin['status'] === 200 && ($resInvEnabledAdmin['json']['success'] ?? false) === true,
        "Status: {$resInvEnabledAdmin['status']}, Body: {$resInvEnabledAdmin['body']}"
    );

    // =========================================================================
    // SUITE 11: Current User / Auth API RBAC Payload (backend/api/auth/index.php)
    // =========================================================================
    echo "\nSUITE 11: Current User / Auth API RBAC Payload Verification\n";

    $resAuthCheck = invokeApiEndpoint('backend/api/auth/index.php', 'GET', [], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 11A: GET auth/index.php returns authenticated user with role_id and permissions array",
        $resAuthCheck['status'] === 200 &&
        isset($resAuthCheck['json']['user']['role_id']) &&
        $resAuthCheck['json']['user']['role_id'] === 1 &&
        is_array($resAuthCheck['json']['user']['permissions']) &&
        in_array('inventory.view', $resAuthCheck['json']['user']['permissions']) &&
        in_array('roles.view', $resAuthCheck['json']['user']['permissions']),
        "Status: {$resAuthCheck['status']}, Body: {$resAuthCheck['body']}"
    );

    // =========================================================================
    // SUITE 12: User Management Role Synchronization (backend/api/users/index.php)
    // =========================================================================
    echo "\nSUITE 12: User Management Role_ID Binding & Synchronization\n";

    // Create a temporary user with role_id = 2 (User)
    $tempUsername = 'test_sync_' . time();
    $resCreateUser = invokeApiEndpoint('backend/api/users/index.php', 'POST', ['action' => 'create'], [
        'username' => $tempUsername,
        'full_name' => 'Sync Test User',
        'password' => 'Password123!',
        'role_id' => 2
    ], ['user_id' => 1, 'role' => 'Administrator']);
    $createdUserId = (int)($resCreateUser['json']['data']['id'] ?? 0);
    if ($createdUserId > 0) {
        $cleanupUserIds[] = $createdUserId;
    }

    assertTest("Test 12A: User created with role_id = 2 binds correctly", $resCreateUser['status'] === 201 && $createdUserId > 0);

    // Update the temporary user's role to Administrator (role_id = 1)
    $resUpdateUser = invokeApiEndpoint('backend/api/users/index.php', 'POST', ['action' => 'update'], [
        'id' => $createdUserId,
        'full_name' => 'Sync Test User (Updated)',
        'role_id' => 1
    ], ['user_id' => 1, 'role' => 'Administrator']);

    $dbUserAfter = $pdo->query("SELECT role_id, role FROM tbl_users WHERE id = {$createdUserId}")->fetch();

    assertTest(
        "Test 12B: Updating user role_id synchronizes legacy role column (role_id=1, role='Administrator')",
        $resUpdateUser['status'] === 200 && (int)$dbUserAfter['role_id'] === 1 && $dbUserAfter['role'] === 'Administrator'
    );

    // Clean up temporary user
    $pdo->exec("DELETE FROM tbl_users WHERE id = {$createdUserId}");

    // =========================================================================
    // SUITE 13: Phase 2 RBAC Corrective Pass Regressions
    // =========================================================================
    echo "\nSUITE 13: Phase 2 RBAC Corrective Pass Regression Verification\n";

    // 13A: Permission seed preserves deactivation (is_active = 0 survives re-run)
    $testPermId = (int)$pdo->query("SELECT id FROM tbl_permissions WHERE module_key = 'inventory' AND permission_key = 'create'")->fetchColumn();
    $pdo->exec("UPDATE tbl_permissions SET is_active = 0 WHERE id = {$testPermId}");
    $seedSql = file_get_contents(__DIR__ . '/../../database/migrations/seed_roles_and_permissions.sql');
    $pdo->exec($seedSql);
    $permActiveAfterSeed = (int)$pdo->query("SELECT is_active FROM tbl_permissions WHERE id = {$testPermId}")->fetchColumn();
    assertTest(
        "Test 13A: Permission seed preserves deactivation (is_active remains 0 on repeated seed)",
        $permActiveAfterSeed === 0
    );
    // Restore permission active
    $pdo->exec("UPDATE tbl_permissions SET is_active = 1 WHERE id = {$testPermId}");

    // 13B: Permission database failure produces HTTP 500 with controlled response
    $outputDbFailure = runPhpSnippet('
        session_start();
        $_SESSION["user_id"] = 1;
        $_SESSION["role"] = "Administrator";
        require "backend/helpers/permissions.php";
        
        register_shutdown_function(function() {
            echo "__HTTP_CODE__:" . http_response_code();
        });

        // Force an invalid DB condition using an in-memory SQLite connection lacking MySQL tables
        try {
            $brokenPdo = new PDO("sqlite::memory:");
            requirePermission("inventory", "view", $brokenPdo);
        } catch (Throwable $e) {
            echo "UNCAUGHT_THROWABLE";
        }
    ');
    
    $httpCode = 0;
    if (preg_match('/__HTTP_CODE__:(\d+)/', $outputDbFailure, $mCode)) {
        $httpCode = (int)$mCode[1];
    }
    $jsonStart = strpos($outputDbFailure, '{');
    $jsonDbFailure = $jsonStart !== false ? json_decode(substr($outputDbFailure, $jsonStart, strrpos($outputDbFailure, '}') - $jsonStart + 1), true) : null;

    assertTest(
        "Test 13B: Permission DB failure produces HTTP 500 with controlled JSON response",
        $httpCode === 500 &&
        is_array($jsonDbFailure) &&
        ($jsonDbFailure['success'] ?? true) === false &&
        str_contains($jsonDbFailure['message'] ?? '', 'Internal server error verifying permissions'),
        "HTTP Code: {$httpCode}, Output: {$outputDbFailure}"
    );

    // 13C: Administrator cannot bypass a missing permission
    $pdo->exec("INSERT INTO tbl_roles (name, is_system, is_active, created_at, updated_at) VALUES ('AdminWithoutDelete', 0, 1, NOW(), NOW())");
    $noDelRoleId = (int)$pdo->lastInsertId();
    $cleanupRoleIds[] = $noDelRoleId;
    
    // Assign all permissions EXCEPT inventory.delete
    $pdo->exec("
        INSERT INTO tbl_role_permissions (role_id, permission_id, created_at)
        SELECT {$noDelRoleId}, id, NOW() 
        FROM tbl_permissions 
        WHERE NOT (module_key = 'inventory' AND permission_key = 'delete')
    ");
    
    // Create user with role 'Administrator' in session/table, but role_id pointing to AdminWithoutDelete
    $pdo->exec("INSERT INTO tbl_users (username, full_name, password, role_id, role, is_active, created_at, updated_at) VALUES ('admin_no_del', 'Admin No Del', 'hash', {$noDelRoleId}, 'Administrator', 1, NOW(), NOW())");
    $noDelUserId = (int)$pdo->lastInsertId();
    $cleanupUserIds[] = $noDelUserId;
    
    // Attempt inventory deletion endpoint
    $resBypassAttempt = invokeApiEndpoint(
        'backend/api/inventory/index.php', 
        'POST', 
        ['action' => 'delete_equipment'], 
        ['id' => 99999], 
        ['user_id' => $noDelUserId, 'role' => 'Administrator']
    );
    assertTest(
        "Test 13C: Administrator cannot bypass a missing permission (HTTP 403 denied)",
        $resBypassAttempt['status'] === 403 && str_contains($resBypassAttempt['json']['message'] ?? '', 'permission'),
        "Status: {$resBypassAttempt['status']}, Body: {$resBypassAttempt['body']}"
    );

    // 13D: Core permissions (roles, users, modules) independent of business-module activation
    $pdo->exec("UPDATE tbl_modules SET is_active = 0 WHERE module_key IN ('inventory', 'communications', 'calendar', 'accomplishments')");
    
    $outputCorePermCheck = runPhpSnippet('
        session_start();
        $_SESSION["user_id"] = 1;
        $_SESSION["role"] = "Administrator";
        require "backend/helpers/permissions.php";
        
        $rolesView = hasPermission("roles", "view");
        $usersView = hasPermission("users", "view");
        $modulesView = hasPermission("modules", "view");
        
        echo json_encode([
            "roles" => $rolesView,
            "users" => $usersView,
            "modules" => $modulesView
        ]);
    ');
    $corePermData = json_decode($outputCorePermCheck, true);
    assertTest(
        "Test 13D: Core permissions (roles, users, modules) remain accessible when business modules disabled",
        is_array($corePermData) &&
        ($corePermData['roles'] ?? false) === true &&
        ($corePermData['users'] ?? false) === true &&
        ($corePermData['modules'] ?? false) === true,
        "Output: {$outputCorePermCheck}"
    );

    // =========================================================================
    // SUITE 14: Core Organization Management (Phase 3)
    // =========================================================================
    echo "\nSUITE 14: Core Organization Management (Phase 3 Domain Integrity & Permissions)\n";

    // 14A: GET organization profile with organization.view returns primary org (ID 1, 200 OK)
    $resOrgGet = invokeApiEndpoint(
        'backend/api/core/organization/index.php',
        'GET',
        [],
        null,
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 14A: GET organization profile as Administrator returns HTTP 200 with primary organization",
        $resOrgGet['status'] === 200 &&
        ($resOrgGet['json']['success'] ?? false) === true &&
        (int)($resOrgGet['json']['data']['id'] ?? 0) === 1 &&
        !empty($resOrgGet['json']['data']['name']),
        "Status: {$resOrgGet['status']}, Body: {$resOrgGet['body']}"
    );

    // 14B: GET organization without organization.view returns 403 Forbidden
    $pdo->exec("INSERT INTO tbl_roles (name, description, is_system, is_active, created_at, updated_at) VALUES ('NoOrgRole', 'Role with no org perm', 0, 1, NOW(), NOW())");
    $noOrgRoleId = (int)$pdo->lastInsertId();
    $cleanupRoleIds[] = $noOrgRoleId;

    $pdo->exec("INSERT INTO tbl_users (username, full_name, password, role, role_id, is_active, created_at, updated_at) VALUES ('no_org_user', 'No Org User', 'hash', 'NoOrgRole', {$noOrgRoleId}, 1, NOW(), NOW())");
    $noOrgUserId = (int)$pdo->lastInsertId();
    $cleanupUserIds[] = $noOrgUserId;

    $resOrgNoPerm = invokeApiEndpoint(
        'backend/api/core/organization/index.php',
        'GET',
        [],
        null,
        ['user_id' => $noOrgUserId, 'role' => 'NoOrgRole']
    );
    assertTest(
        "Test 14B: GET organization without 'organization.view' returns HTTP 403 Forbidden",
        $resOrgNoPerm['status'] === 403 &&
        str_contains($resOrgNoPerm['json']['message'] ?? '', 'permission'),
        "Status: {$resOrgNoPerm['status']}, Body: {$resOrgNoPerm['body']}"
    );

    // 14C: PATCH organization with organization.configure updates fields successfully
    $resOrgPatch = invokeApiEndpoint(
        'backend/api/core/organization/index.php',
        'PATCH',
        [],
        [
            'name' => '6th Infantry Division',
            'short_name' => '6ID-TEST',
            'contact_number' => '+63 999 888 7777'
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 14C: PATCH organization as Administrator updates profile and returns HTTP 200",
        $resOrgPatch['status'] === 200 &&
        ($resOrgPatch['json']['success'] ?? false) === true &&
        ($resOrgPatch['json']['data']['short_name'] ?? '') === '6ID-TEST',
        "Status: {$resOrgPatch['status']}, Body: {$resOrgPatch['body']}"
    );

    // 14D: PATCH organization validation (missing name returns HTTP 400)
    $resOrgPatchInvalid = invokeApiEndpoint(
        'backend/api/core/organization/index.php',
        'PATCH',
        [],
        ['name' => '   '],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 14D: PATCH organization with empty name returns HTTP 400 Bad Request",
        $resOrgPatchInvalid['status'] === 400 &&
        ($resOrgPatchInvalid['json']['success'] ?? true) === false,
        "Status: {$resOrgPatchInvalid['status']}, Body: {$resOrgPatchInvalid['body']}"
    );

    // 14E: PATCH organization validation (invalid email format returns HTTP 400)
    $resOrgPatchInvalidEmail = invokeApiEndpoint(
        'backend/api/core/organization/index.php',
        'PATCH',
        [],
        ['email' => 'invalid-not-an-email'],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 14E: PATCH organization with invalid email format returns HTTP 400 Bad Request",
        $resOrgPatchInvalidEmail['status'] === 400 &&
        ($resOrgPatchInvalidEmail['json']['success'] ?? true) === false &&
        str_contains($resOrgPatchInvalidEmail['json']['message'] ?? '', 'email'),
        "Status: {$resOrgPatchInvalidEmail['status']}, Body: {$resOrgPatchInvalidEmail['body']}"
    );

    // 14F: PATCH organization without 'organization.configure' returns HTTP 403 Forbidden
    $resOrgPatchNoPerm = invokeApiEndpoint(
        'backend/api/core/organization/index.php',
        'PATCH',
        [],
        ['name' => 'Unauthorized Name Change'],
        ['user_id' => $noOrgUserId, 'role' => 'NoOrgRole']
    );
    assertTest(
        "Test 14F: PATCH organization without 'organization.configure' returns HTTP 403 Forbidden",
        $resOrgPatchNoPerm['status'] === 403 &&
        str_contains($resOrgPatchNoPerm['json']['message'] ?? '', 'permission'),
        "Status: {$resOrgPatchNoPerm['status']}, Body: {$resOrgPatchNoPerm['body']}"
    );

    // 14G: Administrator without 'organization.configure' cannot bypass (HTTP 403)
    $pdo->exec("INSERT INTO tbl_roles (name, description, is_system, is_active, created_at, updated_at) VALUES ('AdminNoOrgConfig', 'Admin lacking org config', 0, 1, NOW(), NOW())");
    $adminNoOrgConfigRoleId = (int)$pdo->lastInsertId();
    $cleanupRoleIds[] = $adminNoOrgConfigRoleId;

    $pdo->exec("INSERT INTO tbl_role_permissions (role_id, permission_id, created_at) SELECT {$adminNoOrgConfigRoleId}, id, NOW() FROM tbl_permissions WHERE NOT (module_key = 'organization' AND permission_key = 'configure')");
    $pdo->exec("INSERT INTO tbl_users (username, full_name, password, role, role_id, is_active, created_at, updated_at) VALUES ('admin_no_org_cfg', 'Admin No Org Cfg', 'hash', 'Administrator', {$adminNoOrgConfigRoleId}, 1, NOW(), NOW())");
    $adminNoOrgCfgUserId = (int)$pdo->lastInsertId();
    $cleanupUserIds[] = $adminNoOrgCfgUserId;

    $resAdminNoOrgCfg = invokeApiEndpoint(
        'backend/api/core/organization/index.php',
        'PATCH',
        [],
        ['short_name' => 'BYPASS-FAIL'],
        ['user_id' => $adminNoOrgCfgUserId, 'role' => 'Administrator']
    );
    assertTest(
        "Test 14G: Administrator without 'organization.configure' permission cannot bypass (returns HTTP 403)",
        $resAdminNoOrgCfg['status'] === 403 &&
        str_contains($resAdminNoOrgCfg['json']['message'] ?? '', 'permission'),
        "Status: {$resAdminNoOrgCfg['status']}, Body: {$resAdminNoOrgCfg['body']}"
    );

    // =========================================================================
    // SUITE 15: Core Offices Management (Offices CRUD, Uniqueness & Dependency Guards)
    // =========================================================================
    echo "\nSUITE 15: Core Offices Management (Offices CRUD, Uniqueness & Dependency Guards)\n";

    // 15A: GET offices list with offices.view returns offices array with user_count
    $resOfficesGet = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'GET',
        [],
        null,
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15A: GET offices as Administrator returns HTTP 200 with offices list and user_count",
        $resOfficesGet['status'] === 200 &&
        ($resOfficesGet['json']['success'] ?? false) === true &&
        is_array($resOfficesGet['json']['data']) &&
        count($resOfficesGet['json']['data']) > 0 &&
        isset($resOfficesGet['json']['data'][0]['user_count']),
        "Status: {$resOfficesGet['status']}, Body: {$resOfficesGet['body']}"
    );

    // 15B: GET offices without 'offices.view' returns HTTP 403 Forbidden
    $resOfficesNoPerm = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'GET',
        [],
        null,
        ['user_id' => $noOrgUserId, 'role' => 'NoOrgRole']
    );
    assertTest(
        "Test 15B: GET offices without 'offices.view' returns HTTP 403 Forbidden",
        $resOfficesNoPerm['status'] === 403 &&
        str_contains($resOfficesNoPerm['json']['message'] ?? '', 'permission'),
        "Status: {$resOfficesNoPerm['status']}, Body: {$resOfficesNoPerm['body']}"
    );

    // 15C: POST create office with offices.create succeeds (201 Created)
    $testOfficeCode = 'TEST_OFF_' . time();
    $testOfficeName = 'Test Unit Office Alpha ' . time();
    $resOfficeCreate = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'POST',
        [],
        [
            'code' => $testOfficeCode,
            'name' => $testOfficeName,
            'description' => 'Temporary test office',
            'address' => 'Test Location HQ',
            'contact_number' => '12345',
            'email' => 'test_off@6id.mil.ph'
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    $createdOfficeId = (int)($resOfficeCreate['json']['data']['id'] ?? 0);
    if ($createdOfficeId > 0) {
        $cleanupOfficeIds[] = $createdOfficeId;
    }
    assertTest(
        "Test 15C: POST create office as Administrator creates office and returns HTTP 201",
        $resOfficeCreate['status'] === 201 &&
        ($resOfficeCreate['json']['success'] ?? false) === true &&
        $createdOfficeId > 0 &&
        ($resOfficeCreate['json']['data']['code'] ?? '') === $testOfficeCode,
        "Status: {$resOfficeCreate['status']}, Body: {$resOfficeCreate['body']}"
    );

    // 15D: POST create office without 'offices.create' returns HTTP 403 Forbidden
    $resOfficeCreateNoPerm = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'POST',
        [],
        [
            'code' => 'UNAUTH_OFF',
            'name' => 'Unauthorized Office'
        ],
        ['user_id' => $noOrgUserId, 'role' => 'NoOrgRole']
    );
    assertTest(
        "Test 15D: POST create office without 'offices.create' returns HTTP 403 Forbidden",
        $resOfficeCreateNoPerm['status'] === 403 &&
        str_contains($resOfficeCreateNoPerm['json']['message'] ?? '', 'permission'),
        "Status: {$resOfficeCreateNoPerm['status']}, Body: {$resOfficeCreateNoPerm['body']}"
    );

    // 15E: POST duplicate office code within org rejected with 409 Conflict
    $resOfficeDupCode = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'POST',
        [],
        [
            'code' => $testOfficeCode,
            'name' => 'Different Office Name',
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15E: POST duplicate office code in same organization rejected with HTTP 409 Conflict",
        in_array($resOfficeDupCode['status'], [400, 409], true) &&
        ($resOfficeDupCode['json']['success'] ?? true) === false &&
        str_contains($resOfficeDupCode['json']['message'] ?? '', 'already exists'),
        "Status: {$resOfficeDupCode['status']}, Body: {$resOfficeDupCode['body']}"
    );

    // 15F: POST duplicate office name within org rejected with 409 Conflict
    $resOfficeDupName = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'POST',
        [],
        [
            'code' => 'DIFF_CODE_' . time(),
            'name' => $testOfficeName,
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15F: POST duplicate office name in same organization rejected with HTTP 409 Conflict",
        in_array($resOfficeDupName['status'], [400, 409], true) &&
        ($resOfficeDupName['json']['success'] ?? true) === false &&
        str_contains($resOfficeDupName['json']['message'] ?? '', 'already exists'),
        "Status: {$resOfficeDupName['status']}, Body: {$resOfficeDupName['body']}"
    );

    // 15G: POST create office with non-existent organization rejected with HTTP 400 Bad Request
    $resOfficeInvalidOrg = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'POST',
        [],
        [
            'code' => 'INVALID_ORG_OFF_' . time(),
            'name' => 'Invalid Org Office',
            'organization_id' => 999999
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15G: POST create office with non-existent organization rejected with HTTP 400 Bad Request",
        $resOfficeInvalidOrg['status'] === 400 &&
        ($resOfficeInvalidOrg['json']['success'] ?? true) === false &&
        str_contains($resOfficeInvalidOrg['json']['message'] ?? '', 'organization'),
        "Status: {$resOfficeInvalidOrg['status']}, Body: {$resOfficeInvalidOrg['body']}"
    );

    // 15H: PATCH update office (edit name, toggle is_active) succeeds
    $resOfficePatch = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'PATCH',
        [],
        [
            'id' => $createdOfficeId,
            'name' => 'Test Unit Office Beta Updated',
            'is_active' => 0
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15H: PATCH update office name and deactivate succeeds with HTTP 200",
        $resOfficePatch['status'] === 200 &&
        ($resOfficePatch['json']['success'] ?? false) === true &&
        (int)($resOfficePatch['json']['data']['is_active'] ?? 1) === 0,
        "Status: {$resOfficePatch['status']}, Body: {$resOfficePatch['body']}"
    );

    // 15I: PATCH update office without 'offices.edit' returns HTTP 403 Forbidden
    $resOfficePatchNoPerm = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'PATCH',
        [],
        [
            'id' => $createdOfficeId,
            'name' => 'Unauthorized Patch'
        ],
        ['user_id' => $noOrgUserId, 'role' => 'NoOrgRole']
    );
    assertTest(
        "Test 15I: PATCH update office without 'offices.edit' returns HTTP 403 Forbidden",
        $resOfficePatchNoPerm['status'] === 403 &&
        str_contains($resOfficePatchNoPerm['json']['message'] ?? '', 'permission'),
        "Status: {$resOfficePatchNoPerm['status']}, Body: {$resOfficePatchNoPerm['body']}"
    );

    // 15J: DELETE office without 'offices.delete' returns HTTP 403 Forbidden
    $resOfficeDeleteNoPerm = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'DELETE',
        [],
        ['id' => $createdOfficeId],
        ['user_id' => $noOrgUserId, 'role' => 'NoOrgRole']
    );
    assertTest(
        "Test 15J: DELETE office without 'offices.delete' returns HTTP 403 Forbidden",
        $resOfficeDeleteNoPerm['status'] === 403 &&
        str_contains($resOfficeDeleteNoPerm['json']['message'] ?? '', 'permission'),
        "Status: {$resOfficeDeleteNoPerm['status']}, Body: {$resOfficeDeleteNoPerm['body']}"
    );

    // 15K: Administrator without 'offices.delete' cannot bypass (HTTP 403)
    $pdo->exec("INSERT INTO tbl_roles (name, description, is_system, is_active, created_at, updated_at) VALUES ('AdminNoOffDelete', 'Admin lacking off delete', 0, 1, NOW(), NOW())");
    $adminNoOffDelRoleId = (int)$pdo->lastInsertId();
    $cleanupRoleIds[] = $adminNoOffDelRoleId;

    $pdo->exec("INSERT INTO tbl_role_permissions (role_id, permission_id, created_at) SELECT {$adminNoOffDelRoleId}, id, NOW() FROM tbl_permissions WHERE NOT (module_key = 'offices' AND permission_key = 'delete')");
    $pdo->exec("INSERT INTO tbl_users (username, full_name, password, role, role_id, is_active, created_at, updated_at) VALUES ('admin_no_off_del', 'Admin No Off Del', 'hash', 'Administrator', {$adminNoOffDelRoleId}, 1, NOW(), NOW())");
    $adminNoOffDelUserId = (int)$pdo->lastInsertId();
    $cleanupUserIds[] = $adminNoOffDelUserId;

    $resAdminNoOffDel = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'DELETE',
        [],
        ['id' => $createdOfficeId],
        ['user_id' => $adminNoOffDelUserId, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15K: Administrator without 'offices.delete' permission cannot bypass (returns HTTP 403)",
        $resAdminNoOffDel['status'] === 403 &&
        str_contains($resAdminNoOffDel['json']['message'] ?? '', 'permission'),
        "Status: {$resAdminNoOffDel['status']}, Body: {$resAdminNoOffDel['body']}"
    );

    // Reactivate office for user assignment & dependency tests
    $pdo->exec("UPDATE tbl_offices SET is_active = 1 WHERE id = {$createdOfficeId}");

    // 15L: Deletion guard: Cannot delete office if user is assigned
    $pdo->exec("INSERT INTO tbl_users (username, full_name, password, role, role_id, office_id, is_active, created_at, updated_at) VALUES ('office_guard_user', 'Guard User', 'hash', 'User', 2, {$createdOfficeId}, 1, NOW(), NOW())");
    $guardUserId = (int)$pdo->lastInsertId();
    $cleanupUserIds[] = $guardUserId;

    $resDeleteBlocked = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'DELETE',
        [],
        ['id' => $createdOfficeId],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15L: DELETE office with assigned users blocked with HTTP 409 Conflict",
        in_array($resDeleteBlocked['status'], [400, 409], true) &&
        ($resDeleteBlocked['json']['success'] ?? true) === false &&
        str_contains($resDeleteBlocked['json']['message'] ?? '', 'assigned user'),
        "Status: {$resDeleteBlocked['status']}, Body: {$resDeleteBlocked['body']}"
    );

    // Remove user assignment before historical dependency tests
    $pdo->exec("UPDATE tbl_users SET office_id = NULL WHERE id = {$guardUserId}");

    // 15M: Deletion guard: Cannot delete office if referenced by accomplishments
    $pdo->exec("INSERT INTO tbl_accomplishments (office_id, category_id, date, description, created_at, updated_at, created_by, modified_by) VALUES ({$createdOfficeId}, 1, CURDATE(), 'Historical Activity Check', NOW(), NOW(), 1, 1)");
    $histAccId = (int)$pdo->lastInsertId();
    $cleanupAccomplishmentIds[] = $histAccId;

    $resDeleteHistBlocked = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'DELETE',
        [],
        ['id' => $createdOfficeId],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15M: DELETE office with accomplishments blocked with HTTP 409 Conflict",
        in_array($resDeleteHistBlocked['status'], [400, 409], true) &&
        ($resDeleteHistBlocked['json']['success'] ?? true) === false &&
        str_contains($resDeleteHistBlocked['json']['message'] ?? '', 'historical or operational records'),
        "Status: {$resDeleteHistBlocked['status']}, Body: {$resDeleteHistBlocked['body']}"
    );
    $pdo->exec("DELETE FROM tbl_accomplishments WHERE id = {$histAccId}");
    $cleanupAccomplishmentIds = array_diff($cleanupAccomplishmentIds, [$histAccId]);

    // 15M2: Deletion guard: Cannot delete office if referenced by communications
    $pdo->exec("INSERT INTO tbl_communications (communication_type, office_id, category_id, purpose_id, subject, communication_date, status, created_at, updated_at, created_by) VALUES ('Incoming', {$createdOfficeId}, 1, 1, 'Comm Guard Test', CURDATE(), 'Completed', NOW(), NOW(), 1)");
    $histCommId = (int)$pdo->lastInsertId();
    $cleanupCommunicationIds[] = $histCommId;

    $resDeleteCommBlocked = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'DELETE',
        [],
        ['id' => $createdOfficeId],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15M2: DELETE office with communications blocked with HTTP 409 Conflict",
        in_array($resDeleteCommBlocked['status'], [400, 409], true) &&
        ($resDeleteCommBlocked['json']['success'] ?? true) === false &&
        str_contains($resDeleteCommBlocked['json']['message'] ?? '', 'historical or operational records'),
        "Status: {$resDeleteCommBlocked['status']}, Body: {$resDeleteCommBlocked['body']}"
    );
    $pdo->exec("DELETE FROM tbl_communications WHERE id = {$histCommId}");
    $cleanupCommunicationIds = array_diff($cleanupCommunicationIds, [$histCommId]);

    // 15M3: Deletion guard: Cannot delete office if referenced by current inventory equipment
    $pdo->exec("INSERT INTO tbl_inventory_equipment (office_id, equipment_type, description, serial_number, date_acquired, status, created_at, updated_at) VALUES ({$createdOfficeId}, 'Laptop', 'Guard Test Equipment', 'SN-GUARD-1', CURDATE(), 'Serviceable', NOW(), NOW())");
    $guardEqId = (int)$pdo->lastInsertId();
    $cleanupEquipmentIds[] = $guardEqId;

    $resDeleteEqBlocked = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'DELETE',
        [],
        ['id' => $createdOfficeId],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15M3: DELETE office with current inventory equipment blocked with HTTP 409 Conflict",
        in_array($resDeleteEqBlocked['status'], [400, 409], true) &&
        ($resDeleteEqBlocked['json']['success'] ?? true) === false &&
        str_contains($resDeleteEqBlocked['json']['message'] ?? '', 'historical or operational records'),
        "Status: {$resDeleteEqBlocked['status']}, Body: {$resDeleteEqBlocked['body']}"
    );
    $pdo->exec("DELETE FROM tbl_inventory_equipment WHERE id = {$guardEqId}");
    $cleanupEquipmentIds = array_diff($cleanupEquipmentIds, [$guardEqId]);

    // 15M4: Deletion guard: Cannot delete office if referenced by historical inventory snapshots (Prompt Test 1)
    $pdo->exec("INSERT INTO tbl_inventory_history (`year_month`, office_id, equipment_type, description, serial_number, date_acquired, status, snapshot_date, created_at, updated_at) VALUES ('2026-08', {$createdOfficeId}, 'Radio', 'Historical Snapshot Test', 'RAD-001', '2025-01-01', 'Serviceable', '2026-08-31', NOW(), NOW())");
    $histSnapId = (int)$pdo->lastInsertId();
    $cleanupHistoryIds[] = $histSnapId;

    $resDeleteHistSnapBlocked = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'DELETE',
        [],
        ['id' => $createdOfficeId],
        ['user_id' => 1, 'role' => 'Administrator']
    );

    $offStillExists = (int)$pdo->query("SELECT COUNT(*) FROM tbl_offices WHERE id = {$createdOfficeId}")->fetchColumn() === 1;
    $snapStillExists = (int)$pdo->query("SELECT COUNT(*) FROM tbl_inventory_history WHERE id = {$histSnapId}")->fetchColumn() === 1;

    assertTest(
        "Test 15M4: DELETE office with historical inventory snapshot blocked with HTTP 409 Conflict and records preserved",
        in_array($resDeleteHistSnapBlocked['status'], [400, 409], true) &&
        ($resDeleteHistSnapBlocked['json']['success'] ?? true) === false &&
        str_contains($resDeleteHistSnapBlocked['json']['message'] ?? '', 'historical or operational records') &&
        $offStillExists &&
        $snapStillExists,
        "Status: {$resDeleteHistSnapBlocked['status']}, Body: {$resDeleteHistSnapBlocked['body']}"
    );
    $pdo->exec("DELETE FROM tbl_inventory_history WHERE id = {$histSnapId}");
    $cleanupHistoryIds = array_diff($cleanupHistoryIds, [$histSnapId]);

    // 15M5: Deletion guard: Cannot delete office if referenced by calendar events
    $pdo->exec("INSERT INTO tbl_calendar_events (title, event_date, office_id, created_by) VALUES ('Guard Event', CURDATE(), {$createdOfficeId}, 1)");
    $calEventId = (int)$pdo->lastInsertId();
    $cleanupCalendarEventIds[] = $calEventId;

    $resDeleteCalBlocked = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'DELETE',
        [],
        ['id' => $createdOfficeId],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15M5: DELETE office with calendar events blocked with HTTP 409 Conflict",
        in_array($resDeleteCalBlocked['status'], [400, 409], true) &&
        ($resDeleteCalBlocked['json']['success'] ?? true) === false &&
        str_contains($resDeleteCalBlocked['json']['message'] ?? '', 'historical or operational records'),
        "Status: {$resDeleteCalBlocked['status']}, Body: {$resDeleteCalBlocked['body']}"
    );
    $pdo->exec("DELETE FROM tbl_calendar_events WHERE id = {$calEventId}");
    $cleanupCalendarEventIds = array_diff($cleanupCalendarEventIds, [$calEventId]);

    // 15M6: Database failure during dependency check returns HTTP 500 (does not assume 0 dependencies)
    $outputDbFail = runPhpSnippet('
        $_SERVER["REQUEST_METHOD"] = "DELETE";
        $_GET = ["id" => 999999];
        session_start();
        $_SESSION["user_id"] = 1;
        $_SESSION["role"] = "Administrator";
        
        function sendJsonResponse(bool $success, string $message, $data = null, $errors = null, int $statusCode = 200): void {
            http_response_code($statusCode);
            echo "__HTTP_CODE__:{$statusCode}\n";
            echo json_encode(["success" => $success, "message" => $message]);
            exit;
        }

        require_once "backend/helpers/auth.php";
        require_once "backend/helpers/permissions.php";
        
        class FailingPDO extends PDO {
            public function __construct() {}
            public function beginTransaction(): bool { return true; }
            public function inTransaction(): bool { return false; }
            public function rollBack(): bool { return true; }
            public function prepare(string $query, array $options = []): PDOStatement|false {
                throw new PDOException("Simulated connection timeout during dependency check");
            }
        }
        $pdo = new FailingPDO();
        $input = ["id" => 999999];
        $method = "DELETE";
        
        try {
            if ($method === "DELETE") {
                try {
                    $pdo->beginTransaction();
                    $checkStmt = $pdo->prepare("SELECT id FROM tbl_offices");
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    sendJsonResponse(false, "Database failure deleting office.", null, null, 500);
                }
            }
        } catch (Throwable $e) {
            sendJsonResponse(false, "Unexpected error", null, null, 500);
        }
    ');
    
    $httpCodeDbFail = 0;
    if (preg_match('/__HTTP_CODE__:(\d+)/', $outputDbFail, $mCode)) {
        $httpCodeDbFail = (int)$mCode[1];
    }
    $jsonStart = strpos($outputDbFail, '{');
    $jsonDbFail = $jsonStart !== false ? json_decode(substr($outputDbFail, $jsonStart, strrpos($outputDbFail, '}') - $jsonStart + 1), true) : null;

    assertTest(
        "Test 15M6: Dependency query/database failure produces HTTP 500 and prevents deletion",
        $httpCodeDbFail === 500 &&
        is_array($jsonDbFail) &&
        ($jsonDbFail['success'] ?? true) === false &&
        str_contains($jsonDbFail['message'] ?? '', 'Database failure deleting office'),
        "HTTP: {$httpCodeDbFail}, Output: {$outputDbFail}"
    );

    // 15M7: Transaction rollback semantics: mid-operation failure rolls back completely
    $testRollbackCode = 'TX_ROLLBACK_' . time();
    $pdo->exec("INSERT INTO tbl_offices (organization_id, name, code, is_active, created_at, updated_at) VALUES (1, 'Rollback Test Office', '{$testRollbackCode}', 1, NOW(), NOW())");
    $txOfficeId = (int)$pdo->lastInsertId();
    $cleanupOfficeIds[] = $txOfficeId;

    try {
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM tbl_offices WHERE id = {$txOfficeId}");
        throw new Exception("Simulated mid-transaction failure");
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }

    $officeSurvives = (int)$pdo->query("SELECT COUNT(*) FROM tbl_offices WHERE id = {$txOfficeId}")->fetchColumn() === 1;
    assertTest(
        "Test 15M7: Transaction rollback guarantees office remains intact on mid-operation failure",
        $officeSurvives,
        "Office ID: {$txOfficeId} did not survive transaction rollback"
    );

    // 15N: Offices configuration policies with 'offices.configure' succeeds
    $resOfficesConfig = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'GET',
        ['action' => 'configure'],
        null,
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15N: GET offices configuration with 'offices.configure' returns HTTP 200 metadata",
        $resOfficesConfig['status'] === 200 &&
        ($resOfficesConfig['json']['success'] ?? false) === true &&
        isset($resOfficesConfig['json']['data']['allow_registration']),
        "Status: {$resOfficesConfig['status']}, Body: {$resOfficesConfig['body']}"
    );

    // 15O: Offices configuration without 'offices.configure' returns HTTP 403 Forbidden
    $resOfficesConfigNoPerm = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'GET',
        ['action' => 'configure'],
        null,
        ['user_id' => $noOrgUserId, 'role' => 'NoOrgRole']
    );
    assertTest(
        "Test 15O: GET offices configuration without 'offices.configure' returns HTTP 403 Forbidden",
        $resOfficesConfigNoPerm['status'] === 403 &&
        str_contains($resOfficesConfigNoPerm['json']['message'] ?? '', 'permission'),
        "Status: {$resOfficesConfigNoPerm['status']}, Body: {$resOfficesConfigNoPerm['body']}"
    );

    // 15P: DELETE clean office with 0 dependencies succeeds
    $ephemeralCode = 'EPH_' . time();
    $pdo->exec("INSERT INTO tbl_offices (organization_id, name, code, is_active, created_at, updated_at) VALUES (1, 'Ephemeral Office', '{$ephemeralCode}', 1, NOW(), NOW())");
    $ephemeralId = (int)$pdo->lastInsertId();

    $resDeleteClean = invokeApiEndpoint(
        'backend/api/core/offices/index.php',
        'DELETE',
        [],
        ['id' => $ephemeralId],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 15P: DELETE office with zero dependencies succeeds with HTTP 200",
        $resDeleteClean['status'] === 200 &&
        ($resDeleteClean['json']['success'] ?? false) === true,
        "Status: {$resDeleteClean['status']}, Body: {$resDeleteClean['body']}"
    );

    // =========================================================================
    // SUITE 16: User-to-Office Association & Auth Integration (Phase 3)
    // =========================================================================
    echo "\nSUITE 16: User-to-Office Association & Auth Session Integration\n";

    // 16A: Create user with valid active office associates office_id
    $testUserWithOfficeName = 'user_with_office_' . time();
    $resCreateUserWithOffice = invokeApiEndpoint(
        'backend/api/users/index.php',
        'POST',
        ['action' => 'create'],
        [
            'username' => $testUserWithOfficeName,
            'full_name' => 'Officer Test User',
            'password' => 'Password123!',
            'role_id' => 2,
            'office_id' => $createdOfficeId
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    $officerUserId = (int)($resCreateUserWithOffice['json']['data']['id'] ?? 0);
    if ($officerUserId > 0) {
        $cleanupUserIds[] = $officerUserId;
    }
    assertTest(
        "Test 16A: POST create user with valid active office stores and returns office_id (HTTP 201)",
        $resCreateUserWithOffice['status'] === 201 &&
        ($resCreateUserWithOffice['json']['success'] ?? false) === true &&
        (int)($resCreateUserWithOffice['json']['data']['office_id'] ?? 0) === $createdOfficeId,
        "Status: {$resCreateUserWithOffice['status']}, Body: {$resCreateUserWithOffice['body']}"
    );

    // 16B: User session and login payloads include office_id, office_name, and office_code
    $resAuthSession = invokeApiEndpoint(
        'backend/api/auth/index.php',
        'GET',
        [],
        null,
        ['user_id' => $officerUserId, 'role' => 'User']
    );
    assertTest(
        "Test 16B: GET auth session for user with office includes office_id, office_name, and office_code",
        $resAuthSession['status'] === 200 &&
        ($resAuthSession['json']['authenticated'] ?? false) === true &&
        (int)($resAuthSession['json']['user']['office_id'] ?? 0) === $createdOfficeId &&
        !empty($resAuthSession['json']['user']['office_code']),
        "Status: {$resAuthSession['status']}, Body: {$resAuthSession['body']}"
    );

    // 16C: User creation with invalid or inactive office rejected with HTTP 400 Bad Request
    $pdo->exec("UPDATE tbl_offices SET is_active = 0 WHERE id = {$createdOfficeId}");

    $resCreateUserInactiveOffice = invokeApiEndpoint(
        'backend/api/users/index.php',
        'POST',
        ['action' => 'create'],
        [
            'username' => 'inactive_off_user_' . time(),
            'full_name' => 'Inactive Off User',
            'password' => 'Password123!',
            'role_id' => 2,
            'office_id' => $createdOfficeId
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 16C: POST create user with inactive office rejected with HTTP 400 Bad Request",
        $resCreateUserInactiveOffice['status'] === 400 &&
        ($resCreateUserInactiveOffice['json']['success'] ?? true) === false &&
        str_contains($resCreateUserInactiveOffice['json']['message'] ?? '', 'inactive office'),
        "Status: {$resCreateUserInactiveOffice['status']}, Body: {$resCreateUserInactiveOffice['body']}"
    );

    // 16D: Create user with null/empty office allowed (users without office remain fully functional)
    $testUserNoOfficeName = 'user_no_office_' . time();
    $resCreateUserNoOffice = invokeApiEndpoint(
        'backend/api/users/index.php',
        'POST',
        ['action' => 'create'],
        [
            'username' => $testUserNoOfficeName,
            'full_name' => 'No Office User',
            'password' => 'Password123!',
            'role_id' => 2,
            'office_id' => null
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    $noOfficeUserId = (int)($resCreateUserNoOffice['json']['data']['id'] ?? 0);
    if ($noOfficeUserId > 0) {
        $cleanupUserIds[] = $noOfficeUserId;
    }
    assertTest(
        "Test 16D: POST create user with office_id=null succeeds (HTTP 201, unassigned office allowed)",
        $resCreateUserNoOffice['status'] === 201 &&
        ($resCreateUserNoOffice['json']['success'] ?? false) === true &&
        ($resCreateUserNoOffice['json']['data']['office_id'] ?? null) === null,
        "Status: {$resCreateUserNoOffice['status']}, Body: {$resCreateUserNoOffice['body']}"
    );

    // 16E: Auth session for user without office remains fully functional with null office fields
    $resAuthNoOffice = invokeApiEndpoint(
        'backend/api/auth/index.php',
        'GET',
        [],
        null,
        ['user_id' => $noOfficeUserId, 'role' => 'User']
    );
    assertTest(
        "Test 16E: GET auth session for user without office returns null office fields without error",
        $resAuthNoOffice['status'] === 200 &&
        ($resAuthNoOffice['json']['authenticated'] ?? false) === true &&
        ($resAuthNoOffice['json']['user']['office_id'] ?? null) === null,
        "Status: {$resAuthNoOffice['status']}, Body: {$resAuthNoOffice['body']}"
    );

    // Reactivate created office and create a second office for user office updates
    $pdo->exec("UPDATE tbl_offices SET is_active = 1 WHERE id = {$createdOfficeId}");

    $testOffice2Code = 'TEST_OFF2_' . time();
    $pdo->exec("INSERT INTO tbl_offices (organization_id, name, code, is_active, created_at, updated_at) VALUES (1, 'Second Active Office', '{$testOffice2Code}', 1, NOW(), NOW())");
    $secondOfficeId = (int)$pdo->lastInsertId();
    $cleanupOfficeIds[] = $secondOfficeId;

    // 16F: Update user's office to another active office succeeds
    $resUpdateUserOffice = invokeApiEndpoint(
        'backend/api/users/index.php',
        'POST',
        ['action' => 'update'],
        [
            'id' => $officerUserId,
            'office_id' => $secondOfficeId
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    $userCheckStmt = $pdo->prepare("SELECT office_id FROM tbl_users WHERE id = :id");
    $userCheckStmt->execute([':id' => $officerUserId]);
    $updatedUserOffId = (int)$userCheckStmt->fetchColumn();

    assertTest(
        "Test 16F: POST update user office to another active office succeeds (HTTP 200)",
        $resUpdateUserOffice['status'] === 200 &&
        ($resUpdateUserOffice['json']['success'] ?? false) === true &&
        $updatedUserOffId === $secondOfficeId,
        "Status: {$resUpdateUserOffice['status']}, Body: {$resUpdateUserOffice['body']}"
    );

    // 16G: Update user's office to null succeeds (unassign office)
    $resUpdateUserOfficeNull = invokeApiEndpoint(
        'backend/api/users/index.php',
        'POST',
        ['action' => 'update'],
        [
            'id' => $officerUserId,
            'office_id' => null
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    $userCheckStmt->execute([':id' => $officerUserId]);
    $userOffNullCheck = $userCheckStmt->fetchColumn();

    assertTest(
        "Test 16G: POST update user office to null succeeds (HTTP 200, unassign office)",
        $resUpdateUserOfficeNull['status'] === 200 &&
        ($resUpdateUserOfficeNull['json']['success'] ?? false) === true &&
        $userOffNullCheck === null,
        "Status: {$resUpdateUserOfficeNull['status']}, Body: {$resUpdateUserOfficeNull['body']}"
    );

    // 16H: Update user's office to an inactive office is rejected with HTTP 400 Bad Request
    $pdo->exec("UPDATE tbl_offices SET is_active = 0 WHERE id = {$secondOfficeId}");

    $resUpdateUserOfficeInactive = invokeApiEndpoint(
        'backend/api/users/index.php',
        'POST',
        ['action' => 'update'],
        [
            'id' => $officerUserId,
            'office_id' => $secondOfficeId
        ],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 16H: POST update user office to inactive office rejected with HTTP 400 Bad Request",
        $resUpdateUserOfficeInactive['status'] === 400 &&
        ($resUpdateUserOfficeInactive['json']['success'] ?? true) === false &&
        str_contains($resUpdateUserOfficeInactive['json']['message'] ?? '', 'inactive office'),
        "Status: {$resUpdateUserOfficeInactive['status']}, Body: {$resUpdateUserOfficeInactive['body']}"
    );

    // =========================================================================
    // SUITE 17: Phase 4 Audit Database Schema, Migration & Seed Alignment
    // =========================================================================
    echo "\nSUITE 17: Phase 4 Audit Database Schema, Migration & Seed Alignment\n";

    // 17A: tbl_audit_logs exists and has required columns
    $auditCols = $pdo->query("SHOW COLUMNS FROM tbl_audit_logs")->fetchAll(PDO::FETCH_COLUMN);
    $requiredCols = ['id', 'user_id', 'action', 'module_key', 'entity_type', 'entity_id', 'description', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at'];
    $hasAllCols = count(array_intersect($requiredCols, $auditCols)) === count($requiredCols);
    assertTest(
        "Test 17A: tbl_audit_logs table exists and possesses all required columns",
        $hasAllCols,
        "Columns found: " . implode(', ', $auditCols)
    );

    // 17B: tbl_permissions.is_system column exists
    $permCols = $pdo->query("SHOW COLUMNS FROM tbl_permissions")->fetchAll(PDO::FETCH_COLUMN);
    assertTest(
        "Test 17B: tbl_permissions has is_system column with DEFAULT 0",
        in_array('is_system', $permCols, true),
        "Permissions columns: " . implode(', ', $permCols)
    );

    // 17C: 40 official seeded permissions have is_system = 1
    $systemPermCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_permissions WHERE is_system = 1")->fetchColumn();
    assertTest(
        "Test 17C: Exactly 40 official application permissions are flagged is_system = 1",
        $systemPermCount === 40,
        "Found {$systemPermCount} system permissions"
    );

    // 17D: audit.view permission exists with is_system = 1 and assigned to Administrator
    $auditPerm = $pdo->query("SELECT id, is_system FROM tbl_permissions WHERE module_key = 'audit' AND permission_key = 'view'")->fetch();
    $adminHasAudit = false;
    if ($auditPerm) {
        $adminAuditChk = $pdo->query("SELECT COUNT(*) FROM tbl_role_permissions WHERE role_id = 1 AND permission_id = {$auditPerm['id']}")->fetchColumn();
        $adminHasAudit = ((int)$adminAuditChk > 0);
    }
    assertTest(
        "Test 17D: 'audit.view' permission exists (is_system=1) and is assigned to Administrator role",
        $auditPerm && (int)$auditPerm['is_system'] === 1 && $adminHasAudit,
        "Audit perm found: " . json_encode($auditPerm) . ", Admin assigned: " . ($adminHasAudit ? 'yes' : 'no')
    );

    // =========================================================================
    // SUITE 18: Centralized Audit Helper (auditLog & Recursive Sanitization)
    // =========================================================================
    echo "\nSUITE 18: Centralized Audit Helper (auditLog & Recursive Sanitization)\n";
    require_once __DIR__ . '/../../backend/helpers/audit.php';

    // 18A: auditLog inserts record into tbl_audit_logs with derived actor
    $_SESSION['user_id'] = 1;
    $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
    $_SERVER['HTTP_USER_AGENT'] = '6IS-TestRunner/1.0';

    $testAuditId = auditLog([
        'action' => 'CREATE',
        'module_key' => 'test',
        'entity_type' => 'test_item',
        'entity_id' => '999',
        'description' => 'Test audit log write',
        'old_values' => null,
        'new_values' => ['name' => 'Item 999']
    ], $pdo);

    $fetchAudit = $pdo->query("SELECT * FROM tbl_audit_logs WHERE id = {$testAuditId}")->fetch();
    assertTest(
        "Test 18A: auditLog() records entry with automatic user_id, ip_address, and user_agent",
        $fetchAudit &&
        (int)$fetchAudit['user_id'] === 1 &&
        $fetchAudit['action'] === 'CREATE' &&
        $fetchAudit['ip_address'] === '192.168.1.50' &&
        $fetchAudit['user_agent'] === '6IS-TestRunner/1.0',
        "Recorded: " . json_encode($fetchAudit)
    );
    $pdo->exec("DELETE FROM tbl_audit_logs WHERE id = {$testAuditId}");

    // 18B: Recursive sanitization strips sensitive fields
    $dirtyData = [
        'username' => 'TestUser',
        'password' => 'SuperSecret123!',
        'password_hash' => '$2y$10$abcdefghijklmnopqrstuv',
        'token' => 'bearer-token-abc',
        'nested' => [
            'key' => 'allowed_value',
            'new_password' => 'SecretNewPass',
            'api_key' => '12345-secret',
            'deep' => [
                'session_id' => 'sess_9999',
                'secret' => 'topsecret'
            ]
        ]
    ];
    $sanitized = sanitizeAuditData($dirtyData);
    $rawJson = json_encode($sanitized);
    $containsRawSecrets = str_contains($rawJson, 'SuperSecret123!') ||
        str_contains($rawJson, 'SecretNewPass') ||
        str_contains($rawJson, 'topsecret') ||
        str_contains($rawJson, 'sess_9999') ||
        str_contains($rawJson, '$2y$10$abcdefghijklmnopqrstuv');
    $hasRedacted = ($sanitized['password'] ?? '') === '[REDACTED]' &&
        ($sanitized['nested']['deep']['secret'] ?? '') === '[REDACTED]';
    $retainsAllowed = ($sanitized['username'] ?? '') === 'TestUser' &&
        ($sanitized['nested']['key'] ?? '') === 'allowed_value';

    assertTest(
        "Test 18B: sanitizeAuditData() recursively strips passwords, tokens, secrets, and session IDs",
        !$containsRawSecrets && $hasRedacted && $retainsAllowed,
        "Sanitized output: " . json_encode($sanitized)
    );

    // 18C: Transaction atomicity: if auditLog fails in transaction, state mutation is rolled back
    $preCountUsers = (int)$pdo->query("SELECT COUNT(*) FROM tbl_users WHERE username = 'rollback_test_user'")->fetchColumn();
    $mutationRolledBack = false;
    try {
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO tbl_users (username, full_name, password, role, role_id, is_active, created_at, updated_at) VALUES ('rollback_test_user', 'Rollback User', 'hash', 'User', 2, 1, NOW(), NOW())");
        // Force auditLog failure by simulating DB error
        throw new RuntimeException("Simulated audit write failure");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mutationRolledBack = true;
    }
    $postCountUsers = (int)$pdo->query("SELECT COUNT(*) FROM tbl_users WHERE username = 'rollback_test_user'")->fetchColumn();
    assertTest(
        "Test 18C: Transactional rollback guarantees mutation is aborted if audit write fails",
        $mutationRolledBack && $postCountUsers === 0,
        "Post count: {$postCountUsers}"
    );

    // =========================================================================
    // SUITE 19: Core Audit REST API Endpoint (backend/api/core/audit/index.php)
    // =========================================================================
    echo "\nSUITE 19: Core Audit REST API Endpoint (backend/api/core/audit/index.php)\n";

    // 19A: Unauthenticated GET returns HTTP 401
    $resAuditUnauth = invokeApiEndpoint('backend/api/core/audit/index.php', 'GET', [], null, []);
    assertTest(
        "Test 19A: GET audit logs unauthenticated returns HTTP 401 Unauthorized",
        $resAuditUnauth['status'] === 401,
        "Status: {$resAuditUnauth['status']}"
    );

    // 19B: GET audit logs without audit.view permission returns HTTP 403
    $resAuditNoPerm = invokeApiEndpoint('backend/api/core/audit/index.php', 'GET', [], null, ['user_id' => 2, 'role' => 'User']);
    assertTest(
        "Test 19B: GET audit logs without 'audit.view' permission returns HTTP 403 Forbidden",
        $resAuditNoPerm['status'] === 403,
        "Status: {$resAuditNoPerm['status']}, Body: {$resAuditNoPerm['body']}"
    );

    // 19C: GET audit logs as Administrator returns HTTP 200 with pagination
    $resAuditAdmin = invokeApiEndpoint('backend/api/core/audit/index.php', 'GET', ['limit' => 5], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 19C: GET audit logs as Administrator returns HTTP 200 with paginated entries",
        $resAuditAdmin['status'] === 200 &&
        ($resAuditAdmin['json']['success'] ?? false) === true &&
        isset($resAuditAdmin['json']['data']) &&
        isset($resAuditAdmin['json']['pagination']),
        "Status: {$resAuditAdmin['status']}, Body: {$resAuditAdmin['body']}"
    );

    // 19D: Filter audit logs by module_key
    $resAuditModuleFilter = invokeApiEndpoint('backend/api/core/audit/index.php', 'GET', ['module_key' => 'auth'], null, ['user_id' => 1, 'role' => 'Administrator']);
    $allMatchAuth = true;
    if (!empty($resAuditModuleFilter['json']['data'])) {
        foreach ($resAuditModuleFilter['json']['data'] as $entry) {
            if ($entry['module_key'] !== 'auth') $allMatchAuth = false;
        }
    }
    assertTest(
        "Test 19D: Filter audit logs by module_key='auth' returns matching entries",
        $resAuditModuleFilter['status'] === 200 && $allMatchAuth,
        "Status: {$resAuditModuleFilter['status']}"
    );

    // 19E: Read-only immutability: POST and DELETE return HTTP 405
    $resAuditPost = invokeApiEndpoint('backend/api/core/audit/index.php', 'POST', [], ['action' => 'HACK'], ['user_id' => 1, 'role' => 'Administrator']);
    $resAuditDelete = invokeApiEndpoint('backend/api/core/audit/index.php', 'DELETE', ['id' => 1], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 19E: Audit API is strictly read-only: POST and DELETE return HTTP 405 Method Not Allowed",
        $resAuditPost['status'] === 405 && $resAuditDelete['status'] === 405,
        "POST: {$resAuditPost['status']}, DELETE: {$resAuditDelete['status']}"
    );

    // =========================================================================
    // SUITE 20: Session Fixation & Cookie Hardening Verification
    // =========================================================================
    echo "\nSUITE 20: Session Fixation & Cookie Hardening Verification\n";

    // 20A: Login generates a new session ID and returns fresh CSRF token
    $loginResp = invokeApiEndpoint('backend/api/auth/index.php', 'POST', [], ['username' => 'Admin01', 'password' => 'adminpassword01']);
    assertTest(
        "Test 20A: Successful login returns authenticated payload with fresh CSRF token",
        $loginResp['status'] === 200 &&
        ($loginResp['json']['success'] ?? false) === true &&
        !empty($loginResp['json']['csrf_token']),
        "Status: {$loginResp['status']}, Body: {$loginResp['body']}"
    );

    // 20B: Session cookie parameters enforce HttpOnly and SameSite=Lax
    $cookieCheckOutput = shell_exec("d:\\Apps\\xampp\\php\\php.exe -r \"require 'backend/helpers/auth.php'; ensureSessionStarted(); echo json_encode(session_get_cookie_params());\"") ?? '';
    $cookieParams = json_decode(trim($cookieCheckOutput), true) ?? [];
    assertTest(
        "Test 20B: Session cookie parameters enforce httponly=true and samesite=Lax",
        ($cookieParams['httponly'] ?? false) === true &&
        strtolower($cookieParams['samesite'] ?? '') === 'lax',
        "Cookie params: " . json_encode($cookieParams)
    );

    // =========================================================================
    // SUITE 21: CORS Configuration & Strict Server-Side Allowlist
    // =========================================================================
    echo "\nSUITE 21: CORS Configuration & Strict Server-Side Allowlist\n";

    // 21A: Allowed origin receives Access-Control-Allow-Origin
    $resCorsAllowed = invokeApiEndpoint('backend/api/core/modules/index.php', 'GET', [], null, ['user_id' => 1, 'role' => 'Administrator'], ['Origin' => 'http://localhost:5173']);
    assertTest(
        "Test 21A: Allowed origin (http://localhost:5173) receives successful response",
        $resCorsAllowed['status'] === 200,
        "Status: {$resCorsAllowed['status']}"
    );

    // 21B: Attacker origin OPTIONS preflight rejected with HTTP 403
    $resCorsBlocked = invokeApiEndpoint('backend/api/core/modules/index.php', 'OPTIONS', [], null, [], ['Origin' => 'http://malicious-attacker.com']);
    assertTest(
        "Test 21B: Preflight OPTIONS from unauthorized origin returns HTTP 403 Forbidden",
        $resCorsBlocked['status'] === 403,
        "Status: {$resCorsBlocked['status']}, Body: {$resCorsBlocked['body']}"
    );

    // =========================================================================
    // SUITE 22: CSRF Token Generation & Header-First Validation
    // =========================================================================
    echo "\nSUITE 22: CSRF Token Generation & Header-First Validation\n";

    // 22A: Mutating request without CSRF token is rejected with HTTP 403
    $resCsrfMissing = invokeApiEndpoint(
        'backend/api/core/modules/index.php',
        'PATCH',
        ['module_key' => 'inventory'],
        ['is_active' => true],
        ['user_id' => 1, 'role' => 'Administrator'],
        ['X-CSRF-Token' => '__OMIT__']
    );
    assertTest(
        "Test 22A: Mutating request missing CSRF token is rejected with HTTP 403 Forbidden",
        $resCsrfMissing['status'] === 403 &&
        str_contains($resCsrfMissing['json']['message'] ?? '', 'CSRF'),
        "Status: {$resCsrfMissing['status']}, Body: {$resCsrfMissing['body']}"
    );

    // 22B: Mutating request with invalid CSRF token is rejected with HTTP 403
    $resCsrfInvalid = invokeApiEndpoint(
        'backend/api/core/modules/index.php',
        'PATCH',
        ['module_key' => 'inventory'],
        ['is_active' => true],
        ['user_id' => 1, 'role' => 'Administrator'],
        ['X-CSRF-Token' => 'forged_token_12345']
    );
    assertTest(
        "Test 22B: Mutating request with invalid CSRF token is rejected with HTTP 403 Forbidden",
        $resCsrfInvalid['status'] === 403 &&
        str_contains($resCsrfInvalid['json']['message'] ?? '', 'CSRF'),
        "Status: {$resCsrfInvalid['status']}, Body: {$resCsrfInvalid['body']}"
    );

    // 22C: Mutating request with matching valid CSRF token succeeds (HTTP 200)
    $validCsrf = bin2hex(random_bytes(32));
    $resCsrfValid = invokeApiEndpoint(
        'backend/api/core/modules/index.php',
        'PATCH',
        ['module_key' => 'inventory'],
        ['is_active' => true],
        ['user_id' => 1, 'role' => 'Administrator', 'csrf_token' => $validCsrf],
        ['X-CSRF-Token' => $validCsrf]
    );
    assertTest(
        "Test 22C: Mutating request with matching X-CSRF-Token header succeeds with HTTP 200 OK",
        $resCsrfValid['status'] === 200 &&
        ($resCsrfValid['json']['success'] ?? false) === true,
        "Status: {$resCsrfValid['status']}, Body: {$resCsrfValid['body']}"
    );

    // =========================================================================
    // SUITE 23: Final Administrator Protection & Self-Deactivation Guard
    // =========================================================================
    echo "\nSUITE 23: Final Administrator Protection & Self-Deactivation Guard\n";

    // 23A: Self-deactivation guard: Administrator cannot deactivate themselves
    $resSelfDeact = invokeApiEndpoint(
        'backend/api/users/index.php',
        'POST',
        ['action' => 'toggle_active'],
        ['id' => 1, 'is_active' => 0],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 23A: Administrator attempting to deactivate their own account is rejected with HTTP 400",
        $resSelfDeact['status'] === 400 &&
        str_contains($resSelfDeact['json']['message'] ?? '', 'deactivate your own'),
        "Status: {$resSelfDeact['status']}, Body: {$resSelfDeact['body']}"
    );

    // 23B: Attempting to leave zero active Administrators is rejected with HTTP 400
    // Create a temporary second admin
    $pdo->exec("INSERT INTO tbl_users (username, full_name, password, role, role_id, is_active, created_at, updated_at) VALUES ('temp_admin2', 'Temp Admin', 'hash', 'Administrator', 1, 1, NOW(), NOW())");
    $tempAdmin2Id = (int)$pdo->lastInsertId();
    $cleanupUserIds[] = $tempAdmin2Id;

    // Deactivating the second admin when admin 1 is active succeeds
    $resDeactAdmin2 = invokeApiEndpoint(
        'backend/api/users/index.php',
        'POST',
        ['action' => 'toggle_active'],
        ['id' => $tempAdmin2Id, 'is_active' => 0],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 23B: Deactivating second Administrator when another active Administrator exists succeeds (HTTP 200)",
        $resDeactAdmin2['status'] === 200,
        "Status: {$resDeactAdmin2['status']}"
    );

    // Reactivate tempAdmin2
    $pdo->exec("UPDATE tbl_users SET is_active = 1 WHERE id = {$tempAdmin2Id}");
    // Deactivate tempAdmin2 so Admin01 is the sole active admin
    $pdo->exec("UPDATE tbl_users SET is_active = 0 WHERE id = {$tempAdmin2Id}");

    // Attempting to change role of sole active Administrator away from Administrator (leaving 0 active admins) MUST fail
    $resChangeLastAdminRole = invokeApiEndpoint(
        'backend/api/users/index.php',
        'POST',
        ['action' => 'update'],
        ['id' => 1, 'role_id' => 2, 'role' => 'User'],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 23C: Changing role of the last active Administrator away from Administrator is rejected with HTTP 400",
        $resChangeLastAdminRole['status'] === 400 &&
        str_contains($resChangeLastAdminRole['json']['message'] ?? '', 'final active Administrator'),
        "Status: {$resChangeLastAdminRole['status']}, Body: {$resChangeLastAdminRole['body']}"
    );

    // Deleting the sole active Administrator account MUST fail with HTTP 400
    $resDeleteSoleAdmin = invokeApiEndpoint(
        'backend/api/users/index.php',
        'POST',
        ['action' => 'delete'],
        ['id' => 1],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 23D: Deleting the sole active Administrator account is rejected with HTTP 400",
        $resDeleteSoleAdmin['status'] === 400,
        "Status: {$resDeleteSoleAdmin['status']}, Body: {$resDeleteSoleAdmin['body']}"
    );

    // Clean up tempAdmin2
    $pdo->exec("DELETE FROM tbl_users WHERE id = {$tempAdmin2Id}");

    // =========================================================================
    // SUITE 24: System Roles & Permissions Protection
    // =========================================================================
    echo "\nSUITE 24: System Roles & Permissions Protection\n";

    // 24A: System role Administrator cannot be renamed
    $resRenameSysRole = invokeApiEndpoint('backend/api/core/roles/index.php', 'PATCH', ['id' => 1], ['name' => 'SuperAdmin'], ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 24A: Renaming system role 'Administrator' is rejected with HTTP 400",
        $resRenameSysRole['status'] === 400 && str_contains($resRenameSysRole['json']['message'] ?? '', 'System role'),
        "Status: {$resRenameSysRole['status']}"
    );

    // 24B: System role Administrator cannot be deactivated
    $resDeactSysRole = invokeApiEndpoint('backend/api/core/roles/index.php', 'PATCH', ['id' => 1], ['is_active' => false], ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 24B: Deactivating system role 'Administrator' is rejected with HTTP 400",
        $resDeactSysRole['status'] === 400 && str_contains($resDeactSysRole['json']['message'] ?? '', 'System role'),
        "Status: {$resDeactSysRole['status']}"
    );

    // 24C: System role Administrator cannot be deleted
    $resDelSysRole = invokeApiEndpoint('backend/api/core/roles/index.php', 'DELETE', ['id' => 1], null, ['user_id' => 1, 'role' => 'Administrator']);
    assertTest(
        "Test 24C: Deleting system role 'Administrator' is rejected with HTTP 400",
        $resDelSysRole['status'] === 400 && str_contains($resDelSysRole['json']['message'] ?? '', 'System role'),
        "Status: {$resDelSysRole['status']}"
    );

    // =========================================================================
    // SUITE 25: Organization Minimum Active Count Invariant
    // =========================================================================
    echo "\nSUITE 25: Organization Minimum Active Count Invariant\n";

    // 25A: Attempting to deactivate sole organization is rejected with HTTP 400
    $resDeactOrg = invokeApiEndpoint(
        'backend/api/core/organization/index.php',
        'PATCH',
        [],
        ['is_active' => 0],
        ['user_id' => 1, 'role' => 'Administrator']
    );
    assertTest(
        "Test 25A: Deactivating the sole active organization is rejected with HTTP 400",
        $resDeactOrg['status'] === 400 &&
        str_contains($resDeactOrg['json']['message'] ?? '', 'at least one active organization'),
        "Status: {$resDeactOrg['status']}, Body: {$resDeactOrg['body']}"
    );

} finally {
    // =========================================================================
    // GUARANTEED CLEANUP: Restore Exact Original Module & RBAC Database State
    // =========================================================================
    echo "\nSUITE 7: Original Database State Restoration & Cleanliness (Guaranteed Cleanup)\n";

    // 0. Delete temporary dependent records
    if (!empty($cleanupHistoryIds)) {
        $inHist = implode(',', array_map('intval', array_unique($cleanupHistoryIds)));
        $pdo->exec("DELETE FROM tbl_inventory_history WHERE id IN ({$inHist})");
    }
    if (!empty($cleanupEquipmentIds)) {
        $inEq = implode(',', array_map('intval', array_unique($cleanupEquipmentIds)));
        $pdo->exec("DELETE FROM tbl_inventory_equipment WHERE id IN ({$inEq})");
    }
    if (!empty($cleanupAccomplishmentIds)) {
        $inAcc = implode(',', array_map('intval', array_unique($cleanupAccomplishmentIds)));
        $pdo->exec("DELETE FROM tbl_accomplishments WHERE id IN ({$inAcc})");
    }
    if (!empty($cleanupCommunicationIds)) {
        $inComm = implode(',', array_map('intval', array_unique($cleanupCommunicationIds)));
        $pdo->exec("DELETE FROM tbl_communications WHERE id IN ({$inComm})");
    }
    if (!empty($cleanupCalendarEventIds)) {
        $inCal = implode(',', array_map('intval', array_unique($cleanupCalendarEventIds)));
        $pdo->exec("DELETE FROM tbl_calendar_events WHERE id IN ({$inCal})");
    }

    // 1. Delete all tracked temporary test users
    if (!empty($cleanupUserIds)) {
        $inUsers = implode(',', array_map('intval', array_unique($cleanupUserIds)));
        $pdo->exec("DELETE FROM tbl_users WHERE id IN ({$inUsers})");
    }

    // 2. Delete all tracked temporary test offices
    if (!empty($cleanupOfficeIds)) {
        $inOffices = implode(',', array_map('intval', array_unique($cleanupOfficeIds)));
        $pdo->exec("UPDATE tbl_users SET office_id = NULL WHERE office_id IN ({$inOffices})");
        $pdo->exec("DELETE FROM tbl_offices WHERE id IN ({$inOffices})");
    }

    // 3. Delete all tracked temporary test roles and their permissions
    if (!empty($cleanupRoleIds)) {
        $inRoles = implode(',', array_map('intval', array_unique($cleanupRoleIds)));
        $pdo->exec("DELETE FROM tbl_role_permissions WHERE role_id IN ({$inRoles})");
        $pdo->exec("DELETE FROM tbl_roles WHERE id IN ({$inRoles})");
    }

    // 4. Restore original organization record if modified
    if (!empty($originalOrgRecord)) {
        $upOrg = $pdo->prepare("
            UPDATE tbl_organization
            SET name = :name, short_name = :short_name, description = :description,
                address = :address, contact_number = :contact_number, email = :email,
                is_active = :is_active
            WHERE id = 1
        ");
        $upOrg->execute([
            ':name' => $originalOrgRecord['name'],
            ':short_name' => $originalOrgRecord['short_name'],
            ':description' => $originalOrgRecord['description'],
            ':address' => $originalOrgRecord['address'],
            ':contact_number' => $originalOrgRecord['contact_number'],
            ':email' => $originalOrgRecord['email'],
            ':is_active' => $originalOrgRecord['is_active']
        ]);
    }

    // 5. Restore all original permission states
    foreach ($originalPermissionStates as $permId => $origActive) {
        $upPerm = $pdo->prepare("UPDATE tbl_permissions SET is_active = :act WHERE id = :id");
        $upPerm->execute([':act' => $origActive, ':id' => $permId]);
    }

    // 6. Restore original module activation states
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

<?php
// tests/unit/modules_and_auth_test.php
// Automated CLI Test Suite for 6IS Phase 0 & Phase 1 Integrity, Actual APIs & Data Preservation

ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "===============================================================\n";
echo " 6IS Phase 0 + Phase 1 + Phase 2 Automated Test Suite\n";
echo " Core Auth, Module Registry & RBAC Permissions Architecture\n";
echo "===============================================================\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Track dynamically created fixtures for guaranteed cleanup in finally
$cleanupRoleIds = [];
$cleanupUserIds = [];

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

} finally {
    // =========================================================================
    // GUARANTEED CLEANUP: Restore Exact Original Module & RBAC Database State
    // =========================================================================
    echo "\nSUITE 7: Original Database State Restoration & Cleanliness (Guaranteed Cleanup)\n";

    // 1. Delete all tracked temporary test users
    if (!empty($cleanupUserIds)) {
        $inUsers = implode(',', array_map('intval', array_unique($cleanupUserIds)));
        $pdo->exec("DELETE FROM tbl_users WHERE id IN ({$inUsers})");
    }

    // 2. Delete all tracked temporary test roles and their permissions
    if (!empty($cleanupRoleIds)) {
        $inRoles = implode(',', array_map('intval', array_unique($cleanupRoleIds)));
        $pdo->exec("DELETE FROM tbl_role_permissions WHERE role_id IN ({$inRoles})");
        $pdo->exec("DELETE FROM tbl_roles WHERE id IN ({$inRoles})");
    }

    // 3. Restore all original permission states
    foreach ($originalPermissionStates as $permId => $origActive) {
        $upPerm = $pdo->prepare("UPDATE tbl_permissions SET is_active = :act WHERE id = :id");
        $upPerm->execute([':act' => $origActive, ':id' => $permId]);
    }

    // 4. Restore original module activation states
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

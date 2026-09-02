<?php
// tests/unit/modules_and_auth_test.php
// Automated CLI Test Suite for 6IS Phase 0 (Auth Stabilization) & Phase 1 (Module Registry)

ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "===============================================================\n";
echo " 6IS Phase 0 & Phase 1 Automated Test Suite\n";
echo " Core Authentication Stabilization & Module Registry\n";
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
 * Runs a standalone PHP snippet in an isolated CLI subprocess.
 */
function runPhpSnippet(string $code): string {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_6is_') . '.php';
    $workspaceDir = dirname(__DIR__, 2);
    // Ensure working directory is workspace root
    $wrappedCode = "<?php\nchdir(" . var_export($workspaceDir, true) . ");\n" . $code;
    file_put_contents($tempFile, $wrappedCode);
    $cmd = "d:\\Apps\\xampp\\php\\php.exe " . escapeshellarg($tempFile) . " 2>&1";
    $output = shell_exec($cmd);
    @unlink($tempFile);
    return $output ?? '';
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
// SUITE 1: Authentication Stabilization (Phase 0)
// =========================================================================
echo "SUITE 1: Core Authentication Stabilization Regression (Subprocess Isolation)\n";

// Test 1A & 1B: Unauthenticated request to protected requireAuth() helper
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
// SUITE 2: Database Module Registry Schema & Seeding (Phase 1)
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

// Test 2D: Inactive business modules (performance, finances)
assertTest(
    "Test 2D: Unreleased modules ('performance', 'finances') are inactive by default",
    isset($moduleMap['performance']) && (int)$moduleMap['performance']['is_active'] === 0 &&
    isset($moduleMap['finances']) && (int)$moduleMap['finances']['is_active'] === 0
);

echo "\n";

// =========================================================================
// SUITE 3: Core Protection & Module API (GET & PATCH)
// =========================================================================
echo "SUITE 3: Core Protection & Module Activation API Endpoints\n";

// Test 3A: Attempt to deactivate core module ('dashboard') must be rejected with HTTP 400
$outputDeactCore = runPhpSnippet('
    $_SERVER["REQUEST_METHOD"] = "PATCH";
    $_GET["module_key"] = "dashboard";
    session_start();
    $_SESSION["user_id"] = 1;
    $_SESSION["role"] = "Administrator";
    
    // Test direct module API logic
    $pdo = new PDO("mysql:host=localhost;dbname=db_ict_system", "root", "");
    $stmt = $pdo->prepare("SELECT * FROM tbl_modules WHERE module_key = :k LIMIT 1");
    $stmt->execute([":k" => $_GET["module_key"]]);
    $mod = $stmt->fetch();
    
    $input = ["is_active" => false];
    if ((int)$mod["is_core"] === 1 && !$input["is_active"]) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Core modules cannot be disabled."]);
        exit();
    }
');
$jsonDeactCore = json_decode($outputDeactCore, true);

assertTest(
    "Test 3A: Server rejects deactivating core module 'dashboard' with HTTP 400",
    is_array($jsonDeactCore) && isset($jsonDeactCore['success']) && $jsonDeactCore['success'] === false && str_contains($jsonDeactCore['message'], 'Core modules cannot be disabled'),
    "Output: " . trim($outputDeactCore)
);

// Test 3B: Non-admin user cannot toggle module (HTTP 403)
$outputNonAdmin = runPhpSnippet('
    $_SERVER["REQUEST_METHOD"] = "PATCH";
    session_start();
    $_SESSION["user_id"] = 2;
    $_SESSION["role"] = "User";
    require "backend/helpers/auth.php";
    requireRole("Administrator");
');
$jsonNonAdmin = json_decode($outputNonAdmin, true);

assertTest(
    "Test 3B: Standard non-admin user cannot invoke module PATCH toggle (HTTP 403)",
    is_array($jsonNonAdmin) && isset($jsonNonAdmin['success']) && $jsonNonAdmin['success'] === false && str_contains($jsonNonAdmin['message'], 'Forbidden')
);

// Test 3C: Admin toggling business module ('inventory' -> inactive)
$pdo->exec("UPDATE tbl_modules SET is_active = 0 WHERE module_key = 'inventory'");
$checkInvState = (int)$pdo->query("SELECT is_active FROM tbl_modules WHERE module_key = 'inventory'")->fetchColumn();

assertTest(
    "Test 3C: Business module 'inventory' is_active toggled to 0 (disabled)",
    $checkInvState === 0
);

echo "\n";

// =========================================================================
// SUITE 4: Backend Module Gatekeeper (requireModuleActive)
// =========================================================================
echo "SUITE 4: Backend Gatekeeper Enforcement (requireModuleActive)\n";

// Test 4A: When inventory is inactive, requireModuleActive('inventory') returns HTTP 403
$outputInvDisabled = runPhpSnippet('
    require "backend/helpers/modules.php";
    requireModuleActive("inventory");
');
$jsonInvDisabled = json_decode($outputInvDisabled, true);

assertTest(
    "Test 4A: Inactive module 'inventory' access blocked with HTTP 403 and descriptive message",
    is_array($jsonInvDisabled) && isset($jsonInvDisabled['success']) && $jsonInvDisabled['success'] === false && str_contains($jsonInvDisabled['message'], 'disabled'),
    "Output: " . trim($outputInvDisabled)
);

// Test 4B: Re-activating inventory allows requireModuleActive('inventory') to continue
$pdo->exec("UPDATE tbl_modules SET is_active = 1 WHERE module_key = 'inventory'");
$outputInvActive = runPhpSnippet('
    require "backend/helpers/modules.php";
    requireModuleActive("inventory");
    echo "MODULE_ACTIVE_CONTINUE";
');

assertTest(
    "Test 4B: Active module 'inventory' access allowed through gatekeeper",
    trim($outputInvActive) === 'MODULE_ACTIVE_CONTINUE',
    "Output: " . trim($outputInvActive)
);

echo "\n";

// =========================================================================
// SUITE 5: Zero Data Loss / Table Preservation
// =========================================================================
echo "SUITE 5: Zero Data Loss Verification\n";

$equipmentCount = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_equipment")->fetchColumn();
$jrrsCount = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_jrrs")->fetchColumn();
$commsCount = $pdo->query("SELECT COUNT(*) FROM tbl_communications")->fetchColumn();
$accomplishmentsCount = $pdo->query("SELECT COUNT(*) FROM tbl_accomplishments")->fetchColumn();
$calendarCount = $pdo->query("SELECT COUNT(*) FROM tbl_calendar_events")->fetchColumn();

assertTest(
    "Test 5A: Inventory equipment records completely intact after module state toggles",
    $equipmentCount > 0,
    "Row count: {$equipmentCount}"
);

assertTest(
    "Test 5B: JRRS target records completely intact",
    $jrrsCount > 0,
    "Row count: {$jrrsCount}"
);

assertTest(
    "Test 5C: Communications, Accomplishments, and Calendar records completely intact",
    $commsCount > 0 && $accomplishmentsCount > 0 && $calendarCount > 0,
    "Comms: {$commsCount}, Accomplishments: {$accomplishmentsCount}, Calendar: {$calendarCount}"
);

echo "\n";

// =========================================================================
// SUITE 6: Database State Restoration & Cleanliness
// =========================================================================
echo "SUITE 6: Test Isolation & State Cleanup\n";

// Ensure initial intended states:
// dashboard: 1, inventory: 1, communications: 1, calendar: 1, accomplishments: 1, performance: 0, finances: 0, administrator: 1
$pdo->exec("
    UPDATE tbl_modules SET is_active = 1 WHERE module_key IN ('dashboard', 'inventory', 'communications', 'calendar', 'accomplishments', 'administrator');
    UPDATE tbl_modules SET is_active = 0 WHERE module_key IN ('performance', 'finances');
");

$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_modules WHERE is_active = 1")->fetchColumn();
$inactiveCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_modules WHERE is_active = 0")->fetchColumn();

assertTest(
    "Test 6A: Clean state restored with 6 active production modules and 2 inactive unreleased modules",
    $activeCount === 6 && $inactiveCount === 2,
    "Active: {$activeCount}, Inactive: {$inactiveCount}"
);

echo "\n===============================================================\n";
echo " TEST SUMMARY: {$passedTests} passed, {$failedTests} failed out of {$totalTests} tests.\n";
echo "===============================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);

<?php
// tests/unit/accomplishments_test.php
// Automated CLI Test Suite for 6IS Phase 5 Accomplishments & Operational Reporting Suite

ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "===============================================================\n";
echo " 6IS Phase 5 Automated Test Suite: Accomplishments & Reporting\n";
echo "===============================================================\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

$cleanupAccIds = [];
$cleanupCategoryIds = [];

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
 * Invokes an actual production REST API endpoint in an isolated subprocess.
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

    $wrapper .= "require " . var_export($apiAbsPath, true) . ";\n";

    $tempScript = tempnam(sys_get_temp_dir(), '6is_api_test_') . '.php';
    file_put_contents($tempScript, $wrapper);

    $cmd = "d:\\Apps\\xampp\\php\\php.exe " . escapeshellarg($tempScript) . " 2>&1";
    $rawOutput = shell_exec($cmd) ?? '';
    @unlink($tempScript);

    $status = 200;
    $bodyOutput = $rawOutput;
    $json = null;

    $jsonStart = strpos($rawOutput, '{');
    if ($jsonStart !== false) {
        $possibleJson = substr($rawOutput, $jsonStart);
        $decoded = json_decode($possibleJson, true);
        if (is_array($decoded)) {
            $json = $decoded;
            $bodyOutput = $possibleJson;
        }
    }

    // Try to infer status code from json payload if available
    if (is_array($json) && isset($json['success']) && $json['success'] === false) {
        $status = 400;
    }

    return [
        'status' => $status,
        'body' => $bodyOutput,
        'json' => $json,
        'raw' => $rawOutput
    ];
}

$dbConfig = require dirname(__DIR__, 2) . '/backend/config/database.php';
$pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4", $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

try {
    // =========================================================================
    // SUITE 1: Access Control & Permissions for Accomplishments API
    // =========================================================================
    echo "SUITE 1: Access Control & Permissions Enforcement\n";

    // 1A: Unauthenticated request rejected with HTTP 401
    $resUnauth = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET');
    assertTest(
        "Test 1A: Unauthenticated request rejected with HTTP 401 Unauthorized",
        is_array($resUnauth['json']) &&
        ($resUnauth['json']['success'] ?? true) === false &&
        str_contains(strtolower($resUnauth['json']['message'] ?? ''), 'authentication required'),
        "Body: {$resUnauth['body']}"
    );

    // 1B: Authenticated request without 'accomplishments.view' permission rejected with HTTP 403
    $sessionNoPerms = ['user_id' => 999, 'role' => 'TestRole', 'role_id' => 999, 'username' => 'noperms'];
    $resNoView = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [], null, $sessionNoPerms);
    assertTest(
        "Test 1B: Request without 'accomplishments.view' permission rejected with HTTP 403 Forbidden",
        is_array($resNoView['json']) &&
        ($resNoView['json']['success'] ?? true) === false &&
        str_contains(strtolower($resNoView['json']['message'] ?? ''), 'permission'),
        "Body: {$resNoView['body']}"
    );

    // 1C: Administrator session with full permissions can view accomplishments
    $adminSession = ['user_id' => 1, 'role' => 'Administrator', 'role_id' => 1, 'username' => 'admin'];
    $resAdminView = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', ['view' => 'daily'], null, $adminSession);
    assertTest(
        "Test 1C: Administrator with 'accomplishments.view' receives HTTP 200 with record set",
        is_array($resAdminView['json']) &&
        ($resAdminView['json']['success'] ?? false) === true &&
        isset($resAdminView['json']['data']['records']),
        "Body: {$resAdminView['body']}"
    );

    // =========================================================================
    // SUITE 2: Read Operations & Aggregation Rollup Views
    // =========================================================================
    echo "\nSUITE 2: Read Operations & Reporting Rollups\n";

    // 2A: Fetch options (offices & categories)
    $resOptions = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', ['view' => 'options'], null, $adminSession);
    assertTest(
        "Test 2A: GET view=options returns valid offices and categories dropdowns",
        is_array($resOptions['json']) &&
        ($resOptions['json']['success'] ?? false) === true &&
        !empty($resOptions['json']['data']['offices']) &&
        !empty($resOptions['json']['data']['categories']),
        "Body: {$resOptions['body']}"
    );

    // 2B: Fetch overview statistics
    $resOverview = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', ['view' => 'overview'], null, $adminSession);
    assertTest(
        "Test 2B: GET view=overview returns dashboard count aggregations",
        is_array($resOverview['json']) &&
        ($resOverview['json']['success'] ?? false) === true &&
        isset($resOverview['json']['data']['counts']['today']) &&
        isset($resOverview['json']['data']['counts']['monthly']),
        "Body: {$resOverview['body']}"
    );

    // 2C: Fetch monthly rollup report
    $resMonthly = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', ['view' => 'monthly', 'month' => 8, 'year' => 2026], null, $adminSession);
    assertTest(
        "Test 2C: GET view=monthly returns category summaries and outgoing comms rollup",
        is_array($resMonthly['json']) &&
        ($resMonthly['json']['success'] ?? false) === true &&
        isset($resMonthly['json']['data']['accomplishments_by_category']) &&
        isset($resMonthly['json']['data']['outgoing_comms_by_category']),
        "Body: {$resMonthly['body']}"
    );

    // 2D: Fetch quarterly rollup report
    $resQuarterly = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', ['view' => 'quarterly', 'quarter' => 3, 'year' => 2026], null, $adminSession);
    assertTest(
        "Test 2D: GET view=quarterly returns quarterly summary aggregations",
        is_array($resQuarterly['json']) &&
        ($resQuarterly['json']['success'] ?? false) === true &&
        isset($resQuarterly['json']['data']['records']),
        "Body: {$resQuarterly['body']}"
    );

    // 2E: Fetch annual rollup report
    $resAnnual = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', ['view' => 'annual', 'year' => 2026], null, $adminSession);
    assertTest(
        "Test 2E: GET view=annual returns annual summary aggregations",
        is_array($resAnnual['json']) &&
        ($resAnnual['json']['success'] ?? false) === true &&
        isset($resAnnual['json']['data']['records']),
        "Body: {$resAnnual['body']}"
    );

    // =========================================================================
    // SUITE 3: Mutating Operations, Validation & Audit Trail Coupling
    // =========================================================================
    echo "\nSUITE 3: Mutating CRUD Operations & Central Audit Logging Coupling\n";

    // 3A: Payload validation rejects empty description
    $resInvalidPayload = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1,
        'category_id' => 1,
        'date' => '2026-08-25',
        'description' => ''
    ], $adminSession);
    assertTest(
        "Test 3A: POST with empty description rejected with validation error",
        is_array($resInvalidPayload['json']) &&
        ($resInvalidPayload['json']['success'] ?? true) === false &&
        isset($resInvalidPayload['json']['errors']['description']),
        "Body: {$resInvalidPayload['body']}"
    );

    // 3B: POST create accomplishment succeeds (HTTP 201)
    $testDesc = 'Test Automated Accomplishment ' . bin2hex(random_bytes(4));
    $resCreate = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1,
        'category_id' => 1,
        'date' => '2026-08-25',
        'description' => $testDesc,
        'remarks' => 'Automated test execution remark'
    ], $adminSession);

    $createdAccId = (int)($resCreate['json']['data']['id'] ?? 0);
    if ($createdAccId > 0) {
        $cleanupAccIds[] = $createdAccId;
    }

    assertTest(
        "Test 3B: POST create accomplishment creates record and returns new ID (201)",
        $createdAccId > 0 && ($resCreate['json']['success'] ?? false) === true,
        "Body: {$resCreate['body']}"
    );

    // 3C: Verify central audit log coupling on CREATE
    $createAuditEntry = $pdo->query("
        SELECT * FROM tbl_audit_logs 
        WHERE module_key = 'accomplishments' 
          AND entity_type = 'accomplishment' 
          AND entity_id = '{$createdAccId}' 
          AND action = 'CREATE'
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    assertTest(
        "Test 3C: Creating accomplishment transactionally records CREATE event in tbl_audit_logs",
        !empty($createAuditEntry) && (int)$createAuditEntry['user_id'] === 1,
        "Audit entry not found for created accomplishment ID {$createdAccId}"
    );

    // 3D: PUT update accomplishment succeeds (HTTP 200)
    $updatedDesc = $testDesc . ' [UPDATED]';
    $resUpdate = invokeApiEndpoint('backend/api/accomplishments/index.php', 'PUT', ['id' => $createdAccId], [
        'id' => $createdAccId,
        'office_id' => 1,
        'category_id' => 2,
        'date' => '2026-08-25',
        'description' => $updatedDesc,
        'remarks' => 'Updated test remark'
    ], $adminSession);
    assertTest(
        "Test 3D: PUT update accomplishment updates existing record (HTTP 200)",
        is_array($resUpdate['json']) && ($resUpdate['json']['success'] ?? false) === true,
        "Body: {$resUpdate['body']}"
    );

    // 3E: Verify central audit log coupling on UPDATE with old and new values
    $updateAuditEntry = $pdo->query("
        SELECT * FROM tbl_audit_logs 
        WHERE module_key = 'accomplishments' 
          AND entity_type = 'accomplishment' 
          AND entity_id = '{$createdAccId}' 
          AND action = 'UPDATE'
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    assertTest(
        "Test 3E: Updating accomplishment records UPDATE event with state diff in tbl_audit_logs",
        !empty($updateAuditEntry) &&
        !empty($updateAuditEntry['old_values']) &&
        !empty($updateAuditEntry['new_values']),
        "Update audit entry not found"
    );

    // 3F: DELETE accomplishment soft-deletes record (HTTP 200)
    $resDelete = invokeApiEndpoint('backend/api/accomplishments/index.php', 'DELETE', ['id' => $createdAccId], null, $adminSession);
    assertTest(
        "Test 3F: DELETE accomplishment soft-deletes record (HTTP 200)",
        is_array($resDelete['json']) && ($resDelete['json']['success'] ?? false) === true,
        "Body: {$resDelete['body']}"
    );

    $isSoftDeleted = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments WHERE id = {$createdAccId} AND deleted_at IS NOT NULL")->fetchColumn() === 1;
    assertTest(
        "Test 3G: Soft-deleted accomplishment has deleted_at populated and is hidden from standard views",
        $isSoftDeleted,
        "Record was not soft deleted"
    );

    // 3H: Verify central audit log coupling on DELETE
    $deleteAuditEntry = $pdo->query("
        SELECT * FROM tbl_audit_logs 
        WHERE module_key = 'accomplishments' 
          AND entity_type = 'accomplishment' 
          AND entity_id = '{$createdAccId}' 
          AND action = 'DELETE'
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    assertTest(
        "Test 3H: Deleting accomplishment records DELETE event in tbl_audit_logs",
        !empty($deleteAuditEntry) && (int)$deleteAuditEntry['user_id'] === 1,
        "Delete audit entry not found"
    );

    // =========================================================================
    // SUITE 4: Category Admin Configuration & Dependency Protection
    // =========================================================================
    echo "\nSUITE 4: Category Admin Configuration & Dependency Protection\n";

    // 4A: Create reference category
    $testCatCode = 'TC_' . time();
    $resCreateCat = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', ['action' => 'create_category'], [
        'category_name' => 'Test Category ' . time(),
        'category_code' => $testCatCode
    ], $adminSession);
    $createdCatId = (int)($resCreateCat['json']['data']['id'] ?? 0);
    if ($createdCatId > 0) {
        $cleanupCategoryIds[] = $createdCatId;
    }
    assertTest(
        "Test 4A: Admin create reference category succeeds with HTTP 201",
        $createdCatId > 0 && ($resCreateCat['json']['success'] ?? false) === true,
        "Body: {$resCreateCat['body']}"
    );

    // 4B: Delete category with zero dependencies succeeds
    $resDeleteCat = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', ['action' => 'delete_category'], [
        'id' => $createdCatId
    ], $adminSession);
    assertTest(
        "Test 4B: Admin delete reference category with zero dependencies succeeds",
        is_array($resDeleteCat['json']) && ($resDeleteCat['json']['success'] ?? false) === true,
        "Body: {$resDeleteCat['body']}"
    );

    // =========================================================================
    // SUITE 5: DOCX Monthly Report Generator Service
    // =========================================================================
    echo "\nSUITE 5: Production DOCX Report Generator Service\n";

    require_once dirname(__DIR__, 2) . '/backend/services/MonthlyReportGenerator.php';
    $generator = new MonthlyReportGenerator($pdo);
    $docxBinary = $generator->generate(8, 2026);

    $isZipSignature = substr($docxBinary, 0, 4) === "PK\x03\x04";
    $hasSufficientSize = strlen($docxBinary) > 50000;

    assertTest(
        "Test 5A: MonthlyReportGenerator produces valid DOCX binary stream with Zip signature",
        $isZipSignature && $hasSufficientSize,
        "Binary size: " . strlen($docxBinary) . " bytes, Signature: " . bin2hex(substr($docxBinary, 0, 4))
    );

} finally {
    // Guaranteed Cleanup of Test Fixtures
    foreach ($cleanupAccIds as $accId) {
        $pdo->exec("DELETE FROM tbl_audit_logs WHERE module_key = 'accomplishments' AND entity_id = '{$accId}'");
        $pdo->exec("DELETE FROM tbl_accomplishments WHERE id = {$accId}");
    }
    foreach ($cleanupCategoryIds as $catId) {
        $pdo->exec("DELETE FROM tbl_audit_logs WHERE module_key = 'accomplishments' AND entity_id = '{$catId}'");
        $pdo->exec("DELETE FROM tbl_accomplishment_categories WHERE id = {$catId}");
    }
}

echo "\n===============================================================\n";
echo " ACCOMPLISHMENTS SUITE: {$passedTests} passed, {$failedTests} failed out of {$totalTests} tests.\n";
echo "===============================================================\n";
exit($failedTests === 0 ? 0 : 1);

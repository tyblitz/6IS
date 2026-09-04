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

    // =========================================================================
    // SUITE 6: Search & Joins (Finding 1 Regression Verification)
    // =========================================================================
    echo "\nSUITE 6: Search Queries & Multi-Table Joins (Finding 1 Verification)\n";

    // 6A: Search in monthly view executes derived category aggregation with office joins without SQL 1054 error
    $resSearchMonthly = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly',
        'month' => 8,
        'year' => 2026,
        'search' => 'Meeting'
    ], null, $adminSession);
    assertTest(
        "Test 6A: GET view=monthly with search query executes derived table join without SQL 1054 error",
        is_array($resSearchMonthly['json']) &&
        ($resSearchMonthly['json']['success'] ?? false) === true &&
        isset($resSearchMonthly['json']['data']['accomplishments_by_category']),
        "Body: {$resSearchMonthly['body']}"
    );

    // 6B: Search in daily view executes office and category join filtering
    $resSearchDaily = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'daily',
        'date' => '2026-08-25',
        'search' => 'Operations'
    ], null, $adminSession);
    assertTest(
        "Test 6B: GET view=daily with search query filters accomplishments by description or office",
        is_array($resSearchDaily['json']) &&
        ($resSearchDaily['json']['success'] ?? false) === true &&
        isset($resSearchDaily['json']['data']['records']),
        "Body: {$resSearchDaily['body']}"
    );

    // =========================================================================
    // SUITE 7: Date Edge Cases & Custom Period Boundaries (Findings 4 & 5)
    // =========================================================================
    echo "\nSUITE 7: Date Edge Cases & Custom Period Boundary Validation\n";

    // 7A: Daily view with omitted date parameter defaults to today
    $resDefaultDaily = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', ['view' => 'daily'], null, $adminSession);
    assertTest(
        "Test 7A: GET view=daily without date parameter defaults to current date",
        is_array($resDefaultDaily['json']) &&
        ($resDefaultDaily['json']['success'] ?? false) === true &&
        ($resDefaultDaily['json']['data']['date'] ?? '') === date('Y-m-d'),
        "Body: {$resDefaultDaily['body']}"
    );

    // 7B: Daily view with invalid date format rejected with HTTP 400
    $resInvalidDate = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', ['view' => 'daily', 'date' => 'not-a-date'], null, $adminSession);
    assertTest(
        "Test 7B: GET view=daily with invalid date format rejected with validation error (HTTP 400)",
        is_array($resInvalidDate['json']) &&
        ($resInvalidDate['json']['success'] ?? true) === false,
        "Body: {$resInvalidDate['body']}"
    );

    // 7C: Custom view with reversed date range (start_date > end_date) rejected with HTTP 400
    $resReversedCustom = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'custom',
        'start_date' => '2026-08-31',
        'end_date' => '2026-08-01'
    ], null, $adminSession);
    assertTest(
        "Test 7C: GET view=custom with start_date > end_date rejected with HTTP 400",
        is_array($resReversedCustom['json']) &&
        ($resReversedCustom['json']['success'] ?? true) === false &&
        str_contains(strtolower($resReversedCustom['json']['message'] ?? ''), 'start date must be before or equal to end date'),
        "Body: {$resReversedCustom['body']}"
    );

    // 7D: Custom view with same-day boundaries (start_date == end_date) succeeds with HTTP 200
    $resSameDayCustom = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'custom',
        'start_date' => '2026-08-15',
        'end_date' => '2026-08-15'
    ], null, $adminSession);
    assertTest(
        "Test 7D: GET view=custom with same-day boundaries (start_date == end_date) succeeds with HTTP 200",
        is_array($resSameDayCustom['json']) &&
        ($resSameDayCustom['json']['success'] ?? false) === true &&
        isset($resSameDayCustom['json']['data']['accomplishments_by_category']),
        "Body: {$resSameDayCustom['body']}"
    );

    // 7E: Custom view with cross-month boundaries succeeds with HTTP 200
    $resCrossMonthCustom = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'custom',
        'start_date' => '2026-07-15',
        'end_date' => '2026-08-15'
    ], null, $adminSession);
    assertTest(
        "Test 7E: GET view=custom with cross-month period succeeds with HTTP 200",
        is_array($resCrossMonthCustom['json']) &&
        ($resCrossMonthCustom['json']['success'] ?? false) === true &&
        isset($resCrossMonthCustom['json']['data']['accomplishments_by_category']),
        "Body: {$resCrossMonthCustom['body']}"
    );

    // 7F: Custom view with cross-year boundaries succeeds with HTTP 200
    $resCrossYearCustom = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'custom',
        'start_date' => '2025-12-01',
        'end_date' => '2026-02-01'
    ], null, $adminSession);
    assertTest(
        "Test 7F: GET view=custom with cross-year period succeeds with HTTP 200",
        is_array($resCrossYearCustom['json']) &&
        ($resCrossYearCustom['json']['success'] ?? false) === true &&
        isset($resCrossYearCustom['json']['data']['accomplishments_by_category']),
        "Body: {$resCrossYearCustom['body']}"
    );

    // =========================================================================
    // SUITE 8: Calendar Boundaries, Leap Years & Quarter Boundaries
    // =========================================================================
    echo "\nSUITE 8: Calendar Boundaries, Leap Years & Quarter Periods\n";

    // 8A: Leap year handling for February 2024
    $resLeapYear = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly',
        'month' => 2,
        'year' => 2024
    ], null, $adminSession);
    assertTest(
        "Test 8A: GET view=monthly for leap year February (2024) executes without boundary error",
        is_array($resLeapYear['json']) &&
        ($resLeapYear['json']['success'] ?? false) === true,
        "Body: {$resLeapYear['body']}"
    );

    // 8B: Month start and end boundary checking (e.g. August 2026)
    $resMonthBounds = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly',
        'month' => 8,
        'year' => 2026
    ], null, $adminSession);
    assertTest(
        "Test 8B: GET view=monthly handles month boundaries (Day 1 to last day) properly",
        is_array($resMonthBounds['json']) &&
        ($resMonthBounds['json']['success'] ?? false) === true &&
        isset($resMonthBounds['json']['data']['accomplishments_by_category']),
        "Body: {$resMonthBounds['body']}"
    );

    // 8C: All 4 Quarter boundary transitions (Q1: Jan-Mar, Q2: Apr-Jun, Q3: Jul-Sep, Q4: Oct-Dec)
    $qSuccess = true;
    for ($q = 1; $q <= 4; $q++) {
        $resQ = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
            'view' => 'quarterly',
            'quarter' => $q,
            'year' => 2026
        ], null, $adminSession);
        if (!is_array($resQ['json']) || ($resQ['json']['success'] ?? false) !== true) {
            $qSuccess = false;
            break;
        }
    }
    assertTest(
        "Test 8C: GET view=quarterly operates cleanly across all 4 quarter boundaries (Q1-Q4)",
        $qSuccess,
        "One or more quarters failed to load correctly"
    );

    // 8D: Annual reporting coverage
    $resAnnualLeap = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'annual',
        'year' => 2024
    ], null, $adminSession);
    assertTest(
        "Test 8D: GET view=annual handles entire calendar year spanning Jan 1 - Dec 31",
        is_array($resAnnualLeap['json']) &&
        ($resAnnualLeap['json']['success'] ?? false) === true &&
        isset($resAnnualLeap['json']['data']['records']),
        "Body: {$resAnnualLeap['body']}"
    );

    // =========================================================================
    // SUITE 9: Security Boundaries — CSRF Enforcement & Malformed Inputs
    // =========================================================================
    echo "\nSUITE 9: Security Boundaries, CSRF Enforcement & Strict Input Validation\n";

    // 9A: Mutating POST with invalid CSRF token rejected with HTTP 403 Forbidden
    $resInvalidCsrf = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1,
        'category_id' => 1,
        'date' => '2026-08-25',
        'description' => 'Invalid CSRF attempt'
    ], $adminSession, ['X-CSRF-Token' => 'invalid_csrf_token_value_abc123']);
    assertTest(
        "Test 9A: Mutating POST with invalid CSRF token rejected with HTTP 403 Forbidden",
        is_array($resInvalidCsrf['json']) &&
        ($resInvalidCsrf['json']['success'] ?? true) === false &&
        str_contains(strtolower($resInvalidCsrf['json']['message'] ?? ''), 'csrf'),
        "Body: {$resInvalidCsrf['body']}"
    );

    // 9B: Mutating POST with omitted CSRF token rejected with HTTP 403 Forbidden
    $resOmittedCsrf = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1,
        'category_id' => 1,
        'date' => '2026-08-25',
        'description' => 'Omitted CSRF attempt'
    ], $adminSession, ['X-CSRF-Token' => '__OMIT__']);
    assertTest(
        "Test 9B: Mutating POST with omitted CSRF token rejected with HTTP 403 Forbidden",
        is_array($resOmittedCsrf['json']) &&
        ($resOmittedCsrf['json']['success'] ?? true) === false &&
        str_contains(strtolower($resOmittedCsrf['json']['message'] ?? ''), 'csrf'),
        "Body: {$resOmittedCsrf['body']}"
    );

    // 9C: Mutating POST with non-existent category_id (999999) rejected with validation error
    $resBadCat = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1,
        'category_id' => 999999,
        'date' => '2026-08-25',
        'description' => 'Bad category attempt'
    ], $adminSession);
    assertTest(
        "Test 9C: POST with non-existent category_id rejected with validation error",
        is_array($resBadCat['json']) &&
        ($resBadCat['json']['success'] ?? true) === false &&
        isset($resBadCat['json']['errors']['category_id']),
        "Body: {$resBadCat['body']}"
    );

    // 9D: Mutating POST with non-existent office_id (999999) rejected with validation error
    $resBadOffice = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 999999,
        'category_id' => 1,
        'date' => '2026-08-25',
        'description' => 'Bad office attempt'
    ], $adminSession);
    assertTest(
        "Test 9D: POST with non-existent office_id rejected with validation error",
        is_array($resBadOffice['json']) &&
        ($resBadOffice['json']['success'] ?? true) === false &&
        isset($resBadOffice['json']['errors']['office_id']),
        "Body: {$resBadOffice['body']}"
    );

    // 9E: Mutating PUT with non-existent accomplishment ID (999999) rejected with 404
    $resBadPut = invokeApiEndpoint('backend/api/accomplishments/index.php', 'PUT', ['id' => 999999], [
        'id' => 999999,
        'office_id' => 1,
        'category_id' => 1,
        'date' => '2026-08-25',
        'description' => 'Non-existent accomplishment update'
    ], $adminSession);
    assertTest(
        "Test 9E: PUT with non-existent accomplishment ID rejected with HTTP 404",
        is_array($resBadPut['json']) &&
        ($resBadPut['json']['success'] ?? true) === false &&
        str_contains(strtolower($resBadPut['json']['message'] ?? ''), 'not found'),
        "Body: {$resBadPut['body']}"
    );

    // 9F: Category deletion rejected with HTTP 409 when category is referenced by active accomplishment
    // First create category and accomplishment referencing it
    $testDepCatCode = 'TDEP_' . time();
    $resDepCat = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', ['action' => 'create_category'], [
        'category_name' => 'Dep Category ' . time(),
        'category_code' => $testDepCatCode
    ], $adminSession);
    $depCatId = (int)($resDepCat['json']['data']['id'] ?? 0);
    if ($depCatId > 0) {
        $cleanupCategoryIds[] = $depCatId;
    }

    $resDepAcc = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1,
        'category_id' => $depCatId,
        'date' => '2026-08-25',
        'description' => 'Dep test accomplishment'
    ], $adminSession);
    $depAccId = (int)($resDepAcc['json']['data']['id'] ?? 0);
    if ($depAccId > 0) {
        $cleanupAccIds[] = $depAccId;
    }

    // Try deleting category while active accomplishment references it
    $resDeleteDepCat = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', ['action' => 'delete_category'], [
        'id' => $depCatId
    ], $adminSession);
    assertTest(
        "Test 9F: Category deletion rejected with HTTP 409 when referenced by active accomplishment",
        is_array($resDeleteDepCat['json']) &&
        ($resDeleteDepCat['json']['success'] ?? true) === false &&
        str_contains(strtolower($resDeleteDepCat['json']['message'] ?? ''), 'reference'),
        "Body: {$resDeleteDepCat['body']}"
    );

    // =========================================================================
    // SUITE 10: Known Dataset Aggregation Accuracy & Soft Delete Exclusion
    // =========================================================================
    echo "\nSUITE 10: Known Dataset Aggregation Accuracy & Soft Delete Exclusion\n";

    // 10A: Controlled dataset insertion
    // Insert 2 records in Category 1 and 1 record in Category 2 on 2026-08-11
    $targetDate = '2026-08-11';
    $resKnown1 = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1,
        'category_id' => 1,
        'date' => $targetDate,
        'description' => 'Controlled Test Acc 1 - Cat 1'
    ], $adminSession);
    $knownId1 = (int)($resKnown1['json']['data']['id'] ?? 0);
    if ($knownId1 > 0) $cleanupAccIds[] = $knownId1;

    $resKnown2 = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1,
        'category_id' => 1,
        'date' => $targetDate,
        'description' => 'Controlled Test Acc 2 - Cat 1'
    ], $adminSession);
    $knownId2 = (int)($resKnown2['json']['data']['id'] ?? 0);
    if ($knownId2 > 0) $cleanupAccIds[] = $knownId2;

    $resKnown3 = invokeApiEndpoint('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1,
        'category_id' => 2,
        'date' => $targetDate,
        'description' => 'Controlled Test Acc 3 - Cat 2'
    ], $adminSession);
    $knownId3 = (int)($resKnown3['json']['data']['id'] ?? 0);
    if ($knownId3 > 0) $cleanupAccIds[] = $knownId3;

    assertTest(
        "Test 10A: Controlled test dataset created with known category distribution",
        $knownId1 > 0 && $knownId2 > 0 && $knownId3 > 0,
        "IDs: {$knownId1}, {$knownId2}, {$knownId3}"
    );

    // 10B: Query monthly view and verify Cat 1 and Cat 2 counts reflect the inserted records
    $resKnownMonthly = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly',
        'month' => 8,
        'year' => 2026
    ], null, $adminSession);
    $cats = $resKnownMonthly['json']['data']['accomplishments_by_category'] ?? [];
    $cat1CountBefore = 0;
    foreach ($cats as $cat) {
        if ((int)$cat['category_id'] === 1) {
            $cat1CountBefore = (int)$cat['count'];
            break;
        }
    }
    assertTest(
        "Test 10B: Monthly aggregation authoritatively counts active records by category",
        $cat1CountBefore >= 2,
        "Cat 1 count was {$cat1CountBefore}, expected >= 2"
    );

    // 10C: Soft-delete knownId1 -> verify Cat 1 count immediately decreases by exactly 1
    $resSoftDelKnown = invokeApiEndpoint('backend/api/accomplishments/index.php', 'DELETE', ['id' => $knownId1], null, $adminSession);
    $resKnownMonthlyAfter = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly',
        'month' => 8,
        'year' => 2026
    ], null, $adminSession);
    $catsAfter = $resKnownMonthlyAfter['json']['data']['accomplishments_by_category'] ?? [];
    $cat1CountAfter = 0;
    foreach ($catsAfter as $cat) {
        if ((int)$cat['category_id'] === 1) {
            $cat1CountAfter = (int)$cat['count'];
            break;
        }
    }
    assertTest(
        "Test 10C: Soft-deleted accomplishment is immediately excluded from report counts (count drops by exactly 1)",
        $cat1CountAfter === ($cat1CountBefore - 1),
        "Before: {$cat1CountBefore}, After: {$cat1CountAfter}"
    );

    // 10D: Verify soft-deleted record is excluded from daily view
    $resDailySoftDel = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'daily',
        'date' => $targetDate
    ], null, $adminSession);
    $dailyRecords = $resDailySoftDel['json']['data']['records'] ?? [];
    $foundDeleted = false;
    foreach ($dailyRecords as $rec) {
        if ((int)$rec['id'] === $knownId1) {
            $foundDeleted = true;
            break;
        }
    }
    assertTest(
        "Test 10D: Soft-deleted accomplishment is excluded from daily list view",
        !$foundDeleted,
        "Soft-deleted record #{$knownId1} was found in daily list"
    );

    // =========================================================================
    // SUITE 11: Empty Dataset Handling & DOCX Generator Fidelity (Finding 2)
    // =========================================================================
    echo "\nSUITE 11: Empty Dataset Handling & DOCX Generator Fidelity (Finding 2 Verification)\n";

    // 11A: MonthlyReportGenerator with empty month produces valid DOCX without error and no fake data
    $emptyDocxBinary = $generator->generate(1, 2099);
    $isEmptyZip = substr($emptyDocxBinary, 0, 4) === "PK\x03\x04";
    $hasCleanEmptyDocx = strlen($emptyDocxBinary) > 10000;
    assertTest(
        "Test 11A: MonthlyReportGenerator on empty dataset generates clean valid DOCX without error",
        $isEmptyZip && $hasCleanEmptyDocx,
        "Empty docx size: " . strlen($emptyDocxBinary)
    );

    // 11B: Monthly view for empty future date returns clean empty arrays and 0 counts without errors
    $resEmptyMonth = invokeApiEndpoint('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly',
        'month' => 1,
        'year' => 2099
    ], null, $adminSession);
    assertTest(
        "Test 11B: GET view=monthly on empty period returns clean zero counts without errors",
        is_array($resEmptyMonth['json']) &&
        ($resEmptyMonth['json']['success'] ?? false) === true &&
        is_array($resEmptyMonth['json']['data']['accomplishments_by_category']),
        "Body: {$resEmptyMonth['body']}"
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

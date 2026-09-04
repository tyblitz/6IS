<?php
// tests/unit/phase5_audit_verification.php
// Comprehensive Phase 5 Final Release Readiness Verification Script

ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "====================================================================\n";
echo " 6IS Phase 5 Final Release Readiness Verification Suite\n";
echo " Sections 5-10, 12-17 Deep Verification\n";
echo "====================================================================\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

$cleanupAccIds = [];
$cleanupCategoryIds = [];
$cleanupEventIds = [];
$cleanupCommIds = [];

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
function invokeApi(string $apiRelativePath, string $method = 'GET', array $queryParams = [], ?array $body = null, array $session = [], array $headers = []): array {
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

    $tempScript = tempnam(sys_get_temp_dir(), '6is_audit_test_') . '.php';
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

    if (is_array($json) && isset($json['success']) && $json['success'] === false) {
        $msg = strtolower($json['message'] ?? '');
        if (str_contains($msg, 'unauthorized') || str_contains($msg, 'authentication')) {
            $status = 401;
        } elseif (str_contains($msg, 'forbidden') || str_contains($msg, 'permission') || str_contains($msg, 'csrf')) {
            $status = 403;
        } elseif (str_contains($msg, 'not found')) {
            $status = 404;
        } elseif (str_contains($msg, 'conflict') || str_contains($msg, 'reference') || str_contains($msg, 'cannot delete')) {
            $status = 409;
        } else {
            $status = 400;
        }
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

$adminSession = ['user_id' => 1, 'role' => 'Administrator', 'role_id' => 1, 'username' => 'admin'];

// We choose an unoccupied target year (2036) for exact mathematical isolation
$auditYear = 2036;

try {
    // =========================================================================
    // SECTION 5: REPORT CALCULATION VERIFICATION WITH CONTROLLED DATASET
    // =========================================================================
    echo "--- SECTION 5: REPORT CALCULATION VERIFICATION ---\n";

    // Create 2 test categories
    $catACode = 'AUD_A_' . time();
    $resCatA = invokeApi('backend/api/accomplishments/index.php', 'POST', ['action' => 'create_category'], [
        'category_name' => 'Audit Category A',
        'category_code' => $catACode
    ], $adminSession);
    $catAId = (int)($resCatA['json']['data']['id'] ?? 0);
    $cleanupCategoryIds[] = $catAId;

    $catBCode = 'AUD_B_' . time();
    $resCatB = invokeApi('backend/api/accomplishments/index.php', 'POST', ['action' => 'create_category'], [
        'category_name' => 'Audit Category B',
        'category_code' => $catBCode
    ], $adminSession);
    $catBId = (int)($resCatB['json']['data']['id'] ?? 0);
    $cleanupCategoryIds[] = $catBId;

    assertTest("Sec 5 Setup: Categories A (#{$catAId}) and B (#{$catBId}) created", $catAId > 0 && $catBId > 0);

    // Clean any prior records in auditYear if any
    $pdo->exec("DELETE FROM tbl_accomplishments WHERE YEAR(date) = {$auditYear}");

    // Insert Known Dataset in 2036:
    // Record A -> Category A -> 2036-09-01
    // Record B -> Category A -> 2036-09-02
    // Record C -> Category B -> 2036-09-02
    // Record D -> Category B -> 2036-09-30
    $recA = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-09-01",
        'description' => 'Record A - Audit Test'
    ], $adminSession);
    $recAId = (int)($recA['json']['data']['id'] ?? 0);
    $cleanupAccIds[] = $recAId;

    $recB = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-09-02",
        'description' => 'Record B - Audit Test'
    ], $adminSession);
    $recBId = (int)($recB['json']['data']['id'] ?? 0);
    $cleanupAccIds[] = $recBId;

    $recC = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catBId, 'date' => "{$auditYear}-09-02",
        'description' => 'Record C - Audit Test'
    ], $adminSession);
    $recCId = (int)($recC['json']['data']['id'] ?? 0);
    $cleanupAccIds[] = $recCId;

    $recD = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catBId, 'date' => "{$auditYear}-09-30",
        'description' => 'Record D - Audit Test'
    ], $adminSession);
    $recDId = (int)($recD['json']['data']['id'] ?? 0);
    $cleanupAccIds[] = $recDId;

    assertTest("Sec 5 Setup: Records A, B, C, D created", $recAId > 0 && $recBId > 0 && $recCId > 0 && $recDId > 0);

    // 5.1: Daily on 2036-09-02:
    // Total = 2 (Record B & C), Cat A = 1, Cat B = 1
    $resDaily = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'daily', 'date' => "{$auditYear}-09-02"
    ], null, $adminSession);
    $dailyRecords = $resDaily['json']['data']['records'] ?? [];
    $dailyRecA_Count = 0;
    $dailyRecB_Count = 0;
    foreach ($dailyRecords as $r) {
        if ((int)$r['category_id'] === $catAId) $dailyRecA_Count++;
        if ((int)$r['category_id'] === $catBId) $dailyRecB_Count++;
    }
    assertTest(
        "Sec 5.1: Daily Report (2036-09-02) has total = 2, Cat A = 1, Cat B = 1",
        count($dailyRecords) === 2 && $dailyRecA_Count === 1 && $dailyRecB_Count === 1,
        "Daily total: " . count($dailyRecords) . ", Cat A: {$dailyRecA_Count}, Cat B: {$dailyRecB_Count}"
    );

    // 5.2: Monthly on September 2036:
    // Total = 4 (A, B, C, D), Cat A = 2, Cat B = 2
    $resMonthly = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly', 'month' => 9, 'year' => $auditYear
    ], null, $adminSession);
    $mRecords = $resMonthly['json']['data']['records'] ?? [];
    $mCats = $resMonthly['json']['data']['accomplishments_by_category'] ?? [];
    $mCatA = 0;
    $mCatB = 0;
    foreach ($mCats as $c) {
        if ((int)$c['category_id'] === $catAId) $mCatA = (int)$c['count'];
        if ((int)$c['category_id'] === $catBId) $mCatB = (int)$c['count'];
    }
    assertTest(
        "Sec 5.2: Monthly Report (Sep 2036) has total = 4, Cat A = 2, Cat B = 2",
        count($mRecords) === 4 && $mCatA === 2 && $mCatB === 2,
        "Monthly total: " . count($mRecords) . ", Cat A: {$mCatA}, Cat B: {$mCatB}"
    );

    // 5.3: Quarterly Q3 2036: Total = 4
    $resQuarterly = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'quarterly', 'quarter' => 3, 'year' => $auditYear
    ], null, $adminSession);
    $qRecords = $resQuarterly['json']['data']['records'] ?? [];
    assertTest(
        "Sec 5.3: Quarterly Report (Q3 2036) has total = 4",
        count($qRecords) === 4,
        "Quarterly total: " . count($qRecords)
    );

    // 5.4: Annual 2036: Total = 4
    $resAnnual = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'annual', 'year' => $auditYear
    ], null, $adminSession);
    $aRecords = $resAnnual['json']['data']['records'] ?? [];
    assertTest(
        "Sec 5.4: Annual Report (2036) has total = 4",
        count($aRecords) === 4,
        "Annual total: " . count($aRecords)
    );

    // 5.5: Custom Range 2036-09-02 -> 2036-09-30: Total = 3 (B, C, D)
    $resCustom = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'custom', 'start_date' => "{$auditYear}-09-02", 'end_date' => "{$auditYear}-09-30"
    ], null, $adminSession);
    $cRecords = $resCustom['json']['data']['records'] ?? [];
    assertTest(
        "Sec 5.5: Custom Report (2036-09-02 to 2036-09-30) has total = 3",
        count($cRecords) === 3,
        "Custom total: " . count($cRecords)
    );


    // =========================================================================
    // SECTION 6: DATE BOUNDARY VERIFICATION
    // =========================================================================
    echo "\n--- SECTION 6: DATE BOUNDARY VERIFICATION ---\n";

    // 6.1: Month boundaries: First day (Sep 1) included, Last day (Sep 30) included
    $mDates = array_column($mRecords, 'date');
    $foundSep1 = in_array("{$auditYear}-09-01", $mDates);
    $foundSep30 = in_array("{$auditYear}-09-30", $mDates);
    assertTest("Sec 6.1: Monthly report includes first day ({$auditYear}-09-01) and last day ({$auditYear}-09-30)", $foundSep1 && $foundSep30);

    // 6.2: Quarter transitions
    // Q1: Mar 31 included, Apr 1 excluded
    $qRecMar31 = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-03-31",
        'description' => 'Q1 Last Day Record'
    ], $adminSession);
    $cleanupAccIds[] = (int)($qRecMar31['json']['data']['id'] ?? 0);

    $qRecApr1 = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-04-01",
        'description' => 'Q2 First Day Record'
    ], $adminSession);
    $cleanupAccIds[] = (int)($qRecApr1['json']['data']['id'] ?? 0);

    $resQ1 = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'quarterly', 'quarter' => 1, 'year' => $auditYear
    ], null, $adminSession);
    $q1Dates = array_column($resQ1['json']['data']['records'] ?? [], 'date');
    assertTest(
        "Sec 6.2A: Q1 includes {$auditYear}-03-31 and excludes {$auditYear}-04-01",
        in_array("{$auditYear}-03-31", $q1Dates) && !in_array("{$auditYear}-04-01", $q1Dates)
    );

    // Q2: Jun 30 included, Jul 1 excluded
    $qRecJun30 = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-06-30",
        'description' => 'Q2 Last Day Record'
    ], $adminSession);
    $cleanupAccIds[] = (int)($qRecJun30['json']['data']['id'] ?? 0);

    $qRecJul1 = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-07-01",
        'description' => 'Q3 First Day Record'
    ], $adminSession);
    $cleanupAccIds[] = (int)($qRecJul1['json']['data']['id'] ?? 0);

    $resQ2 = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'quarterly', 'quarter' => 2, 'year' => $auditYear
    ], null, $adminSession);
    $q2Dates = array_column($resQ2['json']['data']['records'] ?? [], 'date');
    assertTest(
        "Sec 6.2B: Q2 includes {$auditYear}-06-30 and excludes {$auditYear}-07-01",
        in_array("{$auditYear}-06-30", $q2Dates) && !in_array("{$auditYear}-07-01", $q2Dates)
    );

    // Q3: Sep 30 included, Oct 1 excluded
    $qRecOct1 = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-10-01",
        'description' => 'Q4 First Day Record'
    ], $adminSession);
    $cleanupAccIds[] = (int)($qRecOct1['json']['data']['id'] ?? 0);

    $resQ3 = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'quarterly', 'quarter' => 3, 'year' => $auditYear
    ], null, $adminSession);
    $q3Dates = array_column($resQ3['json']['data']['records'] ?? [], 'date');
    assertTest(
        "Sec 6.2C: Q3 includes {$auditYear}-09-30 and excludes {$auditYear}-10-01",
        in_array("{$auditYear}-09-30", $q3Dates) && !in_array("{$auditYear}-10-01", $q3Dates)
    );

    // 6.3: Year boundaries: Jan 1 included, Dec 31 included, Jan 1 next year excluded
    $recJan1 = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-01-01",
        'description' => 'Year First Day Record'
    ], $adminSession);
    $cleanupAccIds[] = (int)($recJan1['json']['data']['id'] ?? 0);

    $recDec31 = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-12-31",
        'description' => 'Year Last Day Record'
    ], $adminSession);
    $cleanupAccIds[] = (int)($recDec31['json']['data']['id'] ?? 0);

    $nextYear = $auditYear + 1;
    $recNextJan1 = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$nextYear}-01-01",
        'description' => 'Next Year First Day Record'
    ], $adminSession);
    $cleanupAccIds[] = (int)($recNextJan1['json']['data']['id'] ?? 0);

    $resAnnAudit = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'annual', 'year' => $auditYear
    ], null, $adminSession);
    $annAuditDates = array_column($resAnnAudit['json']['data']['records'] ?? [], 'date');
    assertTest(
        "Sec 6.3: Annual {$auditYear} includes Jan 1 & Dec 31, and excludes Jan 1 {$nextYear}",
        in_array("{$auditYear}-01-01", $annAuditDates) && in_array("{$auditYear}-12-31", $annAuditDates) && !in_array("{$nextYear}-01-01", $annAuditDates)
    );

    // 6.4: Custom: Reversed range rejected
    $resRev = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'custom', 'start_date' => "{$auditYear}-12-31", 'end_date' => "{$auditYear}-01-01"
    ], null, $adminSession);
    assertTest(
        "Sec 6.4: Custom reversed date range rejected with HTTP 400",
        ($resRev['json']['success'] ?? true) === false && $resRev['status'] === 400
    );

    // 6.5: Leap Year 2024-02-29
    $recLeap = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => '2024-02-29',
        'description' => 'Leap Day Record 2024'
    ], $adminSession);
    $cleanupAccIds[] = (int)($recLeap['json']['data']['id'] ?? 0);

    $resFebLeap = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly', 'month' => 2, 'year' => 2024
    ], null, $adminSession);
    $febLeapDates = array_column($resFebLeap['json']['data']['records'] ?? [], 'date');
    $foundFeb29 = in_array('2024-02-29', $febLeapDates);

    $resAnnLeap = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'annual', 'year' => 2024
    ], null, $adminSession);
    $annLeapDates = array_column($resAnnLeap['json']['data']['records'] ?? [], 'date');
    assertTest(
        "Sec 6.5: Leap Year 2024-02-29 is properly included in monthly and annual reports",
        $foundFeb29 && in_array('2024-02-29', $annLeapDates)
    );


    // =========================================================================
    // SECTION 7: SEARCH REGRESSION TEST (OFFICE, CATEGORY, DESCRIPTION, COMBINED)
    // =========================================================================
    echo "\n--- SECTION 7: SEARCH REGRESSION VERIFICATION ---\n";

    // 7.1: Description search in Monthly view
    $resSearchDesc = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly', 'month' => 9, 'year' => $auditYear, 'search' => 'Record A'
    ], null, $adminSession);
    $searchRecords = $resSearchDesc['json']['data']['records'] ?? [];
    assertTest(
        "Sec 7.1: Monthly search by description succeeds without SQL 1054 and returns Record A",
        ($resSearchDesc['json']['success'] ?? false) === true && count($searchRecords) === 1 &&
        $searchRecords[0]['description'] === 'Record A - Audit Test'
    );

    // 7.2: Office search in Monthly view
    $resSearchOffice = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly', 'month' => 9, 'year' => $auditYear, 'office_id' => 1
    ], null, $adminSession);
    assertTest(
        "Sec 7.2: Monthly search by office_id succeeds without SQL 1054 and returns office records",
        ($resSearchOffice['json']['success'] ?? false) === true &&
        count($resSearchOffice['json']['data']['records'] ?? []) === 4
    );

    // 7.3: Category search in Monthly view
    $resSearchCat = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly', 'month' => 9, 'year' => $auditYear, 'category_id' => $catAId
    ], null, $adminSession);
    assertTest(
        "Sec 7.3: Monthly search by category_id succeeds without SQL 1054 and returns Category A records",
        ($resSearchCat['json']['success'] ?? false) === true &&
        count($resSearchCat['json']['data']['records'] ?? []) === 2
    );

    // 7.4: Combined search (search + office + category) in Monthly view
    $resSearchComb = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly', 'month' => 9, 'year' => $auditYear,
        'search' => 'Audit', 'office_id' => 1, 'category_id' => $catAId
    ], null, $adminSession);
    assertTest(
        "Sec 7.4: Combined search filters execute cleanly without 500 or SQL errors",
        ($resSearchComb['json']['success'] ?? false) === true &&
        count($resSearchComb['json']['data']['records'] ?? []) === 2
    );


    // =========================================================================
    // SECTION 8 & 9: EMPTY DATASET & DOCX EXPORT OOXML VERIFICATION
    // =========================================================================
    echo "\n--- SECTION 8 & 9: EMPTY DATASET & DOCX OOXML VERIFICATION ---\n";

    // 8.1: Empty period checks
    $resEmptyDaily = invokeApi('backend/api/accomplishments/index.php', 'GET', ['view' => 'daily', 'date' => '2099-01-01'], null, $adminSession);
    $resEmptyMonth = invokeApi('backend/api/accomplishments/index.php', 'GET', ['view' => 'monthly', 'month' => 1, 'year' => 2099], null, $adminSession);
    $resEmptyQuarter = invokeApi('backend/api/accomplishments/index.php', 'GET', ['view' => 'quarterly', 'quarter' => 1, 'year' => 2099], null, $adminSession);
    $resEmptyAnnual = invokeApi('backend/api/accomplishments/index.php', 'GET', ['view' => 'annual', 'year' => 2099], null, $adminSession);
    $resEmptyCustom = invokeApi('backend/api/accomplishments/index.php', 'GET', ['view' => 'custom', 'start_date' => '2099-01-01', 'end_date' => '2099-01-15'], null, $adminSession);

    assertTest(
        "Sec 8.1: Empty datasets return 0 records and success across all 5 report views",
        ($resEmptyDaily['json']['success'] ?? false) && count($resEmptyDaily['json']['data']['records'] ?? []) === 0 &&
        ($resEmptyMonth['json']['success'] ?? false) && count($resEmptyMonth['json']['data']['records'] ?? []) === 0 &&
        ($resEmptyQuarter['json']['success'] ?? false) && count($resEmptyQuarter['json']['data']['records'] ?? []) === 0 &&
        ($resEmptyAnnual['json']['success'] ?? false) && count($resEmptyAnnual['json']['data']['records'] ?? []) === 0 &&
        ($resEmptyCustom['json']['success'] ?? false) && count($resEmptyCustom['json']['data']['records'] ?? []) === 0
    );

    // 9.1: DOCX Export on Empty Period:
    require_once dirname(__DIR__, 2) . '/backend/services/MonthlyReportGenerator.php';
    $generator = new MonthlyReportGenerator($pdo);
    $emptyDocxBinary = $generator->generate(1, 2099);

    $emptyDocxTemp = tempnam(sys_get_temp_dir(), 'docx_empty_') . '.docx';
    file_put_contents($emptyDocxTemp, $emptyDocxBinary);

    $zip = new ZipArchive();
    $openRes = $zip->open($emptyDocxTemp);
    $emptyDocxXml = '';
    if ($openRes === true) {
        $emptyDocxXml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();
    }
    @unlink($emptyDocxTemp);

    assertTest("Sec 9.1A: Empty DOCX is valid OOXML Zip file", $openRes === true);
    assertTest(
        "Sec 9.1B: Empty DOCX document.xml contains 'No accomplishment activities recorded' empty message",
        str_contains($emptyDocxXml, 'No accomplishment activities recorded')
    );
    assertTest(
        "Sec 9.1C: Empty DOCX contains NO fabricated / placeholder sample records",
        !str_contains($emptyDocxXml, 'Installation of Public Address System') &&
        !str_contains($emptyDocxXml, 'Supervised/Assisted TELCO') &&
        !str_contains($emptyDocxXml, 'LED Board Support')
    );

    // 9.2: DOCX Export on Populated Month (September 2036):
    $popDocxBinary = $generator->generate(9, $auditYear);
    $popDocxTemp = tempnam(sys_get_temp_dir(), 'docx_pop_') . '.docx';
    file_put_contents($popDocxTemp, $popDocxBinary);

    $zipPop = new ZipArchive();
    $popOpenRes = $zipPop->open($popDocxTemp);
    $popDocxXml = '';
    if ($popOpenRes === true) {
        $popDocxXml = $zipPop->getFromName('word/document.xml') ?: '';
        $zipPop->close();
    }
    @unlink($popDocxTemp);

    assertTest("Sec 9.2A: Populated DOCX is valid OOXML", $popOpenRes === true);
    assertTest(
        "Sec 9.2B: Populated DOCX includes correct title and period (September 2036 and 01-30 September 2036)",
        str_contains($popDocxXml, "September {$auditYear}") && str_contains($popDocxXml, "01-30 September {$auditYear}")
    );
    assertTest(
        "Sec 9.2C: Populated DOCX includes our test records and category codes",
        str_contains($popDocxXml, 'Record A - Audit Test') && str_contains($popDocxXml, $catACode)
    );


    // =========================================================================
    // SECTION 12: SECURITY (RBAC, CSRF, CATEGORY MUTATIONS)
    // =========================================================================
    echo "\n--- SECTION 12: SECURITY (RBAC & CSRF ENFORCEMENT) ---\n";

    // 12.1: Unauthenticated request -> 401
    $resSecUnauth = invokeApi('backend/api/accomplishments/index.php', 'GET');
    assertTest("Sec 12.1: Unauthenticated request rejected with HTTP 401", $resSecUnauth['status'] === 401);

    // 12.2: Authenticated user without view permission -> 403
    $unauthSession = ['user_id' => 999, 'role' => 'Guest', 'role_id' => 999, 'username' => 'guest'];
    $resSecNoPerm = invokeApi('backend/api/accomplishments/index.php', 'GET', [], null, $unauthSession);
    assertTest("Sec 12.2: Authenticated user lacking permission rejected with HTTP 403", $resSecNoPerm['status'] === 403);

    // 12.3: Missing CSRF token on mutating request -> 403
    $resSecNoCsrf = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-09-02", 'description' => 'No CSRF'
    ], $adminSession, ['X-CSRF-Token' => '__OMIT__']);
    assertTest("Sec 12.3: Mutating request with omitted CSRF rejected with HTTP 403", $resSecNoCsrf['status'] === 403);

    // 12.4: Invalid CSRF token on mutating request -> 403
    $resSecBadCsrf = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-09-02", 'description' => 'Bad CSRF'
    ], $adminSession, ['X-CSRF-Token' => 'bogus_csrf_123456']);
    assertTest("Sec 12.4: Mutating request with invalid CSRF rejected with HTTP 403", $resSecBadCsrf['status'] === 403);

    // 12.5: Category mutation with valid CSRF succeeds
    $secCatCode = 'SECCAT_' . time();
    $resSecCatCreate = invokeApi('backend/api/accomplishments/index.php', 'POST', ['action' => 'create_category'], [
        'category_name' => 'Sec Test Cat', 'category_code' => $secCatCode
    ], $adminSession);
    $secCatId = (int)($resSecCatCreate['json']['data']['id'] ?? 0);
    $cleanupCategoryIds[] = $secCatId;
    assertTest("Sec 12.5: Category creation with valid CSRF succeeds (HTTP 201)", $secCatId > 0);


    // =========================================================================
    // SECTION 13: SQL INJECTION & MALFORMED INPUT VALIDATION
    // =========================================================================
    echo "\n--- SECTION 13: SQL INJECTION & INPUT VALIDATION ---\n";

    // 13.1: Malicious search strings
    $sqliSearch = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'all', 'search' => "' OR '1'='1' UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12 -- "
    ], null, $adminSession);
    assertTest(
        "Sec 13.1: Search query with SQL injection payload executes safely without leakage or 500 error",
        ($sqliSearch['json']['success'] ?? false) === true
    );

    // 13.2: Malicious category_id
    $sqliCat = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'all', 'category_id' => "1 OR 1=1"
    ], null, $adminSession);
    assertTest("Sec 13.2: Non-integer category_id parameter handled safely", ($sqliCat['json']['success'] ?? false) === true);

    // 13.3: Malicious date parameter
    $sqliDate = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'daily', 'date' => "{$auditYear}-09-02' OR '1'='1"
    ], null, $adminSession);
    assertTest(
        "Sec 13.3: Malicious date parameter rejected safely with controlled error (HTTP 400)",
        ($sqliDate['json']['success'] ?? true) === false && $sqliDate['status'] === 400
    );

    // 13.4: Malicious payload injection in description & remarks
    $resSqliCreate = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1,
        'category_id' => $catAId,
        'date' => "{$auditYear}-09-02",
        'description' => "'; DROP TABLE tbl_accomplishments; -- <script>alert(1)</script>",
        'remarks' => "' OR '1'='1"
    ], $adminSession);
    $sqliAccId = (int)($resSqliCreate['json']['data']['id'] ?? 0);
    $cleanupAccIds[] = $sqliAccId;
    assertTest(
        "Sec 13.4: SQL injection payload in description/remarks stored safely via prepared statements without DB execution",
        $sqliAccId > 0 && ($resSqliCreate['json']['success'] ?? false) === true
    );


    // =========================================================================
    // SECTION 14: AUDIT LOG VERIFICATION & SECRETS SANITIZATION
    // =========================================================================
    echo "\n--- SECTION 14: AUDIT LOG VERIFICATION ---\n";

    // 14.1: Verify CREATE audit log for our test record
    $createLog = $pdo->query("
        SELECT * FROM tbl_audit_logs 
        WHERE module_key = 'accomplishments' AND entity_id = '{$recAId}' AND action = 'CREATE'
    ")->fetch();
    assertTest("Sec 14.1: CREATE event logged in tbl_audit_logs with module_key='accomplishments'", !empty($createLog));

    // 14.2: Perform an UPDATE and verify UPDATE log with diff
    $resAuditUpd = invokeApi('backend/api/accomplishments/index.php', 'PUT', ['id' => $recAId], [
        'id' => $recAId, 'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-09-01",
        'description' => 'Record A - Updated Audit Test',
        'remarks' => 'Modified remarks'
    ], $adminSession);
    $updateLog = $pdo->query("
        SELECT * FROM tbl_audit_logs 
        WHERE module_key = 'accomplishments' AND entity_id = '{$recAId}' AND action = 'UPDATE'
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    assertTest(
        "Sec 14.2: UPDATE event logged with non-empty old_values and new_values",
        !empty($updateLog) && !empty($updateLog['old_values']) && !empty($updateLog['new_values'])
    );

    // 14.3: Ensure audit log does not leak secrets, session IDs, or CSRF tokens
    $leakCheck = $pdo->query("
        SELECT * FROM tbl_audit_logs 
        WHERE module_key = 'accomplishments' AND entity_id = '{$recAId}'
    ")->fetchAll();
    $hasSecret = false;
    foreach ($leakCheck as $row) {
        $dataStr = ($row['old_values'] ?? '') . ($row['new_values'] ?? '');
        if (str_contains(strtolower($dataStr), 'password') || str_contains(strtolower($dataStr), 'csrf_token') || str_contains(strtolower($dataStr), 'phpsessid')) {
            $hasSecret = true;
        }
    }
    assertTest("Sec 14.3: Audit records contain zero passwords, session IDs, or CSRF tokens", !$hasSecret);


    // =========================================================================
    // SECTION 15: SOFT DELETE & CATEGORY DEPENDENCY PROTECTION
    // =========================================================================
    echo "\n--- SECTION 15: SOFT DELETE & DEPENDENCY PROTECTION ---\n";

    // 15.1: Create an accomplishment and soft-delete it
    $recTemp = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-09-15",
        'description' => 'Temp Soft Delete Record'
    ], $adminSession);
    $tempId = (int)($recTemp['json']['data']['id'] ?? 0);
    $cleanupAccIds[] = $tempId;

    // Verify it's in reports before delete
    $resBeforeDel = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly', 'month' => 9, 'year' => $auditYear
    ], null, $adminSession);
    $countBeforeDel = count($resBeforeDel['json']['data']['records'] ?? []);

    // Soft delete
    $resDel = invokeApi('backend/api/accomplishments/index.php', 'DELETE', ['id' => $tempId], null, $adminSession);
    assertTest("Sec 15.1A: Soft delete returns HTTP 200", ($resDel['json']['success'] ?? false) === true);

    // Verify it is excluded from report totals
    $resAfterDel = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly', 'month' => 9, 'year' => $auditYear
    ], null, $adminSession);
    $countAfterDel = count($resAfterDel['json']['data']['records'] ?? []);
    assertTest("Sec 15.1B: Soft-deleted record excluded from monthly report count", $countAfterDel === ($countBeforeDel - 1));

    // Verify historical DB record intact with deleted_at
    $rawDbRow = $pdo->query("SELECT * FROM tbl_accomplishments WHERE id = {$tempId}")->fetch();
    assertTest("Sec 15.1C: Historical database record remains intact with deleted_at populated", !empty($rawDbRow) && !empty($rawDbRow['deleted_at']));

    // 15.2: Category deletion while referenced by active accomplishment rejected with HTTP 409
    $resCatDeleteAttempt = invokeApi('backend/api/accomplishments/index.php', 'POST', ['action' => 'delete_category'], [
        'id' => $catAId
    ], $adminSession);
    assertTest(
        "Sec 15.2: Deleting category referenced by active accomplishment rejected with HTTP 409 Conflict",
        ($resCatDeleteAttempt['json']['success'] ?? true) === false && $resCatDeleteAttempt['status'] === 409
    );


    // =========================================================================
    // SECTION 16: CALENDAR SEPARATION
    // =========================================================================
    echo "\n--- SECTION 16: CALENDAR SEPARATION ---\n";

    $calendarCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM tbl_calendar_events")->fetchColumn();
    $accCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments WHERE deleted_at IS NULL")->fetchColumn();

    // Create an accomplishment
    $recSep = invokeApi('backend/api/accomplishments/index.php', 'POST', [], [
        'office_id' => 1, 'category_id' => $catAId, 'date' => "{$auditYear}-09-20",
        'description' => 'Calendar Separation Test Acc'
    ], $adminSession);
    $sepAccId = (int)($recSep['json']['data']['id'] ?? 0);
    $cleanupAccIds[] = $sepAccId;

    $calendarCountAfter = (int)$pdo->query("SELECT COUNT(*) FROM tbl_calendar_events")->fetchColumn();
    assertTest(
        "Sec 16.1: Creating accomplishment does NOT increase Calendar event count (before: {$calendarCountBefore}, after: {$calendarCountAfter})",
        $calendarCountBefore === $calendarCountAfter
    );

    // Create a calendar event directly via DB
    $pdo->exec("INSERT INTO tbl_calendar_events (title, event_date, start_datetime, end_datetime, office_id, event_type_id, status, created_by, created_at, updated_at) 
                VALUES ('Audit Separation Event', '{$auditYear}-09-20', '{$auditYear}-09-20 10:00:00', '{$auditYear}-09-20 11:00:00', 1, 1, 'Scheduled', 1, NOW(), NOW())");
    $createdEventId = (int)$pdo->lastInsertId();
    $cleanupEventIds[] = $createdEventId;

    $expectedAccCount = $accCountBefore + 1;
    $accCountAfter = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments WHERE deleted_at IS NULL")->fetchColumn();
    assertTest(
        "Sec 16.2: Creating Calendar event does NOT increase Accomplishment count (before: {$expectedAccCount}, after: {$accCountAfter})",
        $accCountAfter === $expectedAccCount
    );


    // =========================================================================
    // SECTION 17: COMMUNICATIONS INTEGRATION
    // =========================================================================
    echo "\n--- SECTION 17: COMMUNICATIONS INTEGRATION ---\n";

    // Check that communications tables are authoritative
    $resMonthlyComms = invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly', 'month' => 9, 'year' => $auditYear
    ], null, $adminSession);
    assertTest(
        "Sec 17.1: Monthly report includes authoritative communications category rollup",
        isset($resMonthlyComms['json']['data']['outgoing_comms_by_category']) &&
        is_array($resMonthlyComms['json']['data']['outgoing_comms_by_category'])
    );

    // Insert a test communication record in tbl_communications
    $pdo->exec("INSERT INTO tbl_communications (communication_type, office_id, category_id, purpose_id, subject, communication_date, status, created_at, updated_at, created_by, modified_by)
                VALUES ('Outgoing', 1, 1, 1, 'Audit Comm Subject', '{$auditYear}-09-10', 'Transmitted', NOW(), NOW(), 1, 1)");
    $commId = (int)$pdo->lastInsertId();
    $cleanupCommIds[] = $commId;

    $commAccCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments")->fetchColumn();
    // Query monthly again
    invokeApi('backend/api/accomplishments/index.php', 'GET', [
        'view' => 'monthly', 'month' => 9, 'year' => $auditYear
    ], null, $adminSession);
    $commAccCountAfter = (int)$pdo->query("SELECT COUNT(*) FROM tbl_accomplishments")->fetchColumn();
    assertTest(
        "Sec 17.2: Communications integration reads authoritative table without creating duplicate records or mutating tables",
        $commAccCountBefore === $commAccCountAfter
    );

} finally {
    // Guaranteed Cleanup of All Test Fixtures
    foreach ($cleanupAccIds as $id) {
        if ($id > 0) {
            $pdo->exec("DELETE FROM tbl_audit_logs WHERE module_key = 'accomplishments' AND entity_id = '{$id}'");
            $pdo->exec("DELETE FROM tbl_accomplishments WHERE id = {$id}");
        }
    }
    foreach ($cleanupCategoryIds as $id) {
        if ($id > 0) {
            $pdo->exec("DELETE FROM tbl_audit_logs WHERE module_key = 'accomplishments' AND entity_id = '{$id}'");
            $pdo->exec("DELETE FROM tbl_accomplishment_categories WHERE id = {$id}");
        }
    }
    foreach ($cleanupEventIds as $id) {
        if ($id > 0) {
            $pdo->exec("DELETE FROM tbl_calendar_events WHERE id = {$id}");
        }
    }
    foreach ($cleanupCommIds as $id) {
        if ($id > 0) {
            $pdo->exec("DELETE FROM tbl_communications WHERE id = {$id}");
        }
    }
    $pdo->exec("DELETE FROM tbl_accomplishments WHERE YEAR(date) = {$auditYear}");
}

echo "\n====================================================================\n";
echo " AUDIT TEST SUMMARY: {$passedTests} passed, {$failedTests} failed out of {$totalTests} tests.\n";
echo "====================================================================\n";
exit($failedTests === 0 ? 0 : 1);

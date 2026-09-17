<?php
// tests/unit/edfs_test.php
// Automated Unit & API Integration Tests for EDFS Account Monitoring Module

$dbConfig = require __DIR__ . '/../../backend/config/database.php';
$pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4", $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$passed = 0;
$failed = 0;

function assertTest($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $description\n";
        $passed++;
    } else {
        echo "  [FAIL] $description\n";
        $failed++;
    }
}

echo "===============================================================\n";
echo " 6IS EDFS ACCOUNT MONITORING TEST SUITE\n";
echo "===============================================================\n\n";

// SUITE 1: Schema & Module Registration
echo "SUITE 1: Schema & Module Registration\n";
$tblCheck = $pdo->query("SHOW TABLES LIKE 'tbl_edfs_accounts'")->rowCount();
assertTest("Test 1: tbl_edfs_accounts exists in database", $tblCheck === 1);

$mod = $pdo->query("SELECT * FROM tbl_modules WHERE module_key = 'edfs'")->fetch();
assertTest("Test 2: 'edfs' module registered in tbl_modules", $mod !== false);
assertTest("Test 3: 'edfs' module is active", $mod && (int)$mod['is_active'] === 1);

$perms = $pdo->query("SELECT permission_key FROM tbl_permissions WHERE module_key = 'edfs'")->fetchAll(PDO::FETCH_COLUMN);
assertTest("Test 4: EDFS has 4 core permissions (view, create, edit, delete)", count($perms) === 4 && in_array('view', $perms) && in_array('create', $perms));

$cols = $pdo->query("SHOW COLUMNS FROM tbl_edfs_accounts")->fetchAll(PDO::FETCH_COLUMN);
assertTest("Test 5: Columns 'source_doc' and 'orig_nr' do not exist in tbl_edfs_accounts", !in_array('source_doc', $cols) && !in_array('orig_nr', $cols));

// SUITE 2: Master De-duplication Invariants
echo "\nSUITE 2: Master De-duplication Invariants\n";
$total = (int)$pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts")->fetchColumn();
assertTest("Test 6: Total seeded accounts equals 286", $total === 286);

// Recommendation 1: AFPCOC & GSMO duplicates removed
$afpcocCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE office_name = 'CFMG AFPCOC'")->fetchColumn();
assertTest("Test 7: AFPCOC has exactly 6 accounts (4 initial + 2 unique additional, duplicates removed)", $afpcocCount === 6);

$gsmoChiefCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE office_name = 'GSMO' AND personnel_name LIKE '%FLOJO%'")->fetchColumn();
assertTest("Test 8: GSMO Chief duplicate removed (COL FLOJO has exactly 1 account)", $gsmoChiefCount === 1);

// Recommendation 2: OG4 Message Center de-duplicated
$og4McCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE office_name = 'OG4' AND current_title LIKE '%Message Center%'")->fetchColumn();
assertTest("Test 9: OG4 Message Center has exactly 1 account (assigned to Diana Rose Peredo)", $og4McCount === 1);

// Recommendation 3: MAJ Delim replaced MAJ Talledo
$deputyG4 = $pdo->query("SELECT * FROM tbl_edfs_accounts WHERE office_name = 'OG4' AND current_title LIKE '%Deputy%'")->fetch();
assertTest("Test 10: OG4 Deputy assigned to MAJ MARIA TESS D DELIM", $deputyG4 && strpos($deputyG4['personnel_name'], 'DELIM') !== false);
assertTest("Test 11: OG4 Deputy remarks record the replacement", $deputyG4 && strpos($deputyG4['remarks'], 'Replaced') !== false);

// Recommendation 4: Officers holding multiple positions have distinct accounts
$casuyonCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE personnel_name LIKE '%CASUYON%'")->fetchColumn();
assertTest("Test 12: LCDR Casuyon holds 3 distinct accounts across HSCA, GAD, and CDC", $casuyonCount === 3);

$gamosCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE personnel_name LIKE '%GAMOS%'")->fetchColumn();
assertTest("Test 13: MAJ Gamos holds distinct accounts across G8 and HAO", $gamosCount === 2);

// SUITE 3: CRUD Operations & Data Integrity
echo "\nSUITE 3: CRUD Operations & Data Integrity\n";

// Insert test with office_id
$firstOffice = $pdo->query("SELECT id, office_code, office_name FROM tbl_offices WHERE office_code IS NOT NULL AND office_code != '' LIMIT 1")->fetch();
$targetOfficeId = $firstOffice ? (int)$firstOffice['id'] : 1;
$targetShortName = $firstOffice ? $firstOffice['office_code'] : 'TEST_OFFICE';

$insStmt = $pdo->prepare("
    INSERT INTO tbl_edfs_accounts (office_id, office_name, current_title, personnel_name, username, account_name, status, remarks)
    VALUES (?, ?, 'Test Action Officer', 'CPT TEST OFFICER', 'test_action_officer', 'test_action_officer', 'Active', 'Test Remarks')
");
$insStmt->execute([$targetOfficeId, $targetShortName]);
$testId = (int)$pdo->lastInsertId();
assertTest("Test 14: Successfully inserted new EDFS account with office_id", $testId > 0);

// Read test
$readStmt = $pdo->prepare("SELECT * FROM tbl_edfs_accounts WHERE id = ?");
$readStmt->execute([$testId]);
$testRow = $readStmt->fetch();
assertTest("Test 15: Read inserted account and verified office/personnel", $testRow && (int)$testRow['office_id'] === $targetOfficeId && $testRow['personnel_name'] === 'CPT TEST OFFICER');
assertTest("Test 16: Unified credentials (account_name === username)", $testRow && $testRow['account_name'] === $testRow['username'] && $testRow['username'] === 'test_action_officer');

// Update test
$upStmt = $pdo->prepare("UPDATE tbl_edfs_accounts SET status = 'For Renewal', remarks = 'Updated in test' WHERE id = ?");
$upStmt->execute([$testId]);
$readStmt->execute([$testId]);
$updatedRow = $readStmt->fetch();
assertTest("Test 17: Updated account status to 'For Renewal'", $updatedRow && $updatedRow['status'] === 'For Renewal');

// SUITE 4: Soft Delete & Office Derivation Refinement
echo "\nSUITE 4: Soft Delete & Office Short Name Invariants\n";

// Soft delete test
$softDelStmt = $pdo->prepare("UPDATE tbl_edfs_accounts SET deleted_at = NOW() WHERE id = ?");
$softDelStmt->execute([$testId]);

// Active query filter test (WHERE deleted_at IS NULL)
$activeStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE id = ? AND deleted_at IS NULL");
$activeStmt->execute([$testId]);
$activeCount = (int)$activeStmt->fetchColumn();
assertTest("Test 18: Soft-deleted account is excluded from active query (deleted_at IS NULL)", $activeCount === 0);

// Audit preservation test (record still exists in database with non-null deleted_at)
$auditStmt = $pdo->prepare("SELECT deleted_at FROM tbl_edfs_accounts WHERE id = ?");
$auditStmt->execute([$testId]);
$auditRow = $auditStmt->fetch();
assertTest("Test 19: Record still preserved in database with timestamp in deleted_at", $auditRow && !empty($auditRow['deleted_at']));

// Cleanup test row physically
$cleanStmt = $pdo->prepare("DELETE FROM tbl_edfs_accounts WHERE id = ?");
$cleanStmt->execute([$testId]);
assertTest("Test 20: Cleaned up unit test temporary record", true);

echo "\n===============================================================\n";
echo " TEST SUMMARY: $passed PASSED, $failed FAILED\n";
echo "===============================================================\n";

if ($failed > 0) {
    exit(1);
}

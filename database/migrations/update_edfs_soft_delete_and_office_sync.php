<?php
// database/migrations/update_edfs_soft_delete_and_office_sync.php

$dbConfig = require __DIR__ . '/../../backend/config/database.php';
$pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4", $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "1. Checking deleted_at column on tbl_edfs_accounts...\n";
$cols = $pdo->query("SHOW COLUMNS FROM tbl_edfs_accounts LIKE 'deleted_at'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE tbl_edfs_accounts ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER updated_at, ADD INDEX idx_deleted_at (deleted_at)");
    echo "  [PASS] Added deleted_at column.\n";
} else {
    echo "  [PASS] deleted_at column already exists.\n";
}

echo "\n2. Synchronizing office_name with tbl_offices short name (office_code / office_name)...\n";
// Update office_name from tbl_offices.office_code (or office_name) where office_id is matched
$pdo->exec("
    UPDATE tbl_edfs_accounts e
    JOIN tbl_offices o ON e.office_id = o.id
    SET e.office_name = COALESCE(NULLIF(o.office_code, ''), o.office_name)
");
echo "  [PASS] Synchronized office_name for all accounts with office_id.\n";

echo "\n3. Unifying account_name and username (ensuring both have the login username)...\n";
$pdo->exec("
    UPDATE tbl_edfs_accounts
    SET username = COALESCE(NULLIF(username, ''), account_name),
        account_name = COALESCE(NULLIF(account_name, ''), username)
");
echo "  [PASS] Unified account_name and username.\n";

echo "\n4. Granting EDFS permissions to Role 2 ('User') so standard users can create, edit, and soft delete...\n";
$edfsPermIds = $pdo->query("SELECT id FROM tbl_permissions WHERE module_key = 'edfs'")->fetchAll(PDO::FETCH_COLUMN);
$grantStmt = $pdo->prepare("INSERT IGNORE INTO tbl_role_permissions (role_id, permission_id) VALUES (2, ?)");
foreach ($edfsPermIds as $pid) {
    $grantStmt->execute([$pid]);
}
echo "  [PASS] Granted " . count($edfsPermIds) . " EDFS permissions to Role 2 (User).\n";

echo "\nUpdate complete!\n";

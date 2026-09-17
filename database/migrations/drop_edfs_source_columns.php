<?php
// database/migrations/drop_edfs_source_columns.php
// Drop source_doc and orig_nr columns from tbl_edfs_accounts

$dbConfig = require __DIR__ . '/../../backend/config/database.php';
$pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4", $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "Dropping source_doc and orig_nr from tbl_edfs_accounts...\n";

$cols = $pdo->query("SHOW COLUMNS FROM tbl_edfs_accounts")->fetchAll(PDO::FETCH_COLUMN);

if (in_array('source_doc', $cols)) {
    $pdo->exec("ALTER TABLE tbl_edfs_accounts DROP COLUMN source_doc");
    echo "  [PASS] Dropped column 'source_doc'.\n";
} else {
    echo "  [INFO] Column 'source_doc' does not exist.\n";
}

if (in_array('orig_nr', $cols)) {
    $pdo->exec("ALTER TABLE tbl_edfs_accounts DROP COLUMN orig_nr");
    echo "  [PASS] Dropped column 'orig_nr'.\n";
} else {
    echo "  [INFO] Column 'orig_nr' does not exist.\n";
}

echo "Migration complete.\n";

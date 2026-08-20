<?php
$dbConfig = require __DIR__ . '/backend/config/database.php';
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);

try {
    $pdo->exec("ALTER TABLE tbl_communications ADD COLUMN image_url VARCHAR(500) NULL AFTER status");
    echo "Column image_url added successfully.\n";
} catch (PDOException $e) {
    echo "Migration info: " . $e->getMessage() . "\n";
}

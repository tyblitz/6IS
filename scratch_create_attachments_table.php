<?php
$dbConfig = require __DIR__ . '/backend/config/database.php';
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_communication_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            communication_id INT NOT NULL,
            image_url VARCHAR(500) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (communication_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_communication_attachments created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}

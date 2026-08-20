<?php
$dbConfig = require __DIR__ . '/backend/config/database.php';
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
$stmt = $pdo->query("DESCRIBE tbl_communications");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

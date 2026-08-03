<?php
// database/setup_db.php
// Script to create database db_ict_system and execute migration tables & seed data

$host = 'localhost';
$username = 'root';
$password = '';
$dbName = 'db_ict_system';

echo "1. Connecting to MySQL server on {$host}...\n";

try {
    $pdo = new PDO("mysql:host={$host}", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "SUCCESS: Connected to MySQL server.\n";

    echo "2. Creating database '{$dbName}' if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "SUCCESS: Database '{$dbName}' created or already exists.\n";

    echo "3. Connecting to database '{$dbName}'...\n";
    $pdo->exec("USE `{$dbName}`;");

    $migrationFile = __DIR__ . '/migrations/create_accomplishments_tables.sql';
    if (!file_exists($migrationFile)) {
        die("ERROR: Migration file missing: {$migrationFile}\n");
    }

    echo "4. Executing SQL migration script...\n";
    $sql = file_get_contents($migrationFile);
    $pdo->exec($sql);
    echo "SUCCESS: Tables and sample seed data created successfully in '{$dbName}'.\n";

    echo "\nSummary of tables created in '{$dbName}':\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        echo " - {$table}: {$count} rows\n";
    }

    echo "\nSETUP COMPLETED SUCCESSFULLY!\n";

} catch (PDOException $e) {
    die("ERROR: Database operation failed: " . $e->getMessage() . "\n");
}

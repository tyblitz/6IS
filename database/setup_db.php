<?php
// database/setup_db.php
// Script to create database db_ict_system and execute migration tables & seed data for Accomplishments & Communications

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

    $migrationFiles = [
        __DIR__ . '/migrations/create_accomplishments_tables.sql',
        __DIR__ . '/migrations/create_communications_tables.sql'
    ];

    echo "4. Executing SQL migration scripts...\n";
    foreach ($migrationFiles as $file) {
        if (!file_exists($file)) {
            echo "WARNING: Migration file missing: {$file}\n";
            continue;
        }
        $sql = file_get_contents($file);
        $pdo->exec($sql);
        echo "SUCCESS: Executed migration script: " . basename($file) . "\n";
    }

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

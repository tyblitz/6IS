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

<<<<<<< HEAD
    $migrations = [
        __DIR__ . '/migrations/create_accomplishments_tables.sql',
        __DIR__ . '/migrations/create_communications_tables.sql'
    ];

    echo "4. Executing SQL migration scripts...\n";
    foreach ($migrations as $migrationFile) {
        if (!file_exists($migrationFile)) {
            die("ERROR: Migration file missing: {$migrationFile}\n");
        }
        $filename = basename($migrationFile);
        echo " - Executing {$filename}...\n";
        $sql = file_get_contents($migrationFile);
        $pdo->exec($sql);
    }

    // Ensure tbl_offices has columns office_abbv, office_category, is_active if created previously
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `tbl_offices`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('office_abbv', $cols)) {
            $pdo->exec("ALTER TABLE `tbl_offices` ADD COLUMN `office_abbv` VARCHAR(20) NULL AFTER `office_code`;");
            $pdo->exec("UPDATE `tbl_offices` SET `office_abbv` = `office_code` WHERE `office_abbv` IS NULL;");
        }
        if (!in_array('office_category', $cols)) {
            $pdo->exec("ALTER TABLE `tbl_offices` ADD COLUMN `office_category` ENUM('Staff', 'Special Staff', 'Group', 'Others') NOT NULL DEFAULT 'Others' AFTER `office_abbv`;");
        }
        if (!in_array('is_active', $cols)) {
            $pdo->exec("ALTER TABLE `tbl_offices` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `office_category`;");
        }
    } catch (Exception $e) {
        // Table created with new schema directly
    }

    // Ensure tbl_communications has communication_type column if created previously
    try {
        $commCols = $pdo->query("SHOW COLUMNS FROM `tbl_communications`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('communication_type', $commCols)) {
            $pdo->exec("ALTER TABLE `tbl_communications` ADD COLUMN `communication_type` ENUM('Incoming', 'Outgoing') NOT NULL DEFAULT 'Incoming' AFTER `id`;");
        }
    } catch (Exception $e) {
        // Table created with new schema directly
    }

    echo "SUCCESS: Tables and sample seed data created successfully in '{$dbName}'.\n";
=======
    $migrationFiles = [
        __DIR__ . '/migrations/create_accomplishments_tables.sql',
        __DIR__ . '/migrations/create_communications_tables.sql',
        __DIR__ . '/migrations/create_auth_tables.sql',
        __DIR__ . '/migrations/create_inventory_tables.sql'
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

    // Ensure tbl_users has necessary columns (is_active, role ENUM) if created previously
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `tbl_users`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('is_active', $cols)) {
            $pdo->exec("ALTER TABLE `tbl_users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`;");
            echo "SUCCESS: Added 'is_active' column to 'tbl_users'.\n";
        }
        // Ensure username is UNIQUE
        try {
            $pdo->exec("ALTER TABLE `tbl_users` ADD UNIQUE INDEX `idx_username` (`username`);");
        } catch (Exception $ex) {
            // Index already exists
        }
    } catch (Exception $e) {
        // Table created freshly with new schema
    }

    // Seed Development Authentication Accounts securely with BCRYPT hashes
    echo "5. Seeding development authentication accounts (Admin01, User01)...\n";

    $devAccounts = [
        [
            'username' => 'Admin01',
            'full_name' => 'System Administrator',
            'password' => 'adminpassword01',
            'role' => 'Administrator'
        ],
        [
            'username' => 'User01',
            'full_name' => 'Standard User',
            'password' => 'userpassword01',
            'role' => 'User'
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO tbl_users (username, full_name, password, role, is_active, created_at, updated_at)
        VALUES (:username, :full_name, :password, :role, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name),
            password = VALUES(password),
            role = VALUES(role),
            is_active = 1,
            deleted_at = NULL,
            updated_at = NOW()
    ");

    foreach ($devAccounts as $acc) {
        $hash = password_hash($acc['password'], PASSWORD_BCRYPT);
        $stmt->execute([
            ':username' => $acc['username'],
            ':full_name' => $acc['full_name'],
            ':password' => $hash,
            ':role' => $acc['role']
        ]);
        echo " - Seeded user '{$acc['username']}' with role '{$acc['role']}'\n";
    }
>>>>>>> module/login

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

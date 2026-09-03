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

    function columnExists(PDO $pdo, string $table, string $column): bool {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
            return (bool)$stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }

    $migrationFiles = [
        __DIR__ . '/migrations/create_accomplishments_tables.sql',
        __DIR__ . '/migrations/create_communications_tables.sql',
        __DIR__ . '/migrations/create_auth_tables.sql',
        __DIR__ . '/migrations/create_inventory_tables.sql',
        __DIR__ . '/migrations/alter_inventory_to_extensible_equipment.sql',
        __DIR__ . '/migrations/create_calendar_tables.sql',
        __DIR__ . '/migrations/create_modules_table.sql',
        __DIR__ . '/migrations/create_roles_tables.sql',
        __DIR__ . '/migrations/add_role_id_to_users.sql',
        __DIR__ . '/migrations/create_organization_and_offices_tables.sql',
        __DIR__ . '/migrations/seed_roles_and_permissions.sql'
    ];

    echo "4. Executing SQL migration scripts...\n";
    foreach ($migrationFiles as $file) {
        if (!file_exists($file)) {
            echo "WARNING: Migration file missing: {$file}\n";
            continue;
        }
        $sql = file_get_contents($file);
        try {
            $pdo->exec($sql);
            echo "SUCCESS: Executed migration script: " . basename($file) . "\n";
        } catch (Exception $e) {
            echo "NOTE on " . basename($file) . ": " . $e->getMessage() . "\n";
        }
    }

    // Ensure tbl_calendar_event_types table exists & is seeded
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tbl_calendar_event_types` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `type_name` VARCHAR(100) NOT NULL,
              `type_code` VARCHAR(20) NOT NULL,
              `color` VARCHAR(20) NULL DEFAULT '#2563EB',
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              `deleted_at` DATETIME NULL,
              UNIQUE KEY `uk_type_code` (`type_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $pdo->exec("
            INSERT INTO `tbl_calendar_event_types` (`id`, `type_name`, `type_code`, `color`, `created_at`, `updated_at`)
            VALUES 
              (1, 'Public Address System', 'PAS', '#16A34A', NOW(), NOW()),
              (2, 'Conference', 'CONF', '#2563EB', NOW(), NOW()),
              (3, 'Video Teleconference', 'VTC', '#9333EA', NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
              `type_name` = VALUES(`type_name`),
              `color` = VALUES(`color`);
        ");
        echo "SUCCESS: Created or verified tbl_calendar_event_types table.\n";
    } catch (Exception $e) {
        echo "tbl_calendar_event_types note: " . $e->getMessage() . "\n";
    }

    // Safely update tbl_calendar_events columns (drop description, event_type, priority; change location to office_id)
    try {
        if (!columnExists($pdo, 'tbl_calendar_events', 'start_datetime')) {
            $pdo->exec("ALTER TABLE `tbl_calendar_events` ADD COLUMN `start_datetime` DATETIME NULL AFTER `event_time`;");
        }
        if (!columnExists($pdo, 'tbl_calendar_events', 'end_datetime')) {
            $pdo->exec("ALTER TABLE `tbl_calendar_events` ADD COLUMN `end_datetime` DATETIME NULL AFTER `start_datetime`;");
        }
        if (!columnExists($pdo, 'tbl_calendar_events', 'all_day')) {
            $pdo->exec("ALTER TABLE `tbl_calendar_events` ADD COLUMN `all_day` TINYINT(1) NOT NULL DEFAULT 0 AFTER `end_datetime`;");
        }
        if (!columnExists($pdo, 'tbl_calendar_events', 'office_id')) {
            $pdo->exec("ALTER TABLE `tbl_calendar_events` ADD COLUMN `office_id` INT NULL AFTER `all_day`;");
        }
        if (!columnExists($pdo, 'tbl_calendar_events', 'event_type_id')) {
            $pdo->exec("ALTER TABLE `tbl_calendar_events` ADD COLUMN `event_type_id` INT NULL AFTER `office_id`;");
        }
        if (!columnExists($pdo, 'tbl_calendar_events', 'status')) {
            $pdo->exec("ALTER TABLE `tbl_calendar_events` ADD COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'Scheduled';");
        }

        // Drop unneeded columns if present
        if (columnExists($pdo, 'tbl_calendar_events', 'description')) {
            $pdo->exec("ALTER TABLE `tbl_calendar_events` DROP COLUMN `description`;");
        }
        if (columnExists($pdo, 'tbl_calendar_events', 'event_type')) {
            $pdo->exec("ALTER TABLE `tbl_calendar_events` DROP COLUMN `event_type`;");
        }
        if (columnExists($pdo, 'tbl_calendar_events', 'priority')) {
            $pdo->exec("ALTER TABLE `tbl_calendar_events` DROP COLUMN `priority`;");
        }
        if (columnExists($pdo, 'tbl_calendar_events', 'location')) {
            $pdo->exec("ALTER TABLE `tbl_calendar_events` DROP COLUMN `location`;");
        }

        $pdo->exec("UPDATE `tbl_calendar_events` SET `start_datetime` = CONCAT(`event_date`, ' ', COALESCE(`event_time`, '09:00:00')) WHERE `start_datetime` IS NULL");
        $pdo->exec("UPDATE `tbl_calendar_events` SET `office_id` = 1 WHERE `office_id` IS NULL");
        $pdo->exec("UPDATE `tbl_calendar_events` SET `event_type_id` = 2 WHERE `event_type_id` IS NULL");
        echo "SUCCESS: Updated tbl_calendar_events schema (office_id, event_type_id, status).\n";
    } catch (Exception $e) {
        echo "tbl_calendar_events alter note: " . $e->getMessage() . "\n";
    }

    // Ensure tbl_calendar_event_reschedules table exists
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tbl_calendar_event_reschedules` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `calendar_event_id` INT NOT NULL,
              `previous_start_datetime` DATETIME NULL,
              `previous_end_datetime` DATETIME NULL,
              `new_start_datetime` DATETIME NOT NULL,
              `new_end_datetime` DATETIME NOT NULL,
              `reason` TEXT NULL,
              `changed_by` INT NOT NULL DEFAULT 1,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (`calendar_event_id`) REFERENCES `tbl_calendar_events` (`id`) ON DELETE CASCADE,
              INDEX `idx_reschedule_event` (`calendar_event_id`),
              INDEX `idx_reschedule_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "SUCCESS: Created or verified tbl_calendar_event_reschedules table.\n";
    } catch (Exception $e) {
        echo "tbl_calendar_event_reschedules note: " . $e->getMessage() . "\n";
    }

    // Ensure tbl_calendar_event_status_history table exists
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tbl_calendar_event_status_history` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `calendar_event_id` INT NOT NULL,
              `previous_status` VARCHAR(50) NULL,
              `new_status` VARCHAR(50) NOT NULL,
              `reason` TEXT NULL,
              `changed_by` INT NOT NULL DEFAULT 1,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (`calendar_event_id`) REFERENCES `tbl_calendar_events` (`id`) ON DELETE CASCADE,
              INDEX `idx_status_history_event` (`calendar_event_id`),
              INDEX `idx_status_history_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "SUCCESS: Created or verified tbl_calendar_event_status_history table.\n";
    } catch (Exception $e) {
        echo "tbl_calendar_event_status_history note: " . $e->getMessage() . "\n";
    }

    // Ensure tbl_accomplishments has calendar_event_id column
    try {
        if (!columnExists($pdo, 'tbl_accomplishments', 'calendar_event_id')) {
            $pdo->exec("ALTER TABLE `tbl_accomplishments` ADD COLUMN `calendar_event_id` INT NULL AFTER `office_id`;");
            $pdo->exec("ALTER TABLE `tbl_accomplishments` ADD INDEX `idx_calendar_event_id` (`calendar_event_id`);");
        }
        echo "SUCCESS: Verified tbl_accomplishments calendar_event_id column.\n";
    } catch (Exception $e) {
        echo "tbl_accomplishments calendar_event_id note: " . $e->getMessage() . "\n";
    }

    // Ensure tbl_inventory_equipment has equipment_type_id, equipment_subtype_id, status_id columns
    try {
        if (!columnExists($pdo, 'tbl_inventory_equipment', 'equipment_type_id')) {
            $pdo->exec("ALTER TABLE `tbl_inventory_equipment` ADD COLUMN `equipment_type_id` INT NULL AFTER `office_id`;");
        }
        if (!columnExists($pdo, 'tbl_inventory_equipment', 'equipment_subtype_id')) {
            $pdo->exec("ALTER TABLE `tbl_inventory_equipment` ADD COLUMN `equipment_subtype_id` INT NULL AFTER `equipment_type_id`;");
        }
        if (!columnExists($pdo, 'tbl_inventory_equipment', 'status_id')) {
            $pdo->exec("ALTER TABLE `tbl_inventory_equipment` ADD COLUMN `status_id` INT NULL AFTER `equipment_subtype_id`;");
        }
        $pdo->exec("
            UPDATE tbl_inventory_equipment e
            JOIN tbl_inventory_equipment_subtypes st ON (
                LOWER(e.equipment_type) = LOWER(st.name) 
                OR (e.equipment_type LIKE '%Desktop%' AND st.name = 'Desktop')
                OR (e.equipment_type LIKE '%PA%' AND st.name = 'Public Address System')
                OR (e.equipment_type LIKE '%Public Address%' AND st.name = 'Public Address System')
            )
            SET e.equipment_subtype_id = st.id,
                e.equipment_type_id = st.equipment_type_id;
        ");
        $pdo->exec("UPDATE `tbl_inventory_equipment` SET `equipment_type_id` = 1 WHERE `equipment_type_id` IS NULL;");
        $pdo->exec("UPDATE `tbl_inventory_equipment` SET `equipment_subtype_id` = 1 WHERE `equipment_subtype_id` IS NULL;");
        $pdo->exec("
            UPDATE tbl_inventory_equipment e
            JOIN tbl_inventory_equipment_statuses s ON LOWER(e.status) = LOWER(s.name)
            SET e.status_id = s.id;
        ");
        $pdo->exec("UPDATE `tbl_inventory_equipment` SET `status_id` = 1 WHERE `status_id` IS NULL;");
        echo "SUCCESS: Verified tbl_inventory_equipment extensible architecture columns.\n";
    } catch (Exception $e) {
        echo "tbl_inventory_equipment columns note: " . $e->getMessage() . "\n";
    }

    // Ensure tbl_inventory_jrrs has equipment_subtype_id column
    try {
        if (!columnExists($pdo, 'tbl_inventory_jrrs', 'equipment_subtype_id')) {
            $pdo->exec("ALTER TABLE `tbl_inventory_jrrs` ADD COLUMN `equipment_subtype_id` INT NULL AFTER `id`;");
            $pdo->exec("UPDATE `tbl_inventory_jrrs` j JOIN `tbl_inventory_equipment_subtypes` st ON LOWER(j.equipment_type) = LOWER(st.name) SET j.equipment_subtype_id = st.id;");
            $pdo->exec("UPDATE `tbl_inventory_jrrs` SET `equipment_subtype_id` = 1 WHERE `equipment_subtype_id` IS NULL;");
        }
    } catch (Exception $e) {
        echo "tbl_inventory_jrrs columns note: " . $e->getMessage() . "\n";
    }

    // Verify Module Registry table exists & is seeded
    try {
        $moduleCount = $pdo->query("SELECT COUNT(*) FROM `tbl_modules`")->fetchColumn();
        echo "SUCCESS: Verified tbl_modules registry ({$moduleCount} official modules registered).\n";
    } catch (Exception $e) {
        echo "WARNING: tbl_modules verification note: " . $e->getMessage() . "\n";
    }

    // Seed Development Authentication Accounts securely with BCRYPT hashes
    echo "5. Seeding authentication accounts (Admin01, User01)...\n";

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

    $roleStmt = $pdo->prepare("SELECT id FROM tbl_roles WHERE name = :name LIMIT 1");

    $stmt = $pdo->prepare("
        INSERT INTO tbl_users (username, full_name, password, role, role_id, is_active, created_at, updated_at)
        VALUES (:username, :full_name, :password, :role, :role_id, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name),
            password = VALUES(password),
            role = VALUES(role),
            role_id = VALUES(role_id),
            is_active = 1,
            deleted_at = NULL,
            updated_at = NOW()
    ");

    foreach ($devAccounts as $acc) {
        $roleStmt->execute([':name' => $acc['role']]);
        $roleId = $roleStmt->fetchColumn() ?: null;
        $hash = password_hash($acc['password'], PASSWORD_BCRYPT);
        $stmt->execute([
            ':username' => $acc['username'],
            ':full_name' => $acc['full_name'],
            ':password' => $hash,
            ':role' => $acc['role'],
            ':role_id' => $roleId
        ]);
        echo " - Seeded user '{$acc['username']}' with role '{$acc['role']}' (role_id: {$roleId})\n";
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

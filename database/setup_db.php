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

    $migrationFiles = [
        __DIR__ . '/migrations/create_accomplishments_tables.sql',
        __DIR__ . '/migrations/create_communications_tables.sql',
        __DIR__ . '/migrations/create_auth_tables.sql',
        __DIR__ . '/migrations/create_inventory_tables.sql',
        __DIR__ . '/migrations/alter_inventory_to_extensible_equipment.sql'
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

    // Ensure tbl_offices has necessary columns
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
    } catch (Exception $e) {}

    // Ensure tbl_communications has communication_type and image_url columns
    try {
        $commCols = $pdo->query("SHOW COLUMNS FROM `tbl_communications`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('communication_type', $commCols)) {
            $pdo->exec("ALTER TABLE `tbl_communications` ADD COLUMN `communication_type` ENUM('Incoming', 'Outgoing') NOT NULL DEFAULT 'Incoming' AFTER `id`;");
        }
        if (!in_array('image_url', $commCols)) {
            $pdo->exec("ALTER TABLE `tbl_communications` ADD COLUMN `image_url` VARCHAR(500) NULL AFTER `status`;");
        }
    } catch (Exception $e) {}

    // Ensure tbl_communication_attachments table exists
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tbl_communication_attachments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `communication_id` INT NOT NULL,
                `image_url` VARCHAR(500) NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (`communication_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Exception $e) {}

    // Ensure tbl_users has necessary columns (is_active, role ENUM)
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `tbl_users`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('is_active', $cols)) {
            $pdo->exec("ALTER TABLE `tbl_users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`;");
        }
        try {
            $pdo->exec("ALTER TABLE `tbl_users` ADD UNIQUE INDEX `idx_username` (`username`);");
        } catch (Exception $ex) {}
    } catch (Exception $e) {}

    // Ensure tbl_inventory_equipment has equipment_type_id, equipment_subtype_id, status_id
    try {
        $eqCols = $pdo->query("SHOW COLUMNS FROM `tbl_inventory_equipment`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('equipment_type_id', $eqCols)) {
            $pdo->exec("ALTER TABLE `tbl_inventory_equipment` ADD COLUMN `equipment_type_id` INT NULL AFTER `office_id`;");
        }
        if (!in_array('equipment_subtype_id', $eqCols)) {
            $pdo->exec("ALTER TABLE `tbl_inventory_equipment` ADD COLUMN `equipment_subtype_id` INT NULL AFTER `equipment_type_id`;");
        }
        if (!in_array('status_id', $eqCols)) {
            $pdo->exec("ALTER TABLE `tbl_inventory_equipment` ADD COLUMN `status_id` INT NULL AFTER `equipment_subtype_id`;");
        }

        // Map existing equipment string fields to reference IDs
        $pdo->exec("
            UPDATE `tbl_inventory_equipment` SET
                `equipment_type_id` = CASE
                    WHEN `equipment_type` IN ('Public Address System', 'PAS', 'Mixer', 'Microphone', 'Speaker') THEN 2
                    ELSE 1
                END,
                `equipment_subtype_id` = CASE
                    WHEN `equipment_type` IN ('Desktop Computer', 'Desktop') THEN 1
                    WHEN `equipment_type` = 'Printer' THEN 2
                    WHEN `equipment_type` = 'AVR' THEN 3
                    WHEN `equipment_type` = 'Projector' THEN 4
                    WHEN `equipment_type` = 'LED TV' THEN 5
                    WHEN `equipment_type` = 'Laptop' THEN 6
                    WHEN `equipment_type` = 'Network Switch' THEN 7
                    WHEN `equipment_type` = 'Mixer' THEN 8
                    WHEN `equipment_type` = 'Microphone' THEN 9
                    WHEN `equipment_type` = 'Speaker' THEN 10
                    WHEN `equipment_type` IN ('Public Address System', 'PAS') THEN 11
                    ELSE 1
                END,
                `status_id` = CASE
                    WHEN `status` = 'Serviceable' THEN 1
                    WHEN `status` = 'For Repair' THEN 2
                    WHEN `status` IN ('For Turn-In / Unserviceable', 'For Turn-in') THEN 3
                    ELSE 1
                END
            WHERE `equipment_type_id` IS NULL OR `equipment_subtype_id` IS NULL OR `status_id` IS NULL;
        ");
    } catch (Exception $e) {
        echo "Inventory equipment mapping note: " . $e->getMessage() . "\n";
    }

    // Ensure tbl_inventory_jrrs has equipment_subtype_id column
    try {
        $jrrsCols = $pdo->query("SHOW COLUMNS FROM `tbl_inventory_jrrs`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('equipment_subtype_id', $jrrsCols)) {
            $pdo->exec("ALTER TABLE `tbl_inventory_jrrs` ADD COLUMN `equipment_subtype_id` INT NULL AFTER `id`;");
        }
        $pdo->exec("
            UPDATE `tbl_inventory_jrrs` SET
                `equipment_subtype_id` = CASE
                    WHEN `equipment_type` IN ('Desktop Computer', 'Desktop') THEN 1
                    WHEN `equipment_type` = 'Printer' THEN 2
                    WHEN `equipment_type` = 'AVR' THEN 3
                    WHEN `equipment_type` = 'Projector' THEN 4
                    WHEN `equipment_type` = 'LED TV' THEN 5
                    WHEN `equipment_type` = 'Laptop' THEN 6
                    WHEN `equipment_type` = 'Network Switch' THEN 7
                    WHEN `equipment_type` = 'Mixer' THEN 8
                    WHEN `equipment_type` = 'Microphone' THEN 9
                    WHEN `equipment_type` = 'Speaker' THEN 10
                    WHEN `equipment_type` IN ('Public Address System', 'PAS') THEN 11
                    ELSE 1
                END
            WHERE `equipment_subtype_id` IS NULL;
        ");

        $defaultTargets = [
            1 => 25, 2 => 10, 3 => 5, 4 => 5, 5 => 5,
            6 => 15, 7 => 8, 8 => 3, 9 => 10, 10 => 6, 11 => 5
        ];
        foreach ($defaultTargets as $stId => $target) {
            $pdo->exec("
                INSERT INTO `tbl_inventory_jrrs` (`equipment_subtype_id`, `target_quantity`, `equipment_type`, `created_at`, `updated_at`)
                VALUES ({$stId}, {$target}, (SELECT `name` FROM `tbl_inventory_equipment_subtypes` WHERE `id` = {$stId}), NOW(), NOW())
                ON DUPLICATE KEY UPDATE `equipment_subtype_id` = VALUES(`equipment_subtype_id`);
            ");
        }
    } catch (Exception $e) {
        echo "JRRS subtype mapping note: " . $e->getMessage() . "\n";
    }

    // Ensure tbl_inventory_history has equipment_type_id, equipment_subtype_id, status_id, attributes_json
    try {
        $histCols = $pdo->query("SHOW COLUMNS FROM `tbl_inventory_history`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('equipment_type_id', $histCols)) {
            $pdo->exec("ALTER TABLE `tbl_inventory_history` ADD COLUMN `equipment_type_id` INT NULL AFTER `office_id`;");
        }
        if (!in_array('equipment_subtype_id', $histCols)) {
            $pdo->exec("ALTER TABLE `tbl_inventory_history` ADD COLUMN `equipment_subtype_id` INT NULL AFTER `equipment_type_id`;");
        }
        if (!in_array('status_id', $histCols)) {
            $pdo->exec("ALTER TABLE `tbl_inventory_history` ADD COLUMN `status_id` INT NULL AFTER `equipment_subtype_id`;");
        }
        if (!in_array('attributes_json', $histCols)) {
            $pdo->exec("ALTER TABLE `tbl_inventory_history` ADD COLUMN `attributes_json` JSON NULL AFTER `status`;");
        }

        $pdo->exec("
            UPDATE `tbl_inventory_history` SET
                `equipment_type_id` = CASE
                    WHEN `equipment_type` IN ('Public Address System', 'PAS', 'Mixer', 'Microphone', 'Speaker') THEN 2
                    ELSE 1
                END,
                `equipment_subtype_id` = CASE
                    WHEN `equipment_type` IN ('Desktop Computer', 'Desktop') THEN 1
                    WHEN `equipment_type` = 'Printer' THEN 2
                    WHEN `equipment_type` = 'AVR' THEN 3
                    WHEN `equipment_type` = 'Projector' THEN 4
                    WHEN `equipment_type` = 'LED TV' THEN 5
                    WHEN `equipment_type` = 'Laptop' THEN 6
                    WHEN `equipment_type` = 'Network Switch' THEN 7
                    WHEN `equipment_type` = 'Mixer' THEN 8
                    WHEN `equipment_type` = 'Microphone' THEN 9
                    WHEN `equipment_type` = 'Speaker' THEN 10
                    WHEN `equipment_type` IN ('Public Address System', 'PAS') THEN 11
                    ELSE 1
                END,
                `status_id` = CASE
                    WHEN `status` = 'Serviceable' THEN 1
                    WHEN `status` = 'For Repair' THEN 2
                    WHEN `status` IN ('For Turn-In / Unserviceable', 'For Turn-in') THEN 3
                    ELSE 1
                END
            WHERE `equipment_type_id` IS NULL OR `equipment_subtype_id` IS NULL OR `status_id` IS NULL;
        ");
    } catch (Exception $e) {
        echo "Inventory history mapping note: " . $e->getMessage() . "\n";
    }

    // Seed sample attribute values for initial sample desktops & printers
    try {
        $desktopSamples = [
            1 => ['Processor' => 'Intel Core i7-9700', 'RAM' => '16 GB', 'Storage' => '512 GB SSD', 'OS' => 'Windows 11 Pro'],
            2 => ['Processor' => 'Intel Core i5-10500', 'RAM' => '8 GB', 'Storage' => '256 GB SSD', 'OS' => 'Windows 10 Pro'],
            7 => ['Processor' => 'Intel Core i5-11500', 'RAM' => '16 GB', 'Storage' => '512 GB SSD', 'OS' => 'Windows 11 Pro'],
            10 => ['Processor' => 'Intel Core i5-10400', 'RAM' => '8 GB', 'Storage' => '512 GB SSD', 'OS' => 'Windows 10 Pro'],
            14 => ['Processor' => 'Intel Core i5-11400T', 'RAM' => '16 GB', 'Storage' => '512 GB SSD', 'OS' => 'Windows 11 Pro'],
            17 => ['Processor' => 'Intel Core i3-10100', 'RAM' => '8 GB', 'Storage' => '1 TB HDD', 'OS' => 'Windows 10 Pro']
        ];

        foreach ($desktopSamples as $eqId => $vals) {
            $pdo->exec("INSERT INTO `tbl_inventory_equipment_attribute_values` (`equipment_id`, `attribute_definition_id`, `value_text`, `created_at`, `updated_at`) VALUES ({$eqId}, 1, '{$vals['Processor']}', NOW(), NOW()) ON DUPLICATE KEY UPDATE `value_text` = VALUES(`value_text`);");
            $pdo->exec("INSERT INTO `tbl_inventory_equipment_attribute_values` (`equipment_id`, `attribute_definition_id`, `value_text`, `created_at`, `updated_at`) VALUES ({$eqId}, 2, '{$vals['RAM']}', NOW(), NOW()) ON DUPLICATE KEY UPDATE `value_text` = VALUES(`value_text`);");
            $pdo->exec("INSERT INTO `tbl_inventory_equipment_attribute_values` (`equipment_id`, `attribute_definition_id`, `value_text`, `created_at`, `updated_at`) VALUES ({$eqId}, 3, '{$vals['Storage']}', NOW(), NOW()) ON DUPLICATE KEY UPDATE `value_text` = VALUES(`value_text`);");
            $pdo->exec("INSERT INTO `tbl_inventory_equipment_attribute_values` (`equipment_id`, `attribute_definition_id`, `value_text`, `created_at`, `updated_at`) VALUES ({$eqId}, 4, '{$vals['OS']}', NOW(), NOW()) ON DUPLICATE KEY UPDATE `value_text` = VALUES(`value_text`);");
        }

        $printerSamples = [
            4 => ['Tech' => 'Laser', 'Color' => 'Monochrome', 'Net' => 1],
            8 => ['Tech' => 'Inkjet', 'Color' => 'Color', 'Net' => 1],
            13 => ['Tech' => 'Laser', 'Color' => 'Monochrome', 'Net' => 1],
            19 => ['Tech' => 'Laser', 'Color' => 'Monochrome', 'Net' => 1]
        ];

        foreach ($printerSamples as $eqId => $vals) {
            $pdo->exec("INSERT INTO `tbl_inventory_equipment_attribute_values` (`equipment_id`, `attribute_definition_id`, `value_text`, `created_at`, `updated_at`) VALUES ({$eqId}, 5, '{$vals['Tech']}', NOW(), NOW()) ON DUPLICATE KEY UPDATE `value_text` = VALUES(`value_text`);");
            $pdo->exec("INSERT INTO `tbl_inventory_equipment_attribute_values` (`equipment_id`, `attribute_definition_id`, `value_text`, `created_at`, `updated_at`) VALUES ({$eqId}, 6, '{$vals['Color']}', NOW(), NOW()) ON DUPLICATE KEY UPDATE `value_text` = VALUES(`value_text`);");
            $pdo->exec("INSERT INTO `tbl_inventory_equipment_attribute_values` (`equipment_id`, `attribute_definition_id`, `value_boolean`, `created_at`, `updated_at`) VALUES ({$eqId}, 7, {$vals['Net']}, NOW(), NOW()) ON DUPLICATE KEY UPDATE `value_boolean` = VALUES(`value_boolean`);");
        }
    } catch (Exception $e) {
        echo "Sample attribute values note: " . $e->getMessage() . "\n";
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

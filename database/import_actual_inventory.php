<?php
/**
 * Master Inventory Importer
 * Imports 707 validated equipment records from Actual Inventory.xlsx
 * into tbl_inventory_equipment with hyphenated Property Numbers and 'N/A' Serial Numbers.
 */

require_once __DIR__ . '/../backend/helpers/inventory_importer_helper.php';
$config = require __DIR__ . '/../backend/config/database.php';

$pdo = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4", $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "===============================================================\n";
echo " 6IS MASTER INVENTORY IMPORTER (Actual Inventory.xlsx)\n";
echo "===============================================================\n\n";

$jsonPath = __DIR__ . '/../scratch_actual_parsed_records.json';
$fallbackJson = 'C:/Users/Tyron/.gemini/antigravity-ide/brain/664a5691-b9d8-433f-a15c-1805588139d0/scratch/actual_parsed_records.json';

if (file_exists($jsonPath)) {
    $recordsJson = file_get_contents($jsonPath);
} elseif (file_exists($fallbackJson)) {
    $recordsJson = file_get_contents($fallbackJson);
} else {
    die("Parsed records JSON not found. Please run scratch/parse_actual_with_hyphens.py first.\n");
}

$records = json_decode($recordsJson, true);
$totalRecords = count($records);
echo "Loaded {$totalRecords} equipment records to import.\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("TRUNCATE TABLE tbl_inventory_equipment_attribute_values");
$pdo->exec("TRUNCATE TABLE tbl_inventory_history");
$pdo->exec("TRUNCATE TABLE tbl_inventory_equipment");

$pdo->beginTransaction();
try {
    $insertStmt = $pdo->prepare("
        INSERT INTO tbl_inventory_equipment 
        (office_id, equipment_type_id, equipment_subtype_id, status_id, equipment_type, description, serial_number, property_number, date_acquired, status, created_by, modified_by, created_at, updated_at) 
        VALUES 
        (:office_id, :type_id, :subtype_id, :status_id, :equipment_type, :description, :serial_number, :property_number, :date_acquired, :status, 1, 1, NOW(), NOW())
    ");

    $ictCount = 0;
    $commCount = 0;

    foreach ($records as $r) {
        $finalSerial = resolveSerialNumber($r['serial_number']);
        $finalProp = $r['property_number'];

        $insertStmt->execute([
            ':office_id'        => $r['office_id'],
            ':type_id'          => $r['type_id'],
            ':subtype_id'       => $r['subtype_id'],
            ':status_id'        => $r['status_id'],
            ':equipment_type'   => $r['equipment_type'],
            ':description'      => $r['description'],
            ':serial_number'    => $finalSerial,
            ':property_number'  => $finalProp,
            ':date_acquired'    => $r['date_acquired'],
            ':status'           => $r['status']
        ]);

        if ($r['type_id'] == 1) {
            $ictCount++;
        } else {
            $commCount++;
        }
    }

    echo "Successfully inserted {$totalRecords} items:\n";
    echo "  - ICT Equipment (ITE Inventory): {$ictCount}\n";
    echo "  - Communications Equipment (CE Inventory): {$commCount}\n\n";

    // Generate snapshot for 2026-09
    $currentYM = '2026-09';
    $today = date('Y-m-d');
    $pdo->exec("
        INSERT INTO tbl_inventory_history 
        (`year_month`, equipment_id, office_id, equipment_type_id, equipment_subtype_id, status_id, equipment_type, description, serial_number, property_number, date_acquired, status, snapshot_date, created_at, updated_at)
        SELECT 
            '{$currentYM}', id, office_id, equipment_type_id, equipment_subtype_id, status_id, equipment_type, description, serial_number, property_number, date_acquired, status, '{$today}', NOW(), NOW()
        FROM tbl_inventory_equipment
    ");

    $histCount = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = '{$currentYM}'")->fetchColumn();
    echo "Created {$histCount} historical snapshot records for period '{$currentYM}'.\n";

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "\nDatabase import completed and committed successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "ERROR: Import failed: " . $e->getMessage() . "\n";
    exit(1);
}

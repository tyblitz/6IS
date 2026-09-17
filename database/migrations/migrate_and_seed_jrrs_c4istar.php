<?php
/**
 * Migration & Seed Script: JRRS Section III. C4ISTAR Baseline & Multi-Subtype Mapping
 *
 * Populates tbl_inventory_jrrs with the 43 standardized C4ISTAR line items (Total TOE: 840)
 * from 6IS Docs Format/JRRS Monthly Report.xls and creates the many-to-one subtype rollup mapping.
 */

$config = require __DIR__ . '/../../backend/config/database.php';

try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4", $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "===============================================================\n";
echo " JRRS C4ISTAR MIGRATION & SEEDING (43 Standardized Line Items)\n";
echo "===============================================================\n\n";

// Helper to check if column exists
function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
    $stmt->execute([':column' => $column]);
    return (bool)$stmt->fetch();
}

// 1. Alter tbl_inventory_jrrs if columns are missing
echo "Step 1: Updating schema for tbl_inventory_jrrs...\n";

if (!columnExists($pdo, 'tbl_inventory_jrrs', 'category')) {
    $pdo->exec("ALTER TABLE `tbl_inventory_jrrs` ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'OTHER C2 SYSTEM' AFTER `id`");
    echo "  Added column 'category'\n";
}

if (!columnExists($pdo, 'tbl_inventory_jrrs', 'sub_category')) {
    $pdo->exec("ALTER TABLE `tbl_inventory_jrrs` ADD COLUMN `sub_category` VARCHAR(100) NULL AFTER `category`");
    echo "  Added column 'sub_category'\n";
}

if (!columnExists($pdo, 'tbl_inventory_jrrs', 'sort_order')) {
    $pdo->exec("ALTER TABLE `tbl_inventory_jrrs` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `sub_category`");
    echo "  Added column 'sort_order'\n";
}

// Modify equipment_type column to allow longer names and drop strict UNIQUE if needed
try {
    $pdo->exec("ALTER TABLE `tbl_inventory_jrrs` MODIFY COLUMN `equipment_type` VARCHAR(150) NOT NULL");
} catch (PDOException $e) {
    // Already modified or compatible
}

// 2. Create tbl_inventory_jrrs_subtypes junction table
echo "Step 2: Creating tbl_inventory_jrrs_subtypes junction table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `tbl_inventory_jrrs_subtypes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `jrrs_id` INT NOT NULL,
        `equipment_subtype_id` INT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_jrrs_subtype` (`jrrs_id`, `equipment_subtype_id`),
        INDEX `idx_jrrs` (`jrrs_id`),
        INDEX `idx_subtype` (`equipment_subtype_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  tbl_inventory_jrrs_subtypes ready.\n";

// 3. Define the 43 standardized C4ISTAR rows
$c4istarItems = [
    // A. COMMUNICATIONS (TOE: 252)
    [
        'category' => 'COMMUNICATIONS',
        'sub_category' => '1. FIXED COMMUNICATION',
        'nomenclature' => 'Mobile Cellular Phone',
        'target_quantity' => 14,
        'subtypes' => [27] // Mobile / Cellular Phone
    ],
    [
        'category' => 'COMMUNICATIONS',
        'sub_category' => '1. FIXED COMMUNICATION',
        'nomenclature' => 'Telephone Set',
        'target_quantity' => 117,
        'subtypes' => [22] // Telephone Set
    ],
    [
        'category' => 'COMMUNICATIONS',
        'sub_category' => '1. FIXED COMMUNICATION',
        'nomenclature' => 'Sattelite Phone',
        'target_quantity' => 0,
        'subtypes' => []
    ],
    [
        'category' => 'COMMUNICATIONS',
        'sub_category' => '1. BASE and MOBILE (Military Specs)',
        'nomenclature' => 'a. UHF Channel Motorolla Radio',
        'target_quantity' => 95,
        'subtypes' => []
    ],
    [
        'category' => 'COMMUNICATIONS',
        'sub_category' => '1. BASE and MOBILE (Military Specs)',
        'nomenclature' => 'b. VHF Harris Radio',
        'target_quantity' => 1,
        'subtypes' => []
    ],
    [
        'category' => 'COMMUNICATIONS',
        'sub_category' => '2. BASE and MOBILE (Commercial Specs)',
        'nomenclature' => 'a. VHF-FM High Band Hand Held Radio',
        'target_quantity' => 24,
        'subtypes' => [21] // Handheld Radio
    ],
    [
        'category' => 'COMMUNICATIONS',
        'sub_category' => '2. BASE and MOBILE (Commercial Specs)',
        'nomenclature' => 'b. VHF-FM Base Radio High Band',
        'target_quantity' => 1,
        'subtypes' => [30] // Base / Mobile Radio
    ],

    // B. CYBER SECURITY (TOE: 12)
    [
        'category' => 'CYBER SECURITY',
        'sub_category' => null,
        'nomenclature' => '1. Crypto',
        'target_quantity' => 0,
        'subtypes' => []
    ],
    [
        'category' => 'CYBER SECURITY',
        'sub_category' => null,
        'nomenclature' => '2. Intrussion Detection System',
        'target_quantity' => 6,
        'subtypes' => []
    ],
    [
        'category' => 'CYBER SECURITY',
        'sub_category' => null,
        'nomenclature' => '3. Intrussion Protection System',
        'target_quantity' => 6,
        'subtypes' => []
    ],

    // C. CENSOR INTEGRATION SYSTEM (TOE: 5)
    [
        'category' => 'CENSOR INTEGRATION SYSTEM',
        'sub_category' => null,
        'nomenclature' => '1. Bar Code Reador',
        'target_quantity' => 1,
        'subtypes' => []
    ],
    [
        'category' => 'CENSOR INTEGRATION SYSTEM',
        'sub_category' => null,
        'nomenclature' => '2. Global Positioning System',
        'target_quantity' => 4,
        'subtypes' => []
    ],

    // D. INFORMATION MANAGEMENT SYSTEM (TOE: 51)
    [
        'category' => 'INFORMATION MANAGEMENT SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Application Software',
        'target_quantity' => 51,
        'subtypes' => []
    ],

    // E. OTHER C2 SYSTEM (TOE: 520)
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Audio Amplifier',
        'target_quantity' => 2,
        'subtypes' => [26] // Audio Amplifier
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Audio Recorder/Voice Recorder',
        'target_quantity' => 5,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Biometric Access System',
        'target_quantity' => 2,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'CCTV',
        'target_quantity' => 31,
        'subtypes' => [15, 32] // CCTV Camera, Surveillance / Spy Camera
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Computer Notebook/Tablet',
        'target_quantity' => 1,
        'subtypes' => [18] // Tablet
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Conference Microphone with Speaker Set',
        'target_quantity' => 2,
        'subtypes' => [24] // Conference System
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Digital Camera',
        'target_quantity' => 23,
        'subtypes' => [29] // Video / Digital Camera
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Desktop Computer Set',
        'target_quantity' => 184,
        'subtypes' => [1] // Desktop
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Document Reader',
        'target_quantity' => 1,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Facsimile Machine',
        'target_quantity' => 3,
        'subtypes' => [33] // Facsimile (Fax) Machine
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Laptop Computer',
        'target_quantity' => 54,
        'subtypes' => [6] // Laptop
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Led Wall/HD Monitor',
        'target_quantity' => 1,
        'subtypes' => [14, 5, 23] // Monitor, LED TV, Television / Video Display
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Local Area Network System',
        'target_quantity' => 35,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Microphone/Lapel Microphone/Wireless Microphone',
        'target_quantity' => 2,
        'subtypes' => [9] // Microphone
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Multimedia Projector',
        'target_quantity' => 30,
        'subtypes' => [4, 25] // Projector, Multi-Media Projector
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Photocopier Machine',
        'target_quantity' => 13,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Photo Printer Machine',
        'target_quantity' => 1,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Public Address System',
        'target_quantity' => 7,
        'subtypes' => [11] // Public Address System
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Printer',
        'target_quantity' => 46,
        'subtypes' => [2] // Printer
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Radio Scanner',
        'target_quantity' => 1,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Router/Modem/Switch',
        'target_quantity' => 20,
        'subtypes' => [16, 7] // Router, Network Switch
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Scanner Document',
        'target_quantity' => 16,
        'subtypes' => [17] // Scanner
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Server',
        'target_quantity' => 5,
        'subtypes' => [19] // Server
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Speaker Set',
        'target_quantity' => 9,
        'subtypes' => [10] // Speaker
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Stereo Component',
        'target_quantity' => 1,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Tape Recorder',
        'target_quantity' => 4,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Video Camcorder HD',
        'target_quantity' => 1,
        'subtypes' => [28] // Body Camera
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Video Camera',
        'target_quantity' => 7,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Wifi Set',
        'target_quantity' => 6,
        'subtypes' => []
    ],
    [
        'category' => 'OTHER C2 SYSTEM',
        'sub_category' => null,
        'nomenclature' => 'Wireless Acess Point (WAP)',
        'target_quantity' => 7,
        'subtypes' => []
    ]
];

echo "Step 3: Seeding 43 C4ISTAR rows and subtype mappings...\n";

$pdo->beginTransaction();
try {
    // Clear existing JRRS and mappings to ensure clean state
    $pdo->exec("DELETE FROM `tbl_inventory_jrrs_subtypes`");
    $pdo->exec("DELETE FROM `tbl_inventory_jrrs`");

    $insertJrrsStmt = $pdo->prepare("
        INSERT INTO `tbl_inventory_jrrs` 
        (`id`, `category`, `sub_category`, `sort_order`, `equipment_type`, `target_quantity`, `created_at`, `updated_at`, `created_by`, `modified_by`)
        VALUES 
        (:id, :category, :sub_category, :sort_order, :equipment_type, :target_quantity, NOW(), NOW(), 1, 1)
    ");

    $insertMapStmt = $pdo->prepare("
        INSERT INTO `tbl_inventory_jrrs_subtypes` 
        (`jrrs_id`, `equipment_subtype_id`, `created_at`, `updated_at`)
        VALUES 
        (:jrrs_id, :subtype_id, NOW(), NOW())
    ");

    $totalToe = 0;
    $sortOrder = 1;

    foreach ($c4istarItems as $item) {
        $jrrsId = $sortOrder;
        $insertJrrsStmt->execute([
            ':id'              => $jrrsId,
            ':category'        => $item['category'],
            ':sub_category'    => $item['sub_category'],
            ':sort_order'      => $sortOrder,
            ':equipment_type'  => $item['nomenclature'],
            ':target_quantity' => $item['target_quantity']
        ]);

        $totalToe += $item['target_quantity'];

        foreach ($item['subtypes'] as $stId) {
            $insertMapStmt->execute([
                ':jrrs_id'     => $jrrsId,
                ':subtype_id'  => $stId
            ]);
        }

        $sortOrder++;
    }

    $pdo->commit();
    echo "  Successfully seeded " . count($c4istarItems) . " C4ISTAR rows.\n";
    echo "  Total Required (TOE) sum: {$totalToe} (Target: 840)\n";

    $mapCount = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_jrrs_subtypes")->fetchColumn();
    echo "  Total subtype mappings created: {$mapCount}\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Seeding failed: " . $e->getMessage() . "\n");
}

echo "\nMigration & Seeding completed successfully.\n";

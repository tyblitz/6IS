<?php
// database/migrations/migrate_and_seed_edfs.php
// Migration & Seeding script for EDFS Account Monitoring Module

echo "===============================================================\n";
echo " 6IS EDFS ACCOUNT MONITORING MIGRATION & SEEDING SCRIPT\n";
echo "===============================================================\n\n";

$dbConfigPath = __DIR__ . '/../../backend/config/database.php';
if (!file_exists($dbConfigPath)) {
    die("Error: Database configuration file not found at $dbConfigPath\n");
}
$dbConfig = require $dbConfigPath;

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "[PASS] Connected to MySQL database {$dbConfig['database']}.\n";
} catch (PDOException $e) {
    die("[FAIL] Database connection failed: " . $e->getMessage() . "\n");
}

// 1. Create tbl_edfs_accounts table
echo "\n--- Step 1: Creating tbl_edfs_accounts ---\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS tbl_edfs_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        office_id INT NULL,
        office_name VARCHAR(100) NOT NULL,
        account_name VARCHAR(150) NULL,
        current_title VARCHAR(255) NOT NULL,
        personnel_name VARCHAR(255) NULL,
        username VARCHAR(150) NULL,
        password VARCHAR(255) NULL,
        status ENUM('Active', 'Inactive', 'For Renewal') NOT NULL DEFAULT 'Active',
        remarks TEXT NULL,
        source_doc VARCHAR(50) NULL,
        orig_nr VARCHAR(20) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_office_id (office_id),
        INDEX idx_office_name (office_name),
        INDEX idx_status (status),
        INDEX idx_username (username),
        INDEX idx_account_name (account_name),
        CONSTRAINT fk_edfs_office FOREIGN KEY (office_id) REFERENCES tbl_offices(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[PASS] Table tbl_edfs_accounts ready.\n";

// 2. Register Module in tbl_modules
echo "\n--- Step 2: Registering module in tbl_modules ---\n";
$stmt = $pdo->prepare("SELECT id FROM tbl_modules WHERE module_key = 'edfs'");
$stmt->execute();
$existingMod = $stmt->fetch();

if (!$existingMod) {
    $pdo->prepare("
        INSERT INTO tbl_modules (module_key, name, description, icon, route, is_core, is_active, sort_order, version)
        VALUES ('edfs', 'EDFS Accounts', 'Electronic Document Filing System Account Monitoring', 'document-text-outline', '/edfs', 0, 1, 6, '1.0.0')
    ")->execute();
    echo "[PASS] Registered 'edfs' module in tbl_modules.\n";
} else {
    $pdo->prepare("
        UPDATE tbl_modules
        SET name = 'EDFS Accounts',
            description = 'Electronic Document Filing System Account Monitoring',
            icon = 'document-text-outline',
            route = '/edfs',
            is_active = 1
        WHERE module_key = 'edfs'
    ")->execute();
    echo "[PASS] Updated 'edfs' module in tbl_modules.\n";
}

// 3. Register Permissions in tbl_permissions
echo "\n--- Step 3: Registering EDFS permissions ---\n";
$edfsPermissions = [
    [
        'permission_key' => 'view',
        'name' => 'View EDFS Accounts',
        'description' => 'View electronic document filing system account records and rosters'
    ],
    [
        'permission_key' => 'create',
        'name' => 'Create EDFS Account',
        'description' => 'Register new EDFS accounts into the monitoring ledger'
    ],
    [
        'permission_key' => 'edit',
        'name' => 'Edit EDFS Account',
        'description' => 'Update EDFS account details, personnel designations, and credentials'
    ],
    [
        'permission_key' => 'delete',
        'name' => 'Delete EDFS Account',
        'description' => 'Archive or remove EDFS accounts from monitoring'
    ]
];

$adminRoleId = 1; // Administrator
$insertedPermIds = [];

foreach ($edfsPermissions as $p) {
    $stmt = $pdo->prepare("SELECT id FROM tbl_permissions WHERE module_key = 'edfs' AND permission_key = ?");
    $stmt->execute([$p['permission_key']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $permId = $existing['id'];
        $pdo->prepare("UPDATE tbl_permissions SET name = ?, description = ?, is_active = 1 WHERE id = ?")
            ->execute([$p['name'], $p['description'], $permId]);
    } else {
        $pdo->prepare("
            INSERT INTO tbl_permissions (module_key, permission_key, name, description, is_active, is_system)
            VALUES ('edfs', ?, ?, ?, 1, 1)
        ")->execute([$p['permission_key'], $p['name'], $p['description']]);
        $permId = $pdo->lastInsertId();
    }
    $insertedPermIds[] = $permId;

    // Link to Administrator role
    $pdo->prepare("
        INSERT IGNORE INTO tbl_role_permissions (role_id, permission_id)
        VALUES (?, ?)
    ")->execute([$adminRoleId, $permId]);
}
echo "[PASS] EDFS permissions registered and granted to Administrator role.\n";

// 4. Ensure all offices exist in tbl_offices
echo "\n--- Step 4: Ensuring all EDFS offices exist in tbl_offices ---\n";
$recordsPath = __DIR__ . '/../../scratch/edfs_merged_records.json';
if (!file_exists($recordsPath)) {
    die("[FAIL] Merged records JSON not found at $recordsPath\n");
}
$records = json_decode(file_get_contents($recordsPath), true);

$officesStmt = $pdo->query("SELECT id, office_name, office_code FROM tbl_offices");
$existingOffices = $officesStmt->fetchAll();

$officeLookup = [];
foreach ($existingOffices as $o) {
    $officeLookup[strtoupper(trim($o['office_name']))] = $o['id'];
    $officeLookup[strtoupper(trim($o['office_code']))] = $o['id'];
}

// Map aliases (e.g. G1 -> OG1, CFMG AFPCOC -> AFPCOC)
$aliasMap = [
    'G1' => 'OG1',
    'G2' => 'OG2',
    'G3' => 'OG3',
    'G4' => 'OG4',
    'G6' => 'OG6',
    'G7' => 'OG7',
    'G8' => 'OG8',
    'G10' => 'OG10',
    'CFMG AFPCOC' => 'AFPCOC',
    'GHQ BAND' => 'GHQ BAND',
    'GHQTS' => 'GHQTS',
    'MPBN' => 'MP BN',
    'SDBN' => 'SDBN'
];

$newOfficesAdded = 0;
foreach ($records as $r) {
    $rawOffice = trim($r['office']);
    $upOffice = strtoupper($rawOffice);
    $canonical = isset($aliasMap[$upOffice]) ? $aliasMap[$upOffice] : $upOffice;

    if (!isset($officeLookup[$canonical]) && !isset($officeLookup[$upOffice])) {
        // Insert missing office
        $ins = $pdo->prepare("
            INSERT INTO tbl_offices (organization_id, office_name, office_code, is_active)
            VALUES (1, ?, ?, 1)
        ");
        $ins->execute([$rawOffice, $rawOffice]);
        $newId = $pdo->lastInsertId();
        $officeLookup[$canonical] = $newId;
        $officeLookup[$upOffice] = $newId;
        $newOfficesAdded++;
        echo "  [NEW OFFICE] Added '$rawOffice' to tbl_offices (ID: $newId)\n";
    }
}
echo "[PASS] Total new offices added: $newOfficesAdded.\n";

// 5. Populate tbl_edfs_accounts with the 286 de-duplicated records
echo "\n--- Step 5: Seeding de-duplicated EDFS accounts ---\n";
// Clear existing records to ensure clean idempotent seed
$pdo->exec("TRUNCATE TABLE tbl_edfs_accounts");

$insertStmt = $pdo->prepare("
    INSERT INTO tbl_edfs_accounts (
        office_id, office_name, account_name, current_title,
        personnel_name, username, password, status, remarks,
        source_doc, orig_nr
    ) VALUES (
        :office_id, :office_name, :account_name, :current_title,
        :personnel_name, :username, :password, :status, :remarks,
        :source_doc, :orig_nr
    )
");

$seededCount = 0;
foreach ($records as $r) {
    $rawOffice = trim($r['office']);
    $upOffice = strtoupper($rawOffice);
    $canonical = isset($aliasMap[$upOffice]) ? $aliasMap[$upOffice] : $upOffice;
    
    $officeId = null;
    if (isset($officeLookup[$canonical])) {
        $officeId = $officeLookup[$canonical];
    } elseif (isset($officeLookup[$upOffice])) {
        $officeId = $officeLookup[$upOffice];
    }

    $accountName = !empty($r['account_name']) ? trim($r['account_name']) : null;
    $personnelName = !empty($r['personnel_name']) ? trim($r['personnel_name']) : null;
    
    // Default username to account_name if available, else null
    $username = $accountName;
    $password = null; // Admin can set / reset password via CRUD
    $status = 'Active';
    $remarks = !empty($r['remarks']) ? trim($r['remarks']) : null;
    $sourceDoc = $r['src'] === 'initial' ? 'Initial List' : 'Additional Sept 2026';
    $origNr = $r['orig_nr'];

    $insertStmt->execute([
        ':office_id' => $officeId,
        ':office_name' => $rawOffice,
        ':account_name' => $accountName,
        ':current_title' => trim($r['current_title']),
        ':personnel_name' => $personnelName,
        ':username' => $username,
        ':password' => $password,
        ':status' => $status,
        ':remarks' => $remarks,
        ':source_doc' => $sourceDoc,
        ':orig_nr' => $origNr
    ]);
    $seededCount++;
}

echo "[PASS] Successfully seeded $seededCount de-duplicated EDFS accounts into tbl_edfs_accounts.\n";

// 6. Verification Summary
$totalAccounts = $pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts")->fetchColumn();
$withName = $pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE personnel_name IS NOT NULL AND personnel_name != ''")->fetchColumn();
$blankName = $pdo->query("SELECT COUNT(*) FROM tbl_edfs_accounts WHERE personnel_name IS NULL OR personnel_name = ''")->fetchColumn();
$uniqueOffices = $pdo->query("SELECT COUNT(DISTINCT office_name) FROM tbl_edfs_accounts")->fetchColumn();

echo "\n===============================================================\n";
echo " EDFS MIGRATION COMPLETE SUMMARY:\n";
echo " Total EDFS Accounts Seeded: $totalAccounts\n";
echo " With Assigned Personnel:    $withName\n";
echo " Generic Desk Accounts:      $blankName (e.g., Message Centers, Acting Roles)\n";
echo " Distinct Offices:           $uniqueOffices\n";
echo "===============================================================\n";

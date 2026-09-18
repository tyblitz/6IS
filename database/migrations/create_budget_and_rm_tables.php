<?php
// database/migrations/create_budget_and_rm_tables.php
// Migration & Seed Script for Budget & R&M Fund Monitoring Module

$dbConfig = require __DIR__ . '/../../backend/config/database.php';
$pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4", $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "===============================================================\n";
echo " MIGRATING & SEEDING BUDGET & R&M MONITORING MODULE\n";
echo "===============================================================\n\n";

// 1. Create tbl_budget_schedules
echo "Step 1: Creating tbl_budget_schedules table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS tbl_budget_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fiscal_year INT NOT NULL DEFAULT 2026,
        office_id INT NOT NULL,
        paps VARCHAR(150) NOT NULL,
        allocated_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        jan TINYINT UNSIGNED NOT NULL DEFAULT 0,
        feb TINYINT UNSIGNED NOT NULL DEFAULT 0,
        mar TINYINT UNSIGNED NOT NULL DEFAULT 0,
        apr TINYINT UNSIGNED NOT NULL DEFAULT 0,
        may TINYINT UNSIGNED NOT NULL DEFAULT 0,
        jun TINYINT UNSIGNED NOT NULL DEFAULT 0,
        jul TINYINT UNSIGNED NOT NULL DEFAULT 0,
        aug TINYINT UNSIGNED NOT NULL DEFAULT 0,
        sep TINYINT UNSIGNED NOT NULL DEFAULT 0,
        oct TINYINT UNSIGNED NOT NULL DEFAULT 0,
        nov TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `dec` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        remarks TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT NULL,
        modified_by INT NULL,
        deleted_at DATETIME NULL DEFAULT NULL,
        INDEX idx_budget_sched_office (office_id),
        INDEX idx_budget_sched_fy (fiscal_year),
        INDEX idx_budget_sched_deleted (deleted_at),
        UNIQUE KEY uq_budget_sched_unique (fiscal_year, office_id, paps),
        CONSTRAINT fk_budget_sched_office FOREIGN KEY (office_id) REFERENCES tbl_offices(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  [PASS] tbl_budget_schedules created or already exists.\n";

// 2. Create tbl_budget_disbursements
echo "\nStep 2: Creating tbl_budget_disbursements table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS tbl_budget_disbursements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NULL,
        office_id INT NOT NULL,
        disbursement_date DATE NOT NULL,
        particulars VARCHAR(255) NOT NULL,
        amount_disbursed DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        received_by VARCHAR(150) NULL,
        chargeability VARCHAR(255) NULL,
        remarks TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT NULL,
        modified_by INT NULL,
        deleted_at DATETIME NULL DEFAULT NULL,
        INDEX idx_budget_disb_sched (schedule_id),
        INDEX idx_budget_disb_office (office_id),
        INDEX idx_budget_disb_date (disbursement_date),
        INDEX idx_budget_disb_deleted (deleted_at),
        CONSTRAINT fk_budget_disb_schedule FOREIGN KEY (schedule_id) REFERENCES tbl_budget_schedules(id) ON DELETE RESTRICT,
        CONSTRAINT fk_budget_disb_office FOREIGN KEY (office_id) REFERENCES tbl_offices(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  [PASS] tbl_budget_disbursements created or already exists.\n";

// 3. Register canonical module in tbl_modules
echo "\nStep 3: Registering canonical 'budget' module in tbl_modules...\n";
$stmt = $pdo->prepare("SELECT id FROM tbl_modules WHERE module_key = 'budget'");
$stmt->execute();
$existingBudgetMod = $stmt->fetch();

if ($existingBudgetMod) {
    $pdo->prepare("
        UPDATE tbl_modules
        SET name = 'Budget & R&M Monitoring',
            description = 'Schedule of Fund Releases and R&M Disbursement Monitoring',
            icon = 'cashOutline',
            route = '/budget',
            is_active = 1
        WHERE module_key = 'budget'
    ")->execute();
    echo "  [PASS] Updated existing 'budget' module (route: /budget, is_active: 1).\n";
} else {
    $pdo->prepare("
        INSERT INTO tbl_modules (module_key, name, description, icon, route, is_active)
        VALUES ('budget', 'Budget & R&M Monitoring', 'Schedule of Fund Releases and R&M Disbursement Monitoring', 'cashOutline', '/budget', 1)
    ")->execute();
    echo "  [PASS] Registered new canonical 'budget' module in tbl_modules.\n";
}

// 4. Register permissions in tbl_permissions
echo "\nStep 4: Registering RBAC permissions...\n";
$budgetPermissions = [
    [
        'permission_key' => 'view',
        'name' => 'View Budget & R&M',
        'description' => 'View fund allocation schedules, release matrix, and disbursement records'
    ],
    [
        'permission_key' => 'create',
        'name' => 'Create Budget & R&M',
        'description' => 'Create allocation schedules and record released fund disbursements'
    ],
    [
        'permission_key' => 'edit',
        'name' => 'Edit Budget & R&M',
        'description' => 'Update fund schedules, disbursement details, or remarks'
    ],
    [
        'permission_key' => 'delete',
        'name' => 'Delete Budget & R&M',
        'description' => 'Archive or soft delete fund schedules and disbursement records'
    ]
];

$permIds = [];
foreach ($budgetPermissions as $p) {
    $stmt = $pdo->prepare("SELECT id FROM tbl_permissions WHERE module_key = 'budget' AND permission_key = ?");
    $stmt->execute([$p['permission_key']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pid = $existing['id'];
        $pdo->prepare("UPDATE tbl_permissions SET name = ?, description = ?, is_active = 1 WHERE id = ?")
            ->execute([$p['name'], $p['description'], $pid]);
    } else {
        $pdo->prepare("
            INSERT INTO tbl_permissions (module_key, permission_key, name, description, is_active, is_system)
            VALUES ('budget', ?, ?, ?, 1, 1)
        ")->execute([$p['permission_key'], $p['name'], $p['description']]);
        $pid = $pdo->lastInsertId();
    }
    $permIds[] = $pid;

    // Grant to Role 1 (Admin) and Role 2 (User)
    $pdo->prepare("INSERT IGNORE INTO tbl_role_permissions (role_id, permission_id) VALUES (1, ?)")->execute([$pid]);
    $pdo->prepare("INSERT IGNORE INTO tbl_role_permissions (role_id, permission_id) VALUES (2, ?)")->execute([$pid]);
}
echo "  [PASS] Registered 4 budget permissions and granted to Administrator & User roles.\n";

// 5. Build office lookup map
$offices = $pdo->query("SELECT id, office_code, office_name FROM tbl_offices")->fetchAll();
$officeLookup = [];
foreach ($offices as $o) {
    $code = strtoupper(trim($o['office_code']));
    $name = strtoupper(trim($o['office_name']));
    if ($code) $officeLookup[$code] = (int)$o['id'];
    if ($name) $officeLookup[$name] = (int)$o['id'];
}
// Special alias mappings
$officeLookup['G10'] = $officeLookup['OG10'] ?? 8;
$officeLookup['G6'] = $officeLookup['OG6'] ?? 5;
$officeLookup['G1'] = $officeLookup['OG1'] ?? 1;
$officeLookup['G3'] = $officeLookup['OG3'] ?? 3;
$officeLookup['NAF'] = $officeLookup['ONAF'] ?? 16;
$officeLookup['MPBN'] = $officeLookup['MP BN'] ?? 40;
$officeLookup['AFPK9'] = $officeLookup['AFP K-9'] ?? 17;

function resolveOfficeId($unit, $lookup, $pdo) {
    $clean = strtoupper(trim($unit));
    $clean = str_replace(['-', ' '], '', $clean);
    foreach ($lookup as $k => $id) {
        $kClean = str_replace(['-', ' '], '', $k);
        if ($kClean === $clean) {
            return $id;
        }
    }
    // Fallback query
    $stmt = $pdo->prepare("SELECT id FROM tbl_offices WHERE office_code LIKE ? OR office_name LIKE ? LIMIT 1");
    $stmt->execute(["%$unit%", "%$unit%"]);
    $row = $stmt->fetch();
    if ($row) {
        return (int)$row['id'];
    }
    throw new RuntimeException("Could not resolve office for unit: {$unit}");
}

// 6. Idempotently Seed Schedule Rows (22 records from R&M 2026.jfif)
echo "\nStep 5: Idempotently seeding 22 Schedule records from R&M 2026.jfif...\n";

$scheduleRows = [
    ['unit' => 'OCG', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 21985.38, 'sched' => ['apr' => 1, 'aug' => 1]],
    ['unit' => 'OCS', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 8853.00, 'sched' => ['feb' => 1, 'may' => 1, 'aug' => 1, 'nov' => 1]],
    ['unit' => 'OG1', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 6500.00, 'sched' => ['apr' => 1]],
    ['unit' => 'OG2', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 8000.00, 'sched' => ['mar' => 1]],
    ['unit' => 'OG3', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 15320.00, 'sched' => ['mar' => 1, 'jun' => 1, 'sep' => 1, 'dec' => 1]],
    ['unit' => 'OG4', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 14276.00, 'sched' => ['mar' => 1, 'aug' => 1]],
    ['unit' => 'OG6', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 6000.00, 'sched' => ['feb' => 1, 'may' => 1, 'aug' => 1, 'nov' => 1]],
    ['unit' => 'G10', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 32429.20, 'sched' => ['mar' => 1, 'jun' => 1]],
    ['unit' => 'SDO', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 17600.00, 'sched' => ['apr' => 1]],
    ['unit' => 'ONAF', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 10638.42, 'sched' => ['apr' => 1]],
    ['unit' => 'ASPG', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 100000.00, 'sched' => ['apr' => 1, 'jul' => 1]],
    ['unit' => 'ESG', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 20000.00, 'sched' => ['may' => 4]],
    ['unit' => 'HPMG', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 24900.00, 'sched' => ['apr' => 1, 'jul' => 1]],
    ['unit' => 'GHQTS', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 9698.00, 'sched' => ['jul' => 1]],
    ['unit' => 'SSMG', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 33000.00, 'sched' => ['feb' => 1]],
    ['unit' => 'CAFS', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 6625.00, 'sched' => ['jan' => 1]],
    ['unit' => 'CSSO', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 3000.00, 'sched' => ['feb' => 1]],
    ['unit' => 'CSMG', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 7500.00, 'sched' => ['apr' => 1]],
    ['unit' => 'AFP K-9', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 4000.00, 'sched' => ['apr' => 1]],
    ['unit' => 'MPBn', 'paps' => 'R & M of ICT Equipments', 'allocated_amount' => 12550.00, 'sched' => ['mar' => 1, 'jun' => 1]],
    ['unit' => 'ASPG', 'paps' => 'Replacement of Handheld Radio Spare Parts', 'allocated_amount' => 300000.00, 'sched' => ['feb' => 1, 'may' => 1, 'aug' => 1, 'nov' => 1]],
    ['unit' => 'OG6', 'paps' => 'Repair and Maintenance of Public Address System (PAS)', 'allocated_amount' => 5000.00, 'sched' => ['may' => 1]]
];

$upsertSchedStmt = $pdo->prepare("
    INSERT INTO tbl_budget_schedules (
        fiscal_year, office_id, paps, allocated_amount,
        jan, feb, mar, apr, may, jun,
        jul, aug, sep, oct, nov, `dec`,
        created_by, modified_by
    ) VALUES (
        2026, :office_id, :paps, :allocated_amount,
        :jan, :feb, :mar, :apr, :may, :jun,
        :jul, :aug, :sep, :oct, :nov, :dec,
        1, 1
    )
    ON DUPLICATE KEY UPDATE
        allocated_amount = VALUES(allocated_amount),
        jan = VALUES(jan),
        feb = VALUES(feb),
        mar = VALUES(mar),
        apr = VALUES(apr),
        may = VALUES(may),
        jun = VALUES(jun),
        jul = VALUES(jul),
        aug = VALUES(aug),
        sep = VALUES(sep),
        oct = VALUES(oct),
        nov = VALUES(nov),
        `dec` = VALUES(`dec`),
        deleted_at = NULL
");

$scheduleIdLookup = []; // Key: officeId_paps
foreach ($scheduleRows as $sr) {
    $officeId = resolveOfficeId($sr['unit'], $officeLookup, $pdo);
    
    $months = ['jan'=>0, 'feb'=>0, 'mar'=>0, 'apr'=>0, 'may'=>0, 'jun'=>0, 'jul'=>0, 'aug'=>0, 'sep'=>0, 'oct'=>0, 'nov'=>0, 'dec'=>0];
    foreach ($sr['sched'] as $m => $cnt) {
        $months[$m] = (int)$cnt;
    }

    $upsertSchedStmt->execute([
        ':office_id' => $officeId,
        ':paps' => $sr['paps'],
        ':allocated_amount' => $sr['allocated_amount'],
        ':jan' => $months['jan'],
        ':feb' => $months['feb'],
        ':mar' => $months['mar'],
        ':apr' => $months['apr'],
        ':may' => $months['may'],
        ':jun' => $months['jun'],
        ':jul' => $months['jul'],
        ':aug' => $months['aug'],
        ':sep' => $months['sep'],
        ':oct' => $months['oct'],
        ':nov' => $months['nov'],
        ':dec' => $months['dec']
    ]);

    // Retrieve the ID
    $fetchStmt = $pdo->prepare("SELECT id FROM tbl_budget_schedules WHERE fiscal_year = 2026 AND office_id = ? AND paps = ?");
    $fetchStmt->execute([$officeId, $sr['paps']]);
    $schedId = (int)$fetchStmt->fetchColumn();
    $scheduleIdLookup[$officeId . '_' . $sr['paps']] = $schedId;
}
echo "  [PASS] 22 allocation schedule lines seeded/updated.\n";

// 7. Idempotently Seed Disbursement Rows (14 records from Received R & M 2026.jfif)
echo "\nStep 6: Idempotently seeding 14 Disbursement records from Received R & M 2026.jfif...\n";

$disbursementRows = [
    ['date' => '2026-06-04', 'particulars' => 'R/M - ICT Eqmt - Q1 - OG10', 'amt' => 12160.00, 'received_by' => 'Ms De Jesus', 'unit' => 'G10', 'charge' => 'Comd - Q1 FY 2026 MOOE - R/M - ICT Eqmt'],
    ['date' => '2026-06-15', 'particulars' => 'R/M - ICT - Q1 OG6', 'amt' => 4500.00, 'received_by' => 'SSg Racadio', 'unit' => 'OG6', 'charge' => 'G6 - Q1 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-06-16', 'particulars' => 'R/M - ICT - Q1 - SSMG', 'amt' => 24750.00, 'received_by' => 'Sgt Gamboa', 'unit' => 'SSMG', 'charge' => 'SSMG - Q1 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-06-30', 'particulars' => 'R/M - ICT - Q2 - CSMG', 'amt' => 5625.00, 'received_by' => 'SSg Patino', 'unit' => 'CSMG', 'charge' => 'CSMG - Q2 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-06-30', 'particulars' => 'R/M - ICT - Q2 - ONAF', 'amt' => 7978.00, 'received_by' => 'Ms De Asis', 'unit' => 'ONAF', 'charge' => 'NAF - Q2 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-07-03', 'particulars' => 'R/M - ICT - Q2 - HPMG', 'amt' => 9337.00, 'received_by' => 'Mr Magtalas', 'unit' => 'HPMG', 'charge' => 'HPMG - Q2 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-07-03', 'particulars' => 'R/M - ICT - Q2 - ASPG', 'amt' => 37500.00, 'received_by' => 'Cpl Reynaldo', 'unit' => 'ASPG', 'charge' => 'ASPG - Q2 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-07-06', 'particulars' => 'R/M - ICT - Q2 - OG1', 'amt' => 4875.00, 'received_by' => 'SSg Quizana', 'unit' => 'OG1', 'charge' => 'G1 - Q2 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-07-21', 'particulars' => 'R/M - ICT Q2 - AFPK9', 'amt' => 3000.00, 'received_by' => 'SSg Elias', 'unit' => 'AFP K-9', 'charge' => 'AFPK9 - Q2 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-08-05', 'particulars' => 'R/M - ICT Eqmt - Q2 - OG10', 'amt' => 12160.00, 'received_by' => 'Ms Miguel', 'unit' => 'G10', 'charge' => 'G10 - Q2 FY 2026 MOOE - R/M - ICT Eqmt'],
    ['date' => '2026-08-13', 'particulars' => 'R/M - ICT - Q2 - OSDO', 'amt' => 13200.00, 'received_by' => 'Mr Collado', 'unit' => 'SDO', 'charge' => 'SDO - Q2 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-08-28', 'particulars' => 'R/M - ICT - Q1 & Q2 - OG3', 'amt' => 5745.00, 'received_by' => 'TSg Pasigan', 'unit' => 'OG3', 'charge' => 'G3 - Q1 & Q2 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-08-28', 'particulars' => 'R/M - ICT - Q2 - MP Bn', 'amt' => 4706.00, 'received_by' => 'A1C Gatin', 'unit' => 'MPBn', 'charge' => 'MP Bn - Q2 FY 2026 MOOE - R/M - ICT'],
    ['date' => '2026-09-10', 'particulars' => 'R/M - ICT - Q3 - OG3', 'amt' => 2872.00, 'received_by' => 'TSg Pasigan', 'unit' => 'OG3', 'charge' => 'G3 - Q3 FY 2026 MOOE - R/M - ICT']
];

$checkDisbStmt = $pdo->prepare("
    SELECT id FROM tbl_budget_disbursements
    WHERE disbursement_date = :date AND office_id = :office_id AND particulars = :particulars AND amount_disbursed = :amt
    LIMIT 1
");

$insDisbStmt = $pdo->prepare("
    INSERT INTO tbl_budget_disbursements (
        schedule_id, office_id, disbursement_date, particulars,
        amount_disbursed, received_by, chargeability,
        created_by, modified_by
    ) VALUES (
        :schedule_id, :office_id, :disbursement_date, :particulars,
        :amount_disbursed, :received_by, :chargeability,
        1, 1
    )
");

$updateDisbStmt = $pdo->prepare("
    UPDATE tbl_budget_disbursements
    SET schedule_id = :schedule_id,
        received_by = :received_by,
        chargeability = :chargeability,
        deleted_at = NULL
    WHERE id = :id
");

foreach ($disbursementRows as $dr) {
    $officeId = resolveOfficeId($dr['unit'], $officeLookup, $pdo);
    // All 14 historical transactions explicitly correspond to "R & M of ICT Equipments"
    $schedId = $scheduleIdLookup[$officeId . '_R & M of ICT Equipments'] ?? null;

    $checkDisbStmt->execute([
        ':date' => $dr['date'],
        ':office_id' => $officeId,
        ':particulars' => $dr['particulars'],
        ':amt' => $dr['amt']
    ]);
    $existingDisb = $checkDisbStmt->fetch();

    if ($existingDisb) {
        $updateDisbStmt->execute([
            ':schedule_id' => $schedId,
            ':received_by' => $dr['received_by'],
            ':chargeability' => $dr['charge'],
            ':id' => $existingDisb['id']
        ]);
    } else {
        $insDisbStmt->execute([
            ':schedule_id' => $schedId,
            ':office_id' => $officeId,
            ':disbursement_date' => $dr['date'],
            ':particulars' => $dr['particulars'],
            ':amount_disbursed' => $dr['amt'],
            ':received_by' => $dr['received_by'],
            ':chargeability' => $dr['charge']
        ]);
    }
}
echo "  [PASS] 14 disbursement records seeded/verified.\n";

// 8. Output Baseline Summary Verification
$totSched = (int)$pdo->query("SELECT COUNT(*) FROM tbl_budget_schedules WHERE deleted_at IS NULL")->fetchColumn();
$totMooe = (float)$pdo->query("SELECT SUM(allocated_amount) FROM tbl_budget_schedules WHERE deleted_at IS NULL")->fetchColumn();
$totDisbCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_budget_disbursements WHERE deleted_at IS NULL")->fetchColumn();
$totDisbAmt = (float)$pdo->query("SELECT SUM(amount_disbursed) FROM tbl_budget_disbursements WHERE deleted_at IS NULL")->fetchColumn();
$unallocatedAmt = (float)$pdo->query("SELECT COALESCE(SUM(amount_disbursed), 0) FROM tbl_budget_disbursements WHERE schedule_id IS NULL AND deleted_at IS NULL")->fetchColumn();
$remBal = $totMooe - $totDisbAmt;

echo "\n--- Baseline Verification Metrics ---\n";
echo "  Total Active Schedules: {$totSched} (Expected: 22)\n";
echo "  Total Approved MOOE: ₱" . number_format($totMooe, 2) . " (Expected: ₱667,875.00)\n";
echo "  Total Active Disbursements: {$totDisbCount} (Expected: 14)\n";
echo "  Total Disbursed: ₱" . number_format($totDisbAmt, 2) . " (Expected: ₱148,408.00)\n";
echo "  Unallocated Disbursed Total: ₱" . number_format($unallocatedAmt, 2) . "\n";
echo "  Overall Remaining Balance: ₱" . number_format($remBal, 2) . " (Expected: ₱519,467.00)\n";

if ($totSched === 22 && abs($totMooe - 667875.00) < 0.01 && $totDisbCount === 14 && abs($totDisbAmt - 148408.00) < 0.01 && abs($remBal - 519467.00) < 0.01) {
    echo "\n>>> SUCCESS: ALL BASELINE FINANCIAL INVARIANTS VERIFIED! <<<\n";
} else {
    echo "\n>>> WARNING: BASELINE FINANCIAL INVARIANTS MISMATCH! <<<\n";
}

echo "\n===============================================================\n";
echo " MIGRATION & SEEDING COMPLETED SUCCESSFULLY!\n";
echo "===============================================================\n";

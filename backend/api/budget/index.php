<?php
// backend/api/budget/index.php
// REST API Endpoint for 6IS Budget & R&M Fund Monitoring Module

require_once __DIR__ . '/../../helpers/cors.php';
handleCors();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/audit.php';
require_once __DIR__ . '/../../helpers/modules.php';
require_once __DIR__ . '/../../helpers/permissions.php';

requireAuth();
requireModuleActive('budget');

/**
 * Standardized Response Helper
 */
function sendResponse($success, $message, $data = null, $errors = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => $errors
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Load Database Config
$configPath = __DIR__ . '/../../config/database.php';
if (!file_exists($configPath)) {
    sendResponse(false, 'Database configuration file missing.', null, null, 500);
}

$dbConfig = require $configPath;
try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    sendResponse(false, 'Database connection failed: ' . $e->getMessage(), null, ['db' => $e->getMessage()], 500);
}

/**
 * Determines whether the authenticated user has cross-office access.
 * Returns true if:
 * 1. User has no assigned office (organization-wide / headquarters user, office_id <= 0).
 * 2. User has administrative role ('Administrator').
 * 3. User possesses organization-level or office-management permissions (offices.configure, organization.configure, audit.view, users.view).
 * 
 * Returns false for ordinary office-scoped users whose office_id is set and who lack command oversight permissions.
 */
function canAccessCrossOffice(?PDO $pdo = null): bool {
    $userOfficeId = (int)($_SESSION['office_id'] ?? 0);
    $userRole = $_SESSION['role'] ?? '';

    // Organization / headquarters level user without office binding
    if ($userOfficeId <= 0) {
        return true;
    }

    // Administrator role
    if (strcasecmp($userRole, 'Administrator') === 0) {
        return true;
    }

    // Check command-level oversight permissions
    if (hasPermission('offices', 'configure', $pdo) ||
        hasPermission('organization', 'configure', $pdo) ||
        hasPermission('audit', 'view', $pdo) ||
        hasPermission('users', 'view', $pdo)) {
        return true;
    }

    return false;
}

// Security Context
$userOfficeId = (int)($_SESSION['office_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 1);
$canCrossOffice = canAccessCrossOffice($pdo);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($pdo, $canCrossOffice, $userOfficeId);
        break;
    case 'POST':
        handlePost($pdo, $canCrossOffice, $userOfficeId, $userId);
        break;
    case 'PUT':
    case 'PATCH':
        handlePut($pdo, $canCrossOffice, $userOfficeId, $userId);
        break;
    case 'DELETE':
        handleDelete($pdo, $canCrossOffice, $userOfficeId, $userId);
        break;
    default:
        sendResponse(false, 'HTTP Method Not Allowed', null, null, 405);
}

/**
 * GET Handler
 */
function handleGet(PDO $pdo, bool $canCrossOffice, int $userOfficeId) {
    requirePermission('budget', 'view', $pdo);

    $view = isset($_GET['view']) ? trim($_GET['view']) : 'schedule';

    // 1. Dropdown options
    if ($view === 'options') {
        try {
            $officesStmt = $pdo->query("SELECT id, office_name, office_code, office_abbv FROM tbl_offices WHERE deleted_at IS NULL ORDER BY office_code ASC");
            $offices = $officesStmt->fetchAll();

            $schedQuery = "
                SELECT s.id, s.office_id, s.fiscal_year, s.paps, s.allocated_amount,
                       COALESCE(NULLIF(o.office_code, ''), o.office_name) AS office_code,
                       o.office_name
                FROM tbl_budget_schedules s
                JOIN tbl_offices o ON s.office_id = o.id
                WHERE s.deleted_at IS NULL
            ";
            if (!$canCrossOffice) {
                $schedQuery .= " AND s.office_id = " . (int)$userOfficeId;
            }
            $schedQuery .= " ORDER BY s.fiscal_year DESC, office_code ASC, s.paps ASC";
            $schedules = $pdo->query($schedQuery)->fetchAll();

            sendResponse(true, 'Options fetched successfully.', [
                'offices' => $offices,
                'schedules' => $schedules
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch options: ' . $e->getMessage(), null, null, 500);
        }
    }

    // 2. Schedule Matrix View
    if ($view === 'schedule') {
        try {
            $params = [];
            $where = ["s.deleted_at IS NULL"];

            // Server-side Office Isolation
            if (!$canCrossOffice) {
                $where[] = "s.office_id = :user_office_id";
                $params[':user_office_id'] = $userOfficeId;
            } elseif (!empty($_GET['office_id'])) {
                $where[] = "s.office_id = :filter_office_id";
                $params[':filter_office_id'] = (int)$_GET['office_id'];
            }

            if (!empty($_GET['fiscal_year'])) {
                $where[] = "s.fiscal_year = :fy";
                $params[':fy'] = (int)$_GET['fiscal_year'];
            }

            $whereSql = implode(' AND ', $where);

            // Fetch active schedules
            $sql = "
                SELECT 
                    s.id, s.fiscal_year, s.office_id, s.paps, s.allocated_amount,
                    s.jan, s.feb, s.mar, s.apr, s.may, s.jun,
                    s.jul, s.aug, s.sep, s.oct, s.nov, s.`dec`,
                    s.remarks, s.created_at, s.updated_at,
                    COALESCE(NULLIF(o.office_code, ''), o.office_name) AS office_code,
                    o.office_name
                FROM tbl_budget_schedules s
                JOIN tbl_offices o ON s.office_id = o.id
                WHERE {$whereSql}
                ORDER BY s.fiscal_year DESC, office_code ASC, s.id ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rawSchedules = $stmt->fetchAll();

            // Fetch linked active disbursements mapped by schedule_id
            $disbSql = "
                SELECT 
                    d.schedule_id,
                    SUM(d.amount_disbursed) AS disbursed_total,
                    GROUP_CONCAT(DISTINCT MONTH(d.disbursement_date)) AS active_months
                FROM tbl_budget_disbursements d
                WHERE d.deleted_at IS NULL AND d.schedule_id IS NOT NULL
                GROUP BY d.schedule_id
            ";
            $disbMap = [];
            foreach ($pdo->query($disbSql)->fetchAll() as $dRow) {
                $disbMap[(int)$dRow['schedule_id']] = [
                    'disbursed_total' => (float)$dRow['disbursed_total'],
                    'active_months' => array_map('intval', explode(',', $dRow['active_months'] ?: ''))
                ];
            }

            $schedules = [];
            $sumMooe = 0.0;
            $sumLinkedDisbursed = 0.0;
            $sumScheduledReleases = 0;

            foreach ($rawSchedules as $s) {
                $sid = (int)$s['id'];
                $allocated = (float)$s['allocated_amount'];
                $sumMooe += $allocated;

                $schedCount = (int)$s['jan'] + (int)$s['feb'] + (int)$s['mar'] +
                              (int)$s['apr'] + (int)$s['may'] + (int)$s['jun'] +
                              (int)$s['jul'] + (int)$s['aug'] + (int)$s['sep'] +
                              (int)$s['oct'] + (int)$s['nov'] + (int)$s['dec'];
                $sumScheduledReleases += $schedCount;

                $linkedData = $disbMap[$sid] ?? ['disbursed_total' => 0.0, 'active_months' => []];
                $disbursed = (float)$linkedData['disbursed_total'];
                $sumLinkedDisbursed += $disbursed;
                $balance = $allocated - $disbursed;

                $schedules[] = [
                    'id' => $sid,
                    'fiscal_year' => (int)$s['fiscal_year'],
                    'office_id' => (int)$s['office_id'],
                    'office_code' => $s['office_code'],
                    'office_name' => $s['office_name'],
                    'paps' => $s['paps'],
                    'allocated_amount' => $allocated,
                    'jan' => (int)$s['jan'],
                    'feb' => (int)$s['feb'],
                    'mar' => (int)$s['mar'],
                    'apr' => (int)$s['apr'],
                    'may' => (int)$s['may'],
                    'jun' => (int)$s['jun'],
                    'jul' => (int)$s['jul'],
                    'aug' => (int)$s['aug'],
                    'sep' => (int)$s['sep'],
                    'oct' => (int)$s['oct'],
                    'nov' => (int)$s['nov'],
                    'dec' => (int)$s['dec'],
                    'schedule_release_count' => $schedCount,
                    'disbursed_total' => $disbursed,
                    'remaining_balance' => $balance,
                    'disbursed_months' => $linkedData['active_months'],
                    'remarks' => $s['remarks'],
                    'created_at' => $s['created_at'],
                    'updated_at' => $s['updated_at']
                ];
            }

            // Calculate overall disbursement metrics respecting office scope
            $disbWhere = ["d.deleted_at IS NULL"];
            $disbParams = [];
            if (!$canCrossOffice) {
                $disbWhere[] = "d.office_id = :user_office_id";
                $disbParams[':user_office_id'] = $userOfficeId;
            } elseif (!empty($_GET['office_id'])) {
                $disbWhere[] = "d.office_id = :filter_office_id";
                $disbParams[':filter_office_id'] = (int)$_GET['office_id'];
            }
            $disbWhereSql = implode(' AND ', $disbWhere);

            // Total overall disbursed (including linked and unlinked)
            $totDisbStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_disbursed), 0) FROM tbl_budget_disbursements d WHERE {$disbWhereSql}");
            $totDisbStmt->execute($disbParams);
            $overallDisbursed = (float)$totDisbStmt->fetchColumn();

            // Unallocated disbursed total (where schedule_id IS NULL)
            $unallocWhereSql = $disbWhereSql . " AND d.schedule_id IS NULL";
            $unallocStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_disbursed), 0) FROM tbl_budget_disbursements d WHERE {$unallocWhereSql}");
            $unallocStmt->execute($disbParams);
            $unallocatedDisbursed = (float)$unallocStmt->fetchColumn();

            $overallRemainingBalance = $sumMooe - $overallDisbursed;

            sendResponse(true, 'Budget schedules fetched successfully.', [
                'schedules' => $schedules,
                'metrics' => [
                    'mooe_total' => $sumMooe,
                    'overall_disbursed_total' => $overallDisbursed,
                    'unallocated_disbursed_total' => $unallocatedDisbursed,
                    'overall_remaining_balance' => $overallRemainingBalance,
                    'total_scheduled_releases' => $sumScheduledReleases,
                    'total_schedule_items' => count($schedules)
                ]
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch budget schedules: ' . $e->getMessage(), null, null, 500);
        }
    }

    // 3. Disbursements Ledger View
    if ($view === 'disbursements') {
        try {
            $params = [];
            $where = ["d.deleted_at IS NULL"];

            if (!$canCrossOffice) {
                $where[] = "d.office_id = :user_office_id";
                $params[':user_office_id'] = $userOfficeId;
            } elseif (!empty($_GET['office_id'])) {
                $where[] = "d.office_id = :filter_office_id";
                $params[':filter_office_id'] = (int)$_GET['office_id'];
            }

            if (!empty($_GET['schedule_id'])) {
                $where[] = "d.schedule_id = :filter_sched_id";
                $params[':filter_sched_id'] = (int)$_GET['schedule_id'];
            }

            if (!empty($_GET['search'])) {
                $search = '%' . trim($_GET['search']) . '%';
                $where[] = "(d.particulars LIKE :search OR d.received_by LIKE :search OR d.chargeability LIKE :search OR o.office_name LIKE :search OR o.office_code LIKE :search)";
                $params[':search'] = $search;
            }

            $whereSql = implode(' AND ', $where);

            $sql = "
                SELECT 
                    d.id, d.schedule_id, d.office_id, d.disbursement_date,
                    d.particulars, d.amount_disbursed, d.received_by,
                    d.chargeability, d.remarks, d.created_at, d.updated_at,
                    COALESCE(NULLIF(o.office_code, ''), o.office_name) AS office_code,
                    o.office_name,
                    s.paps, s.allocated_amount
                FROM tbl_budget_disbursements d
                JOIN tbl_offices o ON d.office_id = o.id
                LEFT JOIN tbl_budget_schedules s ON d.schedule_id = s.id
                WHERE {$whereSql}
                ORDER BY d.disbursement_date DESC, d.id DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rawDisbursements = $stmt->fetchAll();

            $disbursements = [];
            $totalDisbursed = 0.0;

            foreach ($rawDisbursements as $dr) {
                $amt = (float)$dr['amount_disbursed'];
                $totalDisbursed += $amt;

                // Dynamic quarter derivation from disbursement_date
                $month = (int)date('n', strtotime($dr['disbursement_date']));
                $quarter = 'Q' . ceil($month / 3);

                $disbursements[] = [
                    'id' => (int)$dr['id'],
                    'schedule_id' => $dr['schedule_id'] ? (int)$dr['schedule_id'] : null,
                    'office_id' => (int)$dr['office_id'],
                    'office_code' => $dr['office_code'],
                    'office_name' => $dr['office_name'],
                    'paps' => $dr['paps'] ?? 'Unlinked / General',
                    'disbursement_date' => $dr['disbursement_date'],
                    'particulars' => $dr['particulars'],
                    'amount_disbursed' => $amt,
                    'received_by' => $dr['received_by'],
                    'chargeability' => $dr['chargeability'],
                    'quarter' => $quarter,
                    'remarks' => $dr['remarks'],
                    'created_at' => $dr['created_at'],
                    'updated_at' => $dr['updated_at']
                ];
            }

            sendResponse(true, 'Disbursements fetched successfully.', [
                'disbursements' => $disbursements,
                'total_disbursed' => $totalDisbursed,
                'total_count' => count($disbursements)
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch disbursements: ' . $e->getMessage(), null, null, 500);
        }
    }

    sendResponse(false, 'Invalid view parameter specified.', null, null, 400);
}

/**
 * POST Handler (Create Schedule or Disbursement)
 */
function handlePost(PDO $pdo, bool $canCrossOffice, int $userOfficeId, int $userId) {
    validateCsrfToken();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = trim($input['action'] ?? '');

    // 1. Create Disbursement Transaction
    if ($action === 'disbursement') {
        requirePermission('budget', 'create', $pdo);

        $scheduleId = !empty($input['schedule_id']) ? (int)$input['schedule_id'] : 0;
        $particulars = trim($input['particulars'] ?? '');
        $amountDisbursed = isset($input['amount_disbursed']) ? (float)$input['amount_disbursed'] : 0.0;
        $disbursementDate = trim($input['disbursement_date'] ?? '');
        $receivedBy = trim($input['received_by'] ?? '');
        $chargeability = trim($input['chargeability'] ?? '');
        $remarks = trim($input['remarks'] ?? '');

        $errors = [];
        if ($scheduleId <= 0) {
            $errors['schedule_id'] = 'Valid schedule selection is required.';
        }
        if (empty($particulars)) {
            $errors['particulars'] = 'Particulars description is required.';
        }
        if ($amountDisbursed <= 0) {
            $errors['amount_disbursed'] = 'Amount disbursed must be greater than zero.';
        }
        if (empty($disbursementDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $disbursementDate)) {
            $errors['disbursement_date'] = 'Valid disbursement date (YYYY-MM-DD) is required.';
        }

        if (!empty($errors)) {
            sendResponse(false, 'Validation failed.', null, $errors, 400);
        }

        // Load authoritative schedule from database
        $schedStmt = $pdo->prepare("
            SELECT id, office_id, paps, allocated_amount, deleted_at 
            FROM tbl_budget_schedules 
            WHERE id = :id LIMIT 1
        ");
        $schedStmt->execute([':id' => $scheduleId]);
        $schedule = $schedStmt->fetch();

        if (!$schedule || $schedule['deleted_at'] !== null) {
            sendResponse(false, 'Selected schedule does not exist or has been deleted.', null, ['schedule_id' => 'Schedule is inactive or invalid.'], 400);
        }

        $scheduleOfficeId = (int)$schedule['office_id'];

        // Non-admin office restriction
        if (!$canCrossOffice && $scheduleOfficeId !== $userOfficeId) {
            sendResponse(false, 'Forbidden. You cannot create disbursements for another office\'s schedule.', null, null, 403);
        }

        // Authoritative office derivation & mismatch check
        if (isset($input['office_id']) && !empty($input['office_id'])) {
            $submittedOfficeId = (int)$input['office_id'];
            if ($submittedOfficeId !== $scheduleOfficeId) {
                sendResponse(false, 'Submitted office does not match schedule office.', null, ['office_id' => 'Office mismatch with selected schedule.'], 400);
            }
        }

        $officeId = $scheduleOfficeId;

        try {
            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare("
                INSERT INTO tbl_budget_disbursements (
                    schedule_id, office_id, disbursement_date, particulars,
                    amount_disbursed, received_by, chargeability, remarks,
                    created_by, modified_by
                ) VALUES (
                    :schedule_id, :office_id, :disbursement_date, :particulars,
                    :amount_disbursed, :received_by, :chargeability, :remarks,
                    :created_by, :modified_by
                )
            ");
            $insertStmt->execute([
                ':schedule_id' => $scheduleId,
                ':office_id' => $officeId,
                ':disbursement_date' => $disbursementDate,
                ':particulars' => $particulars,
                ':amount_disbursed' => $amountDisbursed,
                ':received_by' => $receivedBy ?: null,
                ':chargeability' => $chargeability ?: null,
                ':remarks' => $remarks ?: null,
                ':created_by' => $userId,
                ':modified_by' => $userId
            ]);

            $newId = (int)$pdo->lastInsertId();

            auditLog([
                'action' => 'CREATE',
                'module_key' => 'budget',
                'entity_type' => 'budget_disbursement',
                'entity_id' => $newId,
                'description' => "Recorded fund disbursement #{$newId} of ₱" . number_format($amountDisbursed, 2) . " for office ID {$officeId}",
                'new_values' => [
                    'id' => $newId,
                    'schedule_id' => $scheduleId,
                    'office_id' => $officeId,
                    'disbursement_date' => $disbursementDate,
                    'amount_disbursed' => $amountDisbursed,
                    'particulars' => $particulars
                ]
            ], $pdo);

            $pdo->commit();

            sendResponse(true, 'Disbursement recorded successfully.', ['id' => $newId], null, 201);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendResponse(false, 'Failed to record disbursement: ' . $e->getMessage(), null, null, 500);
        }
    }

    // 2. Create Schedule Allocation Row
    if ($action === 'schedule') {
        requirePermission('budget', 'create', $pdo);

        $fiscalYear = !empty($input['fiscal_year']) ? (int)$input['fiscal_year'] : 2026;

        if (!$canCrossOffice && !empty($input['office_id']) && (int)$input['office_id'] !== $userOfficeId) {
            sendResponse(false, 'Forbidden. You cannot create schedules for another office.', null, ['office_id' => 'Cross-office creation not permitted.'], 403);
        }

        $targetOfficeId = $canCrossOffice && !empty($input['office_id']) ? (int)$input['office_id'] : $userOfficeId;
        $paps = trim($input['paps'] ?? '');
        $allocatedAmount = isset($input['allocated_amount']) ? (float)$input['allocated_amount'] : 0.0;
        $remarks = trim($input['remarks'] ?? '');

        $errors = [];
        if ($targetOfficeId <= 0) {
            $errors['office_id'] = 'Valid office assignment is required.';
        }
        if (empty($paps)) {
            $errors['paps'] = 'Programs, Activities, and Projects (PAPS) description is required.';
        }
        if ($allocatedAmount < 0) {
            $errors['allocated_amount'] = 'Allocated amount must be zero or positive.';
        }

        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $monthValues = [];
        foreach ($months as $m) {
            if (isset($input[$m])) {
                $val = (int)$input[$m];
                if ($val < 0 || $val > 12) {
                    $errors[$m] = "Planned monthly release count for " . strtoupper($m) . " must be between 0 and 12.";
                } else {
                    $monthValues[$m] = $val;
                }
            } else {
                $monthValues[$m] = 0;
            }
        }

        if (!empty($errors)) {
            sendResponse(false, 'Validation failed.', null, $errors, 400);
        }

        // Verify target office exists
        $offStmt = $pdo->prepare("SELECT id FROM tbl_offices WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $offStmt->execute([':id' => $targetOfficeId]);
        if (!$offStmt->fetch()) {
            sendResponse(false, 'Selected office does not exist in tbl_offices.', null, ['office_id' => 'Invalid office.'], 400);
        }

        try {
            $pdo->beginTransaction();

            $insStmt = $pdo->prepare("
                INSERT INTO tbl_budget_schedules (
                    fiscal_year, office_id, paps, allocated_amount,
                    jan, feb, mar, apr, may, jun,
                    jul, aug, sep, oct, nov, `dec`,
                    remarks, created_by, modified_by
                ) VALUES (
                    :fiscal_year, :office_id, :paps, :allocated_amount,
                    :jan, :feb, :mar, :apr, :may, :jun,
                    :jul, :aug, :sep, :oct, :nov, :dec,
                    :remarks, :created_by, :modified_by
                )
                ON DUPLICATE KEY UPDATE
                    allocated_amount = VALUES(allocated_amount),
                    jan = VALUES(jan), feb = VALUES(feb), mar = VALUES(mar), apr = VALUES(apr),
                    may = VALUES(may), jun = VALUES(jun), jul = VALUES(jul), aug = VALUES(aug),
                    sep = VALUES(sep), oct = VALUES(oct), nov = VALUES(nov), `dec` = VALUES(`dec`),
                    remarks = VALUES(remarks), modified_by = VALUES(modified_by), deleted_at = NULL
            ");

            $params = [
                ':fiscal_year' => $fiscalYear,
                ':office_id' => $targetOfficeId,
                ':paps' => $paps,
                ':allocated_amount' => $allocatedAmount,
                ':remarks' => $remarks ?: null,
                ':created_by' => $userId,
                ':modified_by' => $userId
            ];
            foreach ($months as $m) {
                $params[':' . $m] = $monthValues[$m];
            }

            $insStmt->execute($params);
            $newId = (int)$pdo->lastInsertId();
            if ($newId === 0) {
                // If ON DUPLICATE KEY UPDATE hit, retrieve ID
                $fetchIdStmt = $pdo->prepare("SELECT id FROM tbl_budget_schedules WHERE fiscal_year = :fy AND office_id = :oid AND paps = :paps LIMIT 1");
                $fetchIdStmt->execute([':fy' => $fiscalYear, ':oid' => $targetOfficeId, ':paps' => $paps]);
                $newId = (int)$fetchIdStmt->fetchColumn();
            }

            auditLog([
                'action' => 'CREATE',
                'module_key' => 'budget',
                'entity_type' => 'budget_schedule',
                'entity_id' => $newId,
                'description' => "Created/updated schedule line #{$newId} ({$paps}) for office ID {$targetOfficeId}",
                'new_values' => array_merge(['id' => $newId, 'paps' => $paps, 'allocated_amount' => $allocatedAmount], $monthValues)
            ], $pdo);

            $pdo->commit();

            sendResponse(true, 'Schedule line created successfully.', ['id' => $newId], null, 201);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendResponse(false, 'Failed to create schedule line: ' . $e->getMessage(), null, null, 500);
        }
    }

    sendResponse(false, 'Invalid action specified.', null, null, 400);
}

/**
 * PUT/PATCH Handler (Update Schedule or Disbursement)
 */
function handlePut(PDO $pdo, bool $canCrossOffice, int $userOfficeId, int $userId) {
    validateCsrfToken();
    requirePermission('budget', 'edit', $pdo);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $type = trim($input['type'] ?? 'schedule');

    if ($id <= 0) {
        sendResponse(false, 'Target record ID is required.', null, null, 400);
    }

    // 1. Update Disbursement
    if ($type === 'disbursement') {
        $stmt = $pdo->prepare("SELECT * FROM tbl_budget_disbursements WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch();

        if (!$existing) {
            sendResponse(false, 'Disbursement record not found or already deleted.', null, null, 404);
        }

        // Office restriction
        if (!$canCrossOffice && (int)$existing['office_id'] !== $userOfficeId) {
            sendResponse(false, 'Forbidden. You cannot modify disbursements belonging to another office.', null, null, 403);
        }

        $scheduleId = array_key_exists('schedule_id', $input) ? (int)$input['schedule_id'] : (int)$existing['schedule_id'];
        $particulars = array_key_exists('particulars', $input) ? trim($input['particulars']) : $existing['particulars'];
        $amountDisbursed = array_key_exists('amount_disbursed', $input) ? (float)$input['amount_disbursed'] : (float)$existing['amount_disbursed'];
        $disbursementDate = array_key_exists('disbursement_date', $input) ? trim($input['disbursement_date']) : $existing['disbursement_date'];
        $receivedBy = array_key_exists('received_by', $input) ? trim($input['received_by']) : $existing['received_by'];
        $chargeability = array_key_exists('chargeability', $input) ? trim($input['chargeability']) : $existing['chargeability'];
        $remarks = array_key_exists('remarks', $input) ? trim($input['remarks']) : $existing['remarks'];

        if ($scheduleId > 0) {
            $schedStmt = $pdo->prepare("SELECT id, office_id, deleted_at FROM tbl_budget_schedules WHERE id = :id LIMIT 1");
            $schedStmt->execute([':id' => $scheduleId]);
            $sched = $schedStmt->fetch();
            if (!$sched || $sched['deleted_at'] !== null) {
                sendResponse(false, 'Selected schedule is inactive or does not exist.', null, null, 400);
            }
            if (!$canCrossOffice && (int)$sched['office_id'] !== $userOfficeId) {
                sendResponse(false, 'Forbidden. Target schedule belongs to another office.', null, null, 403);
            }
            $targetOfficeId = (int)$sched['office_id'];
        } else {
            $targetOfficeId = (int)$existing['office_id'];
        }

        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare("
                UPDATE tbl_budget_disbursements
                SET schedule_id = :schedule_id,
                    office_id = :office_id,
                    disbursement_date = :disbursement_date,
                    particulars = :particulars,
                    amount_disbursed = :amount_disbursed,
                    received_by = :received_by,
                    chargeability = :chargeability,
                    remarks = :remarks,
                    modified_by = :modified_by,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':schedule_id' => $scheduleId ?: null,
                ':office_id' => $targetOfficeId,
                ':disbursement_date' => $disbursementDate,
                ':particulars' => $particulars,
                ':amount_disbursed' => $amountDisbursed,
                ':received_by' => $receivedBy ?: null,
                ':chargeability' => $chargeability ?: null,
                ':remarks' => $remarks ?: null,
                ':modified_by' => $userId,
                ':id' => $id
            ]);

            auditLog([
                'action' => 'UPDATE',
                'module_key' => 'budget',
                'entity_type' => 'budget_disbursement',
                'entity_id' => $id,
                'description' => "Updated disbursement transaction #{$id}",
                'old_values' => $existing,
                'new_values' => [
                    'schedule_id' => $scheduleId,
                    'office_id' => $targetOfficeId,
                    'amount_disbursed' => $amountDisbursed,
                    'particulars' => $particulars
                ]
            ], $pdo);

            $pdo->commit();
            sendResponse(true, 'Disbursement updated successfully.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendResponse(false, 'Failed to update disbursement: ' . $e->getMessage(), null, null, 500);
        }
    }

    // 2. Update Schedule
    if ($type === 'schedule') {
        $stmt = $pdo->prepare("SELECT * FROM tbl_budget_schedules WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch();

        if (!$existing) {
            sendResponse(false, 'Schedule record not found or already deleted.', null, null, 404);
        }

        if (!$canCrossOffice && (int)$existing['office_id'] !== $userOfficeId) {
            sendResponse(false, 'Forbidden. You cannot modify schedules belonging to another office.', null, null, 403);
        }

        $paps = array_key_exists('paps', $input) ? trim($input['paps']) : $existing['paps'];
        $allocatedAmount = array_key_exists('allocated_amount', $input) ? (float)$input['allocated_amount'] : (float)$existing['allocated_amount'];
        $remarks = array_key_exists('remarks', $input) ? trim($input['remarks']) : $existing['remarks'];

        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $monthValues = [];
        $errors = [];
        foreach ($months as $m) {
            if (array_key_exists($m, $input)) {
                $val = (int)$input[$m];
                if ($val < 0 || $val > 12) {
                    $errors[$m] = "Planned monthly release count for " . strtoupper($m) . " must be between 0 and 12.";
                } else {
                    $monthValues[$m] = $val;
                }
            } else {
                $monthValues[$m] = (int)$existing[$m];
            }
        }

        if (!empty($errors)) {
            sendResponse(false, 'Validation failed.', null, $errors, 400);
        }

        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare("
                UPDATE tbl_budget_schedules
                SET paps = :paps,
                    allocated_amount = :allocated_amount,
                    jan = :jan, feb = :feb, mar = :mar, apr = :apr,
                    may = :may, jun = :jun, jul = :jul, aug = :aug,
                    sep = :sep, oct = :oct, nov = :nov, `dec` = :dec,
                    remarks = :remarks,
                    modified_by = :modified_by,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $params = [
                ':paps' => $paps,
                ':allocated_amount' => $allocatedAmount,
                ':remarks' => $remarks ?: null,
                ':modified_by' => $userId,
                ':id' => $id
            ];
            foreach ($months as $m) {
                $params[':' . $m] = $monthValues[$m];
            }

            $upd->execute($params);

            auditLog([
                'action' => 'UPDATE',
                'module_key' => 'budget',
                'entity_type' => 'budget_schedule',
                'entity_id' => $id,
                'description' => "Updated budget schedule #{$id} ({$paps})",
                'old_values' => $existing,
                'new_values' => array_merge(['id' => $id, 'paps' => $paps, 'allocated_amount' => $allocatedAmount], $monthValues)
            ], $pdo);

            $pdo->commit();
            sendResponse(true, 'Budget schedule updated successfully.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendResponse(false, 'Failed to update budget schedule: ' . $e->getMessage(), null, null, 500);
        }
    }

    sendResponse(false, 'Invalid update type specified.', null, null, 400);
}

/**
 * DELETE Handler (Soft-delete Schedule or Disbursement)
 */
function handleDelete(PDO $pdo, bool $canCrossOffice, int $userOfficeId, int $userId) {
    validateCsrfToken();
    requirePermission('budget', 'delete', $pdo);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $id = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
    $type = trim($input['type'] ?? ($_GET['type'] ?? 'schedule'));

    if ($id <= 0) {
        sendResponse(false, 'Target record ID is required for deletion.', null, null, 400);
    }

    // 1. Delete Schedule (With Orphan Protection Rule)
    if ($type === 'schedule') {
        $stmt = $pdo->prepare("SELECT * FROM tbl_budget_schedules WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => $id]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            sendResponse(false, 'Schedule record not found or already deleted.', null, null, 404);
        }

        if (!$canCrossOffice && (int)$schedule['office_id'] !== $userOfficeId) {
            sendResponse(false, 'Forbidden. You cannot delete schedules belonging to another office.', null, null, 403);
        }

        // Orphan Protection Check: Cannot soft-delete if active linked disbursements exist
        $checkLinkedStmt = $pdo->prepare("
            SELECT COUNT(*) FROM tbl_budget_disbursements 
            WHERE schedule_id = :id AND deleted_at IS NULL
        ");
        $checkLinkedStmt->execute([':id' => $id]);
        $activeLinkedCount = (int)$checkLinkedStmt->fetchColumn();

        if ($activeLinkedCount > 0) {
            sendResponse(
                false,
                "Cannot delete schedule: {$activeLinkedCount} active linked disbursement(s) exist. Please reassign or delete linked disbursements first.",
                null,
                ['linked_disbursements' => $activeLinkedCount],
                422
            );
        }

        try {
            $pdo->beginTransaction();

            $delStmt = $pdo->prepare("UPDATE tbl_budget_schedules SET deleted_at = NOW(), modified_by = :user_id WHERE id = :id");
            $delStmt->execute([':user_id' => $userId, ':id' => $id]);

            auditLog([
                'action' => 'DELETE',
                'module_key' => 'budget',
                'entity_type' => 'budget_schedule',
                'entity_id' => $id,
                'description' => "Soft-deleted budget schedule #{$id} ({$schedule['paps']})",
                'old_values' => $schedule
            ], $pdo);

            $pdo->commit();
            sendResponse(true, 'Budget schedule soft-deleted successfully.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendResponse(false, 'Failed to delete budget schedule: ' . $e->getMessage(), null, null, 500);
        }
    }

    // 2. Delete Disbursement
    if ($type === 'disbursement') {
        $stmt = $pdo->prepare("SELECT * FROM tbl_budget_disbursements WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => $id]);
        $disbursement = $stmt->fetch();

        if (!$disbursement) {
            sendResponse(false, 'Disbursement record not found or already deleted.', null, null, 404);
        }

        if (!$canCrossOffice && (int)$disbursement['office_id'] !== $userOfficeId) {
            sendResponse(false, 'Forbidden. You cannot delete disbursements belonging to another office.', null, null, 403);
        }

        try {
            $pdo->beginTransaction();

            $delStmt = $pdo->prepare("UPDATE tbl_budget_disbursements SET deleted_at = NOW(), modified_by = :user_id WHERE id = :id");
            $delStmt->execute([':user_id' => $userId, ':id' => $id]);

            auditLog([
                'action' => 'DELETE',
                'module_key' => 'budget',
                'entity_type' => 'budget_disbursement',
                'entity_id' => $id,
                'description' => "Soft-deleted disbursement #{$id} of ₱" . number_format((float)$disbursement['amount_disbursed'], 2),
                'old_values' => $disbursement
            ], $pdo);

            $pdo->commit();
            sendResponse(true, 'Disbursement soft-deleted successfully.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendResponse(false, 'Failed to delete disbursement: ' . $e->getMessage(), null, null, 500);
        }
    }

    sendResponse(false, 'Invalid deletion type specified.', null, null, 400);
}

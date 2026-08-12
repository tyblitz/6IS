<?php
// backend/api/communications/index.php
// REST API Endpoint for 6IS Communications Module

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight CORS OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo);
        break;
    case 'PUT':
        handlePut($pdo);
        break;
    case 'DELETE':
        handleDelete($pdo);
        break;
    default:
        sendResponse(false, 'HTTP Method Not Allowed', null, null, 405);
}

/**
 * GET Handler
 */
function handleGet(PDO $pdo) {
    $view = isset($_GET['view']) ? trim($_GET['view']) : '';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // View: Active Reference Options for Forms
    if ($view === 'options') {
        try {
            $categories = $pdo->query("
                SELECT id, name, code, is_active 
                FROM tbl_communication_categories 
                WHERE is_active = 1 AND deleted_at IS NULL 
                ORDER BY name ASC
            ")->fetchAll();

            $purposes = $pdo->query("
                SELECT id, name, is_active 
                FROM tbl_communication_purposes 
                WHERE is_active = 1 AND deleted_at IS NULL 
                ORDER BY name ASC
            ")->fetchAll();

            $offices = $pdo->query("
                SELECT id, office_name, office_code, office_abbv, office_category, is_active 
                FROM tbl_offices 
                WHERE is_active = 1 AND deleted_at IS NULL 
                ORDER BY office_name ASC
            ")->fetchAll();

            sendResponse(true, 'Communication options fetched successfully.', [
                'categories' => $categories,
                'purposes' => $purposes,
                'offices' => $offices
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch dropdown options.', null, ['error' => $e->getMessage()], 500);
        }
    }

    // View: Active Reference Categories
    if ($view === 'categories') {
        try {
            $categories = $pdo->query("
                SELECT id, name, code, is_active 
                FROM tbl_communication_categories 
                WHERE is_active = 1 AND deleted_at IS NULL 
                ORDER BY name ASC
            ")->fetchAll();
            sendResponse(true, 'Categories fetched successfully.', $categories);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch categories.', null, ['error' => $e->getMessage()], 500);
        }
    }

    // View: Active Reference Purposes
    if ($view === 'purposes') {
        try {
            $purposes = $pdo->query("
                SELECT id, name, is_active 
                FROM tbl_communication_purposes 
                WHERE is_active = 1 AND deleted_at IS NULL 
                ORDER BY name ASC
            ")->fetchAll();
            sendResponse(true, 'Purposes fetched successfully.', $purposes);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch purposes.', null, ['error' => $e->getMessage()], 500);
        }
    }

    // View: Active Reference Offices
    if ($view === 'offices') {
        try {
            $offices = $pdo->query("
                SELECT id, office_name, office_code, office_abbv, office_category, is_active 
                FROM tbl_offices 
                WHERE is_active = 1 AND deleted_at IS NULL 
                ORDER BY office_name ASC
            ")->fetchAll();
            sendResponse(true, 'Offices fetched successfully.', $offices);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch offices.', null, ['error' => $e->getMessage()], 500);
        }
    }

    // View: Summary Reports Breakdown Counts
    if ($view === 'reports') {
        try {
            $byType = $pdo->query("
                SELECT communication_type, COUNT(*) as total 
                FROM tbl_communications 
                WHERE deleted_at IS NULL 
                GROUP BY communication_type
            ")->fetchAll();

            $byCategory = $pdo->query("
                SELECT cat.name as category_name, COUNT(c.id) as total 
                FROM tbl_communications c 
                LEFT JOIN tbl_communication_categories cat ON c.category_id = cat.id 
                WHERE c.deleted_at IS NULL 
                GROUP BY c.category_id, cat.name
            ")->fetchAll();

            $byPurpose = $pdo->query("
                SELECT pur.name as purpose_name, COUNT(c.id) as total 
                FROM tbl_communications c 
                LEFT JOIN tbl_communication_purposes pur ON c.purpose_id = pur.id 
                WHERE c.deleted_at IS NULL 
                GROUP BY c.purpose_id, pur.name
            ")->fetchAll();

            $byStatus = $pdo->query("
                SELECT status, COUNT(*) as total 
                FROM tbl_communications 
                WHERE deleted_at IS NULL 
                GROUP BY status
            ")->fetchAll();

            sendResponse(true, 'Reports summary fetched successfully.', [
                'by_type' => $byType,
                'by_category' => $byCategory,
                'by_purpose' => $byPurpose,
                'by_status' => $byStatus
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch reports summary.', null, ['error' => $e->getMessage()], 500);
        }
    }

    // View: Overview Summary (Today's Communications + Current Month Summary Counts)
    if ($view === 'overview') {
        try {
            $incomingMonthly = $pdo->query("
                SELECT COUNT(*) FROM tbl_communications 
                WHERE communication_type = 'Incoming' 
                  AND deleted_at IS NULL 
                  AND YEAR(communication_date) = YEAR(CURDATE()) 
                  AND MONTH(communication_date) = MONTH(CURDATE())
            ")->fetchColumn();

            $outgoingMonthly = $pdo->query("
                SELECT COUNT(*) FROM tbl_communications 
                WHERE communication_type = 'Outgoing' 
                  AND deleted_at IS NULL 
                  AND YEAR(communication_date) = YEAR(CURDATE()) 
                  AND MONTH(communication_date) = MONTH(CURDATE())
            ")->fetchColumn();

            $todaysIncomingStmt = $pdo->query("
                SELECT 
                    c.id, c.communication_type, c.subject, c.communication_date, c.status,
                    c.office_id, c.category_id, c.purpose_id,
                    o.office_name, o.office_code, o.office_abbv,
                    cat.name as category_name, pur.name as purpose_name
                FROM tbl_communications c
                LEFT JOIN tbl_offices o ON c.office_id = o.id
                LEFT JOIN tbl_communication_categories cat ON c.category_id = cat.id
                LEFT JOIN tbl_communication_purposes pur ON c.purpose_id = pur.id
                WHERE c.deleted_at IS NULL 
                  AND c.communication_type = 'Incoming'
                  AND c.communication_date = CURDATE()
                ORDER BY c.id DESC
            ");
            $todaysIncoming = $todaysIncomingStmt->fetchAll();

            $todaysOutgoingStmt = $pdo->query("
                SELECT 
                    c.id, c.communication_type, c.subject, c.communication_date, c.status,
                    c.office_id, c.category_id, c.purpose_id,
                    o.office_name, o.office_code, o.office_abbv,
                    cat.name as category_name, pur.name as purpose_name
                FROM tbl_communications c
                LEFT JOIN tbl_offices o ON c.office_id = o.id
                LEFT JOIN tbl_communication_categories cat ON c.category_id = cat.id
                LEFT JOIN tbl_communication_purposes pur ON c.purpose_id = pur.id
                WHERE c.deleted_at IS NULL 
                  AND c.communication_type = 'Outgoing'
                  AND c.communication_date = CURDATE()
                ORDER BY c.id DESC
            ");
            $todaysOutgoing = $todaysOutgoingStmt->fetchAll();

            sendResponse(true, 'Overview summary fetched successfully.', [
                'monthly_summary' => [
                    'incoming' => (int)$incomingMonthly,
                    'outgoing' => (int)$outgoingMonthly,
                    'total' => (int)$incomingMonthly + (int)$outgoingMonthly
                ],
                'todays_incoming' => $todaysIncoming,
                'todays_outgoing' => $todaysOutgoing
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch overview summary.', null, ['error' => $e->getMessage()], 500);
        }
    }

    // View: Single Communication Record + Activity Logs + Dynamic Age
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    c.*,
                    o.office_name,
                    o.office_code,
                    o.office_abbv,
                    cat.name as category_name,
                    cat.code as category_code,
                    pur.name as purpose_name
                FROM tbl_communications c
                LEFT JOIN tbl_offices o ON c.office_id = o.id
                LEFT JOIN tbl_communication_categories cat ON c.category_id = cat.id
                LEFT JOIN tbl_communication_purposes pur ON c.purpose_id = pur.id
                WHERE c.id = :id AND c.deleted_at IS NULL
            ");
            $stmt->execute([':id' => $id]);
            $record = $stmt->fetch();

            if (!$record) {
                sendResponse(false, 'Communication record not found.', null, null, 404);
            }

            // Fetch activity history
            $actStmt = $pdo->prepare("
                SELECT id, communication_id, activity_type, activity_date, remarks, created_at, created_by
                FROM tbl_communication_activities
                WHERE communication_id = :id
                ORDER BY activity_date DESC, created_at DESC
            ");
            $actStmt->execute([':id' => $id]);
            $activities = $actStmt->fetchAll();

            $record['activities'] = $activities;

            // Calculate dynamic age from latest activity_date
            if (!empty($activities)) {
                $latestActivityDate = $activities[0]['activity_date'];
                $record['latest_activity_date'] = $latestActivityDate;
                $diff = (new DateTime())->diff(new DateTime($latestActivityDate));
                $record['age_days'] = (int)$diff->days;
            } else {
                $record['latest_activity_date'] = $record['communication_date'];
                $diff = (new DateTime())->diff(new DateTime($record['communication_date'] ?? $record['created_at']));
                $record['age_days'] = (int)$diff->days;
            }

            sendResponse(true, 'Communication details fetched successfully.', $record);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to fetch communication details.', null, ['error' => $e->getMessage()], 500);
        }
    }

    // View: Communications List Query with Filters & Search
    $where = ["c.deleted_at IS NULL"];
    $params = [];

    if (!empty($_GET['type'])) {
        $where[] = "c.communication_type = :type";
        $params[':type'] = trim($_GET['type']);
    }

    if (!empty($_GET['office_id']) && (int)$_GET['office_id'] > 0) {
        $where[] = "c.office_id = :office_id";
        $params[':office_id'] = (int)$_GET['office_id'];
    }

    if (!empty($_GET['category_id']) && (int)$_GET['category_id'] > 0) {
        $where[] = "c.category_id = :category_id";
        $params[':category_id'] = (int)$_GET['category_id'];
    }

    if (!empty($_GET['purpose_id']) && (int)$_GET['purpose_id'] > 0) {
        $where[] = "c.purpose_id = :purpose_id";
        $params[':purpose_id'] = (int)$_GET['purpose_id'];
    }

    if (!empty($_GET['status'])) {
        $where[] = "c.status = :status";
        $params[':status'] = trim($_GET['status']);
    }

    if (!empty($_GET['search'])) {
        $where[] = "(c.subject LIKE :search OR o.office_name LIKE :search OR cat.name LIKE :search OR pur.name LIKE :search)";
        $params[':search'] = '%' . trim($_GET['search']) . '%';
    }

    $whereSql = implode(' AND ', $where);

    try {
        $sql = "
            SELECT 
                c.id,
                c.communication_type,
                c.office_id,
                c.category_id,
                c.purpose_id,
                c.subject,
                c.communication_date,
                c.status,
                c.created_at,
                c.updated_at,
                o.office_name,
                o.office_code,
                o.office_abbv,
                cat.name as category_name,
                cat.code as category_code,
                pur.name as purpose_name,
                (
                    SELECT MAX(act.activity_date) 
                    FROM tbl_communication_activities act 
                    WHERE act.communication_id = c.id
                ) as latest_activity_date
            FROM tbl_communications c
            LEFT JOIN tbl_offices o ON c.office_id = o.id
            LEFT JOIN tbl_communication_categories cat ON c.category_id = cat.id
            LEFT JOIN tbl_communication_purposes pur ON c.purpose_id = pur.id
            WHERE {$whereSql}
            ORDER BY c.communication_date DESC, c.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        // Calculate dynamic age_days for each record using latest_activity_date
        $today = new DateTime();
        foreach ($records as &$rec) {
            $refDateStr = !empty($rec['latest_activity_date']) ? $rec['latest_activity_date'] : ($rec['communication_date'] ?? $rec['created_at']);
            if ($refDateStr) {
                $refDate = new DateTime($refDateStr);
                $diff = $today->diff($refDate);
                $rec['age_days'] = (int)$diff->days;
            } else {
                $rec['age_days'] = 0;
            }
        }
        unset($rec);

        sendResponse(true, 'Communications list fetched successfully.', $records);
    } catch (Exception $e) {
        sendResponse(false, 'Failed to fetch communications list.', null, ['error' => $e->getMessage()], 500);
    }
}

/**
 * POST Handler (Create Communication or Add Process Activity)
 */
function handlePost(PDO $pdo) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data) {
        sendResponse(false, 'Invalid JSON payload.', null, null, 400);
    }

    $action = isset($_GET['action']) ? trim($_GET['action']) : '';

    // Action: Add Process Activity Log Entry
    if ($action === 'add_activity') {
        $communicationId = isset($data['communication_id']) ? (int)$data['communication_id'] : 0;
        $activityType = isset($data['activity_type']) ? trim($data['activity_type']) : '';
        $activityDate = !empty($data['activity_date']) ? trim($data['activity_date']) : date('Y-m-d H:i:s');
        $remarks = isset($data['remarks']) ? trim($data['remarks']) : null;

        if ($communicationId <= 0 || empty($activityType)) {
            sendResponse(false, 'Communication ID and Activity Type are required.', null, null, 400);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_communication_activities
                (communication_id, activity_type, activity_date, remarks, created_at, created_by)
                VALUES
                (:communication_id, :activity_type, :activity_date, :remarks, NOW(), 1)
            ");
            $stmt->execute([
                ':communication_id' => $communicationId,
                ':activity_type' => $activityType,
                ':activity_date' => $activityDate,
                ':remarks' => $remarks
            ]);

            sendResponse(true, 'Activity log entry added successfully.', ['id' => $pdo->lastInsertId()], null, 201);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to add activity entry.', null, ['db' => $e->getMessage()], 500);
        }
    }

    // Default: Create Communication Record
    $errors = validatePayload($pdo, $data);
    if (!empty($errors)) {
        sendResponse(false, 'Validation failed.', null, $errors, 400);
    }

    $commType = trim($data['communication_type']);
    $officeId = (int)$data['office_id'];
    $categoryId = (int)$data['category_id'];
    $purposeId = (int)$data['purpose_id'];
    $subject = isset($data['subject']) ? trim($data['subject']) : '';
    $commDate = !empty($data['communication_date']) ? trim($data['communication_date']) : date('Y-m-d');
    $status = !empty($data['status']) ? trim($data['status']) : 'Pending';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO tbl_communications
            (communication_type, office_id, category_id, purpose_id, subject, communication_date, status, created_at, updated_at, created_by, modified_by)
            VALUES
            (:communication_type, :office_id, :category_id, :purpose_id, :subject, :communication_date, :status, NOW(), NOW(), 1, 1)
        ");
        $stmt->execute([
            ':communication_type' => $commType,
            ':office_id' => $officeId,
            ':category_id' => $categoryId,
            ':purpose_id' => $purposeId,
            ':subject' => $subject,
            ':communication_date' => $commDate,
            ':status' => $status
        ]);

        $newId = $pdo->lastInsertId();

        // Activity Creation Rule 1: Auto-create initial activity_type = 'Logged'
        $actStmt = $pdo->prepare("
            INSERT INTO tbl_communication_activities
            (communication_id, activity_type, activity_date, remarks, created_at, created_by)
            VALUES
            (:communication_id, 'Logged', NOW(), :remarks, NOW(), 1)
        ");
        $actStmt->execute([
            ':communication_id' => $newId,
            ':remarks' => 'Communication record logged into the system.'
        ]);

        $pdo->commit();
        sendResponse(true, 'Communication recorded successfully.', ['id' => $newId], null, 201);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Failed to record communication.', null, ['db' => $e->getMessage()], 500);
    }
}

/**
 * PUT Handler (Update Communication Record)
 */
function handlePut(PDO $pdo) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if ($id <= 0 && isset($data['id'])) {
        $id = (int)$data['id'];
    }

    if ($id <= 0) {
        sendResponse(false, 'Record ID is required for update.', null, null, 400);
    }

    // Check existing active record
    $checkStmt = $pdo->prepare("SELECT id, status FROM tbl_communications WHERE id = :id AND deleted_at IS NULL");
    $checkStmt->execute([':id' => $id]);
    $existing = $checkStmt->fetch();

    if (!$existing) {
        sendResponse(false, 'Communication record not found.', null, null, 404);
    }

    $errors = validatePayload($pdo, $data, true);
    if (!empty($errors)) {
        sendResponse(false, 'Validation failed.', null, $errors, 400);
    }

    $commType = trim($data['communication_type']);
    $officeId = (int)$data['office_id'];
    $categoryId = (int)$data['category_id'];
    $purposeId = (int)$data['purpose_id'];
    $subject = isset($data['subject']) ? trim($data['subject']) : '';
    $commDate = !empty($data['communication_date']) ? trim($data['communication_date']) : date('Y-m-d');
    $newStatus = !empty($data['status']) ? trim($data['status']) : 'Pending';

    $oldStatus = $existing['status'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE tbl_communications SET
                communication_type = :communication_type,
                office_id = :office_id,
                category_id = :category_id,
                purpose_id = :purpose_id,
                subject = :subject,
                communication_date = :communication_date,
                status = :status,
                updated_at = NOW(),
                modified_by = 1
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $id,
            ':communication_type' => $commType,
            ':office_id' => $officeId,
            ':category_id' => $categoryId,
            ':purpose_id' => $purposeId,
            ':subject' => $subject,
            ':communication_date' => $commDate,
            ':status' => $newStatus
        ]);

        // Activity Creation Rule 2: Create activity ONLY IF status changed
        if ($oldStatus !== $newStatus) {
            $actStmt = $pdo->prepare("
                INSERT INTO tbl_communication_activities
                (communication_id, activity_type, activity_date, remarks, created_at, created_by)
                VALUES
                (:communication_id, :activity_type, NOW(), :remarks, NOW(), 1)
            ");
            $actStmt->execute([
                ':communication_id' => $id,
                ':activity_type' => "Status changed to {$newStatus}",
                ':remarks' => "Status updated from {$oldStatus} to {$newStatus}."
            ]);
        }

        $pdo->commit();
        sendResponse(true, 'Communication updated successfully.', ['id' => $id]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Failed to update communication record.', null, ['db' => $e->getMessage()], 500);
    }
}

/**
 * DELETE Handler (Soft Delete)
 */
function handleDelete(PDO $pdo) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        sendResponse(false, 'Record ID is required for deletion.', null, null, 400);
    }

    try {
        // Soft delete communication record; activity history rows in tbl_communication_activities remain intact
        $stmt = $pdo->prepare("
            UPDATE tbl_communications 
            SET deleted_at = NOW(), modified_by = 1 
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            sendResponse(false, 'Record not found or already deleted.', null, null, 404);
        }

        sendResponse(true, 'Communication deleted successfully.', ['id' => $id]);
    } catch (Exception $e) {
        sendResponse(false, 'Failed to delete communication record.', null, ['db' => $e->getMessage()], 500);
    }
}

/**
 * Payload Validation Helper
 */
function validatePayload(PDO $pdo, $data, $isUpdate = false) {
    $errors = [];

    if (empty($data['communication_type']) || !in_array($data['communication_type'], ['Incoming', 'Outgoing'])) {
        $errors['communication_type'] = 'Valid Communication Type (Incoming or Outgoing) is required.';
    }

    if (empty($data['office_id']) || (int)$data['office_id'] <= 0) {
        $errors['office_id'] = 'Office selection is required.';
    } else {
        $check = $pdo->prepare("SELECT id FROM tbl_offices WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
        $check->execute([':id' => (int)$data['office_id']]);
        if (!$check->fetch()) {
            $errors['office_id'] = 'Selected office is invalid or inactive.';
        }
    }

    if (empty($data['category_id']) || (int)$data['category_id'] <= 0) {
        $errors['category_id'] = 'Communication category is required.';
    } else {
        $check = $pdo->prepare("SELECT id FROM tbl_communication_categories WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
        $check->execute([':id' => (int)$data['category_id']]);
        if (!$check->fetch()) {
            $errors['category_id'] = 'Selected category is invalid or inactive.';
        }
    }

    if (empty($data['purpose_id']) || (int)$data['purpose_id'] <= 0) {
        $errors['purpose_id'] = 'Communication purpose is required.';
    } else {
        $check = $pdo->prepare("SELECT id FROM tbl_communication_purposes WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
        $check->execute([':id' => (int)$data['purpose_id']]);
        if (!$check->fetch()) {
            $errors['purpose_id'] = 'Selected purpose is invalid or inactive.';
        }
    }

    if (empty($data['subject']) || strlen(trim($data['subject'])) === 0) {
        $errors['subject'] = 'Subject is required.';
    }

    if (empty($data['communication_date'])) {
        $errors['communication_date'] = 'Communication date is required.';
    }

    return $errors;
}

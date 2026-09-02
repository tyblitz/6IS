<?php
// backend/api/calendar/index.php
// Dedicated REST API for 6IS Scheduled Calendar Activities Hub

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
// CORS & Credential Headers
$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require authenticated session and active module for all calendar requests
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/modules.php';
requireAuth();
requireModuleActive('calendar');

$host = 'localhost';
$dbname = 'db_ict_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

function sendJsonResponse(array $payload, int $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

// ============================================================
// GET ?action=types — Return Calendar Event Types Reference List
// ============================================================
if ($method === 'GET' && $action === 'types') {
    $stmt = $pdo->query("SELECT * FROM tbl_calendar_event_types WHERE deleted_at IS NULL AND is_active = 1 ORDER BY id ASC");
    $types = $stmt->fetchAll();
    sendJsonResponse(['success' => true, 'data' => $types]);
}

// ============================================================
// GET — Authoritative Calendar Activities Endpoint
// ============================================================
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    // Fetch single activity with details & audit logs
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT e.*, 
                   t.type_name AS event_type_name, 
                   t.type_code AS event_type_code_ref,
                   o.office_name,
                   o.office_abbv
            FROM tbl_calendar_events e
            LEFT JOIN tbl_calendar_event_types t ON e.event_type_id = t.id
            LEFT JOIN tbl_offices o ON e.office_id = o.id
            WHERE e.id = ? AND e.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $event = $stmt->fetch();
        if ($event) {
            // Get accomplishment link
            $accStmt = $pdo->prepare("SELECT id FROM tbl_accomplishments WHERE calendar_event_id = ? AND deleted_at IS NULL LIMIT 1");
            $accStmt->execute([$event['id']]);
            $accRow = $accStmt->fetch();
            $event['accomplishment_id'] = $accRow ? intval($accRow['id']) : null;
            $event['has_accomplishment'] = (bool)$accRow;

            // Get reschedule history
            $reschStmt = $pdo->prepare("SELECT * FROM tbl_calendar_event_reschedules WHERE calendar_event_id = ? ORDER BY created_at ASC");
            $reschStmt->execute([$event['id']]);
            $event['reschedules'] = $reschStmt->fetchAll();
            $event['reschedule_count'] = count($event['reschedules']);

            // Get status history
            $statStmt = $pdo->prepare("SELECT * FROM tbl_calendar_event_status_history WHERE calendar_event_id = ? ORDER BY created_at ASC");
            $statStmt->execute([$event['id']]);
            $event['status_history'] = $statStmt->fetchAll();

            sendJsonResponse(['success' => true, 'data' => $event]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Event not found'], 404);
        }
    }

    $targetDate = $_GET['date'] ?? date('Y-m-d');
    $startDate = $_GET['start'] ?? $_GET['start_date'] ?? null;
    $endDate = $_GET['end'] ?? $_GET['end_date'] ?? null;
    $view = $_GET['view'] ?? 'month';
    $filterStatus = $_GET['status'] ?? 'all';
    $filterTypeId = intval($_GET['type_id'] ?? 0);
    $searchQuery = trim($_GET['search'] ?? '');

    if (!$startDate || !$endDate) {
        if ($view === 'month') {
            $year = intval($_GET['year'] ?? date('Y', strtotime($targetDate)));
            $month = intval($_GET['month'] ?? date('m', strtotime($targetDate)));
            $firstDayOfMonth = sprintf('%04d-%02d-01', $year, $month);
            $startDate = date('Y-m-d', strtotime('last sunday', strtotime($firstDayOfMonth)));
            if (date('w', strtotime($firstDayOfMonth)) == 0) {
                $startDate = $firstDayOfMonth;
            }
            $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
            $endDate = date('Y-m-d', strtotime('next saturday', strtotime($lastDayOfMonth)));
            if (date('w', strtotime($lastDayOfMonth)) == 6) {
                $endDate = $lastDayOfMonth;
            }
        } elseif ($view === 'week') {
            $dt = new DateTime($targetDate);
            $dayOfWeek = (int)$dt->format('w'); // 0 = Sunday, 6 = Saturday
            $sunday = (clone $dt)->modify("-{$dayOfWeek} days");
            $saturday = (clone $sunday)->modify('+6 days');
            $startDate = $sunday->format('Y-m-d');
            $endDate = $saturday->format('Y-m-d');
        } elseif ($view === 'day') {
            $startDate = $targetDate;
            $endDate = $targetDate;
        } else {
            $startDate = sprintf('%04d-%02d-01', date('Y'), date('m'));
            $endDate = date('Y-m-t', strtotime($startDate));
        }
    }

    $activities = [];
    $startDateTimeFilter = $startDate . ' 00:00:00';
    $endDateTimeFilter = $endDate . ' 23:59:59';

    // ----------------------------------------------------
    // AUTHORITATIVE SOURCE: Calendar Activities (tbl_calendar_events)
    // ----------------------------------------------------
    $sql = "SELECT e.*, 
                   t.type_name AS event_type_name,
                   t.type_code AS event_type_code_ref,
                   o.office_name,
                   o.office_abbv,
                   (SELECT id FROM tbl_accomplishments a WHERE a.calendar_event_id = e.id AND a.deleted_at IS NULL LIMIT 1) AS linked_accomplishment_id,
                   (SELECT COUNT(*) FROM tbl_calendar_event_reschedules r WHERE r.calendar_event_id = e.id) AS reschedule_count
            FROM tbl_calendar_events e
            LEFT JOIN tbl_calendar_event_types t ON e.event_type_id = t.id
            LEFT JOIN tbl_offices o ON e.office_id = o.id
            WHERE e.deleted_at IS NULL
              AND (
                (e.start_datetime <= :end_dt AND e.end_datetime >= :start_dt)
                OR (e.event_date BETWEEN :start_date AND :end_date)
                OR (e.start_datetime BETWEEN :start_dt2 AND :end_dt2)
              )";
    
    $params = [
        ':end_dt' => $endDateTimeFilter,
        ':start_dt' => $startDateTimeFilter,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
        ':start_dt2' => $startDateTimeFilter,
        ':end_dt2' => $endDateTimeFilter
    ];

    if ($filterStatus !== 'all') {
        $sql .= " AND e.status = :status";
        $params[':status'] = $filterStatus;
    }

    if ($filterTypeId > 0) {
        $sql .= " AND e.event_type_id = :type_id";
        $params[':type_id'] = $filterTypeId;
    }

    if ($searchQuery !== '') {
        $sql .= " AND (e.title LIKE :search OR o.office_name LIKE :search OR o.office_abbv LIKE :search OR t.type_code LIKE :search OR t.type_name LIKE :search)";
        $params[':search'] = '%' . $searchQuery . '%';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    foreach ($events as $evt) {
        $eventDate = $evt['event_date'];
        if (!$eventDate && !empty($evt['start_datetime'])) {
            $eventDate = date('Y-m-d', strtotime($evt['start_datetime']));
        }
        
        $catCode = !empty($evt['event_type_code_ref']) 
            ? $evt['event_type_code_ref'] 
            : 'CONF';

        $accId = $evt['linked_accomplishment_id'] ? intval($evt['linked_accomplishment_id']) : null;

        $activities[] = [
            'id' => 'cal_' . $evt['id'],
            'source' => 'calendar_event',
            'source_id' => intval($evt['id']),
            'title' => $evt['title'],
            'date' => $eventDate,
            'start_datetime' => $evt['start_datetime'] ?? ($eventDate . ' ' . ($evt['event_time'] ?? '09:00:00')),
            'end_datetime' => $evt['end_datetime'] ?? ($eventDate . ' ' . ($evt['event_time'] ? date('H:i:s', strtotime($evt['event_time'] . ' +1 hour')) : '10:00:00')),
            'all_day' => (bool)$evt['all_day'],
            'office_id' => $evt['office_id'] ? intval($evt['office_id']) : 1,
            'office_name' => $evt['office_name'] ?? 'General Headquarters',
            'office_abbv' => $evt['office_abbv'] ?? 'HQ',
            'status' => $evt['status'] ?? 'Scheduled',
            'category' => $evt['event_type_name'] ?? 'Conference',
            'category_code' => $catCode,
            'event_type_id' => $evt['event_type_id'] ? intval($evt['event_type_id']) : 2,
            'event_type_code' => $catCode,
            'accomplishment_id' => $accId,
            'has_accomplishment' => (bool)$accId,
            'reschedule_count' => intval($evt['reschedule_count']),
            'metadata' => [
                'created_by' => $evt['created_by'],
                'created_at' => $evt['created_at']
            ]
        ];
    }

    // Sort chronologically by date and start_datetime
    usort($activities, function ($a, $b) {
        if ($a['date'] === $b['date']) {
            $timeA = $a['start_datetime'] ?? '00:00:00';
            $timeB = $b['start_datetime'] ?? '00:00:00';
            return strcmp($timeA, $timeB);
        }
        return strcmp($a['date'], $b['date']);
    });

    sendJsonResponse([
        'success' => true,
        'count' => count($activities),
        'start_date' => $startDate,
        'end_date' => $endDate,
        'week_start' => $startDate,
        'week_end' => $endDate,
        'data' => $activities
    ]);
}

// ============================================================
// PATCH ?action=status — Update Event Status
// ============================================================
if ($method === 'PATCH' && $action === 'status') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    $eventId = intval($input['id'] ?? 0);
    $newStatus = trim($input['status'] ?? '');
    $reason = trim($input['reason'] ?? '');

    if (!$eventId || !$newStatus) {
        sendJsonResponse(['success' => false, 'message' => 'Event ID and new status are required.'], 400);
    }

    $allowedTransitions = [
        'Scheduled'   => ['In Progress', 'Accomplished', 'Canceled', 'Postponed'],
        'In Progress' => ['Accomplished', 'Canceled', 'Postponed'],
        'Postponed'   => ['Canceled'],
        'Canceled'    => [],
        'Accomplished'=> []
    ];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, status FROM tbl_calendar_events WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if (!$event) {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'Calendar event not found.'], 404);
        }

        $currentStatus = $event['status'];

        if ($currentStatus === $newStatus) {
            $pdo->rollBack();
            sendJsonResponse(['success' => true, 'message' => 'Status is already set to ' . $newStatus, 'data' => $event]);
        }

        $validNext = $allowedTransitions[$currentStatus] ?? [];
        if (!in_array($newStatus, $validNext)) {
            $pdo->rollBack();
            sendJsonResponse([
                'success' => false,
                'message' => "Invalid status transition from '{$currentStatus}' to '{$newStatus}'."
            ], 400);
        }

        // Update status
        $upStmt = $pdo->prepare("UPDATE tbl_calendar_events SET status = ?, updated_at = NOW() WHERE id = ?");
        $upStmt->execute([$newStatus, $eventId]);

        // Insert into tbl_calendar_event_status_history
        $histStmt = $pdo->prepare("
            INSERT INTO tbl_calendar_event_status_history (calendar_event_id, previous_status, new_status, reason, changed_by, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $histStmt->execute([$eventId, $currentStatus, $newStatus, $reason]);

        $pdo->commit();

        sendJsonResponse([
            'success' => true,
            'message' => "Status updated successfully to '{$newStatus}'.",
            'data' => [
                'id' => $eventId,
                'previous_status' => $currentStatus,
                'new_status' => $newStatus
            ]
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendJsonResponse(['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()], 500);
    }
}

// ============================================================
// PATCH ?action=reschedule — Reschedule Event Datetime
// ============================================================
if ($method === 'PATCH' && $action === 'reschedule') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    $eventId = intval($input['id'] ?? 0);
    $newStartStr = trim($input['new_start_datetime'] ?? '');
    $newEndStr = trim($input['new_end_datetime'] ?? '');
    $reason = trim($input['reason'] ?? '');

    if (!$eventId || !$newStartStr || !$newEndStr) {
        sendJsonResponse(['success' => false, 'message' => 'Event ID, new start datetime, and new end datetime are required.'], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, status, event_date, event_time, start_datetime, end_datetime FROM tbl_calendar_events WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if (!$event) {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'Calendar event not found.'], 404);
        }

        $currentStatus = $event['status'];

        if ($currentStatus === 'Accomplished' || $currentStatus === 'Canceled') {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => "Cannot reschedule an event with status '{$currentStatus}'."], 400);
        }

        $prevStart = $event['start_datetime'] ?? ($event['event_date'] . ' ' . ($event['event_time'] ?? '09:00:00'));
        $prevEnd = $event['end_datetime'] ?? date('Y-m-d H:i:s', strtotime($prevStart . ' +1 hour'));

        $newDate = date('Y-m-d', strtotime($newStartStr));
        $newTime = date('H:i:s', strtotime($newStartStr));

        // 1. Record Rescheduling History (ALWAYS)
        $reschStmt = $pdo->prepare("
            INSERT INTO tbl_calendar_event_reschedules (calendar_event_id, previous_start_datetime, previous_end_datetime, new_start_datetime, new_end_datetime, reason, changed_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $reschStmt->execute([$eventId, $prevStart, $prevEnd, $newStartStr, $newEndStr, $reason]);

        // 2. Update Event Current Schedule
        $newStatus = $currentStatus;
        if ($currentStatus === 'Postponed') {
            $newStatus = 'Scheduled';
        }

        $upStmt = $pdo->prepare("
            UPDATE tbl_calendar_events 
            SET event_date = ?, event_time = ?, start_datetime = ?, end_datetime = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $upStmt->execute([$newDate, $newTime, $newStartStr, $newEndStr, $newStatus, $eventId]);

        // 3. Record Status History ONLY if status actually changed (e.g. Postponed -> Scheduled)
        if ($currentStatus !== $newStatus) {
            $histStmt = $pdo->prepare("
                INSERT INTO tbl_calendar_event_status_history (calendar_event_id, previous_status, new_status, reason, changed_by, created_at)
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $histStmt->execute([$eventId, $currentStatus, $newStatus, "Rescheduled event from {$currentStatus} to {$newStatus}"]);
        }

        $pdo->commit();

        sendJsonResponse([
            'success' => true,
            'message' => 'Event rescheduled successfully.',
            'data' => [
                'id' => $eventId,
                'new_date' => $newDate,
                'new_start_datetime' => $newStartStr,
                'new_end_datetime' => $newEndStr,
                'status' => $newStatus
            ]
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendJsonResponse(['success' => false, 'message' => 'Failed to reschedule event: ' . $e->getMessage()], 500);
    }
}

// ============================================================
// POST ?action=restore — Restore Canceled Event
// ============================================================
if ($method === 'POST' && $action === 'restore') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    $eventId = intval($input['id'] ?? 0);
    $reason = trim($input['reason'] ?? 'Restored canceled event');

    if (!$eventId) {
        sendJsonResponse(['success' => false, 'message' => 'Event ID is required.'], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, status FROM tbl_calendar_events WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if (!$event) {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'Calendar event not found.'], 404);
        }

        if ($event['status'] !== 'Canceled') {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'Only canceled events can be restored.'], 400);
        }

        $upStmt = $pdo->prepare("UPDATE tbl_calendar_events SET status = 'Scheduled', updated_at = NOW() WHERE id = ?");
        $upStmt->execute([$eventId]);

        $histStmt = $pdo->prepare("
            INSERT INTO tbl_calendar_event_status_history (calendar_event_id, previous_status, new_status, reason, changed_by, created_at)
            VALUES (?, 'Canceled', 'Scheduled', ?, 1, NOW())
        ");
        $histStmt->execute([$eventId, $reason]);

        $pdo->commit();

        sendJsonResponse(['success' => true, 'message' => 'Canceled event restored to Scheduled successfully.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendJsonResponse(['success' => false, 'message' => 'Failed to restore event: ' . $e->getMessage()], 500);
    }
}

// ============================================================
// POST ?action=create_accomplishment — Transactional Link
// ============================================================
if ($method === 'POST' && $action === 'create_accomplishment') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    $eventId = intval($input['calendar_event_id'] ?? 0);
    $description = trim($input['description'] ?? ($input['title'] ?? ''));
    $officeId = intval($input['office_id'] ?? 1);
    $categoryId = intval($input['category_id'] ?? 1);
    $date = $input['date'] ?? ($input['date_started'] ?? date('Y-m-d'));
    $remarks = trim($input['remarks'] ?? '');

    if (!$eventId || !$description) {
        sendJsonResponse(['success' => false, 'message' => 'Calendar event ID and accomplishment description are required.'], 400);
    }

    try {
        $pdo->beginTransaction();

        // 1. Lock calendar event
        $evtStmt = $pdo->prepare("SELECT id, status, office_id FROM tbl_calendar_events WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
        $evtStmt->execute([$eventId]);
        $event = $evtStmt->fetch();

        if (!$event) {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'Calendar event not found.'], 404);
        }

        if ($event['status'] !== 'Accomplished') {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'Only accomplished calendar events can generate Daily Accomplishments.'], 400);
        }

        $finalOfficeId = $event['office_id'] ? intval($event['office_id']) : $officeId;

        // 2. Check for duplicate creation
        $chkStmt = $pdo->prepare("SELECT id FROM tbl_accomplishments WHERE calendar_event_id = ? AND deleted_at IS NULL FOR UPDATE");
        $chkStmt->execute([$eventId]);
        $existingAcc = $chkStmt->fetch();

        if ($existingAcc) {
            $pdo->rollBack();
            sendJsonResponse([
                'success' => false,
                'message' => 'An accomplishment has already been created for this event.',
                'accomplishment_id' => intval($existingAcc['id'])
            ], 409); // Conflict HTTP 409
        }

        // 3. Insert accomplishment record with calendar_event_id link
        $insStmt = $pdo->prepare("
            INSERT INTO tbl_accomplishments 
            (office_id, category_id, date, description, remarks, calendar_event_id, created_at, updated_at, created_by, modified_by)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 1, 1)
        ");
        $insStmt->execute([
            $finalOfficeId, $categoryId, $date, $description, $remarks, $eventId
        ]);

        $accId = intval($pdo->lastInsertId());

        $pdo->commit();

        sendJsonResponse([
            'success' => true,
            'message' => 'Daily Accomplishment created and linked successfully.',
            'accomplishment_id' => $accId
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendJsonResponse(['success' => false, 'message' => 'Failed creating accomplishment: ' . $e->getMessage()], 500);
    }
}

// ============================================================
// POST — Create Calendar Activity
// ============================================================
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    $title = trim($input['title'] ?? '');
    $eventDate = $input['event_date'] ?? date('Y-m-d');
    $eventTime = $input['event_time'] ?? '09:00:00';
    $eventTypeId = intval($input['event_type_id'] ?? 2);
    $officeId = intval($input['office_id'] ?? 1);
    $allDay = !empty($input['all_day']) ? 1 : 0;

    if (!$title) {
        sendJsonResponse(['success' => false, 'message' => 'Event title is required'], 400);
    }

    $startDt = $input['start_datetime'] ?? ($eventDate . ' ' . $eventTime);
    $endDt = $input['end_datetime'] ?? date('Y-m-d H:i:s', strtotime($startDt . ' +1 hour'));

    $stmt = $pdo->prepare("
        INSERT INTO tbl_calendar_events 
        (title, event_date, event_time, start_datetime, end_datetime, all_day, office_id, event_type_id, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', 1, NOW(), NOW())
    ");
    $stmt->execute([$title, $eventDate, $eventTime, $startDt, $endDt, $allDay, $officeId, $eventTypeId]);
    $newId = intval($pdo->lastInsertId());

    // Insert initial status history row
    $hist = $pdo->prepare("
        INSERT INTO tbl_calendar_event_status_history (calendar_event_id, previous_status, new_status, reason, changed_by, created_at)
        VALUES (?, NULL, 'Scheduled', 'Initial activity creation', 1, NOW())
    ");
    $hist->execute([$newId]);

    sendJsonResponse([
        'success' => true,
        'message' => 'Calendar activity created successfully',
        'data' => [
            'id' => $newId,
            'title' => $title,
            'event_date' => $eventDate,
            'office_id' => $officeId,
            'event_type_id' => $eventTypeId,
            'status' => 'Scheduled'
        ]
    ]);
}

// ============================================================
// PUT — Update Calendar Activity Fields
// ============================================================
if ($method === 'PUT') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    $id = intval($input['id'] ?? 0);
    $title = trim($input['title'] ?? '');

    if (!$id || !$title) {
        sendJsonResponse(['success' => false, 'message' => 'Event ID and title are required'], 400);
    }

    $eventDate = $input['event_date'] ?? date('Y-m-d');
    $eventTime = $input['event_time'] ?? '09:00:00';
    $eventTypeId = intval($input['event_type_id'] ?? 2);
    $officeId = intval($input['office_id'] ?? 1);
    $allDay = !empty($input['all_day']) ? 1 : 0;
    $startDt = $input['start_datetime'] ?? ($eventDate . ' ' . $eventTime);
    $endDt = $input['end_datetime'] ?? date('Y-m-d H:i:s', strtotime($startDt . ' +1 hour'));

    $stmt = $pdo->prepare("
        UPDATE tbl_calendar_events 
        SET title = ?, event_date = ?, event_time = ?, start_datetime = ?, end_datetime = ?, all_day = ?, office_id = ?, event_type_id = ?, updated_at = NOW()
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$title, $eventDate, $eventTime, $startDt, $endDt, $allDay, $officeId, $eventTypeId, $id]);

    sendJsonResponse([
        'success' => true,
        'message' => 'Calendar activity updated successfully'
    ]);
}

// ============================================================
// DELETE — Soft Delete Calendar Event
// ============================================================
if ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendJsonResponse(['success' => false, 'message' => 'Event ID is required'], 400);
    }

    $stmt = $pdo->prepare("UPDATE tbl_calendar_events SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);

    sendJsonResponse(['success' => true, 'message' => 'Calendar activity deleted successfully']);
}

sendJsonResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);

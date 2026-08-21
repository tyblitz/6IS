<?php
// backend/api/calendar/index.php
// REST API for Calendar module — aggregated events from accomplishments, communications, and standalone calendar events

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// GET — Fetch calendar events (aggregated or single)
// ============================================================
if ($method === 'GET') {
    $view = $_GET['view'] ?? null;
    $id = $_GET['id'] ?? null;

    // GET single standalone calendar event
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM tbl_calendar_events WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $event = $stmt->fetch();
        if ($event) {
            echo json_encode(['success' => true, 'data' => $event]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Event not found']);
        }
        exit;
    }

    // GET aggregated events for a month
    if ($view === 'month') {
        $year = intval($_GET['year'] ?? date('Y'));
        $month = intval($_GET['month'] ?? date('m'));

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $events = [];

        // 1. Accomplishments
        $stmt = $pdo->prepare("
            SELECT a.id, a.description AS title, a.date AS event_date, NULL AS event_time,
                   'accomplishment' AS source, a.id AS source_id,
                   COALESCE(c.category_name, 'General') AS category_name,
                   COALESCE(c.category_code, '') AS category_code
            FROM tbl_accomplishments a
            LEFT JOIN tbl_accomplishment_categories c ON a.category_id = c.id
            WHERE a.date BETWEEN ? AND ?
              AND a.deleted_at IS NULL
        ");
        $stmt->execute([$startDate, $endDate]);
        $accomplishments = $stmt->fetchAll();
        foreach ($accomplishments as $row) {
            $events[] = [
                'id' => 'acc_' . $row['id'],
                'title' => $row['title'],
                'date' => $row['event_date'],
                'time' => null,
                'source' => 'accomplishment',
                'source_id' => (int)$row['source_id'],
                'event_type' => 'accomplishment',
                'priority' => 'normal',
                'category_name' => $row['category_name'],
                'category_code' => $row['category_code']
            ];
        }

        // 2. Communications (Incoming)
        $stmt = $pdo->prepare("
            SELECT c.id, CONCAT(COALESCE(cat.code, ''), ' - ', c.subject) AS title,
                   c.communication_date AS event_date, NULL AS event_time,
                   'incoming_comm' AS source, c.id AS source_id,
                   c.status,
                   COALESCE(cat.name, '') AS category_name,
                   COALESCE(cat.code, '') AS category_code
            FROM tbl_communications c
            LEFT JOIN tbl_communication_categories cat ON c.category_id = cat.id
            WHERE c.communication_type = 'Incoming'
              AND c.communication_date BETWEEN ? AND ?
              AND c.deleted_at IS NULL
        ");
        $stmt->execute([$startDate, $endDate]);
        $incoming = $stmt->fetchAll();
        foreach ($incoming as $row) {
            $events[] = [
                'id' => 'inc_' . $row['source_id'],
                'title' => $row['title'],
                'date' => $row['event_date'],
                'time' => null,
                'source' => 'incoming_comm',
                'source_id' => (int)$row['source_id'],
                'event_type' => 'communication',
                'priority' => 'normal',
                'status' => $row['status'],
                'category_name' => $row['category_name'],
                'category_code' => $row['category_code']
            ];
        }

        // 3. Communications (Outgoing)
        $stmt = $pdo->prepare("
            SELECT c.id, CONCAT(COALESCE(cat.code, ''), ' - ', c.subject) AS title,
                   c.communication_date AS event_date, NULL AS event_time,
                   'outgoing_comm' AS source, c.id AS source_id,
                   c.status,
                   COALESCE(cat.name, '') AS category_name,
                   COALESCE(cat.code, '') AS category_code
            FROM tbl_communications c
            LEFT JOIN tbl_communication_categories cat ON c.category_id = cat.id
            WHERE c.communication_type = 'Outgoing'
              AND c.communication_date BETWEEN ? AND ?
              AND c.deleted_at IS NULL
        ");
        $stmt->execute([$startDate, $endDate]);
        $outgoing = $stmt->fetchAll();
        foreach ($outgoing as $row) {
            $events[] = [
                'id' => 'out_' . $row['source_id'],
                'title' => $row['title'],
                'date' => $row['event_date'],
                'time' => null,
                'source' => 'outgoing_comm',
                'source_id' => (int)$row['source_id'],
                'event_type' => 'communication',
                'priority' => 'normal',
                'status' => $row['status'],
                'category_name' => $row['category_name'],
                'category_code' => $row['category_code']
            ];
        }

        // 4. Standalone Calendar Events
        $stmt = $pdo->prepare("
            SELECT * FROM tbl_calendar_events
            WHERE event_date BETWEEN ? AND ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$startDate, $endDate]);
        $calendarEvents = $stmt->fetchAll();
        foreach ($calendarEvents as $row) {
            $events[] = [
                'id' => 'cal_' . $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'date' => $row['event_date'],
                'time' => $row['event_time'],
                'source' => 'calendar_event',
                'source_id' => (int)$row['id'],
                'event_type' => $row['event_type'],
                'priority' => $row['priority']
            ];
        }

        // Sort all events by date, then time
        usort($events, function ($a, $b) {
            $cmp = strcmp($a['date'], $b['date']);
            if ($cmp !== 0) return $cmp;
            $timeA = $a['time'] ?? '00:00:00';
            $timeB = $b['time'] ?? '00:00:00';
            return strcmp($timeA, $timeB);
        });

        echo json_encode(['success' => true, 'data' => $events]);
        exit;
    }

    // GET aggregated events for a week (Dashboard widget)
    if ($view === 'week') {
        $dateParam = $_GET['date'] ?? date('Y-m-d');
        $dt = new DateTime($dateParam);
        $dayOfWeek = (int)$dt->format('w'); // 0=Sun, 1=Mon, ...
        $mondayOffset = ($dayOfWeek === 0) ? -6 : (1 - $dayOfWeek);
        $monday = (clone $dt)->modify("{$mondayOffset} days");
        $sunday = (clone $monday)->modify('+6 days');

        $startDate = $monday->format('Y-m-d');
        $endDate = $sunday->format('Y-m-d');

        $events = [];

        // Accomplishments
        $stmt = $pdo->prepare("
            SELECT a.id, a.description AS title, a.date AS event_date,
                   'accomplishment' AS source, a.id AS source_id
            FROM tbl_accomplishments a
            WHERE a.date BETWEEN ? AND ? AND a.deleted_at IS NULL
        ");
        $stmt->execute([$startDate, $endDate]);
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'id' => 'acc_' . $row['id'],
                'title' => $row['title'],
                'date' => $row['event_date'],
                'time' => null,
                'source' => 'accomplishment',
                'source_id' => (int)$row['source_id'],
                'event_type' => 'accomplishment',
                'priority' => 'normal'
            ];
        }

        // Incoming Communications
        $stmt = $pdo->prepare("
            SELECT c.id, CONCAT(COALESCE(cat.code, ''), ' - ', c.subject) AS title,
                   c.communication_date AS event_date,
                   'incoming_comm' AS source, c.id AS source_id
            FROM tbl_communications c
            LEFT JOIN tbl_communication_categories cat ON c.category_id = cat.id
            WHERE c.communication_type = 'Incoming'
              AND c.communication_date BETWEEN ? AND ? AND c.deleted_at IS NULL
        ");
        $stmt->execute([$startDate, $endDate]);
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'id' => 'inc_' . $row['source_id'],
                'title' => $row['title'],
                'date' => $row['event_date'],
                'time' => null,
                'source' => 'incoming_comm',
                'source_id' => (int)$row['source_id'],
                'event_type' => 'communication',
                'priority' => 'normal'
            ];
        }

        // Outgoing Communications
        $stmt = $pdo->prepare("
            SELECT c.id, CONCAT(COALESCE(cat.code, ''), ' - ', c.subject) AS title,
                   c.communication_date AS event_date,
                   'outgoing_comm' AS source, c.id AS source_id
            FROM tbl_communications c
            LEFT JOIN tbl_communication_categories cat ON c.category_id = cat.id
            WHERE c.communication_type = 'Outgoing'
              AND c.communication_date BETWEEN ? AND ? AND c.deleted_at IS NULL
        ");
        $stmt->execute([$startDate, $endDate]);
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'id' => 'out_' . $row['source_id'],
                'title' => $row['title'],
                'date' => $row['event_date'],
                'time' => null,
                'source' => 'outgoing_comm',
                'source_id' => (int)$row['source_id'],
                'event_type' => 'communication',
                'priority' => 'normal'
            ];
        }

        // Calendar Events
        $stmt = $pdo->prepare("
            SELECT * FROM tbl_calendar_events
            WHERE event_date BETWEEN ? AND ? AND deleted_at IS NULL
        ");
        $stmt->execute([$startDate, $endDate]);
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'id' => 'cal_' . $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'date' => $row['event_date'],
                'time' => $row['event_time'],
                'source' => 'calendar_event',
                'source_id' => (int)$row['id'],
                'event_type' => $row['event_type'],
                'priority' => $row['priority']
            ];
        }

        usort($events, function ($a, $b) {
            $cmp = strcmp($a['date'], $b['date']);
            if ($cmp !== 0) return $cmp;
            $timeA = $a['time'] ?? '00:00:00';
            $timeB = $b['time'] ?? '00:00:00';
            return strcmp($timeA, $timeB);
        });

        echo json_encode([
            'success' => true,
            'data' => $events,
            'week_start' => $startDate,
            'week_end' => $endDate
        ]);
        exit;
    }

    // Default: return empty
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

// ============================================================
// POST — Create standalone calendar event
// ============================================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $eventDate = $input['event_date'] ?? null;
    $eventTime = $input['event_time'] ?? null;
    $eventType = $input['event_type'] ?? 'other';
    $priority = $input['priority'] ?? 'normal';
    $createdBy = $_SESSION['user_id'] ?? null;

    if (empty($title) || empty($eventDate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title and event date are required.']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO tbl_calendar_events (title, description, event_date, event_time, event_type, priority, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$title, $description ?: null, $eventDate, $eventTime ?: null, $eventType, $priority, $createdBy]);

    $newId = $pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM tbl_calendar_events WHERE id = ?");
    $stmt->execute([$newId]);
    $event = $stmt->fetch();

    echo json_encode(['success' => true, 'message' => 'Event created successfully.', 'data' => $event]);
    exit;
}

// ============================================================
// PUT — Update standalone calendar event
// ============================================================
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID is required.']);
        exit;
    }

    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $eventDate = $input['event_date'] ?? null;
    $eventTime = $input['event_time'] ?? null;
    $eventType = $input['event_type'] ?? 'other';
    $priority = $input['priority'] ?? 'normal';

    if (empty($title) || empty($eventDate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title and event date are required.']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE tbl_calendar_events
        SET title = ?, description = ?, event_date = ?, event_time = ?, event_type = ?, priority = ?, updated_at = NOW()
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$title, $description ?: null, $eventDate, $eventTime ?: null, $eventType, $priority, $id]);

    $stmt = $pdo->prepare("SELECT * FROM tbl_calendar_events WHERE id = ?");
    $stmt->execute([$id]);
    $event = $stmt->fetch();

    echo json_encode(['success' => true, 'message' => 'Event updated successfully.', 'data' => $event]);
    exit;
}

// ============================================================
// DELETE — Soft-delete standalone calendar event
// ============================================================
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID is required.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE tbl_calendar_events SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Event deleted successfully.']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);

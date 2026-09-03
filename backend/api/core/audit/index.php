<?php
// backend/api/core/audit/index.php
// REST API Endpoint for 6IS Core Audit Logs (Read-Only)

require_once __DIR__ . '/../../../helpers/cors.php';
handleCors();

header('Content-Type: application/json; charset=utf-8');

// The audit API is strictly read-only; mutations are forbidden
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. The audit log is append-only and strictly read-only.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/permissions.php';

requireAuth();

function sendJsonResponse(bool $success, string $message, $data = null, $errors = null, int $statusCode = 200, ?array $pagination = null): void {
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => $errors
    ];
    if ($pagination !== null) {
        $response['pagination'] = $pagination;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$configPath = __DIR__ . '/../../../config/database.php';
if (!file_exists($configPath)) {
    sendJsonResponse(false, 'Database configuration file missing.', null, null, 500);
}
$dbConfig = require $configPath;

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, 'Database connection failed: ' . $e->getMessage(), null, null, 500);
}

// Authoritative authorization check: requires 'audit.view'
requirePermission('audit', 'view', $pdo);

// Parse and validate pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

// Parse filter parameters
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$userId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
$moduleKey = trim((string)($_GET['module_key'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$entityType = trim((string)($_GET['entity_type'] ?? ''));
$entityId = trim((string)($_GET['entity_id'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

$whereClauses = ['1=1'];
$params = [];

if (!empty($dateFrom)) {
    $whereClauses[] = 'a.created_at >= :date_from';
    $params[':date_from'] = strlen($dateFrom) === 10 ? "{$dateFrom} 00:00:00" : $dateFrom;
}
if (!empty($dateTo)) {
    $whereClauses[] = 'a.created_at <= :date_to';
    $params[':date_to'] = strlen($dateTo) === 10 ? "{$dateTo} 23:59:59" : $dateTo;
}
if ($userId !== null) {
    $whereClauses[] = 'a.user_id = :user_id';
    $params[':user_id'] = $userId;
}
if (!empty($moduleKey)) {
    $whereClauses[] = 'LOWER(a.module_key) = LOWER(:module_key)';
    $params[':module_key'] = $moduleKey;
}
if (!empty($action)) {
    $whereClauses[] = 'UPPER(a.action) = UPPER(:action)';
    $params[':action'] = $action;
}
if (!empty($entityType)) {
    $whereClauses[] = 'LOWER(a.entity_type) = LOWER(:entity_type)';
    $params[':entity_type'] = $entityType;
}
if (!empty($entityId)) {
    $whereClauses[] = 'a.entity_id = :entity_id';
    $params[':entity_id'] = $entityId;
}
if (!empty($search)) {
    $whereClauses[] = '(LOWER(a.description) LIKE :search OR LOWER(a.entity_type) LIKE :search OR LOWER(COALESCE(a.entity_id, \'\')) LIKE :search OR LOWER(COALESCE(u.username, \'\')) LIKE :search)';
    $params[':search'] = '%' . strtolower($search) . '%';
}

$whereSql = implode(' AND ', $whereClauses);

try {
    // 1. Get total count for pagination
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM tbl_audit_logs a
        LEFT JOIN tbl_users u ON a.user_id = u.id
        WHERE {$whereSql}
    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // 2. Query paginated audit entries
    $queryStmt = $pdo->prepare("
        SELECT a.id, a.user_id, u.username, u.full_name,
               a.action, a.module_key, a.entity_type, a.entity_id,
               a.description, a.old_values, a.new_values,
               a.ip_address, a.user_agent, a.created_at
        FROM tbl_audit_logs a
        LEFT JOIN tbl_users u ON a.user_id = u.id
        WHERE {$whereSql}
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $queryStmt->execute($params);
    $rows = $queryStmt->fetchAll();

    $items = array_map(function($r) {
        $oldParsed = null;
        if (!empty($r['old_values'])) {
            $decoded = json_decode($r['old_values'], true);
            $oldParsed = $decoded !== null ? $decoded : $r['old_values'];
        }

        $newParsed = null;
        if (!empty($r['new_values'])) {
            $decoded = json_decode($r['new_values'], true);
            $newParsed = $decoded !== null ? $decoded : $r['new_values'];
        }

        return [
            'id' => (int)$r['id'],
            'user_id' => $r['user_id'] ? (int)$r['user_id'] : null,
            'username' => $r['username'] ?: null,
            'full_name' => $r['full_name'] ?: null,
            'action' => $r['action'],
            'module_key' => $r['module_key'],
            'entity_type' => $r['entity_type'],
            'entity_id' => $r['entity_id'] ?: null,
            'description' => $r['description'],
            'old_values' => $oldParsed,
            'new_values' => $newParsed,
            'ip_address' => $r['ip_address'] ?: null,
            'user_agent' => $r['user_agent'] ?: null,
            'created_at' => $r['created_at']
        ];
    }, $rows);

    $totalPages = (int)ceil($total / $limit);

    sendJsonResponse(true, 'Audit logs retrieved successfully.', $items, null, 200, [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => $totalPages
    ]);
} catch (Throwable $e) {
    error_log('[audit] Database query error: ' . $e->getMessage());
    sendJsonResponse(false, 'Database failure retrieving audit logs.', null, ['error' => $e->getMessage()], 500);
}

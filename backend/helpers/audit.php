<?php
// backend/helpers/audit.php
// Centralized Security & Governance Audit Logging Service for 6IS Core (Phase 4)

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * Sensitive field denylist for audit sanitization.
 * Case-insensitive recursive key inspection.
 */
const AUDIT_SENSITIVE_KEYS = [
    'password',
    'password_hash',
    'new_password',
    'confirm_password',
    'current_password',
    'token',
    'access_token',
    'refresh_token',
    'csrf_token',
    'session_id',
    'cookie',
    'secret',
    'api_key',
    'apikey',
    'authorization'
];

/**
 * Recursively sanitizes data payloads to strip or mask sensitive keys and credentials.
 * 
 * @param mixed $data Input payload (array, scalar, or null)
 * @return mixed Sanitized payload safe for audit persistence
 */
function sanitizeAuditData($data) {
    if (!is_array($data)) {
        return $data;
    }

    $sanitized = [];
    foreach ($data as $key => $value) {
        $lowerKey = strtolower((string)$key);
        $isSensitive = false;

        foreach (AUDIT_SENSITIVE_KEYS as $sensitiveKey) {
            if ($lowerKey === $sensitiveKey || str_contains($lowerKey, $sensitiveKey)) {
                $isSensitive = true;
                break;
            }
        }

        if ($isSensitive) {
            $sanitized[$key] = '[REDACTED]';
        } elseif (is_array($value)) {
            $sanitized[$key] = sanitizeAuditData($value);
        } else {
            $sanitized[$key] = $value;
        }
    }

    return $sanitized;
}

/**
 * Returns a valid PDO connection for audit logging.
 * Reuses existing PDO or resolves from backend/config/database.php.
 * 
 * @param PDO|null $pdo Existing database connection
 * @return PDO
 * @throws RuntimeException If database configuration is missing or connection fails
 */
function getAuditDbConnection(?PDO $pdo = null): PDO {
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configPath = __DIR__ . '/../config/database.php';
    if (!file_exists($configPath)) {
        throw new RuntimeException('Database configuration file missing for audit logger.');
    }

    $dbConfig = require $configPath;
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
    return new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

/**
 * Records a structured, sanitized audit entry in tbl_audit_logs.
 * 
 * Automatically derives:
 * - user_id (from active PHP session unless explicitly specified)
 * - ip_address (from server-observed remote address)
 * - user_agent (from HTTP_USER_AGENT)
 * 
 * Never swallows database errors; throws RuntimeException to allow transaction rollback.
 * 
 * @param array $entry Audit log parameters:
 *                     - action (required): e.g. 'LOGIN', 'CREATE', 'UPDATE', 'DELETE', etc.
 *                     - module_key (required): e.g. 'core', 'users', 'roles', 'offices', etc.
 *                     - entity_type (required): e.g. 'user', 'role', 'office', 'authentication', etc.
 *                     - entity_id (optional): ID or code of target entity
 *                     - description (optional): Human-readable event description
 *                     - old_values (optional): Previous state before mutation (array or string)
 *                     - new_values (optional): New state after mutation (array or string)
 *                     - user_id (optional override): explicitly pass null for unauthenticated events
 * @param PDO|null $pdo Optional existing PDO transaction connection
 * @return int Primary key ID of created audit log row
 * @throws RuntimeException On database or validation failure
 */
function auditLog(array $entry, ?PDO $pdo = null): int {
    $action = strtoupper(trim((string)($entry['action'] ?? '')));
    $moduleKey = strtolower(trim((string)($entry['module_key'] ?? 'core')));
    $entityType = strtolower(trim((string)($entry['entity_type'] ?? '')));
    $entityId = isset($entry['entity_id']) ? (string)$entry['entity_id'] : null;
    $description = isset($entry['description']) ? trim((string)$entry['description']) : null;

    if (empty($action)) {
        throw new InvalidArgumentException('Audit log requires an action.');
    }
    if (empty($moduleKey)) {
        throw new InvalidArgumentException('Audit log requires a module_key.');
    }
    if (empty($entityType)) {
        throw new InvalidArgumentException('Audit log requires an entity_type.');
    }

    // Derive or override user_id
    $userId = null;
    if (array_key_exists('user_id', $entry)) {
        $userId = $entry['user_id'] !== null ? (int)$entry['user_id'] : null;
    } else {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }
    }

    // Derive server-observed network attributes
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Sanitize old_values and new_values recursively
    $oldValuesRaw = $entry['old_values'] ?? null;
    $newValuesRaw = $entry['new_values'] ?? null;

    $sanitizedOld = is_array($oldValuesRaw) ? sanitizeAuditData($oldValuesRaw) : $oldValuesRaw;
    $sanitizedNew = is_array($newValuesRaw) ? sanitizeAuditData($newValuesRaw) : $newValuesRaw;

    $oldValuesJson = is_array($sanitizedOld) ? json_encode($sanitizedOld, JSON_UNESCAPED_UNICODE) : (is_string($sanitizedOld) ? $sanitizedOld : null);
    $newValuesJson = is_array($sanitizedNew) ? json_encode($sanitizedNew, JSON_UNESCAPED_UNICODE) : (is_string($sanitizedNew) ? $sanitizedNew : null);

    try {
        $db = getAuditDbConnection($pdo);
        $stmt = $db->prepare("
            INSERT INTO tbl_audit_logs (
                user_id, action, module_key, entity_type, entity_id,
                description, old_values, new_values, ip_address, user_agent, created_at
            ) VALUES (
                :user_id, :action, :module_key, :entity_type, :entity_id,
                :description, :old_values, :new_values, :ip_address, :user_agent, NOW()
            )
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':module_key' => $moduleKey,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':description' => $description,
            ':old_values' => $oldValuesJson,
            ':new_values' => $newValuesJson,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent
        ]);

        return (int)$db->lastInsertId();
    } catch (Throwable $e) {
        error_log('[audit] Critical failure writing audit log: ' . $e->getMessage());
        throw new RuntimeException('Audit log failure: ' . $e->getMessage(), 0, $e);
    }
}

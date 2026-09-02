<?php
// backend/helpers/modules.php
// Reusable Core Module Activation Helpers & Middleware for 6IS

/**
 * Resolves a valid PDO connection for module verification.
 * Reuses existing PDO if passed or loads from backend/config/database.php.
 * 
 * @param PDO|null $pdo Existing database connection
 * @return PDO
 */
function getModuleDbConnection(?PDO $pdo = null): PDO {
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    static $staticPdo = null;
    if ($staticPdo instanceof PDO) {
        return $staticPdo;
    }

    $configPath = __DIR__ . '/../config/database.php';
    if (!file_exists($configPath)) {
        throw new RuntimeException("Database configuration file missing.");
    }

    $dbConfig = require $configPath;
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
    $staticPdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    return $staticPdo;
}

/**
 * Checks whether a module is registered and active in tbl_modules.
 * 
 * @param string $moduleKey Stable module key ('inventory', 'calendar', etc.)
 * @param PDO|null $pdo Optional existing PDO connection
 * @return bool True if module exists and is active, false otherwise
 */
function isModuleActive(string $moduleKey, ?PDO $pdo = null): bool {
    try {
        $db = getModuleDbConnection($pdo);
        $stmt = $db->prepare("
            SELECT is_active 
            FROM tbl_modules 
            WHERE LOWER(module_key) = LOWER(:module_key) 
            LIMIT 1
        ");
        $stmt->execute([':module_key' => trim($moduleKey)]);
        $row = $stmt->fetch();

        if ($row && (int)$row['is_active'] === 1) {
            return true;
        }

        return false;
    } catch (Exception $e) {
        // Fallback safely to false on query failure
        return false;
    }
}

/**
 * Enforces that a module is active before allowing an API request to proceed.
 * If the module is inactive or missing, rejects with HTTP 403 Forbidden.
 * 
 * IMPORTANT: Call requireAuth() BEFORE calling requireModuleActive()!
 * 
 * @param string $moduleKey Stable module key
 * @param PDO|null $pdo Optional existing PDO connection
 */
function requireModuleActive(string $moduleKey, ?PDO $pdo = null): void {
    if (!isModuleActive($moduleKey, $pdo)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => "Module '{$moduleKey}' is currently disabled on this system."
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

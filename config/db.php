<?php
/**
 * Purpose: Defines the PHP database connection settings and database change-tracking support.
 */

const IRIS_DB_HOST='127.0.0.1'; const IRIS_DB_PORT='3306'; const IRIS_DB_NAME='iris_db_3nf'; const IRIS_DB_USER='root'; const IRIS_DB_PASS='';

final class IRISChangeTracker
{
    private static bool $suspended = false;
    private static bool $queued = false;

    public static function suspend(bool $value): void { self::$suspended = $value; }

    public static function record(string $sql): void
    {
        if (self::$suspended || self::$queued
            || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || empty($_SESSION['user_id'])
            || !preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql)) return;
        self::$queued = true;
        register_shutdown_function([self::class, 'publish']);
    }

    public static function publish(): void
    {
        if (http_response_code() >= 400) return;
        try {
            $pdo = new PDO('mysql:host=' . IRIS_DB_HOST . ';port=' . IRIS_DB_PORT . ';dbname=' . IRIS_DB_NAME . ';charset=utf8mb4', IRIS_DB_USER, IRIS_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("INSERT INTO app_change_state (id, state_data) VALUES (1, JSON_OBJECT('version', 1))
                ON DUPLICATE KEY UPDATE state_data = JSON_SET(
                    COALESCE(state_data, JSON_OBJECT()),
                    '$.version',
                    COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(state_data, '$.version')) AS UNSIGNED), 0) + 1
                )");
        } catch (Throwable $exception) {
            error_log('IRIS change signal failed: ' . $exception->getMessage());
        }
    }
}

final class IRISChangeTrackedStatement extends PDOStatement
{
    protected function __construct() {}

    public function execute(?array $params = null): bool
    {
        $executed = parent::execute($params);
        if ($executed) IRISChangeTracker::record($this->queryString);
        return $executed;
    }
}

final class IRISChangeTrackedPDO extends PDO
{
    public function exec(string $statement): int|false
    {
        $result = parent::exec($statement);
        if ($result !== false) IRISChangeTracker::record($statement);
        return $result;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $statement = $fetchMode === null
            ? parent::query($query)
            : parent::query($query, $fetchMode, ...$fetchModeArgs);
        if ($statement !== false) IRISChangeTracker::record($query);
        return $statement;
    }
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new IRISChangeTrackedPDO('mysql:host=' . IRIS_DB_HOST . ';port=' . IRIS_DB_PORT . ';dbname=' . IRIS_DB_NAME . ';charset=utf8mb4', IRIS_DB_USER, IRIS_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STATEMENT_CLASS => [IRISChangeTrackedStatement::class, []]
    ]);
    return $pdo;
}

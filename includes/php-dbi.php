<?php
declare(strict_types=1);

/**
 * Database abstraction layer using PDO.
 *
 * Provides a unified functional API for database interactions.
 * Supports PDO MySQL and PDO SQLite backends.
 *
 * @package Checkbook
 */

/** @var array $db_connection_info Connection state and metadata */
$db_connection_info = [
    'connected' => false,
    'connection' => null,
    'database' => '',
    'host' => '',
    'login' => '',
    'password' => '',
    'debug' => false,
];

/** @var int $db_query_count Number of queries executed */
$db_query_count = 0;

/**
 * Opens a database connection via PDO.
 *
 * @param string $host     Hostname (ignored for sqlite)
 * @param string $login    Database login
 * @param string $password Database password
 * @param string $database Database name (or file path for sqlite)
 * @param bool   $lazy     Defer connection until first query
 * @return PDO|bool The PDO connection or true if lazy
 */
function dbi_connect(string $host, string $login, string $password, string $database, bool $lazy = true): PDO|bool
{
    global $db_connection_info, $db_query_count;

    $db_query_count = 0;
    $db_connection_info['connected'] = false;
    $db_connection_info['connection'] = null;
    $db_connection_info['database'] = $database;
    $db_connection_info['host'] = $host;
    $db_connection_info['login'] = $login;
    $db_connection_info['password'] = $password;

    if ($lazy) {
        return true;
    }

    return _dbi_ensure_connection();
}

/**
 * Ensures a PDO connection exists, creating one if needed.
 *
 * @return PDO The active connection
 * @throws RuntimeException On connection failure
 */
function _dbi_ensure_connection(): PDO
{
    global $db_connection_info;

    if ($db_connection_info['connected'] && $db_connection_info['connection'] instanceof PDO) {
        return $db_connection_info['connection'];
    }

    $db_type = $GLOBALS['db_type'] ?? 'pdo_mysql';

    try {
        if ($db_type === 'pdo_sqlite' || $db_type === 'sqlite3') {
            $dsn = 'sqlite:' . $db_connection_info['database'];
            $conn = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
            ]);
        } else {
            $dsn = 'mysql:host=' . $db_connection_info['host'] . ';dbname=' . $db_connection_info['database'] . ';charset=utf8mb4';
            $conn = new PDO($dsn, $db_connection_info['login'], $db_connection_info['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
        }
    } catch (PDOException $e) {
        throw new RuntimeException('Error connecting to database: ' . $e->getMessage());
    }

    $db_connection_info['connected'] = true;
    $db_connection_info['connection'] = $conn;
    return $conn;
}

/**
 * Closes the database connection.
 *
 * @param mixed $conn Ignored (kept for API compatibility)
 * @return bool
 */
function dbi_close($conn = null): bool
{
    global $db_connection_info;
    $db_connection_info['connected'] = false;
    $db_connection_info['connection'] = null;
    return true;
}

/**
 * Returns the number of queries executed.
 */
function dbi_num_queries(): int
{
    global $db_query_count;
    return $db_query_count ?? 0;
}

/**
 * Executes a raw SQL query (no parameter binding).
 *
 * Prefer dbi_execute() with parameters for safety.
 *
 * @param string $sql          SQL query
 * @param bool   $fatalOnError Throw on error
 * @param bool   $showError    Show error details
 * @return PDOStatement|bool Result set or false on error
 */
function dbi_query(string $sql, bool $fatalOnError = true, bool $showError = true): PDOStatement|bool
{
    global $db_query_count, $SQLLOG, $db_connection_info;

    if (!empty($db_connection_info['debug'])) {
        $SQLLOG[] = $sql;
    }

    $db_query_count++;

    try {
        $conn = _dbi_ensure_connection();
        $res = $conn->query($sql);
        if ($res === false) {
            throw new RuntimeException('Query failed: ' . implode(' ', $conn->errorInfo()));
        }
        return $res;
    } catch (\Exception $e) {
        if ($fatalOnError) {
            dbi_fatal_error($e->getMessage(), true, $showError);
        }
        return false;
    }
}

/**
 * Executes a SQL query with parameter binding.
 *
 * @param string $sql          SQL with ? placeholders
 * @param array  $params       Values for placeholders
 * @param bool   $fatalOnError Throw on error
 * @param bool   $showError    Show error details
 * @return PDOStatement|bool Result set or false on error
 */
function dbi_execute(string $sql, array $params = [], bool $fatalOnError = true, bool $showError = true): PDOStatement|bool
{
    global $db_query_count, $SQLLOG, $db_connection_info;

    if (empty($params)) {
        return dbi_query($sql, $fatalOnError, $showError);
    }

    if (!empty($db_connection_info['debug'])) {
        $SQLLOG[] = $sql . ' [' . implode(', ', $params) . ']';
    }

    $db_query_count++;

    try {
        $conn = _dbi_ensure_connection();
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . implode(' ', $conn->errorInfo()));
        }
        $stmt->execute($params);
        return $stmt;
    } catch (\Exception $e) {
        if ($fatalOnError) {
            dbi_fatal_error($e->getMessage(), true, $showError);
        }
        return false;
    }
}

/**
 * Fetches a single row as a numeric array.
 *
 * @param PDOStatement|bool $res Result from dbi_query/dbi_execute
 * @return array|null Row data or null
 */
function dbi_fetch_row($res): ?array
{
    if (!$res instanceof PDOStatement) {
        return null;
    }
    $row = $res->fetch(PDO::FETCH_NUM);
    return $row !== false ? $row : null;
}

/**
 * Returns the number of rows affected by the last INSERT/UPDATE/DELETE.
 *
 * @param mixed $conn Ignored
 * @param PDOStatement|bool $res Result from dbi_execute
 * @return int
 */
function dbi_affected_rows($conn, $res): int
{
    if ($res instanceof PDOStatement) {
        return $res->rowCount();
    }
    return 0;
}

/**
 * Frees a result set.
 *
 * @param PDOStatement|bool $res Result to free
 * @return bool
 */
function dbi_free_result($res): bool
{
    if ($res instanceof PDOStatement) {
        $res->closeCursor();
    }
    return true;
}

/**
 * Gets the latest database error message.
 */
function dbi_error(): string
{
    global $db_connection_info;
    if (!empty($db_connection_info['connection']) && $db_connection_info['connection'] instanceof PDO) {
        $info = $db_connection_info['connection']->errorInfo();
        return $info[2] ?? 'Unknown error';
    }
    return 'No connection';
}

/**
 * Returns the last inserted row ID.
 */
function dbi_last_insert_id(): string
{
    $conn = _dbi_ensure_connection();
    return $conn->lastInsertId();
}

/**
 * Displays a fatal database error.
 *
 * @param string $msg       Error message
 * @param bool   $doExit    Terminate execution
 * @param bool   $showError Show error details
 */
function dbi_fatal_error(string $msg, bool $doExit = true, bool $showError = true): void
{
    if ($showError) {
        echo '<h2>Database Error</h2><p>' . htmlspecialchars($msg) . '</p>';
    }
    if ($doExit) {
        throw new RuntimeException($msg);
    }
}

/**
 * Updates a BLOB in the database.
 */
function dbi_update_blob(string $table, string $column, string $key, string $data): bool
{
    $conn = _dbi_ensure_connection();
    $sql = "UPDATE $table SET $column = ? WHERE $key";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, $data, PDO::PARAM_LOB);
    return $stmt->execute();
}

/**
 * Retrieves a BLOB from the database.
 */
function dbi_get_blob(string $table, string $column, string $key): ?string
{
    $sql = "SELECT $column FROM $table WHERE $key";
    $res = dbi_query($sql);
    if (!$res) {
        return null;
    }
    $row = dbi_fetch_row($res);
    dbi_free_result($res);
    return $row[0] ?? null;
}

/**
 * Enable/disable SQL debug logging.
 */
function dbi_set_debug(bool $status = false): void
{
    global $db_connection_info;
    $db_connection_info['debug'] = $status;
}

/**
 * Get SQL debug status.
 */
function dbi_get_debug(): bool
{
    global $db_connection_info;
    return !empty($db_connection_info['debug']);
}

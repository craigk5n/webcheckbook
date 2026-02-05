<?php
declare(strict_types=1);

/**
 * dbi4php - Generic database access for PHP
 *
 * Provides a unified API for database interactions in PHP 8. Supports:
 * - mysqli (MySQL)
 * - sqlite3 (SQLite 3)
 * - pdo_mysql (PDO for MySQL)
 * - pdo_sqlite (PDO for SQLite)
 *
 * The database type is set via $GLOBALS['db_type']. This library assumes a single
 * connection to a single database for simplicity. Multiple queries can be executed
 * simultaneously. Use dbi_error() to retrieve error information on failure.
 *
 * BLOB support is included for storing binary data (e.g., check images).
 *
 * @author Craig Knudsen <craig@k5n.us>
 * @copyright Craig Knudsen, <craig@k5n.us, http://www.k5n.us/
 */

/**
 * Custom exception for database errors.
 */
class DBIException extends Exception {}

/**
 * Opens a database connection.
 *
 * @param string $host      Hostname of database server (ignored for sqlite3, pdo_sqlite)
 * @param string $login     Database login
 * @param string $password  Database login password
 * @param string $database  Name of database (file path for sqlite3)
 * @param bool   $lazy      Wait until a query to connect?
 *
 * @return mysqli|SQLite3|PDO|null The connection object
 * @throws DBIException
 */
function dbi_connect(string $host, string $login, string $password, string $database, bool $lazy = true)
{
    global $db_connection_info, $db_query_count;

    $db_query_count = 0;

    if (!isset($db_connection_info)) {
        $db_connection_info = [];
    }

    $db_connection_info['connected'] = false;
    $db_connection_info['connection'] = null;
    $db_connection_info['database'] = $database;
    $db_connection_info['host'] = $host;
    $db_connection_info['login'] = $login;
    $db_connection_info['password'] = $password;

    if ($lazy && $GLOBALS['db_type'] !== 'mysqli') {
        return true;
    }

    switch ($GLOBALS['db_type']) {
        case 'mysqli':
            $conn = new mysqli($host, $login, $password, $database);
            if ($conn->connect_error) {
                throw new DBIException(translate('Error connecting to database XXX') . ': ' . $conn->connect_error);
            }
            break;
        case 'sqlite3':
            $conn = new SQLite3($database);
            if (!$conn) {
                throw new DBIException(translate('Error connecting to database XXX'));
            }
            break;
        case 'pdo_mysql':
            $conn = new PDO("mysql:host=$host;dbname=$database", $login, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            break;
        case 'pdo_sqlite':
            $conn = new PDO("sqlite:$database", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            break;
        default:
            throw new DBIException(translate('invalid db_type XXX') . ': ' . ($GLOBALS['db_type'] ?? 'not defined'));
    }

    $db_connection_info['connected'] = true;
    $db_connection_info['connection'] = $conn;
    return $conn;
}

/**
 * Closes a database connection.
 *
 * @param mysqli|SQLite3|PDO|null $conn The database connection
 * @return bool True on success
 * @throws DBIException
 */
function dbi_close($conn): bool
{
    global $db_connection_info;

    if (isset($db_connection_info) && !$db_connection_info['connected']) {
        return true;
    }

    if (!$conn && isset($db_connection_info['connection'])) {
        $conn = $db_connection_info['connection'];
    }

    if (!$conn) {
        return true;
    }

    switch ($GLOBALS['db_type']) {
        case 'mysqli':
            $result = $conn->close();
            break;
        case 'sqlite3':
            $result = $conn->close();
            break;
        case 'pdo_mysql':
        case 'pdo_sqlite':
            $result = true; // PDO closes automatically when object is destroyed
            break;
        default:
            throw new DBIException(translate('db_type not defined'));
    }

    $db_connection_info['connected'] = false;
    $db_connection_info['connection'] = null;
    return $result;
}

/**
 * Return the number of database queries executed.
 *
 * @return int
 */
function dbi_num_queries(): int
{
    global $db_query_count;
    return $db_query_count ?? 0;
}

/**
 * Executes an SQL query.
 *
 * @param string $sql           SQL query to execute
 * @param bool   $fatalOnError  Throw exception on error?
 * @param bool   $showError     Display error to user?
 *
 * @return mixed Query result resource/object or true/false for insert/delete
 * @throws DBIException
 */
function dbi_query(string $sql, bool $fatalOnError = true, bool $showError = true)
{
    global $db_connection_info, $db_query_count, $SQLLOG;

    if (!empty($db_connection_info['debug']) && !isset($SQLLOG)) {
        $SQLLOG = [];
    }

    if (!empty($db_connection_info['debug'])) {
        $SQLLOG[] = $sql;
    }

    if (isset($db_connection_info) && !$db_connection_info['connected']) {
        $conn = dbi_connect(
            $db_connection_info['host'],
            $db_connection_info['login'],
            $db_connection_info['password'],
            $db_connection_info['database'],
            false
        );
        $db_connection_info['connected'] = true;
        $db_connection_info['connection'] = $conn;
    }

    $db_query_count++;

    try {
        switch ($GLOBALS['db_type']) {
            case 'mysqli':
                $res = $db_connection_info['connection']->query($sql);
                break;
            case 'sqlite3':
                $res = $db_connection_info['connection']->query($sql);
                break;
            case 'pdo_mysql':
            case 'pdo_sqlite':
                $res = $db_connection_info['connection']->query($sql);
                break;
            default:
                throw new DBIException(translate('db_type not defined'));
        }

        if ($res === false) {
            throw new DBIException(translate('Error executing query') . ($showError ? ': ' . dbi_error() : ''));
        }

        return $res;
    } catch (Exception $e) {
        if ($fatalOnError) {
            dbi_fatal_error($e->getMessage(), true, $showError);
        }
        return false;
    }
}

/**
 * Retrieves a single row from the database as an array.
 *
 * @param mixed $res Query result resource/object from dbi_query()
 * @return array|null Row data or null on error/no data
 * @throws DBIException
 */
function dbi_fetch_row($res): ?array
{
    switch ($GLOBALS['db_type']) {
        case 'mysqli':
            return $res->fetch_array(MYSQLI_NUM) ?: null;
        case 'sqlite3':
            return $res->fetchArray(SQLITE3_NUM) ?: null;
        case 'pdo_mysql':
        case 'pdo_sqlite':
            return $res->fetch(PDO::FETCH_NUM) ?: null;
        default:
            throw new DBIException(translate('db_type not defined'));
    }
}

/**
 * Returns the number of rows affected by the last INSERT, UPDATE, or DELETE.
 *
 * @param mysqli|SQLite3|PDO|null $conn Database connection
 * @param mixed $res Query result resource/object
 * @return int Number of affected rows
 * @throws DBIException
 */
function dbi_affected_rows($conn, $res): int
{
    switch ($GLOBALS['db_type']) {
        case 'mysqli':
            return $conn->affected_rows;
        case 'sqlite3':
            return $conn->changes();
        case 'pdo_mysql':
        case 'pdo_sqlite':
            return $res->rowCount();
        default:
            throw new DBIException(translate('db_type not defined'));
    }
}

/**
 * Updates a BLOB in the database.
 *
 * @param string $table  Table name
 * @param string $column Column name for the BLOB
 * @param string $key    WHERE clause for the row (e.g., "id = 1")
 * @param string $data   Binary data to insert
 * @return bool True on success
 * @throws DBIException
 */
function dbi_update_blob(string $table, string $column, string $key, string $data): bool
{
    global $db_connection_info;

    if (empty($table) || empty($column) || empty($key) || !isset($data)) {
        throw new DBIException(translate('Invalid parameters for BLOB update'));
    }

    $sql = "UPDATE $table SET $column = ? WHERE $key";
    $conn = $db_connection_info['connection'];

    try {
        switch ($GLOBALS['db_type']) {
            case 'mysqli':
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('s', $data);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            case 'sqlite3':
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(1, $data, SQLITE3_BLOB);
                $result = $stmt->execute();
                $stmt->close();
                return $result !== false;
            case 'pdo_mysql':
            case 'pdo_sqlite':
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(1, $data, PDO::PARAM_LOB);
                return $stmt->execute();
            default:
                throw new DBIException(translate('db_type not defined'));
        }
    } catch (Exception $e) {
        throw new DBIException(translate('Error updating BLOB') . ': ' . $e->getMessage());
    }
}

/**
 * Retrieves a BLOB from the database.
 *
 * @param string $table  Table name
 * @param string $column Column name for the BLOB
 * @param string $key    WHERE clause for the row (e.g., "id = 1")
 * @return string|null Binary data or null on error/no data
 * @throws DBIException
 */
function dbi_get_blob(string $table, string $column, string $key): ?string
{
    if (empty($table) || empty($column) || empty($key)) {
        throw new DBIException(translate('Invalid parameters for BLOB retrieval'));
    }

    $sql = "SELECT $column FROM $table WHERE $key";
    $res = dbi_execute($sql);

    if (!$res) {
        return null;
    }

    $row = dbi_fetch_row($res);
    dbi_free_result($res);

    return $row[0] ?? null;
}

/**
 * Frees a result set.
 *
 * @param mixed $res Query result resource/object
 * @return bool True on success
 * @throws DBIException
 */
function dbi_free_result($res): bool
{
    if ($res === true) {
        return true;
    }

    switch ($GLOBALS['db_type']) {
        case 'mysqli':
            $res->free();
            return true;
        case 'sqlite3':
            return true; // SQLite3 handles result freeing automatically
        case 'pdo_mysql':
        case 'pdo_sqlite':
            $res->closeCursor();
            return true;
        default:
            throw new DBIException(translate('db_type not defined'));
    }
}

/**
 * Gets the latest database error message.
 *
 * @return string Error message
 */
function dbi_error(): string
{
    global $db_connection_info;

    switch ($GLOBALS['db_type']) {
        case 'mysqli':
            return $db_connection_info['connection'] ? $db_connection_info['connection']->error : translate('No connection');
        case 'sqlite3':
            return $db_connection_info['connection'] ? $db_connection_info['connection']->lastErrorMsg() : translate('No connection');
        case 'pdo_mysql':
        case 'pdo_sqlite':
            $error = $db_connection_info['connection'] ? $db_connection_info['connection']->errorInfo() : [translate('No connection')];
            return $error[2] ?? translate('Unknown error');
        default:
            return translate('db_type not defined');
    }
}

/**
 * Displays a fatal database error and optionally aborts execution.
 *
 * @param string $msg        Error message
 * @param bool   $doExit     Abort execution?
 * @param bool   $showError  Show error details?
 * @throws DBIException
 */
function dbi_fatal_error(string $msg, bool $doExit = true, bool $showError = true): void
{
    if ($showError) {
        echo '<h2>' . translate('Error') . '</h2>
<!--begin_error (dbierror)-->
' . htmlspecialchars($msg) . '
<!--end_error-->
';
    }

    if ($doExit) {
        throw new DBIException($msg);
    }
}

/**
 * Escapes a string for safe use in SQL queries.
 *
 * @param string $string String to escape
 * @return string Escaped string
 * @throws DBIException
 */
function dbi_escape_string(string $string): string
{
    global $db_connection_info;

    $string = stripslashes($string);

    switch ($GLOBALS['db_type']) {
        case 'mysqli':
            return $db_connection_info['connected']
                ? $db_connection_info['connection']->real_escape_string($string)
                : addslashes($string);
        case 'sqlite3':
            return SQLite3::escapeString($string);
        case 'pdo_mysql':
        case 'pdo_sqlite':
            return $db_connection_info['connection']
                ? $db_connection_info['connection']->quote($string)
                : addslashes($string);
        default:
            throw new DBIException(translate('db_type not defined'));
    }
}

/**
 * Executes a SQL query with parameter binding.
 *
 * @param string $sql           SQL query with ? placeholders
 * @param array  $params        Values for placeholders
 * @param bool   $fatalOnError  Throw exception on error?
 * @param bool   $showError     Display error to user?
 * @return mixed Query result resource/object or true/false for insert/delete
 * @throws DBIException
 */
function dbi_execute(string $sql, array $params = [], bool $fatalOnError = true, bool $showError = true)
{
    global $db_connection_info;

    if (empty($params)) {
        return dbi_query($sql, $fatalOnError, $showError);
    }

    try {
        $conn = $db_connection_info['connection'];
        switch ($GLOBALS['db_type']) {
            case 'mysqli':
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new DBIException(translate('Error preparing query') . ': ' . $conn->error);
                }
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
                $result = $stmt->execute();
                $res = $stmt->get_result() ?: $result;
                $stmt->close();
                return $res;
            case 'sqlite3':
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new DBIException(translate('Error preparing query') . ': ' . $conn->lastErrorMsg());
                }
                foreach ($params as $index => $param) {
                    $stmt->bindValue($index + 1, $param, is_null($param) ? SQLITE3_NULL : SQLITE3_TEXT);
                }
                $res = $stmt->execute();
                return $res ?: true;
            case 'pdo_mysql':
            case 'pdo_sqlite':
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new DBIException(translate('Error preparing query') . ': ' . $conn->errorInfo()[2]);
                }
                foreach ($params as $index => $param) {
                    $stmt->bindValue($index + 1, $param, is_null($param) ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $result = $stmt->execute();
                return $stmt->rowCount() > 0 ? $stmt : true;
            default:
                throw new DBIException(translate('db_type not defined'));
        }
    } catch (Exception $e) {
        if ($fatalOnError) {
            dbi_fatal_error($e->getMessage(), true, $showError);
        }
        return false;
    }
}

/**
 * Enable SQL debugging.
 *
 * @param bool $status Enable debugging?
 */
function dbi_set_debug(bool $status = false): void
{
    global $db_connection_info;

    if (!isset($db_connection_info)) {
        $db_connection_info = [];
    }

    $db_connection_info['debug'] = $status;
}

/**
 * Get the SQL debug status.
 *
 * @return bool
 */
function dbi_get_debug(): bool
{
    global $db_connection_info;

    return isset($db_connection_info) && !empty($db_connection_info['debug']);
}
?>

<?php
declare(strict_types=1);

/**
 * Utility functions for the checkbook application.
 *
 * This file contains helper functions for handling HTTP requests, database operations,
 * date manipulations, and other utilities used in the checkbook web application.
 * Updated for PHP 8 compatibility and best practices by Grok (xAI) in September 2025.
 *
 * @package Checkbook
 */

/**
 * Retrieves a value from the HTTP POST method.
 *
 * @param string $name The name of the form field.
 * @return string|null The value from the POST data, or null if not set.
 */
function getPostValue(string $name): ?string
{
    return isset($_POST[$name]) && is_string($_POST[$name]) && !empty($_POST[$name])
        ? $_POST[$name]
        : null;
}

/**
 * Retrieves a value from the HTTP GET method.
 *
 * @param string $name The name of the query parameter.
 * @return string|null The value from the GET data, or null if not set.
 */
function getGetValue(string $name): ?string
{
    return isset($_GET[$name]) && is_string($_GET[$name]) && !empty($_GET[$name])
        ? $_GET[$name]
        : null;
}

/**
 * Retrieves a value from either HTTP POST or GET method, with optional format validation.
 *
 * @param string $name   The name of the form field or query parameter.
 * @param string $format Optional regex pattern to validate the input.
 * @param bool   $fatal  If true, throws an exception on format mismatch.
 * @return string The validated value, or empty string if invalid or not set.
 * @throws Exception If $fatal is true and the input doesn't match the format.
 */
function getValue(string $name, string $format = '', bool $fatal = false): string
{
    $val = getPostValue($name) ?? getGetValue($name) ?? '';

    if ($format !== '' && !preg_match("/^$format$/", $val)) {
        if ($fatal) {
            throw new Exception('Fatal Error: Invalid data format for ' . $name);
        }
        return '';
    }

    return $val;
}

/**
 * Retrieves an integer value from HTTP POST or GET method.
 *
 * @param string $name  The name of the form field or query parameter.
 * @param bool   $fatal If true, throws an exception if the value is not an integer.
 * @return int|string The integer value, or empty string if invalid.
 * @throws Exception If $fatal is true and the input is not a valid integer.
 */
function getIntValue(string $name, bool $fatal = false): int|string
{
    $val = getValue($name, '-?[0-9]+', $fatal);
    return $val === '' ? '' : (int)$val;
}

/**
 * Retrieves the browser's preferred language from the HTTP Accept-Language header.
 *
 * @return string The language code (e.g., 'English-US'), defaults to 'English-US' if none found.
 */
function get_browser_language(): string
{
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (empty($acceptLang)) {
        return 'English-US';
    }

    // Parse Accept-Language header (e.g., "en-US,en;q=0.9,es;q=0.8")
    $langs = [];
    $lang_parse = preg_split('/[,;]/', $acceptLang);
    foreach ($lang_parse as $lang) {
        $lang = trim($lang);
        if (strpos($lang, 'q=') === false && !empty($lang)) {
            $langs[] = $lang;
        }
    }

    // Map language codes to supported translation file
    foreach ($langs as $lang) {
        if (in_array($lang, ['en', 'en-US'], true)) {
            return 'English-US';
        }
    }

    return 'English-US'; // Fallback
}

/**
 * Redirects to the specified URL, handling MS IIS/PWS compatibility.
 *
 * @param string $url The URL to redirect to.
 */
function do_redirect(string $url): void
{
    $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';

    if (str_starts_with($server_software, 'Micro')) {
        echo <<<HTML
<html>
<head>
    <title>Redirect</title>
    <meta http-equiv="refresh" content="0; url=$url" />
</head>
<body>
    Redirecting to ... <a href="$url">here</a>.
</body>
</html>
HTML;
    } else {
        header('Location: ' . $url);
        echo <<<HTML
<html>
<head>
    <title>Redirect</title>
</head>
<body>
    Redirecting to ... <a href="$url">here</a>.
</body>
</html>
HTML;
    }
    global $c;
    if (isset($c)) {
        dbi_close($c);
    }
    exit;
}

/**
 * Returns the full month name for the specified month.
 *
 * @param int $month Month number (0-11).
 * @return string The translated full month name.
 */
function month_name(int $month): string
{
    $months = [
        translate('January'),
        translate('February'),
        translate('March'),
        translate('April'),
        translate('May_'),
        translate('June'),
        translate('July'),
        translate('August'),
        translate('September'),
        translate('October'),
        translate('November'),
        translate('December'),
    ];
    return $months[$month] ?? "unknown-month($month)";
}

/**
 * Returns the abbreviated month name for the specified month.
 *
 * @param int $month Month number (0-11).
 * @return string The translated abbreviated month name.
 */
function month_short_name(int $month): string
{
    $months = [
        translate('Jan'),
        translate('Feb'),
        translate('Mar'),
        translate('Apr'),
        translate('May'),
        translate('Jun'),
        translate('Jul'),
        translate('Aug'),
        translate('Sep'),
        translate('Oct'),
        translate('Nov'),
        translate('Dec'),
    ];
    return $months[$month] ?? "unknown-month($month)";
}

/**
 * Returns the full weekday name for the specified day.
 *
 * @param int $weekday Weekday number (0=Sunday, ..., 6=Saturday).
 * @return string The translated full weekday name.
 */
function weekday_name(int $weekday): string
{
    $weekdays = [
        translate('Sunday'),
        translate('Monday'),
        translate('Tuesday'),
        translate('Wednesday'),
        translate('Thursday'),
        translate('Friday'),
        translate('Saturday'),
    ];
    return $weekdays[$weekday] ?? "unknown-weekday($weekday)";
}

/**
 * Returns the abbreviated weekday name for the specified day.
 *
 * @param int $weekday Weekday number (0=Sunday, ..., 6=Saturday).
 * @return string The translated abbreviated weekday name.
 */
function weekday_short_name(int $weekday): string
{
    $weekdays = [
        translate('Sun'),
        translate('Mon'),
        translate('Tue'),
        translate('Wed'),
        translate('Thu'),
        translate('Fri'),
        translate('Sat'),
    ];
    return $weekdays[$weekday] ?? "unknown-weekday($weekday)";
}

/**
 * Formats a date string according to the specified or default format.
 *
 * @param string $indate        Date in YYYYMMDD format.
 * @param string $format        Optional format string (e.g., '__month__ __dd__, __yyyy__').
 * @param bool   $show_weekday  Whether to include the weekday in the output.
 * @param bool   $short_months  Whether to use short month names.
 * @param string $server_time   Optional server time for timezone adjustments.
 * @return string The formatted date string.
 */
function date_to_str(string|int $indate, string $format = '', bool $show_weekday = true, bool $short_months = false, string $server_time = ''): string
{
    global $DATE_FORMAT, $TZ_OFFSET;

    $indate = empty($indate) ? date('Ymd') : (string)$indate;
    $format = empty($format) ? ($DATE_FORMAT ?: '__month__ __dd__, __yyyy__') : $format;

    $year = (int)substr($indate, 0, 4);
    $month = (int)substr($indate, 4, 2);
    $day = (int)substr($indate, 6, 2);

    if ($server_time !== '' && is_numeric($server_time)) {
        $time = (int)$server_time + ($TZ_OFFSET * 10000);
        if ($time > 240000) {
            $indate = date('Ymd', mktime(3, 0, 0, $month, $day + 1, $year));
        } elseif ($time < 0) {
            $indate = date('Ymd', mktime(3, 0, 0, $month, $day - 1, $year));
        }
    }

    $year = (int)substr($indate, 0, 4);
    $month = (int)substr($indate, 4, 2);
    $day = (int)substr($indate, 6, 2);
    $date = mktime(3, 0, 0, $month, $day, $year);
    $wday = (int)date('w', $date);

    $weekday = $show_weekday ? ($short_months ? weekday_short_name($wday) : weekday_name($wday)) : '';
    $month_name = $short_months ? month_short_name($month - 1) : month_name($month - 1);
    $yyyy = $year;
    $yy = sprintf('%02d', $year % 100);

    $ret = str_replace(['__yyyy__', '__yy__', '__month__', '__mon__', '__dd__', '__mm__'], [$yyyy, $yy, $month_name, $month_name, $day, $month], $format);

    return $show_weekday ? "$weekday, $ret" : $ret;
}

/**
 * Displays a fatal error message and terminates execution.
 *
 * @param string $msg The error message to display.
 */
if (!function_exists('fatalError')) {
    function fatalError(string $msg): void
    {
        echo <<<HTML
    <html>
    <head>
        <title>Error</title>
    </head>
    <body>
        <h2>Error</h2>
        $msg
    </body>
    </html>
    HTML;
        exit;
    }
}

/**
 * Updates account balances in the chk_account table based on transactions.
 *
 * @param int $acct The account ID.
 * @throws Exception On database errors.
 */
function update_balances(int $acct): void
{
    $res = dbi_execute('SELECT SUM(chk_amount) FROM chk_trans WHERE chk_acct_id = ?', [$acct]);
    if ($res) {
        $row = dbi_fetch_row($res);
        $sum = (float)($row[0] ?? 0);
        dbi_execute('UPDATE chk_account SET chk_balance = ? WHERE chk_acct_id = ?', [$sum, $acct]);
        dbi_free_result($res);
    } else {
        throw new Exception(translate('Database error') . ': ' . translate('Unable to calculate balance'));
    }

    $res = dbi_execute('SELECT SUM(chk_amount) FROM chk_trans WHERE chk_acct_id = ? AND chk_reconciled = ?', [$acct, 'Y']);
    if ($res) {
        $row = dbi_fetch_row($res);
        $sum = (float)($row[0] ?? 0);
        dbi_execute('UPDATE chk_account SET chk_bank_balance = ? WHERE chk_acct_id = ?', [$sum, $acct]);
        dbi_free_result($res);
    } else {
        throw new Exception(translate('Database error') . ': ' . translate('Unable to calculate bank balance'));
    }
}

/**
 * Shifts a date by a specified number of days.
 *
 * @param string $date The date in YYYYMMDD format.
 * @param int    $days Number of days to shift (positive or negative).
 * @return string The shifted date in YYYYMMDD format.
 */
function shiftDate(string|int $date, int $days): string
{
    $date = (string)$date;
    $year = (int)substr($date, 0, 4);
    $month = (int)substr($date, 4, 2);
    $day = (int)substr($date, 6, 2);
    return date('Ymd', mktime(3, 0, 0, $month, $day + $days, $year));
}

/**
 * Finds transactions matching a bank transaction within a date and amount range.
 *
 * @param array $bankTrans Transaction details with 'date', 'amount', and 'no'.
 * @param int   $daysBack  Days to search backward (default 14).
 * @param int   $acct      The account ID.
 * @return array List of matching transactions.
 * @throws Exception On database errors.
 */
function find_transactions(array $bankTrans, int $daysBack = 14, int $acct): array
{
    $ids = [];
    $matches = [];
    $date1 = shiftDate($bankTrans['date'], -$daysBack);
    $date2 = shiftDate($bankTrans['date'], 7);
    $min = number_format($bankTrans['amount'] - 0.50, 2, '.', '');
    $max = number_format($bankTrans['amount'] + 0.50, 2, '.', '');
    $foundCheckNum = false;

    $sql = 'SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description ' .
           'FROM chk_trans ' .
           'WHERE chk_amount > ? AND chk_amount < ? ' .
           'AND chk_reconciled = ? AND chk_date >= ? AND chk_date <= ? ' .
           'AND chk_acct_id = ?';
    $res = dbi_execute($sql, [$min, $max, 'N', $date1, $date2, $acct]);

    if (!$res) {
        throw new Exception(translate('Database error') . ': ' . translate('Unable to find transactions'));
    }

    while ($row = dbi_fetch_row($res)) {
        $matches[] = [
            'account' => $acct,
            'trans_id' => (int)$row[0],
            'type' => (int)$row[1],
            'no' => $row[2] !== null ? (string)$row[2] : '',
            'amount' => (float)$row[3],
            'date' => (string)$row[4],
            'description' => (string)$row[5],
        ];
        $ids[] = (int)$row[0];
        if ($row[2] !== null && (int)$row[2] > 100 && (int)$row[2] === (int)$bankTrans['no']) {
            $foundCheckNum = true;
        }
    }
    dbi_free_result($res);

    if (!$foundCheckNum && !empty($bankTrans['no']) && (int)$bankTrans['no'] > 99) {
        $sql = 'SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description ' .
               'FROM chk_trans ' .
               'WHERE chk_reconciled = ? AND chk_no = ? AND chk_acct_id = ?';
        $res = dbi_execute($sql, ['N', $bankTrans['no'], $acct]);

        if (!$res) {
            throw new Exception(translate('Database error') . ': ' . translate('Unable to find transactions by check number'));
        }

        $matches = [];
        while ($row = dbi_fetch_row($res)) {
            $matches[] = [
                'account' => $acct,
                'trans_id' => (int)$row[0],
                'type' => (int)$row[1],
                'no' => $row[2] !== null ? (string)$row[2] : '',
                'amount' => (float)$row[3],
                'date' => (string)$row[4],
                'description' => (string)$row[5],
            ];
            $ids[] = (int)$row[0];
        }
        dbi_free_result($res);
    }

    $min = number_format(-$bankTrans['amount'] - 0.50, 2, '.', '');
    $max = number_format(-$bankTrans['amount'] + 0.50, 2, '.', '');
    $sql = 'SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description ' .
           'FROM chk_trans ' .
           'WHERE chk_amount > ? AND chk_amount < ? ' .
           'AND chk_reconciled = ? AND chk_date >= ? AND chk_date <= ? ' .
           'AND chk_acct_id = ?';
    $res = dbi_execute($sql, [$min, $max, 'N', $date1, $date2, $acct]);

    if (!$res) {
        throw new Exception(translate('Database error') . ': ' . translate('Unable to find transactions'));
    }

    while ($row = dbi_fetch_row($res)) {
        $matches[] = [
            'account' => $acct,
            'trans_id' => (int)$row[0],
            'type' => (int)$row[1],
            'no' => $row[2] !== null ? (string)$row[2] : '',
            'amount' => (float)$row[3],
            'date' => (string)$row[4],
            'description' => (string)$row[5],
        ];
        $ids[] = (int)$row[0];
    }
    dbi_free_result($res);

    $date1 = shiftDate($bankTrans['date'], -30);
    $date2 = shiftDate($bankTrans['date'], 0);
    $sql = 'SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description ' .
           'FROM chk_trans ' .
           'WHERE chk_reconciled = ? AND chk_date >= ? AND chk_date <= ? ' .
           'AND chk_acct_id = ? ' .
           'ORDER BY chk_date DESC LIMIT 10';
    $res = dbi_execute($sql, ['N', $date1, $date2, $acct]);

    if (!$res) {
        throw new Exception(translate('Database error') . ': ' . translate('Unable to find recent transactions'));
    }

    while ($row = dbi_fetch_row($res)) {
        if (!in_array((int)$row[0], $ids, true)) {
            $matches[] = [
                'account' => $acct,
                'trans_id' => (int)$row[0],
                'type' => (int)$row[1],
                'no' => $row[2] !== null ? (string)$row[2] : '',
                'amount' => (float)$row[3],
                'date' => (string)$row[4],
                'description' => (string)$row[5],
            ];
            $ids[] = (int)$row[0];
        }
    }
    dbi_free_result($res);

    return $matches;
}

/**
 * Retrieves the description of the most recent reconciled transaction matching a bank description.
 *
 * @param int    $acct           The account ID.
 * @param string $bankDescription The bank transaction description.
 * @return string The matching description, or empty string if none found.
 */
function get_description_from_prior_reconcile(int $acct, string $bankDescription): string
{
    $sql = 'SELECT chk_trans.chk_description ' .
           'FROM chk_bank_trans ' .
           'INNER JOIN chk_trans ON chk_trans.chk_trans_id = chk_bank_trans.chk_trans_id ' .
           'WHERE chk_bank_trans.chk_description = ? AND chk_trans.chk_acct_id = ? ' .
           'ORDER BY chk_trans.chk_date DESC LIMIT 1';
    $res = dbi_execute($sql, [$bankDescription, $acct]);

    if (!$res) {
        throw new Exception(translate('Database error') . ': ' . translate('Unable to retrieve prior description'));
    }

    $ret = '';
    if ($row = dbi_fetch_row($res)) {
        $ret = (string)$row[0];
    }
    dbi_free_result($res);
    return $ret;
}

/**
 * Retrieves the amount of the most recent transaction for a given description.
 *
 * @param int    $acct        The account ID.
 * @param string $description The transaction description.
 * @return string The amount, or empty string if none found.
 */
function get_last_amount_for_description(int $acct, string $description): string
{
    $sql = 'SELECT chk_amount FROM chk_trans ' .
           'WHERE chk_acct_id = ? AND chk_description = ? ' .
           'ORDER BY chk_date DESC LIMIT 1';
    $res = dbi_execute($sql, [$acct, $description]);

    if (!$res) {
        throw new Exception(translate('Database error') . ': ' . translate('Unable to retrieve last amount'));
    }

    $ret = '';
    if ($row = dbi_fetch_row($res)) {
        $ret = (string)$row[0];
    }
    dbi_free_result($res);
    return $ret;
}

/**
 * Loads account info from the database.
 *
 * @param int $acctId The account ID.
 * @return array Account data with keys: acct_id, bank, name, account_no, balance, bank_balance, start_date, end_date
 */
function get_account_info(int $acctId): array
{
    $sql = 'SELECT chk_bank, chk_name, chk_account_no, chk_balance, chk_bank_balance ' .
           'FROM chk_account WHERE chk_acct_id = ?';
    $res = dbi_execute($sql, [$acctId]);
    if (!$res) {
        fatalError('Database error: ' . dbi_error());
    }
    $row = dbi_fetch_row($res);
    if (!$row) {
        fatalError('No such account: ' . $acctId);
    }
    $account = [
        'acct_id' => $acctId,
        'bank' => (string)$row[0],
        'name' => (string)$row[1],
        'account_no' => (string)$row[2],
        'balance' => (float)$row[3],
        'bank_balance' => (float)$row[4],
    ];
    dbi_free_result($res);

    $sql = 'SELECT MIN(chk_date), MAX(chk_date) FROM chk_trans WHERE chk_acct_id = ?';
    $res = dbi_execute($sql, [$acctId]);
    if ($res) {
        if ($row = dbi_fetch_row($res)) {
            $account['start_date'] = (string)($row[0] ?? '');
            $account['end_date'] = (string)($row[1] ?? '');
        }
        dbi_free_result($res);
    }

    return $account;
}

/**
 * Gets the next available transaction ID for an account.
 *
 * @param int $acctId The account ID.
 * @return int The next transaction ID.
 */
function get_next_trans_id(int $acctId): int
{
    $sql = 'SELECT MAX(chk_trans_id) FROM chk_trans WHERE chk_acct_id = ?';
    $res = dbi_execute($sql, [$acctId]);
    $nextId = 1;
    if ($res) {
        if ($row = dbi_fetch_row($res)) {
            $nextId = (int)$row[0] + 1;
        }
        dbi_free_result($res);
    }
    return $nextId;
}

/**
 * Parses a user-entered date string (MM/DD/YYYY, MM/DD/YY, MM/DD, or D) into YYYYMMDD format.
 *
 * @param string $date The date string to parse.
 * @return string Date in YYYYMMDD format, or empty string if parsing fails.
 */
function parse_date_input(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        return '';
    }

    $parts = preg_split('/[\/\-]/', $date);
    if ($parts === false) {
        return '';
    }

    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');

    if (count($parts) === 1) {
        // Just a day number
        $month = $currentMonth;
        $day = (int)$parts[0];
        $year = $currentYear;
    } elseif (count($parts) === 2) {
        // MM/DD
        $month = (int)$parts[0];
        $day = (int)$parts[1];
        $year = $currentYear;
        // If the month is in the future, assume last year
        if ($month > $currentMonth) {
            $year--;
        }
    } elseif (count($parts) >= 3) {
        $month = (int)$parts[0];
        $day = (int)$parts[1];
        $year = (int)$parts[2];
        if ($year < 100) {
            $year += 2000;
        }
        if ($year > 2050) {
            $year -= 100; // 1990s
        }
    } else {
        return '';
    }

    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
        return '';
    }

    return sprintf('%04d%02d%02d', $year, $month, $day);
}

/**
 * Parses CSV header row and returns column indices.
 *
 * @param array $headers Array of header strings from the CSV first row.
 * @return array Associative array with keys: date, check, description, debit, credit, status, balance.
 *               Values are column indices (0-based) or -1 if not found.
 */
function parse_csv_headers(array $headers): array
{
    $indices = [
        'date' => -1,
        'check' => -1,
        'description' => -1,
        'debit' => -1,
        'credit' => -1,
        'status' => -1,
        'balance' => -1,
    ];

    foreach ($headers as $i => $header) {
        $h = strtolower(trim((string)$header));
        if (preg_match('/date/', $h) && $indices['date'] === -1) {
            $indices['date'] = $i;
        } elseif (preg_match('/check/', $h) && $indices['check'] === -1) {
            $indices['check'] = $i;
        } elseif (preg_match('/descr/', $h) && $indices['description'] === -1) {
            $indices['description'] = $i;
        } elseif (preg_match('/debit/', $h) && $indices['debit'] === -1) {
            $indices['debit'] = $i;
        } elseif (preg_match('/credit/', $h) && $indices['credit'] === -1) {
            $indices['credit'] = $i;
        } elseif (preg_match('/status/', $h) && $indices['status'] === -1) {
            $indices['status'] = $i;
        } elseif (preg_match('/balance/', $h) && $indices['balance'] === -1) {
            $indices['balance'] = $i;
        }
    }

    return $indices;
}

/**
 * Validates CSV headers and returns an array of error messages.
 *
 * @param array $indices Result from parse_csv_headers().
 * @return array Array of error strings (empty if all required headers found).
 */
function validate_csv_headers(array $indices): array
{
    $errors = [];
    $required = ['date', 'check', 'description', 'debit', 'credit', 'balance'];
    foreach ($required as $field) {
        if ($indices[$field] < 0) {
            $errors[] = 'Did not find "' . $field . '" in header';
        }
    }
    return $errors;
}

/**
 * Parses a single CSV data row into a transaction array.
 *
 * @param array $data       The CSV row data.
 * @param array $indices    Column indices from parse_csv_headers().
 * @param int   $numHeaders Expected number of columns.
 * @param int   $lineNum    Line number in the CSV file (for error messages).
 * @return array ['transaction' => array|null, 'error' => string|null]
 */
function parse_csv_row(array $data, array $indices, int $numHeaders, int $lineNum): array
{
    if (count($data) !== $numHeaders) {
        return [
            'transaction' => null,
            'error' => sprintf('Line %d has %d columns instead of %d', $lineNum, count($data), $numHeaders),
        ];
    }

    $date = $data[$indices['date']] ?? '';
    if (!preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $date, $args)) {
        return [
            'transaction' => null,
            'error' => sprintf('Invalid date format at line %d: %s', $lineNum, $date),
        ];
    }
    $month = (int)$args[1];
    $day = (int)$args[2];
    $year = (int)$args[3];
    if ($year < 100) {
        $year += 2000;
    }
    if (!checkdate($month, $day, $year)) {
        return [
            'transaction' => null,
            'error' => sprintf('Invalid date at line %d', $lineNum),
        ];
    }
    $dateStr = sprintf('%04d%02d%02d', $year, $month, $day);

    $credit = (float)($data[$indices['credit']] ?? 0.0);
    $debit = (float)($data[$indices['debit']] ?? 0.0);
    $amount = $credit > 0.0 ? $credit : -$debit;
    if ($amount == 0.0) {
        return ['transaction' => null, 'error' => null]; // skip empty
    }

    $checkNo = !empty($data[$indices['check']]) ? (string)$data[$indices['check']] : null;
    $desc = strtoupper(trim((string)($data[$indices['description']] ?? '')));
    if (strlen($desc) > 100) {
        return [
            'transaction' => null,
            'error' => sprintf('Description too long at line %d', $lineNum),
        ];
    }

    return [
        'transaction' => [
            'date' => $dateStr,
            'amount' => $amount,
            'no' => $checkNo,
            'desc' => $desc,
            'memo' => '',
        ],
        'error' => null,
    ];
}

/**
 * Determines transaction type from amount and check number.
 *
 * @param float       $amount The transaction amount (positive or negative).
 * @param string|null $checkNo The check number, if any.
 * @return int Transaction type: 1=Deposit, 2=Debit, 3=Check, 4=Fee
 */
function determine_transaction_type(float $amount, ?string $checkNo): int
{
    if ($amount >= 0) {
        return 1; // Deposit
    }
    if (!empty($checkNo)) {
        return 3; // Check
    }
    return 2; // Debit
}

/**
 * Formats an amount for display.
 *
 * @param float $amount The amount.
 * @return string Formatted amount string.
 */
function format_amount(float $amount): string
{
    return sprintf('%.2f', $amount);
}

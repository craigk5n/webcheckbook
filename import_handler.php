<?php
declare(strict_types=1);

/**
 * Handles the import of transactions from a CSV file in the checkbook application.
 *
 * Processes an uploaded CSV file, validates its structure, and inserts transactions
 * into the database. Displays import results or errors. Updated for PHP 8 best
 * practices by Grok (xAI) in September 2025.
 *
 * @package Checkbook
 */

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

$acct = getIntValue('acct', true);
if (empty($acct)) {
    fatalError(translate('No account specified'));
}

// Get account info using prepared statement
$sql = 'SELECT chk_bank, chk_name, chk_account_no, chk_balance, chk_bank_balance ' .
       'FROM chk_account WHERE chk_acct_id = ?';
$res = dbi_execute($sql, [$acct]);
$account = [];
if ($res) {
    if ($row = dbi_fetch_row($res)) {
        $account = [
            'acct_id' => $acct,
            'bank' => (string)$row[0],
            'name' => (string)$row[1],
            'account_no' => (string)$row[2],
            'balance' => (float)$row[3],
            'bank_balance' => (float)$row[4],
        ];
        dbi_free_result($res);
    } else {
        fatalError(translate('No such account: ') . $acct);
    }
} else {
    fatalError(translate('Database error') . ': Unable to retrieve account information.');
}

// Get first and last transaction date
$sql = 'SELECT MIN(chk_date), MAX(chk_date) FROM chk_trans WHERE chk_acct_id = ?';
$res = dbi_execute($sql, [$acct]);
if ($res) {
    if ($row = dbi_fetch_row($res)) {
        $account['start_date'] = (string)($row[0] ?? '');
        $account['end_date'] = (string)($row[1] ?? '');
    }
    dbi_free_result($res);
}

$description = getValue('description');
if (strlen($description) > 100) {
    fatalError(translate('Description too long'));
}
if (empty($description) && !empty($_FILES['FileName']['name'])) {
    $description = htmlspecialchars(basename($_FILES['FileName']['name']));
}

if (empty($_FILES['FileName']) || $_FILES['FileName']['size'] === 0 || $_FILES['FileName']['error'] !== UPLOAD_ERR_OK) {
    fatalError(translate('No valid file uploaded'));
}

$title = translate('Import Results');
print_header($title);
print_heading($title);

$errors = [];
$start_date = '';
$end_date = '';
$transactions = [];
$sequence = 0;

// Get next statement ID
$statement_id = 1;
$sql = 'SELECT MAX(chk_statement_id) FROM chk_bank_statement';
$res = dbi_execute($sql, []);
if ($res) {
    if ($row = dbi_fetch_row($res)) {
        $statement_id = (int)$row[0] + 1;
    }
    dbi_free_result($res);
}

// Process CSV file
$fd = fopen($_FILES['FileName']['tmp_name'], 'r');
if ($fd === false) {
    fatalError(translate('Error opening uploaded file'));
}

$data = fgetcsv($fd, 1000, ',');
if ($data === false) {
    fclose($fd);
    fatalError(translate('Error reading CSV file'));
}

$num_header = count($data);
$date_ind = $chk_ind = $desc_ind = $debit_ind = $credit_ind = $status_ind = $balance_ind = -1;
foreach ($data as $i => $header) {
    $h = strtolower((string)$header);
    if (preg_match('/date/', $h)) {
        $date_ind = $i;
    } elseif (preg_match('/check/', $h)) {
        $chk_ind = $i;
    } elseif (preg_match('/descr/', $h)) {
        $desc_ind = $i;
    } elseif (preg_match('/debit/', $h)) {
        $debit_ind = $i;
    } elseif (preg_match('/credit/', $h)) {
        $credit_ind = $i;
    } elseif (preg_match('/status/', $h)) {
        $status_ind = $i;
    } elseif (preg_match('/balance/', $h)) {
        $balance_ind = $i;
    }
}

// Validate required headers
if ($date_ind < 0) {
    $errors[] = translate('Did not find "date" in header');
}
if ($chk_ind < 0) {
    $errors[] = translate('Did not find "check" in header');
}
if ($desc_ind < 0) {
    $errors[] = translate('Did not find "description" in header');
}
if ($credit_ind < 0) {
    $errors[] = translate('Did not find "credit" in header');
}
if ($debit_ind < 0) {
    $errors[] = translate('Did not find "debit" in header');
}
if ($balance_ind < 0) {
    $errors[] = translate('Did not find "balance" in header');
}

if (empty($errors)) {
    $line = 1;
    while (($data = fgetcsv($fd, 1000, ',')) !== false) {
        $line++;
        if (count($data) !== $num_header) {
            $errors[] = sprintf(translate('Line %d has %d columns instead of %d'), $line, count($data), $num_header);
            continue;
        }

        $date = $data[$date_ind] ?? '';
        if (!preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $date, $args)) {
            $errors[] = sprintf(translate('Invalid date format at line %d'), $line);
            continue;
        }
        $month = (int)$args[1];
        $day = (int)$args[2];
        $year = (int)$args[3];
        if ($year < 100) {
            $year += 2000;
        }
        if (!checkdate($month, $day, $year)) {
            $errors[] = sprintf(translate('Invalid date at line %d'), $line);
            continue;
        }
        $date_str = sprintf('%04d%02d%02d', $year, $month, $day);

        $credit = (float)($data[$credit_ind] ?? 0.0);
        $debit = (float)($data[$debit_ind] ?? 0.0);
        $amount = $credit > 0.0 ? $credit : -$debit;
        if ($amount == 0.0) {
            continue; // Skip empty transactions
        }

        $check_no = !empty($data[$chk_ind]) ? (string)$data[$chk_ind] : null;
        $desc = strtoupper((string)($data[$desc_ind] ?? ''));
        if (strlen($desc) > 100) {
            $errors[] = sprintf(translate('Description too long at line %d'), $line);
            continue;
        }

        $transactions[] = [
            'date' => $date_str,
            'amount' => $amount,
            'no' => $check_no,
            'desc' => $desc,
            'memo' => '',
        ];

        if (empty($start_date) || $date_str < $start_date) {
            $start_date = $date_str;
        }
        if (empty($end_date) || $date_str > $end_date) {
            $end_date = $date_str;
        }
    }
    fclose($fd);
} else {
    fclose($fd);
}

if (empty($errors)) {
    // Insert statement
    $sql = 'INSERT INTO chk_bank_statement (chk_acct_id, chk_statement_id, chk_description, chk_start_date, chk_end_date) ' .
           'VALUES (?, ?, ?, ?, ?)';
    if (!dbi_execute($sql, [$acct, $statement_id, $description, $start_date ?: date('Ymd'), $end_date ?: date('Ymd')])) {
        $errors[] = translate('Database error') . ': Unable to insert statement.';
    }

    // Insert transactions
    foreach ($transactions as $index => $trans) {
        add_transaction($trans['date'], $trans['amount'], $trans['no'], $trans['desc'], $trans['memo'], $index + 1);
    }
}

if (empty($errors)) {
    echo htmlspecialchars(sprintf(translate('%d transactions were imported'), count($transactions))) . "\n";
} else {
    echo htmlspecialchars(translate('Errors importing')) . ":<br><br>\n";
    foreach ($errors as $error) {
        echo htmlspecialchars($error) . "<br>\n";
    }
}

print_trailer();

/**
 * Inserts a single transaction into chk_bank_trans.
 *
 * @param string $date   Transaction date (YYYYMMDD)
 * @param float  $amount Transaction amount
 * @param ?string $no    Check number or null
 * @param string $desc   Transaction description
 * @param string $memo   Transaction memo
 * @param int    $sequence Transaction sequence number
 */
function add_transaction(string $date, float $amount, ?string $no, string $desc, string $memo, int $sequence): void {
    global $acct, $statement_id, $errors;

    echo '<br><br><b>' . translate('Date') . ':</b> ' . htmlspecialchars($date) . '<br>';
    echo '<b>' . translate('Amount') . ':</b> ' . htmlspecialchars(sprintf('%.2f', $amount)) . '<br>';
    echo '<b>' . translate('No') . ':</b> ' . htmlspecialchars($no ?? '-') . '<br>';
    echo '<b>' . translate('Desc') . ':</b> ' . htmlspecialchars($desc) . '<br>';
    echo '<b>' . translate('Memo') . ':</b> ' . htmlspecialchars($memo) . '<br>';

    $sql = 'INSERT INTO chk_bank_trans (chk_acct_id, chk_statement_id, chk_sequence, chk_no, chk_amount, chk_date, chk_description, chk_memo) ' .
           'VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    if (!dbi_execute($sql, [$acct, $statement_id, $sequence, $no, $amount, $date, $desc, $memo])) {
        $errors[] = translate('Database error') . ': ' . translate('Unable to insert transaction') . ' (' . htmlspecialchars($date) . ')';
    }
}
?>
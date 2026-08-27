<?php
declare(strict_types=1);

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

$account = get_account_info($acct);

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

$headerRow = csv_read_row($fd);
if ($headerRow === false) {
    fclose($fd);
    fatalError(translate('Error reading CSV file'));
}

$num_header = count($headerRow);
$indices = parse_csv_headers($headerRow);
$errors = validate_csv_headers($indices);

if (empty($errors)) {
    $line = 1;
    while (($data = csv_read_row($fd)) !== false) {
        $line++;
        $result = parse_csv_row($data, $indices, $num_header, $line);

        if ($result['error'] !== null) {
            $errors[] = $result['error'];
            continue;
        }

        if ($result['transaction'] === null) {
            continue; // Skipped (e.g. zero amount)
        }

        $trans = $result['transaction'];
        $transactions[] = $trans;

        if (empty($start_date) || $trans['date'] < $start_date) {
            $start_date = $trans['date'];
        }
        if (empty($end_date) || $trans['date'] > $end_date) {
            $end_date = $trans['date'];
        }
    }
}
fclose($fd);

if (empty($errors)) {
    // Insert statement
    $sql = 'INSERT INTO chk_bank_statement (chk_acct_id, chk_statement_id, chk_description, chk_start_date, chk_end_date) ' .
           'VALUES (?, ?, ?, ?, ?)';
    if (!dbi_execute($sql, [$acct, $statement_id, $description, $start_date ?: date('Ymd'), $end_date ?: date('Ymd')])) {
        $errors[] = translate('Database error') . ': Unable to insert statement.';
    }

    // Insert transactions
    foreach ($transactions as $index => $trans) {
        $sequence = $index + 1;
        echo '<br><br><b>' . translate('Date') . ':</b> ' . htmlspecialchars($trans['date']) . '<br>';
        echo '<b>' . translate('Amount') . ':</b> ' . htmlspecialchars(sprintf('%.2f', $trans['amount'])) . '<br>';
        echo '<b>' . translate('No') . ':</b> ' . htmlspecialchars($trans['no'] ?? '-') . '<br>';
        echo '<b>' . translate('Desc') . ':</b> ' . htmlspecialchars($trans['desc']) . '<br>';
        echo '<b>' . translate('Memo') . ':</b> ' . htmlspecialchars($trans['memo']) . '<br>';

        $sql = 'INSERT INTO chk_bank_trans (chk_acct_id, chk_statement_id, chk_sequence, chk_no, chk_amount, chk_date, chk_description, chk_memo) ' .
               'VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        if (!dbi_execute($sql, [$acct, $statement_id, $sequence, $trans['no'], $trans['amount'], $trans['date'], $trans['desc'], $trans['memo']])) {
            $errors[] = translate('Database error') . ': Unable to insert transaction (' . htmlspecialchars($trans['date']) . ')';
        }
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

<?php
declare(strict_types=1);

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

$acct = getIntValue('acct');
if (empty($acct))
    fatalError('No account specified');

$statement = getIntValue('statement');
if (empty($statement))
    fatalError('No statement specified');

$seq = getIntValue('seq');
if (!isset($seq))
    fatalError('No sequence specified');
if (empty($seq))
    $seq = 0;

$adding = getValue('add') !== '';

$trans_id = getIntValue('trans_id');
if (empty($trans_id) && !$adding)
    fatalError('You must select a transaction');

$Account = get_account_info($acct);

// Add new transaction first
if ($adding) {
    $next_id = get_next_trans_id($acct);
    $trans_id = $next_id;

    $date = getValue('date');
    $dateStr = parse_date_input($date);
    if (empty($dateStr)) {
        fatalError('Invalid date');
    }

    $num = getValue('num');
    $description = getValue('description');
    $amount = (float)getValue('amount');
    if ($amount < 0) {
        $type = !empty($num) ? 3 : 2; // check or debit
    } else {
        $type = 1; // deposit
    }
    if (empty($num)) {
        $num = null;
    }
    $description = strtoupper($description);

    $sql = 'INSERT INTO chk_trans (chk_acct_id, chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description, chk_reconciled) ' .
           'VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    if (!dbi_execute($sql, [$acct, $next_id, $type, $num, $amount, $dateStr, $description, 'N'])) {
        fatalError('Database error: ' . dbi_error());
    }
}

// Mark transaction as reconciled
$sql = 'UPDATE chk_trans SET chk_reconciled = ? WHERE chk_acct_id = ? AND chk_trans_id = ?';
if (!dbi_execute($sql, ['Y', $acct, $trans_id]))
    fatalError('Database error: ' . dbi_error());

// Link bank transaction to user transaction
$sql = 'UPDATE chk_bank_trans SET chk_trans_id = ? WHERE chk_acct_id = ? AND chk_statement_id = ? AND chk_sequence = ?';
if (!dbi_execute($sql, [$trans_id, $acct, $statement, $seq]))
    fatalError('Database error: ' . dbi_error());

update_balances($acct);

// Find next unreconciled bank transaction
$sql = 'SELECT chk_sequence FROM chk_bank_trans ' .
       'WHERE chk_acct_id = ? AND chk_statement_id = ? AND chk_sequence > ? ' .
       'ORDER BY chk_sequence';
$res = dbi_execute($sql, [$acct, $statement, $seq]);
if (!$res)
    fatalError('Database error: ' . dbi_error());

if ($row = dbi_fetch_row($res)) {
    $url = "reconcile.php?acct=$acct&statement=$statement&seq=$row[0]";
    dbi_free_result($res);
    do_redirect($url);
} else {
    dbi_free_result($res);
    do_redirect("reconcile.php?acct=$acct&statement=$statement");
}

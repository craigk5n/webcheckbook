<?php
declare(strict_types=1);

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

$acct = getIntValue('acct');
if (empty($acct)) {
    fatalError('No account specified');
}

$Account = get_account_info($acct);
$next_id = get_next_trans_id($acct);

for ($i = 0; $i < 99; $i++) {
    $date = getValue("date_$i");
    $description = getValue("description_$i");
    if (empty($date) || empty($description)) {
        continue;
    }

    $dateStr = parse_date_input($date);
    if (empty($dateStr)) {
        continue;
    }

    $type = (int)getValue("type_$i");
    $num = getValue("num_$i");
    if ($type != 3) {
        $num = null;
    }
    $amount = (float)getValue("amount_$i");
    if ($type != 1) {
        $amount = -$amount;
    }
    $description = strtoupper($description);

    $sql = 'INSERT INTO chk_trans (chk_acct_id, chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description, chk_reconciled) ' .
           'VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    if (!dbi_execute($sql, [$acct, $next_id, $type, $num, $amount, $dateStr, $description, 'N'])) {
        fatalError('Database error: ' . dbi_error());
    }
    $next_id++;
}

update_balances($acct);

do_redirect("add_trans.php?acct=$acct");

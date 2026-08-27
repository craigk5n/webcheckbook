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

print_header(translate('Reports') . ': ' . $Account['name']);
print_heading(translate('Reports') . ' ' . translate('Account') . ': ' . $Account['name']);
print_account_info($Account);

$start = getValue('start');
$end = getValue('end');
$search = getValue('search');
$rec = getValue('reconciled');

$sql = 'SELECT chk_no FROM chk_trans WHERE chk_acct_id = ? ORDER BY chk_no ASC';
$res = dbi_execute($sql, [$acct]);
if (!$res)
    fatalError('Error in query: ' . dbi_error());

$lastNum = -1;
$missing = [];
$dups = [];
$found = [];
$first = 0;
$last = 0;
while ($row = dbi_fetch_row($res)) {
    $thisNum = (int)$row[0];
    $last = $thisNum;
    if ($first == 0)
        $first = $thisNum;
    if ($lastNum > 0 && $thisNum == $lastNum)
        $dups[] = $thisNum;
    $found[] = $thisNum;
    $lastNum = $thisNum;
}
dbi_free_result($res);

for ($i = $first; $i <= $last; $i++) {
    if (!in_array($i, $found)) {
        $missing[] = $i;
    }
}

?>
<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Duplicate Check Numbers</h5></div>
    <div class="card-body">
<?php if (count($dups) == 0): ?>
        <p class="text-muted mb-0">None</p>
<?php else: ?>
        <div class="d-flex flex-wrap gap-1">
<?php foreach ($dups as $d): ?>
            <span class="badge bg-danger"><?php echo $d; ?></span>
<?php endforeach; ?>
        </div>
<?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Missing Check Numbers</h5></div>
    <div class="card-body">
<?php if (count($missing) == 0): ?>
        <p class="text-muted mb-0">None</p>
<?php else: ?>
        <div class="d-flex flex-wrap gap-1">
<?php foreach ($missing as $m): ?>
            <span class="badge bg-warning text-dark"><?php echo $m; ?></span>
<?php endforeach; ?>
        </div>
<?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Unreconciled Transactions</h5></div>
    <div class="card-body">
<?php

// Get posting date of most recent reconciled transaction
$sql = 'SELECT MAX(chk_date) FROM chk_trans WHERE chk_reconciled = ? AND chk_acct_id = ?';
$res = dbi_execute($sql, ['Y', $acct]);
if (!$res)
    fatalError('Error in query: ' . dbi_error());
$row = dbi_fetch_row($res);
$lastDate = $row[0] ?? '';
dbi_free_result($res);

if (empty($lastDate)) {
    echo '<p class="text-muted mb-0">Nothing reconciled yet.</p>';
} else {
    echo '<p>Showing transactions before <strong>' .
        date_to_str($lastDate, '__mm__/__dd__/__yyyy__', false) . '</strong></p>' . "\n";

    $sql = 'SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description ' .
           'FROM chk_trans WHERE chk_reconciled = ? AND chk_date <= ? AND chk_acct_id = ? ' .
           'ORDER BY chk_date DESC';
    $res = dbi_execute($sql, ['N', $lastDate, $acct]);
    if (!$res)
        fatalError('Error in query: ' . dbi_error());

    $cnt = 0;
    while ($row = dbi_fetch_row($res)) {
        $trans = [
            'account' => $acct,
            'trans_id' => $row[0],
            'type' => $row[1],
            'no' => $row[2],
            'amount' => $row[3],
            'date' => $row[4],
            'description' => $row[5],
            'reconciled' => 'N',
        ];
        if ($trans['amount'] != 0.0) {
            $cnt++;
            print_transaction($trans, $cnt == 1, false);
        }
    }
    dbi_free_result($res);
    if ($cnt > 0) {
        close_table();
    } else {
        echo '<p class="text-muted mb-0">None</p>';
    }
}
?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Incorrect Reconciled Amounts</h5></div>
    <div class="card-body">
<?php

$sql = 'SELECT b.chk_acct_id, b.chk_statement_id, b.chk_sequence, ' .
    'b.chk_no, b.chk_amount, b.chk_date, b.chk_description, ' .
    'b.chk_memo, b.chk_trans_id, ' .
    'a.chk_type, a.chk_no, a.chk_amount, a.chk_date, a.chk_description, ' .
    'a.chk_reconciled ' .
    'FROM chk_bank_trans AS b ' .
    'LEFT OUTER JOIN chk_trans a ON b.chk_trans_id = a.chk_trans_id ' .
    'WHERE a.chk_acct_id = ? AND a.chk_amount <> b.chk_amount AND b.chk_acct_id = ? ' .
    'ORDER BY b.chk_date ASC';
$res = dbi_execute($sql, [$acct, $acct]);
$arr = ['Bank Chk#', 'Bank Amount', 'Bank Date', 'Bank Description',
    'Chk#', 'Amount', 'Date', 'Description', 'Memo'];
open_table($arr);
$rowNum = 0;
while ($row = dbi_fetch_row($res)) {
    $i = 0;
    $trans = [
        'bank.acct_id' => $row[$i++],
        'bank.b.chk_statement_id' => $row[$i++],
        'bank.b.chk_sequence' => $row[$i++],
        'bank.chk_no' => $row[$i++],
        'bank.amount' => $row[$i++],
        'bank.date' => $row[$i++],
        'bank.description' => $row[$i++],
        'bank.memo' => $row[$i++],
        'bank.trans_id' => $row[$i++],
        'my.chk_type' => $row[$i++],
        'my.chk_no' => $row[$i++],
        'my.amount' => $row[$i++],
        'my.date' => $row[$i++],
        'my.description' => $row[$i++],
        'my.reconciled' => $row[$i++],
    ];
    if (abs($trans['bank.amount'] - $trans['my.amount']) < 0.009)
        continue;
    $rowNum++;
    $url = "edit_trans.php?acct=" . $acct . "&trans=" . (int)$trans['bank.trans_id'];
    $rowClass = ($trans['bank.amount'] > 0.0) ? 'table-success' : '';
    print "<tr class=\"$rowClass\">";
    print "<td class=\"text-end\">" .
        (empty($trans['bank.chk_no']) ? '-' : htmlentities((string)$trans['bank.chk_no'])) . "</td>";
    print "<td class=\"text-end\">" .
        sprintf('%.2f', $trans['bank.amount']) . "</td>";
    print "<td>" .
        date_to_str($trans['bank.date'], '__mm__/__dd__/__yyyy__', false) . "</td>";
    print "<td>" .
        htmlentities($trans['bank.description']) . "</td>";
    print "<td class=\"text-end\">" .
        (empty($trans['my.chk_no']) ? '-' : htmlentities((string)$trans['my.chk_no'])) . "</td>";
    print "<td class=\"text-end\">" .
        sprintf('%.2f', $trans['my.amount']) . "</td>";
    print "<td><a href=\"$url\">" .
        date_to_str($trans['my.date'], '__mm__/__dd__/__yyyy__', false) . "</a></td>";
    print "<td>" .
        htmlentities($trans['my.description']) . "</td>";
    print "<td>" .
        htmlentities($trans['my.memo'] ?? '') . "</td>";
    print "</tr>\n";
}
dbi_free_result($res);
close_table();
if ($rowNum == 0) {
    echo '<p class="text-muted">None</p>';
}
?>
    </div>
</div>

<?php
print_trailer();

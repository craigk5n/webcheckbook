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

print_header(translate('Account') . ': ' . $Account['name']);
print_heading(translate('Search') . ' ' . translate('Account') . ': ' . $Account['name']);
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
<h3>Duplicate Check Numbers</h3>
<ul>
<?php
if (count($dups) == 0)
    echo "<li>None</li>\n";
for ($i = 0; $i < count($dups); $i++) {
    print "<li> " . $dups[$i] . " </li>\n";
}
?>
</ul>

<h3>Missing Check Numbers</h3>
<ul>
<?php
if (count($missing) == 0)
    echo "<li>None</li>\n";
for ($i = 0; $i < count($missing); $i++) {
    print "<li> " . $missing[$i] . " </li>\n";
}
?>
</ul>

<h3>Unreconciled Transactions</h3>
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
    echo "<p>Nothing reconciled yet.</p>";
} else {
    ?><p>Showing transactions before <b><?php
    echo date_to_str($lastDate, '__mm__/__dd__/__yyyy__', false);
    echo "</b></p>\n";

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
    close_table();
}

echo "<h3>Incorrect Reconciled Amounts</h3>\n";

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
    print "<tr>";
    $odd = ($rowNum % 2 > 0) ? 'odd' : 'even';
    $css = ($trans['bank.amount'] > 0.0) ? 'deposit' : "withdrawal-$odd";
    print "<td class=\"$css\" align=\"right\">" .
        (empty($trans['bank.chk_no']) ? '-' : htmlentities((string)$trans['bank.chk_no'])) . "</td>";
    print "<td class=\"$css\" align=\"right\">" .
        sprintf('%.2f', $trans['bank.amount']) . "</td>";
    print "<td class=\"$css\">" .
        date_to_str($trans['bank.date'], '__mm__/__dd__/__yyyy__', false) . "</td>";
    print "<td class=\"$css\">" .
        htmlentities($trans['bank.description']) . "</td>";
    print "<td class=\"$css\" align=\"right\">" .
        (empty($trans['my.chk_no']) ? '-' : htmlentities((string)$trans['my.chk_no'])) . "</td>";
    print "<td class=\"$css\" align=\"right\">" .
        sprintf('%.2f', $trans['my.amount']) . "</td>";
    print "<td class=\"$css\"><a href=\"$url\">" .
        date_to_str($trans['my.date'], '__mm__/__dd__/__yyyy__', false) . "</a></td>";
    print "<td class=\"$css\">" .
        htmlentities($trans['my.description']) . "</td>";
    print "<td class=\"$css\">" .
        htmlentities($trans['my.memo'] ?? '') . "</td>";
    print "</tr>\n";
}
dbi_free_result($res);
close_table();

print_trailer();

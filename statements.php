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

$Account = get_account_info($acct);

print_header(translate('Statements'));
print_heading(translate('Statements'));
print_account_info($Account);

$res = dbi_execute(
    'SELECT chk_statement_id, chk_description, chk_start_date, chk_end_date FROM chk_bank_statement WHERE chk_acct_id = ? ORDER BY chk_end_date DESC',
    [$acct]
);
if (!$res)
    fatalError('Database error: ' . dbi_error());

echo "<ul>\n";
while ($row = dbi_fetch_row($res)) {
    $stmtId = (int)$row[0];
    $out = "<a href=\"reconcile.php?acct=$acct&statement=$stmtId\">" .
        htmlentities((string)$row[1]) . "</a>: " .
        date_to_str((string)$row[2], '__mm__/__dd__/__yyyy__', false) . ' - ' .
        date_to_str((string)$row[3], '__mm__/__dd__/__yyyy__', false);

    $res2 = dbi_execute(
        'SELECT COUNT(*) FROM chk_bank_trans WHERE chk_acct_id = ? AND chk_statement_id = ? AND chk_trans_id IS NULL',
        [$acct, $stmtId]
    );
    $num_not_rec = 0;
    if ($res2 && ($row2 = dbi_fetch_row($res2))) {
        $num_not_rec = (int)$row2[0];
    }
    dbi_free_result($res2);

    $res2 = dbi_execute(
        'SELECT COUNT(*) FROM chk_bank_trans WHERE chk_acct_id = ? AND chk_statement_id = ?',
        [$acct, $stmtId]
    );
    $num_rec = 0;
    if ($res2 && ($row2 = dbi_fetch_row($res2))) {
        $num_rec = (int)$row2[0];
    }
    dbi_free_result($res2);

    if ($num_not_rec > 0)
        print '<li><b>';
    else
        print '<li>';

    if ($num_not_rec == 0) {
        $out .= " (All $num_rec reconciled)";
    } else {
        $out .= " ($num_not_rec of $num_rec not reconciled)";
        if ($num_not_rec == $num_rec) {
            $out .= ' <a onclick="return confirm(\'Are you sure you want to delete this statement?\');" href="statement_del.php?acct=' . $acct . '&statement=' . $stmtId . '">Delete</a>';
        }
    }
    if ($num_not_rec > 0)
        $out .= "</b>";
    print $out . "</li>\n";
}
dbi_free_result($res);
echo "</ul>\n";

print_trailer();

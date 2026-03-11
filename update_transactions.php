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

print_header(translate('Account') . ': ' . $Account['name']);
print_heading(translate('Account') . ': ' . $Account['name']);

update_balances($acct);

print_account_info($Account);

$res = dbi_execute(
    'SELECT chk_trans_id, chk_description FROM chk_trans WHERE chk_acct_id = ?',
    [$acct]
);
if (!$res)
    fatalError('Error in query: ' . dbi_error());

$i = 0;
while ($row = dbi_fetch_row($res)) {
    $id = $row[0];
    $name = strtoupper($row[1]);
    if (!dbi_execute('UPDATE chk_trans SET chk_description = ? WHERE chk_acct_id = ? AND chk_trans_id = ?', [$name, $acct, $id])) {
        fatalError('Error updating: ' . dbi_error());
    }
    $i++;
}
dbi_free_result($res);

echo "<p>$i records updated.</p>\n";

print_trailer();

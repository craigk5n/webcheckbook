<?php
declare(strict_types=1);

/**
 * Displays a paginated list of transactions for a specific account.
 *
 * Retrieves account information and transactions for the specified account ID,
 * displaying them in a table with pagination controls. Updated for PHP 8 best practices
 * by Grok (xAI) in September 2025. Changes include strict typing, prepared statements
 * to prevent SQL injection, consistent HTML escaping, improved error handling,
 * and removal of unused $doUpdate variable.
 *
 * @package Checkbook
 */

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

const NUM_DISPLAY = 50;

$first = getIntValue('first');
$acct = getIntValue('acct', true);
if (empty($acct)) {
    fatalError(translate('No account specified'));
}

$account = get_account_info($acct);

update_balances($acct);

$title = translate('Account') . ': ' . htmlentities($account['name']);
print_header($title);
print_heading($title);

print_account_info($account);

echo '<p><a href="edit_account.php?acct=' . $acct . '">' . translate('Edit Account') . '</a></p>';

$sql = 'SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description, chk_reconciled ' .
       'FROM chk_trans WHERE chk_acct_id = ? ORDER BY chk_date ASC';
$res = dbi_execute($sql, [$acct]);
$rows = [];
$bal = 0.0;
$bank_bal = 0.0;
$last_date = '';
if ($res) {
    while ($row = dbi_fetch_row($res)) {
        $class = $row[3] > 0 ? 'deposit' : (count($rows) % 2 === 0 ? 'withdrawal-even' : 'withdrawal-odd');
        if (empty($rows) || $row[4] !== $last_date) {
            $rows[] = '<tr><td colspan="7" style="height: 1px; background-color: #000;"></td></tr>';
        }
        $bal += (float)$row[3];
        $bank_bal += $row[6] === 'Y' ? (float)$row[3] : 0.0;
        $rows[] = sprintf(
            '<tr>' .
            '<td class="%s"><a href="edit_trans.php?acct=%d&trans=%d">%s</a></td>' .
            '<td class="%s">%s</td>' .
            '<td class="%s">%s</td>' .
            '<td class="%s" align="right">%.2f</td>' .
            '<td class="%s" align="right">%.2f</td>' .
            '<td class="%s" align="right"><img src="%s" alt="rec" /></td>' .
            '<td class="%s" align="right">%.2f</td>' .
            '</tr>',
            $class, $acct, (int)$row[0], date_to_str((string)$row[4], '__mm__/__dd__/__yyyy__', false),
            $class, empty($row[2]) ? '-' : htmlentities((string)$row[2]),
            $class, htmlentities((string)$row[5]),
            $class, (float)$row[3],
            $class, $bal,
            $class, $row[6] === 'Y' ? 'images/reconciled.png' : 'images/not_reconciled.png',
            $class, $bank_bal
        );
        $last_date = (string)$row[4];
    }
    dbi_free_result($res);
} else {
    fatalError(translate('Database error') . ': Unable to retrieve transactions.');
}

$rows[] = '<tr><td colspan="7" style="height: 1px; background-color: #000;"></td></tr>';

$first = max((int)$first, 0);
$first = empty($first) ? max(count($rows) - NUM_DISPLAY, 0) : $first;
$display_rows = array_slice($rows, $first, NUM_DISPLAY);

open_table(['Date', 'Chk#', 'Comment', 'Amount', 'Balance', ' ', 'Bank Bal']);
foreach ($display_rows as $row) {
    echo $row . "\n";
}
close_table();

?>

<form action="list.php" method="GET">
    <input type="hidden" name="acct" value="<?php echo htmlspecialchars((string)$acct); ?>" />
    <input type="hidden" name="first" value="<?php echo htmlspecialchars((string)max($first - NUM_DISPLAY, 0)); ?>" />
    <input type="submit" value="<?php echo translate('Previous 100'); ?>" />
</form>

<?php
print_trailer();
?>
<?php
declare(strict_types=1);

/**
 * Displays a paginated list of transactions for a specific account.
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

echo '<p><a class="btn btn-sm btn-outline-secondary" href="edit_account.php?acct=' . $acct . '">' . translate('Edit Account') . '</a></p>';

$sql = 'SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description, chk_reconciled ' .
       'FROM chk_trans WHERE chk_acct_id = ? ORDER BY chk_date ASC';
$res = dbi_execute($sql, [$acct]);
$rows = [];
$bal = 0.0;
$bank_bal = 0.0;
$last_date = '';
if ($res) {
    while ($row = dbi_fetch_row($res)) {
        $rowClass = $row[3] > 0 ? 'table-success' : '';
        if (empty($rows) || $row[4] !== $last_date) {
            $rows[] = '<tr><td colspan="7" class="p-0" style="height: 2px; background-color: #dee2e6;"></td></tr>';
        }
        $bal += (float)$row[3];
        $bank_bal += $row[6] === 'Y' ? (float)$row[3] : 0.0;
        $reconciled = ($row[6] === 'Y');
        $badge = $reconciled
            ? '<span class="badge bg-success">R</span>'
            : '<span class="badge bg-secondary">-</span>';
        $rows[] = sprintf(
            '<tr class="%s">' .
            '<td><a href="edit_trans.php?acct=%d&trans=%d">%s</a></td>' .
            '<td>%s</td>' .
            '<td>%s</td>' .
            '<td class="text-end">%.2f</td>' .
            '<td class="text-end">%.2f</td>' .
            '<td>%s</td>' .
            '<td class="text-end">%.2f</td>' .
            '</tr>',
            $rowClass, $acct, (int)$row[0], date_to_str((string)$row[4], '__mm__/__dd__/__yyyy__', false),
            empty($row[2]) ? '-' : htmlentities((string)$row[2]),
            htmlentities((string)$row[5]),
            (float)$row[3],
            $bal,
            $badge,
            $bank_bal
        );
        $last_date = (string)$row[4];
    }
    dbi_free_result($res);
} else {
    fatalError(translate('Database error') . ': Unable to retrieve transactions.');
}

$rows[] = '<tr><td colspan="7" class="p-0" style="height: 2px; background-color: #dee2e6;"></td></tr>';

$first = max((int)$first, 0);
$first = empty($first) ? max(count($rows) - NUM_DISPLAY, 0) : $first;
$display_rows = array_slice($rows, $first, NUM_DISPLAY);

open_table(['Date', 'Chk#', 'Comment', 'Amount', 'Balance', ' ', 'Bank Bal']);
foreach ($display_rows as $row) {
    echo $row . "\n";
}
close_table();

// Pagination
$totalRows = count($rows);
$prevFirst = max($first - NUM_DISPLAY, 0);
$nextFirst = min($first + NUM_DISPLAY, $totalRows - 1);
$hasPrev = ($first > 0);
$hasNext = ($first + NUM_DISPLAY < $totalRows);

echo '<nav><ul class="pagination">';
if ($hasPrev) {
    echo '<li class="page-item"><a class="page-link" href="list.php?acct=' . $acct . '&first=0">First</a></li>';
    echo '<li class="page-item"><a class="page-link" href="list.php?acct=' . $acct . '&first=' . $prevFirst . '">Previous</a></li>';
} else {
    echo '<li class="page-item disabled"><span class="page-link">First</span></li>';
    echo '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
}
if ($hasNext) {
    echo '<li class="page-item"><a class="page-link" href="list.php?acct=' . $acct . '&first=' . $nextFirst . '">Next</a></li>';
    echo '<li class="page-item"><a class="page-link" href="list.php?acct=' . $acct . '&first=' . max($totalRows - NUM_DISPLAY, 0) . '">Last</a></li>';
} else {
    echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    echo '<li class="page-item disabled"><span class="page-link">Last</span></li>';
}
echo '</ul></nav>';

print_trailer();
?>

<?php
declare(strict_types=1);

/**
 * Displays a list of accounts in the checkbook application.
 *
 * Retrieves all accounts from the chk_account table and presents them in a table
 * with links to view transactions. Updated for PHP 8 best practices by Grok (xAI)
 * in September 2025.
 *
 * @package Checkbook
 */

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

// Get account info using prepared statement
$sql = 'SELECT chk_acct_id, chk_bank, chk_name, chk_account_no, chk_balance, chk_bank_balance ' .
       'FROM chk_account ORDER BY chk_acct_id';
$res = dbi_execute($sql, []);
$accounts = [];
if ($res) {
    while ($row = dbi_fetch_row($res)) {
        $accounts[] = [
            'acct_id' => (int)$row[0],
            'bank' => (string)$row[1],
            'name' => (string)$row[2],
            'account_no' => (string)$row[3],
            'balance' => (float)$row[4],
            'bank_balance' => (float)$row[5],
        ];
    }
    dbi_free_result($res);
} else {
    fatalError(translate('Database error') . ': Unable to retrieve accounts.');
}

$title = translate('Accounts');
print_header($title);
print_heading($title);

open_table([
    translate('Bank'),
    translate('Acct Name'),
    translate('Acct No'),
    translate('Balance'),
    translate('Bank Bal'),
]);

foreach ($accounts as $account) {
    echo '<tr>';
    echo sprintf(
        '<td><a href="list.php?acct=%d">%s</a></td>',
        $account['acct_id'],
        htmlspecialchars($account['bank'])
    );
    print_table_cell(htmlspecialchars($account['name']));
    print_table_cell(htmlspecialchars($account['account_no']));
    print_table_cell(sprintf('%.2f', $account['balance']));
    print_table_cell(sprintf('%.2f', $account['bank_balance']));
    echo "</tr>\n";
}

close_table();
print_trailer();
?>
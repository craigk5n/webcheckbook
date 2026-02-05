<?php
declare(strict_types=1);

/**
 * Displays a list of all accounts in the checkbook application.
 *
 * Retrieves account information from the chk_account table and displays it in a table
 * with columns for bank, account name, account number, start date, end date, balance,
 * and bank balance. Updated for PHP 8 best practices by Grok (xAI) in September 2025.
 * Changes include using prepared statements, strict typing, improved error handling,
 * consistent HTML escaping, and removal of unused multi_sort function and debug code.
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
$sql = 'SELECT chk_acct_id, chk_bank, chk_name, chk_account_no, ' .
       'chk_balance, chk_bank_balance ' .
       'FROM chk_account ORDER BY chk_is_archived, chk_acct_id';
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
    fatalError('Database error: Unable to retrieve account information.');
}

// Fetch first and last transaction dates for each account
foreach ($accounts as &$account) {
    $sql = 'SELECT MIN(chk_date), MAX(chk_date) FROM chk_trans WHERE chk_acct_id = ?';
    $res = dbi_execute($sql, [$account['acct_id']]);
    if ($res) {
        if ($row = dbi_fetch_row($res)) {
            $account['start_date'] = (string)($row[0] ?? '');
            $account['end_date'] = (string)($row[1] ?? '');
        }
        dbi_free_result($res);
    }
}
unset($account); // Clean up reference

print_header(translate('Accounts'));
print_heading(translate('Accounts'));

open_table(['Bank', 'Acct Name', 'Acct No', 'Start Date', 'End Date', 'Balance', 'Bank Bal']);
foreach ($accounts as $account) {
    echo "<tr>\n";
    print_table_cell(htmlentities($account['bank']));
    echo '<td><a href="list.php?acct=' . $account['acct_id'] . '">' . htmlentities($account['name']) . "</a></td>\n";
    print_table_cell(htmlentities($account['account_no']));
    print_table_cell($account['start_date'] ? date_to_str($account['start_date'], '__mm__/__dd__/__yyyy__', false) : '');
    print_table_cell($account['end_date'] ? date_to_str($account['end_date'], '__mm__/__dd__/__yyyy__', false) : '');
    print_table_cell(sprintf('%.02f', $account['balance']));
    print_table_cell(sprintf('%.02f', $account['bank_balance']));
    echo "</tr>\n";
}
close_table();

print_trailer();
?>
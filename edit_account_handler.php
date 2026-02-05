<?php
declare(strict_types=1);

/**
 * Handles the submission of the account edit form in the checkbook application.
 *
 * Updates the chk_account table with submitted form data (bank, name, account number,
 * and archived status) and redirects to the account’s transaction list. Updated for PHP 8
 * best practices by Grok (xAI) in September 2025.
 *
 * @package Checkbook
 */

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

$acct = getIntValue('acct', true);
if (empty($acct)) {
    fatalError(translate('No account specified'));
}

// Validate form inputs
$bank = getValue('bank');
$name = getValue('name');
$account_no = getValue('account_no');
$is_archived = getValue('is_archived') === 'Y' ? 'Y' : 'N';

// Basic validation for non-empty and length constraints
if (empty($bank) || strlen($bank) > 100) {
    fatalError(translate('Invalid bank name'));
}
if (empty($name) || strlen($name) > 100) {
    fatalError(translate('Invalid account name'));
}
if (strlen($account_no) > 50) {
    fatalError(translate('Invalid account number'));
}

// Update account in database
$sql = 'UPDATE chk_account SET chk_bank = ?, chk_name = ?, chk_account_no = ?, chk_is_archived = ? ' .
       'WHERE chk_acct_id = ?';
if (!dbi_execute($sql, [$bank, $name, $account_no, $is_archived, $acct])) {
    fatalError(translate('Database error') . ': Unable to update account information.');
}

update_balances($acct);

do_redirect(sprintf('list.php?acct=%d', $acct));
?>
<?php
declare(strict_types=1);

/**
 * Handles the submission of the transaction edit form in the checkbook application.
 *
 * Updates the chk_trans table with submitted form data (date, type, check number,
 * description, and amount) and redirects to the account’s transaction list. Updated
 * for PHP 8 best practices by Grok (xAI) in September 2025.
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
$trans = getIntValue('trans', true);
if (empty($acct)) {
    fatalError(translate('No account specified'));
}
if (empty($trans)) {
    fatalError(translate('No transaction specified'));
}

// Validate form inputs
$date = getValue('date');
$type = (int) getValue('type');
$num = getValue('num');
$description = getValue('description');
$amount = (float) getValue('amount');

// Validate date format (mm/dd/yyyy or mm/dd/yy)
if (!preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $date, $dateAr)) {
    fatalError(translate('Invalid date format'));
}
$month = (int) $dateAr[1];
$day = (int) $dateAr[2];
$year = (int) $dateAr[3];
if ($year < 100) {
    $year += 2000;
}
if (!checkdate($month, $day, $year)) {
    fatalError(translate('Invalid date'));
}
$dateStr = sprintf('%04d%02d%02d', $year, $month, $day);

// Validate transaction type
if (!in_array($type, [1, 2, 3, 4], true)) {
    fatalError(translate('Invalid transaction type'));
}

// Handle check number (NULL for non-check types)
$num = ($type === 3 && !empty($num)) ? $num : null;

// Validate description and amount
if (strlen($description) > 100) {
    fatalError(translate('Description too long'));
}
$description = strtoupper($description); // Preserve original behavior
if ($amount < -1000000 || $amount > 1000000) {
    fatalError(translate('Invalid amount'));
}
if ($type !== 1) {
    $amount = -$amount; // Non-deposit transactions are negative
}

// Update transaction in database
$sql = 'UPDATE chk_trans SET chk_type = ?, chk_no = ?, chk_amount = ?, chk_date = ?, chk_description = ? ' .
       'WHERE chk_acct_id = ? AND chk_trans_id = ?';
if (!dbi_execute($sql, [$type, $num, $amount, $dateStr, $description, $acct, $trans])) {
    fatalError(translate('Database error') . ': Unable to update transaction.');
}

update_balances($acct);

do_redirect(sprintf('list.php?acct=%d', $acct));
?>
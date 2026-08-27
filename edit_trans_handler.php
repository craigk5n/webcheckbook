<?php
declare(strict_types=1);

/**
 * Handles the submission of the transaction edit form in the checkbook application.
 *
 * Updates the chk_trans table with submitted form data (date, type, check number,
 * description, and amount) and redirects to the account's transaction list.
 *
 * A reconciled transaction is handled differently: only its date and check number
 * may change, and the change has to be confirmed first.  The edit form confirms
 * in a modal; if that confirmation is missing (no JavaScript, or a direct POST)
 * this handler renders a confirmation page instead of saving.
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

// Load the stored transaction so we know whether it is reconciled and which of
// the submitted values we are allowed to apply.
$sql = 'SELECT chk_type, chk_no, chk_amount, chk_date, chk_description, chk_reconciled ' .
       'FROM chk_trans WHERE chk_acct_id = ? AND chk_trans_id = ?';
$res = dbi_execute($sql, [$acct, $trans]);
if (!$res) {
    fatalError(translate('Database error') . ': Unable to retrieve transaction information.');
}
$row = dbi_fetch_row($res);
if (!$row) {
    dbi_free_result($res);
    fatalError(translate('No such transaction: ') . $trans);
}
$stored = [
    'type' => (int)$row[0],
    'num' => $row[1] !== null ? (string)$row[1] : '',
    'amount' => (float)$row[2],
    'date' => (string)$row[3],
    'description' => (string)$row[4],
    'reconciled' => (string)$row[5],
];
dbi_free_result($res);

$isReconciled = $stored['reconciled'] === 'Y';

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

// Check numbers are stored as integers
$num = trim($num);
if ($num !== '' && !ctype_digit($num)) {
    fatalError(translate('Check number must be a number'));
}

if ($isReconciled) {
    // Only the date and check number may change; everything else keeps the
    // value it had when the transaction was reconciled.
    $changes = describe_reconciled_changes(
        ['date' => $stored['date'], 'num' => $stored['num']],
        ['date' => $dateStr, 'num' => $num]
    );

    if (empty($changes)) {
        do_redirect(sprintf('list.php?acct=%d', $acct));
    }

    if (getValue('confirm') !== '1') {
        print_reconciled_confirmation($acct, $trans, $dateStr, $num, $changes);
        exit;
    }

    $sql = 'UPDATE chk_trans SET chk_no = ?, chk_date = ? WHERE chk_acct_id = ? AND chk_trans_id = ?';
    if (!dbi_execute($sql, [$num === '' ? null : (int)$num, $dateStr, $acct, $trans])) {
        fatalError(translate('Database error') . ': Unable to update transaction.');
    }
} else {
    // Validate transaction type
    if (!in_array($type, [1, 2, 3, 4], true)) {
        fatalError(translate('Invalid transaction type'));
    }

    // Handle check number (NULL for non-check types)
    $num = ($type === 3 && $num !== '') ? $num : null;

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
}

update_balances($acct);

do_redirect(sprintf('list.php?acct=%d', $acct));

/**
 * Renders the confirmation page shown before a reconciled transaction is changed.
 *
 * This is the fallback for submissions that arrive without the edit form's modal
 * confirmation.  It re-posts the same values along with the confirmation flag.
 *
 * @param int $acct The account id.
 * @param int $trans The transaction id.
 * @param string $dateStr The submitted date in YYYYMMDD format.
 * @param string $num The submitted check number ('' for none).
 * @param array $changes Changes as returned by describe_reconciled_changes().
 */
function print_reconciled_confirmation(int $acct, int $trans, string $dateStr, string $num, array $changes): void
{
    $account = get_account_info($acct);
    $bankTrans = get_matched_bank_trans($acct, $trans);

    $title = translate('Change a reconciled transaction?');
    print_header($title);
    print_heading($title);
    print_account_info($account);

    echo '<div class="card border-warning mb-3" style="max-width: 44rem;">' . "\n";
    echo '<div class="card-body">' . "\n";
    echo '<p><i class="bi bi-exclamation-triangle-fill text-warning me-1" aria-hidden="true"></i>' .
        translate('This transaction was already matched to a bank statement. Saving these changes will make it differ from what the bank reported.') .
        "</p>\n";

    echo '<ul class="list-group list-group-flush mb-3">' . "\n";
    foreach ($changes as $change) {
        echo '<li class="list-group-item d-flex justify-content-between align-items-center px-0">' .
            '<span class="fw-semibold">' . htmlentities($change['label']) . '</span>' .
            '<span><span class="text-muted text-decoration-line-through">' . htmlentities($change['from']) . '</span>' .
            ' <i class="bi bi-arrow-right mx-1" aria-hidden="true"></i> ' .
            '<span class="fw-semibold">' . htmlentities($change['to']) . '</span></span></li>' . "\n";
    }
    echo "</ul>\n";

    if ($bankTrans !== null) {
        echo '<p class="small text-muted"><strong>' . translate('Bank reported') . ':</strong> ' .
            htmlentities(format_bank_trans_summary($bankTrans)) . "</p>\n";
    }

    echo '<form action="edit_trans_handler.php" method="POST" class="d-flex gap-2">' . "\n";
    printf('<input type="hidden" name="acct" value="%d" />' . "\n", $acct);
    printf('<input type="hidden" name="trans" value="%d" />' . "\n", $trans);
    printf('<input type="hidden" name="date" value="%s" />' . "\n",
        htmlspecialchars(date_to_str($dateStr, '__mm__/__dd__/__yyyy__', false)));
    printf('<input type="hidden" name="num" value="%s" />' . "\n", htmlspecialchars($num));
    echo '<input type="hidden" name="confirm" value="1" />' . "\n";
    echo '<button type="submit" class="btn btn-warning">' . translate('Save changes anyway') . "</button>\n";
    printf('<a class="btn btn-outline-secondary" href="edit_trans.php?acct=%d&amp;trans=%d">%s</a>' . "\n",
        $acct, $trans, translate('Cancel'));
    echo "</form>\n";

    echo "</div>\n</div>\n";

    print_trailer();
}

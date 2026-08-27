<?php
declare(strict_types=1);

/**
 * Handles deletion of a transaction in the checkbook application.
 *
 * Deleting requires an explicit confirmation.  The edit page confirms in a
 * modal; if that confirmation is missing (no JavaScript, or a direct POST) this
 * handler renders a confirmation page instead of deleting.
 *
 * If the transaction was reconciled, the bank statement line it was matched to
 * is unlinked first so the statement shows it as unreconciled again rather than
 * pointing at a row that no longer exists.
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

if (getValue('confirm') !== '1') {
    print_delete_confirmation($acct, $trans, $stored);
    exit;
}

// Release the bank statement line first so it is reconcilable again.
if (!dbi_execute('UPDATE chk_bank_trans SET chk_trans_id = NULL WHERE chk_acct_id = ? AND chk_trans_id = ?', [$acct, $trans])) {
    fatalError(translate('Database error') . ': Unable to unlink the bank statement line.');
}

if (!dbi_execute('DELETE FROM chk_trans WHERE chk_acct_id = ? AND chk_trans_id = ?', [$acct, $trans])) {
    fatalError(translate('Database error') . ': Unable to delete transaction.');
}

update_balances($acct);

do_redirect(sprintf('list.php?acct=%d', $acct));

/**
 * Renders the confirmation page shown before a transaction is deleted.
 *
 * @param int $acct The account id.
 * @param int $trans The transaction id.
 * @param array $stored The transaction being deleted.
 */
function print_delete_confirmation(int $acct, int $trans, array $stored): void
{
    $account = get_account_info($acct);
    $bankTrans = get_matched_bank_trans($acct, $trans);

    $title = translate('Delete this transaction?');
    print_header($title);
    print_heading($title);
    print_account_info($account);

    echo '<div class="card border-danger mb-3" style="max-width: 44rem;">' . "\n";
    echo '<div class="card-body">' . "\n";
    echo '<p><i class="bi bi-exclamation-octagon-fill text-danger me-1" aria-hidden="true"></i>' .
        translate('This cannot be undone.') . "</p>\n";

    echo '<dl class="row mb-3">' . "\n";
    $fields = [
        translate('Date') => date_to_str($stored['date'], '__mm__/__dd__/__yyyy__', false),
        translate('ChkNo') => $stored['num'] === '' ? translate('(none)') : $stored['num'],
        translate('Description') => $stored['description'],
        translate('Amount') => sprintf('%.2f', $stored['amount']),
    ];
    foreach ($fields as $label => $value) {
        echo '<dt class="col-sm-3">' . htmlentities($label) . '</dt>' .
            '<dd class="col-sm-9">' . htmlentities($value) . "</dd>\n";
    }
    echo "</dl>\n";

    if ($stored['reconciled'] === 'Y') {
        echo '<div class="alert alert-warning"><strong>' . translate('This transaction is reconciled') . '.</strong> ' .
            translate('The bank statement line it was matched to will go back to unreconciled so you can match it again.');
        if ($bankTrans !== null) {
            echo '<br><span class="small">' . htmlentities(format_bank_trans_summary($bankTrans)) . '</span>';
        }
        echo "</div>\n";
    }

    echo '<form action="delete_trans_handler.php" method="POST" class="d-flex gap-2">' . "\n";
    printf('<input type="hidden" name="acct" value="%d" />' . "\n", $acct);
    printf('<input type="hidden" name="trans" value="%d" />' . "\n", $trans);
    echo '<input type="hidden" name="confirm" value="1" />' . "\n";
    echo '<button type="submit" class="btn btn-danger">' . translate('Delete transaction') . "</button>\n";
    printf('<a class="btn btn-outline-secondary" href="edit_trans.php?acct=%d&amp;trans=%d">%s</a>' . "\n",
        $acct, $trans, translate('Cancel'));
    echo "</form>\n";

    echo "</div>\n</div>\n";

    print_trailer();
}

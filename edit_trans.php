<?php
declare(strict_types=1);

/**
 * Displays a form to edit a transaction in the checkbook application.
 *
 * Retrieves account and transaction information for the specified account and transaction IDs,
 * presenting a form to edit date, type, check number, description, and amount. Updated for PHP 8
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
$trans = getIntValue('trans', true);
if (empty($acct)) {
    fatalError(translate('No account specified'));
}
if (empty($trans)) {
    fatalError(translate('No transaction specified'));
}

// Get account info using prepared statement
$sql = 'SELECT chk_bank, chk_name, chk_account_no, chk_balance, chk_bank_balance ' .
       'FROM chk_account WHERE chk_acct_id = ?';
$res = dbi_execute($sql, [$acct]);
$account = [];
if ($res) {
    if ($row = dbi_fetch_row($res)) {
        $account = [
            'acct_id' => $acct,
            'bank' => (string)$row[0],
            'name' => (string)$row[1],
            'account_no' => (string)$row[2],
            'balance' => (float)$row[3],
            'bank_balance' => (float)$row[4],
        ];
        dbi_free_result($res);
    } else {
        fatalError(translate('No such account: ') . $acct);
    }
} else {
    fatalError(translate('Database error') . ': Unable to retrieve account information.');
}

// Get first and last transaction date
$sql = 'SELECT MIN(chk_date), MAX(chk_date) FROM chk_trans WHERE chk_acct_id = ?';
$res = dbi_execute($sql, [$acct]);
if ($res) {
    if ($row = dbi_fetch_row($res)) {
        $account['start_date'] = (string)($row[0] ?? '');
        $account['end_date'] = (string)($row[1] ?? '');
    }
    dbi_free_result($res);
}

// Get transaction info using prepared statement
$sql = 'SELECT chk_type, chk_no, chk_amount, chk_date, chk_description, chk_reconciled ' .
       'FROM chk_trans WHERE chk_acct_id = ? AND chk_trans_id = ?';
$res = dbi_execute($sql, [$acct, $trans]);
$transaction = [];
if ($res) {
    if ($row = dbi_fetch_row($res)) {
        $transaction = [
            'type' => (int)$row[0],
            'num' => $row[1] !== null ? (string)$row[1] : '',
            'amount' => (float)$row[2],
            'date' => (string)$row[3],
            'description' => (string)$row[4],
            'reconciled' => (string)$row[5],
        ];
        // Adjust amount for non-deposit transactions
        if ($transaction['type'] !== 1) {
            $transaction['amount'] = -$transaction['amount'];
        }
        dbi_free_result($res);
    } else {
        fatalError(translate('No such transaction: ') . $trans);
    }
} else {
    fatalError(translate('Database error') . ': Unable to retrieve transaction information.');
}

$title = translate('Account') . ': ' . htmlentities($account['name']);
print_header($title);
print_heading($title);
print_account_info($account);

?>

<form action="edit_trans_handler.php" method="POST">
    <input type="hidden" name="acct" value="<?php echo htmlspecialchars((string)$account['acct_id']); ?>" />
    <input type="hidden" name="trans" value="<?php echo htmlspecialchars((string)$trans); ?>" />

    <table border="0">
        <tr>
            <th><?php echo translate('Date'); ?></th>
            <th><?php echo translate('Type'); ?></th>
            <th><?php echo translate('ChkNo'); ?></th>
            <th><?php echo translate('Description'); ?></th>
            <th><?php echo translate('Amount'); ?></th>
        </tr>
        <tr>
            <td><input size="11" name="date" value="<?php echo htmlspecialchars(date_to_str($transaction['date'], '__mm__/__dd__/__yyyy__', false)); ?>" /></td>
            <td>
                <select name="type">
                    <option value="2" <?php echo $transaction['type'] === 2 ? 'selected' : ''; ?>><?php echo translate('Debit'); ?></option>
                    <option value="3" <?php echo $transaction['type'] === 3 ? 'selected' : ''; ?>><?php echo translate('Check'); ?></option>
                    <option value="4" <?php echo $transaction['type'] === 4 ? 'selected' : ''; ?>><?php echo translate('Charge/Fee'); ?></option>
                    <option value="1" <?php echo $transaction['type'] === 1 ? 'selected' : ''; ?>><?php echo translate('Deposit'); ?></option>
                </select>
            </td>
            <td><input size="7" name="num" value="<?php echo htmlspecialchars($transaction['num']); ?>" /></td>
            <td><input size="40" name="description" value="<?php echo htmlspecialchars($transaction['description']); ?>" /></td>
            <td><input size="8" name="amount" value="<?php echo htmlspecialchars(sprintf('%.2f', $transaction['amount'])); ?>" /></td>
        </tr>
    </table>

    <?php if ($transaction['reconciled'] === 'Y'): ?>
        <p><b><?php echo translate('NOTE'); ?>:</b> <?php echo translate('You cannot edit a transaction that has been reconciled.'); ?></p>
    <?php else: ?>
        <input type="submit" value="<?php echo translate('Save'); ?>" />
    <?php endif; ?>
</form>

<?php
print_trailer();
?>
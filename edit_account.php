<?php
declare(strict_types=1);

/**
 * Displays a form to edit account details in the checkbook application.
 *
 * Retrieves account information for the specified account ID and presents a form
 * to edit bank, account name, account number, and archived status. Updated for PHP 8
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

// Get account info using prepared statement
$sql = 'SELECT chk_bank, chk_name, chk_account_no, chk_balance, chk_bank_balance, chk_is_archived ' .
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
            'is_archived' => (string)($row[5] ?? 'N'),
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

$title = translate('Account') . ': ' . htmlentities($account['name']);
print_header($title);
print_heading($title);

?>

<form action="edit_account_handler.php" method="POST">
    <input type="hidden" name="acct" value="<?php echo htmlspecialchars((string)$account['acct_id']); ?>" />

    <table border="0">
        <tr>
            <td><b><?php echo translate('Bank'); ?>:</b></td>
            <td><input size="25" name="bank" value="<?php echo htmlspecialchars($account['bank']); ?>" /></td>
        </tr>
        <tr>
            <td><b><?php echo translate('Account Name'); ?>:</b></td>
            <td><input size="25" name="name" value="<?php echo htmlspecialchars($account['name']); ?>" /></td>
        </tr>
        <tr>
            <td><b><?php echo translate('Account No.'); ?>:</b></td>
            <td><input size="25" name="account_no" value="<?php echo htmlspecialchars($account['account_no']); ?>" /></td>
        </tr>
        <tr>
            <td><b><?php echo translate('Status'); ?>:</b></td>
            <td>
                <input type="radio" name="is_archived" value="N" <?php echo $account['is_archived'] === 'N' ? 'checked' : ''; ?> /> <?php echo translate('Active'); ?>
                &nbsp;&nbsp;
                <input type="radio" name="is_archived" value="Y" <?php echo $account['is_archived'] === 'Y' ? 'checked' : ''; ?> /> <?php echo translate('Archived'); ?>
            </td>
        </tr>
    </table>

    <input type="submit" value="<?php echo translate('Save'); ?>" />
</form>

<?php
print_trailer();
?>
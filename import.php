<?php
declare(strict_types=1);

/**
 * Displays a form to import transactions from a CSV file in the checkbook application.
 *
 * Allows users to upload a CSV file with transaction data for a specified account.
 * Updated for PHP 8 best practices by Grok (xAI) in September 2025.
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

$title = translate('Import');
print_header($title);
print_heading($title);
print_account_info($account);

?>

<form action="import_handler.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="acct" value="<?php echo htmlspecialchars((string)$account['acct_id']); ?>" />
    <table style="border: 0;">
        <tr>
            <td style="font-weight: bold;"><?php echo translate('Description'); ?>:</td>
            <td><input type="text" size="30" name="description" /></td>
        </tr>
        <tr>
            <td style="font-weight: bold;"><?php echo translate('CSV File'); ?>:</td>
            <td><input type="file" name="FileName" size="45" maxlength="50" accept=".csv" /></td>
        </tr>
        <tr>
            <td colspan="2"><input type="submit" value="<?php echo translate('Import'); ?>" /></td>
        </tr>
    </table>
</form>

<?php
print_trailer();
?>
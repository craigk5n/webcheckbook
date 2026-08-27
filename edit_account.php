<?php
declare(strict_types=1);

/**
 * Displays a form to edit account details in the checkbook application.
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

    <div class="row mb-3">
        <label for="bank" class="col-sm-2 col-form-label"><?php echo translate('Bank'); ?>:</label>
        <div class="col-sm-6">
            <input type="text" class="form-control" id="bank" name="bank" value="<?php echo htmlspecialchars($account['bank']); ?>" />
        </div>
    </div>
    <div class="row mb-3">
        <label for="name" class="col-sm-2 col-form-label"><?php echo translate('Account Name'); ?>:</label>
        <div class="col-sm-6">
            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($account['name']); ?>" />
        </div>
    </div>
    <div class="row mb-3">
        <label for="account_no" class="col-sm-2 col-form-label"><?php echo translate('Account No.'); ?>:</label>
        <div class="col-sm-6">
            <input type="text" class="form-control" id="account_no" name="account_no" value="<?php echo htmlspecialchars($account['account_no']); ?>" />
        </div>
    </div>
    <div class="row mb-3">
        <label class="col-sm-2 col-form-label"><?php echo translate('Status'); ?>:</label>
        <div class="col-sm-6">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="is_archived" id="statusActive" value="N" <?php echo $account['is_archived'] === 'N' ? 'checked' : ''; ?> />
                <label class="form-check-label" for="statusActive"><?php echo translate('Active'); ?></label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="is_archived" id="statusArchived" value="Y" <?php echo $account['is_archived'] === 'Y' ? 'checked' : ''; ?> />
                <label class="form-check-label" for="statusArchived"><?php echo translate('Archived'); ?></label>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6 offset-sm-2">
            <button type="submit" class="btn btn-primary"><?php echo translate('Save'); ?></button>
        </div>
    </div>
</form>

<?php
print_trailer();
?>
